<?php declare(strict_types=1);

namespace Cartography;

use Omeka\Stdlib\Message;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\Settings\Settings $settings
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$settings = $services->get('Omeka\Settings');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

if (version_compare($oldVersion, '3.1.0', '<')) {
    throw new \Omeka\Module\Exception\ModuleCannotInstallException(
        (string) new \Omeka\Stdlib\Message(
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
