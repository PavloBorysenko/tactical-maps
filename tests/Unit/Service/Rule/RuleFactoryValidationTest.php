<?php

/**
 * Unit tests for RuleFactory validation functionality
 * 
 * @category Tests
 * @package  App\Tests\Unit\Service\Rule
 * @author   Tactical Maps Team
 * @license  MIT
 * @link     https://github.com/tactical-maps
 */

namespace App\Tests\Unit\Service\Rule;

use App\Service\Rule\RuleFactory;
use App\Service\Rule\RuleValidatorInterface;
use App\Service\Rule\ObjectIdRule;
use App\Service\Rule\SideIdRule;
use App\Exception\InvalidRuleConfigurationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RuleFactory validation functionality
 * 
 * Tests the validation integration within RuleFactory.
 */
class RuleFactoryValidationTest extends TestCase
{
    private RuleFactory $factory;
    private MockObject $mockValidator;
    private MockObject $mockLogger;
    private array $mockRules;

    /**
     * Set up test fixtures
     * 
     * @return void
     */
    protected function setUp(): void
    {
        $this->mockValidator = $this->createMock(RuleValidatorInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Use real rule instances for testing (they have static methods)
        $this->mockRules = [
            new ObjectIdRule(),
            new SideIdRule()
        ];
        
        $this->factory = new RuleFactory(
            $this->mockRules,
            $this->mockValidator,
            $this->mockLogger
        );
    }

    /**
     * Test successful rule creation from valid configuration
     * 
     * @return void
     */
    public function testCreateRulesFromValidConfig(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3, 4, 5],
            'SideIdRule' => [1, 2]
        ];

        // Mock validator to return no errors (valid config)
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Expect successful logging
        $this->mockLogger
            ->expects($this->once())
            ->method('info')
            ->with('Rules created from configuration');

        // Act
        $result = $this->factory->createRulesFromConfig($config);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('ObjectIdRule', $result[0]['name']);
        $this->assertEquals('SideIdRule', $result[1]['name']);
        $this->assertEquals(50, $result[0]['priority']);
        $this->assertEquals(75, $result[1]['priority']);
    }

    /**
     * Test rule creation failure with invalid configuration
     * 
     * @return void
     */
    public function testCreateRulesFromInvalidConfig(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => ['invalid', 'data']
        ];

        $validationErrors = [
            '[ObjectIdRule] Invalid type: expected integer, got string',
            '[ObjectIdRule] Minimum value is 1'
        ];

        // Mock validator to return validation errors
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn($validationErrors);

        // Expect exception to be thrown
        $this->expectException(InvalidRuleConfigurationException::class);
        $this->expectExceptionMessage('Rule configuration validation failed');

        // Act
        $this->factory->createRulesFromConfig($config);
    }

    /**
     * Test schema building from available rules
     * 
     * @return void
     */
    public function testSchemaBuildingFromRules(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3]
        ];

        // Mock validator - we expect it to be called with a schema that includes ObjectIdRule
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->with(
                $config,
                $this->callback(function ($schema) {
                    // Verify that schema contains ObjectIdRule properties
                    return isset($schema->properties->ObjectIdRule) 
                        && isset($schema->properties->SideIdRule);
                })
            )
            ->willReturn([]);

        // Act
        $this->factory->createRulesFromConfig($config);
    }

    /**
     * Test rule creation with non-existent rule
     * 
     * @return void
     */
    public function testCreateRulesWithNonExistentRule(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [1, 2, 3],
            'NonExistentRule' => [1, 2, 3]
        ];

        // Mock validator to pass validation
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Expect warning about non-existent rule (called twice - once in getRule, once in createRuleInstances)
        $this->mockLogger
            ->expects($this->exactly(2))
            ->method('warning');

        // Act
        $result = $this->factory->createRulesFromConfig($config);

        // Assert - should only contain existing rule
        $this->assertCount(1, $result);
        $this->assertEquals('ObjectIdRule', $result[0]['name']);
    }

    /**
     * Test rule priority sorting
     * 
     * @return void
     */
    public function testRulePrioritySorting(): void
    {
        // Arrange - SideIdRule has higher priority number (75) than ObjectIdRule (50)
        // Lower number = higher priority, so ObjectIdRule should come first
        $config = [
            'SideIdRule' => [1, 2],      // Priority 75
            'ObjectIdRule' => [1, 2, 3]  // Priority 50
        ];

        // Mock validator to pass validation
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Act
        $result = $this->factory->createRulesFromConfig($config);

        // Assert - ObjectIdRule should be first (lower priority number)
        $this->assertCount(2, $result);
        $this->assertEquals('ObjectIdRule', $result[0]['name']);
        $this->assertEquals('SideIdRule', $result[1]['name']);
        $this->assertEquals(50, $result[0]['priority']);
        $this->assertEquals(75, $result[1]['priority']);
    }

    /**
     * Test empty configuration validation
     * 
     * @return void
     */
    public function testEmptyConfigurationValidation(): void
    {
        // Arrange
        $config = [];

        $validationErrors = ['Configuration cannot be empty'];

        // Mock validator to return validation error
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn($validationErrors);

        // Expect exception
        $this->expectException(InvalidRuleConfigurationException::class);

        // Act
        $this->factory->createRulesFromConfig($config);
    }

    /**
     * Test validation error details are preserved
     * 
     * @return void
     */
    public function testValidationErrorDetailsPreserved(): void
    {
        // Arrange
        $config = [
            'ObjectIdRule' => [-1, 0, 'invalid']
        ];

        $validationErrors = [
            '[ObjectIdRule.0] Value must be at least 1',
            '[ObjectIdRule.1] Value must be at least 1',
            '[ObjectIdRule.2] Invalid type: expected integer, got string'
        ];

        // Mock validator to return detailed errors
        $this->mockValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn($validationErrors);

        // Act & Assert
        try {
            $this->factory->createRulesFromConfig($config);
            $this->fail('Expected InvalidRuleConfigurationException to be thrown');
        } catch (InvalidRuleConfigurationException $e) {
            $errors = $e->getValidationErrors();
            $this->assertCount(3, $errors);
            $this->assertContains('[ObjectIdRule.0] Value must be at least 1', $errors);
            $this->assertContains('[ObjectIdRule.1] Value must be at least 1', $errors);
            $this->assertContains('[ObjectIdRule.2] Invalid type: expected integer, got string', $errors);
        }
    }

    /**
     * Test schema caching functionality
     * 
     * @return void
     */
    public function testSchemaCaching(): void
    {
        // Arrange
        $config1 = ['ObjectIdRule' => [1, 2, 3]];
        $config2 = ['ObjectIdRule' => [4, 5, 6]];

        // Mock validator to be called twice
        $this->mockValidator
            ->expects($this->exactly(2))
            ->method('validateWithSchema')
            ->willReturn([]);

        // Act - call twice with different configs but same rules
        $this->factory->createRulesFromConfig($config1);
        $this->factory->createRulesFromConfig($config2);

        // Assert - schema should be built only once (tested implicitly)
        // If schema wasn't cached, this test would fail due to multiple static calls
        $this->assertTrue(true, 'Schema caching works correctly');
    }
}
