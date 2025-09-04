<?php

namespace App\Exception;

/**
 * Exception thrown when rule configuration is invalid
 * 
 * This exception is used when JSON Schema validation fails
 * or when rule configuration contains invalid data.
 */
class InvalidRuleConfigurationException extends \Exception
{
    private array $validationErrors = [];

    /**
     * Create exception with validation errors
     * 
     * @param string $message Exception message
     * @param array $validationErrors Array of validation error messages
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Rule configuration validation failed',
        array $validationErrors = [],
        int $code = 0,
        \Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
    }

    /**
     * Get validation errors
     * 
     * @return array Array of validation error messages
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    /**
     * Set validation errors
     * 
     * @param array $errors Array of validation error messages
     * 
     * @return void
     */
    public function setValidationErrors(array $errors): void
    {
        $this->validationErrors = $errors;
    }
}