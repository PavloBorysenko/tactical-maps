<?php

/**
 * Unit tests for RuleConfigValidator
 * 
 * @category Tests
 * @package  App\Tests\Unit\Service
 * @author   Tactical Maps Team
 * @license  MIT
 * @link     https://github.com/tactical-maps
 */

namespace App\Tests\Unit\Service;

use App\Service\RuleConfigValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RuleConfigValidator
 * 
 * Tests JSON Schema validation functionality for rule configurations.
 */
class RuleConfigValidatorTest extends TestCase
{
    private RuleConfigValidator $validator;
    private MockObject $mockLogger;

    /**
     * Set up test fixtures
     * 
     * @return void
     */
    protected function setUp(): void
    {
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->validator = new RuleConfigValidator($this->mockLogger);
    }

    /**
     * Test basic structure validation with valid configuration
     * 
     * @return void
     */
    public function testValidBasicStructure(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3, 4, 5],
            'SideIdRule' => [1, 2],
            'ValidRule123' => ['some', 'data']
        ];
        $schema = $this->createBasicSchema();

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertEmpty($errors, 'Valid configuration should pass validation');
    }

    /**
     * Test basic structure validation with empty configuration
     * 
     * @return void
     */
    public function testEmptyConfiguration(): void
    {
        // Arrange
        $config = [];
        $schema = $this->createBasicSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('Basic rule configuration validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration cannot be empty', $errors);
    }

    /**
     * Test basic structure validation with invalid rule names
     * 
     * @return void
     */
    public function testInvalidRuleNames(): void
    {
        // Arrange
        $config = [
            '' => [1, 2, 3],                    // Empty rule name
            '123InvalidStart' => [1, 2],        // Starts with number
            'Invalid-Name' => [1, 2],           // Contains hyphen
            'Invalid.Name' => [1, 2],           // Contains dot
            'Invalid Name' => [1, 2],           // Contains space
            'Valid_Rule_123' => [1, 2]          // Valid name
        ];
        $schema = $this->createBasicSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('Basic rule configuration validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Rule name must be a non-empty string', implode(' ', $errors));
        $this->assertStringContainsString('Invalid rule name format: 123InvalidStart', implode(' ', $errors));
        $this->assertStringContainsString('Invalid rule name format: Invalid-Name', implode(' ', $errors));
        $this->assertStringContainsString('Invalid rule name format: Invalid.Name', implode(' ', $errors));
        $this->assertStringContainsString('Invalid rule name format: Invalid Name', implode(' ', $errors));
    }

    /**
     * Test JSON Schema validation with valid ObjectIdRule configuration
     * 
     * @return void
     */
    public function testValidObjectIdRuleSchema(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3, 4, 5]
        ];
        $schema = $this->createObjectIdRuleSchema();

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertEmpty($errors, 'Valid ObjectIdRule configuration should pass');
    }

    /**
     * Test JSON Schema validation with invalid ObjectIdRule configuration
     * 
     * @return void
     */
    public function testInvalidObjectIdRuleSchema(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [
                'invalid',  // Not an integer
                -1,         // Negative number
                0,          // Zero
                1.5,        // Float
                null        // Null value
            ]
        ];
        $schema = $this->createObjectIdRuleSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors, 'Invalid ObjectIdRule configuration should fail');
        
        // Check that we have validation errors for invalid values
        $errorString = implode(' ', $errors);
        $this->assertStringContainsString('ObjectIdRule', $errorString);
    }

    /**
     * Test JSON Schema validation with SideIdRule configuration
     * 
     * @return void
     */
    public function testValidSideIdRuleSchema(): void
    {
        // Arrange
        $config = [
            'SideIdRule' => [1, 2, 3]
        ];
        $schema = $this->createSideIdRuleSchema();

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertEmpty($errors, 'Valid SideIdRule configuration should pass');
    }

    /**
     * Test JSON Schema validation with too many items
     * 
     * @return void
     */
    public function testTooManyItemsInObjectIdRule(): void
    {
        // Arrange - ObjectIdRule has maxItems: 100
        $config = [
            'ObjectIdRule' => range(1, 101) // 101 items, exceeds limit
        ];
        $schema = $this->createObjectIdRuleSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
        $errorString = implode(' ', $errors);
        $this->assertStringContainsString('ObjectIdRule', $errorString);
    }

    /**
     * Test JSON Schema validation with duplicate items
     * 
     * @return void
     */
    public function testDuplicateItemsInObjectIdRule(): void
    {
        // Arrange - ObjectIdRule requires uniqueItems: true
        $config = [
            'ObjectIdRule' => [1, 2, 3, 2, 4] // Duplicate '2'
        ];
        $schema = $this->createObjectIdRuleSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
        $errorString = implode(' ', $errors);
        $this->assertStringContainsString('ObjectIdRule', $errorString);
    }

    /**
     * Test JSON Schema validation with additional properties
     * 
     * @return void
     */
    public function testAdditionalPropertiesNotAllowed(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3],
            'UnknownRule' => [1, 2, 3]  // This rule doesn't exist in schema
        ];
        $schema = $this->createObjectIdRuleSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
        $errorString = implode(' ', $errors);
        $this->assertStringContainsString('additional', strtolower($errorString));
    }

    /**
     * Test combined valid rules configuration
     * 
     * @return void
     */
    public function testCombinedValidRules(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3, 4, 5],
            'SideIdRule' => [1, 2]
        ];
        $schema = $this->createCombinedSchema();

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertEmpty($errors, 'Valid combined rules should pass validation');
    }

    /**
     * Test mixed valid and invalid rules configuration
     * 
     * @return void
     */
    public function testMixedValidInvalidRules(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3],        // Valid
            'SideIdRule' => ['invalid', -1]     // Invalid
        ];
        $schema = $this->createCombinedSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
        $errorString = implode(' ', $errors);
        $this->assertStringContainsString('SideIdRule', $errorString);
    }

    /**
     * Test minimum items validation
     * 
     * @return void
     */
    public function testMinimumItemsValidation(): void
    {
        // Arrange - Both rules require minItems: 1
        $config = [
            'ObjectIdRule' => []  // Empty array, violates minItems
        ];
        $schema = $this->createObjectIdRuleSchema();

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('warning')
            ->with('JSON schema validation failed');

        // Act
        $errors = $this->validator->validateWithSchema($config, $schema);

        // Assert
        $this->assertNotEmpty($errors);
    }

    /**
     * Create basic schema for testing
     * 
     * @return object
     */
    private function createBasicSchema(): object
    {
        return json_decode(json_encode([
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => true,
            'minProperties' => 1
        ]));
    }

    /**
     * Create ObjectIdRule schema for testing
     * 
     * @return object
     */
    private function createObjectIdRuleSchema(): object
    {
        return json_decode(json_encode([
            'type' => 'object',
            'properties' => [
                'ObjectIdRule' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                        'minimum' => 1
                    ],
                    'minItems' => 1,
                    'maxItems' => 100,
                    'uniqueItems' => true
                ]
            ],
            'additionalProperties' => false,
            'minProperties' => 1
        ]));
    }

    /**
     * Create SideIdRule schema for testing
     * 
     * @return object
     */
    private function createSideIdRuleSchema(): object
    {
        return json_decode(json_encode([
            'type' => 'object',
            'properties' => [
                'SideIdRule' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                        'minimum' => 1
                    ],
                    'minItems' => 1,
                    'maxItems' => 50,
                    'uniqueItems' => true
                ]
            ],
            'additionalProperties' => false,
            'minProperties' => 1
        ]));
    }

    /**
     * Create combined schema for testing multiple rules
     * 
     * @return object
     */
    private function createCombinedSchema(): object
    {
        return json_decode(json_encode([
            'type' => 'object',
            'properties' => [
                'ObjectIdRule' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                        'minimum' => 1
                    ],
                    'minItems' => 1,
                    'maxItems' => 100,
                    'uniqueItems' => true
                ],
                'SideIdRule' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'integer',
                        'minimum' => 1
                    ],
                    'minItems' => 1,
                    'maxItems' => 50,
                    'uniqueItems' => true
                ]
            ],
            'additionalProperties' => false,
            'minProperties' => 1
        ]));
    }
}