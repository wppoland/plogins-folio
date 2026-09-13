<?php
/**
 * Service wiring. Returns a closure that registers every service in the
 * container. Folio is self-contained: no runtime Composer dependencies.
 *
 * @package Folio
 */

declare(strict_types=1);

use Folio\Admin\ProductActions;
use Folio\Admin\Settings;
use Folio\Container;
use Folio\Document\DocumentBuilder;
use Folio\Document\ProductData;
use Folio\Frontend\Buttons;
use Folio\Migrator;
use Folio\Render\HtmlRenderer;
use Folio\Service\PrintRoute;
use Folio\Service\ProductQuery;
use Folio\Service\Settings as SettingsStore;

defined('ABSPATH') || exit;

return static function (Container $c): void {
    $c->singleton(Migrator::class, static fn (): Migrator => new Migrator());

    $c->singleton(SettingsStore::class, static fn (): SettingsStore => new SettingsStore());

    $c->singleton(ProductData::class, static fn (): ProductData => new ProductData());

    $c->singleton(ProductQuery::class, static fn (): ProductQuery => new ProductQuery());

    // The one place that decides what a document contains. Both renderers read
    // what this produces, which is why a field added here needs no change in
    // whatever draws it.
    $c->singleton(DocumentBuilder::class, static fn (): DocumentBuilder => new DocumentBuilder(
        $c->get(SettingsStore::class),
        $c->get(ProductData::class),
        $c->get(ProductQuery::class),
    ));

    $c->singleton(HtmlRenderer::class, static fn (): HtmlRenderer => new HtmlRenderer(
        $c->get(SettingsStore::class),
    ));

    $c->singleton(PrintRoute::class, static fn (): PrintRoute => new PrintRoute(
        $c->get(DocumentBuilder::class),
        $c->get(HtmlRenderer::class),
        $c->get(ProductQuery::class),
        $c->get(SettingsStore::class),
    ));

    $c->singleton(Buttons::class, static fn (): Buttons => new Buttons(
        $c->get(SettingsStore::class),
    ));

    if (is_admin()) {
        $c->singleton(Settings::class, static fn (): Settings => new Settings(
            $c->get(SettingsStore::class),
            $c->get(ProductData::class),
        ));
        $c->singleton(ProductActions::class, static fn (): ProductActions => new ProductActions());
    }
};
