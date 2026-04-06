<?php declare(strict_types=1);

namespace Cartography\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

abstract class AbstractCartographyResourceBlock implements ResourcePageBlockLayoutInterface
{
    protected static bool $headersEmitted = false;

    abstract protected function cartographyType(): string;

    public function getCompatibleResourceNames(): array
    {
        return ['items', 'media', 'item_sets'];
    }

    public function render(
        PhpRenderer $view,
        AbstractResourceEntityRepresentation $resource
    ): string {
        $type = $this->cartographyType();
        // Compute annotate as the OR of both resource page blocks so the first
        // header emission loads leaflet-draw and sets global userRights even
        // when describe and locate share the same page.
        $canAnnotate = $view->userIsAllowed(
            \Annotate\Entity\Annotation::class,
            'create'
        );
        $annotateDescribe = $canAnnotate
            && (bool) $view->siteSetting('cartography_annotate_describe');
        $annotateLocate = $canAnnotate
            && (bool) $view->siteSetting('cartography_annotate_locate');
        $annotate = $type === 'describe' ? $annotateDescribe : $annotateLocate;
        $annotateGlobal = $annotateDescribe || $annotateLocate;
        $emitHeaders = !self::$headersEmitted;
        self::$headersEmitted = true;

        return $view->partial('common/resource-page-block-layout/cartography-block', [
            'resource' => $resource,
            'type' => $type,
            'annotate' => $annotate,
            'annotateGlobal' => $annotateGlobal,
            'emitHeaders' => $emitHeaders,
        ]);
    }
}
