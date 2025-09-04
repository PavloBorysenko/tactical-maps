<?php

namespace App\Tests\Unit\Service\Rule\Fixtures;

use App\Service\Rule\StatefulRuleInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Mock stateful rule fixture for testing
 * 
 * This is a test implementation of StatefulRuleInterface used for unit testing.
 * It simulates a rule with request count limiting functionality.
 */
class MockStatefulRule implements StatefulRuleInterface
{
    private string $name;
    private int $priority;

    /**
     * Constructor for mock stateful rule
     * 
     * @param string $name Rule name
     * @param int $priority Rule priority
     */
    public function __construct(string $name = 'mock_stateful_rule', int $priority = 50)
    {
        $this->name = $name;
        $this->priority = $priority;
    }

    /**
     * Get rule name for identification
     * 
     * @return string Rule name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get rule priority for sorting
     * 
     * @return int Priority value
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Apply rule to QueryBuilder - blocks queries when limit exceeded
     * 
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param array $config Rule configuration with state
     * @return QueryBuilder Modified query builder
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        // Simple mock implementation - add a condition based on state
        if (isset($config['_state']['count']) && $config['_state']['count'] >= ($config['limit'] ?? 10)) {
            // Exceed limit - return empty result
            $queryBuilder->andWhere('1 = 0');
        }
        
        return $queryBuilder;
    }

    /**
     * Apply rule to objects in memory - blocks objects when limit exceeded
     * 
     * @param array $geoObjects Array of geo objects
     * @param array $config Rule configuration with state
     * @return array Filtered array of objects
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        // In-memory filtering based on state
        if (isset($config['_state']['count']) && $config['_state']['count'] >= ($config['limit'] ?? 10)) {
            return []; // Limit exceeded
        }
        
        return $geoObjects;
    }

    /**
     * Get JSON Schema for this mock rule
     * 
     * @return array JSON Schema for rule configuration
     */
    public static function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1
                ],
                '_state' => [
                    'type' => 'object',
                    'properties' => [
                        'count' => ['type' => 'integer'],
                        'initialized_at' => ['type' => 'integer']
                    ]
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
     * @return array Initial state data
     */
    public function initializeRuleState(array $config): array
    {
        return [
            'count' => 0,
            'initialized_at' => time()
        ];
    }

    /**
     * Update rule state on each use
     * 
     * @param array $config Rule configuration with current state
     * @return array Updated state data
     */
    public function updateRuleState(array $config): array
    {
        $state = $config['_state'] ?? ['count' => 0, 'initialized_at' => time()];
        
        // Increment usage count
        $state['count'] = ($state['count'] ?? 0) + 1;
        $state['last_used'] = time();
        
        return $state;
    }
}
