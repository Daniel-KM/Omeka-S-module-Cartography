<?php declare(strict_types=1);

namespace CartographyTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

trait CartographyTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array
     */
    protected $createdResources = [];

    /**
     * @var bool
     */
    protected $isAdmin = false;

    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if ($this->services === null) {
            $this->services = $this->getApplication()->getServiceManager();
        }
        return $this->services;
    }

    protected function api(): ApiManager
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    protected function loginAdmin(): void
    {
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
        $this->isAdmin = true;
    }

    protected function logout(): void
    {
        $this->getServiceLocator()->get('Omeka\AuthenticationService')->clearIdentity();
        $this->isAdmin = false;
    }

    /**
     * Rebuild the application, for example to apply new settings to the acl.
     */
    protected function resetApplication(): void
    {
        $isAdmin = $this->isAdmin;
        $this->reset();
        $this->services = null;
        $isAdmin ? $this->loginAdmin() : $this->logout();
    }

    protected function setSetting(string $name, $value): void
    {
        $this->loginAdmin();
        $this->getServiceLocator()->get('Omeka\Settings')->set($name, $value);
        $this->logout();
    }

    protected function createItem(): ItemRepresentation
    {
        $item = $this->api()->create('items', [
            'dcterms:title' => [[
                'type' => 'literal',
                'property_id' => 1,
                '@value' => 'Cartography test item',
            ]],
        ])->getContent();
        $this->createdResources[] = ['items', $item->id()];
        return $item;
    }

    protected function createSite(string $slug): SiteRepresentation
    {
        $site = $this->api()->create('sites', [
            'o:slug' => $slug,
            'o:theme' => 'default',
            'o:title' => 'Cartography test site',
            'o:is_public' => true,
        ])->getContent();
        $this->createdResources[] = ['sites', $site->id()];
        return $site;
    }

    protected function trackAnnotation(?int $id): void
    {
        if ($id) {
            $this->createdResources[] = ['annotations', $id];
        }
    }

    protected function cleanupResources(): void
    {
        $this->services = null;
        $this->loginAdmin();
        foreach (array_reverse($this->createdResources) as [$resourceName, $id]) {
            try {
                $this->api()->delete($resourceName, $id);
            } catch (\Exception $e) {
                // Already deleted.
            }
        }
        $this->createdResources = [];
    }

    /**
     * Dispatch an ajax post and return the decoded json response.
     */
    protected function postAjax(string $url, array $data): array
    {
        $this->resetApplication();
        $this->getRequest()
            ->setMethod('POST')
            ->getPost()->fromArray($data);
        $this->dispatch($url, 'POST', $data, true);
        return json_decode((string) $this->getResponse()->getContent(), true) ?: [];
    }

    protected function getAjax(string $url): array
    {
        $this->resetApplication();
        $this->dispatch($url, 'GET', [], true);
        return json_decode((string) $this->getResponse()->getContent(), true) ?: [];
    }
}
