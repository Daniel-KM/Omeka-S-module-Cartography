<?php
namespace Cartography;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

// TODO Remove this requirement.
require_once 'AbstractGenericModule.php';

/**
 * Cartography
 *
 * Allows to annotate an image or a wms map with the w3c web annotation data model and vocabulary.
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractGenericModule
{
    protected $dependency = 'Annotate';

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        // Manage the module dependency, in particular when upgrading.
        // Once disabled, this current method and other ones are no more called.
        $services = $event->getApplication()->getServiceManager();
        if (!$this->isModuleActive($services, $this->dependency)) {
            $this->disableModule($services, __NAMESPACE__);
            return;
        }

        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        parent::install($serviceLocator);
        $this->installResources($serviceLocator);
    }

    // TODO Cartography vocabulary is not removed.

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $roles = $acl->getRoles();
        // TODO Limit rights to access annotate actions too (annotations are already managed).
        $acl->allow(
            null,
            [Controller\Site\CartographyController::class]
        );
        $acl->allow(
            $roles,
            [Controller\Admin\CartographyController::class]
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
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'addSiteSettingsFilters']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $renderer->ckEditor();
        return parent::getConfigForm($renderer);
    }

    public function addFormElementsSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $config = $services->get('Config');
        $form = $event->getTarget();

        $defaultSiteSettings = $config[strtolower(__NAMESPACE__)]['site_settings'];

        $fieldset = new Fieldset('cartography');
        $fieldset->setLabel('Annotate images and maps (cartography)'); // @translate

        $fieldset->add([
            'name' => 'cartography_append_public',
            'type' => Element\MultiCheckbox::class,
            'options' => [
                'label' => 'Append to pages', // @translate
                'info' => 'If unchecked, the viewer can be added via the helper in the theme or the block in any page.', // @translate
                'value_options' => [
                    // 'describe_item_sets_show' => 'Describe item set', // @translate
                    'describe_items_show' => 'Describe item', // @translate
                    // 'describe_media_show' => 'Describe media', // @translate
                    // 'locate_item_sets_show' => 'Locate item set', // @translate
                    'locate_items_show' => 'Locate item', // @translate
                    // 'locate_media_show' => 'Locate media', // @translate
                ],
            ],
            'attributes' => [
                'id' => 'cartography_append_public',
                'value' => $siteSettings->get(
                    'cartography_append_public',
                    $defaultSiteSettings['cartography_append_public']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'cartography_annotate',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Enable annotation', // @translate
                'info' => 'Allows to enable/disable the image/map annotation on this specific site. In all cases, the rights are defined by the module Annotate.', // @translate
            ],
            'attributes' => [
                'id' => 'cartography_annotate',
                'disabled' => 'disabled',
                'value' => $siteSettings->get(
                    'cartography_annotate',
                    $defaultSiteSettings['cartography_annotate']
                ),
            ],
        ]);

        $form->add($fieldset);
    }

    public function addSiteSettingsFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_append_public',
            'required' => false,
        ]);
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

        $settings = $services->get('Omeka\Settings');
        $displayTab = $settings->get('cartography_display_tab', []);
        if (empty($displayTab)) {
            return;
        }

        $acl = $services->get('Omeka\Acl');

        $rightRead = $acl->userIsAllowed(\Annotate\Entity\Annotation::class, 'read');
        if (!$rightRead) {
            return;
        }

        /** @var \Zend\View\Renderer\PhpRenderer $view */
        $view = $event->getTarget();
        $resource = $view->resource;
        $displayDescribe = in_array('describe', $displayTab);
        $displayLocate = in_array('locate', $displayTab);

        // This check avoids to load the css and js two times.
        $displayAll = $displayDescribe && $displayLocate;

        if ($displayDescribe) {
            echo $view->cartography($resource, [
                'type' => 'describe',
                'annotate' => true,
                'headers' => true,
                'sections' => $displayAll ? ['describe', 'locate'] : ['describe'],
            ]);
        }
        if ($displayLocate) {
            echo $view->cartography($resource, [
                'type' => 'locate',
                'annotate' => true,
                'headers' => !$displayAll,
                'sections' => $displayAll ? ['describe', 'locate'] : ['locate'],
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
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $displayTab = $siteSettings->get('cartography_append_public');
        if (empty($displayTab)) {
            return;
        }

        $annotate = (bool) $siteSettings->get('cartography_annotate');

        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();
        $displayDescribe = in_array('describe_' . $resourceName . '_show', $displayTab);
        $displayLocate = in_array('locate_' . $resourceName . '_show', $displayTab);

        // This check avoids to load the css and js two times.
        $displayAll = $displayDescribe && $displayLocate;

        if ($displayDescribe) {
            echo $view->cartography($resource, [
                'type' => 'describe',
                'annotate' => $annotate,
                'headers' => true,
                'sections' => $displayAll ? ['describe', 'locate'] : ['describe'],
            ]);
        }
        if ($displayLocate) {
            echo $view->cartography($resource, [
                'type' => 'locate',
                'annotate' => $annotate,
                'headers' => !$displayAll,
                'sections' => $displayAll ? ['describe', 'locate'] : ['locate'],
            ]);
        }
    }

    protected function installResources(ServiceLocatorInterface $services)
    {
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
        $this->createVocabulary($services, $vocabulary);

        // Add specific custom vocabularies.
        $customVocabPaths = [
            // TODO Move custom vocab into annotation or use a specific to Cartography?
            __DIR__ . '/data/custom-vocabs/Annotation-Body-oa-hasPurpose.json',
            __DIR__ . '/data/custom-vocabs/Cartography-cartography-uncertainty.json',
        ];
        foreach ($customVocabPaths as $filepath) {
            $this->createCustomVocab($services, $filepath);
        }

        // Complete the annotation custom vocabularies.
        $customVocabPaths = [
            __DIR__ . '/data/custom-vocabs/Cartography-Target-dcterms-format.json',
            __DIR__ . '/data/custom-vocabs/Cartography-Target-rdf-type.json',
        ];
        foreach ($customVocabPaths as $filepath) {
            $this->updateCustomVocab($services, $filepath);
        }
    }

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
}
