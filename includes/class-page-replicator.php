<?php
/**
 * Класс клонирования и синхронизации региональных посадочных страниц
 *
 * @package WpMultiGeoEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiGeo_Page_Replicator {

	/**
	 * Конструктор: регистрация хуков для рендеринга шаблонов региональных копий
	 */
	public function __construct() {
		add_filter( 'template_include', array( $this, 'filter_template_include' ), 99 );
	}

	/**
	 * Подключение шаблона главной страницы для региональных копий
	 *
	 * @param string $template Путь к текущему файлу шаблона.
	 * @return string
	 */
	public function filter_template_include( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}

		$page_id = get_queried_object_id();
		if ( ! $page_id ) {
			return $template;
		}

		$is_replicated = (bool) get_post_meta( $page_id, '_wpmg_is_replicated_front', true );

		if ( ! $is_replicated ) {
			$options     = get_option( 'multigeo_settings', array() );
			$target_slug = ! empty( $options['target_slug'] ) ? $this->sanitize_slug( $options['target_slug'] ) : '';
			$post        = get_post( $page_id );

			if ( $post && ! empty( $target_slug ) && $post->post_name === $target_slug ) {
				$is_replicated = true;
			}
		}

		if ( $is_replicated ) {
			$homepage_id = (int) get_option( 'page_on_front' );
			if ( ! $homepage_id ) {
				$home_candidate = $this->get_page_by_slug( 'home' );
				if ( $home_candidate ) {
					$homepage_id = $home_candidate->ID;
				}
			}

			// 1. Проверяем кастомный шаблон у оригинальной главной страницы
			if ( $homepage_id ) {
				$custom_tpl = get_page_template_slug( $homepage_id );
				if ( ! empty( $custom_tpl ) ) {
					$located = locate_template( array( $custom_tpl ) );
					if ( $located ) {
						return $located;
					}
				}
			}

			// 2. Если в теме есть front-page.php
			$front_page = locate_template( array( 'front-page.php' ) );
			if ( $front_page ) {
				return $front_page;
			}

			// 3. Если в теме есть home.php
			$home_page = locate_template( array( 'home.php' ) );
			if ( $home_page ) {
				return $home_page;
			}
		}

		return $template;
	}

	/**
	 * Создание или обновление региональной страницы
	 *
	 * @param string $slug Желаемый URL-слаг.
	 * @param string $geo  Код ГЕО (например: ru, kz, by, br, ca).
	 * @return int|false ID созданной или обновленной страницы.
	 */
	public function replicate_front_page( $slug, $geo ) {
		$clean_slug = $this->sanitize_slug( $slug );
		if ( empty( $clean_slug ) ) {
			return false;
		}

		$homepage_id = (int) get_option( 'page_on_front' );
		if ( ! $homepage_id ) {
			$home_candidate = $this->get_page_by_slug( 'home' );
			if ( $home_candidate ) {
				$homepage_id = $home_candidate->ID;
			}
		}

		$source_post = $homepage_id ? get_post( $homepage_id ) : null;

		$title    = 'Главная (' . strtoupper( sanitize_text_field( $geo ) ) . ')';
		$content  = $source_post ? $source_post->post_content : '';
		$template = $source_post ? get_page_template_slug( $homepage_id ) : '';

		if ( empty( $template ) ) {
			$theme_templates = wp_get_theme()->get_page_templates();
			if ( isset( $theme_templates['home.php'] ) ) {
				$template = 'home.php';
			}
		}

		$existing_page = $this->get_page_by_slug( $clean_slug );

		if ( $existing_page ) {
			$page_id = $existing_page->ID;

			wp_update_post(
				array(
					'ID'            => $page_id,
					'post_title'    => $title,
					'post_name'     => $clean_slug,
					'post_content'  => $content,
					'page_template' => $template,
				)
			);
		} else {
			$page_args = array(
				'post_title'     => $title,
				'post_name'      => $clean_slug,
				'post_content'   => $content,
				'post_status'    => 'publish',
				'post_type'      => 'page',
				'post_parent'    => 0,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'page_template'  => $template,
			);

			$page_id = wp_insert_post( $page_args );
			if ( is_wp_error( $page_id ) || ! $page_id ) {
				return false;
			}
		}

		if ( $page_id ) {
			update_post_meta( $page_id, '_wpmg_is_replicated_front', '1' );
			update_post_meta( $page_id, '_wpmg_geo_code', sanitize_text_field( $geo ) );

			if ( ! empty( $template ) ) {
				update_post_meta( $page_id, '_wp_page_template', $template );
			}

			if ( $homepage_id ) {
				$this->copy_metadata( $homepage_id, $page_id );
			}
		}

		return $page_id;
	}

	/**
	 * Обновление слага существующей региональной страницы при смене настроек
	 *
	 * @param string $old_slug Старый слаг.
	 * @param string $new_slug Новый слаг.
	 * @param string $geo      Код ГЕО.
	 * @return bool
	 */
	public function update_page_slug( $old_slug, $new_slug, $geo ) {
		$old_clean = $this->sanitize_slug( $old_slug );
		$new_clean = $this->sanitize_slug( $new_slug );

		if ( empty( $old_clean ) || empty( $new_clean ) || $old_clean === $new_clean ) {
			return false;
		}

		$page = $this->get_page_by_slug( $old_clean );
		if ( ! $page ) {
			return false;
		}

		wp_update_post(
			array(
				'ID'         => $page->ID,
				'post_name'  => $new_clean,
				'post_title' => 'Главная (' . strtoupper( sanitize_text_field( $geo ) ) . ')',
			)
		);

		return true;
	}

	/**
	 * Получение объекта страницы по слагу
	 *
	 * @param string $slug URL-слаг.
	 * @return WP_Post|null
	 */
	public function get_page_by_slug( $slug ) {
		$clean_slug = $this->sanitize_slug( $slug );
		if ( empty( $clean_slug ) ) {
			return null;
		}

		$posts = get_posts(
			array(
				'name'           => $clean_slug,
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
				'posts_per_page' => 1,
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Копирование всех метаданных и полей ACF
	 *
	 * @param int $source_id ID страницы-источника.
	 * @param int $target_id ID целевой копии.
	 */
	private function copy_metadata( $source_id, $target_id ) {
		$meta_keys = get_post_custom_keys( $source_id );
		if ( ! empty( $meta_keys ) ) {
			$ignored = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_page_template' );

			foreach ( $meta_keys as $key ) {
				if ( in_array( $key, $ignored, true ) || strpos( $key, '_oembed_' ) === 0 || strpos( $key, '_yoast_' ) === 0 ) {
					continue;
				}

				$values = get_post_meta( $source_id, $key );
				foreach ( $values as $value ) {
					update_post_meta( $target_id, $key, maybe_unserialize( $value ) );
				}
			}
		}

		// Копирование полей Advanced Custom Fields Pro
		if ( function_exists( 'get_fields' ) && function_exists( 'update_field' ) ) {
			$acf_fields = get_fields( $source_id );
			if ( is_array( $acf_fields ) ) {
				foreach ( $acf_fields as $field_key => $field_val ) {
					update_field( $field_key, $field_val, $target_id );
				}
			}
		}

		// Обложка записи
		$thumb_id = get_post_thumbnail_id( $source_id );
		if ( $thumb_id ) {
			set_post_thumbnail( $target_id, $thumb_id );
		}
	}

	/**
	 * Очистка и нормализация слага
	 *
	 * @param string $slug
	 * @return string
	 */
	public function sanitize_slug( $slug ) {
		return trim( sanitize_title( (string) $slug ), '/' );
	}
}
