<?php

declare(strict_types=1);

namespace Folio\Service;

defined('ABSPATH') || exit;

/**
 * Every customer-facing string, in the language of the site.
 *
 * They do not live in config/defaults.php, and that is not a style preference.
 * A string in a config array is never wrapped in a gettext call, so it never
 * reaches the .pot, and once it has been written into the settings option even
 * a complete language pack cannot reach it. The packaged default for an
 * overridable string is an empty string, meaning "use the translated default
 * below"; a merchant who types their own still wins and it is stored as typed.
 */
final class Texts
{
    /**
     * Setting key => translated default.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Buttons and links.
            'button_sheet'     => __('Print this product', 'printvane'),
            'button_catalog'   => __('Print this catalog', 'printvane'),
            'button_pricelist' => __('Print price list', 'printvane'),
            'print_now'        => __('Print, or save as PDF', 'printvane'),

            // Document furniture.
            'doc_sheet_title'     => __('Product sheet', 'printvane'),
            'doc_catalog_title'   => __('Catalog', 'printvane'),
            'doc_pricelist_title' => __('Price list', 'printvane'),
            'footer_note'         => '',

            // Field labels.
            'field_image'             => __('Image', 'printvane'),
            'field_sku'               => __('SKU', 'printvane'),
            'field_gtin'              => __('GTIN', 'printvane'),
            'field_price'             => __('Price', 'printvane'),
            'field_short_description' => __('Summary', 'printvane'),
            'field_description'       => __('Description', 'printvane'),
            'field_attributes'        => __('Attributes', 'printvane'),
            'field_categories'        => __('Categories', 'printvane'),
            'field_stock'             => __('Availability', 'printvane'),
            'field_dimensions'        => __('Dimensions', 'printvane'),
            'field_weight'            => __('Weight', 'printvane'),

            // Stock wording.
            'stock_in'        => __('In stock', 'printvane'),
            'stock_out'       => __('Out of stock', 'printvane'),
            'stock_backorder' => __('On backorder', 'printvane'),

            // Price list column headings.
            'col_sku'   => __('SKU', 'printvane'),
            'col_name'  => __('Product', 'printvane'),
            'col_price' => __('Price', 'printvane'),
            'col_gtin'  => __('GTIN', 'printvane'),
            'col_stock' => __('Availability', 'printvane'),

            // Messages.
            'empty'        => __('There is nothing to print here.', 'printvane'),
            'not_available' => __('This document is not available.', 'printvane'),
        ];
    }

    /**
     * Fills every empty text key with its translated default, leaving anything
     * the merchant typed alone.
     *
     * The Settings store calls this once while resolving, so the rest of the
     * plugin reads plain settings and can never print an English literal by
     * reaching past it.
     *
     * @param array<string, mixed> $texts
     * @return array<string, string>
     */
    public static function apply(array $texts): array
    {
        $resolved = [];

        foreach (self::defaults() as $key => $default) {
            $stored        = (string) ($texts[$key] ?? '');
            $resolved[$key] = '' !== trim($stored) ? $stored : $default;
        }

        return $resolved;
    }

    /**
     * One resolved string, for the handful of callers that have no settings
     * array to hand (field labels built deep inside a renderer).
     */
    public static function get(string $key): string
    {
        $texts = (array) (get_option(Settings::OPTION, [])['texts'] ?? []);

        return self::apply($texts)[$key] ?? '';
    }
}
