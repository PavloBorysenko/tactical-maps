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
use Psr\Log\LoggerInterface;

/**
 * Observer Rule Service
 * 
 * Stage 1 implementation with validation and graceful fallback
 * Later stages will add actual rule application logic
 */
class ObserverRuleService
{
    public function __construct(
        private GeoObjectRepository $geoObjectRepository,
        private RuleFactoryInterface $ruleFactory,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get filtered geo objects for observer
     * 
     * Stage 1: Validates rules configuration and logs, but falls back to default behavior
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

        // Try to validate and create rules
        try {
            $rules = $this->validateAndCreateRules($rulesConfig);
            $this->logRulesValidated($observer, $rules);
            
            // TODO: Stage 2+ - Apply actual rules here
            // For now, return default result
            return $this->getDefaultGeoObjects($observer);
            
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
     * Validate rules configuration and create rule instances
     * 
     * @param array $rulesConfig Rules configuration from observer
     * @return array Array of validated rule instances
     * @throws InvalidRuleConfigurationException If validation fails
     */
    private function validateAndCreateRules(array $rulesConfig): array
    {
        return $this->ruleFactory->createRulesFromConfig($rulesConfig);
    }

    /**
     * Log successful rules validation (only in debug mode)
     * 
     * @param Observer $observer Observer entity
     * @param array $rules Validated rules
     * @return void
     */
    private function logRulesValidated(Observer $observer, array $rules): void
    {
        $this->logger->debug('Rules validated successfully (Stage 1)', [
            'observer' => $observer->getName(),
            'rules_count' => count($rules)
        ]);
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
