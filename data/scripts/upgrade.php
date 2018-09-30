<?php
namespace Cartography;

/**
 * @var \Zend\ServiceManager\ServiceLocatorInterface $serviceLocator
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Api\Manager $api
 */
$services = $serviceLocator;
$settings = $services->get('Omeka\Settings');
$config = require dirname(dirname(__DIR__)) . '/config/module.config.php';
$connection = $services->get('Omeka\Connection');
$entityManager = $services->get('Omeka\EntityManager');
$api = $services->get('Omeka\ApiManager');

if (version_compare($oldVersion, '3.0.1', '<')) {
    $settings->set('cartography_user_guide', $config['cartography']['config']['cartography_user_guide']);
}

if (version_compare($oldVersion, '3.0.2', '<')) {
   // Complete the annotation custom vocabularies.
    $customVocabPaths = [
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-Target-dcterms-format.json',
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-Target-rdf-type.json',
    ];
    foreach ($customVocabPaths as $filepath) {
        $data = json_decode(file_get_contents($filepath), true);
        $label = $data['o:label'];
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                sprintf(
                    'The custom vocab named "%s" is not available.', // @translate
                    $label
                ));
        }
        $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
        $terms = array_unique(array_merge($terms, $data['o:terms']));
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['is_partial' => true]);
    }

     $customVocabPaths = [
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Annotation-Body-oa-hasPurpose.json',
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-cartography-uncertainty.json',
    ];
    foreach ($customVocabPaths as $filepath) {
        $data = json_decode(file_get_contents($filepath), true);
        $data['o:terms'] = implode(PHP_EOL, $data['o:terms']);
        $api->create('custom_vocabs', $data);
    }

    $oldTabs = $settings->get('cartography_display_tab', []);
    $newTabs = [];
    if (in_array('describing', $oldTabs) || in_array('describe', $oldTabs)) {
        $newTabs[] = 'describe';
    }
    if (in_array('locating', $oldTabs) || in_array('locate', $oldTabs)) {
        $newTabs[] = 'locate';
    }
    $settings->set('cartography_display_tab', $newTabs);

    // Replace "highlighting" by "locating".
    $properties = $api->search('properties', [
        'term' => 'oa:motivatedBy',
    ])->getContent();
    $propertyId = reset($properties);
    $propertyId = $propertyId->id();
    $sql = <<<SQL
UPDATE value
SET value = 'locating'
WHERE value = 'highlighting' AND property_id = $propertyId;
SQL;
    $connection->exec($sql);

    // Replace "certitude" by "uncertainty".
    $properties = $api->search('properties', [
        'term' => 'cartography:certitude',
    ])->getContent();
    $propertyId = reset($properties);
    $propertyId = $propertyId->id();
    $sql = <<<SQL
UPDATE `property`
SET `local_name` = 'uncertainty', `label` = 'Uncertainty', `comment` = 'Level of uncertainty of a data.'
WHERE `id` = $propertyId;
SQL;
    $connection->exec($sql);
}
