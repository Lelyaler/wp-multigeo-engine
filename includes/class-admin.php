<?php
/**
 * Класс панели управления плагина в админке WordPress
 *
 * @package WpMultiGeoEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiGeo_Admin {

	/**
	 * Репликатор страниц
	 *
	 * @var MultiGeo_Page_Replicator
	 */
	private $replicator;

	/**
	 * Конструктор
	 *
	 * @param MultiGeo_Page_Replicator $replicator
	 */
	public function __construct( MultiGeo_Page_Replicator $replicator ) {
		$this->replicator = $replicator;

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Регистрация страницы настроек
	 */
	public function register_admin_menu() {
		add_options_page(
			__( 'Настройки MultiGEO Engine', 'wp-multigeo-engine' ),
			__( 'MultiGEO Engine', 'wp-multigeo-engine' ),
			'manage_options',
			'wp-multigeo-engine',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Подключение стилей и скриптов админки
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_wp-multigeo-engine' === $hook_suffix ) {
			wp_enqueue_style( 'multigeo-admin-style', plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/admin-style.css', array(), '1.0.0' );
			wp_enqueue_script( 'multigeo-admin-script', plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/admin-script.js', array(), '1.0.0', true );
		}
	}

	/**
	 * Отрисовка страницы настроек и обработка форм
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав для доступа к настройкам.', 'wp-multigeo-engine' ) );
		}

		$notice = '';
		$options = get_option( 'multigeo_settings', array(
			'target_slug'     => '',
			'target_geo'      => 'ru',
			'yandex_redirect' => false,
		) );

		// Сохранение настроек
		if ( isset( $_POST['save_multigeo_settings'] ) ) {
			check_admin_referer( 'multigeo_settings_action', 'multigeo_nonce' );

			$old_slug = $options['target_slug'] ?? '';
			$new_slug = trim( sanitize_title( wp_unslash( $_POST['target_slug'] ?? '' ) ) );
			$new_geo  = trim( sanitize_text_field( wp_unslash( $_POST['target_geo'] ?? 'ru' ) ) );
			$yandex   = ! empty( $_POST['yandex_redirect'] );

			if ( ! empty( $old_slug ) && ! empty( $new_slug ) && $old_slug !== $new_slug ) {
				$this->replicator->update_page_slug( $old_slug, $new_slug, $new_geo );
			}

			$options['target_slug']     = $new_slug;
			$options['target_geo']      = strtolower( $new_geo );
			$options['yandex_redirect'] = $yandex;

			update_option( 'multigeo_settings', $options );
			$notice = __( 'Настройки успешно сохранены.', 'wp-multigeo-engine' );
		}

		// Ручная синхронизация / клонирование страницы
		if ( isset( $_POST['sync_now'] ) ) {
			check_admin_referer( 'multigeo_sync_action', 'multigeo_sync_nonce' );

			if ( ! empty( $options['target_slug'] ) ) {
				$page_id = $this->replicator->replicate_front_page( $options['target_slug'], $options['target_geo'] );
				if ( $page_id ) {
					$notice = sprintf( __( 'Целевая страница успешно синхронизирована (ID: %d).', 'wp-multigeo-engine' ), $page_id );
				} else {
					$notice = __( 'Ошибка: убедитесь, что в Настройки -> Чтение задана статическая главная страница.', 'wp-multigeo-engine' );
				}
			}
		}

		// Генерация нового API ключа
		if ( isset( $_POST['generate_api_key'] ) ) {
			check_admin_referer( 'multigeo_key_action', 'multigeo_key_nonce' );

			$new_key = wp_generate_password( 32, false );
			update_option( 'multigeo_api_key', $new_key );
			$notice = __( 'Сгенерирован новый API-ключ для внешней интеграции.', 'wp-multigeo-engine' );
		}

		$api_key = get_option( 'multigeo_api_key', '' );
		if ( empty( $api_key ) ) {
			$api_key = wp_generate_password( 32, false );
			update_option( 'multigeo_api_key', $api_key );
		}

		$target_page = ! empty( $options['target_slug'] ) ? $this->replicator->get_page_by_slug( $options['target_slug'] ) : null;

		include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/views/settings-view.php';
	}
}
