<?php

namespace App\Service\Rule;

use App\Entity\GeoObject;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractObserverRule implements RuleInterface
{
    /**
     * Apply rule to QueryBuilder (SQL level)
     * Default implementation - no changes (rule will be applied in memory)
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        return $queryBuilder; // Default: no query changes
    }

    /**
     * Apply rule to objects in memory
     * Default implementation - no changes
     */
    public function applyToObjects(array $geoObjects, array $config): array
    {
        return $geoObjects; // Default: no filtering
    }

    /**
     * Get rule name for identification
     */
    public function getName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Get rule priority (lower number = higher priority)
     * Default priority is 100
     */
    public function getPriority(): int
    {
        return 100;
    }

    /**
     * Get JSON Schema for validating this rule's configuration
     * Default implementation returns empty schema (no validation)
     * Static method since schema doesn't depend on instance state
     */
    public static function getConfigSchema(): array
    {
        return [];
    }
}
