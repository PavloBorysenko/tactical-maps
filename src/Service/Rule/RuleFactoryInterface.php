<?php

/**
 * Rule Factory Interface
 * 
 * Defines contract for rule management and validation
 */

namespace App\Service\Rule;

use App\Exception\InvalidRuleConfigurationException;

/**
 * Rule Factory Interface
 * 
 * Interface for managing rule instances and validation
 */
interface RuleFactoryInterface
{
    /**
     * Get rule instance by name
     * 
     * @param string $ruleName Rule name
     * @return RuleInterface|null Rule instance or null if not found
     */
    public function getRule(string $ruleName): ?RuleInterface;

    /**
     * Get all available rules
     * 
     * @return array Array of rule instances indexed by name
     */
    public function getAllRules(): array;

    /**
     * Check if rule exists
     * 
     * @param string $ruleName Rule name
     * @return bool True if rule exists, false otherwise
     */
    public function hasRule(string $ruleName): bool;

    /**
     * Create and validate rules from configuration
     * 
     * @param array $config Rule configuration
     * @return array Array of validated rule instances with their configurations
     * @throws InvalidRuleConfigurationException If configuration is invalid
     */
    public function createRulesFromConfig(array $config): array;
}
