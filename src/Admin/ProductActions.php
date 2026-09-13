<?php

declare(strict_types=1);

namespace Folio\Admin;

use Folio\Contract\HasHooks;
use Folio\Document\DocumentBuilder;
use Folio\Service\PrintRoute;

defined('ABSPATH') || exit;

/**
 * A "Print" row action on the products list, so a shop manager can produce one
 * product's sheet without opening the product first.
 */
final class ProductActions implements HasHooks
{
    public function registerHooks(): void
    {
        add_filter('post_row_actions', [$this, 'addRowAction'], 10, 2);
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function addRowAction(array $actions, \WP_Post $post): array
    {
        if ('product' !== $post->post_type || ! current_user_can('read_post', $post->ID)) {
            return $actions;
        }

        $actions['folio_print'] = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            esc_url(PrintRoute::url(DocumentBuilder::SHEET, $post->ID)),
            esc_html__('Print', 'plogins-folio'),
        );

        return $actions;
    }
}
