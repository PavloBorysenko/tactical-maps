<?php

/**
 * Unit tests for SideIdRule
 * 
 * @category Tests
 * @package  App\Tests\Unit\Service\Rule
 * @author   Tactical Maps Team
 * @license  MIT
 * @link     https://github.com/tactical-maps
 */

namespace App\Tests\Unit\Service\Rule;

use App\Service\Rule\SideIdRule;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for SideIdRule
 * 
 * Tests side-based filtering functionality for geo objects.
 */
class SideIdRuleTest extends TestCase
{
    private SideIdRule $_rule;
    private MockObject $_mockQueryBuilder;
    private MockObject $_mockEntityManager;

    /**
     * Set up test fixtures
     * 
     * @return void
     */
    protected function setUp(): void
    {
        $this->_rule = new SideIdRule();
        $this->_mockEntityManager = $this->createMock(EntityManagerInterface::class);
        $this->_mockQueryBuilder = $this->createMock(QueryBuilder::class);
    }

    /**
     * Test applying rule with valid side IDs
     * 
     * @return void
     */
    public function testApplyToQueryWithValidSideIds(): void
    {
        // Arrange
        $config = [1, 2, 3];
        
        // Mock query builder methods
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('g.side', 's')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('s.id IN (:allowedSideIds)')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('allowedSideIds', [1, 2, 3])
            ->willReturnSelf();
        
        // Act
        $result = $this->_rule->applyToQuery($this->_mockQueryBuilder, $config);
        
        // Assert
        $this->assertSame($this->_mockQueryBuilder, $result);
    }

    /**
     * Test applying rule with empty configuration
     * 
     * @return void
     */
    public function testApplyToQueryWithEmptyConfig(): void
    {
        // Arrange
        $config = [];
        
        // Mock should not be called for empty config
        $this->_mockQueryBuilder
            ->expects($this->never())
            ->method('leftJoin');
        
        $this->_mockQueryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->_mockQueryBuilder
            ->expects($this->never())
            ->method('setParameter');
        
        // Act
        $result = $this->_rule->applyToQuery($this->_mockQueryBuilder, $config);
        
        // Assert
        $this->assertSame($this->_mockQueryBuilder, $result);
    }

    /**
     * Test applying rule with invalid side IDs (non-numeric, zero, negative)
     * 
     * @return void
     */
    public function testApplyToQueryWithInvalidSideIds(): void
    {
        // Arrange
        $config = ['invalid', 0, -1, 'string', null];
        
        // Mock should not be called for invalid config
        $this->_mockQueryBuilder
            ->expects($this->never())
            ->method('leftJoin');
        
        $this->_mockQueryBuilder
            ->expects($this->never())
            ->method('andWhere');
        
        $this->_mockQueryBuilder
            ->expects($this->never())
            ->method('setParameter');
        
        // Act
        $result = $this->_rule->applyToQuery($this->_mockQueryBuilder, $config);
        
        // Assert
        $this->assertSame($this->_mockQueryBuilder, $result);
    }

    /**
     * Test applying rule with mixed valid and invalid side IDs
     * 
     * @return void
     */
    public function testApplyToQueryWithMixedValidInvalidSideIds(): void
    {
        // Arrange
        $config = [1, 'invalid', 2, 0, 3, -1, 'string'];
        $expectedValidIds = [1, 2, 3];
        
        // Mock query builder methods
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('g.side', 's')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('s.id IN (:allowedSideIds)')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('allowedSideIds', $expectedValidIds)
            ->willReturnSelf();
        
        // Act
        $result = $this->_rule->applyToQuery($this->_mockQueryBuilder, $config);
        
        // Assert
        $this->assertSame($this->_mockQueryBuilder, $result);
    }

    /**
     * Test rule name
     * 
     * @return void
     */
    public function testGetName(): void
    {
        // Act
        $name = $this->_rule->getName();
        
        // Assert
        $this->assertEquals('SideIdRule', $name);
    }

    /**
     * Test rule priority
     * 
     * @return void
     */
    public function testGetPriority(): void
    {
        // Act
        $priority = $this->_rule->getPriority();
        
        // Assert
        $this->assertEquals(75, $priority);
        $this->assertGreaterThan(50, $priority); // Lower priority than ObjectIdRule (50)
        $this->assertLessThan(100, $priority);   // Higher priority than default (100)
    }

    /**
     * Test configuration schema
     * 
     * @return void
     */
    public function testGetConfigSchema(): void
    {
        // Act
        $schema = SideIdRule::getConfigSchema();
        
        // Assert
        $this->assertIsArray($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertEquals('integer', $schema['items']['type']);
        $this->assertEquals(1, $schema['items']['minimum']);
        $this->assertEquals(1, $schema['minItems']);
        $this->assertEquals(50, $schema['maxItems']);
        $this->assertTrue($schema['uniqueItems']);
    }

    /**
     * Test applyToObjects method (inherited from AbstractObserverRule)
     * 
     * @return void
     */
    public function testApplyToObjects(): void
    {
        // Arrange
        $geoObjects = ['object1', 'object2', 'object3'];
        $config = [1, 2, 3];
        
        // Act - should return unchanged array (default implementation)
        $result = $this->_rule->applyToObjects($geoObjects, $config);
        
        // Assert
        $this->assertSame($geoObjects, $result);
    }

    /**
     * Test with string numeric IDs (should be converted to integers)
     * 
     * @return void
     */
    public function testApplyToQueryWithStringNumericIds(): void
    {
        // Arrange
        $config = ['1', '2', '3'];
        $expectedValidIds = [1, 2, 3];
        
        // Mock query builder methods
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('g.side', 's')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('s.id IN (:allowedSideIds)')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('allowedSideIds', $expectedValidIds)
            ->willReturnSelf();
        
        // Act
        $result = $this->_rule->applyToQuery($this->_mockQueryBuilder, $config);
        
        // Assert
        $this->assertSame($this->_mockQueryBuilder, $result);
    }

    /**
     * Test with single side ID
     * 
     * @return void
     */
    public function testApplyToQueryWithSingleSideId(): void
    {
        // Arrange
        $config = [42];
        
        // Mock query builder methods
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('leftJoin')
            ->with('g.side', 's')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('s.id IN (:allowedSideIds)')
            ->willReturnSelf();
        
        $this->_mockQueryBuilder
            ->expects($this->once())
            ->method('setParameter')
            ->with('allowedSideIds', [42])
            ->willReturnSelf();
        
        // Act
        $result = $this->_rule->applyToQuery($this->_mockQueryBuilder, $config);
        
        // Assert
        $this->assertSame($this->_mockQueryBuilder, $result);
    }
}
