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
$translate = $plugins->get('translate');
$messenger = $plugins->get('messenger');
$entityManager = $services->get('Omeka\EntityManager');

$minVersions = [
    'Common' => '3.4.83',
    'CustomVocab' => '2.1.0',
    'Annotate' => '3.4.13',
    'DataTypeGeometry' => '3.4.7',
];
foreach ($minVersions as $name => $version) {
    if (!method_exists($this, 'checkModuleActiveVersion')
        || !$this->checkModuleActiveVersion($name, $version)
    ) {
        $message = new \Omeka\Stdlib\Message(
            $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
            $name,
            $version
        );
        $messenger->addError($message);
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            (string) $translate('Missing requirement. Unable to upgrade.') // @translate
        );
    }
}

if (version_compare($oldVersion, '3.1.0', '<')) {
    $message = new \Omeka\Stdlib\Message(
        'To upgrade to Cartography 3.1.0, you must follow the steps described in upgrade_from_alpha.md.' // @translate
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
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

if (version_compare($oldVersion, '3.4.4', '<')) {
    $messenger->addSuccess(
        'The module was fully rewritten, fixed and improved. All issues in user interfaces were fixed. New blocks were added to allow public to view or annotate.' // @translate
    );

    $messenger->addWarning(
        'The format KML is no more supported: use wkt or geojson.' // @translate
    );

    $messenger->addWarning(
        'Next version will improve normalization of the storage of styles, that is not specified in the w3c recommendation. Part of the IIIF format will be used when possible.' // @translate
    );
}
