<?php

use \Exception as Exception;
use \SplFileObject as SplFileObject;
use Goodby\CSV\Import\Standard\Interpreter;
use Goodby\CSV\Import\Standard\Lexer;
use Goodby\CSV\Import\Standard\LexerConfig;

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
     * Default CSV delimeter.
     *
     * @since    1.0.0
     *
     * @var string Default CSV delimeter
     */
    public static $default_delimeter_symbol = ';';

    /**
     * Default CSV enclosure symbol.
     *
     * @since    1.0.0
     *
     * @var string Default CSV enclosure symbol
     */
    public static $default_enclosure_symbol = '&#34;';

    /**
     * Default No. of the row with header cells.
     *
     * @since    1.0.0
     *
     * @var string Default CSV enclosure symbol
     */
    public static $default_header_cells_row_number = 1;

    /**
     * Default CSV file charset.
     *
     * @since    1.0.0
     *
     * @var string Default CSV file charset
     */
    public static $default_charset = 'utf-8';

    /**
     * Charset options used to fill <option> in charset <select> field.
     *
     * @since    1.0.0
     *
     * @var array Charset options
     */
    public static $charset_options = ['utf-32', 'utf-16', 'utf-8', 'koi8-r', 'windows-1251', 'windows-1252'];

    /**
     * Names of all value fields in the form (eg. inputs and selects).
     *
     * @since    1.0.0
     */
    private $hidden_field_name = 'product_csv_importer_submit_hidden';
    private $delimeter_field_name = 'product_csv_importer_delimeter_symbol';
    private $enclosure_field_name = 'product_csv_importer_enclosure_symbol';
    private $charset_field_name = 'product_csv_importer_charset';
    private $file_field_name = 'product_csv_importer_file_import';
    private $header_cells_row_field_name = 'product_csv_importer_header_cells_row_number';

    public $updated_parameters = [];
    public $count_created_products = 0;
    public $count_updated_products = 0;

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

    public static function has_files_to_upload($id)
    {
        return (!empty($_FILES)) && isset($_FILES[$id]);
    }

    private function is_received_data_valid()
    {
        if (!self::has_files_to_upload($this->file_field_name) || !$_FILES[$this->file_field_name]['name']) {
            return false;
        }

        return true;
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
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__).'css/product-csv-importer-admin.css', [], $this->version, 'all');
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
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__).'js/product-csv-importer-admin.js', [
            'jquery',
        ], $this->version, false);
        wp_enqueue_script('jquery-plugin-numeric-input', plugin_dir_url(__FILE__).'js/numericInput.min.js', [
            'jquery',
        ], $this->version, false);
    }

    /**
     * Register an admin top-level menu page.
     *
     * @since    1.0.0
     */
    public function add_menu_pages()
    {
        add_menu_page(__('Products import', 'product-csv-importer'), __('Products import', 'product-csv-importer'), 'manage_options', 'import_page', [
            $this,
            'import_page_html',
        ], plugin_dir_url(__FILE__).'images/icon.png', 59);
    }

    public function import_page_html()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'product-csv-importer'));
        } ?><div class="product-csv-importer-admin-wrapper"><?php

        /* if form is already sended */
        if (isset($_POST['Submit'])) {
            $is_valid = $this->is_received_data_valid();
        }

        if (isset($_POST[$this->hidden_field_name]) &&
            $_POST[$this->hidden_field_name] == 'Y' && isset($is_valid) && $is_valid) {
            ?><h2><?= __('Products import result', 'product-csv-importer') ?></h2><?php

            $file = wp_upload_bits($_FILES[$this->file_field_name]['name'],
                null,
                @file_get_contents($_FILES[$this->file_field_name]['tmp_name']));

            if (false === $file['error'] && isset($file['file'])) {
                $is_valid = $this->parse_csv_file($file['file']);

                if ($is_valid) : ?>
                    <p>
                        <?= __('Created products: ', 'product-csv-importer') ?>
                        <?= $this->count_created_products ?>
                    </p>
                    <p>
                        <?= __('Updated products: ', 'product-csv-importer') ?>
                        <?= $this->count_updated_products ?>
                    </p>
                <?php
                endif;
            }

            if (isset($is_valid) && !$is_valid):
                ?>
                    <div class="error notif">
                    <p>
                      <strong>
                        <?php echo __('Form is not filled correctly. Perhaps you forgot to select the .csv file to import. Please, try again!', 'product-csv-importer'); ?>
                      </strong>
                    </p>
                    </div>
                <?php
            endif;
        } else {
            ?><h2><?= __('Products import', 'product-csv-importer') ?></h2>
            <img class="product_csv_importer_logo_large" src="<?= plugin_dir_url(__FILE__) ?>images/icon-large.png" />
            <?php if (isset($is_valid) && !$is_valid): ?>
                <div class="error notif">
                    <p>
                      <strong>
                        <?php echo __('Form is not filled correctly. Perhaps you forgot to select the .csv file to import. Please, try again!', 'product-csv-importer'); ?>
                      </strong>
                    </p>
                </div>
            <?php endif; ?>
            <form enctype="multipart/form-data" name="product_csv_importer_form" method="post" action="">
            <input type="hidden" name="<?php echo $this->hidden_field_name; ?>" value="Y">
            <table class="form-table">
              <tbody>
                <tr>
                  <th scope="row">
                    <label for="<?= $this->delimeter_field_name ?>">
                      <?php echo __('Delimeter', 'product-csv-importer'); ?>
                    </label>
                  </th>
                  <td>
                    <input class="regular-text" type="text" maxlength="1" pattern="[^0-9A-Za-z]" id="<?= $this->delimeter_field_name ?>" name="<?= $this->delimeter_field_name ?>" value="<?= self::$default_delimeter_symbol ?>" required />
                    <p class="description">
                      <?php echo __('Character separating one cell from another', 'product-csv-importer'); ?>
                    </p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="<?= $this->enclosure_field_name ?>">
                      <?php echo __('Enclosure', 'product-csv-importer'); ?>
                    </label>
                  </th>
                  <td>
                    <input class="regular-text" type="text" maxlength="1" pattern="[^0-9A-Za-z]" id="<?= $this->enclosure_field_name ?>" name="<?= $this->enclosure_field_name ?>" value="<?= self::$default_enclosure_symbol ?>" required />
                    <p class="description">
                      <?php echo __('Symbol in which the values of table cells are wrapped', 'product-csv-importer'); ?>
                    </p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="<?= $this->header_cells_row_field_name ?>">
                      <?php echo __('Row number with header cells', 'product-csv-importer'); ?>
                    </label>
                  </th>
                  <td>
                    <input class="regular-text" type="number" id="<?= $this->header_cells_row_field_name ?>" name="<?= $this->header_cells_row_field_name ?>" value="<?= self::$default_header_cells_row_number ?>" required />
                    <p class="description">
                      <?php echo __('The ordinal number of the line containing the names of the output parameters of the store, corresponding to each column of the .csv file', 'product-csv-importer'); ?>
                    </p>
                  </td>
                </tr>
                <tr>
                  <th scope="row">
                    <label for="<?= $this->charset_field_name ?>">
                      <?php echo __('File charset', 'product-csv-importer'); ?>
                    </label>
                  </th>
                  <td>
                    <select id="<?= $this->charset_field_name ?>" name="<?= $this->charset_field_name ?>">
                      <?php foreach (self::$charset_options as $charset): ?>
                          <option <?php echo ($charset === self::$default_charset) ? ' selected' : ''; ?>>
                            <?= $charset ?>
                          </option>
                      <?php endforeach; ?>
                    </select>
                <p class="description">
                  <?php echo __('If Microsoft Excel was used to create the .csv file, you must select the windows-1251 encoding. In most other cases (for example, LibreOffice Calc in Linux) the basic encoding is utf-8.', 'product-csv-importer'); ?>
                </p>
              </td>
            </tr>
            <tr>
            <td scope="row" colspan="2" style="padding-left: 0;">
              <div class="importer-file-field-wrap">
                <span>
                  <?php echo __('Importing CSV-file', 'product-csv-importer'); ?>
                </span>
                <label for="<?= $this->file_field_name ?>">
                  <?php echo __('Select', 'product-csv-importer'); ?>
                  <input class="regular-text" type="file" id="<?= $this->file_field_name ?>" name="<?= $this->file_field_name ?>" value="" />
                </label>
              </div>
            </td>
            </tr>
            <tr>
            <th>
            </th>
            <td>
              <input type="submit" name="Submit" class="button-primary" value="<?php echo __('Save', 'product-csv-importer'); ?>" />
            </td>
            </tr>
            </tbody>
            </table>
            </form>
            <script type="text/javascript">
              (function( $ ) {
                var headerCellsRowNumberFieldId = '<?= $this->header_cells_row_field_name ?>';
                var headerCellsRowDomEl = document.getElementById(headerCellsRowNumberFieldId);
                /* custom input file field start */
                $('.importer-file-field-wrap').each(function() {
                  var parent = this;
                  $(this).find('input').change(function() {
                    var filepath = this.value;
                    var m = filepath.match(/([^\/\\]+)$/);
                    var filename = m[1];
                    $(parent).find('span').html(filename);
                  }
                                              );
                }
                                                   );
                /* custom input file field end */
                /* numeric inputs initializing start */
                if (headerCellsRowDomEl) {
                  $(headerCellsRowDomEl).numericInput(
                    {
                      allowFloat: false,
                      allowNegative: false,
                      min: 1,
                      max: 9,
                    }
                  );
                }
                /* numeric inputs initializing end */
              }
              )( jQuery );
            </script>
        <?php 
        } ?></div>

        <?php

    }

    private function parse_csv_file($url)
    {
        $values = [];
        $config_flags = SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::READ_CSV;
        $header_cells_row = $_POST[$this->header_cells_row_field_name];
        $self = $this;
        $row_counter = 1;
        $enclosure_val_length = strlen($_POST[$this->enclosure_field_name]);
        $enclosure_symbol = ($enclosure_val_length > 1) ? substr($_POST[$this->enclosure_field_name], $enclosure_val_length - 1) : $_POST[$this->enclosure_field_name];
        $config = new LexerConfig();
        $config->setDelimiter($_POST[$this->delimeter_field_name]) // Разделитель
            ->setEnclosure($enclosure_symbol) // Контейнер
            ->setEscape('\\') // Управляющий символ
            ->setToCharset('UTF-8') // Кодировка на выходе
            ->setFromCharset($_POST[$this->charset_field_name]); // Кодировка файла-источника
        $config->setFlags($config_flags);
        $lexer = new Lexer($config);
        $interpreter = new Interpreter();
        $interpreter->addObserver(function (array $row) use (&$self, $header_cells_row, &$row_counter) {
            if ($row_counter == $header_cells_row) {
                foreach ($row as $cell) {
                    $self->updated_parameters[] = $cell;
                }
            } else {
                $data = [];
                foreach ($self->updated_parameters as $index => $cell_name) {
                    if (count($row) > $index) {
                        $data[$cell_name] = $row[$index];
                    }
                }
                if (isset($data['_sku'])) {
                    $product = pci_get_product_by_sku($data['_sku']);
                    if ($product != null) {
                        if (pci_update_product_by_id($product->id, $data)) {
                            ++$self->count_updated_products;
                        }
                    } else {
                        pci_create_product($data);
                        ++$self->count_created_products;
                    }
                }
            }
            ++$row_counter;
        });

        try {
            $lexer->parse($url, $interpreter);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}
