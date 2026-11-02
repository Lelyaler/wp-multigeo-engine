<?php
/**
 * Класс управления мета-тегами Canonical, Hreflang и совместимостью с SEO-плагинами
 *
 * @package WpMultiGeoEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiGeo_Seo_Manager {

	/**
	 * Детектор поисковых краулеров
	 *
	 * @var MultiGeo_Bot_Detector
	 */
	private $bot_detector;

	/**
	 * Настройки плагина
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Конструктор
	 *
	 * @param MultiGeo_Bot_Detector $bot_detector
	 * @param array                  $options
	 */
	public function __construct( MultiGeo_Bot_Detector $bot_detector, array $options ) {
		$this->bot_detector = $bot_detector;
		$this->options      = $options;

		add_action( 'wp_head', array( $this, 'output_seo_tags' ), 1 );
		add_filter( 'language_attributes', array( $this, 'filter_language_attributes' ), 20 );
		add_action( 'template_redirect', array( $this, 'handle_yandex_redirect' ) );
		add_action( 'template_redirect', array( $this, 'suppress_competing_canonicals' ), 1 );
	}

	/**
	 * Получение списка всех активных региональных версий
	 *
	 * @return array Массив [ slug => geo_code ]
	 */
	public function get_all_regional_versions() {
		$versions = array();

		// Настройка из опций плагина
		$target_slug = trim( (string) ( $this->options['target_slug'] ?? '' ) );
		$target_geo  = trim( (string) ( $this->options['target_geo'] ?? 'ru' ) );
		if ( ! empty( $target_slug ) ) {
			$versions[ $target_slug ] = $target_geo;
		}

		// Все созданные региональные посадочные страницы
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'meta_key'       => '_wpmg_is_replicated_front',
				'meta_value'     => '1',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
			)
		);

		if ( ! empty( $pages ) ) {
			foreach ( $pages as $p ) {
				$geo = (string) get_post_meta( $p->ID, '_wpmg_geo_code', true );
				$versions[ $p->post_name ] = ! empty( $geo ) ? $geo : $p->post_name;
			}
		}

		return $versions;
	}

	/**
	 * Получение контекста текущей региональной страницы
	 *
	 * @return array|null [ 'slug' => string, 'geo' => string ]
	 */
	public function get_current_regional_context() {
		$target_slug = trim( (string) ( $this->options['target_slug'] ?? '' ) );
		$target_geo  = trim( (string) ( $this->options['target_geo'] ?? 'ru' ) );

		if ( is_singular( 'page' ) ) {
			$page_id = get_queried_object_id();
			if ( $page_id ) {
				$is_replicated = (bool) get_post_meta( $page_id, '_wpmg_is_replicated_front', true );
				$geo           = (string) get_post_meta( $page_id, '_wpmg_geo_code', true );
				$post          = get_post( $page_id );

				if ( $is_replicated && $post ) {
					return array(
						'slug' => $post->post_name,
						'geo'  => ! empty( $geo ) ? $geo : ( ( $post->post_name === $target_slug ) ? $target_geo : $post->post_name ),
					);
				}

				if ( $post && ! empty( $target_slug ) && $post->post_name === $target_slug ) {
					return array(
						'slug' => $target_slug,
						'geo'  => $target_geo,
					);
				}
			}
		}

		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( ! empty( $path ) ) {
			if ( ! empty( $target_slug ) && $path === trim( $target_slug, '/' ) ) {
				return array(
					'slug' => $target_slug,
					'geo'  => $target_geo,
				);
			}

			$posts = get_posts(
				array(
					'name'           => $path,
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
				)
			);
			if ( ! empty( $posts ) && get_post_meta( $posts[0]->ID, '_wpmg_is_replicated_front', true ) ) {
				$geo = (string) get_post_meta( $posts[0]->ID, '_wpmg_geo_code', true );
				return array(
					'slug' => $posts[0]->post_name,
					'geo'  => ! empty( $geo ) ? $geo : $posts[0]->post_name,
				);
			}
		}

		return null;
	}

	/**
	 * Генерация и вывод SEO тегов в head
	 */
	public function output_seo_tags() {
		$regional_context = $this->get_current_regional_context();
		$is_front         = $this->is_front_page();

		if ( ! $is_front && ! $regional_context ) {
			return;
		}

		$root_url     = trailingslashit( home_url() );
		$all_versions = $this->get_all_regional_versions();

		$tags = array();

		// Hreflang для поисковых краулеров или администратора
		if ( $this->bot_detector->is_search_bot() || current_user_can( 'manage_options' ) ) {
			$tags[] = '<link rel="alternate" hreflang="x-default" href="' . esc_url( $root_url ) . '" />';

			foreach ( $all_versions as $slug => $geo ) {
				$v_url         = trailingslashit( home_url( '/' . $slug ) );
				$hreflang_code = $this->get_hreflang_code( $geo );
				$tags[]        = '<link rel="alternate" hreflang="' . esc_attr( $hreflang_code ) . '" href="' . esc_url( $v_url ) . '" />';
			}
		}

		// Логика Canonical
		if ( $this->bot_detector->is_googlebot() ) {
			if ( $regional_context ) {
				$target_url = trailingslashit( home_url( '/' . $regional_context['slug'] ) );
			} else {
				$first_slug = ! empty( $all_versions ) ? array_key_first( $all_versions ) : '';
				$target_url = ! empty( $first_slug ) ? trailingslashit( home_url( '/' . $first_slug ) ) : $root_url;
			}
			$tags[] = '<link rel="canonical" href="' . esc_url( $target_url ) . '" />';
		} else {
			$current_url = $regional_context ? trailingslashit( home_url( '/' . $regional_context['slug'] ) ) : $root_url;
			$tags[]      = '<link rel="canonical" href="' . esc_url( $current_url ) . '" />';
		}

		if ( ! empty( $tags ) ) {
			echo "<!-- WP MultiGEO Engine -->\n";
			echo implode( "\n", $tags ) . "\n";
			echo "<!-- /WP MultiGEO Engine -->\n";
		}
	}

	/**
	 * Опциональный 302 редирект для YandexBot с целевой страницы на корень
	 */
	public function handle_yandex_redirect() {
		if ( empty( $this->options['yandex_redirect'] ) || ! $this->bot_detector->is_yandexbot() ) {
			return;
		}

		$regional_context = $this->get_current_regional_context();
		if ( $regional_context ) {
			wp_safe_redirect( trailingslashit( home_url() ), 302 );
			exit;
		}
	}

	/**
	 * Отключение конфликтующих тегов Canonical от Yoast SEO, RankMath и ядра WP
	 */
	public function suppress_competing_canonicals() {
		$regional_context = $this->get_current_regional_context();

		if ( $this->is_front_page() || $regional_context ) {
			// Отключение стандартного каноникала ядра
			remove_action( 'wp_head', 'rel_canonical' );

			// Отключение каноникала Yoast SEO
			add_filter( 'wpseo_canonical', '__return_false', 99 );

			// Отключение каноникала RankMath
			if ( class_exists( 'RankMath' ) ) {
				add_filter( 'rank_math/frontend/canonical', '__return_false', 99 );
			}

			// Отключение каноникала All in One SEO
			add_filter( 'aioseo_canonical_url', '__return_false', 99 );
		}
	}

	/**
	 * Корректировка атрибута языка lang в теге <html>
	 *
	 * @param string $output
	 * @return string
	 */
	public function filter_language_attributes( $output ) {
		$regional_context = $this->get_current_regional_context();
		if ( ! $regional_context ) {
			return $output;
		}

		$lang_code = $this->get_hreflang_code( $regional_context['geo'] );
		$output    = preg_replace( '/\s*lang=["\'][^"\']*["\']/', '', $output );
		return trim( $output ) . ' lang="' . esc_attr( $lang_code ) . '"';
	}

	/**
	 * Получение корректного ISO hreflang кода
	 *
	 * @param string $geo Код страны или языка.
	 * @return string
	 */
	public function get_hreflang_code( $geo ) {
		$geo = strtolower( trim( $geo ) );

		// Поддержка составных кодов вида ru-kz, en-ca, pt-br
		if ( strpos( $geo, '-' ) !== false ) {
			$parts = explode( '-', $geo, 2 );
			return strtolower( $parts[0] ) . '-' . strtoupper( $parts[1] );
		}

		// Если передан 2-буквенный региональный код
		$primary_lang = 'ru';
		if ( in_array( $geo, array( 'br', 'pt' ), true ) ) {
			$primary_lang = 'pt';
		} elseif ( in_array( $geo, array( 'ca', 'us', 'gb', 'au', 'nz' ), true ) ) {
			$primary_lang = 'en';
		} elseif ( in_array( $geo, array( 'mx', 'es', 'ar', 'cl' ), true ) ) {
			$primary_lang = 'es';
		} elseif ( in_array( $geo, array( 'de', 'at', 'ch' ), true ) ) {
			$primary_lang = 'de';
		}

		if ( $geo === $primary_lang ) {
			return $primary_lang;
		}

		return $primary_lang . '-' . strtoupper( $geo );
	}

	/**
	 * Проверка главной страницы
	 *
	 * @return bool
	 */
	private function is_front_page() {
		if ( is_front_page() || is_home() ) {
			return true;
		}

		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		return empty( $path );
	}

	/**
	 * Проверка целевой страницы
	 *
	 * @param string $slug
	 * @return bool
	 */
	private function is_target_page( $slug ) {
		$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		return $path === trim( $slug, '/' );
	}
}
