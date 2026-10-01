<?php

declare(strict_types=1);

namespace Folio\Render;

use Folio\Document\Block;
use Folio\Document\Document;
use Folio\Document\ProductData;
use Folio\Service\Settings;

defined('ABSPATH') || exit;

/**
 * Draws a Document as the standalone HTML page the browser prints.
 *
 * A renderer is a match on the block type and nothing more. A type it does not
 * know is skipped, which is what lets another plugin add a block through
 * `folio/document` that only a different medium can draw.
 */
final class HtmlRenderer
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /**
     * Everything the printed document is allowed to contain.
     *
     * The template escapes against this at the point of output rather than
     * relying on the escaping done while building, because `folio/document`
     * lets another plugin add blocks and a guarantee that depends on nobody
     * hooking a filter is not a guarantee.
     *
     * @return array<string, array<string, bool>>
     */
    public static function allowedHtml(): array
    {
        $common = ['class' => true, 'id' => true, 'aria-hidden' => true, 'aria-label' => true];

        return [
            'main'    => $common,
            'header'  => $common,
            'footer'  => $common,
            'figure'  => $common,
            'div'     => $common,
            'span'    => $common,
            'p'       => $common,
            'h1'      => $common,
            'h2'      => $common,
            'dl'      => $common,
            'dt'      => $common,
            'dd'      => $common,
            'br'      => [],
            'del'     => [],
            'ins'     => [],
            'a'       => $common + ['href' => true, 'rel' => true, 'target' => true],
            'img'     => $common + [
                'src'      => true,
                'srcset'   => true,
                'sizes'    => true,
                'alt'      => true,
                'width'    => true,
                'height'   => true,
                'loading'  => true,
                'decoding' => true,
            ],
            'table'   => $common,
            'thead'   => $common,
            'tbody'   => $common,
            'tr'      => $common,
            'th'      => $common + ['scope' => true, 'colspan' => true],
            'td'      => $common + ['colspan' => true],
        ];
    }

    public function render(Document $document): string
    {
        $out = [];

        // A price list is a run of ROW blocks behind a column head. The table
        // tags are opened when that head appears and closed by the first block
        // that is not a row, so the block list stays a flat list and no block
        // has to emit half a tag.
        $inTable = false;

        foreach ($document->blocks as $block) {
            if (! $block instanceof Block) {
                continue;
            }

            $opensTable = Block::FIELDS === $block->type && 'columns' === $block->string('role');

            if ($inTable && Block::ROW !== $block->type && ! $opensTable) {
                $out[]   = '</tbody></table>';
                $inTable = false;
            }

            $html = match ($block->type) {
                Block::HEADER     => $this->header($block),
                Block::FOOTER     => $this->footer($block),
                Block::HEADING    => $this->heading($block),
                Block::IMAGE      => $this->image($block),
                Block::TEXT       => $this->text($block),
                Block::FIELDS     => $this->fields($block),
                Block::ROW        => $this->row($block),
                Block::PAGE_BREAK => '<div class="folio-break" aria-hidden="true"></div>',
                default           => null,
            };

            if (null === $html || '' === $html) {
                continue;
            }

            if ($opensTable) {
                $html    = '<table class="folio-table">' . $html . '<tbody>';
                $inTable = true;
            }

            $out[] = $html;
        }

        if ($inTable) {
            $out[] = '</tbody></table>';
        }

        return $this->wrap($document, implode("\n", $out));
    }

    private function wrap(Document $document, string $inner): string
    {
        $classes = 'folio folio--' . sanitize_html_class($document->layout);

        if ('catalog' === $document->layout) {
            $columns  = max(1, min(4, $this->settings->int('catalog_columns', 2)));
            $classes .= ' folio--cols-' . $columns;
        }

        return '<main class="' . esc_attr($classes) . '">' . $inner . '</main>';
    }

    private function header(Block $block): string
    {
        $logo = $block->string('logo');
        $shop = $block->string('shop');
        $date = $block->string('date');

        $out = '<header class="folio-header">';

        if ('' !== $logo) {
            $out .= '<img class="folio-header__logo" src="' . esc_url($logo) . '" alt="' . esc_attr($shop) . '">';
        }

        $out .= '<div class="folio-header__meta">';

        if ('' !== $shop) {
            $out .= '<span class="folio-header__shop">' . esc_html($shop) . '</span>';
        }

        $out .= '<h1 class="folio-header__title">' . esc_html($block->string('title')) . '</h1>';

        if ('' !== $date) {
            $out .= '<span class="folio-header__date">' . esc_html($date) . '</span>';
        }

        return $out . '</div></header>';
    }

    private function footer(Block $block): string
    {
        $note  = $block->string('note');
        $total = (int) $block->get('total', 0);
        $shown = (int) $block->get('shown', $total);
        $part  = (int) $block->get('part', 1);
        $parts = (int) $block->get('parts', 1);

        $out = '<footer class="folio-footer">';

        if ('' !== $note) {
            $out .= '<p class="folio-footer__note">' . esc_html($note) . '</p>';
        }

        $summary = $parts > 1
            ? sprintf(
                /* translators: 1: products on this part, 2: part number, 3: total parts, 4: products in total. */
                __('End of document: %1$d products, part %2$d of %3$d, %4$d in total.', 'printvane'),
                $shown,
                $part,
                $parts,
                $total,
            )
            : sprintf(
                /* translators: %d: number of products in the document. */
                _n('End of document: %d product.', 'End of document: %d products.', $shown, 'printvane'),
                $shown,
            );

        $out .= '<p class="folio-footer__end">' . esc_html($summary) . '</p>';

        return $out . '</footer>';
    }

    private function heading(Block $block): string
    {
        $level = 2 === (int) $block->get('level', 2) ? 'h2' : 'h1';
        $text  = esc_html($block->string('text'));
        $url   = $block->string('url');

        $inner = '' !== $url
            ? '<a class="folio-title__link" href="' . esc_url($url) . '">' . $text . '</a>'
            : $text;

        return '<' . $level . ' class="folio-title">' . $inner . '</' . $level . '>';
    }

    private function image(Block $block): string
    {
        $url = $block->string('url');

        if ('' === $url) {
            return '';
        }

        $srcset = $block->string('srcset');
        $attrs  = '' !== $srcset ? ' srcset="' . esc_attr($srcset) . '" sizes="(min-width: 600px) 50vw, 100vw"' : '';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attrs is built above from esc_attr'd values only.
        return '<figure class="folio-image"><img src="' . esc_url($url) . '"' . $attrs
            . ' alt="' . esc_attr($block->string('alt')) . '" loading="lazy"></figure>';
    }

    private function text(Block $block): string
    {
        $text = $block->string('text');

        return '' === $text ? '' : '<p class="folio-text">' . nl2br(esc_html($text)) . '</p>';
    }

    private function fields(Block $block): string
    {
        // The price list's column head arrives as a FIELDS block with a role.
        if ('columns' === $block->string('role')) {
            $out = '<thead><tr>';

            foreach ((array) $block->get('columns', []) as $column) {
                $out .= '<th scope="col">' . esc_html((string) ($column['label'] ?? '')) . '</th>';
            }

            return $out . '</tr></thead>';
        }

        $pairs = (array) $block->get('pairs', []);

        if ([] === $pairs) {
            return '';
        }

        $out = '<dl class="folio-fields">';

        foreach ($pairs as $pair) {
            $label = (string) ($pair['label'] ?? '');
            $value = (string) ($pair['value'] ?? '');
            $key   = sanitize_html_class((string) ($pair['key'] ?? ''));

            $out .= '<div class="folio-field folio-field--' . esc_attr($key) . '">';
            $out .= '<dt>' . esc_html($label) . '</dt>';
            $out .= '<dd>' . ('price' === $key ? wp_kses($value, ProductData::PRICE_TAGS) : nl2br(esc_html($value))) . '</dd>';
            $out .= '</div>';
        }

        return $out . '</dl>';
    }

    private function row(Block $block): string
    {
        $cells = (array) $block->get('cells', []);

        if ([] === $cells) {
            return '';
        }

        // Same order the column head used, so the cells line up with it.
        $order = ['sku', 'name', 'gtin', 'stock', 'price'];
        $out   = '<tr>';

        foreach ($order as $key) {
            if (! array_key_exists($key, $cells)) {
                continue;
            }

            $out .= '<td class="folio-cell folio-cell--' . esc_attr($key) . '">'
                . ('price' === $key ? wp_kses((string) $cells[$key], ProductData::PRICE_TAGS) : esc_html((string) $cells[$key])) . '</td>';
        }

        return $out . '</tr>';
    }
}
