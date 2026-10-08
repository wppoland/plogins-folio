=== Printvane - Product Sheets, Catalogs and Price Lists for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, catalog, price list, product sheet, print
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Print a product sheet, a whole category catalog or a price list from your shop, and save any of them as a PDF from your browser.

== Description ==

Printvane turns what is already in your shop into something you can put on paper: a one-page sheet for a single product, a catalog of a whole category, or a compact price list with no images.

Every document opens in its own page, styled for print. Your browser's print dialog does the rest, including **Save as PDF**.

= Three documents =

* **Product sheet** - one product per page: image, price, description, attributes, whatever you choose to show.
* **Catalog** - every product in a category, or in the whole shop, laid out in one to four columns.
* **Price list** - SKU, name, price and availability in a table, with the column headings repeated on every printed page.

= Why there is no PDF button =

Because your browser is better at it, and honest about it.

A plugin that builds PDFs on your server has to carry a PDF engine. A usable one is tens of megabytes, most of that fonts, and it still runs out of memory on a large catalog. Your browser already has an excellent one: it produces a real PDF with selectable text, the right font for every alphabet, and images at the printer's resolution, and it cannot time out.

So Printvane gives you the document, and **File > Print > Save as PDF** gives you the file.

What a browser cannot do is produce a file with nobody at the keyboard. If you need that, see the FAQ below.

= Prices are the prices your customer sees =

Printvane reads prices through WooCommerce's own price API in the context of whoever opened the document. That means sale prices, tax display settings, and any plugin of yours that changes prices for a role or a quantity are all reflected without configuring anything. A catalog-mode plugin that hides prices hides them here too.

= Large catalogs =

A document holds at most 200 products. Past that, Printvane paginates: you get "part 1 of 25" with a link to the next, and each part is its own print job. The last line of every document says how many products it holds, so you can always tell a document that ended from one that was cut short.

= Documentation and links =

* **Documentation**: [plogins.com/printvane/docs/](https://plogins.com/printvane/docs/)
* **Plugin page**: [plogins.com/printvane/](https://plogins.com/printvane/)
* **Bug reports and feature requests**: [github.com/wppoland/plogins-folio/issues](https://github.com/wppoland/plogins-folio/issues)

== Installation ==

1. Install and activate the plugin. WooCommerce must be active.
2. Go to **WooCommerce > Printvane** and choose what each document shows.
3. Print links appear on product pages and on shop and category pages. There is also a **Print** row action on the products list.

== Frequently Asked Questions ==

= Where do I get an actual PDF file? =

Open any document and use your browser's print dialog, then choose **Save as PDF** as the destination. Chrome, Firefox, Safari and Edge all offer it.

= Can I generate PDF files automatically, without opening a page? =

No. Building files on a schedule, exporting the whole catalog in the background, or attaching a sheet to an email needs a PDF engine running on your server, and this plugin deliberately does not carry one.

= Can I print only some products? =

Yes. Print from a category page and you get that category; attribute and price filters are not carried over, so a filtered shop page prints its whole category. Use the `[printvane_print]` (or `[folio_print]`) shortcode to place a link anywhere.

= What does the shortcode accept? =

`[printvane_print mode="sheet" id="123" text="Print this"]`. `mode` is `sheet`, `catalog` or `pricelist`. On a product page `id` can be left out.

= Can customers print, or only me? =

Anyone who can see the product can print it. The documents show public catalog data, and a product that is private, password protected or hidden from the catalog is refused unless you can already read it.

= My printed images look blurry. =

Printvane asks for WooCommerce's single-product image size and offers the browser the full `srcset`, so it can pick a higher resolution candidate. If your product images are small to begin with, there is nothing larger to print. Upload larger originals and regenerate thumbnails.

= Does it work with my page cache? =

The document is never cached. Prices can differ per customer, so caching one person's document and serving it to the next would show the wrong prices.

== Screenshots ==

1. A product sheet, ready to print.
2. A category catalog in two columns.
3. A price list with SKU, availability and price.
4. The settings screen: what each document shows.

== Changelog ==

= 1.0.3 =
* Renamed to Printvane, with the slug and text domain `printvane`. Settings, shortcode, hooks and the `?folio=` links are unchanged.

= 1.0.2 =
* A sale price prints struck through beside the new price, instead of repeating WooCommerce's screen-reader sentences on paper.
* FAQ no longer mentions a paid edition that does not exist, and no longer claims a filtered shop page prints only what the filter left.

= 1.0.1 =
* Display name drops the "Plogins " prefix; the slug, text domain and option keys are unchanged.

= 1.0.0 =
* First release: product sheets, category and shop catalogs, and price lists, all printable from the browser.
