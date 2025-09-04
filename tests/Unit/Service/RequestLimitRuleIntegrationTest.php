<?php

namespace App\Tests\Unit\Service;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Entity\Observer;
use App\Repository\GeoObjectRepository;
use App\Service\ObserverRuleService;
use App\Service\Rule\RuleFactoryInterface;
use App\Service\Rule\RuleValidatorInterface;
use App\Service\Rule\RequestLimitRule;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RequestLimitRuleIntegrationTest extends TestCase
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

    public function testRequestLimitRuleIntegrationWorkflow(): void
    {
        // Arrange
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([
            'request_limit' => [
                'limit' => 2
            ]
        ]);

        $requestLimitRule = new RequestLimitRule();
        $geoObjects = [new GeoObject(), new GeoObject(), new GeoObject()];

        // Mock rule factory to return our RequestLimitRule
        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('request_limit')
            ->willReturn($requestLimitRule);

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
        $this->assertArrayHasKey('request_limit', $updatedRules);
        $this->assertArrayHasKey('_state', $updatedRules['request_limit']);
        
        $state = $updatedRules['request_limit']['_state'];
        $this->assertArrayHasKey('remaining', $state);
        $this->assertEquals(1, $state['remaining']); // 2 - 1 = 1
        $this->assertArrayHasKey('initialized_at', $state);
        $this->assertArrayHasKey('last_used_at', $state);
    }

    public function testRequestLimitRuleExhausted(): void
    {
        // Arrange - Observer with exhausted limit
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([
            'request_limit' => [
                'limit' => 3,
                '_state' => [
                    'remaining' => 0, // Already exhausted
                    'initialized_at' => time() - 100,
                    'last_used_at' => time() - 10
                ]
            ]
        ]);

        $requestLimitRule = new RequestLimitRule();

        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('request_limit')
            ->willReturn($requestLimitRule);

        $this->configValidator
            ->expects($this->once())
            ->method('validateWithSchema')
            ->willReturn([]);

        // Mock QueryBuilder - should be modified to return empty result
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        
        // Expect multiple andWhere calls: first for isActive, then for limit rule
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) use ($queryBuilder) {
                // Accept both the isActive condition and the limit condition
                $this->assertContains($condition, ['g.isActive = true', '1 = 0']);
                return $queryBuilder;
            });
            
        $queryBuilder->method('getQuery')->willReturn(
            $this->createQueryMock([]) // Empty result
        );

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        // Should still update state (decrement to 0 again)
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('refresh');
        $this->entityManager->expects($this->once())->method('flush');
        $this->entityManager->expects($this->once())->method('commit');

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert - Should return empty result
        $this->assertEmpty($result);
    }

    private function createQueryMock(array $result)
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn($result);
        return $query;
    }
}
