<?php declare(strict_types=1);

namespace Cartography\Site\BlockLayout;

class CartographyLocate extends AbstractCartographyBlock
{
    public function getLabel()
    {
        return 'Cartography: Locate'; // @translate
    }

    protected function cartographyType(): string
    {
        return 'locate';
    }
}
