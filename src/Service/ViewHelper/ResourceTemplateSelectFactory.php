<?php
// TODO Remove this copy of Omeka core used for compatibily with Omeka < 1.2.1.

namespace Cartography\Service\ViewHelper;

use Cartography\View\Helper\ResourceTemplateSelect;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ResourceTemplateSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ResourceTemplateSelect($services->get('FormElementManager'));
    }
}
