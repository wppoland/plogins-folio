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
            'button_sheet'     => __('Print this product', 'plogins-folio'),
            'button_catalog'   => __('Print this catalog', 'plogins-folio'),
            'button_pricelist' => __('Print price list', 'plogins-folio'),
            'print_now'        => __('Print, or save as PDF', 'plogins-folio'),

            // Document furniture.
            'doc_sheet_title'     => __('Product sheet', 'plogins-folio'),
            'doc_catalog_title'   => __('Catalog', 'plogins-folio'),
            'doc_pricelist_title' => __('Price list', 'plogins-folio'),
            'footer_note'         => '',

            // Field labels.
            'field_image'             => __('Image', 'plogins-folio'),
            'field_sku'               => __('SKU', 'plogins-folio'),
            'field_gtin'              => __('GTIN', 'plogins-folio'),
            'field_price'             => __('Price', 'plogins-folio'),
            'field_short_description' => __('Summary', 'plogins-folio'),
            'field_description'       => __('Description', 'plogins-folio'),
            'field_attributes'        => __('Attributes', 'plogins-folio'),
            'field_categories'        => __('Categories', 'plogins-folio'),
            'field_stock'             => __('Availability', 'plogins-folio'),
            'field_dimensions'        => __('Dimensions', 'plogins-folio'),
            'field_weight'            => __('Weight', 'plogins-folio'),

            // Stock wording.
            'stock_in'        => __('In stock', 'plogins-folio'),
            'stock_out'       => __('Out of stock', 'plogins-folio'),
            'stock_backorder' => __('On backorder', 'plogins-folio'),

            // Price list column headings.
            'col_sku'   => __('SKU', 'plogins-folio'),
            'col_name'  => __('Product', 'plogins-folio'),
            'col_price' => __('Price', 'plogins-folio'),
            'col_gtin'  => __('GTIN', 'plogins-folio'),
            'col_stock' => __('Availability', 'plogins-folio'),

            // Messages.
            'empty'        => __('There is nothing to print here.', 'plogins-folio'),
            'not_available' => __('This document is not available.', 'plogins-folio'),
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
