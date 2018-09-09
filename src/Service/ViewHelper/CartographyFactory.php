<?php
namespace Cartography\Service\ViewHelper;

use Cartography\View\Helper\Cartography;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CartographyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPlugins = $services->get('ControllerPluginManager');
        $resourceAnnotationsPlugin = $controllerPlugins->get('resourceAnnotations');
        return new Cartography($resourceAnnotationsPlugin);
    }
}
