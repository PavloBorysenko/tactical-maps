<?php

namespace App\Service\Rule;

/**
 * Interface for rules that maintain state between requests
 * 
 * Stateful rules can store and modify their configuration based on usage.
 * State is persisted in the Observer's JSON configuration.
 */
interface StatefulRuleInterface extends RuleInterface
{
    /**
     * Initialize rule state on first use
     * 
     * Called when the rule is used for the first time and no '_state' 
     * key exists in the configuration.
     * 
     * @param array $config Current rule configuration
     * 
     * @return array Initial state array to be stored in '_state' key
     */
    public function initializeRuleState(array $config): array;

    /**
     * Update state after rule usage
     * 
     * Called every time the rule is processed. The rule can update
     * its internal state (counters, timestamps, flags, etc.).
     * 
     * @param array $config Current rule configuration including '_state'
     * 
     * @return array Updated state array to be saved back to configuration
     */
    public function updateRuleState(array $config): array;
}
