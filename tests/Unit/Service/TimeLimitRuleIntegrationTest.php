<?php

namespace App\Tests\Unit\Service;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Entity\Observer;
use App\Repository\GeoObjectRepository;
use App\Service\ObserverRuleService;
use App\Service\Rule\RuleFactoryInterface;
use App\Service\Rule\RuleValidatorInterface;
use App\Service\Rule\TimeLimitRule;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TimeLimitRuleIntegrationTest extends TestCase
{
    private GeoObjectRepository $geoObjectRepository;
    private RuleFactoryInterface $ruleFactory;
    private RuleValidatorInterface $configValidator;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private ObserverRuleService $observerRuleService;

    protected function setUp(): void
    {
        $this->geoObjectRepository = $this->createMock(GeoObjectRepository::class);
        $this->ruleFactory = $this->createMock(RuleFactoryInterface::class);
        $this->configValidator = $this->createMock(RuleValidatorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observerRuleService = new ObserverRuleService(
            $this->geoObjectRepository,
            $this->ruleFactory,
            $this->configValidator,
            $this->entityManager,
            $this->logger
        );
    }

    public function testTimeLimitRuleIntegrationWorkflow(): void
    {
        // Arrange
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([
            'time_limit' => [
                'duration_seconds' => 60 // 1 minute
            ]
        ]);

        $timeLimitRule = new TimeLimitRule();
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];

        // Mock rule factory to return our TimeLimitRule
        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('time_limit')
            ->willReturn($timeLimitRule);

        // Mock configuration validator to pass validation
        $this->configValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Mock QueryBuilder for SQL phase
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn(
            $this->createQueryMock($geoObjects)
        );

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        // Mock entity manager for state persistence
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('refresh');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        // Act - First request (should work and initialize state)
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert - Should return objects and update observer rules with state
        $this->assertCount(3, $result);
        
        // Check that observer's rules were updated with state
        $updatedRules = $observer->getRules();
        $this->assertArrayHasKey('time_limit', $updatedRules);
        $this->assertArrayHasKey('_state', $updatedRules['time_limit']);
        
        $state = $updatedRules['time_limit']['_state'];
        $this->assertArrayHasKey('first_used_at', $state);
        $this->assertArrayHasKey('expires_at', $state);
        $this->assertArrayHasKey('last_used_at', $state);
        
        // Check time calculations
        $this->assertEquals($state['first_used_at'] + 60, $state['expires_at']);
        $this->assertIsInt($state['last_used_at']);
    }

    public function testTimeLimitRuleExpired(): void
    {
        // Arrange - Observer with expired time limit
        $pastTime = time() - 120; // 2 minutes ago
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([
            'time_limit' => [
                'duration_seconds' => 60, // 1 minute duration
                '_state' => [
                    'first_used_at' => $pastTime,
                    'expires_at' => $pastTime + 60, // Expired 1 minute ago
                    'last_used_at' => $pastTime + 30
                ]
            ]
        ]);

        $timeLimitRule = new TimeLimitRule();

        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('time_limit')
            ->willReturn($timeLimitRule);

        $this->configValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Mock QueryBuilder - should be modified to return empty result
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        
        // Expect multiple andWhere calls: first for isActive, then for time limit rule
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) use ($queryBuilder) {
                // Accept both the isActive condition and the time limit condition
                $this->assertContains($condition, ['g.isActive = true', '1 = 0']);
                return $queryBuilder;
            });
            
        $queryBuilder->method('getQuery')->willReturn(
            $this->createQueryMock([]) // Empty result due to time expiration
        );

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        // Should still update state (update last_used_at)
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('refresh');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert - Should return empty result
        $this->assertEmpty($result);
    }

    public function testTimeLimitRuleWithinTimeLimit(): void
    {
        // Arrange - Observer with active time limit
        $recentTime = time() - 30; // 30 seconds ago
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([
            'time_limit' => [
                'duration_seconds' => 60, // 1 minute duration
                '_state' => [
                    'first_used_at' => $recentTime,
                    'expires_at' => $recentTime + 60, // Expires in 30 seconds
                    'last_used_at' => $recentTime + 10
                ]
            ]
        ]);

        $timeLimitRule = new TimeLimitRule();
        $geoObjects = [new GeoObject(), new GeoObject()];

        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('time_limit')
            ->willReturn($timeLimitRule);

        $this->configValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Mock QueryBuilder - should not be modified (within time limit)
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        
        // Only expect the isActive condition, not the time limit blocking condition
        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('g.isActive = true')
            ->willReturnSelf();
            
        $queryBuilder->method('getQuery')->willReturn(
            $this->createQueryMock($geoObjects)
        );

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        // Should update state
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('refresh');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert - Should return objects
        $this->assertCount(2, $result);
    }

    private function createQueryMock(array $result)
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn($result);
        return $query;
    }
}
