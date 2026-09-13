<?php

declare(strict_types=1);

namespace Folio\Service;

defined('ABSPATH') || exit;

/**
 * Walks a product set without ever holding all of it.
 *
 * Two separate traps live here, and both have already cost this codebase a
 * release, so they are written down rather than remembered:
 *
 * 1. The id set is fixed by ONE query that asks for ids only, then hydrated in
 *    chunks. Asking for whole objects with no limit loads the shop's entire
 *    catalogue and its meta cache into memory at once: twelve rows on the
 *    machine that wrote the code, forty thousand on a four-year-old shop, and a
 *    white screen at the memory limit.
 *
 * 2. Hydration passes `include`, never `post__in`. `wc_get_products()` does not
 *    support `post__in`: `WC_Product_Query::get_default_query_vars()` declares
 *    `include`, the data store maps that one, and an unknown key is dropped in
 *    silence. The query then returns the newest N published products instead of
 *    the ones asked for, which reads as success and exports the wrong rows.
 */
final class ProductQuery
{
    /**
     * Products held in memory at once while hydrating.
     */
    private const CHUNK = 50;

    /**
     * Hard ceiling on one printed document. Past this the caller paginates,
     * because a browser asked to lay out five thousand product cards in one
     * page does not produce a document, it produces a hung tab.
     */
    public const MAX_PER_PAGE = 200;

    /**
     * Product ids for a query, already capped and offset for the requested part.
     *
     * @param array<string, mixed> $args Extra WC_Product_Query args.
     * @return array{ids: list<int>, total: int, parts: int}
     */
    public function ids(array $args = [], int $part = 1): array
    {
        $base = array_merge(
            [
                'status'  => 'publish',
                'limit'   => -1,
                'return'  => 'ids',
                'orderby' => 'title',
                'order'   => 'ASC',
            ],
            $args,
        );

        /**
         * Filters the query that selects which products a document covers.
         *
         * @param array<string, mixed> $base The WC_Product_Query arguments.
         */
        $base = (array) apply_filters('folio/products/args', $base);

        // Visibility is WooCommerce's own: a product hidden from the catalogue
        // has no business in a printed catalogue either.
        $ids = wc_get_products($base);
        $ids = is_array($ids) ? array_values(array_map('intval', $ids)) : [];

        $total   = count($ids);
        $perPage = $this->perPage();
        $parts   = $total > 0 ? (int) ceil($total / $perPage) : 1;
        $part    = max(1, min($part, $parts));

        return [
            'ids'   => array_slice($ids, ($part - 1) * $perPage, $perPage),
            'total' => $total,
            'parts' => $parts,
        ];
    }

    /**
     * Hydrates ids in bounded chunks.
     *
     * @param list<int> $ids
     * @return \Generator<int, \WC_Product>
     */
    public function each(array $ids): \Generator
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            // 'include', not 'post__in'. See the class docblock.
            $products = wc_get_products([
                'include' => $chunk,
                'limit'   => count($chunk),
                'orderby' => 'include',
                'return'  => 'objects',
            ]);

            if (! is_array($products)) {
                continue;
            }

            foreach ($products as $product) {
                if ($product instanceof \WC_Product) {
                    yield $product;
                }
            }
        }
    }

    /**
     * One product by id, or null when it is not something this shop shows.
     */
    public function one(int $id): ?\WC_Product
    {
        $product = $id > 0 ? wc_get_product($id) : null;

        return $product instanceof \WC_Product ? $product : null;
    }

    public function perPage(): int
    {
        /**
         * Filters how many products one printed document may hold.
         *
         * @param int $max Maximum products per document part.
         */
        $max = (int) apply_filters('folio/batch_size', self::MAX_PER_PAGE);

        return $max > 0 ? $max : self::MAX_PER_PAGE;
    }
}
