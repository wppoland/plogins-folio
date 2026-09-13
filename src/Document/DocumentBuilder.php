<?php

declare(strict_types=1);

namespace Folio\Document;

use Folio\Service\ProductQuery;
use Folio\Service\Settings;

defined('ABSPATH') || exit;

/**
 * The one place that decides what a document contains and in what order.
 *
 * The plugin this was written against has nine layout methods that each
 * concatenate their own HTML, picked by a nine-branch if/elseif that is itself
 * copy-pasted three times, once per output format. Adding a field there means
 * editing twenty-seven places and getting all of them right. Here a layout is a
 * list of blocks, the same list whatever draws it, so a field added to the
 * sheet appears in every medium at once.
 */
final class DocumentBuilder
{
    public const SHEET     = 'sheet';
    public const CATALOG   = 'catalog';
    public const PRICELIST = 'pricelist';

    public function __construct(
        private readonly Settings $settings,
        private readonly ProductData $data,
        private readonly ProductQuery $query,
    ) {
    }

    /**
     * One product, one sheet.
     */
    public function sheet(\WC_Product $product): Document
    {
        $fields = $this->settings->fields(self::SHEET);
        $blocks = [$this->header($this->settings->text('doc_sheet_title'))];

        foreach ($this->productBlocks($product, $fields, true) as $block) {
            $blocks[] = $block;
        }

        $blocks[] = $this->footer(1, 1);

        return $this->finish(new Document(
            $product->get_name(),
            self::SHEET,
            $blocks,
            ['product_id' => $product->get_id(), 'total' => 1, 'part' => 1, 'parts' => 1],
        ));
    }

    /**
     * Many products as cards, one grid.
     *
     * @param array<string, mixed> $args
     */
    public function catalog(array $args, int $part = 1, string $title = ''): Document
    {
        return $this->many(self::CATALOG, $args, $part, $title !== '' ? $title : $this->settings->text('doc_catalog_title'));
    }

    /**
     * Many products as table rows, no images.
     *
     * @param array<string, mixed> $args
     */
    public function priceList(array $args, int $part = 1, string $title = ''): Document
    {
        return $this->many(self::PRICELIST, $args, $part, $title !== '' ? $title : $this->settings->text('doc_pricelist_title'));
    }

    /**
     * @param array<string, mixed> $args
     */
    private function many(string $layout, array $args, int $part, string $title): Document
    {
        $selection = $this->query->ids($args, $part);
        $fields    = $this->settings->fields($layout);

        $blocks = [$this->header($title)];

        if (self::PRICELIST === $layout) {
            $blocks[] = Block::of(Block::FIELDS, [
                'role'    => 'columns',
                'columns' => $this->priceListColumns($fields),
            ]);
        }

        foreach ($this->query->each($selection['ids']) as $product) {
            if (self::PRICELIST === $layout) {
                $blocks[] = Block::of(Block::ROW, [
                    'product_id' => $product->get_id(),
                    'cells'      => $this->priceListCells($product, $fields),
                ]);
                continue;
            }

            foreach ($this->productBlocks($product, $fields, false) as $block) {
                $blocks[] = $block;
            }
        }

        $blocks[] = $this->footer($selection['total'], $selection['parts'], $part, count($selection['ids']));

        return $this->finish(new Document(
            $title,
            $layout,
            $blocks,
            [
                'total' => $selection['total'],
                'part'  => $part,
                'parts' => $selection['parts'],
                'shown' => count($selection['ids']),
            ],
        ));
    }

    /**
     * One product's blocks. A sheet gives each product its own page; a catalog
     * card does not break.
     *
     * @param list<string> $fields
     * @return list<Block>
     */
    private function productBlocks(\WC_Product $product, array $fields, bool $ownPage): array
    {
        $blocks = [];

        $blocks[] = Block::of(Block::HEADING, [
            'text'       => $product->get_name(),
            'product_id' => $product->get_id(),
            'level'      => $ownPage ? 1 : 2,
            'url'        => $product->get_permalink(),
        ]);

        if (in_array('image', $fields, true)) {
            $image = $this->data->image($product);

            if (null !== $image) {
                $blocks[] = Block::of(Block::IMAGE, $image + ['product_id' => $product->get_id()]);
            }
        }

        $pairs = $this->data->pairs($product, $fields);

        if ([] !== $pairs) {
            $blocks[] = Block::of(Block::FIELDS, [
                'product_id' => $product->get_id(),
                'pairs'      => $pairs,
            ]);
        }

        if ($ownPage) {
            $blocks[] = Block::of(Block::PAGE_BREAK);
        }

        return $blocks;
    }

    /**
     * @param list<string> $fields
     * @return list<array{key: string, label: string}>
     */
    private function priceListColumns(array $fields): array
    {
        $columns = [];

        if (in_array('sku', $fields, true)) {
            $columns[] = ['key' => 'sku', 'label' => $this->settings->text('col_sku')];
        }

        $columns[] = ['key' => 'name', 'label' => $this->settings->text('col_name')];

        if (in_array('gtin', $fields, true)) {
            $columns[] = ['key' => 'gtin', 'label' => $this->settings->text('col_gtin')];
        }

        if (in_array('stock', $fields, true)) {
            $columns[] = ['key' => 'stock', 'label' => $this->settings->text('col_stock')];
        }

        if (in_array('price', $fields, true)) {
            $columns[] = ['key' => 'price', 'label' => $this->settings->text('col_price')];
        }

        return $columns;
    }

    /**
     * @param list<string> $fields
     * @return array<string, string>
     */
    private function priceListCells(\WC_Product $product, array $fields): array
    {
        $cells = ['name' => $product->get_name()];

        foreach (['sku', 'gtin', 'stock', 'price'] as $key) {
            if (in_array($key, $fields, true)) {
                $cells[$key] = $this->data->value($product, $key);
            }
        }

        return $cells;
    }

    private function header(string $title): Block
    {
        $logoId = $this->settings->int('logo_id');
        $logo   = '';

        if ($this->settings->bool('show_logo') && $logoId > 0) {
            $src  = wp_get_attachment_image_src($logoId, 'medium');
            $logo = is_array($src) ? (string) $src[0] : '';
        }

        return Block::of(Block::HEADER, [
            'title' => $title,
            'logo'  => $logo,
            'shop'  => $this->settings->bool('show_shop') ? get_bloginfo('name') : '',
            'date'  => $this->settings->bool('show_date') ? wp_date(get_option('date_format', 'Y-m-d')) : '',
        ]);
    }

    /**
     * The footer carries the terminator line.
     *
     * A printed HTML document that was cut short by a fatal or a time limit
     * looks, on paper, exactly like a document that ended. The last line says
     * how many products it holds and which part it is, so a merchant can tell
     * the difference without counting.
     */
    private function footer(int $total, int $parts, int $part = 1, ?int $shown = null): Block
    {
        return Block::of(Block::FOOTER, [
            'note'  => $this->settings->text('footer_note'),
            'total' => $total,
            'shown' => $shown ?? $total,
            'part'  => $part,
            'parts' => $parts,
            'pages' => $this->settings->bool('show_page_numbers'),
        ]);
    }

    private function finish(Document $document): Document
    {
        /**
         * Filters a built document before it is rendered.
         *
         * This is the escape hatch, and deliberately the only broad one: it
         * hands over the whole block list, so a caller can add, remove or
         * reorder anything without the plugin having to anticipate it. A
         * renderer skips a block type it does not know.
         *
         * @param Document $document The built document.
         */
        $filtered = apply_filters('folio/document', $document);

        return $filtered instanceof Document ? $filtered : $document;
    }
}
