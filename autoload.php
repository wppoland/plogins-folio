<?php
/**
 * Autoloading: prefer Composer's optimized classmap when present, otherwise fall
 * back to a minimal PSR-4 autoloader so the plugin still boots if vendor/ is
 * absent. Folio is self-contained, it has no runtime Composer dependencies.
 *
 * @package Folio
 */

declare(strict_types=1);

namespace Folio;

defined('ABSPATH') || exit;

$folio_composer = __DIR__ . '/vendor/autoload.php';
if (is_readable($folio_composer)) {
    require_once $folio_composer;
    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix  = 'Folio\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});
