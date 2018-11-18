<?php
namespace Cartography;

use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

// TODO Remove this requirement.
require_once 'AbstractGenericModule.php';
require_once 'ModuleResourcesTrait.php';

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
    use ModuleResourcesTrait;

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
            [$this, 'handleSiteSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'handleSiteSettingsFilters']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $renderer->ckEditor();
        return parent::getConfigForm($renderer);
    }

    public function handleSiteSettingsFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_append_public',
            'required' => false,
        ]);
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_annotate',
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

    /**
     * @todo To be moved inside trait.
     * @param ServiceLocatorInterface $services
     */
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
            __DIR__ . '/data/custom-vocabs/Cartography-oa-MotivatedBy-Locate.json',
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

        // Create resource templates for annotations.
        $settings = $services->get('Omeka\Settings');
        $resourceTemplatePaths = [
            'cartography_template_describe' => __DIR__ . '/data/resource-templates/Cartography_Describe.json',
            'cartography_template_locate' => __DIR__ . '/data/resource-templates/Cartography_Locate.json',
        ];
        foreach ($resourceTemplatePaths as $key => $filepath) {
            $resourceTemplate = $this->createResourceTemplate($services, $filepath);
            $settings->set($key, [$resourceTemplate->id()]);
        }
    }
}
