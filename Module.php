<?php
namespace Cartography;

// TODO Remove this requirement.
require_once dirname(__DIR__) . '/Annotate/src/Module/AbstractGenericModule.php';
require_once dirname(__DIR__) . '/Annotate/src/Module/ModuleResourcesTrait.php';

use Annotate\Module\AbstractGenericModule;
use Annotate\Module\ModuleResourcesTrait;
use Doctrine\Common\Collections\Criteria;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;

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
        if (!$this->isModuleActive($this->dependency)) {
            $this->disableModule(__NAMESPACE__);
            return;
        }

        // Load composer dependencies. No need to use init().
        require_once __DIR__ . '/vendor/autoload.php';

        // TODO It is possible to register each geometry separately (line, point…). Is it useful? Or a Omeka type is enough (geometry:point…)? Or a column in the table (no)?
        \Doctrine\DBAL\Types\Type::addType(
            'geometry',
            \CrEOF\Spatial\DBAL\Types\GeometryType::class
        );

        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        // Copy of the parent process in order to check the database version.
        $useMyIsam = $this->requireMyIsamToSupportGeometry($serviceLocator);
        $filepath = $useMyIsam
            ? $this->modulePath() . '/data/install/schema-myisam.sql'
            :  $this->modulePath() . '/data/install/schema.sql';

        $this->setServiceLocator($serviceLocator);
        $this->checkDependency();
        $this->checkDependencies();
        $this->execSqlFromFile($filepath);
        $this->manageConfig('install');
        $this->manageMainSettings('install');
        $this->manageSiteSettings('install');
        $this->manageUserSettings('install');

        $this->installResources();
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

        // Manage the geometry data type.
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
            \Annotate\Controller\Admin\AnnotationController::class,
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach(
                $controller,
                'view.add.after',
                [$this, 'prepareResourceForm']
            );
            $sharedEventManager->attach(
                $controller,
                'view.edit.after',
                [$this, 'prepareResourceForm']
            );
        }
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            \Annotate\Api\Adapter\AnnotationBodyAdapter::class,
            \Annotate\Api\Adapter\AnnotationTargetAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.hydrate.post',
                [$this, 'saveGeometryData']
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

    /**
     * Prepare resource forms for geometry data type.
     *
     * @param Event $event
     */
    public function prepareResourceForm(Event $event)
    {
        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('css/cartography.css', 'Cartography'));
        $headScript = $view->headScript();
        // $settings = $this->getServiceLocator()->get('Omeka\Settings');
        // $datatypes = $settings->get('cartography_datatypes', []);
        $datatypes = ['geometry'];
        $headScript->appendScript('var geometryDatatypes = ' . json_encode($datatypes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';');
        $headScript->appendFile($view->assetUrl('vendor/terraformer/terraformer.min.js', 'Cartography'));
        $headScript->appendFile($view->assetUrl('vendor/terraformer-wkt-parser/terraformer-wkt-parser.min.js', 'Cartography'));
        $headScript->appendFile($view->assetUrl('js/cartography-geometry-datatype.js', 'Cartography'));
    }

    /**
     * Save geometric data into the geometry table.
     *
     * This clears all existing geometries and (re)saves them during create and
     * update operations for a resource (item, item set, media). We do this as
     * an easy way to ensure that the geometries in the geometry table are in
     * sync with the geometries in the value table.
     *
     * @see \NumericDataTypes\Module::saveNumericData()
     *
     * @param Event $event
     */
    public function saveGeometryData(Event $event)
    {
        $entity = $event->getParam('entity');
        if (!$entity instanceof \Omeka\Entity\Resource) {
            // This is not a resource.
            return;
        }

        $services = $this->getServiceLocator();
        $dataTypeName = 'geometry';
        /** @var \Cartography\DataType\Geometry $dataType */
        $dataType = $services->get('Omeka\DataTypeManager')->get($dataTypeName);

        $entityValues = $entity->getValues();
        $criteria = Criteria::create()->where(Criteria::expr()->eq('type', $dataTypeName));
        $matchingValues = $entityValues->matching($criteria);
        // This resource has no data values of this type.
        if (!count($matchingValues)) {
            return;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        $dataTypeClass = \Cartography\Entity\DataTypeGeometry::class;

        // TODO Remove this persist, that is used only when a geometry is updated on the map.
        // Persist is required for annotation, since there is no cascade persist
        // between annotation and values.
        $entityManager->persist($entity);

        /** @var \Cartography\Entity\DataTypeGeometry[] $existingDataValues */
        $existingDataValues = [];
        if ($entity->getId()) {
            $dql = sprintf('SELECT n FROM %s n WHERE n.resource = :resource', $dataTypeClass);
            $query = $entityManager->createQuery($dql);
            $query->setParameter('resource', $entity);
            $existingDataValues = $query->getResult();
        }

        foreach ($matchingValues as $value) {
            // Avoid ID churn by reusing data rows.
            $dataValue = current($existingDataValues);
            // No more number rows to reuse. Create a new one.
            if ($dataValue === false) {
                $dataValue = new $dataTypeClass;
                $entityManager->persist($dataValue);
            } else {
                // Null out data values as we reuse them. Note that existing
                // data values are already managed and will update during flush.
                $existingDataValues[key($existingDataValues)] = null;
                next($existingDataValues);
            }
            $dataValue->setResource($entity);
            $dataValue->setProperty($value->getProperty());
            $geometry = $dataType->getGeometryFromValue($value->getValue());
            $dataValue->setValue($geometry);
        }

        // Remove any data values that weren't reused.
        foreach ($existingDataValues as $existingDataValue) {
            if ($existingDataValue !== null) {
                $entityManager->remove($existingDataValue);
            }
        }
    }

    /**
     * Get all data types added by this module.
     *
     * @return \Omeka\DataType\AbstractDataType[]
     */
    public function getGeometryDataTypes()
    {
        $dataTypes = $this->getConfig()['data_types']['invokables'];
        $list = $this->getConfig()['data_types']['invokables'];
        $geometryDataTypes = [];
        foreach (array_keys($list) as $dataType) {
            $geometryDataTypes[$dataType] = $dataTypes->get($dataType);
        }
        return $geometryDataTypes;
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
        foreach ($resourceTemplatePaths as $key => $filepath) {
            $resourceTemplate = $this->createResourceTemplate($filepath);
            $settings->set($key, [$resourceTemplate->id()]);
        }
    }

    /**
     * Check if the Omeka database requires myIsam to support Geometry.
     *
     * @see readme.md.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return bool Return false by default: if a specific database is used,
     * it is presumably geometry compliant.
     */
    protected function requireMyIsamToSupportGeometry(ServiceLocatorInterface $services)
    {
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        $sql = 'SHOW VARIABLES LIKE "version";';
        $stmt = $connection->query($sql);
        $version= $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);

        $isMySql = stripos($version, 'mysql') !== false;
        if ($isMySql) {
            return version_compare($version, '5.7.5', '<');
        }

        $isMariaDb = stripos($version, 'mariadb') !== false;
        if ($isMariaDb) {
            return version_compare($version, '10.2.2', '<');
        }

        $sql = 'SHOW VARIABLES LIKE "innodb_version";';
        $stmt = $connection->query($sql);
        $version= $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);
        $isInnoDb = !empty($version);
        if ($isInnoDb) {
            return version_compare($version, '5.7.14', '<');
        }

        return false;
    }
}
