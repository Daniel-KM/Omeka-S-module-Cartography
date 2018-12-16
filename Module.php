<?php
namespace Cartography;

// TODO Remove this requirement.
require_once dirname(__DIR__) . '/Annotate/src/Module/AbstractGenericModule.php';
require_once dirname(__DIR__) . '/Annotate/src/Module/ModuleResourcesTrait.php';

use Annotate\Module\AbstractGenericModule;
use Annotate\Module\ModuleResourcesTrait;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Cartography
 *
 * Allows to annotate an image or a wms map with the w3c web annotation data
 * model and vocabulary.
 *
 * @copyright Daniel Berthereau, 2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractGenericModule
{
    use ModuleResourcesTrait;

    protected $dependencies = [
        'Annotate',
        'DataTypeGeometry',
    ];

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        if (!$this->areModulesActive($this->dependencies)) {
            $this->disableModule(__NAMESPACE__);
            return;
        }

        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        parent::install($serviceLocator);
        $this->installResources();
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->setServiceLocator($serviceLocator);
        foreach (['Cartography Describe', 'Cartography Locate'] as $resourceTemplate) {
            $this->removeResourceTemplate($resourceTemplate);
        }
        parent::uninstall($serviceLocator);
    }

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
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_input_filters',
            [$this, 'handleMainSettingsFilters']
        );
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

    public function handleMainSettings(Event $event)
    {
        $ckEditorHelper = $this->getServiceLocator()->get('ViewHelperManager')
            ->get('ckEditor');
        $ckEditorHelper();
        parent::handleMainSettings($event);
    }

    public function handleMainSettingsFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_display_tab',
            'required' => false,
        ]);
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_template_describe',
            'required' => false,
        ]);
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_template_describe_empty',
            'required' => false,
        ]);
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_template_locate',
            'required' => false,
        ]);
        $inputFilter->get('cartography')->add([
            'name' => 'cartography_template_describe_empty',
            'required' => false,
        ]);
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

        $displayTabs = $services->get('Omeka\Settings')->get('cartography_display_tab', []);
        if (empty($displayTabs)) {
            return;
        }

        $sectionNav = $event->getParam('section_nav');
        if (in_array('describe', $displayTabs)) {
            $sectionNav['describe'] = 'Describe'; // @translate
        }
        if (in_array('locate', $displayTabs)) {
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
        $displayTabs = $settings->get('cartography_display_tab', []);
        if (empty($displayTabs)) {
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
        $displayDescribe = in_array('describe', $displayTabs);
        $displayLocate = in_array('locate', $displayTabs);

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
        $displayTabs = $siteSettings->get('cartography_append_public');
        if (empty($displayTabs)) {
            return;
        }

        $annotate = (bool) $siteSettings->get('cartography_annotate');

        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();
        $displayDescribe = in_array('describe_' . $resourceName . '_show', $displayTabs);
        $displayLocate = in_array('locate_' . $resourceName . '_show', $displayTabs);

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

    protected function installResources()
    {
        $services = $this->getServiceLocator();

        // Complete the annotation custom vocabularies.
        $customVocabPaths = [
            __DIR__ . '/data/custom-vocabs/Cartography-Target-dcterms-format.json',
            __DIR__ . '/data/custom-vocabs/Cartography-Target-rdf-type.json',
        ];
        foreach ($customVocabPaths as $filepath) {
            $this->updateCustomVocab($filepath);
        }

        // Create resource templates for annotations.
        $settings = $services->get('Omeka\Settings');
        $resourceTemplatePaths = [
            'cartography_template_describe' => __DIR__ . '/data/resource-templates/Cartography_Describe.json',
            'cartography_template_locate' => __DIR__ . '/data/resource-templates/Cartography_Locate.json',
        ];
        $resourceTemplateSettings = [
            'cartography_template_describe' => [
                'oa:motivatedBy' => 'oa:Annotation',
                'rdf:value' => 'oa:hasBody',
                'oa:hasPurpose' => 'oa:hasBody',
                'oa:hasBody' => 'oa:Annotation',
            ],
            'cartography_template_locate' => [
                'oa:motivatedBy' => 'oa:Annotation',
                'rdf:value' => 'oa:hasBody',
                'oa:hasPurpose' => 'oa:hasBody',
                'oa:hasBody' => 'oa:Annotation',
            ],
        ];
        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        foreach ($resourceTemplatePaths as $key => $filepath) {
            $resourceTemplate = $this->createResourceTemplate($filepath);
            // Add the special resource template settings.
            $resourceTemplateData[$resourceTemplate->id()] = $resourceTemplateSettings[$key];
            // Set the template as default template.
            $settings->set($key, [$resourceTemplate->id()]);
        }
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }
}
