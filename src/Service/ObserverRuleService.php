<?php

/**
 * Observer Rule Service for Stage 1
 * 
 * Handles rule-based filtering for Observer entities with graceful fallback
 */

namespace App\Service;

use App\Entity\Observer;
use App\Exception\InvalidRuleConfigurationException;
use App\Repository\GeoObjectRepository;
use App\Service\Rule\RuleFactoryInterface;
use App\Service\Rule\RuleValidatorInterface;
use App\Service\Rule\StatefulRuleInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Observer Rule Service
 * 
 * Integrated approach: handles rule creation, state management, validation and application
 * in a single optimized flow without duplication
 */
class ObserverRuleService
{
    public function __construct(
        private GeoObjectRepository $geoObjectRepository,
        private RuleFactoryInterface $ruleFactory,
        private RuleValidatorInterface $configValidator,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get filtered geo objects for observer
     * 
     * Integrated approach: processes all rules in single pass - creation, state, validation, application
     * 
     * @param Observer $observer Observer entity with potential rules configuration
     * @return array Array of GeoObject entities
     */
    public function getFilteredGeoObjects(Observer $observer): array
    {
        $rulesConfig = $observer->getRules();
        
        // If no rules configured, use default behavior
        if (empty($rulesConfig)) {
            return $this->getDefaultGeoObjects($observer);
        }

        try {
            // Single integrated processing loop
            $processedRules = [];
            $updatedConfig = $rulesConfig;
            $configChanged = false;
            
            // Process each rule: create -> state -> validate -> prepare
            foreach ($rulesConfig as $ruleName => $config) {
                try {
                    // 1. Create rule instance ONCE
                    $rule = $this->ruleFactory->getRule($ruleName);
                    
                    if (!$rule) {
                        $this->logger->warning("Rule not found: $ruleName");
                        continue;
                    }
                    
                    // 2. Process state (if stateful)
                    if ($rule instanceof StatefulRuleInterface) {
                        [$config, $stateChanged] = $this->processRuleState($rule, $config);
                        
                        if ($stateChanged) {
                            $updatedConfig[$ruleName] = $config;
                            $configChanged = true;
                        }
                    }
                    
                    // 3. Validate configuration
                    $this->validateRuleConfig($rule, $config);
                    
                    // 4. Add to processed rules
                    $processedRules[] = [
                        'rule' => $rule,
                        'config' => $config,
                        'name' => $ruleName
                    ];
                    
                } catch (\Exception $e) {
                    $this->logger->error("Failed to process rule: $ruleName", [
                        'error' => $e->getMessage()
                    ]);
                    // Continue with other rules
                }
            }
            
            // 5. Save configuration changes if any
            if ($configChanged) {
                $this->saveObserverConfiguration($observer, $updatedConfig);
            }
            
            // 6. Apply all processed rules
            $this->logger->debug('Rules processed successfully', [
                'observer' => $observer->getName(),
                'rules_count' => count($processedRules)
            ]);
            
            return $this->applyProcessedRules($observer, $processedRules);
            
        } catch (InvalidRuleConfigurationException $e) {
            $this->logValidationError($observer, $rulesConfig, $e);
            return $this->getDefaultGeoObjects($observer);
        }
    }

    /**
     * Get default geo objects for observer (fallback behavior)
     * 
     * @param Observer $observer Observer entity
     * @return array Array of GeoObject entities
     */
    private function getDefaultGeoObjects(Observer $observer): array
    {
        return $this->geoObjectRepository->findActiveByMap($observer->getMap());
    }

    /**
     * Process rule state (initialize or update)
     * 
     * @param StatefulRuleInterface $rule Rule instance
     * @param array $config Rule configuration
     * @return array [updated_config, state_changed]
     */
    private function processRuleState(StatefulRuleInterface $rule, array $config): array
    {
        $stateChanged = false;
        
        // Initialize state on first use
        if (!isset($config['_state'])) {
            $config['_state'] = $rule->initializeRuleState($config);
            $stateChanged = true;
            
            $this->logger->info('Rule state initialized', [
                'rule' => $rule->getName(),
                'initial_state' => $config['_state']
            ]);
        }
        
        // Update state
        $newState = $rule->updateRuleState($config);
        if ($newState !== $config['_state']) {
            $config['_state'] = $newState;
            $stateChanged = true;
            
            $this->logger->debug('Rule state updated', [
                'rule' => $rule->getName(),
                'new_state' => $newState
            ]);
        }
        
        return [$config, $stateChanged];
    }
    
    /**
     * Validate rule configuration using JSON Schema
     * 
     * @param \App\Service\Rule\RuleInterface $rule Rule instance
     * @param array $config Rule configuration
     * @throws InvalidRuleConfigurationException If validation fails
     */
    private function validateRuleConfig($rule, array $config): void
    {
        // Create temporary config for validation
        $tempConfig = [$rule->getName() => $config];
        
        // Build schema for this specific rule
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                $rule->getName() => $rule::getConfigSchema()
            ],
            'additionalProperties' => false
        ];
        
        $errors = $this->configValidator->validateWithSchema($tempConfig, $schema);
        
        if (!empty($errors)) {
            throw new InvalidRuleConfigurationException(
                "Rule configuration invalid: " . $rule->getName(),
                $errors
            );
        }
    }
    
    /**
     * Save updated observer configuration
     * 
     * @param Observer $observer Observer entity
     * @param array $config Updated rules configuration
     */
    private function saveObserverConfiguration(Observer $observer, array $config): void
    {
        try {
            $this->entityManager->beginTransaction();
            
            // Refresh observer to prevent race conditions
            $this->entityManager->refresh($observer);
            $observer->setRules($config);
            $this->entityManager->flush();
            $this->entityManager->commit();
            
            $this->logger->info('Observer configuration updated', [
                'observer' => $observer->getName()
            ]);
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to save observer configuration', [
                'observer' => $observer->getName(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Apply processed rules to observer using hybrid SQL+Memory approach
     * 
     * @param Observer $observer Observer entity
     * @param array $processedRules Array of processed rules with their configurations
     * @return array Array of filtered GeoObject entities
     */
    private function applyProcessedRules(Observer $observer, array $processedRules): array
    {
        $map = $observer->getMap();
        
        // Phase 1: SQL-level filtering
        $queryBuilder = $this->geoObjectRepository->createQueryBuilder('g')
            ->where('g.map = :map')
            ->andWhere('g.isActive = true')
            ->setParameter('map', $map);
        
        // Apply SQL-compatible rules to QueryBuilder
        foreach ($processedRules as $ruleData) {
            $rule = $ruleData['rule'];
            $config = $ruleData['config'];
            
            $queryBuilder = $rule->applyToQuery($queryBuilder, $config);
        }
        
        // Execute SQL query to get pre-filtered objects
        $geoObjects = $queryBuilder->getQuery()->getResult() ?? [];
        
        $this->logger->debug('SQL phase completed', [
            'observer' => $observer->getName(),
            'objects_after_sql' => count($geoObjects),
            'applied_rules' => count($processedRules)
        ]);
        
        // Phase 2: Memory-level filtering for complex rules
        foreach ($processedRules as $ruleData) {
            $rule = $ruleData['rule'];
            $config = $ruleData['config'];
            
            $geoObjects = $rule->applyToObjects($geoObjects, $config);
        }
        
        $this->logger->info('Rules applied successfully', [
            'observer' => $observer->getName(),
            'final_objects_count' => count($geoObjects),
            'rules_applied' => count($processedRules)
        ]);
        
        return $geoObjects;
    }



    /**
     * Log validation error
     * 
     * @param Observer $observer Observer entity
     * @param array $rulesConfig Original rules configuration
     * @param InvalidRuleConfigurationException $exception Validation exception
     * @return void
     */
    private function logValidationError(Observer $observer, array $rulesConfig, InvalidRuleConfigurationException $exception): void
    {
        $this->logger->error('Invalid rule configuration, using default behavior', [
            'observer' => $observer->getName(),
            'validation_errors' => $exception->getValidationErrors()
        ]);
    }
}
