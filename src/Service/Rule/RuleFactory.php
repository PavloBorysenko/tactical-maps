<?php

/**
 * Rule Factory
 * 
 * Manages rule instances, validation, and rule creation from configuration
 */

namespace App\Service\Rule;

use App\Exception\InvalidRuleConfigurationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Rule Factory
 * 
 * Central factory for managing rules with validation and priority sorting
 */
class RuleFactory implements RuleFactoryInterface
{
    private array $ruleInstances = [];
    private ?object $schema = null;

    public function __construct(
        #[AutowireIterator('observer.rule')] private iterable $rules,
        private RuleValidatorInterface $configValidator,
        private LoggerInterface $logger
    ) {
        $this->indexRules();
    }

    public function getRule(string $ruleName): ?RuleInterface
    {
        $sanitizedName = $this->sanitizeRuleName($ruleName);

        if (isset($this->ruleInstances[$sanitizedName])) {
            return $this->ruleInstances[$sanitizedName];
        }

        $this->logger->warning('Rule not found', [
            'requested' => $ruleName,
            'sanitized' => $sanitizedName,
            'available' => array_keys($this->ruleInstances)
        ]);

        return null;
    }

    public function getAllRules(): array
    {
        return $this->ruleInstances;
    }

    public function hasRule(string $ruleName): bool
    {
        $sanitizedName = $this->sanitizeRuleName($ruleName);
        return isset($this->ruleInstances[$sanitizedName]);
    }

    private function indexRules(): void
    {
        foreach ($this->rules as $rule) {
            $ruleName = $rule->getName();
            $this->ruleInstances[$ruleName] = $rule;
        }

        // Sort rules by priority
        uasort($this->ruleInstances, function (RuleInterface $a, RuleInterface $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        $this->logger->info('Rules indexed', [
            'count' => count($this->ruleInstances),
            'rules' => array_keys($this->ruleInstances)
        ]);
    }

    private function sanitizeRuleName(string $ruleName): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $ruleName);

        if (empty($sanitized)) {
            throw new \InvalidArgumentException("Invalid rule name after sanitization: $ruleName");
        }

        if (!preg_match('/^[a-zA-Z]/', $sanitized)) {
            throw new \InvalidArgumentException("Rule name must start with a letter: $sanitized");
        }

        return $sanitized;
    }

    /**
     * Create and validate rules from configuration
     * 
     * @param array $config Rule configuration
     * @return array Array of validated rule instances with their configurations
     * @throws InvalidRuleConfigurationException If configuration is invalid
     */
    public function createRulesFromConfig(array $config): array
    {
        // 1. Validate configuration against schema
        $this->validateRuleConfiguration($config);
        
        // 2. Create rule instances from configuration
        $rules = $this->createRuleInstances($config);
        
        // 3. Sort rules by priority and log result
        $sortedRules = $this->sortRulesByPriority($rules);
        $this->logRuleCreationResult($sortedRules);
        
        return $sortedRules;
    }

    /**
     * Validate rule configuration against schema
     * 
     * @param array $config Rule configuration
     * @throws InvalidRuleConfigurationException If configuration is invalid
     * @return void
     */
    private function validateRuleConfiguration(array $config): void
    {
        $schema = $this->buildSchema();
        $validationErrors = $this->configValidator->validateWithSchema($config, $schema);
        
        if (!empty($validationErrors)) {
            throw new InvalidRuleConfigurationException($validationErrors);
        }
    }

    /**
     * Create rule instances from validated configuration
     * 
     * @param array $config Validated rule configuration
     * @return array Array of rule instances with their configurations
     */
    private function createRuleInstances(array $config): array
    {
        $rules = [];
        
        foreach ($config as $ruleName => $ruleConfig) {
            $rule = $this->getRule($ruleName);
            if ($rule) {
                $rules[] = [
                    'rule' => $rule,
                    'config' => $ruleConfig,
                    'name' => $ruleName,
                    'priority' => $rule->getPriority()
                ];
            } else {
                $this->logger->warning('Rule not found during creation', [
                    'rule_name' => $ruleName,
                    'available_rules' => array_keys($this->ruleInstances)
                ]);
            }
        }
        
        return $rules;
    }

    /**
     * Sort rules by priority (lower number = higher priority)
     * 
     * @param array $rules Array of rule instances
     * @return array Sorted array of rule instances
     */
    private function sortRulesByPriority(array $rules): array
    {
        usort($rules, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        return $rules;
    }

    /**
     * Log rule creation result
     * 
     * @param array $rules Created and sorted rules
     * @return void
     */
    private function logRuleCreationResult(array $rules): void
    {
        $this->logger->info('Rules created from configuration', [
            'rules_count' => count($rules),
            'rule_names' => array_column($rules, 'name')
        ]);
    }

    /**
     * Build JSON schema from all available rules
     * 
     * @return object JSON schema object
     */
    private function buildSchema(): object
    {
        if ($this->schema === null) {
            $properties = [];
            
            foreach ($this->ruleInstances as $ruleName => $rule) {
                $ruleSchema = $rule::getConfigSchema();
                
                if (!empty($ruleSchema)) {
                    $properties[$ruleName] = $ruleSchema;
                }
            }
            
            $this->schema = json_decode(json_encode([
                'type' => 'object',
                'properties' => $properties,
                'additionalProperties' => false,
                'minProperties' => 1
            ]));
            
            $this->logger->debug('Built schema from available rules', [
                'rules_count' => count($properties),
                'rule_names' => array_keys($properties)
            ]);
        }
        
        return $this->schema;
    }
}
