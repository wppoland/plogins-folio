<?php
/**
 * Boot order: services listed here are resolved from the container and have
 * their registerHooks() called during Plugin::boot(). Each must implement
 * Folio\Contract\HasHooks.
 *
 * @package Folio
 *
 * @return array<class-string>
 */

declare(strict_types=1);

use Folio\Admin\ProductActions;
use Folio\Admin\Settings;
use Folio\Frontend\Buttons;
use Folio\Service\PrintRoute;

defined('ABSPATH') || exit;

return [
    // The print route first: it answers on template_redirect and must be able
    // to take over the request before anything renders a theme.
    PrintRoute::class,
    Buttons::class,
    ...(is_admin() ? [Settings::class, ProductActions::class] : []),
];
