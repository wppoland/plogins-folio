<?php

declare(strict_types=1);

namespace Folio;

use Folio\Contract\HasHooks;

defined('ABSPATH') || exit;

final class Plugin
{
    private static ?self $instance = null;

    private Container $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();
        (require __DIR__ . '/../config/services.php')($this->container);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function container(): Container
    {
        return $this->container;
    }

    /**
     * Absolute URL to a bundled asset, relative to the plugin root.
     */
    public function url(string $path = ''): string
    {
        return FOLIO_URL . ltrim($path, '/');
    }

    /**
     * Absolute filesystem path to a bundled file, relative to the plugin root.
     */
    public function path(string $path = ''): string
    {
        return FOLIO_DIR . ltrim($path, '/');
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->container->get(Migrator::class)->maybeMigrate();

        /** @var array<class-string<HasHooks>> $hooks */
        $hooks = require __DIR__ . '/../config/hooks.php';
        foreach ($hooks as $id) {
            $service = $this->container->get($id);
            if ($service instanceof HasHooks) {
                $service->registerHooks();
            }
        }

        /**
         * Fires after the plugin has fully booted.
         *
         * @param Plugin $plugin The booted plugin instance.
         */
        do_action('folio/booted', $this);
    }
}
