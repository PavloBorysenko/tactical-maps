<?php

namespace App\Service\Rule;

interface RuleValidatorInterface
{
    /**
     * Validate rule configuration
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $config): array;
}
