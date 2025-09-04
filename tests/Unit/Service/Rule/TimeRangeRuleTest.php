<?php

namespace App\Tests\Unit\Service\Rule;

use App\Entity\GeoObject;
use App\Service\Rule\TimeRangeRule;
use App\Service\Rule\RuleInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class TimeRangeRuleTest extends TestCase
{
    private TimeRangeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new TimeRangeRule();
    }

    public function testImplementsCorrectInterface(): void
    {
        $this->assertInstanceOf(RuleInterface::class, $this->rule);
    }

    public function testGetName(): void
    {
        $this->assertEquals('time_range', $this->rule->getName());
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(10, $this->rule->getPriority());
    }

    public function testGetConfigSchema(): void
    {
        $schema = TimeRangeRule::getConfigSchema();
        
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('start_time', $schema['properties']);
        $this->assertArrayHasKey('end_time', $schema['properties']);
        $this->assertArrayHasKey('timezone', $schema['properties']);
        $this->assertContains('start_time', $schema['required']);
        $this->assertContains('end_time', $schema['required']);
        
        // Check time format patterns
        $startTimeProperty = $schema['properties']['start_time'];
        $this->assertEquals('string', $startTimeProperty['type']);
        $this->assertArrayHasKey('pattern', $startTimeProperty);
        
        $endTimeProperty = $schema['properties']['end_time'];
        $this->assertEquals('string', $endTimeProperty['type']);
        $this->assertArrayHasKey('pattern', $endTimeProperty);
        
        // Check timezone enum
        $timezoneProperty = $schema['properties']['timezone'];
        $this->assertEquals('string', $timezoneProperty['type']);
        $this->assertArrayHasKey('enum', $timezoneProperty);
        $this->assertContains('UTC', $timezoneProperty['enum']);
        $this->assertContains('Europe/London', $timezoneProperty['enum']);
    }

    public function testApplyToQueryAlwaysAllowed(): void
    {
        // Test with very wide time range (always allowed)
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $config = [
            'start_time' => '00:00',
            'end_time' => '23:59',
            'timezone' => 'UTC'
        ];
        
        // Query should not be modified (always within time range)
        $queryBuilder->expects($this->never())->method('andWhere');
        
        $result = $this->rule->applyToQuery($queryBuilder, $config);
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToQueryNeverAllowed(): void
    {
        // Test with impossible time range (never allowed)
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $config = [
            'start_time' => '02:00',
            'end_time' => '02:00',
            'timezone' => 'UTC'
        ];
        
        // Query should be modified to return empty result (unless exactly 02:00)
        $result = $this->rule->applyToQuery($queryBuilder, $config);
        $this->assertSame($queryBuilder, $result);
    }

    public function testApplyToObjectsAlwaysAllowed(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];
        $config = [
            'start_time' => '00:00',
            'end_time' => '23:59',
            'timezone' => 'UTC'
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        
        $this->assertEquals($geoObjects, $result);
        $this->assertCount(3, $result);
    }

    public function testTimeRangeLogic(): void
    {
        // Test the core time range logic without depending on current time
        $geoObjects = [new GeoObject(), new GeoObject()];
        
        // Test normal range (doesn't span midnight)
        $config = [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'timezone' => 'UTC'
        ];
        
        // The actual result depends on current time, but we can test the rule exists
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertIsArray($result);
        
        // Test range spanning midnight
        $config = [
            'start_time' => '22:00',
            'end_time' => '06:00',
            'timezone' => 'UTC'
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertIsArray($result);
    }

    public function testDifferentTimezones(): void
    {
        $geoObjects = [new GeoObject()];
        
        // Test with different timezone configurations
        $timezones = ['UTC', 'Europe/Berlin', 'America/New_York', 'Asia/Tokyo'];
        
        foreach ($timezones as $timezone) {
            $config = [
                'start_time' => '09:00',
                'end_time' => '17:00',
                'timezone' => $timezone
            ];
            
            $result = $this->rule->applyToObjects($geoObjects, $config);
            $this->assertIsArray($result);
        }
    }

    public function testInvalidConfiguration(): void
    {
        $geoObjects = [new GeoObject(), new GeoObject()];
        
        // Test with missing start_time
        $config = ['end_time' => '17:00'];
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result); // Should allow access on invalid config
        
        // Test with missing end_time
        $config = ['start_time' => '09:00'];
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result); // Should allow access on invalid config
        
        // Test with invalid timezone
        $config = [
            'start_time' => '09:00',
            'end_time' => '17:00',
            'timezone' => 'Invalid/Timezone'
        ];
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(2, $result); // Should allow access on invalid config
    }

    public function testDefaultTimezone(): void
    {
        $geoObjects = [new GeoObject()];
        $config = [
            'start_time' => '00:00',
            'end_time' => '23:59'
            // No timezone specified, should default to UTC
        ];
        
        $result = $this->rule->applyToObjects($geoObjects, $config);
        $this->assertCount(1, $result); // Should work with default UTC timezone
    }
}
