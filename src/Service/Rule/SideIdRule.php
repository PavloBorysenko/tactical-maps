<?php

namespace App\Service\Rule;

use Doctrine\ORM\QueryBuilder;

/**
 * Rule for displaying objects that belong to specific sides by their ID
 * Filters geo objects by side ownership
 */
class SideIdRule extends AbstractObserverRule
{
    public function applyToQuery(QueryBuilder $queryBuilder, array $config): QueryBuilder
    {
        if (empty($config)) {
            return $queryBuilder;
        }

        // Validation: all elements must be numbers (Side IDs)
        $validIds = array_filter($config, function($id) {
            return is_numeric($id) && $id > 0;
        });

        if (empty($validIds)) {
            return $queryBuilder;
        }

        // Join with Side entity and filter by side IDs
        return $queryBuilder
            ->leftJoin('g.side', 's')
            ->andWhere('s.id IN (:allowedSideIds)')
            ->setParameter('allowedSideIds', array_map('intval', array_values($validIds)));
    }

    /**
     * Medium priority - side filtering should be applied after ID filtering
     * but before visibility rules
     */
    public function getPriority(): int
    {
        return 75; // Medium-high priority
    }

    /**
     * JSON Schema for SideIdRule configuration
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
            'maxItems' => 50, // Limit on number of Side IDs
            'uniqueItems' => true // Unique Side IDs
        ];
    }
}
