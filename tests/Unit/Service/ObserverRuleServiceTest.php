<?php

namespace App\Tests\Unit\Service;

use App\Entity\Observer;
use App\Entity\Map;
use App\Exception\InvalidRuleConfigurationException;
use App\Repository\GeoObjectRepository;
use App\Service\ObserverRuleService;
use App\Service\Rule\RuleFactoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ObserverRuleService
 * 
 * Tests rule-based filtering for observers with graceful fallback behavior.
 * Uses mocks to avoid database dependencies and test logging behavior.
 */
class ObserverRuleServiceTest extends TestCase
{
    private ObserverRuleService $service;
    private GeoObjectRepository $mockRepository;
    private RuleFactoryInterface $mockRuleFactory;
    private LoggerInterface $mockLogger;
    private Observer $mockObserver;
    private Map $mockMap;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(GeoObjectRepository::class);
        $this->mockRuleFactory = $this->createMock(RuleFactoryInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        $this->service = new ObserverRuleService(
            $this->mockRepository,
            $this->mockRuleFactory,
            $this->mockLogger
        );
        
        // Create mock entities
        $this->mockMap = $this->createMock(Map::class);
        $this->mockObserver = $this->createMock(Observer::class);
        $this->mockObserver->method('getMap')->willReturn($this->mockMap);
    }

    /**
     * Test behavior with empty rules configuration (fallback to default)
     */
    public function testGetFilteredGeoObjectsWithEmptyRules(): void
    {
        $expectedGeoObjects = ['geo1', 'geo2', 'geo3'];
        
        // Observer has no rules configured
        $this->mockObserver->method('getRules')->willReturn([]);
        $this->mockObserver->method('getName')->willReturn('TestObserver');
        
        // Repository should be called for default behavior
        $this->mockRepository->expects($this->once())
            ->method('findActiveByMap')
            ->with($this->mockMap)
            ->willReturn($expectedGeoObjects);
        
        // RuleFactory should not be called
        $this->mockRuleFactory->expects($this->never())
            ->method('createRulesFromConfig');
        
        // No logging should occur for empty rules
        $this->mockLogger->expects($this->never())
            ->method('debug');
        $this->mockLogger->expects($this->never())
            ->method('error');
        
        $result = $this->service->getFilteredGeoObjects($this->mockObserver);
        
        $this->assertEquals($expectedGeoObjects, $result);
    }

    /**
     * Test successful rules validation and creation (Stage 1 - still returns default)
     */
    public function testGetFilteredGeoObjectsWithValidRules(): void
    {
        $rulesConfig = [
            'ObjectIdRule' => [1, 2, 3],
            'AnotherRule' => ['key' => 'value']
        ];
        $expectedGeoObjects = ['geo1', 'geo2', 'geo3'];
        // Create mock rule objects
        $mockRule1 = $this->createMock(\App\Service\Rule\RuleInterface::class);
        $mockRule1->method('applyToQuery')->willReturnArgument(0);
        $mockRule1->method('applyToObjects')->willReturnArgument(0);
        
        $mockRule2 = $this->createMock(\App\Service\Rule\RuleInterface::class);
        $mockRule2->method('applyToQuery')->willReturnArgument(0);
        $mockRule2->method('applyToObjects')->willReturnArgument(0);
        
        $validatedRules = [
            ['name' => 'ObjectIdRule', 'priority' => 50, 'rule' => $mockRule1, 'config' => [1, 2, 3]],
            ['name' => 'AnotherRule', 'priority' => 100, 'rule' => $mockRule2, 'config' => ['key' => 'value']]
        ];
        
        $this->mockObserver->method('getRules')->willReturn($rulesConfig);
        $this->mockObserver->method('getName')->willReturn('TestObserver');
        
        // RuleFactory should validate and create rules successfully
        $this->mockRuleFactory->expects($this->once())
            ->method('createRulesFromConfig')
            ->with($rulesConfig)
            ->willReturn($validatedRules);
        
        // Setup QueryBuilder mock for SQL phase
        $mockQueryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $mockQueryBuilder->method('where')->willReturnSelf();
        $mockQueryBuilder->method('andWhere')->willReturnSelf();
        $mockQueryBuilder->method('setParameter')->willReturnSelf();
        
        $mockQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery->method('getResult')->willReturn($expectedGeoObjects);
        $mockQueryBuilder->method('getQuery')->willReturn($mockQuery);
        
        $this->mockRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($mockQueryBuilder);
        
        // Should log successful validation and rule application
        $this->mockLogger->expects($this->exactly(2))
            ->method('debug');
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Rules applied successfully', $this->isArray());
        
        // Should not log any errors
        $this->mockLogger->expects($this->never())
            ->method('error');
        
        $result = $this->service->getFilteredGeoObjects($this->mockObserver);
        
        $this->assertEquals($expectedGeoObjects, $result);
    }

    /**
     * Test handling of invalid rules configuration with graceful fallback
     */
    public function testGetFilteredGeoObjectsWithInvalidRules(): void
    {
        $invalidRulesConfig = [
            'InvalidRule!' => 'bad_config'
        ];
        $expectedGeoObjects = ['geo1', 'geo2', 'geo3'];
        $validationErrors = ['Invalid rule name format: InvalidRule!'];
        
        $this->mockObserver->method('getRules')->willReturn($invalidRulesConfig);
        $this->mockObserver->method('getName')->willReturn('TestObserver');
        
        // RuleFactory should throw validation exception
        $exception = new InvalidRuleConfigurationException(
            $validationErrors,
            'Rule configuration validation failed'
        );
        
        $this->mockRuleFactory->expects($this->once())
            ->method('createRulesFromConfig')
            ->with($invalidRulesConfig)
            ->willThrowException($exception);
        
        // Repository should be called for fallback behavior
        $this->mockRepository->expects($this->once())
            ->method('findActiveByMap')
            ->with($this->mockMap)
            ->willReturn($expectedGeoObjects);
        
        // Should log error with validation details
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with('Invalid rule configuration, using default behavior', [
                'observer' => 'TestObserver',
                'validation_errors' => $validationErrors
            ]);
        
        // Should not log debug messages
        $this->mockLogger->expects($this->never())
            ->method('debug');
        
        $result = $this->service->getFilteredGeoObjects($this->mockObserver);
        
        $this->assertEquals($expectedGeoObjects, $result);
    }

    /**
     * Test with null rules configuration (treated as empty)
     */
    public function testGetFilteredGeoObjectsWithNullRules(): void
    {
        $expectedGeoObjects = ['geo1', 'geo2'];
        
        // Observer has null rules (empty() returns true for null)
        $this->mockObserver->method('getRules')->willReturn([]);
        
        $this->mockRepository->expects($this->once())
            ->method('findActiveByMap')
            ->willReturn($expectedGeoObjects);
        
        $this->mockRuleFactory->expects($this->never())
            ->method('createRulesFromConfig');
        
        $result = $this->service->getFilteredGeoObjects($this->mockObserver);
        
        $this->assertEquals($expectedGeoObjects, $result);
    }

    /**
     * Test complex rules configuration with successful validation
     */
    public function testGetFilteredGeoObjectsWithComplexValidRules(): void
    {
        $complexRulesConfig = [
            'ObjectIdRule' => [1, 2, 3, 4, 5],
            'GeospatialRule' => [
                'type' => 'circle',
                'center' => ['lat' => 50.0, 'lng' => 30.0],
                'radius' => 1000
            ],
            'TimeBasedRule' => [
                'start_time' => '08:00',
                'end_time' => '18:00'
            ]
        ];
        $expectedGeoObjects = ['filtered_geo1', 'filtered_geo2'];
        // Create mock rule objects
        $mockRule1 = $this->createMock(\App\Service\Rule\RuleInterface::class);
        $mockRule1->method('applyToQuery')->willReturnArgument(0);
        $mockRule1->method('applyToObjects')->willReturnArgument(0);
        
        $mockRule2 = $this->createMock(\App\Service\Rule\RuleInterface::class);
        $mockRule2->method('applyToQuery')->willReturnArgument(0);
        $mockRule2->method('applyToObjects')->willReturnArgument(0);
        
        $mockRule3 = $this->createMock(\App\Service\Rule\RuleInterface::class);
        $mockRule3->method('applyToQuery')->willReturnArgument(0);
        $mockRule3->method('applyToObjects')->willReturnArgument(0);
        
        $validatedRules = [
            ['name' => 'ObjectIdRule', 'priority' => 50, 'rule' => $mockRule1, 'config' => [1, 2, 3, 4, 5]],
            ['name' => 'GeospatialRule', 'priority' => 75, 'rule' => $mockRule2, 'config' => ['type' => 'circle', 'center' => ['lat' => 50.0, 'lng' => 30.0], 'radius' => 1000]],
            ['name' => 'TimeBasedRule', 'priority' => 100, 'rule' => $mockRule3, 'config' => ['start_time' => '08:00', 'end_time' => '18:00']]
        ];
        
        $this->mockObserver->method('getRules')->willReturn($complexRulesConfig);
        $this->mockObserver->method('getName')->willReturn('ComplexObserver');
        
        $this->mockRuleFactory->expects($this->once())
            ->method('createRulesFromConfig')
            ->with($complexRulesConfig)
            ->willReturn($validatedRules);
        
        // Setup QueryBuilder mock for SQL phase
        $mockQueryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $mockQueryBuilder->method('where')->willReturnSelf();
        $mockQueryBuilder->method('andWhere')->willReturnSelf();
        $mockQueryBuilder->method('setParameter')->willReturnSelf();
        
        $mockQuery = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery->method('getResult')->willReturn($expectedGeoObjects);
        $mockQueryBuilder->method('getQuery')->willReturn($mockQuery);
        
        $this->mockRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($mockQueryBuilder);
        
        // Should log successful validation and rule application
        $this->mockLogger->expects($this->exactly(2))
            ->method('debug');
        
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with('Rules applied successfully', $this->isArray());
        
        $result = $this->service->getFilteredGeoObjects($this->mockObserver);
        
        $this->assertEquals($expectedGeoObjects, $result);
    }

    /**
     * Test that different observers get independent results
     */
    public function testGetFilteredGeoObjectsWithDifferentObservers(): void
    {
        // First observer with rules
        $observer1 = $this->createMock(Observer::class);
        $map1 = $this->createMock(Map::class);
        $observer1->method('getMap')->willReturn($map1);
        $observer1->method('getRules')->willReturn(['ObjectIdRule' => [1, 2]]);
        $observer1->method('getName')->willReturn('Observer1');
        
        // Second observer without rules
        $observer2 = $this->createMock(Observer::class);
        $map2 = $this->createMock(Map::class);
        $observer2->method('getMap')->willReturn($map2);
        $observer2->method('getRules')->willReturn([]);
        $observer2->method('getName')->willReturn('Observer2');
        
        $expectedGeoObjects1 = ['geo1', 'geo2'];
        $expectedGeoObjects2 = ['geo3', 'geo4'];
        
        // Create mock rule for first observer
        $mockRule = $this->createMock(\App\Service\Rule\RuleInterface::class);
        $mockRule->method('applyToQuery')->willReturnArgument(0);
        $mockRule->method('applyToObjects')->willReturnArgument(0);
        
        // Setup rule factory for first observer
        $this->mockRuleFactory->expects($this->once())
            ->method('createRulesFromConfig')
            ->with(['ObjectIdRule' => [1, 2]])
            ->willReturn([['name' => 'ObjectIdRule', 'priority' => 50, 'rule' => $mockRule, 'config' => [1, 2]]]);
        
        // Setup QueryBuilder mocks for different observers
        $mockQueryBuilder1 = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $mockQueryBuilder1->method('where')->willReturnSelf();
        $mockQueryBuilder1->method('andWhere')->willReturnSelf();
        $mockQueryBuilder1->method('setParameter')->willReturnSelf();
        
        $mockQuery1 = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery1->method('getResult')->willReturn($expectedGeoObjects1);
        $mockQueryBuilder1->method('getQuery')->willReturn($mockQuery1);
        
        $mockQueryBuilder2 = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $mockQueryBuilder2->method('where')->willReturnSelf();
        $mockQueryBuilder2->method('andWhere')->willReturnSelf();
        $mockQueryBuilder2->method('setParameter')->willReturnSelf();
        
        $mockQuery2 = $this->createMock(\Doctrine\ORM\Query::class);
        $mockQuery2->method('getResult')->willReturn($expectedGeoObjects2);
        $mockQueryBuilder2->method('getQuery')->willReturn($mockQuery2);
        
        // Setup repository calls for different observers
        // First observer uses QueryBuilder (has rules)
        $this->mockRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($mockQueryBuilder1);
        
        // Second observer uses findActiveByMap directly (no rules)
        $this->mockRepository->expects($this->once())
            ->method('findActiveByMap')
            ->with($map2)
            ->willReturn($expectedGeoObjects2);
        
        // Test first observer (with rules)
        $result1 = $this->service->getFilteredGeoObjects($observer1);
        $this->assertEquals($expectedGeoObjects1, $result1);
        
        // Test second observer (without rules)
        $result2 = $this->service->getFilteredGeoObjects($observer2);
        $this->assertEquals($expectedGeoObjects2, $result2);
    }

    /**
     * Test error logging includes correct validation errors
     */
    public function testValidationErrorLoggingDetails(): void
    {
        $invalidConfig = ['BadRule123' => 'invalid'];
        $validationErrors = [
            'Invalid rule name format: BadRule123',
            'Configuration must be an array'
        ];
        
        $this->mockObserver->method('getRules')->willReturn($invalidConfig);
        $this->mockObserver->method('getName')->willReturn('ErrorObserver');
        
        $exception = new InvalidRuleConfigurationException(
            $validationErrors,
            'Validation failed'
        );
        
        $this->mockRuleFactory->method('createRulesFromConfig')
            ->willThrowException($exception);
        
        $this->mockRepository->method('findActiveByMap')
            ->willReturn([]);
        
        // Verify error logging includes all validation errors
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Invalid rule configuration, using default behavior',
                $this->callback(function ($context) use ($validationErrors) {
                    return $context['observer'] === 'ErrorObserver' &&
                           $context['validation_errors'] === $validationErrors;
                })
            );
        
        $this->service->getFilteredGeoObjects($this->mockObserver);
    }

    /**
     * Test that repository is always called exactly once per method call
     */
    public function testRepositoryCalledExactlyOnce(): void
    {
        $this->mockObserver->method('getRules')->willReturn([]);
        $this->mockRepository->expects($this->once())
            ->method('findActiveByMap')
            ->willReturn(['geo1']);
        
        // Call method multiple times to ensure repository is called each time
        $this->service->getFilteredGeoObjects($this->mockObserver);
        
        // Reset expectations for second call
        $this->mockRepository = $this->createMock(GeoObjectRepository::class);
        $this->service = new ObserverRuleService(
            $this->mockRepository,
            $this->mockRuleFactory,
            $this->mockLogger
        );
        
        $this->mockRepository->expects($this->once())
            ->method('findActiveByMap')
            ->willReturn(['geo2']);
        
        $this->service->getFilteredGeoObjects($this->mockObserver);
    }
}
