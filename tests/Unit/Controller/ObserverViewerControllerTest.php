<?php

/**
 * Unit tests for ObserverViewerController
 * 
 * @category Tests
 * @package  App\Tests\Unit\Controller
 * @author   Tactical Maps Team
 * @license  MIT
 * @link     https://github.com/tactical-maps
 */

namespace App\Tests\Unit\Controller;

use App\Controller\ObserverViewerController;
use App\Entity\Observer;
use App\Entity\Map;
use App\Entity\GeoObject;
use App\Repository\ObserverRepository;
use App\Service\ObserverRuleService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;

/**
 * Unit tests for ObserverViewerController
 * 
 * Tests the observer viewer functionality including token validation,
 * rule service integration, and template rendering.
 */
class ObserverViewerControllerTest extends TestCase
{
    private ObserverViewerController $_controller;
    private MockObject $_mockObserverRepository;
    private MockObject $_mockObserverRuleService;
    private MockObject $_mockTwig;
    private MockObject $_mockObserver;
    private MockObject $_mockMap;

    /**
     * Set up test fixtures
     * 
     * @return void
     */
    protected function setUp(): void
    {
        // Create mocks
        $this->_mockObserverRepository = $this->createMock(ObserverRepository::class);
        $this->_mockObserverRuleService = $this->createMock(ObserverRuleService::class);
        $this->_mockTwig = $this->createMock(Environment::class);
        
        // Create controller instance
        $this->_controller = new ObserverViewerController();
        $this->_controller->setContainer($this->_createMockContainer());
        
        // Create mock entities
        $this->_mockObserver = $this->createMock(Observer::class);
        $this->_mockMap = $this->createMock(Map::class);
    }

    /**
     * Test successful observer view with valid token
     * 
     * @return void
     */
    public function testViewWithValidToken(): void
    {
        // Arrange
        $token = 'valid-token-123';
        $observerName = 'Test Observer';
        $mapTitle = 'Test Map';
        $mapId = 42;
        
        // Mock geo objects
        $geoObject1 = $this->createMock(GeoObject::class);
        $geoObject1->expects($this->any())
            ->method('getName')
            ->willReturn('Object 1');
        $geoObject1->expects($this->any())
            ->method('getTtl')
            ->willReturn(300);
        $geoObject1->expects($this->any())
            ->method('getGeometryType')
            ->willReturn('Point');
        
        $geoObject2 = $this->createMock(GeoObject::class);
        $geoObject2->expects($this->any())
            ->method('getName')
            ->willReturn('Object 2');
        $geoObject2->expects($this->any())
            ->method('getTtl')
            ->willReturn(600);
        $geoObject2->expects($this->any())
            ->method('getGeometryType')
            ->willReturn('Polygon');
        
        $geoObjects = [$geoObject1, $geoObject2];
        
        // Configure mock observer
        $this->_mockObserver->expects($this->any())
            ->method('getName')
            ->willReturn($observerName);
        $this->_mockObserver->expects($this->any())
            ->method('getMap')
            ->willReturn($this->_mockMap);
        
        // Configure mock map
        $this->_mockMap->expects($this->any())
            ->method('getTitle')
            ->willReturn($mapTitle);
        $this->_mockMap->expects($this->any())
            ->method('getId')
            ->willReturn($mapId);
        
        // Configure repository mock
        $this->_mockObserverRepository
            ->expects($this->once())
            ->method('findByAccessToken')
            ->with($token)
            ->willReturn($this->_mockObserver);
        
        // Configure rule service mock
        $this->_mockObserverRuleService
            ->expects($this->once())
            ->method('getFilteredGeoObjects')
            ->with($this->_mockObserver)
            ->willReturn($geoObjects);
        
        // Configure twig mock to return rendered content
        $expectedContent = '<html>Rendered observer view</html>';
        $this->_mockTwig
            ->expects($this->once())
            ->method('render')
            ->with(
                'observer_viewer/view.html.twig',
                [
                    'observer' => $this->_mockObserver,
                    'map' => $this->_mockMap,
                    'geoObjects' => $geoObjects,
                ]
            )
            ->willReturn($expectedContent);
        
        // Act
        $response = $this->_controller->view(
            $token,
            $this->_mockObserverRepository,
            $this->_mockObserverRuleService
        );
        
        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedContent, $response->getContent());
    }

    /**
     * Test NotFoundHttpException when observer is not found
     * 
     * @return void
     */
    public function testViewWithInvalidToken(): void
    {
        // Arrange
        $invalidToken = 'invalid-token';
        
        // Configure repository to return null (observer not found)
        $this->_mockObserverRepository
            ->expects($this->once())
            ->method('findByAccessToken')
            ->with($invalidToken)
            ->willReturn(null);
        
        // Rule service should not be called
        $this->_mockObserverRuleService
            ->expects($this->never())
            ->method('getFilteredGeoObjects');
        
        // Expect exception
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Observer not found or invalid token');
        
        // Act
        $this->_controller->view(
            $invalidToken,
            $this->_mockObserverRepository,
            $this->_mockObserverRuleService
        );
    }

    /**
     * Test observer view with no geo objects
     * 
     * @return void
     */
    public function testViewWithNoGeoObjects(): void
    {
        // Arrange
        $token = 'valid-token-empty';
        $observerName = 'Empty Observer';
        $mapTitle = 'Empty Map';
        $mapId = 1;
        
        // Configure mock observer and map
        $this->_mockObserver->expects($this->any())
            ->method('getName')
            ->willReturn($observerName);
        $this->_mockObserver->expects($this->any())
            ->method('getMap')
            ->willReturn($this->_mockMap);
        $this->_mockMap->expects($this->any())
            ->method('getTitle')
            ->willReturn($mapTitle);
        $this->_mockMap->expects($this->any())
            ->method('getId')
            ->willReturn($mapId);
        
        // Configure repository mock
        $this->_mockObserverRepository
            ->expects($this->once())
            ->method('findByAccessToken')
            ->with($token)
            ->willReturn($this->_mockObserver);
        
        // Configure rule service to return empty array
        $this->_mockObserverRuleService
            ->expects($this->once())
            ->method('getFilteredGeoObjects')
            ->with($this->_mockObserver)
            ->willReturn([]);
        
        // Configure twig mock
        $expectedContent = '<html>Empty observer view</html>';
        $this->_mockTwig
            ->expects($this->once())
            ->method('render')
            ->with(
                'observer_viewer/view.html.twig',
                [
                    'observer' => $this->_mockObserver,
                    'map' => $this->_mockMap,
                    'geoObjects' => [],
                ]
            )
            ->willReturn($expectedContent);
        
        // Act
        $response = $this->_controller->view(
            $token,
            $this->_mockObserverRepository,
            $this->_mockObserverRuleService
        );
        
        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expectedContent, $response->getContent());
    }

    /**
     * Test that rule service integration works correctly
     * 
     * @return void
     */
    public function testRuleServiceIntegration(): void
    {
        // Arrange
        $token = 'rule-test-token';
        $observerName = 'Rule Test Observer';
        $mapTitle = 'Rule Test Map';
        $mapId = 100;
        
        // Create mock geo object with specific properties
        $geoObject = $this->createMock(GeoObject::class);
        $geoObject->expects($this->any())
            ->method('getName')
            ->willReturn('Filtered Object');
        $geoObject->expects($this->any())
            ->method('getTtl')
            ->willReturn(1200);
        $geoObject->expects($this->any())
            ->method('getGeometryType')
            ->willReturn('LineString');
        
        $filteredObjects = [$geoObject];
        
        // Configure mocks
        $this->_mockObserver->expects($this->any())
            ->method('getName')
            ->willReturn($observerName);
        $this->_mockObserver->expects($this->any())
            ->method('getMap')
            ->willReturn($this->_mockMap);
        $this->_mockMap->expects($this->any())
            ->method('getTitle')
            ->willReturn($mapTitle);
        $this->_mockMap->expects($this->any())
            ->method('getId')
            ->willReturn($mapId);
        
        $this->_mockObserverRepository
            ->expects($this->once())
            ->method('findByAccessToken')
            ->with($token)
            ->willReturn($this->_mockObserver);
        
        // Verify that the rule service is called with the correct observer
        $this->_mockObserverRuleService
            ->expects($this->once())
            ->method('getFilteredGeoObjects')
            ->with($this->identicalTo($this->_mockObserver))
            ->willReturn($filteredObjects);
        
        $this->_mockTwig
            ->expects($this->once())
            ->method('render')
            ->with(
                'observer_viewer/view.html.twig',
                [
                    'observer' => $this->_mockObserver,
                    'map' => $this->_mockMap,
                    'geoObjects' => $filteredObjects,
                ]
            )
            ->willReturn('<html>Filtered view</html>');
        
        // Act
        $response = $this->_controller->view(
            $token,
            $this->_mockObserverRepository,
            $this->_mockObserverRuleService
        );
        
        // Assert
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test with special characters in token
     * 
     * @return void
     */
    public function testViewWithSpecialCharactersInToken(): void
    {
        // Arrange
        $specialToken = 'token-with-special-chars_123!@#';
        
        // Configure repository to return null
        $this->_mockObserverRepository
            ->expects($this->once())
            ->method('findByAccessToken')
            ->with($specialToken)
            ->willReturn(null);
        
        // Expect exception
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Observer not found or invalid token');
        
        // Act
        $this->_controller->view(
            $specialToken,
            $this->_mockObserverRepository,
            $this->_mockObserverRuleService
        );
    }

    /**
     * Create a mock container for the controller
     * 
     * @return MockObject
     */
    private function _createMockContainer(): MockObject
    {
        $container = $this->createMock(ContainerInterface::class);
        
        $container
            ->method('has')
            ->willReturnMap(
                [
                    ['twig', true],
                ]
            );
        
        $container
            ->method('get')
            ->willReturnMap(
                [
                    ['twig', $this->_mockTwig],
                ]
            );
        
        return $container;
    }
}