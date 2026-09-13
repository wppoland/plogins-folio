<?php
/**
 * Guards the two things about ProductQuery that are easy to break and silent
 * when broken. Run inside WordPress:
 *
 *   wp eval-file tests/product-query-check.php
 *
 * @package Folio
 */

// No declare(strict_types=1) here: wp eval-file runs this through eval(), where
// a declare must be the first statement in the script and cannot be.

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run this through wp eval-file, it needs WordPress.\n");
    exit(2);
}

// Counters live in $GLOBALS on purpose. wp eval-file runs this through eval(),
// where top-level variables are function-scoped, so a `global $checks` inside
// the helper below would bind to a different variable and the script would
// report "OK (0 checks)" however many assertions ran. A summary that says
// green while having measured nothing is worse than no check at all.
$GLOBALS['folio_failures'] = 0;
$GLOBALS['folio_checks']   = 0;

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function folio_assert(string $what, $expected, $actual): void
{
    ++$GLOBALS['folio_checks'];

    if ($expected === $actual) {
        echo "ok   {$what}\n";
        return;
    }

    ++$GLOBALS['folio_failures'];
    echo "FAIL {$what}\n";
    echo '     expected: ' . wp_json_encode($expected) . "\n";
    echo '     actual  : ' . wp_json_encode($actual) . "\n";
}

$query = \Folio\Plugin::instance()->container()->get(\Folio\Service\ProductQuery::class);
$all   = $query->ids([]);

if (count($all['ids']) < 5) {
    fwrite(STDERR, "This check needs at least 5 published products.\n");
    exit(2);
}

// 1. Hydration returns exactly the products asked for, in the order asked for.
//
// wc_get_products() does not support 'post__in': the key is dropped in silence
// and the query answers with the newest N published products instead. That
// reads as success and prints the wrong catalogue. This check fails the moment
// someone swaps 'include' back.
$want = array_slice($all['ids'], 0, 5);
$got  = [];

foreach ($query->each($want) as $product) {
    $got[] = $product->get_id();
}

folio_assert('hydration returns the requested ids, in order', $want, $got);

// 2. A document is capped, and the cap is what paginates it.
add_filter('folio/batch_size', static fn (): int => 2);

$page1 = $query->ids([], 1);
$page2 = $query->ids([], 2);

folio_assert('a part holds at most the cap', 2, count($page1['ids']));
folio_assert('the part count is derived from the total', (int) ceil($page1['total'] / 2), $page1['parts']);
folio_assert('part 2 continues where part 1 stopped', array_slice($all['ids'], 2, 2), $page2['ids']);
folio_assert('no product appears in two parts', [], array_intersect($page1['ids'], $page2['ids']));

remove_all_filters('folio/batch_size');

// 3. Asking past the last part clamps rather than returning an empty document
// with a footer claiming it holds nothing.
$beyond = $query->ids([], 9999);
folio_assert('a part number past the end clamps to the last part', $beyond['parts'], $beyond['parts']);
folio_assert('the clamped part still holds products', true, count($beyond['ids']) > 0);

$failures = (int) $GLOBALS['folio_failures'];
$checks   = (int) $GLOBALS['folio_checks'];

if (0 === $checks) {
    echo "\nproduct-query-check: nothing was measured, so this is not a pass.\n";
    exit(2);
}

echo "\n" . ($failures > 0 ? "product-query-check: {$failures} of {$checks} FAILED\n" : "product-query-check: OK ({$checks} checks)\n");

exit($failures > 0 ? 1 : 0);
