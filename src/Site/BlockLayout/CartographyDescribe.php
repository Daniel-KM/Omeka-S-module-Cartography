<?php declare(strict_types=1);

namespace Cartography\Site\BlockLayout;

class CartographyDescribe extends AbstractCartographyBlock
{
    public function getLabel()
    {
        return 'Cartography: Describe'; // @translate
    }

    protected function cartographyType(): string
    {
        return 'describe';
    }
}
