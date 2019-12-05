<?php defined( 'ABSPATH' ) OR exit;
/*
Plugin Name: Yml for Yandex Market
Description: Подключите свой магазин к Яндекс Маркету и выгружайте товары, получая новых клиентов!
Tags: yml, yandex, market, export, woocommerce
Author: Maxim Glazunov
Author URI: https://icopydoc.ru
License: GPLv2
Version: 2.3.3
Text Domain: yml-for-yandex-market
Domain Path: /languages/
WC requires at least: 3.0.0
WC tested up to: 3.8.0
*/
/*  Copyright YEAR  PLUGIN_AUTHOR_NAME  (email : djdiplomat@yandex.ru)
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
require_once plugin_dir_path(__FILE__).'/functions.php'; // Подключаем файл функций
require_once plugin_dir_path(__FILE__).'/offer.php';
register_activation_hook(__FILE__, array('YmlforYandexMarket', 'on_activation'));
register_deactivation_hook(__FILE__, array('YmlforYandexMarket', 'on_deactivation'));
register_uninstall_hook(__FILE__, array('YmlforYandexMarket', 'on_uninstall'));
add_action('plugins_loaded', array('YmlforYandexMarket', 'init'));
add_action('plugins_loaded', 'yfym_load_plugin_textdomain'); // load translation
function yfym_load_plugin_textdomain() {
 load_plugin_textdomain('yfym', false, dirname(plugin_basename(__FILE__)).'/languages/');
}
class YmlforYandexMarket {
 protected static $instance;
 public static function init() {
	is_null( self::$instance ) AND self::$instance = new self;
	return self::$instance;
 }
	
 public function __construct() {
	// yfym_DIR contains /home/p135/www/site.ru/wp-content/plugins/myplagin/
	define('yfym_DIR', plugin_dir_path(__FILE__)); 
	// yfym_URL contains http://site.ru/wp-content/plugins/myplagin/
	define('yfym_URL', plugin_dir_url(__FILE__));
	// yfym_UPLOAD_DIR contains /home/p256/www/site.ru/wp-content/uploads
	$upload_dir = (object)wp_get_upload_dir();
	define('yfym_UPLOAD_DIR', $upload_dir->basedir);
	// yfym_UPLOAD_DIR contains /home/p256/www/site.ru/wp-content/uploads/yfym
	$name_dir = $upload_dir->basedir."/yfym"; 
	define('yfym_NAME_DIR', $name_dir);
	$yfym_keeplogs = yfym_optionGET('yfym_keeplogs');
	define('yfym_KEEPLOGS', $yfym_keeplogs);
	define('yfym_VER', '2.3.3');	

	add_action('admin_menu', array($this, 'add_admin_menu' ));
	add_filter('upload_mimes', array($this, 'yfym_add_mime_types'));
	
	add_filter('cron_schedules', array($this, 'cron_add_seventy_sec'));
	add_filter('cron_schedules', array($this, 'cron_add_six_hours'));
	 
	add_action('yfym_cron_sborki', array($this, 'yfym_do_this_seventy_sec'));	 
	add_action('yfym_cron_period', array($this, 'yfym_do_this_event'));
		
	// индивидуальные опции доставки товара
	add_action('add_meta_boxes', array($this, 'yfym_add_custom_box'));
	add_action('save_post', array($this, 'yfym_save_post_product_function'), 50, 3);
	// пришлось юзать save_post вместо save_post_product ибо wc блочит обновы
	
	add_action('admin_notices', array($this, 'yfym_admin_notices_function'));

	/* Регаем стили только для страницы настроек плагина	*/
	add_action('admin_init', function() {
		wp_register_style('yfym-admin-css', plugins_url('css/yfym.css', __FILE__));
	}, 9999);
 }

 public static function yfym_admin_css_func() {
	/* Ставим css-файл в очередь на вывод */
	wp_enqueue_style('yfym-admin-css');
 } 

 public static function yfym_admin_head_css_func() {
	/* печатаем css в шапке админки */
	print '<style>/* Yml for Yandex Market */
		.icp_img1 {background-image: url('. yfym_URL .'/img/sl1.jpg);}
		.icp_img2 {background-image: url('. yfym_URL .'/img/sl2.jpg);}
		.icp_img3 {background-image: url('. yfym_URL .'/img/sl3.jpg);}
	</style>';
 }  
 
 // Срабатывает при активации плагина (вызывается единожды)
 public static function on_activation() {
	$upload_dir = (object)wp_get_upload_dir();
	$name_dir = $upload_dir->basedir."/yfym";
	if (!mkdir($name_dir)) {
		return false;
	}
	if (is_multisite()) {
		// Устанавливаем опции по умолчанию (будут храниться в таблице настроек WP)
		add_blog_option(get_current_blog_id(), 'yfym_version', '2.3.3');
		add_blog_option(get_current_blog_id(), 'yfym_status_cron', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_step_export', '500');
		add_blog_option(get_current_blog_id(), 'yfym_status_sborki', '-1'); // статус сборки файла
		add_blog_option(get_current_blog_id(), 'yfym_date_sborki', 'unknown'); // дата последней сборки
		add_blog_option(get_current_blog_id(), 'yfym_type_sborki', 'yml'); // тип собираемого файла yml или xls
		add_blog_option(get_current_blog_id(), 'yfym_file_url', ''); // урл до файла
		add_blog_option(get_current_blog_id(), 'yfym_file_file', ''); // путь до файла
		add_blog_option(get_current_blog_id(), 'yfym_ufup', '0');
		add_blog_option(get_current_blog_id(), 'yfym_keeplogs', '0');
		add_blog_option(get_current_blog_id(), 'yfym_magazin_type', 'woocommerce'); // тип плагина магазина 
		add_blog_option(get_current_blog_id(), 'yfym_vendor', 'none'); // тип плагина магазина
		add_blog_option(get_current_blog_id(), 'yfym_whot_export', 'all'); // что выгружать (все или там где галка)
		add_blog_option(get_current_blog_id(), 'yfym_skip_missing_products', '0');
		add_blog_option(get_current_blog_id(), 'yfym_date_save_set', 'unknown'); // дата сохранения настроек
		add_blog_option(get_current_blog_id(), 'yfym_separator_type', 'type1'); 
				
		$blog_title = get_bloginfo('name');
		add_blog_option(get_current_blog_id(), 'yfym_shop_name', $blog_title);
		add_blog_option(get_current_blog_id(), 'yfym_company_name', $blog_title);
		add_blog_option(get_current_blog_id(), 'yfym_main_product', 'other');		
		add_blog_option(get_current_blog_id(), 'yfym_adult', 'no');
		add_blog_option(get_current_blog_id(), 'yfym_desc', 'full');
		add_blog_option(get_current_blog_id(), 'yfym_price_from', 'no'); // разрешить "цена от"
		add_blog_option(get_current_blog_id(), 'yfym_oldprice', 'no');
		add_blog_option(get_current_blog_id(), 'yfym_params_arr', '');
		add_blog_option(get_current_blog_id(), 'yfym_add_in_name_arr', '');
		add_blog_option(get_current_blog_id(), 'yfym_no_group_id_arr', '');
		add_blog_option(get_current_blog_id(), 'yfym_product_tag_arr', ''); // id меток таксономии product_tag
		add_blog_option(get_current_blog_id(), 'yfym_store', 'false');
		add_blog_option(get_current_blog_id(), 'yfym_delivery_options', '0');
		add_blog_option(get_current_blog_id(), 'yfym_delivery', 'false');
		add_blog_option(get_current_blog_id(), 'yfym_delivery_cost', '0');
		add_blog_option(get_current_blog_id(), 'yfym_delivery_days', '32');
		add_blog_option(get_current_blog_id(), 'yfym_order_before', '');		
		add_blog_option(get_current_blog_id(), 'yfym_sales_notes_cat', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_sales_notes', '');
		add_blog_option(get_current_blog_id(), 'yfym_model', 'none'); // атрибут model магазина			
		add_blog_option(get_current_blog_id(), 'yfym_pickup', 'true');		
		add_blog_option(get_current_blog_id(), 'yfym_barcode', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_enable_auto_discount', '');	
		add_blog_option(get_current_blog_id(), 'yfym_expiry', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_downloadable', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_age', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_country_of_origin', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_manufacturer_warranty', 'off');
		add_blog_option(get_current_blog_id(), 'yfym_errors', '');
		add_blog_option(get_current_blog_id(), 'yfym_enable_auto_discounts', '');		
		add_blog_option(get_current_blog_id(), 'yfym_skip_backorders_products', '0');
		add_blog_option(get_current_blog_id(), 'yfym_no_default_png_products', '0');
		add_blog_option(get_current_blog_id(), 'yfym_skip_products_without_pic', '0');		
		
	} else {
		// Устанавливаем опции по умолчанию (будут храниться в таблице настроек WP)
		add_option('yfym_version', '2.3.3');
		add_option('yfym_status_cron', 'off');
		add_option('yfym_step_export', '500');
		add_option('yfym_status_sborki', '-1'); // статус сборки файла
		add_option('yfym_date_sborki', 'unknown'); // дата последней сборки		
		add_option('yfym_type_sborki', 'yml'); // тип собираемого файла yml или xls
		add_option('yfym_file_url', ''); // урл до файла
		add_option('yfym_file_file', ''); // путь до файла
		add_option('yfym_ufup', '0');
		add_option('yfym_keeplogs', '0');
		add_option('yfym_magazin_type', 'woocommerce'); // тип плагина магазина 
		add_option('yfym_vendor', 'none'); // тип плагина магазина
		add_option('yfym_whot_export', 'all'); // что выгружать (все или там где галка)
		add_option('yfym_skip_missing_products', '0');
		add_option('yfym_date_save_set', 'unknown'); // дата сохранения настроек		
		add_option('yfym_separator_type', 'type1'); 
		
		$blog_title = get_bloginfo('name');
		add_option('yfym_shop_name', $blog_title);
		add_option('yfym_company_name', $blog_title);
		add_option('yfym_main_product', 'other');		
		add_option('yfym_adult', 'no');
		add_option('yfym_desc', 'full');
		add_option('yfym_price_from', 'no'); // разрешить "цена от"
		add_option('yfym_oldprice', 'no');
		add_option('yfym_params_arr', '');
		add_option('yfym_add_in_name_arr', '');
		add_option('yfym_no_group_id_arr', '');
		add_option('yfym_product_tag_arr', ''); // id меток таксономии product_tag
		add_option('yfym_store', 'false');
		add_option('yfym_delivery', 'false');
		add_option('yfym_delivery_options', '0');
		add_option('yfym_delivery_cost', '0');
		add_option('yfym_delivery_days', '32');
		add_option('yfym_order_before', '');
		add_option('yfym_sales_notes_cat', 'off');
		add_option('yfym_sales_notes', '');
		add_option('yfym_model', 'none'); // атрибут model магазина
		add_option('yfym_pickup', 'true');
		add_option('yfym_barcode', 'off');
		add_option('yfym_enable_auto_discount', '');
		add_option('yfym_expiry', 'off');
		add_option('yfym_downloadable', 'off');
		add_option('yfym_age', 'off');	
		add_option('yfym_country_of_origin', 'off');
		add_option('yfym_manufacturer_warranty', 'off');
		add_option('yfym_errors', '');
		add_option('yfym_enable_auto_discounts', '');
		add_option('yfym_skip_backorders_products', '0');
		add_option('yfym_no_default_png_products', '0');	
		add_option('yfym_skip_products_without_pic', '0');		
	}
 }
 
 // Срабатывает при отключении плагина (вызывается единожды)
 public static function on_deactivation() {
	wp_clear_scheduled_hook('yfym_cron_period');
	wp_clear_scheduled_hook('yfym_cron_sborki');

	deactivate_plugins('yml-for-yandex-market-pro/yml-for-yandex-market-pro.php');
	deactivate_plugins('yml-for-yandex-market-promos-export/yml-for-yandex-market-promos-export.php');
 } 
 
 // Срабатывает при удалении плагина (вызывается единожды)
 public static function on_uninstall() {
	if (is_multisite()) {		
		delete_blog_option(get_current_blog_id(), 'yfym_shop_name');
		delete_blog_option(get_current_blog_id(), 'yfym_company_name');
		delete_blog_option(get_current_blog_id(), 'yfym_main_product');		
		delete_blog_option(get_current_blog_id(), 'yfym_version');
		delete_blog_option(get_current_blog_id(), 'yfym_status_cron');
		delete_blog_option(get_current_blog_id(), 'yfym_whot_export');
		delete_blog_option(get_current_blog_id(), 'yfym_skip_missing_products');
		delete_blog_option(get_current_blog_id(), 'yfym_date_save_set');
		delete_blog_option(get_current_blog_id(), 'yfym_separator_type'); 
		delete_blog_option(get_current_blog_id(), 'yfym_status_sborki');
		delete_blog_option(get_current_blog_id(), 'yfym_date_sborki');
		delete_blog_option(get_current_blog_id(), 'yfym_type_sborki');
		delete_blog_option(get_current_blog_id(), 'yfym_vendor');
		delete_blog_option(get_current_blog_id(), 'yfym_model');
		delete_blog_option(get_current_blog_id(), 'yfym_params_arr');
		delete_blog_option(get_current_blog_id(), 'yfym_add_in_name_arr');
		delete_blog_option(get_current_blog_id(), 'yfym_no_group_id_arr');
		delete_blog_option(get_current_blog_id(), 'yfym_product_tag_arr');
		delete_blog_option(get_current_blog_id(), 'yfym_file_url');
		delete_blog_option(get_current_blog_id(), 'yfym_file_file');
		delete_blog_option(get_current_blog_id(), 'yfym_ufup');
		delete_blog_option(get_current_blog_id(), 'yfym_keeplogs');
		delete_blog_option(get_current_blog_id(), 'yfym_magazin_type');
		delete_blog_option(get_current_blog_id(), 'yfym_pickup');
		delete_blog_option(get_current_blog_id(), 'yfym_store');
		delete_blog_option(get_current_blog_id(), 'yfym_delivery');
		delete_blog_option(get_current_blog_id(), 'yfym_delivery_cost');
		delete_blog_option(get_current_blog_id(), 'yfym_delivery_days');
		delete_blog_option(get_current_blog_id(), 'yfym_sales_notes_cat');
		delete_blog_option(get_current_blog_id(), 'yfym_sales_notes');
		delete_blog_option(get_current_blog_id(), 'yfym_price_from');	
		delete_blog_option(get_current_blog_id(), 'yfym_desc');
		delete_blog_option(get_current_blog_id(), 'yfym_barcode');
		delete_blog_option(get_current_blog_id(), 'yfym_enable_auto_discount');
		delete_blog_option(get_current_blog_id(), 'yfym_expiry');
		delete_blog_option(get_current_blog_id(), 'yfym_downloadable');
		delete_blog_option(get_current_blog_id(), 'yfym_age');
		delete_blog_option(get_current_blog_id(), 'yfym_country_of_origin');
		delete_blog_option(get_current_blog_id(), 'yfym_manufacturer_warranty');
		delete_blog_option(get_current_blog_id(), 'yfym_adult');
		delete_blog_option(get_current_blog_id(), 'yfym_oldprice');
		delete_blog_option(get_current_blog_id(), 'yfym_step_export');
		delete_blog_option(get_current_blog_id(), 'yfym_errors');
		delete_blog_option(get_current_blog_id(), 'yfym_enable_auto_discounts');
		delete_blog_option(get_current_blog_id(), 'yfym_skip_backorders_products');
		delete_blog_option(get_current_blog_id(), 'yfym_no_default_png_products');
		delete_blog_option(get_current_blog_id(), 'yfym_skip_products_without_pic');		
	} else {
		delete_option('yfym_shop_name');
		delete_option('yfym_company_name');
		delete_option('yfym_main_product');			
		delete_option('yfym_version');
		delete_option('yfym_status_cron');
		delete_option('yfym_whot_export');
		delete_option('yfym_skip_missing_products');
		delete_option('yfym_date_save_set');
		delete_option('yfym_separator_type');
		delete_option('yfym_status_sborki');
		delete_option('yfym_date_sborki');
		delete_option('yfym_type_sborki');
		delete_option('yfym_vendor');
		delete_option('yfym_model');
		delete_option('yfym_params_arr');
		delete_option('yfym_add_in_name_arr');
		delete_option('yfym_no_group_id_arr');
		delete_option('yfym_product_tag_arr');
		delete_option('yfym_file_url');
		delete_option('yfym_file_file');
		delete_option('yfym_ufup');
		delete_option('yfym_keeplogs');
		delete_option('yfym_magazin_type');
		delete_option('yfym_pickup');
		delete_option('yfym_store');
		delete_option('yfym_delivery');
		delete_option('yfym_delivery_cost');
		delete_option('yfym_delivery_days');
		delete_option('yfym_sales_notes_cat');
		delete_option('yfym_sales_notes');
		delete_option('yfym_price_from');	
		delete_option('yfym_desc');
		delete_option('yfym_barcode');
		delete_option('yfym_enable_auto_discount');
		delete_option('yfym_expiry');
		delete_option('yfym_downloadable');
		delete_option('yfym_age');
		delete_option('yfym_country_of_origin');
		delete_option('yfym_manufacturer_warranty');
		delete_option('yfym_adult');
		delete_option('yfym_oldprice');
		delete_option('yfym_step_export');
		delete_option('yfym_errors');
		delete_option('yfym_enable_auto_discounts');
		delete_option('yfym_skip_backorders_products');
		delete_option('yfym_no_default_png_products');	
		delete_option('yfym_skip_products_without_pic');		
	}
 }

 // Добавляем пункты меню
 public function add_admin_menu() {
	$page_suffix = add_menu_page(null , __('Export Yandex Market', 'yfym'), 'manage_options', 'yfymexport', 'yfym_export_page', 'dashicons-redo', 51);
	require_once yfym_DIR.'/export.php'; // Подключаем файл настроек
	// создаём хук, чтобы стили выводились только на странице настроек
	add_action('admin_print_styles-' . $page_suffix, array($this, 'yfym_admin_css_func'));
 	add_action('admin_print_styles-' . $page_suffix, array($this, 'yfym_admin_head_css_func'));
	
	add_submenu_page( 'yfymexport', __('Add Extensions', 'yfym'), __('Extensions', 'yfym'), 'manage_options', 'yfymextensions', 'yfym_extensions_page' );
	require_once yfym_DIR.'/extensions.php';
 } 
 
 // Создает папку для хранения временных файлов
 public static function dir_create() {
	 
 }
 
 // Разрешим загрузку xml и csv файлов
 public function yfym_add_mime_types($mimes) {
	$mimes ['csv'] = 'text/csv';
	$mimes ['xml'] = 'text/xml';		
	return $mimes;
 } 

 /* добавляем интервалы крон в 70 секунд и 6 часов */
 public function cron_add_seventy_sec($schedules) {
	$schedules['seventy_sec'] = array(
		'interval' => 70,
		'display' => '70 sec'
	);
	return $schedules;
 }
 public function cron_add_six_hours($schedules) {
	$schedules['six_hours'] = array(
		'interval' => 21600,
		'display' => '6 hours'
	);
	return $schedules;
 }
 /* end добавляем интервалы крон в 70 секунд и 6 часов */ 
 
 // Добавляем блок Настройки акции на страницау создания поста акции
 public function yfym_add_custom_box() {
	$screens = array('product');
	add_meta_box('yfym_delivery_options', __('Settings delivery options for Yandex Market', 'yfym'),  array($this,'yfym_meta_box_callback'), $screens /*, 'side'*/ );
 }
 // HTML код блока Настройки акции
 public function yfym_meta_box_callback($post, $meta) {
	// $screens = $meta['args'];
	// Используем nonce для верификации
	wp_nonce_field(plugin_basename(__FILE__), 'yfym_noncename');

	$yfym_meta = new stdClass; // читаем все метаполя
	foreach((array)get_post_meta($post->ID) as $k => $v) $yfym_meta->$k = $v[0];

	$yfym_individual_delivery = $yfym_meta->yfym_individual_delivery;
	$yfym_cost = $yfym_meta->yfym_cost;
	$yfym_days = $yfym_meta->yfym_days;
	$yfym_order_before = $yfym_meta->yfym_order_before;
	$yfym_pickup_cost = $yfym_meta->yfym_pickup_cost;
	$yfym_pickup_days = $yfym_meta->yfym_pickup_days;
	$yfym_pickup_order_before = $yfym_meta->yfym_pickup_order_before;
	$yfym_bid = $yfym_meta->yfym_bid;	
	$yfym_condition = $yfym_meta->yfym_condition;
	$yfym_reason = $yfym_meta->yfym_reason;
	$yfym_credit_template = $yfym_meta->yfym_credit_template;
	
	// Поля формы для введения данных
	?>
	<p><span class="description"><?php _e('Here you can set up individual options terms for this product', 'yfym'); ?>. <a target="_blank" href="//yandex.ru/support/partnermarket/elements/delivery-options.html#structure"><?php _e('Read more on Yandex', 'yfym'); ?></a></span></p>
	<table class="form-table"><tbody>
	 <tr>
		<th scope="row"><label for="yfym_individual_delivery"><?php _e('Delivery', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<select name="yfym_individual_delivery" id="yfym_individual_delivery">	
			<option value="off" <?php selected($yfym_individual_delivery, 'off'); ?>><?php _e('Use global settings', 'yfym'); ?></option>			
			<option value="false" <?php selected($yfym_individual_delivery, 'false'); ?>>False</option>
			<option value="true" <?php selected($yfym_individual_delivery, 'true'); ?>>True</option>
			</select><br />
			<span class="description"><?php _e('Optional element', 'yfym'); ?> <strong>delivery</strong>.</span>
		</td>
	 </tr>	
	 <tr>
		<th scope="row"><label for="yfym_cost"><?php _e('Delivery cost', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_cost" min="0" type="number" name="yfym_cost" value="<?php echo $yfym_cost; ?>" /><br />
			<span class="description"><?php _e('Required element', 'yfym'); ?> <strong>cost</strong> <?php _e('of attribute', 'yfym'); ?> <strong>delivery-option</strong></span>
		</td>
	 </tr>
	 <tr>
		<th scope="row"><label for="yfym_days"><?php _e('Delivery days', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_days" type="text" name="yfym_days" value="<?php echo $yfym_days; ?>" /><br />
			<span class="description"><?php _e('Required element', 'yfym'); ?> <strong>days</strong> <?php _e('of attribute', 'yfym'); ?> <strong>delivery-option</strong></span>
		</td>
	 </tr>
	 <tr>
		<th scope="row"><label for="yfym_order_before"><?php _e('The time', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_order_before" type="text" name="yfym_order_before" value="<?php echo $yfym_order_before; ?>" /><br />
			<span class="description"><?php _e('Optional element', 'yfym'); ?> <strong>order-before</strong> <?php _e('of attribute', 'yfym'); ?> <strong>delivery-option</strong>.<br /><?php _e('The time in which you need to place an order to get it at this time', 'yfym'); ?></span>
		</td>
	 </tr>
	</tbody></table>
	<p><span class="description"><?php _e('Here you can configure the pickup conditions for this product', 'yfym'); ?>.</span></p>
	<table class="form-table"><tbody>
	 <tr>
		<th scope="row"><label for="yfym_pickup_cost"><?php _e('Pickup cost', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_pickup_cost" min="0" type="number" name="yfym_pickup_cost" value="<?php echo $yfym_pickup_cost; ?>" /><br />
			<span class="description"><?php _e('Required element', 'yfym'); ?> <strong>cost</strong> <?php _e('of attribute', 'yfym'); ?> <strong>pickup-options</strong></span>
		</td>
	 </tr>
	 <tr>
		<th scope="row"><label for="yfym_pickup_days"><?php _e('Pickup days', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_pickup_days" type="text" name="yfym_pickup_days" value="<?php echo $yfym_pickup_days; ?>" /><br />
			<span class="description"><?php _e('Required element', 'yfym'); ?> <strong>days</strong> <?php _e('of attribute', 'yfym'); ?> <strong>pickup-options</strong></span>
		</td>
	 </tr>
	 <tr>
		<th scope="row"><label for="yfym_pickup_order_before"><?php _e('The time', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_pickup_order_before" type="text" name="yfym_pickup_order_before" value="<?php echo $yfym_pickup_order_before; ?>" /><br />
			<span class="description"><?php _e('Optional element', 'yfym'); ?> <strong>order-before</strong> <?php _e('of attribute', 'yfym'); ?> <strong>pickup-options</strong>.<br /><?php _e('The time in which you need to place an order to get it at this time', 'yfym'); ?></span>
		</td>
	 </tr>
	</tbody></table>
	<p><span class="description"><?php _e('Bid values', 'yfym'); ?> & <?php _e('Сondition', 'yfym'); ?>.</span></p>
	<table class="form-table"><tbody>
	 <tr>
		<th scope="row"><label for="yfym_bid"><?php _e('Bid values', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_bid" type="text" name="yfym_bid" value="<?php echo $yfym_bid; ?>" /><br />
			<span class="description"><?php _e('Optional element', 'yfym'); ?> <strong>bid</strong>.<br /><?php _e('Bid values in your price list. Specify the bid amount in Yandex cents: for example, the value 80 corresponds to the bid of 0.8 Yandex units. The values must be positive integers', 'yfym'); ?>. <a target="_blank" href="//yandex.ru/support/partnermarket/elements/bid-cbid.html"><?php _e('Read more on Yandex', 'yfym'); ?></a></span>
		</td>
	 </tr>
	 <tr>
		<th scope="row"><label for="yfym_condition"><?php _e('Сondition', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<select name="yfym_condition" id="yfym_condition">	
			<option value="off" <?php selected($yfym_condition, 'off'); ?>><?php _e('None', 'yfym'); ?></option>			
			<option value="likenew" <?php selected($yfym_condition, 'likenew'); ?>><?php _e('Like New', 'yfym'); ?></option>
			<option value="used" <?php selected($yfym_condition, 'used'); ?>><?php _e('Used', 'yfym'); ?></option>
			</select><br />
			<span class="description"><?php _e('Optional element', 'yfym'); ?> <strong>condition</strong>.</span>
		</td>
	 </tr>
	 <tr>		
		<th scope="row"><label for="yfym_reason"><?php _e('Reason', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_reason" type="text" name="yfym_reason" value="<?php echo $yfym_reason; ?>" /><br />
			<span class="description"><?php _e('Required element', 'yfym'); ?> <strong>reason</strong> <?php _e('of attribute', 'yfym'); ?> <strong>condition</strong></span>
		</td>		
	 </tr>	 
	 <tr>		
		<th scope="row"><label for="yfym_credit_template"><?php _e('ID of the loan program', 'yfym'); ?></label></th>
		<td class="overalldesc">
			<input id="yfym_credit_template" type="text" name="yfym_credit_template" value="<?php echo $yfym_credit_template; ?>" /><br />
			<span class="description"><?php _e('Optional element', 'yfym'); ?> <strong>credit-template</strong>. <a target="_blank" href="//yandex.ru/support/partnermarket/efficiency/credit.html"><?php _e('Read more on Yandex', 'yfym'); ?></a></span>
		</td>		
	 </tr>	 
	</tbody></table><?php 
 }
 // Сохраняем данные блока, когда пост сохраняется
 function yfym_save_post_product_function ($post_id, $post, $update) {
	yfym_error_log('Стартовала функция yfym_save_post_product_function! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
	
	if ($post->post_type !== 'product') {return;} // если это не товар вукомерц
	if (wp_is_post_revision($post_id)) {return;} // если это ревизия
	// проверяем nonce нашей страницы, потому что save_post может быть вызван с другого места.
//	if (!wp_verify_nonce($_POST['yfym_noncename'], plugin_basename(__FILE__))) {return;}
	// если это автосохранение ничего не делаем
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {return;}
	// проверяем права юзера
	if (!current_user_can('edit_post', $post_id)) {return;}
	// Все ОК. Теперь, нужно найти и сохранить данные
	// Очищаем значение поля input.
	yfym_error_log('Работает функция yfym_save_post_product_function! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);

	// Убедимся что поле установлено.
	if (isset($_POST['yfym_cost'])) {	
		$yfym_individual_delivery = sanitize_text_field($_POST['yfym_individual_delivery']);
		$yfym_cost = sanitize_text_field($_POST['yfym_cost']);
		$yfym_days = sanitize_text_field($_POST['yfym_days']);
		$yfym_order_before = sanitize_text_field($_POST['yfym_order_before']);
		$yfym_pickup_cost = sanitize_text_field($_POST['yfym_pickup_cost']);
		$yfym_pickup_days = sanitize_text_field($_POST['yfym_pickup_days']);
		$yfym_pickup_order_before = sanitize_text_field($_POST['yfym_pickup_order_before']);
		$yfym_bid = sanitize_text_field($_POST['yfym_bid']);
		$yfym_condition = sanitize_text_field($_POST['yfym_condition']);
		$yfym_reason = sanitize_text_field($_POST['yfym_reason']);
		$yfym_credit_template = sanitize_text_field($_POST['yfym_credit_template']);
		
		// Обновляем данные в базе данных
		update_post_meta($post_id, 'yfym_individual_delivery', $yfym_individual_delivery);
		update_post_meta($post_id, 'yfym_cost', $yfym_cost);
		update_post_meta($post_id, 'yfym_days', $yfym_days);
		update_post_meta($post_id, 'yfym_order_before', $yfym_order_before);
		update_post_meta($post_id, 'yfym_pickup_cost', $yfym_pickup_cost);
		update_post_meta($post_id, 'yfym_pickup_days', $yfym_pickup_days);
		update_post_meta($post_id, 'yfym_pickup_order_before', $yfym_pickup_order_before);
		update_post_meta($post_id, 'yfym_bid', $yfym_bid);
		update_post_meta($post_id, 'yfym_condition', $yfym_condition);
		update_post_meta($post_id, 'yfym_reason', $yfym_reason);
		update_post_meta($post_id, 'yfym_credit_template', $yfym_credit_template);
				
	}
	$result_yml = yfym_unit($post_id); // формируем фид товара
	yfym_wf($result_yml, $post_id); // записываем кэш-файл
	
	// нужно ли запускать обновление фида при перезаписи файла
	$yfym_ufup = yfym_optionGET('yfym_ufup');
	if ($yfym_ufup !== 'on') {return;}
	$status_sborki = (int)yfym_optionGET('yfym_status_sborki');
	if ($status_sborki > -1) {return;} // если идет сборка фида - пропуск
	
	$yfym_date_save_set = yfym_optionGET('yfym_date_save_set');
	$yfym_date_sborki = yfym_optionGET('yfym_date_sborki');	
	
	if (is_multisite()) {
		/*
		*	wp_get_upload_dir();
		*   'path'    => '/home/site.ru/public_html/wp-content/uploads/2016/04',
		*	'url'     => 'http://site.ru/wp-content/uploads/2016/04',
		*	'subdir'  => '/2016/04',
		*	'basedir' => '/home/site.ru/public_html/wp-content/uploads',
		*	'baseurl' => 'http://site.ru/wp-content/uploads',
		*	'error'   => false,
		*/
		$upload_dir = (object)wp_get_upload_dir();
		$filenamefeed = $upload_dir->basedir."/feed-yml-".get_current_blog_id().".xml";		
	} else {
		$upload_dir = (object)wp_get_upload_dir();
		$filenamefeed = $upload_dir->basedir."/feed-yml-0.xml";
	}
	if (!file_exists($filenamefeed)) {return;} // файла с фидом нет

	clearstatcache(); // очищаем кэш дат файлов
	$last_upd_file = filemtime($filenamefeed);
	yfym_error_log('$yfym_date_save_set='.$yfym_date_save_set.';$filenamefeed='.$filenamefeed, 0);
	yfym_error_log('Начинаем сравнивать даты! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);	
	if ($yfym_date_save_set > $last_upd_file) {
		// настройки сохранялись позже, чем создан фид		
		// нужно полностью пересобрать фид
		$yfym_status_cron = yfym_optionGET('yfym_status_cron');
		$recurrence = $yfym_status_cron;
		wp_clear_scheduled_hook('yfym_cron_period');
		wp_schedule_event( time(), $recurrence, 'yfym_cron_period');
		yfym_error_log('yfym_cron_period внесен в список заданий! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
	} else { // нужно лишь обновить цены	
		yfym_error_log('Нужно лишь обновить цены! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
		yfym_onlygluing();
	}
	return;
 }
  
 /* функции крона */
 public function yfym_do_this_seventy_sec() {
	if (is_multisite()) { 
		$log = get_blog_option(get_current_blog_id(), 'yfym_status_sborki');
	} else {
		$log = get_option('yfym_status_sborki');
	}
	yfym_error_log('Крон yfym_do_this_seventy_sec запущен. log = '.$log.'; Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
	$this->yfym_construct_yml(); // делаем что-либо каждые 70 сек
 }
 public function yfym_do_this_event() {
	yfym_error_log('Крон yfym_do_this_event включен. Делаем что-то каждый час', 0);
	if (is_multisite()) {
		$step_export = (int)get_blog_option(get_current_blog_id(), 'yfym_step_export');
		if ($step_export == 0) {$step_export = 500;}
		update_blog_option(get_current_blog_id(), 'yfym_status_sborki', $step_export);
	} else {
		$step_export = (int)get_option('yfym_step_export');
		if ($step_export == 0) {$step_export = 500;}		
		update_option('yfym_status_sborki', $step_export);
	}
	wp_clear_scheduled_hook( 'yfym_cron_sborki' );
	wp_schedule_event(time(), 'seventy_sec', 'yfym_cron_sborki');
 }
 /* end функции крона */
 
 // Вывод различных notices
 public function yfym_admin_notices_function() {
  if (is_multisite()) {	
	if (get_blog_option(get_current_blog_id(), 'yfym_magazin_type') == 'woocommerce') { 
		if (!class_exists('WooCommerce')) {
			print '<div class="notice error is-dismissible"><p>'. __('WooCommerce is not active!', 'yfym'). '.</p></div>';
		}
	}	
	$yfym_version = get_blog_option(get_current_blog_id(), 'yfym_version');
	$status_sborki = (int)get_blog_option(get_current_blog_id(), 'yfym_status_sborki');
  } else {
	if (get_option('yfym_magazin_type') == 'woocommerce') { 
		if (!class_exists('WooCommerce')) {
			print '<div class="notice error is-dismissible"><p>'. __('WooCommerce is not active!', 'yfym'). '.</p></div>';
		}
	}
	$yfym_version = get_option('yfym_version');		
	$status_sborki = (int)get_option('yfym_status_sborki');	
  }

  if ($yfym_version == '2.3.3' || $yfym_version == '2.1.2' || $yfym_version == '2.0.9' || $yfym_version == '2.0.11' || $yfym_version == '2.0.10') {} else {
	print '<div class="notice error is-dismissible"><p>'. __('Plugin has been updated to version', 'yfym').' 2.3.3. '. __('Please resave the plugin settings', 'yfym'). ' YML for Yandex Market!</p></div>';
  }
  
  if ($status_sborki !== -1) {	
	$count_posts = wp_count_posts('product');
	$vsegotovarov = $count_posts->publish;
	if (is_multisite()) {
		$step_export = (int)get_blog_option(get_current_blog_id(), 'yfym_step_export');
	} else {
		$step_export = (int)get_option('yfym_step_export');
	}
	if ($step_export == 0) {$step_export = 500;}		
	$vobrabotke = $status_sborki-$step_export;
	if ($vsegotovarov > $vobrabotke) {
		$vyvod = __('Progress', 'yfym').': '.$vobrabotke.' '. __('from', 'yfym').' '.$vsegotovarov.' '. __('products', 'yfym') .'.<br />'.__('If the progress indicators have not changed within 20 minutes, try reducing the "Step of export" in the plugin settings', 'yfym');
	} else {
		$vyvod = __('Prior to the completion of less than 70 seconds', 'yfym');
	}	
	print '<div class="updated notice notice-success is-dismissible"><p>'. __('We are working on automatic file creation. YML will be developed soon', 'yfym').'. '.$vyvod.'.</p></div>';
  }	
  if (isset($_REQUEST['yfym_submit_action'])) {
	$run_text = '';
	if (sanitize_text_field($_POST['yfym_run_cron']) !== 'off') {
		$run_text = '. '. __('Creating the feed is running. You can continue working with the website', 'yfym');
	}
	print '<div class="updated notice notice-success is-dismissible"><p>'. __('Updated', 'yfym'). $run_text .'.</p></div>';
  }
  if (isset($_REQUEST['yfym_submit_reset'])) {
	print '<div class="updated notice notice-success is-dismissible"><p>'. __('The settings have been reset', 'yfym'). '.</p></div>';		
  }
  if (isset($_REQUEST['yfym_submit_send_stat'])) {
	print '<div class="updated notice notice-success is-dismissible"><p>'. __('The data has been sent. Thank you', 'yfym'). '.</p></div>';		
  }
 }
 
 // сборка
 public static function yfym_construct_yml() {
	yfym_error_log('Стартовала yfym_construct_yml. Файл: yml-for-yandex-market.php; Строка: '.__LINE__ , 0);

 	$result_yml = '';
	$status_sborki = (int)yfym_optionGET('yfym_status_sborki');

	if ($status_sborki == -1 ) {	
		wp_clear_scheduled_hook('yfym_cron_sborki'); // файл уже собран. На всякий случай отключим крон сборки
		return;
	} 
		
	$yfym_date_save_set = yfym_optionGET('yfym_date_save_set');
	if ($yfym_date_save_set == '') {	
		$unixtime = current_time('timestamp', 1); // 1335808087 - временная зона GMT (Unix формат)
		if (is_multisite()) {
			update_blog_option(get_current_blog_id(), 'yfym_date_save_set', $unixtime);
		} else {
			update_option('yfym_date_save_set', $unixtime);		
		}
	}
	$yfym_date_sborki = yfym_optionGET('yfym_date_sborki');	
	
	if (is_multisite()) {
		/*
		*	wp_get_upload_dir();
		*   'path'    => '/home/site.ru/public_html/wp-content/uploads/2016/04',
		*	'url'     => 'http://site.ru/wp-content/uploads/2016/04',
		*	'subdir'  => '/2016/04',
		*	'basedir' => '/home/site.ru/public_html/wp-content/uploads',
		*	'baseurl' => 'http://site.ru/wp-content/uploads',
		*	'error'   => false,
		*/
		$upload_dir = (object)wp_get_upload_dir();
		$filenamefeed = $upload_dir->basedir."/feed-yml-".get_current_blog_id().".xml";		
	} else {
		$upload_dir = (object)wp_get_upload_dir();
		$filenamefeed = $upload_dir->basedir."/feed-yml-0.xml";
	}
	if (file_exists($filenamefeed)) {		
		yfym_error_log('Файл с фидом '.$filenamefeed.' есть. Файл: yml-for-yandex-market.php; Строка: '.__LINE__ , 0);
		//return; // файла с фидом нет	
		// $yfym_file_url = urldecode(get_option('yfym_file_url'));
		// $yfym_file_file = preg_replace('/\?v=[\d]+$/', '', $yfym_file_url);
		clearstatcache(); // очищаем кэш дат файлов
		$last_upd_file = filemtime($filenamefeed);
		yfym_error_log('$yfym_date_save_set='.$yfym_date_save_set.'; $filenamefeed='.$filenamefeed, 0);
		yfym_error_log('Начинаем сравнивать даты! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);	
		if ($yfym_date_save_set < $last_upd_file) {
			yfym_error_log('Нужно лишь обновить цены! Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
			yfym_onlygluing();
			return;
		}	
	}
	// далее исходим из того, что файла с фидом нет, либо нужна полная сборка
	
	$step_export = (int)yfym_optionGET('yfym_step_export');
	if ($step_export == 0) {$step_export = 500;}
	
	if ($status_sborki == $step_export) { // начинаем сборку файла
		do_action('yfym_before_construct', 'full'); // сборка стартовала
		$result_yml = yfym_feed_header();
		/* создаем файл или перезаписываем старый удалив содержимое */
		$result = yfym_write_file($result_yml, 'w+');
		if ($result !== true) {
			yfym_error_log('yfym_write_file вернула ошибку! $result ='.$result.'; Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
			return; 
		}
	} 
	if ($status_sborki > 1) {
		$result_yml	= '';
		$offset = $status_sborki-$step_export;
		$whot_export = yfym_optionGET('yfym_whot_export');
		if ($whot_export == 'all' || $whot_export == 'simple') {
			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => $step_export, // сколько выводить товаров
				'offset' => $offset,
				'relation' => 'AND'
			);
		} else {
			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => $step_export, // сколько выводить товаров
				'offset' => $offset,
				'relation' => 'AND',
				'meta_query' => array(
					array(
						'key' => 'vygruzhat',
						'value' => 'on'
					)
				)
			);		
		}
		$args = apply_filters('yfym_query_arg_filter', $args);
		$featured_query = new WP_Query($args);
		$prod_id_arr = array(); 
		if ($featured_query->have_posts()) { 		
		 for ($i = 0; $i < count($featured_query->posts); $i++) {
		  // $prod_id_arr[] .= $featured_query->posts[$i]->ID;
		  $prod_id_arr[$i]['ID'] = $featured_query->posts[$i]->ID;
		  $prod_id_arr[$i]['post_modified_gmt'] =$featured_query->posts[$i]->post_modified_gmt;
		 }
		 //for ($i = 0; $i < count($prod_id_arr); $i++) {
		 wp_reset_query(); /* Remember to reset */
		 unset($featured_query); // чутка освободим память
		 yfym_gluing($prod_id_arr);
		 // }
		 $status_sborki = $status_sborki + $step_export;
		 yfym_error_log('status_sborki увеличен на '.$step_export.' и равен '.$status_sborki.'; Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
				
		 update_option('yfym_status_sborki', $status_sborki);		 
		 
		} else {
		 // если постов нет, пишем концовку файла
		 $result_yml .= "</offers>". PHP_EOL; 
		 $result_yml = apply_filters('yfym_after_offers_filter', $result_yml);
		 $result_yml .= "</shop>". PHP_EOL ."</yml_catalog>";
		 /* создаем файл или перезаписываем старый удалив содержимое */
		 $result = yfym_write_file($result_yml,'a');
		 yfym_rename_file();		 
		 // выставляем статус сборки в "готово"
		 $status_sborki = -1;
		 if ($result == true) {
			update_option('yfym_status_sborki', $status_sborki);
			// останавливаем крон сборки
			wp_clear_scheduled_hook('yfym_cron_sborki');
			do_action('yfym_after_construct', 'full'); // сборка закончена
		 } else {
			yfym_error_log('yfym_write_file вернула ошибку! Я не смог записать концовку файла... $result ='.$result.'; Файл: yml-for-yandex-market.php; Строка: '.__LINE__, 0);
			do_action('yfym_after_construct', 'false'); // сборка закончена
			return;
		 }		 
		}
	} // end if ($status_sborki > 1)
 } // end public static function yfym_construct_yml
} /* end class YmlforYandexMarket */
?>