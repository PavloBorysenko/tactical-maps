<?php

namespace App\Service\Rule;

use Doctrine\ORM\QueryBuilder;

/**
 * Rule for displaying specific objects by their ID
 * Useful for creating personalized views
 */
class ObjectIdRule extends AbstractObserverRule
{
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        if (empty($config)) {
            return $queryBuilder;
        }

        // Validation: all elements must be numbers (IDs)
        $validIds = array_filter($config, function($id) {
            return is_numeric($id) && $id > 0;
        });

        if (empty($validIds)) {
            return $queryBuilder;
        }

        return $queryBuilder
            ->andWhere('g.id IN (:allowedIds)')
            ->setParameter('allowedIds', array_map('intval', $validIds));
    }

    /**
     * Higher than average priority - ID filtering should be applied early
     */
    public function getPriority(): int
    {
        return 50; // High priority
    }

    /**
     * JSON Schema for ObjectIdRule configuration
     * Static method for better performance - no need to create instance for validation
     */
    public static function getConfigSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'integer',
                'minimum' => 1
            ],
            'minItems' => 1,
            'maxItems' => 100, // Limit on number of IDs
            'uniqueItems' => true // Unique IDs
        ];
    }
}
