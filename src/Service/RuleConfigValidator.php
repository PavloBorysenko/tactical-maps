<?php

/**
 * Rule Configuration Validator
 * 
 * Simple JSON schema validator that validates rule configurations
 * against provided schema. No longer discovers rules - schema is built externally.
 */

namespace App\Service;

use App\Service\Rule\RuleValidatorInterface;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;
use Psr\Log\LoggerInterface;

/**
 * Rule Configuration Validator
 * 
 * Validates rule configurations against JSON schema.
 * Schema is provided externally (by RuleFactory) for better separation of concerns.
 */
class RuleConfigValidator implements RuleValidatorInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Validate rule configuration against specific schema
     * 
     * Performs both basic validation (structure, names) and JSON schema validation
     * 
     * @param array  $config Rule configuration to validate
     * @param object $schema JSON schema object
     * @return array Array of validation errors (empty if valid)
     */
    public function validateWithSchema(array $config, object $schema): array
    {
        // 1. Basic validation first (faster, catches obvious errors)
        $basicErrors = $this->validateBasicStructure($config);
        if (!empty($basicErrors)) {
            $this->logValidationFailure('Basic rule configuration validation failed', $basicErrors, $config);
            return $basicErrors;
        }
        
        // 2. JSON Schema validation
        $schemaErrors = $this->validateAgainstJsonSchema($config, $schema);
        if (!empty($schemaErrors)) {
            $this->logValidationFailure('JSON schema validation failed', $schemaErrors, $config);
        }
        
        return $schemaErrors;
    }

    /**
     * Validate basic configuration structure and rule names
     * 
     * @param array $config Rule configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    private function validateBasicStructure(array $config): array
    {
        $errors = [];
        
        if (empty($config)) {
            $errors[] = 'Configuration cannot be empty';
            return $errors;
        }
        
        foreach ($config as $ruleName => $ruleConfig) {
            if (!is_string($ruleName) || empty($ruleName)) {
                $errors[] = 'Rule name must be a non-empty string';
                continue;
            }
            
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $ruleName)) {
                $errors[] = "Invalid rule name format: {$ruleName}";
            }
        }
        
        return $errors;
    }

    /**
     * Validate configuration against JSON schema
     * 
     * @param array  $config Rule configuration to validate
     * @param object $schema JSON schema object
     * @return array Array of validation errors (empty if valid)
     */
    private function validateAgainstJsonSchema(array $config, object $schema): array
    {
        $validator = new Validator();
        $configObject = json_decode(json_encode($config));

        $validator->validate($configObject, $schema, Constraint::CHECK_MODE_COERCE_TYPES);

        $errors = [];
        if (!$validator->isValid()) {
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s", $error['property'], $error['message']);
            }
        }

        return $errors;
    }

    /**
     * Log validation failure with context
     * 
     * @param string $message Log message
     * @param array  $errors  Validation errors
     * @param array  $config  Configuration that failed validation
     * @return void
     */
    private function logValidationFailure(string $message, array $errors, array $config): void
    {
        $this->logger->warning($message, [
            'errors' => $errors,
            'config' => $config
        ]);
    }
}
