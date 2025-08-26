<?php

namespace App\Service\Rule;

interface RuleFactoryInterface
{
    /**
     * Get rule instance by name
     */
    public function getRule(string $ruleName): ?RuleInterface;

    /**
     * Get all available rules
     */
    public function getAllRules(): array;

    /**
     * Check if rule exists
     */
    public function hasRule(string $ruleName): bool;
}
