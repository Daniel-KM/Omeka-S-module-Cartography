<?php
namespace Cartography\Service\ControllerPlugin;

use Cartography\Mvc\Controller\Plugin\SizeImage;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SizeImageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        return new SizeImage($basePath, $tempFileFactory);
    }
}
