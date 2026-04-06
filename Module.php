<?php declare(strict_types=1);

namespace Cartography;

if (!class_exists('Common\TraitModule', false)) {
    require_once file_exists(dirname(__DIR__) . '/Common/src/TraitModule.php')
        ? dirname(__DIR__) . '/Common/src/TraitModule.php'
        : dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * Cartography
 *
 * Allows to annotate an image or a wms map with the w3c web annotation
 * data model and vocabulary.
 *
 * @copyright Daniel Berthereau, 2018-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    protected $dependencies = [
        'Annotate',
        'DataTypeGeometry',
    ];

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        if (!$this->areModulesActive($this->dependencies)) {
            $this->disableModule(__NAMESPACE__);
            return;
        }

        $this->addAclRules();
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');

        // Add cartography-specific terms to shared Annotate custom vocabs.
        $this->enrichCustomVocab($api, 'Annotation Target dcterms:format', [
            'application/vnd.ogc.gml',
            'application/vnd.google-earth.kml+xml',
        ]);
        $this->enrichCustomVocab($api, 'Annotation Target rdf:type', [
            'o:Media',
        ]);

        // The resource templates are automatically installed
        // during install.
        $resourceTemplateSettings = [
            'Cartography Describe' => [
                'setting' => 'cartography_template_describe',
                'data' => [
                    'oa:motivatedBy' => 'oa:Annotation',
                    'rdf:value' => 'oa:hasBody',
                    'oa:hasPurpose' => 'oa:hasBody',
                    'oa:hasBody' => 'oa:Annotation',
                ],
            ],
            'Cartography Locate' => [
                'setting' => 'cartography_template_locate',
                'data' => [
                    'oa:motivatedBy' => 'oa:Annotation',
                    'rdf:value' => 'oa:hasBody',
                    'oa:hasPurpose' => 'oa:hasBody',
                    'oa:hasBody' => 'oa:Annotation',
                ],
            ],
        ];
        $resourceTemplateData = $settings->get('annotate_resource_template_data', []);
        foreach ($resourceTemplateSettings as $label => $data) {
            $resourceTemplate = $api->read('resource_templates', ['label' => $label])->getContent();
            // Add the special resource template settings.
            $resourceTemplateData[$resourceTemplate->id()] = $data['data'];
            // Set the template as default template.
            $settings->set($data['setting'], [$resourceTemplate->id()]);
        }
        $settings->set('annotate_resource_template_data', $resourceTemplateData);
    }

    protected function postUninstall(): void
    {
        $installResources = $this->getManageModuleAndResources();
        foreach ([
            'Cartography Describe',
            'Cartography Locate',
        ] as $resourceTemplate) {
            $installResources->removeResourceTemplate(
                $resourceTemplate
            );
        }
    }

    /**
     * Add terms to an existing custom vocab without duplicates.
     */
    protected function enrichCustomVocab(
        $api,
        string $label,
        array $newTerms
    ): void {
        try {
            $customVocab = $api
                ->read('custom_vocabs', ['label' => $label])
                ->getContent();
        } catch (\Throwable $e) {
            return;
        }
        $terms = $customVocab->terms();
        $terms = is_array($terms)
            ? $terms
            : array_map('trim', explode(PHP_EOL, $terms));
        $merged = array_unique(array_merge($terms, $newTerms));
        if (count($merged) === count($terms)) {
            return;
        }
        $api->update('custom_vocabs', $customVocab->id(), [
            'o:label' => $label,
            'o:terms' => implode(PHP_EOL, $merged),
        ], [], ['isPartial' => true]);
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        $roles = $acl->getRoles();
        // TODO Limit rights to access annotate actions too (annotations are already managed).
        $acl
            ->allow(
                null,
                [Controller\Site\CartographyController::class]
            )
            ->allow(
                $roles,
                [Controller\Admin\CartographyController::class]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
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

    public function handleMainSettingsFilters(Event $event): void
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter
            ->add([
                'name' => 'cartography_display_tab',
                'required' => false,
            ])
            ->add([
                'name' => 'cartography_template_describe',
                'required' => false,
            ])
            ->add([
                'name' => 'cartography_template_describe_empty',
                'required' => false,
            ])
            ->add([
                'name' => 'cartography_template_locate',
                'required' => false,
            ])
            ->add([
                'name' => 'cartography_template_describe_empty',
                'required' => false,
            ]);
    }

    public function handleSiteSettingsFilters(Event $event): void
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter
            ->add([
                'name' => 'cartography_append_public',
                'required' => false,
            ])
            ->add([
                'name' => 'cartography_annotate',
                'required' => false,
            ]);
    }

    /**
     * Add a tab to section navigation.
     *
     * @param Event $event
     */
    public function addTab(Event $event): void
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
    public function displayTabSection(Event $event): void
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

        /** @var \Laminas\View\Renderer\PhpRenderer $view */
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
    public function displayPublic(Event $event): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $placements = $siteSettings->get('cartography_placement', []);
        if (empty($placements)) {
            return;
        }

        $view = $event->getTarget();
        $resource = $view->resource;
        $resourceName = $resource->resourceName();
        $displayDescribe = in_array('after/' . $resourceName . '/describe', $placements);
        $displayLocate = in_array('after/' . $resourceName . '/locate', $placements);

        $annotateDescribe = $displayDescribe
            && (bool) $siteSettings->get('cartography_annotate_describe')
            && $view->userIsAllowed(\Annotate\Entity\Annotation::class, 'create');
        $annotateLocate = $displayLocate
            && (bool) $siteSettings->get('cartography_annotate_locate')
            && $view->userIsAllowed(\Annotate\Entity\Annotation::class, 'create');

        // This check avoids to load the css and js two times.
        $displayAll = $displayDescribe && $displayLocate;

        if ($displayDescribe) {
            echo $view->cartography($resource, [
                'type' => 'describe',
                'annotate' => $annotateDescribe,
                'headers' => true,
                'sections' => $displayAll ? ['describe', 'locate'] : ['describe'],
            ]);
        }
        if ($displayLocate) {
            echo $view->cartography($resource, [
                'type' => 'locate',
                'annotate' => $annotateLocate,
                'headers' => !$displayAll,
                'sections' => $displayAll ? ['describe', 'locate'] : ['locate'],
            ]);
        }
    }
}
