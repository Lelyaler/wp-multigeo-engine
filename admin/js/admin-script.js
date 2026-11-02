/**
 * Скрипты интерфейса MultiGEO Engine
 */
document.addEventListener('DOMContentLoaded', function () {
  const slugInput = document.getElementById('target_slug');
  const geoInput = document.getElementById('target_geo');

  if (slugInput) {
    slugInput.addEventListener('input', function () {
      // Автоматическое приведение слага к безопасному виду
      this.value = this.value
        .toLowerCase()
        .replace(/[^a-z0-9\-]/g, '')
        .replace(/^\/+|\/+$/g, '');
    });
  }

  if (geoInput) {
    geoInput.addEventListener('input', function () {
      this.value = this.value.toLowerCase().replace(/[^a-z\-]/g, '');
    });
  }
});
