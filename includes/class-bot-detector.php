<?php
/**
 * Класс обнаружения поисковых краулеров
 *
 * @package WpMultiGeoEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MultiGeo_Bot_Detector {

	/**
	 * Флаг Googlebot
	 *
	 * @var bool
	 */
	private $is_googlebot = false;

	/**
	 * Флаг YandexBot
	 *
	 * @var bool
	 */
	private $is_yandexbot = false;

	/**
	 * Флаг любого поискового краулера
	 *
	 * @var bool
	 */
	private $is_search_bot = false;

	/**
	 * Конструктор с анализом User-Agent
	 */
	public function __construct() {
		$this->detect();
	}

	/**
	 * Проверка текущего User-Agent
	 */
	private function detect() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';

		if ( empty( $user_agent ) ) {
			return;
		}

		$this->is_googlebot = (bool) preg_match( '/Googlebot|Google-InspectionTool|Google-Site-Verification|Mediapartners-Google/i', $user_agent );
		$this->is_yandexbot = (bool) preg_match( '/YandexBot|YandexAccessibilityBot|YandexMobileBot|YandexDirectDyn|YandexMetrika/i', $user_agent );

		$this->is_search_bot = $this->is_googlebot || $this->is_yandexbot || (bool) preg_match( '/bingbot|msnbot|Baiduspider|DuckDuckBot|Yahoo! Slurp/i', $user_agent );
	}

	/**
	 * Запрос от Googlebot
	 *
	 * @return bool
	 */
	public function is_googlebot() {
		return $this->is_googlebot;
	}

	/**
	 * Запрос от YandexBot
	 *
	 * @return bool
	 */
	public function is_yandexbot() {
		return $this->is_yandexbot;
	}

	/**
	 * Запрос от любого известного краулера
	 *
	 * @return bool
	 */
	public function is_search_bot() {
		return $this->is_search_bot;
	}
}
