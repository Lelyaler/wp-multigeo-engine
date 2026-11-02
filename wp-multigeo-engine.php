<?php
/**
 * Plugin Name:       WP MultiGEO & Satellite Engine
 * Plugin URI:        https://github.com/Lelyaler/wp-multigeo-engine
 * Description:       Модуль управления региональными посадочными копиями, мультиязычными Hreflang, дифференцированными Canonical для поисковых ботов и интеграцией по защищённому REST API.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Valeria Chernyavskaya
 * Author URI:        https://lelyaler.github.io/Portfolio/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-multigeo-engine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MULTIGEO_ENGINE_VERSION', '1.0.0' );
define( 'MULTIGEO_ENGINE_FILE', __FILE__ );
define( 'MULTIGEO_ENGINE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MULTIGEO_ENGINE_URL', plugin_dir_url( __FILE__ ) );

// Подключение модулей
require_once MULTIGEO_ENGINE_PATH . 'includes/class-bot-detector.php';
require_once MULTIGEO_ENGINE_PATH . 'includes/class-page-replicator.php';
require_once MULTIGEO_ENGINE_PATH . 'includes/class-seo-manager.php';
require_once MULTIGEO_ENGINE_PATH . 'includes/class-rest-api.php';
require_once MULTIGEO_ENGINE_PATH . 'includes/class-admin.php';

/**
 * Инициализация ядра плагина
 */
function multigeo_engine_init() {
	$options = get_option( 'multigeo_settings', array(
		'target_slug'     => '',
		'target_geo'      => 'ru',
		'yandex_redirect' => false,
	) );

	$bot_detector = new MultiGeo_Bot_Detector();
	$replicator   = new MultiGeo_Page_Replicator();
	$seo_manager  = new MultiGeo_Seo_Manager( $bot_detector, $options );
	$rest_api     = new MultiGeo_Rest_Api( $replicator );

	if ( is_admin() ) {
		$admin = new MultiGeo_Admin( $replicator );
	}
}
add_action( 'plugins_loaded', 'multigeo_engine_init' );

/**
 * Активация плагина
 */
register_activation_hook( __FILE__, 'multigeo_engine_activate' );
function multigeo_engine_activate() {
	if ( ! get_option( 'multigeo_api_key' ) ) {
		update_option( 'multigeo_api_key', wp_generate_password( 32, false ) );
	}

	flush_rewrite_rules();
}

/**
 * Деактивация плагина
 */
register_deactivation_hook( __FILE__, 'multigeo_engine_deactivate' );
function multigeo_engine_deactivate() {
	flush_rewrite_rules();
}
