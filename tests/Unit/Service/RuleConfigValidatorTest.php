<?php

namespace App\Tests\Unit\Service;

use App\Service\RuleConfigValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RuleConfigValidator
 * 
 * Tests rule configuration validation, JSON schema validation, and error handling.
 * Uses mock logger to avoid dependencies and test logging behavior.
 */
class RuleConfigValidatorTest extends TestCase
{
    private RuleConfigValidator $validator;
    private LoggerInterface $mockLogger;

    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->validator = new RuleConfigValidator($this->mockLogger);
    }

    /**
     * Test successful validation with valid configuration and schema
     */
    public function testValidateWithSchemaSuccess(): void
    {
        $config = [
            'ObjectIdRule' => [1, 2, 3],
            'AnotherRule' => ['key' => 'value']
        ];
        
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                'ObjectIdRule' => (object) [
                    'type' => 'array',
                    'items' => (object) ['type' => 'integer']
                ],
                'AnotherRule' => (object) [
                    'type' => 'object'
                ]
            ],
            'additionalProperties' => false
        ];
        
        // Should not log any warnings for valid config
        $this->mockLogger->expects($this->never())
            ->method('warning');
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertEmpty($errors);
    }

    /**
     * Test validation failure with empty configuration
     */
    public function testValidateWithSchemaEmptyConfig(): void
    {
        $config = [];
        $schema = (object) ['type' => 'object'];
        
        // Should log warning about basic validation failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Basic rule configuration validation failed', $this->isArray());
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration cannot be empty', $errors);
    }

    /**
     * Test validation failure with invalid rule names
     */
    public function testValidateWithSchemaInvalidRuleNames(): void
    {
        $config = [
            '' => [1, 2, 3],           // Empty rule name
            '123InvalidName' => ['test'], // Name starts with number
            'Invalid-Name!' => ['test']   // Invalid characters
        ];
        
        $schema = (object) ['type' => 'object'];
        
        // Should log warning about basic validation failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Basic rule configuration validation failed', $this->isArray());
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Rule name must be a non-empty string', $errors);
        $this->assertContains('Invalid rule name format: 123InvalidName', $errors);
        $this->assertContains('Invalid rule name format: Invalid-Name!', $errors);
    }

    /**
     * Test validation failure with JSON schema errors
     */
    public function testValidateWithSchemaJsonSchemaErrors(): void
    {
        $config = [
            'ObjectIdRule' => 'invalid_type_should_be_array'
        ];
        
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                'ObjectIdRule' => (object) [
                    'type' => 'array',
                    'items' => (object) ['type' => 'integer']
                ]
            ],
            'additionalProperties' => false
        ];
        
        // Should log warning about JSON schema validation failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed', $this->isArray());
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertNotEmpty($errors);
        $this->assertCount(1, $errors);
        // Error message should contain property path and validation message
        $this->assertStringContainsString('ObjectIdRule', $errors[0]);
    }

    /**
     * Test validation with valid rule names
     */
    public function testValidRuleNames(): void
    {
        $config = [
            'ValidRule' => ['test'],
            'ValidRule123' => ['test'],
            'Valid_Rule_Name' => ['test'],
            'AnotherValidRule' => ['test']
        ];
        
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                'ValidRule' => (object) ['type' => 'array'],
                'ValidRule123' => (object) ['type' => 'array'],
                'Valid_Rule_Name' => (object) ['type' => 'array'],
                'AnotherValidRule' => (object) ['type' => 'array']
            ],
            'additionalProperties' => false
        ];
        
        // Should not log any warnings
        $this->mockLogger->expects($this->never())
            ->method('warning');
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertEmpty($errors);
    }

    /**
     * Test complex JSON schema validation
     */
    public function testComplexJsonSchemaValidation(): void
    {
        $config = [
            'ObjectIdRule' => [1, 2, 3, -1], // -1 should fail minimum validation
            'ComplexRule' => [
                'required_field' => 'value',
                'optional_field' => 123
                // missing 'another_required_field'
            ]
        ];
        
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                'ObjectIdRule' => (object) [
                    'type' => 'array',
                    'items' => (object) [
                        'type' => 'integer',
                        'minimum' => 1
                    ]
                ],
                'ComplexRule' => (object) [
                    'type' => 'object',
                    'properties' => (object) [
                        'required_field' => (object) ['type' => 'string'],
                        'another_required_field' => (object) ['type' => 'string'],
                        'optional_field' => (object) ['type' => 'integer']
                    ],
                    'required' => ['required_field', 'another_required_field'],
                    'additionalProperties' => false
                ]
            ],
            'additionalProperties' => false
        ];
        
        // Should log warning about JSON schema validation failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed', $this->isArray());
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertNotEmpty($errors);
        $this->assertGreaterThan(1, count($errors)); // Should have multiple errors
        
        // Check that errors contain expected property paths
        $errorString = implode(' ', $errors);
        $this->assertStringContainsString('ObjectIdRule', $errorString);
        $this->assertStringContainsString('ComplexRule', $errorString);
    }

    /**
     * Test validation with additional properties not allowed
     */
    public function testAdditionalPropertiesNotAllowed(): void
    {
        $config = [
            'ValidRule' => ['test'],
            'UnknownRule' => ['test'] // This should fail additionalProperties: false
        ];
        
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                'ValidRule' => (object) ['type' => 'array']
            ],
            'additionalProperties' => false
        ];
        
        // Should log warning about JSON schema validation failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed', $this->isArray());
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('UnknownRule', $errors[0]);
    }

    /**
     * Test validation with type coercion
     */
    public function testTypeCoercion(): void
    {
        $config = [
            'NumberRule' => ['1', '2', '3'] // Strings that should be coerced to integers
        ];
        
        $schema = (object) [
            'type' => 'object',
            'properties' => (object) [
                'NumberRule' => (object) [
                    'type' => 'array',
                    'items' => (object) ['type' => 'integer']
                ]
            ],
            'additionalProperties' => false
        ];
        
        // Should not log warnings - type coercion should work
        $this->mockLogger->expects($this->never())
            ->method('warning');
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertEmpty($errors);
    }

    /**
     * Test validation with non-string rule name keys
     */
    public function testNonStringRuleNameKeys(): void
    {
        $config = [
            123 => ['test'],        // Numeric key
            'ValidRule' => ['test'] // Valid string key
        ];
        
        $schema = (object) ['type' => 'object'];
        
        // Should log warning about basic validation failure
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Basic rule configuration validation failed', $this->isArray());
        
        $errors = $this->validator->validateWithSchema($config, $schema);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Rule name must be a non-empty string', $errors);
    }

    /**
     * Test logging context includes errors and config
     */
    public function testLoggingContext(): void
    {
        $config = ['InvalidRule!' => ['test']];
        $schema = (object) ['type' => 'object'];
        
        // Capture the logging arguments
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Basic rule configuration validation failed',
                $this->callback(function ($context) use ($config) {
                    return isset($context['errors']) && 
                           isset($context['config']) &&
                           $context['config'] === $config &&
                           is_array($context['errors']) &&
                           !empty($context['errors']);
                })
            );
        
        $this->validator->validateWithSchema($config, $schema);
    }
}
