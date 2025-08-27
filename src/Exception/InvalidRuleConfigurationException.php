<?php

/**
 * Exception thrown when rule configuration validation fails
 */

namespace App\Exception;

use Exception;

/**
 * Invalid Rule Configuration Exception
 * 
 * Thrown when observer rule configuration fails validation
 */
class InvalidRuleConfigurationException extends Exception
{
    /**
     * @param array $validationErrors Array of validation error messages
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Exception|null $previous Previous exception
     */
    public function __construct(
        private array $validationErrors,
        string $message = 'Rule configuration validation failed',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
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
     * Get formatted error message with all validation errors
     * 
     * @return string Formatted error message
     */
    public function getDetailedMessage(): string
    {
        return $this->getMessage() . ': ' . implode(', ', $this->validationErrors);
    }
}
