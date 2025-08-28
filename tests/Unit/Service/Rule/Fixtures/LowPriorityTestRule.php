<?php

namespace App\Tests\Unit\Service\Rule\Fixtures;

use App\Service\Rule\RuleInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * Low priority test rule fixture
 * 
 * Used to test rule priority sorting and schema building.
 * This is a mock implementation for unit testing purposes only.
 */
class LowPriorityTestRule implements RuleInterface
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
        return 'LowPriorityTestRule';
    }

    /**
     * Get rule priority (low priority = high number)
     * 
     * @return int Priority value (100 = low priority)
     */
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * Get JSON Schema for this test rule
     * 
     * @return array JSON Schema for object with string value
     */
    public static function getConfigSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['value' => ['type' => 'string']]
        ];
    }
}
