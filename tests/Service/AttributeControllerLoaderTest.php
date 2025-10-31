<?php

declare(strict_types=1);

namespace Tourze\AutoJsControlBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Tourze\AutoJsControlBundle\Service\AttributeControllerLoader;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    private AttributeControllerLoader $loader;

    protected function onSetUp(): void
    {
        $this->loader = self::getService(AttributeControllerLoader::class);
    }

    public function testLoadCallsAutoload(): void
    {
        // Act
        $collection = $this->loader->load('resource');

        // Assert
        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count(), 'RouteCollection should contain routes');

        // Verify that it contains routes from expected controllers
        $routeNames = array_keys($collection->all());
        $hasDeviceRoutes = false;
        $hasScriptRoutes = false;
        $hasTaskRoutes = false;

        foreach ($routeNames as $routeName) {
            if (str_contains($routeName, 'device')) {
                $hasDeviceRoutes = true;
            }
            if (str_contains($routeName, 'script')) {
                $hasScriptRoutes = true;
            }
            if (str_contains($routeName, 'task')) {
                $hasTaskRoutes = true;
            }
        }

        $this->assertTrue(
            $hasDeviceRoutes || $hasScriptRoutes || $hasTaskRoutes,
            'RouteCollection should contain routes from at least one controller'
        );
    }

    public function testSupportsAlwaysReturnsFalse(): void
    {
        // Act & Assert
        $this->assertFalse($this->loader->supports('any_resource'));
        $this->assertFalse($this->loader->supports('any_resource', 'any_type'));
        $this->assertFalse($this->loader->supports(null));
        $this->assertFalse($this->loader->supports(''));
    }

    public function testAutoloadReturnsRouteCollection(): void
    {
        // Act
        $collection = $this->loader->autoload();

        // Assert
        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());

        // Check that routes are properly loaded
        $routes = $collection->all();
        foreach ($routes as $route) {
            $this->assertInstanceOf(Route::class, $route);
            $this->assertNotNull($route->getPath());
            $this->assertNotEmpty($route->getDefaults());
        }
    }

    public function testAutoloadLoadsControllers(): void
    {
        // Act
        $collection = $this->loader->autoload();

        // Assert - Check that controllers are loaded by verifying route collection has routes
        $routes = $collection->all();
        $this->assertNotEmpty($routes, 'Routes should be loaded from controllers');

        // Verify that we have device routes (script and task controllers have been replaced with EasyAdmin CrudControllers)
        $deviceRoutes = 0;

        foreach ($routes as $route) {
            $path = $route->getPath();
            if (str_contains($path, '/device/')) {
                ++$deviceRoutes;
            }
        }

        $this->assertGreaterThan(0, $deviceRoutes, 'Should have device routes');

        // Note: Script and task routes are no longer loaded here as they have been
        // replaced with EasyAdmin CrudControllers as mentioned in AttributeControllerLoader comments
    }

    public function testLoaderIsProperlyConfigured(): void
    {
        // This test verifies that the loader is properly configured and functional
        // We use the container-managed instance instead of creating a new one

        $collection = $this->loader->autoload();

        // If the loader wasn't properly configured, this would fail
        $this->assertInstanceOf(RouteCollection::class, $collection);
        $this->assertGreaterThan(0, $collection->count());
    }

    public function testLoadWithDifferentResourcesCallsAutoload(): void
    {
        // Test that load always delegates to autoload regardless of resource
        $resources = [
            'test_resource',
            null,
            '',
            'another_resource',
        ];

        foreach ($resources as $resource) {
            $collection = $this->loader->load($resource);
            $this->assertInstanceOf(RouteCollection::class, $collection);
            $this->assertGreaterThan(0, $collection->count());
        }
    }

    public function testLoadWithDifferentTypesCallsAutoload(): void
    {
        // Test that load always delegates to autoload regardless of type
        $types = [
            'annotation',
            'attribute',
            null,
            'custom_type',
        ];

        foreach ($types as $type) {
            $collection = $this->loader->load('resource', $type);
            $this->assertInstanceOf(RouteCollection::class, $collection);
            $this->assertGreaterThan(0, $collection->count());
        }
    }

    public function testAutoloadReturnsSameRoutesOnMultipleCalls(): void
    {
        // Test that autoload is consistent
        $collection1 = $this->loader->autoload();
        $collection2 = $this->loader->autoload();

        $this->assertEquals($collection1->count(), $collection2->count());

        // Compare route names
        $routes1 = array_keys($collection1->all());
        $routes2 = array_keys($collection2->all());

        sort($routes1);
        sort($routes2);

        $this->assertEquals($routes1, $routes2);
    }
}
