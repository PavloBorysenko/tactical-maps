<?php

namespace App\Service\Rule;

use App\Entity\Observer;
use App\Entity\Map;

interface RuleEngineInterface
{
    /**
     * Apply all rules to get filtered geo objects
     */
    public function applyRules(Observer $observer): array;

    /**
     * Apply SQL-based rules to QueryBuilder
     */
    public function applySqlRules(Map $map, array $rulesConfig): array;

    /**
     * Apply memory-based rules to objects array
     */
    public function applyMemoryRules(array $geoObjects, array $rulesConfig): array;
}
