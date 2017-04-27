<?php

/**
 * The plugin bootstrap file.
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/Kyborg2011
 * @since             1.0.0
 *
 * @wordpress-plugin
 * Plugin Name:       Product CSV Importer
 * Plugin URI:        https://github.com/Kyborg2011
 * Description:       Free simple products importer from CSV files. Working with Woocommerce.
 * Version:           1.0.0
 * Author:            Anton Babinin
 * Author URI:        https://github.com/Kyborg2011
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       product-csv-importer
 * Domain Path:       /languages
 */

/**
 * Load composer.
 */
$composer = dirname(__FILE__).'/vendor/autoload.php';
if (file_exists($composer)) {
    require_once $composer;
}

/*
 * If this file is called directly, abort.
 */
if (!defined('WPINC')) {
    die;
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-product-csv-importer-activator.php.
 */
function activate_product_csv_importer()
{
    require_once plugin_dir_path(__FILE__).'includes/class-product-csv-importer-activator.php';
    Product_Csv_Importer_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-product-csv-importer-deactivator.php.
 */
function deactivate_product_csv_importer()
{
    require_once plugin_dir_path(__FILE__).'includes/class-product-csv-importer-deactivator.php';
    Product_Csv_Importer_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_product_csv_importer');
register_deactivation_hook(__FILE__, 'deactivate_product_csv_importer');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__).'includes/class-product-csv-importer.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_product_csv_importer()
{
    $plugin = new Product_Csv_Importer();
    $plugin->run();
}
run_product_csv_importer();

/**
 * Custom utility methods start.
 */

/**
 * Getting WC_Product by SKU or 'null' if product SKU not fount.
 *
 * @since    1.0.0
 *
 * @param string $sku The product SKU (marking)
 *
 * @return WC_Product $product The product entity
 */
function pci_get_product_by_sku($sku)
{
    global $wpdb;
    $product = null;
    $sql_query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1";

    $product_id = $wpdb->get_var($wpdb->prepare($sql_query, $sku));
    if ($product_id) {
        $product = new WC_Product($product_id);
    }

    return $product;
}

/**
 * Updating WC product post.
 *
 * @since    1.0.0
 *
 * @return bool
 */
function pci_update_product_by_id($post_id, $data)
{
    if ($post_id) {
        wp_set_object_terms($post_id, $data['product_cat'], 'product_cat');
        wp_set_object_terms($post_id, Constants::DEFAULT_PRODUCT_TYPE, 'product_type');

        foreach ($data as $key => $val) {
            if ($key === '_sku') {
                $attachment_dir = wp_upload_dir();
                $new_filename = wp_unique_filename($attachment_dir['path'], $val.Constants::DEFAULT_ATTACHMENT_EXT);
                $attachment_fullpath = $attachment_dir['path'].'/'.$new_filename;
                $product_image_fullpath = get_home_path().Constants::DEFAULT_DIRNAME_WITH_ATTACHMENTS.$val.Constants::DEFAULT_ATTACHMENT_EXT;
                if (file_exists($product_image_fullpath)) {
                    if (copy($product_image_fullpath, $attachment_fullpath)) {
                        $attachment = [
                            'guid' => $attachment_fullpath,
                            'post_type' => 'attachment',
                            'post_title' => $attachment_fullpath,
                            'post_content' => '',
                            'post_parent' => $post_id,
                            'post_status' => 'publish',
                            'post_mime_type' => 'image/jpeg',
                            'post_author' => 2,
                        ];

                        // Attach the image to post
                        $attach_id = wp_insert_attachment($attachment, $attachment_fullpath, $post_id);
                        if ($attach_id) {
                            add_post_meta($post_id, '_thumbnail_id', $attach_id);
                        }
                    }
                }
            }
            update_post_meta($post_id, $key, $val);
        }

        if (isset($data['brand'])) {
            $term_taxonomy_ids = wp_set_object_terms($post_id, $data['brand'], Constants::DEFAULT_MANUFACTURER_ATTRIBUTE_NAME, true);
            $brand_attribute = [
                Constants::DEFAULT_MANUFACTURER_ATTRIBUTE_NAME => [
                    'name' => Constants::DEFAULT_MANUFACTURER_ATTRIBUTE_NAME,
                    'value' => $data['brand'],
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1,
                ],
            ];
            update_post_meta($post_id, '_product_attributes', $brand_attribute);
        }

        update_post_meta($post_id, '_visibility', 'visible');
        update_post_meta($post_id, '_stock_status', 'instock');
        update_post_meta($post_id, 'total_sales', '0');
        update_post_meta($post_id, '_downloadable', 'yes');
        update_post_meta($post_id, '_virtual', 'yes');
        update_post_meta($post_id, '_regular_price', '1');
        update_post_meta($post_id, '_sale_price', '1');
        update_post_meta($post_id, '_purchase_note', '');
        update_post_meta($post_id, '_featured', 'no');
        update_post_meta($post_id, '_weight', '');
        update_post_meta($post_id, '_length', '');
        update_post_meta($post_id, '_width', '');
        update_post_meta($post_id, '_height', '');
        update_post_meta($post_id, '_sale_price_dates_from', '');
        update_post_meta($post_id, '_sale_price_dates_to', '');
        update_post_meta($post_id, '_sold_individually', '');
        update_post_meta($post_id, '_manage_stock', 'no');
        update_post_meta($post_id, '_backorders', 'no');
        update_post_meta($post_id, '_stock', '');

        return true;
    }

    return false;
}

/**
 * Creating WC product post.
 *
 * @since    1.0.0
 *
 * @return int $post_id The product post id
 */
function pci_create_product($data)
{
    $post = [
        'post_author' => Constants::AUTHOR_USER_ID,
        'post_content' => (isset($data['post_content'])) ? $data['post_content'] : '',
        'post_status' => Constants::DEFAULT_POST_STATUS,
        'post_title' => (isset($data['post_title'])) ? $data['post_title'] : '',
        'post_type' => Constants::DEFAULT_POST_TYPE,
    ];

    // Create post
    $post_id = wp_insert_post($post);
    // Update all woocommerce meta fields of a new product post
    pci_update_product_by_id($post_id, $data);

    return $post_id;
}

/**
 * Creating MYSQL table for faster CSV import.
 *
 * @param string $fields Columns for creating MySQL table
 *
 * @since    1.0.0
 */
function pci_create_table($fields)
{
    // do NOT forget this global
    global $wpdb;

    // this if statement makes sure that the table doe not exist already
    if ($wpdb->get_var('show tables like ' . Constants::INTERMEDIATE_TABLE_NAME)
        !== Constants::INTERMEDIATE_TABLE_NAME) {

        $sql = 'CREATE TABLE ' . Constants::INTERMEDIATE_TABLE_NAME .
            ' (' . $fields . ');';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

/*
 * Custom utility methods end
 */
