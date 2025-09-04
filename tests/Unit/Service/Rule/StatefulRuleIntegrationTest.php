<?php

namespace App\Tests\Unit\Service\Rule;

use App\Entity\GeoObject;
use App\Service\Rule\StatefulRuleInterface;
use App\Service\Rule\RuleInterface;
use App\Tests\Unit\Service\Rule\Fixtures\MockStatefulRule;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test StatefulRuleInterface integration and behavior
 */
class StatefulRuleIntegrationTest extends TestCase
{
    private MockStatefulRule $statefulRule;

    protected function setUp(): void
    {
        $this->statefulRule = new MockStatefulRule();
    }

    public function testStatefulRuleImplementsCorrectInterfaces(): void
    {
        // Assert
        $this->assertInstanceOf(StatefulRuleInterface::class, $this->statefulRule);
        $this->assertInstanceOf(RuleInterface::class, $this->statefulRule);
    }

    public function testInitializeRuleState(): void
    {
        // Arrange
        $config = ['limit' => 5];

        // Act
        $state = $this->statefulRule->initializeRuleState($config);

        // Assert
        $this->assertIsArray($state);
        $this->assertArrayHasKey('count', $state);
        $this->assertArrayHasKey('initialized_at', $state);
        $this->assertEquals(0, $state['count']);
        $this->assertIsInt($state['initialized_at']);
    }

    public function testUpdateRuleStateIncrementsCount(): void
    {
        // Arrange
        $config = [
            'limit' => 5,
            '_state' => [
                'count' => 3,
                'initialized_at' => time() - 100
            ]
        ];

        // Act
        $newState = $this->statefulRule->updateRuleState($config);

        // Assert
        $this->assertEquals(4, $newState['count']);
        $this->assertArrayHasKey('last_used', $newState);
        $this->assertIsInt($newState['last_used']);
    }

    public function testUpdateRuleStateWithoutExistingState(): void
    {
        // Arrange
        $config = ['limit' => 5];

        // Act
        $newState = $this->statefulRule->updateRuleState($config);

        // Assert
        $this->assertEquals(1, $newState['count']);
        $this->assertArrayHasKey('initialized_at', $newState);
        $this->assertArrayHasKey('last_used', $newState);
    }

    public function testApplyToQueryWithinLimit(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $config = [
            'limit' => 10,
            '_state' => [
                'count' => 5,
                'initialized_at' => time()
            ]
        ];

        // Query should not be modified (within limit)
        $queryBuilder->expects($this->never())->method('andWhere');

        // Act
        $result = $this->statefulRule->applyToQuery($queryBuilder, $config);

        // Assert
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToQueryExceedsLimit(): void
    {
        // Arrange
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $config = [
            'limit' => 5,
            '_state' => [
                'count' => 10, // Exceeds limit
                'initialized_at' => time()
            ]
        ];

        // Query should be modified to return empty result
        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('1 = 0')
            ->willReturnSelf();

        // Act
        $result = $this->statefulRule->applyToQuery($queryBuilder, $config);

        // Assert
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToObjectsWithinLimit(): void
    {
        // Arrange
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $config = [
            'limit' => 10,
            '_state' => [
                'count' => 3,
                'initialized_at' => time()
            ]
        ];

        // Act
        $result = $this->statefulRule->applyToObjects($geoObjects, $config);

        // Assert
        $this->assertEquals($geoObjects, $result);
        $this->assertCount(3, $result);
    }

    public function testApplyToObjectsExceedsLimit(): void
    {
        // Arrange
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $config = [
            'limit' => 5,
            '_state' => [
                'count' => 10, // Exceeds limit
                'initialized_at' => time()
            ]
        ];

        // Act
        $result = $this->statefulRule->applyToObjects($geoObjects, $config);

        // Assert
        $this->assertEmpty($result);
    }

    public function testGetConfigSchemaStructure(): void
    {
        // Act
        $schema = MockStatefulRule::getConfigSchema();

        // Assert
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertArrayHasKey('_state', $schema['properties']);
        $this->assertContains('limit', $schema['required']);
    }

    public function testStatefulRuleWorkflow(): void
    {
        // Arrange - Simulate complete workflow
        $config = ['limit' => 3];
        
        // Step 1: Initialize state
        $initialState = $this->statefulRule->initializeRuleState($config);
        $config['_state'] = $initialState;
        
        $this->assertEquals(0, $config['_state']['count']);
        
        // Step 2: First usage - should work
        $config['_state'] = $this->statefulRule->updateRuleState($config);
        $this->assertEquals(1, $config['_state']['count']);
        
        $geoObjects = [new GeoObject(), new GeoObject()];
        $result = $this->statefulRule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result);
        
        // Step 3: Second usage - should still work
        $config['_state'] = $this->statefulRule->updateRuleState($config);
        $this->assertEquals(2, $config['_state']['count']);
        
        $result = $this->statefulRule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result);
        
        // Step 4: Third usage - should still work (at limit)
        $config['_state'] = $this->statefulRule->updateRuleState($config);
        $this->assertEquals(3, $config['_state']['count']);
        
        $result = $this->statefulRule->applyToObjects($geoObjects, $config);
        $this->assertEmpty($result); // Now exceeds limit
        
        // Step 5: Fourth usage - should be blocked
        $config['_state'] = $this->statefulRule->updateRuleState($config);
        $this->assertEquals(4, $config['_state']['count']);
        
        $result = $this->statefulRule->applyToObjects($geoObjects, $config);
        $this->assertEmpty($result); // Still blocked
    }
}
