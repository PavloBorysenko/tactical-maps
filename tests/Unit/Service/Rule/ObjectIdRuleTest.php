<?php

namespace App\Tests\Unit\Service\Rule;

use App\Service\Rule\ObjectIdRule;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ObjectIdRule
 * 
 * Tests ID filtering, validation, priority, and schema configuration.
 * Uses mock QueryBuilder to avoid database dependencies.
 */
class ObjectIdRuleTest extends TestCase
{
    private ObjectIdRule $rule;
    private QueryBuilder $mockQueryBuilder;
    private EntityManagerInterface $mockEntityManager;

    protected function setUp(): void
    {
        $this->rule = new ObjectIdRule();
        
        // Create mock EntityManager
        $this->mockEntityManager = $this->createMock(EntityManagerInterface::class);
        
        // Create mock QueryBuilder
        $this->mockQueryBuilder = $this->createMock(QueryBuilder::class);
    }

    /**
     * Test successful ID filtering with valid configuration
     */
    public function testApplyToQueryWithValidIds(): void
    {
        $config = [1, 2, 3, 5];
        
        // Expect andWhere to be called with correct condition
        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('g.id IN (:allowedIds)')
            ->willReturn($this->mockQueryBuilder);
        
        // Expect setParameter to be called with filtered and converted IDs
        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('allowedIds', [1, 2, 3, 5])
            ->willReturn($this->mockQueryBuilder);
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    /**
     * Test ID filtering with mixed valid and invalid IDs
     */
    public function testApplyToQueryWithMixedIds(): void
    {
        $config = [1, 'invalid', 3, -5, 0, '7', 8.5];
        
        // Only valid positive numbers should be used: 1, 3, 7, 8 (8.5 becomes 8)
        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('g.id IN (:allowedIds)')
            ->willReturn($this->mockQueryBuilder);
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('allowedIds', [0 => 1, 2 => 3, 5 => 7, 6 => 8]) // Valid IDs with original indices
            ->willReturn($this->mockQueryBuilder);
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    /**
     * Test empty configuration returns unchanged QueryBuilder
     */
    public function testApplyToQueryWithEmptyConfig(): void
    {
        $config = [];
        
        // Should not call andWhere or setParameter
        $this->mockQueryBuilder->expects($this->never())
            ->method('andWhere');
        
        $this->mockQueryBuilder->expects($this->never())
            ->method('setParameter');
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    /**
     * Test configuration with only invalid IDs returns unchanged QueryBuilder
     */
    public function testApplyToQueryWithOnlyInvalidIds(): void
    {
        $config = ['invalid', -1, 0, -5];
        
        // Should not call andWhere or setParameter since no valid IDs
        $this->mockQueryBuilder->expects($this->never())
            ->method('andWhere');
        
        $this->mockQueryBuilder->expects($this->never())
            ->method('setParameter');
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    /**
     * Test string numeric IDs are properly converted
     */
    public function testApplyToQueryWithStringNumericIds(): void
    {
        $config = ['1', '2', '3'];
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('g.id IN (:allowedIds)')
            ->willReturn($this->mockQueryBuilder);
        
        // String IDs should be converted to integers
        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('allowedIds', [1, 2, 3])
            ->willReturn($this->mockQueryBuilder);
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    /**
     * Test rule priority is high (50)
     */
    public function testGetPriority(): void
    {
        $priority = $this->rule->getPriority();
        
        $this->assertEquals(50, $priority);
        $this->assertIsInt($priority);
    }

    /**
     * Test rule name matches class name
     */
    public function testGetName(): void
    {
        $name = $this->rule->getName();
        
        $this->assertEquals('ObjectIdRule', $name);
    }

    /**
     * Test static config schema structure
     */
    public function testGetConfigSchema(): void
    {
        $schema = ObjectIdRule::getConfigSchema();
        
        // Verify schema structure
        $this->assertIsArray($schema);
        $this->assertEquals('array', $schema['type']);
        $this->assertIsArray($schema['items']);
        $this->assertEquals('integer', $schema['items']['type']);
        $this->assertEquals(1, $schema['items']['minimum']);
        $this->assertEquals(1, $schema['minItems']);
        $this->assertEquals(100, $schema['maxItems']);
        $this->assertTrue($schema['uniqueItems']);
    }

    /**
     * Test schema validation requirements
     */
    public function testSchemaValidationRequirements(): void
    {
        $schema = ObjectIdRule::getConfigSchema();
        
        // Schema should enforce positive integers only
        $this->assertArrayHasKey('minimum', $schema['items']);
        $this->assertEquals(1, $schema['items']['minimum']);
        
        // Schema should require at least one item
        $this->assertArrayHasKey('minItems', $schema);
        $this->assertEquals(1, $schema['minItems']);
        
        // Schema should limit maximum items for performance
        $this->assertArrayHasKey('maxItems', $schema);
        $this->assertEquals(100, $schema['maxItems']);
        
        // Schema should enforce unique IDs
        $this->assertArrayHasKey('uniqueItems', $schema);
        $this->assertTrue($schema['uniqueItems']);
    }

    /**
     * Test large ID list handling
     */
    public function testApplyToQueryWithLargeIdList(): void
    {
        // Create array with many IDs
        $config = range(1, 50);
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('g.id IN (:allowedIds)')
            ->willReturn($this->mockQueryBuilder);
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('allowedIds', $config)
            ->willReturn($this->mockQueryBuilder);
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }

    /**
     * Test duplicate IDs are handled correctly
     */
    public function testApplyToQueryWithDuplicateIds(): void
    {
        $config = [1, 2, 2, 3, 1, 3];
        
        $this->mockQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('g.id IN (:allowedIds)')
            ->willReturn($this->mockQueryBuilder);
        
        // Duplicates should be preserved in the parameter
        // (SQL IN clause handles duplicates automatically)
        $this->mockQueryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('allowedIds', [1, 2, 2, 3, 1, 3])
            ->willReturn($this->mockQueryBuilder);
        
        $result = $this->rule->applyToQuery($this->mockQueryBuilder, $config);
        
        $this->assertSame($this->mockQueryBuilder, $result);
    }
}
