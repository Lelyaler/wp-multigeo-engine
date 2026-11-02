<?php
/**
 * Класс безопасного REST API для интеграции с внешними системами управления
 *
 * @package WpMultiGeoEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiGeo_Rest_Api {

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

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Регистрация эндпоинтов REST API
	 */
	public function register_routes() {
		register_rest_route(
			'multigeo/v1',
			'/update-target',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_update_target' ),
				'permission_callback' => array( $this, 'check_api_permission' ),
				'args'                => array(
					'target_slug' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					),
					'target_geo'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'multigeo/v1',
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_get_status' ),
				'permission_callback' => array( $this, 'check_api_permission' ),
			)
		);
	}

	/**
	 * Проверка прав доступа: X-API-Key заголовок либо права manage_options
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_api_permission( WP_REST_Request $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$provided_key = $request->get_header( 'x-api-key' );
		$stored_key   = get_option( 'multigeo_api_key', '' );

		if ( ! empty( $stored_key ) && ! empty( $provided_key ) && hash_equals( (string) $stored_key, (string) $provided_key ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Доступ запрещён. Требуется валидный X-API-Key заголовок или авторизация администратора.', 'wp-multigeo-engine' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Обработка обновления целевого слага и ГЕО
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_target( WP_REST_Request $request ) {
		$new_slug = trim( (string) $request->get_param( 'target_slug' ) );
		$new_geo  = trim( (string) $request->get_param( 'target_geo' ) );

		if ( empty( $new_slug ) || ! preg_match( '/^[a-z0-9\-]+$/i', $new_slug ) ) {
			return new WP_Error( 'invalid_slug', __( 'Некорректный формат URL-слага.', 'wp-multigeo-engine' ), array( 'status' => 400 ) );
		}

		if ( empty( $new_geo ) || ! preg_match( '/^[a-z]{2}(-[a-z]{2})?$/i', $new_geo ) ) {
			return new WP_Error( 'invalid_geo', __( 'Некорректный формат кода ГЕО. Используйте ISO формат (например: ru, kz, en-ca).', 'wp-multigeo-engine' ), array( 'status' => 400 ) );
		}

		$options  = get_option( 'multigeo_settings', array() );
		$old_slug = $options['target_slug'] ?? '';

		// Переименование или клонирование страницы
		if ( ! empty( $old_slug ) && $old_slug !== $new_slug ) {
			$this->replicator->update_page_slug( $old_slug, $new_slug, $new_geo );
		}

		$page_id = $this->replicator->replicate_front_page( $new_slug, $new_geo );

		$options['target_slug'] = $new_slug;
		$options['target_geo']  = strtolower( $new_geo );
		update_option( 'multigeo_settings', $options );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Целевая ГЕО-версия успешно обновлена.', 'wp-multigeo-engine' ),
				'data'    => array(
					'target_slug' => $new_slug,
					'target_geo'  => $new_geo,
					'page_id'     => $page_id ? (int) $page_id : null,
					'page_url'    => $page_id ? get_permalink( $page_id ) : null,
				),
			),
			200
		);
	}

	/**
	 * Получение текущего статуса конфигурации
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_get_status( WP_REST_Request $request ) {
		$options     = get_option( 'multigeo_settings', array() );
		$target_slug = $options['target_slug'] ?? '';
		$target_geo  = $options['target_geo'] ?? '';

		$page = ! empty( $target_slug ) ? $this->replicator->get_page_by_slug( $target_slug ) : null;

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'target_slug' => $target_slug,
					'target_geo'  => $target_geo,
					'page_id'     => $page ? (int) $page->ID : null,
					'page_url'    => $page ? get_permalink( $page->ID ) : null,
					'status'      => $page ? 'active' : 'not_created',
				),
			),
			200
		);
	}
}
