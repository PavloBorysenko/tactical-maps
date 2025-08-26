<?php

namespace App\Service\Rule;

use App\Entity\GeoObject;
use Doctrine\ORM\QueryBuilder;

interface RuleInterface
{
    /**
     * Apply rule to QueryBuilder (SQL level)
     * If rule cannot be applied at SQL level, return unchanged QueryBuilder
     */
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder;

    /**
     * Apply rule to objects in memory
     * This is always called after SQL phase
     */
    public function applyToObjects(array $geoObjects, array $config): array;

    /**
     * Get rule name for identification
     */
    public function getName(): string;

    /**
     * Get rule priority (lower number = higher priority)
     */
    public function getPriority(): int;

    /**
     * Get JSON Schema for validating this rule's configuration
     * Static method since schema doesn't depend on instance state
     */
    public static function getConfigSchema(): array;
}
