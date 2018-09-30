<?php
namespace Cartography;

use Cartography\Form\ConfigForm;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * Cartography
 *
 * Allows to annotate a map with the web annotation data model.
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        // TODO Find a better way to disable a module when dependencies are unavailable.
        $services = $event->getApplication()->getServiceManager();
        if ($this->checkDependencies($services)) {
            $this->addAclRules();
        } else {
            $this->disableModule($services);
            $translator = $services->get('MvcTranslator');
            $message = new Message($translator->translate('The module "%s" was automatically deactivated because the dependencies are unavailable.'), // @translate
                __NAMESPACE__
            );
            $messenger = new Messenger();
            $messenger->addWarning($message);
        }
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');
        $translator = $serviceLocator->get('MvcTranslator');

        if (!$this->checkDependencies($serviceLocator)) {
            $message = new Message($translator->translate('This module requires the module "%s".'), // @translate
                'Annotate'
            );
            throw new ModuleCannotInstallException($message);
        }

        $vocabulary = [
            'vocabulary' => [
                'o:namespace_uri' => 'http://localhost/ns/cartography/',
                'o:prefix' => 'cartography',
                'o:label' => 'Cartography', // @translate
                'o:comment' => 'Specific metadata for cartography (to be removed).', // @translate
            ],
            'strategy' => 'file',
            'file' => 'cartography.ttl',
            'format' => 'turtle',
        ];
        $this->createVocabulary($vocabulary, $serviceLocator);

        // Complete the annotation custom vocabularies.
        $customVocabPaths = [
            __DIR__ . '/data/custom-vocabs/Cartography-Target-dcterms-format.json',
            __DIR__ . '/data/custom-vocabs/Cartography-Target-rdf-type.json',
        ];
        foreach ($customVocabPaths as $filepath) {
            $data = json_decode(file_get_contents($filepath), true);
            $label = $data['o:label'];
            try {
                $customVocab = $api
                    ->read('custom_vocabs', ['label' => $label])->getContent();
            } catch (NotFoundException $e) {
                throw new ModuleCannotInstallException(
                    sprintf(
                        'The custom vocab named "%s" is not available.', // @translate
                        $label
                    ));
            }
            $terms = array_map('trim', explode(PHP_EOL, $customVocab->terms()));
            $terms = array_merge($terms, $data['o:terms']);
            $api->update('custom_vocabs', $customVocab->id(), [
                'o:label' => $label,
                'o:terms' => implode(PHP_EOL, $terms),
            ], [], ['is_partial' => true]);
        }

        // Add a specific custom vocabularies.
        $customVocabPaths = [
            // TODO Move custom vocab into annotation or use a specific to Cartography?
            __DIR__ . '/data/custom-vocabs/Annotation-Body-oa-hasPurpose.json',
        ];
        foreach ($customVocabPaths as $filepath) {
            $data = json_decode(file_get_contents($filepath), true);
            $data['o:terms'] = implode(PHP_EOL, $data['o:terms']);
            $api->create('custom_vocabs', $data);
        }

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'install');
        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        // TODO Cartography vocabulary is not removed.

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
        $this->manageSiteSettings($serviceLocator, 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion,
        ServiceLocatorInterface $serviceLocator
    ) {
        require_once 'data/scripts/upgrade.php';
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    protected function manageSiteSettings(ServiceLocatorInterface $serviceLocator, $process)
    {
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $this->manageSettings($siteSettings, $process, 'site_settings');
        }
    }

    /**
     * Check if all dependencies are enabled.
     *
     * @param ServiceLocatorInterface $services
     * @return bool
     */
    protected function checkDependencies(ServiceLocatorInterface $services)
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        $config = require __DIR__ . '/config/module.config.php';
        $dependencies = $config[strtolower(__NAMESPACE__)]['dependencies'];
        foreach ($dependencies as $moduleClass) {
            $module = $moduleManager->getModule($moduleClass);
            if (empty($module) || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                return false;
            }
        }
        return true;
    }

    /**
     * Disable the module.
     *
     * @param ServiceLocatorInterface $services
     */
    protected function disableModule(ServiceLocatorInterface $services)
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule(__NAMESPACE__);
        $moduleManager->deactivate($module);
    }


    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $roles = $acl->getRoles();
        $acl->allow(
            $roles,
            Controller\Admin\CartographyController::class
        );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Events for the admin board.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            [$this, 'addTab'],
            // The priority should be below Mapping to avoid possible issue.
            -1
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            [$this, 'displayTabSection'],
            // The priority should be below Mapping to avoid possible issue.
            -1
        );

        // Events for the public front-end.
        $controllers = [
            'Omeka\Controller\Site\Item',
            'Omeka\Controller\Site\ItemSet',
            'Omeka\Controller\Site\Media',
        ];
        foreach ($controllers as $controller) {
            // Add the cartography to the resource show public pages.
            $sharedEventManager->attach(
                $controller,
                'view.show.after',
                [$this, 'displayPublic']
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'addFormElementsSiteSettings']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

        $renderer->ckEditor();

        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function addFormElementsSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $config = $services->get('Config');
        $form = $event->getTarget();

        $defaultSiteSettings = $config[strtolower(__NAMESPACE__)]['site_settings'];

        $fieldset = new Fieldset('cartography');
        $fieldset->setLabel('Cartography'); // @translate

        $fieldset->add([
            'name' => 'cartography_append_item_set_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append cartography automatically to item set page', // @translate
                'info' => 'If unchecked, the cartography can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'cartography_append_item_set_show',
                    $defaultSiteSettings['cartography_append_item_set_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'cartography_append_item_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append cartography automatically to item page', // @translate
                'info' => 'If unchecked, the cartography can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'cartography_append_item_show',
                    $defaultSiteSettings['cartography_append_item_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'cartography_append_media_show',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Append cartography automatically to media page', // @translate
                'info' => 'If unchecked, the cartography can be added via the helper in the theme or the block in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'cartography_append_media_show',
                    $defaultSiteSettings['cartography_append_media_show']
                ),
            ],
        ]);

        $form->add($fieldset);
    }

    /**
     * Add a tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event)
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $allowed = $acl->userIsAllowed(\Annotate\Entity\Annotation::class, 'read');
        if (!$allowed) {
            return;
        }

        $sectionNav = $event->getParam('section_nav');

        $displayTab = $services->get('Omeka\Settings')->get('cartography_display_tab');
        if (in_array('describing', $displayTab)) {
            $sectionNav['describing'] = 'Describe'; // @translate
        }
        if (in_array('locating', $displayTab)) {
            $sectionNav['locating'] = 'Locate'; // @translate
        }
        $event->setParam('section_nav', $sectionNav);
    }

    /**
     * Display a partial for a resource.
     *
     * @param Event $event
     */
    public function displayTabSection(Event $event)
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');
        $allowed = $acl->userIsAllowed(\Annotate\Entity\Annotation::class, 'read');
        if (!$allowed) {
            return;
        }

        $api = $services->get('Omeka\ApiManager');
        try {
            $customVocab = $api->read('custom_vocabs', [
                'label' => 'Annotation Body oa:hasPurpose',
            ])->getContent();
            $oaHasPurpose = explode(PHP_EOL, $customVocab->terms());
        } catch (NotFoundException $e) {
            $oaHasPurpose = [];
        }

        /** @var \Zend\View\Renderer\PhpRenderer $view */
        $view = $event->getTarget();

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $view->resource;

        $displayTab = $services->get('Omeka\Settings')->get('cartography_display_tab');

        if (in_array('describing', $displayTab)) {
            $config = $services->get('Config');
            $this->basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            // Add the url and the size of the file.
            $media = $resource->primaryMedia();
            $image = null;
            if ($media && $media->hasOriginal()) {
                if (strtok($media->mediaType(), '/') === 'image') {
                    $size = $this->_getImageSize($media, 'original');
                    if ($size) {
                        $image['url'] = $media->originalUrl();
                        $image['size'] = array_values($size);
                    }
                }
            }
            $query = [
                'property' => [
                    [
                        'joiner' => 'and',
                        'property' => 'oa:motivatedBy',
                        'type' => 'eq',
                        'text' => 'describing',
                    ],
                ],
            ];
            $geometries = $this->fetchGeometries($resource, $query);
            echo $view->partial('cartography/admin/cartography/annotate-describing', [
                'resource' => $resource,
                'geometries' => $geometries,
                'image' => $image,
                'oaHasPurposeSelect' => $oaHasPurpose,
            ]);
        }

        if (in_array('locating', $displayTab)) {
            $query = [
                'property' => [
                    [
                        'joiner' => 'and',
                        'property' => 'oa:motivatedBy',
                        'type' => 'eq',
                        'text' => 'highlighting',
                    ],
                ],
            ];
            echo $view->partial('cartography/admin/cartography/annotate-locating', [
                'resource' => $resource,
                'geometries' => $this->fetchGeometries($resource, $query),
                'oaHasPurposeSelect' => $oaHasPurpose,
            ]);
        }
    }

    /**
     * Prepare all geometries for a resource.
     *
     * @todo Factorize with Cartography plugin and clean the process (make it available dynamically).
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param array $query
     * @return array
     */
    protected function fetchGeometries(AbstractResourceEntityRepresentation $resource, array $query)
    {
        $geometries = [];

        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        /** @var \Annotate\Mvc\Controller\Plugin\ResourceAnnotations $resourceAnnotationsPlugin */
        $resourceAnnotationsPlugin = $controllerPlugins->get('resourceAnnotations');
        /** @var \Annotate\Api\Representation\AnnotationRepresentation[] $annotations */
        $annotations = $resourceAnnotationsPlugin($resource, $query);

        foreach ($annotations as $annotation) {
            $target = $annotation->primaryTarget();
            if (!$target) {
                continue;
            }

            $format = $target->value('dcterms:format');
            if (empty($format)) {
                continue;
            }

            $geometry = [];
            $format = $format->value();
            if ($format === 'application/wkt') {
                $value = $target->value('rdf:value');
                $geometry['id'] = $annotation->id();
                $geometry['wkt'] = $value ? $value->value() : null;
            }

            $styleClass = $target->value('oa:styleClass');
            if ($styleClass && $styleClass->value() === 'leaflet-interactive') {
                $options = $annotation->value('oa:styledBy');
                if ($options) {
                    $options = json_decode($options->value(), true);
                    if (!empty($options['leaflet-interactive'])) {
                        $geometry['options'] = $options['leaflet-interactive'];
                    }
                }
            }

            $body = $annotation->primaryBody();
            if ($body) {
                $value = $body->value('rdf:value');
                $geometry['options']['popupContent'] = $value ? $value->value() : '';
                $value = $body->value('oa:hasPurpose');
                $geometry['options']['oaHasPurpose'] = $value ? $value->value() : '';
            }

            $geometries[$annotation->id()] = $geometry;
        }

        return $geometries;
    }

    /**
     * Display a partial for a resource in public.
     *
     * @param Event $event
     */
    public function displayPublic(Event $event)
    {
        $serviceLocator = $this->getServiceLocator();
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();
        $appendMap = [
            'item_sets' => 'cartography_append_item_set_show',
            'items' => 'cartography_append_item_show',
            'media' => 'cartography_append_media_show',
        ];
        if (!$siteSettings->get($appendMap[$resourceName])) {
            return;
        }

        echo $view->cartography($resource);
    }

    /**
     * Create a vocabulary, with a check of its existence before.
     *
     * @param array $vocabulary
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool True if the vocabulary has been created, false if it
     * exists already, so it is not created twice.
     */
    protected function createVocabulary(array $vocabulary, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');

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
                sprintf(
                    'An error occured when adding the prefix "%s": another vocabulary exists. Resolve the conflict before installing this module.', // @translate
                    $vocabulary['vocabulary']['o:prefix']
                ));
        }

        /** @var \Omeka\Stdlib\RdfImporter $rdfImporter */
        $rdfImporter = $serviceLocator->get('Omeka\RdfImporter');

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
                sprintf(
                    'An error occured when adding the prefix "%s" and the associated properties: %s', // @translate
                    $vocabulary['vocabulary']['o:prefix'], $e->getMessage()
                ));
        }

        return true;
    }

    /**
     * Get a storage path.
     *
     * @param string $prefix The storage prefix
     * @param string $name The file name, or basename if extension is passed
     * @param null|string $extension The file extension
     * @return string
     * @todo Refactorize.
     */
    protected function getStoragePath($prefix, $name, $extension = null)
    {
        return sprintf('%s/%s%s', $prefix, $name, $extension ? ".$extension" : null);
    }

    /**
     * Get an array of the width and height of the image file.
     *
     * @param MediaRepresentation $media
     * @param string $imageType
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     *
     * @see \IiifServer\View\Helper\IiifManifest::_getImageSize()
     * @see \IiifServer\View\Helper\IiifInfo::_getImageSize()
     * @see \IiifServer\Controller\ImageController::_getImageSize()
     * @todo Refactorize.
     */
    protected function _getImageSize(MediaRepresentation $media, $imageType = 'original')
    {
        // Check if this is an image.
        if (empty($media) || strpos($media->mediaType(), 'image/') !== 0) {
            return [
                'width' => null,
                'height' => null,
            ];
        }

        // The storage adapter should be checked for external storage.
        if ($imageType == 'original') {
            $storagePath = $this->getStoragePath($imageType, $media->filename());
        } else {
            $storagePath = $this->getStoragePath($imageType, $media->storageId(), 'jpg');
        }
        $filepath = $this->basePath
            . DIRECTORY_SEPARATOR . $storagePath;
        $result = $this->_getWidthAndHeight($filepath);

        if (empty($result['width']) || empty($result['height'])) {
            throw new \Exception("Failed to get image resolution: $filepath");
        }

        return $result;
    }

    /**
     * Helper to get width and height of an image.
     *
     * @param string $filepath This should be an image (no check here).
     * @return array Associative array of width and height of the image file.
     * If the file is not an image, the width and the height will be null.
     * @see \IiifServer\Controller\ImageController::_getWidthAndHeight()
     * @todo Refactorize.
     */
    protected function _getWidthAndHeight($filepath)
    {
        // TODO Manage tempFileFactory.

        // An internet path.
        if (strpos($filepath, 'https://') === 0 || strpos($filepath, 'http://') === 0) {
            $tempFile = $this->tempFileFactory->build();
            $tempPath = $tempFile->getTempPath();
            $tempFile->delete();
            $result = file_put_contents($tempPath, $filepath);
            if ($result !== false) {
                list($width, $height) = getimagesize($tempPath);
                unlink($tempPath);
                return [
                    'width' => $width,
                    'height' => $height,
                ];
            }
            unlink($tempPath);
        }
        // A normal path.
        elseif (file_exists($filepath)) {
            list($width, $height) = getimagesize($filepath);
            return [
                'width' => $width,
                'height' => $height,
            ];
        }

        return [
            'width' => null,
            'height' => null,
        ];
    }
}
