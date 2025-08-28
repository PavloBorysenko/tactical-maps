<?php

namespace App\Tests\Unit\Service\Rule\Fixtures;

use App\Service\Rule\RuleInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * High priority test rule fixture
 * 
 * Used to test rule priority sorting and basic rule functionality.
 * This is a mock implementation for unit testing purposes only.
 */
class HighPriorityTestRule implements RuleInterface
{
    /**
     * Apply rule to QueryBuilder (no-op for testing)
     * 
     * @param QueryBuilder $queryBuilder The query builder instance
     * @param array $config Rule configuration
     * @return QueryBuilder Unchanged query builder
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        return $queryBuilder;
    }

    /**
     * Apply rule to objects in memory (no-op for testing)
     * 
     * @param array $geoObjects Array of geo objects
     * @param array $config Rule configuration
     * @return array Unchanged array of objects
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        return $geoObjects;
    }

    /**
     * Get rule name for identification
     * 
     * @return string Rule name
     */
    public function getName(): string
    {
        return 'HighPriorityTestRule';
    }

    /**
     * Get rule priority (high priority = low number)
     * 
     * @return int Priority value (10 = high priority)
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Get JSON Schema for this test rule
     * 
     * @return array JSON Schema for array of integers
     */
    public static function getConfigSchema(): array
    {
        return [
            'type' => 'array',
            'items' => ['type' => 'integer']
        ];
    }
}
