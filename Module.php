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
 * Allows to annotate an image or a wms map with the w3c web annotation data model and vocabulary.
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
            ], [], ['isPartial' => true]);
        }

        // Add specific custom vocabularies.
        $customVocabPaths = [
            // TODO Move custom vocab into annotation or use a specific to Cartography?
            __DIR__ . '/data/custom-vocabs/Annotation-Body-oa-hasPurpose.json',
            __DIR__ . '/data/custom-vocabs/Cartography-cartography-uncertainty.json',
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
            // 'Omeka\Controller\Site\ItemSet',
            // 'Omeka\Controller\Site\Media',
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
            $value = $settings->get($name, $value);
            switch ($name) {
                case 'cartography_locate_wms':
                    $values = '';
                    foreach ($value as $v) {
                        $values .= $v['url'] . ' ' . $v['label'] . PHP_EOL;
                    }
                    $value = $values;
                    break;
            }
            $data[$name] = $value;
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

        // The str_replace() allows to fix Apple copy/paste.
        $list = array_filter(array_map('trim', explode(
            PHP_EOL,
            str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $params['cartography_locate_wms'])
        )));
        $params['cartography_locate_wms'] = [];
        foreach ($list as $line) {
            list($wmsUrl, $wmsLabel) = array_map('trim', explode(' ', $line, 2));
            if ($wmsUrl) {
                $params['cartography_locate_wms'][] = [
                    'url' => $wmsUrl,
                    'label' => $wmsLabel ?: '',
                ];
            }
        }

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

        /*
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
        */

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

        /*
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
        */

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

        $displayTab = $services->get('Omeka\Settings')->get('cartography_display_tab', []);
        if (empty($displayTab)) {
            return;
        }

        $sectionNav = $event->getParam('section_nav');
        if (in_array('describe', $displayTab)) {
            $sectionNav['describe'] = 'Describe'; // @translate
        }
        if (in_array('locate', $displayTab)) {
            $sectionNav['locate'] = 'Locate'; // @translate
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

        $settings = $services->get('Omeka\Settings');
        $displayTab = $settings->get('cartography_display_tab', []);
        if (empty($displayTab)) {
            return;
        }

        $api = $services->get('Omeka\ApiManager');

        /** @var \Zend\View\Renderer\PhpRenderer $view */
        $view = $event->getTarget();

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $view->resource;

        try {
            $customVocab = $api->read('custom_vocabs', [
                'label' => 'Annotation oa:motivatedBy',
            ])->getContent();
            $oaMotivatedBy = explode(PHP_EOL, $customVocab->terms());
        } catch (NotFoundException $e) {
            $oaMotivatedBy = [];
        }

        try {
            $customVocab = $api->read('custom_vocabs', [
                'label' => 'Annotation Body oa:hasPurpose',
            ])->getContent();
            $oaHasPurpose = explode(PHP_EOL, $customVocab->terms());
        } catch (NotFoundException $e) {
            $oaHasPurpose = [];
        }

        try {
            $customVocab = $api->read('custom_vocabs', [
                'label' => 'Cartography cartography:uncertainty',
            ])->getContent();
            $cartographyUncertainty = explode(PHP_EOL, $customVocab->terms());
        } catch (NotFoundException $e) {
            $cartographyUncertainty = [];
        }

        echo $view->partial('cartography/admin/cartography/annotate', [
            'resource' => $resource,
            'oaMotivatedBySelect' => $oaMotivatedBy,
            'oaHasPurposeSelect' => $oaHasPurpose,
            'cartographyUncertaintySelect' => $cartographyUncertainty,
        ]);

        $displayTab = $settings->get('cartography_display_tab', []);

        if (in_array('describe', $displayTab)) {
            echo $view->partial('cartography/admin/cartography/annotate-describe');
        }

        if (in_array('locate', $displayTab)) {
            // Display wms layers, if any. The url should finish with "?", and
            // one layer may be required. Style and format can be added too.
            $wmsLayers = $settings->get('cartography_locate_wms', []);
            // Manage wms layers as uri only.
            $values = $resource->value('dcterms:spatial', ['type' => 'uri', 'all' => true, 'default' => []]);
            foreach ($values as $value) {
                $url = $value->uri();
                if (parse_url($url)) {
                    // Don't add the same url two times.
                    foreach ($wmsLayers as $wmsLayer) {
                        if ($wmsLayer['url'] === $url) {
                            continue 2;
                        }
                    }
                    $wmsLayers[] = [
                        'url' => $url,
                        'label' => $value->value(),
                    ];
                }
            }

            echo $view->partial('cartography/admin/cartography/annotate-locate', [
                'wmsLayers' => $wmsLayers,
                'jsLocate' => $settings->get('cartography_js_locate', ''),
            ]);
        }
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
}
