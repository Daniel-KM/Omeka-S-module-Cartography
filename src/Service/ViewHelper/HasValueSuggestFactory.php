<?php declare(strict_types=1);
namespace Cartography\Service\ViewHelper;

use Cartography\View\Helper\HasValueSuggest;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class HasValueSuggestFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('ValueSuggest');
        $hasModule = $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
        return new HasValueSuggest(
            $hasModule
        );
    }
}
