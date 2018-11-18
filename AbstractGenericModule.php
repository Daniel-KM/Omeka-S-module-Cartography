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

use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * This generic class allows to manage all methods that should run once only
 * and that are generic to all modules.
 */
abstract class AbstractGenericModule extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->checkDependency($serviceLocator);
        $this->execSqlFromFile($serviceLocator, __DIR__ . '/data/install/schema.sql');
        $settings = $serviceLocator->get('Omeka\Settings');
        $this->manageSettings($settings, 'install');
        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->execSqlFromFile($serviceLocator, __DIR__ . '/data/install/uninstall.sql');
        $settings = $serviceLocator->get('Omeka\Settings');
        $this->manageSettings($settings, 'uninstall');
        $this->manageSiteSettings($serviceLocator, 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        $filepath = __DIR__ . '/data/scripts/upgrade.php';
        if (file_exists($filepath) && filesize($filepath) && is_readable($filepath)) {
            require_once $filepath;
        }
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(Form\ConfigForm::class);

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

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
        $form = $services->get('FormElementManager')->get(Form\ConfigForm::class);

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

    protected function execSqlFromFile(ServiceLocatorInterface $services, $filepath)
    {
        if (!file_exists($filepath) || !filesize($filepath) || !is_readable($filepath)) {
            return;
        }
        $connection = $services->get('Omeka\Connection');
        $sql = file_get_contents($filepath);
        $connection->exec($sql);
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        if (!isset($config[strtolower(__NAMESPACE__)][$key])) {
            return;
        }
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

    protected function manageSiteSettings(ServiceLocatorInterface $services, $process)
    {
        $siteSettings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $this->manageSettings($siteSettings, $process, 'site_settings');
        }
    }

    protected function checkDependency(ServiceLocatorInterface $services)
    {
        if (empty($this->dependency) || $this->isModuleActive($services, $this->dependency)) {
            return;
        }

        $translator = $services->get('MvcTranslator');
        $message = new Message($translator->translate('This module requires the module "%s".'), // @translate
            $this->dependency
        );
        throw new ModuleCannotInstallException($message);
    }

    /**
     * Check if a module is active.
     *
     * @param ServiceLocatorInterface $services
     * @param string $moduleClass
     * @return bool
     */
    protected function isModuleActive(ServiceLocatorInterface $services, $moduleClass)
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleClass);
        return $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }

    /**
     * Disable a module.
     *
     * @param ServiceLocatorInterface $services
     * @param string $moduleClass
     */
    protected function disableModule(ServiceLocatorInterface $services, $moduleClass)
    {
        // Check if the module is enabled first to avoid an exception.
        if (!$this->isModuleActive($services, $moduleClass)) {
            return;
        }

        // Check if the user is a global admin to avoid right issues.
        $user = $services->get('Omeka\AuthenticationService')->getIdentity();
        if (!$user || $user->getRole() !== \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN) {
            return;
        }

        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($moduleClass);
        $moduleManager->deactivate($module);

        $translator = $services->get('MvcTranslator');
        $message = new \Omeka\Stdlib\Message(
            $translator->translate('The module "%s" was automatically deactivated because the dependencies are unavailable.'), // @translate
            $moduleClass
        );
        $messenger = new \Omeka\Mvc\Controller\Plugin\Messenger();
        $messenger->addWarning($message);

        $logger = $services->get('Omeka\Logger');
        $logger->warn($message);
    }
}
