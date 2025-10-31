<?php

namespace Tourze\AutoJsControlBundle\Service;

use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\AutoJsControlBundle\Controller\Device\GetScriptController;
use Tourze\AutoJsControlBundle\Controller\Device\HeartbeatController;
use Tourze\AutoJsControlBundle\Controller\Device\RegisterController;
use Tourze\AutoJsControlBundle\Controller\Device\ReportResultController;
use Tourze\AutoJsControlBundle\Controller\Device\UploadLogsController;
use Tourze\AutoJsControlBundle\Controller\Device\UploadScreenshotController;
use Tourze\RoutingAutoLoaderBundle\Service\RoutingAutoLoaderInterface;

#[AutoconfigureTag(name: 'routing.loader')]
class AttributeControllerLoader extends Loader implements RoutingAutoLoaderInterface
{
    private AttributeRouteControllerLoader $controllerLoader;

    public function __construct()
    {
        parent::__construct();
        $this->controllerLoader = new AttributeRouteControllerLoader();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->autoload();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return false;
    }

    public function autoload(): RouteCollection
    {
        $collection = new RouteCollection();

        // Device controllers
        $collection->addCollection($this->controllerLoader->load(RegisterController::class));
        $collection->addCollection($this->controllerLoader->load(HeartbeatController::class));
        $collection->addCollection($this->controllerLoader->load(GetScriptController::class));
        $collection->addCollection($this->controllerLoader->load(ReportResultController::class));
        $collection->addCollection($this->controllerLoader->load(UploadLogsController::class));
        $collection->addCollection($this->controllerLoader->load(UploadScreenshotController::class));

        // Script controllers have been replaced with EasyAdmin CrudControllers
        // Task controllers have been replaced with EasyAdmin CrudControllers

        return $collection;
    }
}
