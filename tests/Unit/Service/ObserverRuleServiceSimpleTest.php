<?php

namespace App\Tests\Unit\Service;

use App\Entity\GeoObject;
use App\Entity\Map;
use App\Entity\Observer;
use App\Exception\InvalidRuleConfigurationException;
use App\Repository\GeoObjectRepository;
use App\Service\ObserverRuleService;
use App\Service\Rule\RuleFactoryInterface;
use App\Service\Rule\RuleValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ObserverRuleServiceSimpleTest extends TestCase
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

    public function testGetFilteredGeoObjectsWithNoRules(): void
    {
        // Arrange
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([]);

        $expectedGeoObjects = [new GeoObject(), new GeoObject()];

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('findActiveByMap')
            ->with($map)
            ->willReturn($expectedGeoObjects);

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert
        $this->assertEquals($expectedGeoObjects, $result);
    }

    public function testGetFilteredGeoObjectsWithEmptyArrayRules(): void
    {
        // Arrange
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules([]);

        $expectedGeoObjects = [new GeoObject(), new GeoObject()];

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('findActiveByMap')
            ->with($map)
            ->willReturn($expectedGeoObjects);

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert
        $this->assertEquals($expectedGeoObjects, $result);
    }

    public function testGetFilteredGeoObjectsWithNonExistentRule(): void
    {
        // Arrange
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules(['non_existent_rule' => ['param' => 'value']]);

        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('non_existent_rule')
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Rule not found: non_existent_rule');

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn(
            $this->createQueryMock([new GeoObject()])
        );

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert
        $this->assertCount(1, $result);
    }

    // Note: Validation error test is complex due to static method calls in real implementation
    // This would require integration tests with real rule instances

    public function testGetFilteredGeoObjectsWithException(): void
    {
        // Arrange
        $map = new Map();
        $observer = new Observer();
        $observer->setMap($map);
        $observer->setRules(['test_rule' => ['param' => 'value']]);

        $this->ruleFactory
            ->expects($this->once())
            ->method('getRule')
            ->with('test_rule')
            ->willThrowException(new \Exception('Factory error'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to process rule: test_rule');

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn(
            $this->createQueryMock([new GeoObject()])
        );

        $this->geoObjectRepository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        // Act
        $result = $this->observerRuleService->getFilteredGeoObjects($observer);

        // Assert - should continue processing with remaining rules (none in this case)
        $this->assertCount(1, $result);
    }

    private function createQueryMock(array $result)
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willReturn($result);
        return $query;
    }
}
