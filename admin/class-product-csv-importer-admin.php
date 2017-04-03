<?php

use \SplFileObject as SplFileObject;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\LexerConfig;

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/Kyborg2011
 * @since      1.0.0
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @author     Anton Babinin <wkyborgw@gmail.com>
 */
class Product_Csv_Importer_Admin
{
    /**
     * Default CSV delimeter
     *
     * @since    1.0.0
     *
     * @var string Default CSV delimeter
     */
    public static $default_delimeter_symbol = ';';

    /**
     * Default CSV enclosure symbol
     *
     * @since    1.0.0
     *
     * @var string Default CSV enclosure symbol
     */
    public static $default_enclosure_symbol = "&#34;";

    /**
     * Default CSV file charset
     *
     * @since    1.0.0
     *
     * @var string Default CSV file charset
     */
    public static $default_charset = 'utf-8';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The ID of this plugin
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     *
     * @var string The current version of this plugin
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     *
     * @param string $plugin_name The name of this plugin
     * @param string $version     The version of this plugin
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Getting WC_Product by SKU or 'null' if product SKU not fount
     *
     * @since    1.0.0
     *
     * @param  string     $sku     The product SKU (marking)
     * @return WC_Product $product The product entity
     */
    public static function get_product_by_sku( $sku )
    {
        global $wpdb;
        $product = null;
        $sql_query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1";

        $product_id = $wpdb->get_var( $wpdb->prepare(
            $sql_query, $sku ) );
        if ( $product_id )
            $product = new WC_Product( $product_id );

        return $product;
    }

    public static function has_files_to_upload($id)
    {
        return (!empty($_FILES)) && isset($_FILES[ $id ]);
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /*
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Product_Csv_Importer_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Product_Csv_Importer_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__).'css/product-csv-importer-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /*
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Product_Csv_Importer_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Product_Csv_Importer_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__).'js/product-csv-importer-admin.js', array('jquery'), $this->version, false);
    }

    /**
     * Register an admin top-level menu page.
     *
     * @since    1.0.0
     */
    public function add_menu_pages()
    {
        add_menu_page(
		        __('Products Import', 'product-csv-importer'),
		        __('Products Import', 'product-csv-importer'),
		        'manage_options',
		        'import_page',
		        array($this, 'import_page_html'),
		        plugin_dir_url(__FILE__).'images/icon.png',
		        59
    		);
    }

    public function import_page_html()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'product-csv-importer'));
        }

        $charsets = array(
            'utf-32',
            'utf-16',
            'utf-8',
            'koi8-r',
            'windows-1251',
            'windows-1252',
        );
        $hidden_field_name = 'product_csv_importer_submit_hidden';
        $delimeter_field_name = 'product_csv_importer_delimeter_symbol';
        $enclosure_field_name = 'product_csv_importer_enclosure_symbol';
        $charset_field_name = 'product_csv_importer_charset';
        $file_field_name = 'product_csv_importer_file_import';

        ?><div class="product-csv-importer-admin-wrapper"><?php

        if (isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y') {
            if (Product_Csv_Importer_Admin::has_files_to_upload($file_field_name)) {
                $file = wp_upload_bits($_FILES[$file_field_name]['name'], null,
                        @file_get_contents($_FILES[$file_field_name]['tmp_name']));

                if (false === $file['error'] && isset($file['file'])) {
                    $this->parse_csv_file($file['file']);
                } else {
                    echo '<p>Ошибка: '.$file['error'].'</tr>';
                }
            } ?>
	<div class="updated"><p><strong><?php _e('Загрузка товаров начата...'); ?></strong></tr></div>
	<?php

        }

        ?><h2><?=__('Products Import', 'product-csv-importer')?></h2>

        <img class="product_csv_importer_logo_large" src="<?=plugin_dir_url(__FILE__)?>images/icon-large.png" />

			<form enctype="multipart/form-data" name="product_csv_importer_form" method="post" action="">
					<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
          <table class="form-table">
              <tbody>
      <tr>
					<th scope="row"><label for="<?=$delimeter_field_name?>">
							<?php echo __('Delimeter', 'product-csv-importer') ?>
					</label></th>
					<td><input class="regular-text" type="text" id="<?=$delimeter_field_name?>" name="<?=$delimeter_field_name?>" value="<?=self::$default_delimeter_symbol?>" /></td>
			</tr>

      <tr>
					<th scope="row"><label for="<?=$enclosure_field_name?>">
							<?php echo __('Enclosure', 'product-csv-importer') ?>
					</label></th>
					<td><input class="regular-text" type="text" id="<?=$enclosure_field_name?>" name="<?=$enclosure_field_name?>" value="<?=self::$default_enclosure_symbol?>" /></td>
			</tr>

      <tr>
					<th scope="row"><label for="<?=$charset_field_name?>">
							<?php echo __('File charset', 'product-csv-importer') ?>
					</label></th>
					<td><select id="<?=$charset_field_name?>" name="<?=$charset_field_name?>">
              <?php foreach ($charsets as $charset) : ?>
                  <option<?php echo ($charset === Product_Csv_Importer_Admin::$default_charset) ? ' selected' : '' ?>>
                      <?=$charset?>
                  </option>
              <?php endforeach; ?>
          </select></td>
			</tr>

			<tr>
					<td scope="row" colspan="2" style="padding-left: 0;"><div class="importer-file-field-wrap">
            <span><?php echo __('Importing CSV-file', 'product-csv-importer') ?></span>
    <label for="<?=$file_field_name?>">
        <?php echo __('Select', 'product-csv-importer') ?><input class="regular-text" type="file" id="<?=$file_field_name?>" name="<?=$file_field_name?>" value="" />
    </label></div></td>
			</tr>

			<tr><th></th><td>
          <input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save') ?>" /></td>
			</tr>

    </tbody>
  </table>

			</form>
      <script type="text/javascript">
        (function( $ ) {
            $('.importer-file-field-wrap').each(function() {
              var parent = this;
              $(this).find('input').change(function() {
                var filepath = this.value;
                var m = filepath.match(/([^\/\\]+)$/);
                var filename = m[1];
                $(parent).find('span').html(filename);
              });
           });
        })( jQuery );
      </script>
	</div>

	<?php
    }

    private function parse_csv_file($url)
    {
        $values = array();
        $config_flags = SplFileObject::READ_AHEAD |
            SplFileObject::SKIP_EMPTY |
            SplFileObject::READ_CSV;

        $config = new LexerConfig();
        $config
            ->setDelimiter(";")           // Разделитель
            ->setEnclosure("\"")          // Контейнер
            ->setEscape("\\")             // Управляющий символ
            ->setToCharset('UTF-8')       // Кодировка на выходе
            ->setFromCharset('UTF-8');    // Кодировка файла-источника
        $config->setFlags($config_flags);

        $lexer = new Lexer($config);
        $interpreter = new Interpreter();
        $interpreter->addObserver(function(array $row) use (&$values) {
            if (count($row) > 4) {
                $sku = $row[4];
                $product = Product_Csv_Importer_Admin::get_product_by_sku($sku);
                ?><p>Артикул: <?=$sku?><br />Найденный товар: <?php echo ($product != null) ? $product->id : 'нет'; ?></tr><?php
            }
        });

        $lexer->parse($url, $interpreter);

        return;
    }
}
