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
  * Load composer
  */
 $composer = dirname(__FILE__) . '/vendor/autoload.php';
 if ( file_exists($composer) ) {
     require_once $composer;
 }

/**
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
