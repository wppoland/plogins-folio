<?php

declare(strict_types=1);

namespace Folio\Service;

use Folio\Contract\HasHooks;
use Folio\Document\Document;
use Folio\Document\DocumentBuilder;
use Folio\Render\HtmlRenderer;

defined('ABSPATH') || exit;

/**
 * Serves the printable document at `?folio=...`.
 *
 * There is no nonce here on purpose. This is a GET view of pages the shop
 * already shows to everyone, so a nonce would only break the one thing a
 * merchant actually wants: sending the link to a customer. What guards it is
 * visibility, checked below, plus the same `nocache_headers()` the invoice
 * route uses, because the prices on the page are the prices of whoever is
 * asking and a page cache would hand one customer another's.
 */
final class PrintRoute implements HasHooks
{
    public const QUERY = 'folio';

    public function __construct(
        private readonly DocumentBuilder $builder,
        private readonly HtmlRenderer $renderer,
        private readonly ProductQuery $products,
        private readonly Settings $settings,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'maybeRender']);
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY;
        $vars[] = 'folio_id';
        $vars[] = 'folio_part';

        return $vars;
    }

    public function maybeRender(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $mode = isset($_GET[self::QUERY]) ? sanitize_key(wp_unslash($_GET[self::QUERY])) : '';
        $id   = isset($_GET['folio_id']) ? absint($_GET['folio_id']) : 0;
        $part = isset($_GET['folio_part']) ? max(1, absint($_GET['folio_part'])) : 1;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ('' === $mode) {
            return;
        }

        $document = match ($mode) {
            DocumentBuilder::SHEET     => $this->sheet($id),
            DocumentBuilder::CATALOG   => $this->catalog($id, $part),
            DocumentBuilder::PRICELIST => $this->priceList($id, $part),
            default                    => null,
        };

        if (null === $document) {
            wp_die(
                esc_html(Texts::get('not_available')),
                '',
                ['response' => 404],
            );
        }

        // One person's view of one shop's prices, never a page. Same reasoning
        // as the invoice route: a CDN or page cache here serves a wholesale
        // customer's price list to the next visitor.
        nocache_headers();

        // Registered here rather than on wp_enqueue_scripts because this runs
        // on template_redirect, which fires first: a handle registered on the
        // later hook does not exist yet and the document prints unstyled.
        wp_register_style(
            'printvane-print',
            plugins_url('assets/css/print.css', \Folio\PLUGIN_FILE),
            [],
            \Folio\VERSION,
        );
        wp_register_script(
            'printvane-print',
            plugins_url('assets/js/print.js', \Folio\PLUGIN_FILE),
            [],
            \Folio\VERSION,
            true,
        );

        $folio_document = $document;
        $folio_body     = $this->renderer->render($document);
        $folio_settings = $this->settings;
        $folio_next     = $this->nextPartUrl($mode, $id, $document);

        require FOLIO_DIR . 'templates/document.php';
        exit;
    }

    private function sheet(int $id): ?Document
    {
        $product = $this->products->one($id);

        if (null === $product || ! $this->mayPrint($product)) {
            return null;
        }

        return $this->builder->sheet($product);
    }

    private function catalog(int $termId, int $part): Document
    {
        [$args, $title] = $this->termScope($termId);

        return $this->builder->catalog($args, $part, $title);
    }

    private function priceList(int $termId, int $part): Document
    {
        [$args, $title] = $this->termScope($termId);

        return $this->builder->priceList($args, $part, $title);
    }

    /**
     * A category id narrows the document to that category; without one it
     * covers the whole shop.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function termScope(int $termId): array
    {
        if ($termId <= 0) {
            return [[], ''];
        }

        $term = get_term($termId, 'product_cat');

        if (! $term instanceof \WP_Term) {
            return [[], ''];
        }

        return [['category' => [$term->slug]], $term->name];
    }

    /**
     * Visibility, not authorisation: whatever the shop shows publicly may be
     * printed, and anything else needs the capability to read it.
     */
    private function mayPrint(\WC_Product $product): bool
    {
        $allowed = 'publish' === $product->get_status()
            && $product->is_visible()
            && ! post_password_required($product->get_id());

        if (! $allowed) {
            $allowed = current_user_can('read_post', $product->get_id());
        }

        /**
         * Filters whether a product may be printed.
         *
         * @param bool        $allowed Whether the current request may print it.
         * @param \WC_Product $product The product being printed.
         */
        return (bool) apply_filters('folio/can_print', $allowed, $product);
    }

    /**
     * The link to the next part, when the set did not fit in one document.
     */
    private function nextPartUrl(string $mode, int $id, Document $document): string
    {
        $part  = (int) $document->metaValue('part', 1);
        $parts = (int) $document->metaValue('parts', 1);

        if ($part >= $parts) {
            return '';
        }

        return self::url($mode, $id, $part + 1);
    }

    /**
     * A print URL. Public so buttons and the admin row action build the same
     * one rather than each assembling their own.
     */
    public static function url(string $mode, int $id = 0, int $part = 1): string
    {
        $args = [self::QUERY => $mode];

        if ($id > 0) {
            $args['folio_id'] = $id;
        }

        if ($part > 1) {
            $args['folio_part'] = $part;
        }

        return add_query_arg($args, home_url('/'));
    }
}
