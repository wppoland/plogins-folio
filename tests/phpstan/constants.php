<?php
/**
 * Constants needed by PHPStan to analyse the plugin without bootstrapping WordPress.
 *
 * @package Folio
 */

declare(strict_types=1);

namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }
    if (! defined('FOLIO_DIR')) {
        define('FOLIO_DIR', '/tmp/folio/');
    }
    if (! defined('FOLIO_URL')) {
        define('FOLIO_URL', 'https://example.test/wp-content/plugins/folio/');
    }
}

namespace Folio {
    if (! defined('Folio\\VERSION')) {
        define('Folio\\VERSION', '0.1.0');
    }
    if (! defined('Folio\\PLUGIN_FILE')) {
        define('Folio\\PLUGIN_FILE', '/tmp/printvane/printvane.php');
    }
}
