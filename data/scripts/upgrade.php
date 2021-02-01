<?php declare(strict_types=1);
namespace Cartography;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(__DIR__, 2) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.1.0', '<')) {
    throw new \Omeka\Module\Exception\ModuleCannotInstallException(
        new \Omeka\Stdlib\Message(
            'To upgrade to Cartography 3.1.0, you must follow the steps described in upgrade_from_alpha.md.' // @translate
    ));
}

if (version_compare($oldVersion, '3.1.3.2', '<')) {
    $locate = $settings->get('cartography_js_locate', '');
    $replace = <<<JS
'Grayscale': L.tileLayer.provider('OpenStreetMap.BlackAndWhite'),
JS;
    $locate = str_replace($replace, '', $locate);
    $replace = <<<JS
'Grayscale':L.tileLayer.provider('OpenStreetMap.BlackAndWhite'),
JS;
    $locate = str_replace($replace, '', $locate);
    $settings->set('cartography_js_locate', $locate);
}
