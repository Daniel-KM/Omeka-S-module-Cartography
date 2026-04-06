<?php declare(strict_types=1);

namespace Cartography\Site\ResourcePageBlockLayout;

class CartographyLocate extends AbstractCartographyResourceBlock
{
    public function getLabel(): string
    {
        return 'Cartography: Locate'; // @translate
    }

    protected function cartographyType(): string
    {
        return 'locate';
    }
}
