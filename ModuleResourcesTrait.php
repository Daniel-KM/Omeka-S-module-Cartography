<?php
/*
 * Copyright Daniel Berthereau, 2018
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace Cartography;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * This generic trait allows to manage all resources methods that should run
 * once only and that are generic to all modules. A little config over code.
 */
trait ModuleResourcesTrait
{
    abstract protected function installResources(ServiceLocatorInterface $services);

    /**
     * Create a vocabulary, with a check of its existence before.
     *
     * @param ServiceLocatorInterface $services
     * @param array $vocabulary
     * @throws ModuleCannotInstallException
     * @return bool True if the vocabulary has been created, false if it exists
     * already, so it is not created twice.
     */
    protected function createVocabulary(ServiceLocatorInterface $services, array $vocabulary)
    {
        $api = $services->get('Omeka\ApiManager');

        // Check if the vocabulary have been already imported.
        $prefix = $vocabulary['vocabulary']['o:prefix'];

        try {
            /** @var \Omeka\Api\Representation\VocabularyRepresentation $vocabularyRepresentation */
            $vocabularyRepresentation = $api
                ->read('vocabularies', ['prefix' => $prefix])->getContent();
        } catch (NotFoundException $e) {
            $vocabularyRepresentation = null;
        }

        if ($vocabularyRepresentation) {
            // Check if it is the same vocabulary.
            if ($vocabularyRepresentation->namespaceUri() === $vocabulary['vocabulary']['o:namespace_uri']) {
                $message = new Message('The vocabulary "%s" was already installed and was kept.', // @translate
                    $vocabulary['vocabulary']['o:label']);
                $messenger = new Messenger();
                $messenger->addWarning($message);
                return false;
            }

            // It is another vocabulary with the same prefix.
            throw new ModuleCannotInstallException(
                new Message(
                    'An error occured when adding the prefix "%s": another vocabulary exists. Resolve the conflict before installing this module.', // @translate
                    $vocabulary['vocabulary']['o:prefix']
                ));
        }

        /** @var \Omeka\Stdlib\RdfImporter $rdfImporter */
        $rdfImporter = $services->get('Omeka\RdfImporter');

        try {
            $rdfImporter->import(
                $vocabulary['strategy'],
                $vocabulary['vocabulary'],
                [
                    'file' => __DIR__ . '/data/vocabularies/' . $vocabulary['file'],
                    'format' => $vocabulary['format'],
                ]
            );
        } catch (\Omeka\Api\Exception\ValidationException $e) {
            throw new ModuleCannotInstallException(
                new Message(
                    'An error occured when adding the prefix "%s" and the associated properties: %s', // @translate
                    $vocabulary['vocabulary']['o:prefix'], $e->getMessage()
                ));
        }

        return true;
    }

    /**
     * Create a custom vocab.
     *
     * @param ServiceLocatorInterface $services
     * @param string $filepath
     */
    protected function createCustomVocab(ServiceLocatorInterface $services, $filepath)
    {
        $api = $services->get('Omeka\ApiManager');
        $data = json_decode(file_get_contents($filepath), true);
        $data['o:terms'] = implode(PHP_EOL, $data['o:terms']);
        $api->create('custom_vocabs', $data);
    }

    /**
     * Update a vocabulary, with a check of its existence before.
     *
     * @param ServiceLocatorInterface $services
     * @param string $filepath
     * @throws ModuleCannotInstallException
     */
    protected function updateCustomVocab(ServiceLocatorInterface $services, $filepath)
    {
        $api = $services->get('Omeka\ApiManager');
        $data = json_decode(file_get_contents($filepath), true);

        $label = $data['o:label'];
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])->getContent();
        } catch (NotFoundException $e) {
            throw new ModuleCannotInstallException(
                new Message(
                    'The custom vocab named "%s" is not available.', // @translate
                    $label
                ));
        }

        $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
        $terms = array_merge($terms, $data['o:terms']);
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $terms),
        ], [], ['isPartial' => true]);
    }

    /**
     * Create a resource template, with a check of its existence before.
     *
     * @todo Some checks of the resource termplate controller are skipped currently.
     *
     * @param ServiceLocatorInterface $services
     * @param string $filepath
     * @return \Omeka\Api\Representation\ResourceTemplateRepresentation
     * @throws ModuleCannotInstallException
     */
    protected function createResourceTemplate(ServiceLocatorInterface $services, $filepath)
    {
        $api = $services->get('ControllerPluginManager')->get('api');
        $data = json_decode(file_get_contents($filepath), true);

        // Check if the resource template exists, so it is not replaced.
        $label = $data['o:label'];
        try {
            $api->read('resource_templates', ['label' => $label]);
            $message = new Message(
                'The resource template named "%s" is already available and is skipped.', // @translate
                $label
            );
            $messenger = new Messenger();
            $messenger->addWarning($message);
            return;
        } catch (NotFoundException $e) {
        }

        // Set the iinternal ids of classes, properties and data types.
        // TODO Check if the output is valid (else an error will be thrown during import).
        $data = $this->flagValid($services, $data);

        // Manage the custom vocabs that may be set inside the template.
        foreach ($data['o:resource_template_property'] as &$templateProperty) {
            if (strpos($templateProperty['data_type_name'], 'customvocab:') !== 0) {
                continue;
            }
            $label = $templateProperty['data_type_label'];
            try {
                $customVocab = $api
                    ->read('custom_vocabs', ['label' => $label])->getContent();
            } catch (NotFoundException $e) {
                throw new ModuleCannotInstallException(
                    new Message(
                        'The custom vocab named "%s" is not available.', // @translate
                        $label
                    ));
            }
            $templateProperty['data_type_name'] = 'customvocab:' . $customVocab->id();
        }
        unset($templateProperty);

        // Process import.
        $resourceTemplate = $api->create('resource_templates', $data)->getContent();
        return $resourceTemplate;
    }

    /**
     * Flag members and data types as valid.
     *
     * Copy of the method of the resource template controller (with services).
     *
     * @see \Omeka\Controller\Admin\ResourceTemplateController::flagValid()
     *
     * All members start as invalid until we determine whether the corresponding
     * vocabulary and member exists in this installation. All data types start
     * as "Default" (i.e. none declared) until we determine whether they match
     * the native types (literal, uri, resource).
     *
     * We flag a valid vocabulary by adding [vocabulary_prefix] to the member; a
     * valid class by adding [o:id]; and a valid property by adding
     * [o:property][o:id]. We flag a valid data type by adding [o:data_type] to
     * the property. By design, the API will only hydrate members and data types
     * that are flagged as valid.
     *
     * @param ServiceLocatorInterface $services
     * @param array $import
     * @return array
     */
    protected function flagValid(ServiceLocatorInterface $services, array $import)
    {
        $vocabs = [];
        $dataTypes = [
            'literal',
            'uri',
            'resource',
            'resource:item',
            'resource:itemset',
            'resource:media',
        ];

        $api = $services->get('ControllerPluginManager')->get('api');

        $getVocab = function ($namespaceUri) use (&$vocabs, $api) {
            if (isset($vocabs[$namespaceUri])) {
                return $vocabs[$namespaceUri];
            }
            $vocab = $api->searchOne('vocabularies', [
                'namespace_uri' => $namespaceUri,
            ])->getContent();
            if ($vocab) {
                $vocabs[$namespaceUri] = $vocab;
                return $vocab;
            }
            return false;
        };

        if (isset($import['o:resource_class'])) {
            if ($vocab = $getVocab($import['o:resource_class']['vocabulary_namespace_uri'])) {
                $import['o:resource_class']['vocabulary_prefix'] = $vocab->prefix();
                $class = $api->searchOne('resource_classes', [
                    'vocabulary_namespace_uri' => $import['o:resource_class']['vocabulary_namespace_uri'],
                    'local_name' => $import['o:resource_class']['local_name'],
                ])->getContent();
                if ($class) {
                    $import['o:resource_class']['o:id'] = $class->id();
                }
            }
        }

        foreach ($import['o:resource_template_property'] as $key => $property) {
            if ($vocab = $getVocab($property['vocabulary_namespace_uri'])) {
                $import['o:resource_template_property'][$key]['vocabulary_prefix'] = $vocab->prefix();
                $prop = $api->searchOne('properties', [
                    'vocabulary_namespace_uri' => $property['vocabulary_namespace_uri'],
                    'local_name' => $property['local_name'],
                ])->getContent();
                if ($prop) {
                    $import['o:resource_template_property'][$key]['o:property'] = ['o:id' => $prop->id()];
                    if (in_array($import['o:resource_template_property'][$key]['data_type_name'], $dataTypes)) {
                        $import['o:resource_template_property'][$key]['o:data_type'] = $import['o:resource_template_property'][$key]['data_type_name'];
                    }
                }
            }
        }

        return $import;
    }
}
