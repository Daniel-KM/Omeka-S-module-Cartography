<?php declare(strict_types=1);

namespace Cartography\Site\ResourcePageBlockLayout;

class CartographyDescribe extends AbstractCartographyResourceBlock
{
    public function getLabel(): string
    {
        return 'Cartography: Describe'; // @translate
    }

    protected function cartographyType(): string
    {
        return 'describe';
    }
}
