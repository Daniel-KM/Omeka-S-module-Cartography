<?php declare(strict_types=1);

namespace Cartography\Controller\Site;

use Cartography\Controller\AbstractCartographyController;

class CartographyController extends AbstractCartographyController
{
    protected function notAjax()
    {
        $siteSlug = $this->params('site-slug');
        return $siteSlug
            ? $this->redirect()->toRoute('site', ['site-slug' => $siteSlug])
            : $this->redirect()->toRoute('top');
    }
}
