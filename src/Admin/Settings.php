<?php

declare(strict_types=1);

namespace Folio\Admin;

use Folio\Contract\HasHooks;
use Folio\Document\DocumentBuilder;
use Folio\Document\ProductData;
use Folio\Service\PrintRoute;
use Folio\Service\Settings as SettingsStore;
use Folio\Service\Texts;

defined('ABSPATH') || exit;

/**
 * The settings screen, under the WooCommerce menu.
 *
 * Saving is aligned to `manage_woocommerce` so a shop manager can use it, and
 * every field written here is read somewhere: the estate gates a setting that
 * is stored and never consulted, because a switch that changes nothing is worse
 * than no switch.
 */
final class Settings implements HasHooks
{
    private const PAGE = 'folio-settings';

    public function __construct(
        private readonly SettingsStore $store,
        private readonly ProductData $data,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenuPage(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Folio Settings', 'plogins-folio'),
            __('Folio', 'plogins-folio'),
            'manage_woocommerce',
            self::PAGE,
            [$this, 'renderPage'],
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            self::PAGE,
            SettingsStore::OPTION,
            ['type' => 'array', 'sanitize_callback' => [$this, 'sanitize']],
        );

        add_filter(
            'option_page_capability_' . self::PAGE,
            static fn (): string => 'manage_woocommerce',
        );
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $known = array_keys($this->data->fields());

        $clean = [
            'button_on_product'  => ! empty($input['button_on_product']),
            'button_on_archive'  => ! empty($input['button_on_archive']),
            'show_logo'          => ! empty($input['show_logo']),
            'show_shop'          => ! empty($input['show_shop']),
            'show_date'          => ! empty($input['show_date']),
            'show_page_numbers'  => ! empty($input['show_page_numbers']),
            'logo_id'            => absint($input['logo_id'] ?? 0),
            'catalog_columns'    => max(1, min(4, absint($input['catalog_columns'] ?? 2))),
        ];

        foreach (['sheet_fields', 'catalog_fields', 'pricelist_fields'] as $key) {
            $value        = is_array($input[$key] ?? null) ? $input[$key] : [];
            $clean[$key]  = array_values(array_intersect($known, array_map('sanitize_key', $value)));
        }

        // Text overrides are stored as typed. An empty one is stored as empty,
        // which means "use the translated default", so clearing a field gets
        // the merchant back to a string their language pack can reach.
        $texts = [];

        foreach (array_keys(Texts::defaults()) as $key) {
            $raw          = (string) ($input['texts'][$key] ?? '');
            $texts[$key]  = 'footer_note' === $key
                ? sanitize_textarea_field($raw)
                : sanitize_text_field($raw);
        }

        $clean['texts'] = $texts;

        return $clean;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = $this->store->all();
        $fields   = $this->data->fields();
        $option   = SettingsStore::OPTION;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Folio', 'plogins-folio'); ?></h1>
            <p class="description">
                <?php echo esc_html__('Print a product sheet, a category catalog or a price list. Use your browser\'s print dialog to save any of them as a PDF.', 'plogins-folio'); ?>
            </p>

            <p>
                <a class="button" target="_blank" rel="noopener"
                   href="<?php echo esc_url(PrintRoute::url(DocumentBuilder::CATALOG)); ?>">
                    <?php echo esc_html__('Preview the whole-shop catalog', 'plogins-folio'); ?>
                </a>
                <a class="button" target="_blank" rel="noopener"
                   href="<?php echo esc_url(PrintRoute::url(DocumentBuilder::PRICELIST)); ?>">
                    <?php echo esc_html__('Preview the price list', 'plogins-folio'); ?>
                </a>
            </p>

            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>

                <h2><?php echo esc_html__('Where the links appear', 'plogins-folio'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $this->checkbox($option, 'button_on_product', __('On the product page', 'plogins-folio'), ! empty($settings['button_on_product']));
                    $this->checkbox($option, 'button_on_archive', __('On shop and category pages', 'plogins-folio'), ! empty($settings['button_on_archive']));
                    ?>
                </table>

                <h2><?php echo esc_html__('What each document shows', 'plogins-folio'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $this->fieldChecklist($option, 'sheet_fields', __('Product sheet', 'plogins-folio'), $fields, (array) $settings['sheet_fields']);
                    $this->fieldChecklist($option, 'catalog_fields', __('Catalog', 'plogins-folio'), $fields, (array) $settings['catalog_fields']);
                    $this->fieldChecklist($option, 'pricelist_fields', __('Price list', 'plogins-folio'), $fields, (array) $settings['pricelist_fields']);
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="folio-columns"><?php echo esc_html__('Catalog columns', 'plogins-folio'); ?></label>
                        </th>
                        <td>
                            <input type="number" min="1" max="4" id="folio-columns"
                                   name="<?php echo esc_attr($option); ?>[catalog_columns]"
                                   value="<?php echo esc_attr((string) $settings['catalog_columns']); ?>" class="small-text">
                            <p class="description"><?php echo esc_html__('Columns are applied when printing, not on screen.', 'plogins-folio'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Header and footer', 'plogins-folio'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php
                    $this->checkbox($option, 'show_logo', __('Show a logo', 'plogins-folio'), ! empty($settings['show_logo']));
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="folio-logo"><?php echo esc_html__('Logo attachment ID', 'plogins-folio'); ?></label>
                        </th>
                        <td>
                            <input type="number" min="0" id="folio-logo"
                                   name="<?php echo esc_attr($option); ?>[logo_id]"
                                   value="<?php echo esc_attr((string) $settings['logo_id']); ?>" class="small-text">
                            <p class="description"><?php echo esc_html__('The media library ID of the image to print in the header. Leave at 0 for none.', 'plogins-folio'); ?></p>
                        </td>
                    </tr>
                    <?php
                    $this->checkbox($option, 'show_shop', __('Show the shop name', 'plogins-folio'), ! empty($settings['show_shop']));
                    $this->checkbox($option, 'show_date', __('Show the date the document was printed', 'plogins-folio'), ! empty($settings['show_date']));
                    $this->checkbox($option, 'show_page_numbers', __('Show how many products the document holds', 'plogins-folio'), ! empty($settings['show_page_numbers']));
                    ?>
                    <tr>
                        <th scope="row">
                            <label for="folio-footer-note"><?php echo esc_html__('Footer note', 'plogins-folio'); ?></label>
                        </th>
                        <td>
                            <textarea id="folio-footer-note" rows="3" class="large-text"
                                      name="<?php echo esc_attr($option); ?>[texts][footer_note]"><?php
                                echo esc_textarea((string) ($settings['texts']['footer_note'] ?? ''));
                            ?></textarea>
                            <p class="description"><?php echo esc_html__('Printed at the end of every document. Prices valid until, terms, contact details.', 'plogins-folio'); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php echo esc_html__('Wording', 'plogins-folio'); ?></h2>
                <p class="description">
                    <?php echo esc_html__('Leave a field empty to use the translated default for your site language.', 'plogins-folio'); ?>
                </p>
                <table class="form-table" role="presentation">
                    <?php
                    foreach (['button_sheet', 'button_catalog', 'button_pricelist', 'doc_sheet_title', 'doc_catalog_title', 'doc_pricelist_title'] as $key) {
                        $this->textField($option, $key, $settings['texts'][$key] ?? '');
                    }
                    ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function checkbox(string $option, string $key, string $label, bool $checked): void
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label>
                    <input type="checkbox" value="1"
                           name="<?php echo esc_attr($option . '[' . $key . ']'); ?>"
                        <?php checked($checked); ?>>
                    <?php echo esc_html__('Enabled', 'plogins-folio'); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * @param array<string, string> $fields
     * @param array<int, string>    $enabled
     */
    private function fieldChecklist(string $option, string $key, string $label, array $fields, array $enabled): void
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><?php echo esc_html($label); ?></legend>
                    <?php foreach ($fields as $fieldKey => $fieldLabel) : ?>
                        <label style="display:inline-block;min-width:11em;">
                            <input type="checkbox" value="<?php echo esc_attr($fieldKey); ?>"
                                   name="<?php echo esc_attr($option . '[' . $key . '][]'); ?>"
                                <?php checked(in_array($fieldKey, $enabled, true)); ?>>
                            <?php echo esc_html($fieldLabel); ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            </td>
        </tr>
        <?php
    }

    private function textField(string $option, string $key, string $value): void
    {
        $defaults = Texts::defaults();
        ?>
        <tr>
            <th scope="row">
                <label for="folio-text-<?php echo esc_attr($key); ?>">
                    <?php echo esc_html($defaults[$key] ?? $key); ?>
                </label>
            </th>
            <td>
                <input type="text" class="regular-text" id="folio-text-<?php echo esc_attr($key); ?>"
                       name="<?php echo esc_attr($option . '[texts][' . $key . ']'); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       placeholder="<?php echo esc_attr($defaults[$key] ?? ''); ?>">
            </td>
        </tr>
        <?php
    }
}
