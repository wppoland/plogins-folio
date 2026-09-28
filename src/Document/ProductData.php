<?php

declare(strict_types=1);

namespace Folio\Document;

use Folio\Service\Texts;

defined('ABSPATH') || exit;

/**
 * Turns one product into the ordered label/value pairs a document shows.
 *
 * Prices are read through WooCommerce's own price API in the current request's
 * context, never from the raw meta. That is deliberate and it is what makes the
 * printed price the price the person holding the paper would actually pay:
 * catalog-mode plugins filter `woocommerce_get_price_html` to hide it, tiered
 * and role-based pricing filter the price itself, and the shop's own
 * tax-display setting is already baked into `get_price_html()`. Reading the
 * meta would quietly undo all three.
 */
final class ProductData
{
    /**
     * Every field this plugin can print, in the order it prints them.
     *
     * @return array<string, string> field key => label
     */
    public function fields(): array
    {
        $fields = [
            'image'       => Texts::get('field_image'),
            'sku'         => Texts::get('field_sku'),
            'gtin'        => Texts::get('field_gtin'),
            'price'       => Texts::get('field_price'),
            'short'       => Texts::get('field_short_description'),
            'description' => Texts::get('field_description'),
            'attributes'  => Texts::get('field_attributes'),
            'categories'  => Texts::get('field_categories'),
            'stock'       => Texts::get('field_stock'),
            'dimensions'  => Texts::get('field_dimensions'),
            'weight'      => Texts::get('field_weight'),
        ];

        /**
         * Filters the printable fields and their labels.
         *
         * @param array<string, string> $fields Field key => label.
         */
        $filtered = apply_filters('folio/fields', $fields);

        return is_array($filtered) ? $filtered : $fields;
    }

    /**
     * The label/value pairs for one product, limited to the enabled fields and
     * skipping anything the product has nothing to say about.
     *
     * @param list<string> $enabled
     * @return list<array{key: string, label: string, value: string}>
     */
    public function pairs(\WC_Product $product, array $enabled): array
    {
        $labels = $this->fields();
        $pairs  = [];

        foreach ($labels as $key => $label) {
            if (! in_array($key, $enabled, true) || 'image' === $key) {
                continue;
            }

            $value = $this->value($product, $key);

            if ('' === $value) {
                continue;
            }

            $pairs[] = ['key' => $key, 'label' => $label, 'value' => $value];
        }

        return $pairs;
    }

    /**
     * One field as printable text. Returns an empty string when the product
     * carries nothing for it, so the caller can drop the row entirely rather
     * than print a label with a blank beside it.
     */
    public function value(\WC_Product $product, string $key): string
    {
        return match ($key) {
            'sku'         => $product->get_sku(),
            'gtin'        => $this->gtin($product),
            // Already formatted, already tax-adjusted, already filtered by
            // whatever prices this shop. Only <del>/<ins> survive, so a sale
            // still prints struck through; the screen-reader sentences go,
            // because the document never loads the CSS that hides them.
            'price'       => self::price($product->get_price_html()),
            'short'       => $this->text($product->get_short_description()),
            'description' => $this->text($product->get_description()),
            'attributes'  => $this->attributes($product),
            'categories'  => $this->terms($product, 'product_cat'),
            'stock'       => $this->stock($product),
            'dimensions'  => $this->dimensions($product),
            'weight'      => $this->weight($product),
            default       => '',
        };
    }

    /**
     * The image to print, at a size worth printing.
     *
     * A thumbnail blown up to 60mm on paper is about 63 dpi and looks like a
     * fax, so this asks for the single-product size and lets the browser pick a
     * larger candidate from the srcset when the print resolution warrants it.
     *
     * @return array{id: int, url: string, srcset: string, alt: string}|null
     */
    public function image(\WC_Product $product): ?array
    {
        $id = (int) $product->get_image_id();

        if ($id <= 0) {
            return null;
        }

        $src = wp_get_attachment_image_src($id, 'woocommerce_single');

        if (! is_array($src) || '' === (string) $src[0]) {
            return null;
        }

        $srcset = wp_get_attachment_image_srcset($id, 'woocommerce_single');

        return [
            'id'     => $id,
            'url'    => (string) $src[0],
            'srcset' => is_string($srcset) ? $srcset : '',
            'alt'    => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
        ];
    }

    /**
     * WooCommerce 9.2 gave products a native GTIN/UPC/EAN/ISBN field. Older
     * releases have no accessor, and this plugin supports WooCommerce 8.0, so
     * ask before calling.
     */
    private function gtin(\WC_Product $product): string
    {
        if (! method_exists($product, 'get_global_unique_id')) {
            return '';
        }

        return (string) $product->get_global_unique_id();
    }

    private function stock(\WC_Product $product): string
    {
        $status = $product->get_stock_status();
        $qty    = $product->get_stock_quantity();

        if ($product->managing_stock() && null !== $qty) {
            return sprintf('%s (%d)', $this->statusLabel($status), $qty);
        }

        return $this->statusLabel($status);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'instock'     => Texts::get('stock_in'),
            'outofstock'  => Texts::get('stock_out'),
            'onbackorder' => Texts::get('stock_backorder'),
            default       => '',
        };
    }

    /**
     * WooCommerce formats a product with no dimensions as the localised "N/A"
     * rather than an empty string, so asking the formatter first would print a
     * Dimensions row saying nothing on every product that has none.
     */
    private function dimensions(\WC_Product $product): string
    {
        $dimensions = array_filter(
            $product->get_dimensions(false),
            static fn ($value): bool => '' !== trim((string) $value),
        );

        if ([] === $dimensions) {
            return '';
        }

        return wp_strip_all_tags((string) wc_format_dimensions($product->get_dimensions(false)));
    }

    private function weight(\WC_Product $product): string
    {
        $weight = $product->get_weight();

        if ('' === (string) $weight) {
            return '';
        }

        return $weight . ' ' . get_option('woocommerce_weight_unit', 'kg');
    }

    /**
     * Visible attributes as "Name: value, value" lines.
     */
    private function attributes(\WC_Product $product): string
    {
        $out = [];

        foreach ($product->get_attributes() as $attribute) {
            if (! $attribute instanceof \WC_Product_Attribute || ! $attribute->get_visible()) {
                continue;
            }

            $name   = wc_attribute_label($attribute->get_name());
            $values = $attribute->is_taxonomy()
                ? wp_get_post_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names'])
                : $attribute->get_options();

            if (is_wp_error($values) || [] === $values) {
                continue;
            }

            $out[] = $name . ': ' . implode(', ', array_map('strval', $values));
        }

        return implode("\n", $out);
    }

    private function terms(\WC_Product $product, string $taxonomy): string
    {
        $names = wp_get_post_terms($product->get_id(), $taxonomy, ['fields' => 'names']);

        if (is_wp_error($names) || [] === $names) {
            return '';
        }

        return implode(', ', array_map('strval', $names));
    }

    /**
     * Shortcodes are run, then everything is flattened to text: a document is
     * printed, so an embedded gallery or a form has nowhere to go.
     */
    private function text(string $html): string
    {
        if ('' === trim($html)) {
            return '';
        }

        $rendered = do_shortcode($html);
        $rendered = wp_strip_all_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $rendered));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $rendered));
    }

    /** Markup a printed price may keep. */
    public const PRICE_TAGS = ['del' => [], 'ins' => []];

    public static function price(string $html): string
    {
        $html = (string) preg_replace('#<span[^>]*class="[^"]*screen-reader-text[^"]*"[^>]*>.*?</span>#s', '', $html);

        return trim((string) preg_replace('/\s+/', ' ', wp_kses($html, self::PRICE_TAGS)));
    }
}
