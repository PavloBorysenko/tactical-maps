<?php

namespace App\Service\Rule;

use App\Service\Rule\StatefulRuleInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Request limit rule - limits number of requests with countdown
 * 
 * This rule maintains a decreasing counter of remaining requests.
 * When counter reaches zero, all subsequent requests return empty results.
 */
class RequestLimitRule implements StatefulRuleInterface
{
    /**
     * Get rule name for identification
     * 
     * @return string Rule name
     */
    public function getName(): string
    {
        return 'request_limit';
    }

    /**
     * Get rule priority for sorting
     * Higher priority (lower number) = executes earlier
     * 
     * @return int Priority value (30 = high priority, before filtering rules)
     */
    public function getPriority(): int
    {
        return 30;
    }

    /**
     * Apply rule to QueryBuilder - blocks queries when limit exhausted
     * 
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param array $config Rule configuration with state
     * @return QueryBuilder Modified query builder
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        // Use original value (before decrement) to check if request should be allowed
        $originalRemaining = $config['_state']['_original_remaining'] ?? 
                           $config['_state']['remaining'] ?? 0;
        
        if ($originalRemaining <= 0) {
            // Limit exhausted - return guaranteed empty result
            $queryBuilder->andWhere('1 = 0');
        }
        
        return $queryBuilder;
    }

    /**
     * Apply rule to objects in memory - blocks objects when limit exhausted
     * 
     * @param array $geoObjects Array of geo objects
     * @param array $config Rule configuration with state
     * @return array Filtered array of objects
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        // Use original value (before decrement) to check if request should be allowed
        $originalRemaining = $config['_state']['_original_remaining'] ?? 
                           $config['_state']['remaining'] ?? 0;
        
        if ($originalRemaining <= 0) {
            return []; // Limit exhausted
        }
        
        return $geoObjects;
    }

    /**
     * Get JSON Schema for rule configuration
     * 
     * @return array JSON Schema for validation
     */
    public static function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Maximum number of requests allowed'
                ],
                '_state' => [
                    'type' => 'object',
                    'properties' => [
                        'remaining' => [
                            'type' => 'integer',
                            'minimum' => 0,
                            'description' => 'Number of requests remaining'
                        ],
                        'initialized_at' => [
                            'type' => 'integer',
                            'description' => 'Timestamp when rule was initialized'
                        ],
                        'last_used_at' => [
                            'type' => 'integer',
                            'description' => 'Timestamp of last request'
                        ]
                    ],
                    'required' => ['remaining', 'initialized_at'],
                    'additionalProperties' => false
                ]
            ],
            'required' => ['limit'],
            'additionalProperties' => false
        ];
    }

    /**
     * Initialize rule state on first use
     * 
     * @param array $config Rule configuration
     * @return array Initial state with full request counter
     */
    public function initializeRuleState(array $config): array
    {
        $limit = $config['limit'] ?? 10; // Default 10 requests
        
        return [
            'remaining' => $limit,
            'initialized_at' => time(),
            'last_used_at' => null
        ];
    }

    /**
     * Update rule state on each use - decrements counter
     * 
     * @param array $config Rule configuration with current state
     * @return array Updated state with decremented counter
     */
    public function updateRuleState(array $config): array
    {
        $state = $config['_state'] ?? $this->initializeRuleState($config);
        
        // Store original value before decrement for apply methods to use
        $state['_original_remaining'] = $state['remaining'];
        
        // Only decrement if we have remaining requests
        if ($state['remaining'] > 0) {
            $state['remaining'] = $state['remaining'] - 1;
        }
        $state['last_used_at'] = time();
        
        return $state;
    }
}
