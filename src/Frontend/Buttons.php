<?php

declare(strict_types=1);

namespace Folio\Frontend;

use Folio\Contract\HasHooks;
use Folio\Document\DocumentBuilder;
use Folio\Service\PrintRoute;
use Folio\Service\Settings;

defined('ABSPATH') || exit;

/**
 * The links that open a printable document.
 *
 * They are ordinary links with visible text. The plugin this was written
 * against renders an icon with no accessible name, so a screen reader announces
 * nothing at all; there is no reason for that here, and a link is also the only
 * form that survives being opened in a new tab or copied to a colleague.
 */
final class Buttons implements HasHooks
{
    public function __construct(private readonly Settings $settings)
    {
    }

    public function registerHooks(): void
    {
        if ($this->settings->bool('button_on_product', true)) {
            add_action('woocommerce_after_add_to_cart_form', [$this, 'renderProductLink'], 20);
        }

        if ($this->settings->bool('button_on_archive', true)) {
            add_action('woocommerce_after_shop_loop', [$this, 'renderArchiveLinks'], 20);
        }

        add_shortcode('folio_print', [$this, 'shortcode']);
        add_shortcode('printvane_print', [$this, 'shortcode']);
    }

    public function renderProductLink(): void
    {
        global $product;

        if (! $product instanceof \WC_Product) {
            return;
        }

        echo wp_kses_post($this->link(
            PrintRoute::url(DocumentBuilder::SHEET, $product->get_id()),
            $this->settings->text('button_sheet'),
        ));
    }

    /**
     * On an archive the links cover what the visitor is actually looking at: a
     * category page prints that category, a filtered view prints the filter's
     * category. That is why the term is read from the queried object rather
     * than from a setting.
     */
    public function renderArchiveLinks(): void
    {
        $termId = 0;
        $object = get_queried_object();

        if ($object instanceof \WP_Term && 'product_cat' === $object->taxonomy) {
            $termId = (int) $object->term_id;
        }

        $out  = '<div class="folio-links">';
        $out .= $this->link(
            PrintRoute::url(DocumentBuilder::CATALOG, $termId),
            $this->settings->text('button_catalog'),
        );
        $out .= $this->link(
            PrintRoute::url(DocumentBuilder::PRICELIST, $termId),
            $this->settings->text('button_pricelist'),
        );
        $out .= '</div>';

        echo wp_kses_post($out);
    }

    /**
     * [folio_print mode="sheet|catalog|pricelist" id="123" text="..."]
     *
     * @param array<string, string>|string $atts
     */
    public function shortcode($atts): string
    {
        $atts = shortcode_atts(
            ['mode' => DocumentBuilder::SHEET, 'id' => '0', 'text' => ''],
            is_array($atts) ? $atts : [],
            'folio_print',
        );

        $mode = sanitize_key($atts['mode']);

        if (! in_array($mode, [DocumentBuilder::SHEET, DocumentBuilder::CATALOG, DocumentBuilder::PRICELIST], true)) {
            return '';
        }

        $id = absint($atts['id']);

        if (DocumentBuilder::SHEET === $mode && $id <= 0) {
            global $product;
            $id = $product instanceof \WC_Product ? $product->get_id() : 0;

            if ($id <= 0) {
                return '';
            }
        }

        $label = '' !== trim($atts['text']) ? $atts['text'] : $this->settings->text('button_' . $mode);

        return $this->link(PrintRoute::url($mode, $id), $label);
    }

    private function link(string $url, string $label): string
    {
        if ('' === trim($label)) {
            return '';
        }

        return '<a class="folio-link button" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }
}
