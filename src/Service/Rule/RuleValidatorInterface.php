<?php

/**
 * Rule Validator Interface
 * 
 * Defines contract for rule configuration validation
 */

namespace App\Service\Rule;

/**
 * Rule Validator Interface
 * 
 * Interface for validating rule configurations
 */
interface RuleValidatorInterface
{
    /**
     * Validate rule configuration against specific JSON schema
     * 
     * Performs both basic validation (structure, names) and JSON schema validation
     * 
     * @param array  $config Rule configuration to validate
     * @param object $schema JSON schema object
     * @return array Array of validation errors (empty if valid)
     */
    public function validateWithSchema(array $config, object $schema): array;
}

