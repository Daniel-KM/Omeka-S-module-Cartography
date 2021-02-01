<?php declare(strict_types=1);
namespace Cartography\Controller\Admin;

use Cartography\Controller\AbstractCartographyController;

class CartographyController extends AbstractCartographyController
{
    protected function notAjax()
    {
        $this->messenger()->addError('This url is not available.'); // @translate
        return $this->redirect()->toRoute('admin');
    }
}
