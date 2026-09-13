<?php
/**
 * Packaged defaults for `folio_settings`.
 *
 * Note what is NOT here: customer-facing sentences. Those live in
 * src/Service/Texts.php as translated defaults, because a string written into
 * this array would never reach the .pot and no language pack could ever
 * translate it. The `texts` key below is deliberately an empty array: an empty
 * value means "use the translated default", and only a merchant override is
 * ever stored.
 *
 * @package Folio
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Where the print links appear.
    'button_on_product' => true,
    'button_on_archive' => true,

    // Which fields each layout prints, in the order ProductData lists them.
    'sheet_fields'     => ['image', 'sku', 'gtin', 'price', 'short', 'description', 'attributes', 'categories', 'stock', 'dimensions', 'weight'],
    'catalog_fields'   => ['image', 'sku', 'price', 'short', 'stock'],
    'pricelist_fields' => ['sku', 'price', 'gtin', 'stock'],

    // Catalog grid density.
    'catalog_columns' => 2,

    // Document furniture.
    'show_logo'   => true,
    'logo_id'     => 0,
    'show_shop'   => true,
    'show_date'   => true,
    'show_page_numbers' => true,

    // Merchant text overrides. Empty means "use the translated default".
    'texts' => [],
];
