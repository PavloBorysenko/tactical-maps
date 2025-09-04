<?php

namespace App\Tests\Unit\Service\Rule;

use App\Entity\GeoObject;
use App\Service\Rule\TimeLimitRule;
use App\Service\Rule\StatefulRuleInterface;
use App\Service\Rule\RuleInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class TimeLimitRuleTest extends TestCase
{
    private TimeLimitRule $rule;

    protected function setUp(): void
    {
        $this->rule = new TimeLimitRule();
    }

    public function testImplementsCorrectInterfaces(): void
    {
        $this->assertInstanceOf(StatefulRuleInterface::class, $this->rule);
        $this->assertInstanceOf(RuleInterface::class, $this->rule);
    }

    public function testGetName(): void
    {
        $this->assertEquals('time_limit', $this->rule->getName());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(20, $this->rule->getPriority());
    }

    public function testGetConfigSchema(): void
    {
        $schema = TimeLimitRule::getConfigSchema();
        
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('duration_seconds', $schema['properties']);
        $this->assertArrayHasKey('_state', $schema['properties']);
        $this->assertContains('duration_seconds', $schema['required']);
        
        // Check duration_seconds property
        $durationProperty = $schema['properties']['duration_seconds'];
        $this->assertEquals('integer', $durationProperty['type']);
        $this->assertEquals(1, $durationProperty['minimum']);
        
        // Check state structure
        $stateProperty = $schema['properties']['_state'];
        $this->assertEquals('object', $stateProperty['type']);
        $this->assertArrayHasKey('first_used_at', $stateProperty['properties']);
        $this->assertArrayHasKey('expires_at', $stateProperty['properties']);
        $this->assertArrayHasKey('last_used_at', $stateProperty['properties']);
        $this->assertContains('first_used_at', $stateProperty['required']);
        $this->assertContains('expires_at', $stateProperty['required']);
    }

    public function testInitializeRuleState(): void
    {
        $config = ['duration_seconds' => 60]; // 1 minute
        $beforeTime = time();
        
        $state = $this->rule->initializeRuleState($config);
        
        $afterTime = time();
        
        $this->assertIsArray($state);
        $this->assertArrayHasKey('first_used_at', $state);
        $this->assertArrayHasKey('expires_at', $state);
        $this->assertArrayHasKey('last_used_at', $state);
        
        $this->assertGreaterThanOrEqual($beforeTime, $state['first_used_at']);
        $this->assertLessThanOrEqual($afterTime, $state['first_used_at']);
        $this->assertEquals($state['first_used_at'] + 60, $state['expires_at']);
        $this->assertNull($state['last_used_at']);
    }

    public function testInitializeRuleStateWithDefaultDuration(): void
    {
        $config = [];
        $beforeTime = time();
        
        $state = $this->rule->initializeRuleState($config);
        
        $this->assertEquals($state['first_used_at'] + 300, $state['expires_at']); // Default 5 minutes
    }

    public function testUpdateRuleStateUpdatesLastUsed(): void
    {
        $pastTime = time() - 100;
        $config = [
            'duration_seconds' => 60,
            '_state' => [
                'first_used_at' => $pastTime,
                'expires_at' => $pastTime + 60,
                'last_used_at' => null
            ]
        ];
        
        $beforeUpdate = time();
        $newState = $this->rule->updateRuleState($config);
        $afterUpdate = time();
        
        // first_used_at and expires_at should remain unchanged
        $this->assertEquals($pastTime, $newState['first_used_at']);
        $this->assertEquals($pastTime + 60, $newState['expires_at']);
        
        // last_used_at should be updated
        $this->assertGreaterThanOrEqual($beforeUpdate, $newState['last_used_at']);
        $this->assertLessThanOrEqual($afterUpdate, $newState['last_used_at']);
    }

    public function testUpdateRuleStateWithoutExistingState(): void
    {
        $config = ['duration_seconds' => 30];
        $beforeTime = time();
        
        $newState = $this->rule->updateRuleState($config);
        
        $afterTime = time();
        
        // Should initialize state and set last_used_at
        $this->assertGreaterThanOrEqual($beforeTime, $newState['first_used_at']);
        $this->assertLessThanOrEqual($afterTime, $newState['first_used_at']);
        $this->assertEquals($newState['first_used_at'] + 30, $newState['expires_at']);
        $this->assertGreaterThanOrEqual($beforeTime, $newState['last_used_at']);
        $this->assertLessThanOrEqual($afterTime, $newState['last_used_at']);
    }

    public function testApplyToQueryWithinTimeLimit(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $currentTime = time();
        $config = [
            'duration_seconds' => 60,
            '_state' => [
                'first_used_at' => $currentTime - 30, // 30 seconds ago
                'expires_at' => $currentTime + 30,     // Expires in 30 seconds
                'last_used_at' => $currentTime - 10
            ]
        ];
        
        // Query should not be modified (within time limit)
        $queryBuilder->expects($this->never())->method('andWhere');
        
        $result = $this->rule->applyToQuery($queryBuilder, $config);
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToQueryTimeLimitExceeded(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $currentTime = time();
        $config = [
            'duration_seconds' => 60,
            '_state' => [
                'first_used_at' => $currentTime - 120, // 2 minutes ago
                'expires_at' => $currentTime - 60,     // Expired 1 minute ago
                'last_used_at' => $currentTime - 30
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

    public function testApplyToObjectsWithinTimeLimit(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $currentTime = time();
        $config = [
            'duration_seconds' => 120,
            '_state' => [
                'first_used_at' => $currentTime - 60, // 1 minute ago
                'expires_at' => $currentTime + 60,     // Expires in 1 minute
                'last_used_at' => $currentTime - 10
            ]
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        
        $this->assertEquals($geoObjects, $result);
        $this->assertCount(3, $result);
    }

    public function testApplyToObjectsTimeLimitExceeded(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $currentTime = time();
        $config = [
            'duration_seconds' => 60,
            '_state' => [
                'first_used_at' => $currentTime - 180, // 3 minutes ago
                'expires_at' => $currentTime - 120,    // Expired 2 minutes ago
                'last_used_at' => $currentTime - 90
            ]
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        
        $this->assertEmpty($result);
    }

    public function testApplyToObjectsWithoutState(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject()];
        $config = ['duration_seconds' => 60];
        
        // Without state, should allow access (state will be initialized later)
        $result = $this->rule->applyToObjects($geoObjects, $config);
        
        $this->assertEquals($geoObjects, $result);
        $this->assertCount(2, $result);
    }

    public function testCompleteWorkflowWithTimeProgression(): void
    {
        $config = ['duration_seconds' => 2]; // 2 seconds for quick test
        $geoObjects = [new GeoObject(), new GeoObject()];
        
        // Step 1: Initialize state
        $initialState = $this->rule->initializeRuleState($config);
        $config['_state'] = $initialState;
        
        $this->assertIsInt($config['_state']['first_used_at']);
        $this->assertEquals($config['_state']['first_used_at'] + 2, $config['_state']['expires_at']);
        
        // Step 2: Immediate access - should work
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result);
        
        // Step 3: Update state (simulate usage)
        $config['_state'] = $this->rule->updateRuleState($config);
        $this->assertIsInt($config['_state']['last_used_at']);
        
        // Step 4: Access after 1 second - should still work
        sleep(1);
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result);
        
        // Step 5: Access after expiration - should be blocked
        sleep(2); // Total 3 seconds, rule expires after 2
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertEmpty($result);
    }

    public function testTimeExpirationBoundary(): void
    {
        $geoObjects = [new GeoObject()];
        
        // Test with expired time (clearly in the past)
        $config = [
            'duration_seconds' => 60,
            '_state' => [
                'first_used_at' => time() - 120,
                'expires_at' => time() - 60,      // Expired 1 minute ago
                'last_used_at' => time() - 90
            ]
        ];
        
        // Should be blocked (expired)
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertEmpty($result);
        
        // Test with future expiration time
        $config['_state']['expires_at'] = time() + 60; // Expires in 1 minute
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(1, $result);
    }
}
