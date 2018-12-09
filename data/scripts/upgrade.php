<?php
namespace Cartography;

/**
 * @var Module $this
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
$plugins = $services->get('ControllerPluginManager');
$api = $plugins->get('api');
$space = strtolower(__NAMESPACE__);

if (version_compare($oldVersion, '3.0.1', '<')) {
    $settings->set('cartography_user_guide',
        $config[$space]['settings']['cartography_user_guide']);
}

if (version_compare($oldVersion, '3.0.2-alpha', '<')) {
    $customVocabPaths = [
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Annotation-Body-oa-hasPurpose.json',
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-cartography-uncertainty.json',
    ];
    foreach ($customVocabPaths as $filepath) {
        $this->createCustomVocab($services, $filepath);
    }

    $customVocabPaths = [
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-Target-dcterms-format.json',
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-Target-rdf-type.json',
    ];
    foreach ($customVocabPaths as $filepath) {
        $this->updateCustomVocab($services, $filepath);
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
    $property = $api->searchOne('properties', [
        'term' => 'oa:motivatedBy',
    ])->getContent();
    $propertyId = $property->id();
    $sql = <<<SQL
UPDATE value
SET value = 'locating'
WHERE value = 'highlighting' AND property_id = $propertyId;
SQL;
    $connection->exec($sql);

    // Replace "certitude" by "uncertainty".
    $property = $api->searchOne('properties', [
        'term' => 'cartography:certitude',
    ])->getContent();
    $propertyId = $property->id();
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
    $property = $api->searchOne('properties', [
        'term' => 'oa:motivatedBy',
    ])->getContent();
    $propertyId = $property->id();
    $sql = <<<SQL
UPDATE value
SET value = 'highlighting'
WHERE value = 'locating' AND property_id = $propertyId;
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.5-beta2', '<')) {
    // Remove general wms (replaced by upper/lower resource wms).
    $settings->delete('cartography_locate_wms');

    // Add options for describe and locate at site level.
    $siteSettings = $services->get('Omeka\Settings\Site');
    /** @var \Omeka\Api\Representation\SiteRepresentation[] $sites */
    $sites = $api->search('sites')->getContent();
    foreach ($sites as $site) {
        $siteSettings->setTargetId($site->id());
        if ($siteSettings->get('cartography_append_item_show', true)) {
            $siteSettings->set('cartography_append_public', ['describe_items_show', 'locate_items_show']);
        } else {
            $siteSettings->set('cartography_append_public', []);
        }
        $siteSettings->delete('cartography_append_item_set_show');
        $siteSettings->delete('cartography_append_item_show');
        $siteSettings->delete('cartography_append_media_show');
    }
    $settings->delete('cartography_append_item_set_show');
    $settings->delete('cartography_append_item_show');
    $settings->delete('cartography_append_media_show');
}

if (version_compare($oldVersion, '3.0.6-beta', '<')) {
    $customVocabPaths = [
        dirname(dirname(__DIR__)) . '/data/custom-vocabs/Cartography-oa-MotivatedBy-Locate.json',
    ];
    foreach ($customVocabPaths as $filepath) {
        $this->createCustomVocab($services, $filepath);
    }

    $resourceTemplatePaths = [
        'cartography_template_describe' => dirname(dirname(__DIR__)) . '/data/resource-templates/Cartography_Describe.json',
        'cartography_template_locate' => dirname(dirname(__DIR__)) . '/data/resource-templates/Cartography_Locate.json',
    ];
    foreach ($resourceTemplatePaths as $key => $filepath) {
        $resourceTemplate = $this->createResourceTemplate($services, $filepath);
        $settings->set($key, [$resourceTemplate->id()]);
    }
}

if (version_compare($oldVersion, '3.0.7-beta', '<')) {
    foreach (['cartography_template_describe', 'cartography_template_locate'] as $list) {
        $ids = [];
        $labels = $config[$space]['settings'][$list];
        foreach ($labels as $label) {
            $resourceTemplate = $api->searchOne('resource_templates', ['label' => $label])->getContent();
            if ($resourceTemplate) {
                $ids[] = $resourceTemplate->id();
            }
        }
        $settings->set('cartography_template_describe', $ids);
    }
    $settings->set('cartography_template_describe_empty',
        $config[$space]['settings']['cartography_template_describe_empty']);
    $settings->set('cartography_template_locate_empty',
        $config[$space]['settings']['cartography_template_locate_empty']);
}

if (version_compare($oldVersion, '3.0.8-beta', '<')) {
    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
    $messenger->addWarning(
        'This new version uses a resource template as a form to annotate image and maps.'
        . ' ' . 'The default ones use the old static one, if they were not renamed.'
        . ' ' . 'You may update them in "Resource templates" and select them in "Admin settings".' // @translate
    );

    $data = $settings->get('annotate_resource_template_data', []);
    $map = [
        'oa:motivatedBy' => 'oa:Annotation',
        'rdf:value' => 'oa:hasBody',
        'oa:hasPurpose' => 'oa:hasBody',
        'oa:hasBody' => 'oa:Annotation',
        'cartography:uncertainty' => 'oa:hasTarget',
    ];
    foreach (['Cartography Describe', 'Cartography Locate'] as $label) {
        $resourceTemplate = $api->searchOne('resource_templates', ['label' => $label])->getContent();
        if ($resourceTemplate) {
            foreach ($map as $term => $part) {
                $data[$resourceTemplate->id()][$term] = $part;
            }
        } else {
            $messenger->addWarning(sprintf('Resource template "%s" was not found and was not updated.', $label)); // @translate
        }
    }

    $settings->set('annotate_resource_template_data', $data);
}

if (version_compare($oldVersion, '3.0.9-beta', '<')) {
    // Replace "literal" by "geometry" for rdf:value of targets.
    $property = $api->searchOne('properties', [
        'term' => 'rdf:value',
    ])->getContent();
    $propertyId = $property->id();
    $sql = <<<SQL
UPDATE value
INNER JOIN annotation_target ON annotation_target.id = value.resource_id
SET type = "geometry", lang = NULL, value_resource_id = NULL, uri = NULL
WHERE value.property_id = $propertyId
AND value.type = "literal"
AND (value.value_resource_id IS NULL)
AND (value.lang = "" OR value.lang IS NULL)
AND (value.uri = "" OR value.uri IS NULL);
SQL;
    $connection->exec($sql);
}

if (version_compare($oldVersion, '3.0.10-beta', '<')) {
    $useMyIsam = $this->requireMyIsamToSupportGeometry($serviceLocator);
    $filepath = $useMyIsam
        ? $this->modulePath() . '/data/install/schema-myisam.sql'
        :  $this->modulePath() . '/data/install/schema.sql';
    $this->execSqlFromFile($filepath);

    // Index existing geometries.
    $sql = <<<SQL
INSERT INTO data_type_geometry (resource_id, property_id, value)
SELECT resource_id, property_id, GeomFromText(value)
FROM value
WHERE type = "geometry"
ORDER BY id ASC;
SQL;
    $result = $connection->exec($sql);
    $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
    $messenger->addSuccess(sprintf('%d geometries were indexed in the new table "data_type_geometry".', $result)); // @translate
}
