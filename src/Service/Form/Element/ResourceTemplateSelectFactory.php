<?php
// TODO Remove this copy of Omeka core used for compatibily with Omeka < 1.2.1.

namespace Cartography\Service\Form\Element;

use Interop\Container\ContainerInterface;
use Cartography\Form\Element\ResourceTemplateSelect;
use Zend\ServiceManager\Factory\FactoryInterface;

class ResourceTemplateSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new ResourceTemplateSelect;
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
