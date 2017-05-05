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

use WP_Query;

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
        /* Set product category */
        foreach($data as $k => $v) {
            if (strpos($k, 'product_cat') !== false) {
                wp_set_object_terms($post_id, $v, 'product_cat');
            }
        }
        wp_set_object_terms($post_id, Constants::DEFAULT_PRODUCT_TYPE, 'product_type');

        $updating_post_data = array(
            'ID'           => $post_id,
        );
        if (isset($data['post_title'])) {
            $updating_post_data['post_title'] = $data['post_title'];
        }
        if (isset($data['post_content'])) {
            $updating_post_data['post_content'] = $data['post_content'];
        }
        wp_update_post( $updating_post_data );

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
 * Create MYSQL table for faster CSV import.
 *
 * @param string $fields Columns for creating MySQL table
 *
 * @since    1.0.0
 */
function pci_create_table($fields)
{
    // do NOT forget this global
    global $wpdb;

    // if the table is already exists in DB - remove it
    if ($wpdb->get_var("SHOW TABLES LIKE '".Constants::INTERMEDIATE_TABLE_NAME."'")
        === Constants::INTERMEDIATE_TABLE_NAME) {
        $wpdb->query('DROP TABLE '.Constants::INTERMEDIATE_TABLE_NAME);
    }

    $sql = 'CREATE TABLE '.Constants::INTERMEDIATE_TABLE_NAME.' ('.
        $fields.');';
    require_once ABSPATH.'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * Load data from .csv file to MySQL table.
 *
 * @param string $file_name Full absolute path to uploadded .csv file for importing
 *
 * @since    1.0.0
 */
function pci_load_csv_into_mysql($file_name)
{
    // do NOT forget this global
    global $wpdb;

    $table_name = Constants::INTERMEDIATE_TABLE_NAME;

    $fields_terminated_by = Product_Csv_Importer_Admin::$delimeter_symbol;
    $optionally_enclosed_by = Product_Csv_Importer_Admin::$enclosure_symbol;
    if ($optionally_enclosed_by === Product_Csv_Importer_Admin::$default_enclosure_symbol) {
        $optionally_enclosed_by = '"';
    }

    $query = "LOAD DATA LOCAL INFILE '$file_name'
     INTO TABLE $table_name
     FIELDS TERMINATED BY '$fields_terminated_by'
     ENCLOSED BY '$optionally_enclosed_by'
     LINES TERMINATED BY '\n'
     IGNORE 1 LINES";

    $wpdb->query($query);
}

/**
 * Action used for processing data from .csv file to working entities on WP + WC website.
 *
 * @since    1.0.0
 */
function pci_process_data_ajax_action()
{
    global $wpdb;

    $result = [];
    $count_updated_products = 0;
    $count_created_products = 0;
    $created_products_sku = array();
    $updated_products_sku = array();
    $table_name = Constants::INTERMEDIATE_TABLE_NAME;

    // Default limit value
    $limit = 50;
    // Default offset value
    $offset = 0;

    if (isset($_POST['limit']) && $_POST['limit']) {
        $limit = intval(strip_tags(trim($_POST['limit'])));
    }
    if (isset($_POST['offset']) && $_POST['offset']) {
        $offset = intval(strip_tags(trim($_POST['offset'])));
    }

    $querystr = "SELECT COUNT(*) FROM $table_name";
    $total_entries_number = $wpdb->get_var($querystr);

    $querystr = "SELECT * FROM $table_name LIMIT $limit OFFSET $offset";
    $importing_data = $wpdb->get_results($querystr, ARRAY_A);
    $number_of_current_entities = count($importing_data);
    $result['number'] = $number_of_current_entities;

    /* Processing some debug information, when $wpdb gots an error */
    if ($wpdb->last_error) {
        $result = [
            'error' => 'MySQL database select query error',
            'wpdb_last_error' => $wpdb->last_error,
            'count' => 0,
        ];
        wp_send_json($result);
        wp_die();
    }

    /* Processing error-response, when nothing not found in database */
    if (!$number_of_current_entities) {
        $result = [
            'error' => 'Entities not found',
            'count' => 0,
        ];
        wp_send_json($result);
        wp_die();
    }

    foreach ($importing_data as $data) {
        if (isset($data['_sku'])) {
            $product = pci_get_product_by_sku($data['_sku']);
            if ($product != null) {
                if (pci_update_product_by_id($product->id, $data)) {
                    ++$count_updated_products;
                    $updated_products_sku[] = $data['_sku'];
                }
            } else {
                pci_create_product($data);
                ++$count_created_products;
                $created_products_sku[] = $data['_sku'];
            }
        }
    }

    $result['count'] = $total_entries_number;
    $result['count_created_products'] = $count_created_products;
    $result['count_updated_products'] = $count_updated_products;
    $result['created_products_sku'] = $created_products_sku;
    $result['updated_products_sku'] = $updated_products_sku;

    wp_send_json($result);
    wp_die();
}
add_action('wp_ajax_pci_process_data_ajax_action', 'pci_process_data_ajax_action');

function pci_get_not_founded_products_action() {
    global $wpdb;

    $result = array();
    $sku_list = $_POST['sku'];

    $brandName = '';
    if (isset($_POST['brandName'])) {
        $brandName = strip_tags(trim($_POST['brandName']));
    }

        if ($brandName && count($sku_list)) {
            $args = array(
                'post_type'  => 'product',
                'posts_per_page'  => -1,
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $sku_list,
                        'compare' => 'NOT IN'
                    ),
                    array(
                        'key' => 'brand',
                        'value' => $brandName,
                    ),
                )
            );
            $the_query = new WP_Query( $args );

            if ( $the_query->have_posts() ) {
                while ( $the_query->have_posts() ) {
                    $the_query->the_post();
                    $theid = get_the_ID();
                    $sku = get_post_meta( $theid, '_sku', true );
                    if ($sku) $result[] = $sku;
                }
                wp_reset_postdata();
            }
        }

    wp_send_json($result);
    wp_die();
}
add_action('wp_ajax_pci_get_not_founded_products_action', 'pci_get_not_founded_products_action');

/*
 * Custom utility methods end
 */
