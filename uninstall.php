<?php
/**
 * Очистка настроек при удалении плагина
 *
 * @package WpMultiGeoEngine
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'multigeo_settings' );
delete_option( 'multigeo_api_key' );
