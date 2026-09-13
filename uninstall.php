<?php
/**
 * Uninstall cleanup for Folio.
 *
 * Removes the plugin's own options when it is deleted from wp-admin. Nothing this
 * plugin writes belongs to the shop's content: it stores settings and nothing
 * else, and WooCommerce data is never touched.
 *
 * @package Folio
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('folio_settings');
delete_option('folio_db_version');
