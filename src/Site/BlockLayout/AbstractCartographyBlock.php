<?php declare(strict_types=1);

namespace Cartography\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

abstract class AbstractCartographyBlock extends AbstractBlockLayout
{
    protected static bool $headersEmitted = false;

    abstract protected function cartographyType(): string;

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $data = $block->getData() ?: [];
        $data['annotate'] = !empty($data['annotate']);
        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        ?SitePageRepresentation $page = null,
        ?SitePageBlockRepresentation $block = null
    ) {
        $annotate = $block ? (bool) $block->dataValue('annotate') : false;
        $namePrefix = 'o:block[__blockIndex__][o:data]';

        $html = $view->blockAttachmentsForm($block);
        $html .= '<div class="field">'
            . sprintf(
                '<label><input type="checkbox" name="%s[annotate]" value="1"%s/> %s</label>',
                $view->escapeHtmlAttr($namePrefix),
                $annotate ? ' checked="checked"' : '',
                $view->translate('Enable annotation toolbar')
            )
            . '</div>';

        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $entries = [];
        foreach ($block->attachments() as $attachment) {
            $item = $attachment->item();
            if (!$item) {
                continue;
            }
            $media = $attachment->media();
            $entries[] = [
                'resource' => $item,
                'media_id' => $media ? $media->id() : null,
            ];
        }
        if (!$entries) {
            return '';
        }
        $emitHeaders = !self::$headersEmitted;
        self::$headersEmitted = true;

        $annotate = (bool) $block->dataValue('annotate')
            && $view->userIsAllowed(\Annotate\Entity\Annotation::class, 'create');

        return $view->partial('common/block-layout/cartography-block', [
            'entries' => $entries,
            'type' => $this->cartographyType(),
            'annotate' => $annotate,
            'emitHeaders' => $emitHeaders,
        ]);
    }
}
