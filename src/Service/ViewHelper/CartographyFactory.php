<?php declare(strict_types=1);

namespace Cartography\Service\ViewHelper;

use Cartography\View\Helper\Cartography;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CartographyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new Cartography(
            $services->has('SpamGuard\FormToken') ? $services->get('SpamGuard\FormToken') : null,
            (int) ($services->get('Omeka\Settings')->get('spamguard_pow_difficulty') ?: 4)
        );
    }
}
