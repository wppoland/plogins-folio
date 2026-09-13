<?php
/**
 * The standalone printable document.
 *
 * This plugin does not bundle a PDF engine. A usable one is tens of megabytes,
 * most of it fonts, and that has no business in a plugin downloaded from
 * WordPress.org. Every browser prints to PDF and does it well: selectable text,
 * the right font for every script, images at the printer's resolution. So the
 * document is styled for print and the button below opens that dialog.
 *
 * Variables in scope, set by Folio\Service\PrintRoute:
 *
 * @var \Folio\Document\Document $folio_document
 * @var string                   $folio_body     Rendered document markup.
 * @var \Folio\Service\Settings  $folio_settings
 * @var string                   $folio_next     URL of the next part, or ''.
 *
 * @package Folio
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($folio_document->title); ?></title>
    <?php wp_print_styles('plogins-folio-print'); ?>
</head>
<body class="folio-document">

<div class="folio-toolbar no-print">
    <button type="button" class="folio-toolbar__print">
        <?php echo esc_html(\Folio\Service\Texts::get('print_now')); ?>
    </button>
    <?php if ('' !== $folio_next) : ?>
        <a class="folio-toolbar__next" href="<?php echo esc_url($folio_next); ?>">
            <?php
            echo esc_html(sprintf(
                /* translators: %d: the next part number. */
                __('Next part (%d)', 'plogins-folio'),
                ((int) $folio_document->metaValue('part', 1)) + 1,
            ));
            ?>
        </a>
    <?php endif; ?>
</div>

<?php
// Escaped here, at the point of output, against an allowlist of the tags the
// renderer can produce, rather than trusting that it escaped everything on the
// way in. The renderer does escape every value, but `folio/document` lets
// another plugin put blocks into the list first, and "it was safe when we built
// it" is exactly the reasoning that stops being true the moment a filter is
// added. Escaping late costs one call and cannot rot.
echo wp_kses($folio_body, \Folio\Render\HtmlRenderer::allowedHtml());
?>

<?php wp_print_scripts('plogins-folio-print'); ?>
</body>
</html>
