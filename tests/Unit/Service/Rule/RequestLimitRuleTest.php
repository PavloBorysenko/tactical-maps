<?php

namespace App\Tests\Unit\Service\Rule;

use App\Entity\GeoObject;
use App\Service\Rule\RequestLimitRule;
use App\Service\Rule\StatefulRuleInterface;
use App\Service\Rule\RuleInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class RequestLimitRuleTest extends TestCase
{
    private RequestLimitRule $rule;

    protected function setUp(): void
    {
        $this->rule = new RequestLimitRule();
    }

    public function testImplementsCorrectInterfaces(): void
    {
        $this->assertInstanceOf(StatefulRuleInterface::class, $this->rule);
        $this->assertInstanceOf(RuleInterface::class, $this->rule);
    }

    public function testGetName(): void
    {
        $this->assertEquals('request_limit', $this->rule->getName());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(30, $this->rule->getPriority());
    }

    public function testGetConfigSchema(): void
    {
        $schema = RequestLimitRule::getConfigSchema();
        
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('limit', $schema['properties']);
        $this->assertArrayHasKey('_state', $schema['properties']);
        $this->assertContains('limit', $schema['required']);
        
        // Check limit property
        $limitProperty = $schema['properties']['limit'];
        $this->assertEquals('integer', $limitProperty['type']);
        $this->assertEquals(1, $limitProperty['minimum']);
        
        // Check state structure
        $stateProperty = $schema['properties']['_state'];
        $this->assertEquals('object', $stateProperty['type']);
        $this->assertArrayHasKey('remaining', $stateProperty['properties']);
        $this->assertArrayHasKey('initialized_at', $stateProperty['properties']);
        $this->assertContains('remaining', $stateProperty['required']);
        $this->assertContains('initialized_at', $stateProperty['required']);
    }

    public function testInitializeRuleState(): void
    {
        $config = ['limit' => 5];
        $state = $this->rule->initializeRuleState($config);
        
        $this->assertIsArray($state);
        $this->assertArrayHasKey('remaining', $state);
        $this->assertArrayHasKey('initialized_at', $state);
        $this->assertArrayHasKey('last_used_at', $state);
        
        $this->assertEquals(5, $state['remaining']);
        $this->assertIsInt($state['initialized_at']);
        $this->assertNull($state['last_used_at']);
    }

    public function testInitializeRuleStateWithDefaultLimit(): void
    {
        $config = [];
        $state = $this->rule->initializeRuleState($config);
        
        $this->assertEquals(10, $state['remaining']); // Default limit
    }

    public function testUpdateRuleStateDecrementsCounter(): void
    {
        $config = [
            'limit' => 5,
            '_state' => [
                'remaining' => 3,
                'initialized_at' => time() - 100,
                'last_used_at' => null
            ]
        ];
        
        $newState = $this->rule->updateRuleState($config);
        
        $this->assertEquals(2, $newState['remaining']); // Decremented by 1
        $this->assertIsInt($newState['last_used_at']);
        $this->assertGreaterThan(0, $newState['last_used_at']);
    }

    public function testUpdateRuleStateMinimumZero(): void
    {
        $config = [
            'limit' => 5,
            '_state' => [
                'remaining' => 0,
                'initialized_at' => time() - 100,
                'last_used_at' => time() - 50
            ]
        ];
        
        $newState = $this->rule->updateRuleState($config);
        
        $this->assertEquals(0, $newState['remaining']); // Remains 0, no decrement when already 0
    }

    public function testUpdateRuleStateWithoutExistingState(): void
    {
        $config = ['limit' => 3];
        $newState = $this->rule->updateRuleState($config);
        
        $this->assertEquals(2, $newState['remaining']); // 3 - 1 = 2
        $this->assertIsInt($newState['initialized_at']);
        $this->assertIsInt($newState['last_used_at']);
    }

    public function testApplyToQueryWithinLimit(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $config = [
            'limit' => 5,
            '_state' => [
                'remaining' => 3,
                'initialized_at' => time(),
                'last_used_at' => null
            ]
        ];
        
        // Query should not be modified (within limit)
        $queryBuilder->expects($this->never())->method('andWhere');
        
        $result = $this->rule->applyToQuery($queryBuilder, $config);
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToQueryLimitExhausted(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $config = [
            'limit' => 5,
            '_state' => [
                'remaining' => 0, // Limit exhausted
                'initialized_at' => time(),
                'last_used_at' => time()
            ]
        ];
        
        // Query should be modified to return empty result
        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('1 = 0')
            ->willReturnSelf();
        
        $result = $this->rule->applyToQuery($queryBuilder, $config);
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToObjectsWithinLimit(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $config = [
            'limit' => 5,
            '_state' => [
                'remaining' => 2,
                'initialized_at' => time(),
                'last_used_at' => null
            ]
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        
        $this->assertEquals($geoObjects, $result);
        $this->assertCount(3, $result);
    }

    public function testApplyToObjectsLimitExhausted(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $config = [
            'limit' => 5,
            '_state' => [
                'remaining' => 0, // Limit exhausted
                'initialized_at' => time(),
                'last_used_at' => time()
            ]
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        
        $this->assertEmpty($result);
    }

    public function testCompleteWorkflowCountdown(): void
    {
        $config = ['limit' => 3];
        
        // Step 1: Initialize state
        $initialState = $this->rule->initializeRuleState($config);
        $config['_state'] = $initialState;
        $this->assertEquals(3, $config['_state']['remaining']);
        
        $geoObjects = [new GeoObject(), new GeoObject()];
        
        // Step 2: First request (3 -> 2)
        $config['_state'] = $this->rule->updateRuleState($config);
        $this->assertEquals(2, $config['_state']['remaining']);
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result); // Should work
        
        // Step 3: Second request (2 -> 1)
        $config['_state'] = $this->rule->updateRuleState($config);
        $this->assertEquals(1, $config['_state']['remaining']);
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result); // Should work
        
        // Step 4: Third request (1 -> 0, but uses original value 1)
        $config['_state'] = $this->rule->updateRuleState($config);
        $this->assertEquals(0, $config['_state']['remaining']);
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result); // Should work! Uses original value (1 > 0)
        
        // Step 5: Fourth request (0 -> 0)
        $config['_state'] = $this->rule->updateRuleState($config);
        $this->assertEquals(0, $config['_state']['remaining']);
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertEmpty($result); // Still blocked
    }
}
