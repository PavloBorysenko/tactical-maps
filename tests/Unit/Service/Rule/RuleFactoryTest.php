<?php

namespace App\Tests\Unit\Service\Rule;

use App\Exception\InvalidRuleConfigurationException;
use App\Service\Rule\RuleFactory;
use App\Service\Rule\RuleValidatorInterface;
use App\Tests\Unit\Service\Rule\Fixtures\HighPriorityTestRule;
use App\Tests\Unit\Service\Rule\Fixtures\LowPriorityTestRule;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for RuleFactory
 * 
 * Tests rule management, validation, creation, and priority sorting.
 * Uses test rule fixtures from the Fixtures directory to avoid dependencies.
 */
class RuleFactoryTest extends TestCase
{
    private RuleFactory $ruleFactory;
    private RuleValidatorInterface $mockValidator;
    private LoggerInterface $mockLogger;
    private HighPriorityTestRule $rule1;
    private LowPriorityTestRule $rule2;

    protected function setUp(): void
    {
        $this->mockValidator = $this->createMock(RuleValidatorInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Create real test rule instances
        $this->rule1 = new HighPriorityTestRule();
        $this->rule2 = new LowPriorityTestRule();
        
        $rules = [$this->rule1, $this->rule2];
        
        $this->ruleFactory = new RuleFactory($rules, $this->mockValidator, $this->mockLogger);
    }

    /**
     * Test successful rule retrieval by name
     */
    public function testGetRuleSuccess(): void
    {
        $rule = $this->ruleFactory->getRule('HighPriorityTestRule');
        
        $this->assertSame($this->rule1, $rule);
        $this->assertEquals('HighPriorityTestRule', $rule->getName());
    }

    /**
     * Test rule retrieval with non-existent rule name
     */
    public function testGetRuleNotFound(): void
    {
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with('Rule not found', $this->isArray());
        
        $rule = $this->ruleFactory->getRule('NonExistentRule');
        
        $this->assertNull($rule);
    }

    /**
     * Test rule name sanitization
     */
    public function testRuleNameSanitization(): void
    {
        // Test with special characters that should be removed
        $rule = $this->ruleFactory->getRule('High-Priority@Rule!');
        
        $this->assertNull($rule); // Should not find anything after sanitization
    }

    /**
     * Test invalid rule name throws exception
     */
    public function testInvalidRuleNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rule name must start with a letter');
        
        $this->ruleFactory->getRule('123InvalidName');
    }

    /**
     * Test getAllRules returns all indexed rules
     */
    public function testGetAllRules(): void
    {
        $rules = $this->ruleFactory->getAllRules();
        
        $this->assertCount(2, $rules);
        $this->assertArrayHasKey('HighPriorityTestRule', $rules);
        $this->assertArrayHasKey('LowPriorityTestRule', $rules);
        
        // Verify rules are sorted by priority (lower number = higher priority)
        $ruleNames = array_keys($rules);
        $this->assertEquals('HighPriorityTestRule', $ruleNames[0]); // Priority 10 should be first
        $this->assertEquals('LowPriorityTestRule', $ruleNames[1]);  // Priority 100 should be second
    }

    /**
     * Test hasRule method
     */
    public function testHasRule(): void
    {
        $this->assertTrue($this->ruleFactory->hasRule('HighPriorityTestRule'));
        $this->assertTrue($this->ruleFactory->hasRule('LowPriorityTestRule'));
        $this->assertFalse($this->ruleFactory->hasRule('NonExistentRule'));
    }

    /**
     * Test successful rule creation from valid configuration
     */
    public function testCreateRulesFromConfigSuccess(): void
    {
        $config = [
            'HighPriorityTestRule' => [1, 2, 3],
            'LowPriorityTestRule' => ['value' => 'test']
        ];
        
        // Mock validator to return no errors (valid config)
        $this->mockValidator->expects($this->once())
            ->method('validateWithSchema')
            ->with($config, $this->isObject())
            ->willReturn([]);
        
        // Expect info log about successful creation
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Rules created from configuration', $this->isArray());
        
        $result = $this->ruleFactory->createRulesFromConfig($config);
        
        $this->assertCount(2, $result);
        
        // Verify first rule (higher priority)
        $this->assertEquals('HighPriorityTestRule', $result[0]['name']);
        $this->assertEquals(10, $result[0]['priority']);
        $this->assertSame($this->rule1, $result[0]['rule']);
        $this->assertEquals([1, 2, 3], $result[0]['config']);
        
        // Verify second rule (lower priority)
        $this->assertEquals('LowPriorityTestRule', $result[1]['name']);
        $this->assertEquals(100, $result[1]['priority']);
        $this->assertSame($this->rule2, $result[1]['rule']);
        $this->assertEquals(['value' => 'test'], $result[1]['config']);
    }

    /**
     * Test rule creation with validation errors throws exception
     */
    public function testCreateRulesFromConfigValidationFailure(): void
    {
        $config = [
            'HighPriorityTestRule' => 'invalid_config'
        ];
        
        $validationErrors = ['Invalid configuration format'];
        
        // Mock validator to return validation errors
        $this->mockValidator->expects($this->once())
            ->method('validateWithSchema')
            ->with($config, $this->isObject())
            ->willReturn($validationErrors);
        
        $this->expectException(InvalidRuleConfigurationException::class);
        $this->expectExceptionMessage('Rule configuration validation failed');
        
        $this->ruleFactory->createRulesFromConfig($config);
    }

    /**
     * Test rule creation with non-existent rule in config
     */
    public function testCreateRulesFromConfigWithNonExistentRule(): void
    {
        $config = [
            'HighPriorityTestRule' => [1, 2, 3],
            'NonExistentRule' => ['test' => 'value']
        ];
        
        // Mock validator to return no errors
        $this->mockValidator->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);
        
        // Expect warning about non-existent rule (called twice - once in getRule, once in createRuleInstances)
        $this->mockLogger->expects($this->exactly(2))
            ->method('warning');
        
        // Expect info log about successful creation
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Rules created from configuration', $this->isArray());
        
        $result = $this->ruleFactory->createRulesFromConfig($config);
        
        // Should only contain the existing rule
        $this->assertCount(1, $result);
        $this->assertEquals('HighPriorityTestRule', $result[0]['name']);
    }

    /**
     * Test schema building from available rules
     */
    public function testSchemaBuilding(): void
    {
        $config = ['HighPriorityTestRule' => [1, 2, 3]];
        
        // Mock validator to return no errors and capture the schema
        $capturedSchema = null;
        $this->mockValidator->expects($this->once())
            ->method('validateWithSchema')
            ->with($config, $this->callback(function ($schema) use (&$capturedSchema) {
                $capturedSchema = $schema;
                return true;
            }))
            ->willReturn([]);
        
        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with('Built schema from available rules', $this->isArray());
        
        $this->ruleFactory->createRulesFromConfig($config);
        
        // Verify schema structure
        $this->assertNotNull($capturedSchema);
        $this->assertEquals('object', $capturedSchema->type);
        $this->assertObjectHasProperty('properties', $capturedSchema);
        $this->assertObjectHasProperty('HighPriorityTestRule', $capturedSchema->properties);
        $this->assertObjectHasProperty('LowPriorityTestRule', $capturedSchema->properties);
        $this->assertFalse($capturedSchema->additionalProperties);
        $this->assertEquals(1, $capturedSchema->minProperties);
    }

    /**
     * Test empty configuration throws validation exception
     */
    public function testEmptyConfigurationThrowsException(): void
    {
        $config = [];
        
        $validationErrors = ['Configuration cannot be empty'];
        
        $this->mockValidator->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn($validationErrors);
        
        $this->expectException(InvalidRuleConfigurationException::class);
        
        $this->ruleFactory->createRulesFromConfig($config);
    }

    /**
     * Test rule priority sorting
     */
    public function testRulePrioritySorting(): void
    {
        $config = [
            'LowPriorityTestRule' => ['value' => 'test'],   // Priority 100
            'HighPriorityTestRule' => [1, 2, 3]            // Priority 10
        ];
        
        $this->mockValidator->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);
        
        $result = $this->ruleFactory->createRulesFromConfig($config);
        
        // Rules should be sorted by priority (lower number = higher priority)
        $this->assertEquals('HighPriorityTestRule', $result[0]['name']); // Priority 10 first
        $this->assertEquals('LowPriorityTestRule', $result[1]['name']);  // Priority 100 second
    }
}
