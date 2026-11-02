<?php declare(strict_types=1);

namespace CartographyTest\Controller;

use CartographyTest\CartographyTestTrait;
use Omeka\Test\AbstractHttpControllerTestCase;

/**
 * Ajax endpoints of the site and admin cartography controllers.
 */
class CartographyControllerTest extends AbstractHttpControllerTestCase
{
    use CartographyTestTrait;

    protected $item;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->item = $this->createItem();
        $this->site = $this->createSite('cartography-test-' . bin2hex(random_bytes(4)));
    }

    public function tearDown(): void
    {
        $this->setSetting('annotate_public_allow_annotate', false);
        $this->cleanupResources();
        $this->logout();
        parent::tearDown();
    }

    public function testSiteResourceTemplatesAreAvailableAnonymously(): void
    {
        $this->logout();
        $result = $this->getAjax($this->siteUrl('/cartography/resource-templates?type=describe'));
        $this->assertResponseStatusCode(200);
        $this->assertIsArray($result);
        $templateIds = array_filter(array_column($result, 'o:id'));
        $this->assertNotEmpty($templateIds, 'The default describe template is installed.');
    }

    public function testResourceTemplatesRequireType(): void
    {
        $result = $this->getAjax('/admin/cartography/resource-templates');
        $this->assertResponseStatusCode(400);
        $this->assertSame('error', $result['status']);
    }

    public function testGeometriesOfUnknownResource(): void
    {
        $this->getAjax($this->siteUrl('/cartography/999999999/geometries'));
        $this->assertResponseStatusCode(404);
    }

    public function testAnnotateRequiresAjax(): void
    {
        $this->resetApplication();
        $this->dispatch($this->siteUrl('/cartography/annotate'), 'POST', ['wkt' => 'POINT (1 2)']);
        $this->assertResponseStatusCode(302);
    }

    public function testAnnotateRejectsInvalidWkt(): void
    {
        $result = $this->postAjax('/admin/cartography/annotate', [
            'resource_id' => $this->item->id(),
            'wkt' => 'NOT A GEOMETRY',
        ]);
        $this->assertResponseStatusCode(400);
        $this->assertSame('error', $result['status']);
    }

    public function testAnnotateUnknownResource(): void
    {
        $this->postAjax('/admin/cartography/annotate', [
            'resource_id' => 999999999,
            'wkt' => 'POINT (1 2)',
        ]);
        $this->assertResponseStatusCode(404);
    }

    public function testCreateUpdateAndDeleteAnnotation(): void
    {
        $result = $this->postAjax('/admin/cartography/annotate', $this->annotationData());
        $this->assertResponseStatusCode(200);
        $this->assertSame('success', $result['status'], json_encode($result));
        $id = $result['result']['id'];
        $this->trackAnnotation($id);
        $this->assertSame($this->item->id(), $result['result']['resourceId']);

        $result = $this->getAjax('/admin/cartography/' . $this->item->id() . '/geometries');
        $this->assertResponseStatusCode(200);
        $geometries = array_column($result['geometries'], null, 'id');
        $this->assertArrayHasKey($id, $geometries);
        $this->assertSame('POINT (10 20)', $geometries[$id]['wkt']);
        $this->assertSame('#ff0000', $geometries[$id]['options']['color']);
        $this->assertArrayNotHasKey('unmanaged', $geometries[$id]['options']);

        $result = $this->postAjax('/admin/cartography/annotate', [
            'id' => $id,
            'wkt' => 'POINT (3 4)',
            'options' => ['color' => '#00ff00'],
        ]);
        $this->assertResponseStatusCode(200);
        $this->assertSame('success', $result['status'], json_encode($result));

        $result = $this->getAjax('/admin/cartography/' . $this->item->id() . '/geometries');
        $geometries = array_column($result['geometries'], null, 'id');
        $this->assertSame('POINT (3 4)', $geometries[$id]['wkt']);
        $this->assertSame('#00ff00', $geometries[$id]['options']['color']);

        $result = $this->postAjax('/admin/cartography/delete-annotation', ['id' => $id]);
        $this->assertResponseStatusCode(200);
        $this->assertTrue($result['result']);

        $result = $this->getAjax('/admin/cartography/' . $this->item->id() . '/geometries');
        $this->assertNotContains($id, array_column($result['geometries'], 'id'));
    }

    public function testAnonymousCannotDeleteAnnotation(): void
    {
        $result = $this->postAjax('/admin/cartography/annotate', $this->annotationData());
        $id = $result['result']['id'];
        $this->trackAnnotation($id);

        $this->logout();
        $this->postAjax($this->siteUrl('/cartography/delete-annotation'), ['id' => $id]);
        $this->assertContains($this->getResponse()->getStatusCode(), [403, 404]);

        $this->loginAdmin();
        $this->assertNotNull($this->api()->read('annotations', $id)->getContent());
    }

    public function testAnonymousCannotAnnotateByDefault(): void
    {
        $this->logout();
        $this->postAjax($this->siteUrl('/cartography/annotate'), $this->annotationData());
        $this->assertNotSame(200, $this->getResponse()->getStatusCode());
        $this->assertSame(0, $this->countAnnotationsOfItem());
    }

    public function testAnonymousCanAnnotateWhenAllowed(): void
    {
        $this->allowAnonymous();
        $data = $this->annotationData();
        if ($this->hasSpamGuard()) {
            $data['spamguard'] = $this->solveProof();
        }
        $result = $this->postAjax($this->siteUrl('/cartography/annotate'), $data);
        $this->assertResponseStatusCode(200);
        $this->assertSame('success', $result['status'], json_encode($result));
        $id = $result['result']['id'];
        $this->trackAnnotation($id);
        $this->assertNull($result['result']['annotation']['o:owner']);

        // The geometries of an anonymous annotation can be displayed.
        $result = $this->getAjax($this->siteUrl('/cartography/' . $this->item->id() . '/geometries'));
        $this->assertResponseStatusCode(200);
        $geometries = array_column($result['geometries'], null, 'id');
        $this->assertArrayHasKey($id, $geometries);
        $this->assertNull($geometries[$id]['options']['metadata']['o:owner']['id']);
    }

    public function testAnonymousAnnotationWithoutProofIsRejectedWithSpamGuard(): void
    {
        if (!$this->hasSpamGuard()) {
            $this->markTestSkipped('Module SpamGuard not installed.');
        }
        $this->allowAnonymous();
        $result = $this->postAjax($this->siteUrl('/cartography/annotate'), $this->annotationData());
        $this->assertSame(400, $this->getResponse()->getStatusCode(), (string) $this->getResponse()->getContent());
        $this->assertSame('error', $result['status']);
        $this->assertNotEmpty($result['message']);
        $this->assertSame(0, $this->countAnnotationsOfItem());
    }

    public function testAnonymousAnnotationWithForgedProofIsRejectedWithSpamGuard(): void
    {
        if (!$this->hasSpamGuard()) {
            $this->markTestSkipped('Module SpamGuard not installed.');
        }
        $this->allowAnonymous();
        $data = $this->annotationData();
        $data['spamguard'] = $this->solveProof();
        $data['spamguard']['nonce'] = 'forged';
        $this->postAjax($this->siteUrl('/cartography/annotate'), $data);
        $this->assertResponseStatusCode(400);
        $this->assertSame(0, $this->countAnnotationsOfItem());
    }

    protected function siteUrl(string $path): string
    {
        return '/s/' . $this->site->slug() . $path;
    }

    protected function annotationData(): array
    {
        return [
            'resource_id' => $this->item->id(),
            'wkt' => 'POINT (10 20)',
            'options' => ['color' => '#ff0000', 'unmanaged' => 'removed'],
        ];
    }

    protected function allowAnonymous(): void
    {
        $this->setSetting('annotate_public_allow_annotate', true);
        $this->resetApplication();
    }

    protected function hasSpamGuard(): bool
    {
        return $this->getServiceLocator()->has('SpamGuard\FormToken');
    }

    protected function countAnnotationsOfItem(): int
    {
        $this->loginAdmin();
        $total = $this->api()->search('annotations', [
            'resource_id' => $this->item->id(),
        ])->getTotalResults();
        $this->logout();
        return $total;
    }

    /**
     * Get a token issued one minute ago and solve its proof-of-work.
     */
    protected function solveProof(): array
    {
        $services = $this->getServiceLocator();
        $difficulty = (int) ($services->get('Omeka\Settings')->get('spamguard_pow_difficulty') ?: 4);
        $salt = bin2hex(random_bytes(16));
        $ip = (string) (new \Laminas\Http\PhpEnvironment\RemoteAddress())->getIpAddress();
        $token = $services->get('SpamGuard\FormToken')->issue($salt, $ip, time() - 60);
        $prefix = str_repeat('0', $difficulty);
        for ($nonce = 0; ; ++$nonce) {
            if (strpos(hash('sha256', $salt . ':' . $nonce), $prefix) === 0) {
                break;
            }
        }
        return ['token' => $token, 'salt' => $salt, 'nonce' => (string) $nonce];
    }
}
