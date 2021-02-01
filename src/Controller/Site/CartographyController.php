<?php declare(strict_types=1);
namespace Cartography\Controller\Site;

use Cartography\Controller\AbstractCartographyController;

class CartographyController extends AbstractCartographyController
{
    protected function notAjax()
    {
        return $this->redirect()->toRoute('site');
    }
}
