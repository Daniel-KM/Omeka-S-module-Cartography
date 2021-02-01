<?php declare(strict_types=1);
namespace Cartography\View\Helper;

use Laminas\View\Helper\AbstractHelper;

class HasValueSuggest extends AbstractHelper
{
    /**
     * @var bool
     */
    protected $hasValueSuggest;

    /**
     * @param bool $hasValueSuggest
     */
    public function __construct($hasValueSuggest)
    {
        $this->hasValueSuggest = $hasValueSuggest;
    }

    /**
     * Check if the module ValueSuggest is available.
     *
     * @todo Check if the module ValueSuggest is used in one of the used annotation templates.
     *
     * @return bool
     */
    public function __invoke()
    {
        return $this->hasValueSuggest;
    }
}
