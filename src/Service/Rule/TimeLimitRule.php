<?php

namespace App\Service\Rule;

use App\Service\Rule\StatefulRuleInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Time limit rule - limits access based on time duration
 * 
 * This rule allows access for a specified duration after first use.
 * After expiration, all requests return empty results.
 */
class TimeLimitRule implements StatefulRuleInterface
{
    /**
     * Get rule name for identification
     * 
     * @return string Rule name
     */
    public function getName(): string
    {
        return 'time_limit';
    }

    /**
     * Get rule priority for sorting
     * Higher priority (lower number) = executes earlier
     * 
     * @return int Priority value (20 = very high priority, before other rules)
     */
    public function getPriority(): int
    {
        return 20;
    }

    /**
     * Apply rule to QueryBuilder - blocks queries when time limit exceeded
     * 
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param array $config Rule configuration with state
     * @return QueryBuilder Modified query builder
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        if ($this->isTimeExpired($config)) {
            // Time limit exceeded - return guaranteed empty result
            $queryBuilder->andWhere('1 = 0');
        }
        
        return $queryBuilder;
    }

    /**
     * Apply rule to objects in memory - blocks objects when time limit exceeded
     * 
     * @param array $geoObjects Array of geo objects
     * @param array $config Rule configuration with state
     * @return array Filtered array of objects
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        if ($this->isTimeExpired($config)) {
            return []; // Time limit exceeded
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
                'duration_seconds' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Duration in seconds for which access is allowed'
                ],
                '_state' => [
                    'type' => 'object',
                    'properties' => [
                        'first_used_at' => [
                            'type' => 'integer',
                            'description' => 'Timestamp when rule was first used'
                        ],
                        'expires_at' => [
                            'type' => 'integer',
                            'description' => 'Timestamp when rule expires'
                        ],
                        'last_used_at' => [
                            'type' => 'integer',
                            'description' => 'Timestamp of last request'
                        ]
                    ],
                    'required' => ['first_used_at', 'expires_at'],
                    'additionalProperties' => false
                ]
            ],
            'required' => ['duration_seconds'],
            'additionalProperties' => false
        ];
    }

    /**
     * Initialize rule state on first use
     * 
     * @param array $config Rule configuration
     * @return array Initial state with start and expiration times
     */
    public function initializeRuleState(array $config): array
    {
        $currentTime = time();
        $duration = $config['duration_seconds'] ?? 300; // Default 5 minutes
        
        return [
            'first_used_at' => $currentTime,
            'expires_at' => $currentTime + $duration,
            'last_used_at' => null
        ];
    }

    /**
     * Update rule state on each use - updates last used time
     * 
     * @param array $config Rule configuration with current state
     * @return array Updated state with last used time
     */
    public function updateRuleState(array $config): array
    {
        $state = $config['_state'] ?? $this->initializeRuleState($config);
        
        // Only update last_used_at, time limits don't change on each use
        $state['last_used_at'] = time();
        
        return $state;
    }

    /**
     * Check if time limit has been exceeded
     * 
     * @param array $config Rule configuration with state
     * @return bool True if time limit exceeded
     */
    private function isTimeExpired(array $config): bool
    {
        if (!isset($config['_state']['expires_at'])) {
            return false; // No state yet, allow access
        }
        
        $currentTime = time();
        $expiresAt = $config['_state']['expires_at'];
        
        return $currentTime > $expiresAt;
    }
}
