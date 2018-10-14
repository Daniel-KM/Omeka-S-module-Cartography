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

if (version_compare($oldVersion, '3.0.2-alpha', '<')) {
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
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                new \Omeka\Stdlib\Message(
                    'The custom vocab named "%s" is not available.', // @translate
                    $label
                ));
        }
        $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
        $terms = array_unique(array_merge($terms, $data['o:terms']));
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['isPartial' => true]);
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

if (version_compare($oldVersion, '3.0.3-alpha', '<')) {
    $moduleManager = $services->get('Omeka\ModuleManager');
    $moduleClass = 'Annotate';
    $version = '3.0.3';
    $module = $moduleManager->getModule($moduleClass);
    if (empty($module)
        || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE
        || version_compare($module->getDb('version'), $version, '<')
    ) {
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            new \Omeka\Stdlib\Message(
                'The module "%s" must be upgraded to version %s first.', // @translate
                $moduleClass, $version
            ));
    }

    // Complete the annotation of a custom vocabulary.
    // This new data are reverted in 3.0.5, but needed temporary for upgrade.
    $data = [
        'o:label' => 'Annotation oa:motivatedBy',
        'o:lang' => '',
        'o:terms' => [
            'locating',
        ],
    ];
    $label = $data['o:label'];
    try {
        $customVocab = $api
            ->read('custom_vocabs', ['label' => $label])->getContent();
    } catch (\Omeka\Api\Exception\NotFoundException $e) {
        // Manage the case where Annotate is updated later.
        if ($label === 'Annotation oa:motivatedBy') {
            $label = 'Annotation oa:Motivation';
        }
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                new \Omeka\Stdlib\Message(
                    'The custom vocab named "%s" is not available.', // @translate
                    $label
                ));
        }
    }
    $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
    $terms = array_unique(array_merge($terms, $data['o:terms']));
    $api->update('custom_vocabs', $customVocab->id(), [
        'o:label' => $label,
        'o:terms' => implode(PHP_EOL, $terms),
    ], [], ['isPartial' => true]);
}

if (version_compare($oldVersion, '3.0.4-alpha', '<')) {
    // Add the first media id to all geometries that are not a "locating";
    // directly related to item.

    $sqlSelect = <<<'SQL'
SELECT id FROM item;
SQL;
    $stmt = $connection->query($sqlSelect);
    while ($id = $stmt->fetchColumn()) {
        $item = $api->read('items', $id)->getContent();

        // The item should have an image file.
        $media = $item->primaryMedia();
        if (empty($media) || !$media->hasOriginal() || strpos($media->mediaType(), 'image/') !== 0) {
            continue;
        }

        $annotations = $api->search('annotations', ['resource_id' => $id])->getContent();
        foreach ($annotations as $annotation) {
            $motivatedBy = $annotation->value('oa:motivatedBy');
            if ($motivatedBy && $motivatedBy->value() === 'locating') {
                continue;
            }

            $target = $annotation->primaryTarget();
            if (empty($target)) {
                continue;
            }

            // Update geometries only.
            $format = $target->value('dcterms:format');
            if (empty($format) || $format->value() !== 'application/wkt') {
                continue;
            }

            $values = $target->value('rdf:value', ['all' => true, 'default' => []]);
            if (!count($values)) {
                continue;
            }
            foreach ($values as $value) {
                // Don't modify if it has already a media id.
                if ($value->type() === 'resource') {
                    continue 2;
                }
            }

            $data = [];
            // Save the media id first.
            $data['rdf:value'] = [[
                'property_id' => $value->property()->id(),
                'type' => 'resource',
                'value_resource_id' => $media->id(),
            ]];
            foreach ($values as $value) {
                $data['rdf:value'][] = $value->jsonSerialize();
            }

            // This is an annotation "describe", without media id, so add it.
            $api->update('annotation_targets', $target->id(), $data, [], ['isPartial' => true, 'collectionAction' => 'append']);
        }
    }
}

if (version_compare($oldVersion, '3.0.5-alpha', '<')) {
    // Remove "locating from the custom vocab.
    try {
        $label = 'Annotation oa:motivatedBy';
        $customVocab = $api
            ->read('custom_vocabs', ['label' => $label])->getContent();
    } catch (\Omeka\Api\Exception\NotFoundException $e) {
        throw new \Omeka\Module\Exception\ModuleCannotInstallException(
            new \Omeka\Stdlib\Message(
                'The custom vocab named "%s" is not available.', // @translate
                $label
            ));
    }
    $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
    $key = array_search('locating', $terms);
    if ($key !== false) {
        unset($terms[$key]);
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['isPartial' => true]);
    }

    // Replace "locating"  by "highlighting".
    $properties = $api->search('properties', [
        'term' => 'oa:motivatedBy',
    ])->getContent();
    $propertyId = reset($properties);
    $propertyId = $propertyId->id();
    $sql = <<<SQL
UPDATE value
SET value = 'highlighting'
WHERE value = 'locating' AND property_id = $propertyId;
SQL;
    $connection->exec($sql);
}
