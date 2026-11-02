<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap multigeo-wrap">
  <div class="multigeo-header">
    <div>
      <h1 class="wp-heading-inline"><?php esc_html_e( 'MultiGEO & Satellite Engine', 'wp-multigeo-engine' ); ?></h1>
      <span class="multigeo-badge"><?php esc_html_e( 'v1.0.0 • iGaming & PBN Suite', 'wp-multigeo-engine' ); ?></span>
      <p class="description">
        <?php esc_html_e( 'Модуль управления региональными посадочными копиями, двуликими тегами Canonical для поисковых ботов и интеграцией по защищённому REST API.', 'wp-multigeo-engine' ); ?>
      </p>
    </div>
  </div>

  <?php if ( ! empty( $notice ) ) : ?>
    <div class="notice notice-success is-dismissible">
      <p><?php echo esc_html( $notice ); ?></p>
    </div>
  <?php endif; ?>

  <!-- Сводка текущего состояния -->
  <div class="multigeo-kpi-grid">
    <div class="multigeo-kpi-card">
      <div class="multigeo-kpi-label"><?php esc_html_e( 'Целевой URL-слаг', 'wp-multigeo-engine' ); ?></div>
      <div class="multigeo-kpi-val">
        <?php echo ! empty( $options['target_slug'] ) ? '<code>/' . esc_html( $options['target_slug'] ) . '/</code>' : '<em>' . esc_html__( 'Не задан', 'wp-multigeo-engine' ) . '</em>'; ?>
      </div>
    </div>
    <div class="multigeo-kpi-card">
      <div class="multigeo-kpi-label"><?php esc_html_e( 'Региональный код (GEO)', 'wp-multigeo-engine' ); ?></div>
      <div class="multigeo-kpi-val">
        <span class="multigeo-geo-tag"><?php echo esc_html( strtoupper( $options['target_geo'] ?? 'RU' ) ); ?></span>
      </div>
    </div>
    <div class="multigeo-kpi-card">
      <div class="multigeo-kpi-label"><?php esc_html_e( 'Статус целевой страницы', 'wp-multigeo-engine' ); ?></div>
      <div class="multigeo-kpi-val">
        <?php if ( $target_page ) : ?>
          <span class="multigeo-status-active">● <?php esc_html_e( 'Опубликована', 'wp-multigeo-engine' ); ?></span>
          <a href="<?php echo esc_url( get_permalink( $target_page->ID ) ); ?>" target="_blank" class="multigeo-link-view"><?php esc_html_e( 'Открыть', 'wp-multigeo-engine' ); ?> &rarr;</a>
        <?php else : ?>
          <span class="multigeo-status-inactive">○ <?php esc_html_e( 'Не создана', 'wp-multigeo-engine' ); ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div class="multigeo-kpi-card">
      <div class="multigeo-kpi-label"><?php esc_html_e( 'Режим ботов', 'wp-multigeo-engine' ); ?></div>
      <div class="multigeo-kpi-val">
        <span style="font-size: 13px; color: #1d2327;">Googlebot: Canonical &rarr; Target</span>
      </div>
    </div>
  </div>

  <div class="multigeo-columns">
    <!-- Левая колонка: Настройки плагина -->
    <div class="multigeo-col-main">
      <div class="postbox multigeo-card">
        <div class="postbox-header">
          <h2><?php esc_html_e( 'Параметры регионального таргетинга', 'wp-multigeo-engine' ); ?></h2>
        </div>
        <div class="inside">
          <form method="post">
            <?php wp_nonce_field( 'multigeo_settings_action', 'multigeo_nonce' ); ?>

            <div class="multigeo-form-group">
              <label for="target_slug"><?php esc_html_e( 'URL-слаг целевой страницы:', 'wp-multigeo-engine' ); ?> <span class="required">*</span></label>
              <input type="text" id="target_slug" name="target_slug" class="regular-text code" value="<?php echo esc_attr( $options['target_slug'] ?? '' ); ?>" placeholder="ru, kz, ca, br..." required>
              <p class="description">
                <?php esc_html_e( 'URL, на который будет склонирована главная страница (например, site.com/kz/). Допустимы латинские буквы, цифры и дефис.', 'wp-multigeo-engine' ); ?>
              </p>
            </div>

            <div class="multigeo-form-group">
              <label for="target_geo"><?php esc_html_e( 'Региональный ISO код ГЕО:', 'wp-multigeo-engine' ); ?> <span class="required">*</span></label>
              <input type="text" id="target_geo" name="target_geo" class="regular-text code" value="<?php echo esc_attr( $options['target_geo'] ?? 'ru' ); ?>" placeholder="ru, kz, en-ca, pt-br..." required>
              <p class="description">
                <?php esc_html_e( 'Используется для формирования тега hreflang и атрибута lang. Примеры: ru, kz, by, en-ca, pt-br.', 'wp-multigeo-engine' ); ?>
              </p>
            </div>

            <div class="multigeo-form-group">
              <label>
                <input type="checkbox" name="yandex_redirect" value="1" <?php checked( ! empty( $options['yandex_redirect'] ) ); ?>>
                <?php esc_html_e( 'Включить 302-редирект для YandexBot с целевой копии на главную', 'wp-multigeo-engine' ); ?>
              </label>
              <p class="description">
                <?php esc_html_e( 'Позволяет удерживать поискового краулера Яндекса на корневом URL, сохраняя для Googlebot отдельную региональную версию.', 'wp-multigeo-engine' ); ?>
              </p>
            </div>

            <div class="multigeo-actions-bar">
              <button type="submit" name="save_multigeo_settings" class="button button-primary button-large">
                <?php esc_html_e( 'Сохранить параметры', 'wp-multigeo-engine' ); ?>
              </button>
            </div>
          </form>

          <?php if ( ! empty( $options['target_slug'] ) ) : ?>
            <hr style="margin: 24px 0 16px; border: 0; border-top: 1px solid #f0f0f1;">
            <form method="post" style="display: flex; align-items: center; justify-content: space-between;">
              <?php wp_nonce_field( 'multigeo_sync_action', 'multigeo_sync_nonce' ); ?>
              <div>
                <strong><?php esc_html_e( 'Синхронизация контента и полей ACF:', 'wp-multigeo-engine' ); ?></strong>
                <p class="description" style="margin: 2px 0 0;"><?php esc_html_e( 'Обновит текст, шаблон, миниатюры и поля Advanced Custom Fields на целевой странице.', 'wp-multigeo-engine' ); ?></p>
              </div>
              <button type="submit" name="sync_now" class="button button-secondary">
                🔄 <?php esc_html_e( 'Синхронизировать сейчас', 'wp-multigeo-engine' ); ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Правая колонка: REST API Интеграция -->
    <div class="multigeo-col-side">
      <div class="postbox multigeo-card">
        <div class="postbox-header">
          <h2><?php esc_html_e( 'Внешняя интеграция (REST API)', 'wp-multigeo-engine' ); ?></h2>
        </div>
        <div class="inside">
          <p class="description">
            <?php esc_html_e( 'Используйте защищённые эндпоинты для автоматического переключения ГЕО и слагов из центральной CRM или сетки сателлитов.', 'wp-multigeo-engine' ); ?>
          </p>

          <div class="multigeo-api-key-block">
            <label><strong><?php esc_html_e( 'Секретный ключ API (X-API-Key):', 'wp-multigeo-engine' ); ?></strong></label>
            <div style="display: flex; gap: 8px; margin-top: 6px;">
              <input type="text" readonly value="<?php echo esc_attr( $api_key ); ?>" class="large-text code" style="background: #f6f7f7;">
            </div>
            <form method="post" style="margin-top: 8px;" onsubmit="return confirm('Сгенерировать новый ключ? Предыдущий ключ перестанет действовать.');">
              <?php wp_nonce_field( 'multigeo_key_action', 'multigeo_key_nonce' ); ?>
              <button type="submit" name="generate_api_key" class="button button-small">
                <?php esc_html_e( 'Сгенерировать новый ключ', 'wp-multigeo-engine' ); ?>
              </button>
            </form>
          </div>

          <div class="multigeo-api-endpoints">
            <p><strong><?php esc_html_e( 'Эндпоинт обновления:', 'wp-multigeo-engine' ); ?></strong></p>
            <code>POST <?php echo esc_html( rest_url( 'multigeo/v1/update-target' ) ); ?></code>

            <p style="margin-top: 10px;"><strong><?php esc_html_e( 'Пример вызова cURL:', 'wp-multigeo-engine' ); ?></strong></p>
            <pre class="multigeo-curl-example"><code>curl -X POST "<?php echo esc_url( rest_url( 'multigeo/v1/update-target' ) ); ?>" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <?php echo esc_attr( $api_key ); ?>" \
  -d '{"target_slug":"ca","target_geo":"en-ca"}'</code></pre>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
