<?php
namespace Cartography\Service\ViewHelper;

use Cartography\View\Helper\NormalizeGeometryQuery;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class NormalizeGeometryQueryFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new NormalizeGeometryQuery(
            $services->get('Omeka\EntityManager')
        );
    }
}
