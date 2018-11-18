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
use Omeka\Settings\SettingsInterface;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * This generic class allows to manage all methods that should run once only
 * and that are generic to all modules. A little config over code.
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
        $this->manageConfig($serviceLocator, 'install');
        $this->manageMainSettings($serviceLocator, 'install');
        $this->manageSiteSettings($serviceLocator, 'install');
        $this->manageUserSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->execSqlFromFile($serviceLocator, __DIR__ . '/data/install/uninstall.sql');
        $this->manageConfig($serviceLocator, 'uninstall');
        $this->manageMainSettings($serviceLocator, 'uninstall');
        $this->manageSiteSettings($serviceLocator, 'uninstall');
        // Don't uninstall user settings, they don't belong to admin.
        // $this->manageUserSettings($serviceLocator, 'uninstall');
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

        $formManager = $services->get('FormElementManager');
        $formClass = __NAMESPACE__ . '\Form\ConfigForm';
        if (!$formManager->has($formClass)) {
            return;
        }

        $settings = $services->get('Omeka\Settings');
        $data = $this->prepareDataToPopulate($settings, 'config');
        if (empty($data)) {
            return;
        }

        $form = $services->get('FormElementManager')->get($formClass);
        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space]['config'])) {
            return;
        }

        $formManager = $services->get('FormElementManager');
        $formClass = Form\ConfigForm::class;
        if (!$formManager->has($formClass)) {
            return;
        }

        $params = $controller->getRequest()->getPost();

        $form = $formManager->get($formClass);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();

        $settings = $services->get('Omeka\Settings');
        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function handleMainSettings(Event $event)
    {
        $this->handleAnySettings($event, 'settings');
    }

    public function handleSiteSettings(Event $event)
    {
        $this->handleAnySettings($event, 'site_settings');
    }

    public function handleUserSettings(Event $event)
    {
        $this->handleAnySettings($event, 'user_settings');
    }

    protected function handleAnySettings(Event $event, $settingsType)
    {
        $services = $this->getServiceLocator();

        $settingsTypes = [
            // 'config' => 'Omeka\Settings',
            'settings' => 'Omeka\Settings',
            'site_settings' => 'Omeka\Settings\Site',
            'user_settings' => 'Omeka\Settings\User',
        ];
        if (!isset($settingsTypes[$settingsType])) {
            return;
        }

        $settingFieldsets = [
            // 'config' => Form\ConfigForm::class,
            'settings' => Form\SettingsFieldset::class,
            'site_settings' => Form\SiteSettingsFieldset::class,
            'user_settings' => Form\UserSettingsFieldset::class,
        ];
        if (!isset($settingFieldsets[$settingsType])) {
            return;
        }

        $settings = $services->get($settingsTypes[$settingsType]);
        $data = $this->prepareDataToPopulate($settings, $settingsType);
        if (empty($data)) {
            return;
        }

        $space = strtolower(__NAMESPACE__);

        $fieldset = $services->get('FormElementManager')->get($settingFieldsets[$settingsType]);
        $fieldset->setName($space);
        $form = $event->getTarget();
        $form->add($fieldset);
        $form->get($space)->populateValues($data);
    }

    /**
     * Prepare data for a form or a fieldset.
     *
     * To be overridden by module for specific keys.
     *
     * @todo Use form methods to populate.
     * @param SettingsInterface $settings
     * @param string $settingsType
     * @return array
     */
    protected function prepareDataToPopulate(SettingsInterface $settings, $settingsType)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space][$settingsType])) {
            return;
        }

        $defaultSettings = $config[$space][$settingsType];

        $data = [];
        foreach ($defaultSettings as $name => $value) {
            $val = $settings->get($name, $value);
            $data[$name] = $val;
        }
        return $data;
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

    protected function manageConfig(ServiceLocatorInterface $services, $process)
    {
        $settings = $services->get('Omeka\Settings');
        $this->manageAnySettings($settings, 'config', $process);
    }

    protected function manageMainSettings(ServiceLocatorInterface $services, $process)
    {
        $settings = $services->get('Omeka\Settings');
        $this->manageAnySettings($settings, 'settings', $process);
    }

    protected function manageSiteSettings(ServiceLocatorInterface $services, $process)
    {
        $settingsType = 'site_settings';
        $config = require __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $settings = $services->get('Omeka\Settings\Site');
        $api = $services->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $settings->setTargetId($site->id());
            $this->manageAnySettings($settings, $settingsType, $process);
        }
    }

    protected function manageUserSettings(ServiceLocatorInterface $services, $process)
    {
        $settingsType = 'user_settings';
        $config = require __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $settings = $services->get('Omeka\Settings\User');
        $api = $services->get('Omeka\ApiManager');
        $users = $api->search('users')->getContent();
        foreach ($users as $user) {
            $settings->setTargetId($user->id());
            $this->manageAnySettings($settings, $settingsType, $process);
        }
    }

    protected function manageAnySettings(SettingsInterface $settings, $settingsType, $process)
    {
        $config = require __DIR__ . '/config/module.config.php';
        $space = strtolower(__NAMESPACE__);
        if (empty($config[$space][$settingsType])) {
            return;
        }
        $defaultSettings = $config[$space][$settingsType];
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

    /**
     * Clean the text area and get each line separately.
     *
     * This method fixes Apple.
     *
     * @param string $string
     * @return array
     */
    protected function cleanTextareaInput($string)
    {
        // The str_replace() allows to fix Apple copy/paste.
        return array_filter(array_map('trim', explode(
            PHP_EOL,
            str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $string)
        )));
    }
}
