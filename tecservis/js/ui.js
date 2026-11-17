/* TEC SERVIS - preferencias visuales. No interviene en formularios ni lógica de negocio. */
(function () {
  'use strict';
  var root = document.documentElement;
  var zoom = Number(localStorage.getItem('tecservis_zoom') || '1');
  var theme = localStorage.getItem('tecservis_theme') || 'light';

  function applyTheme() {
    root.setAttribute('data-theme', theme);
    var button = document.getElementById('themeToggle');
    if (button) {
      button.textContent = theme === 'dark' ? '☀' : '☾';
      button.setAttribute('aria-label', theme === 'dark' ? 'Activar modo claro' : 'Activar modo oscuro');
      button.title = theme === 'dark' ? 'Modo claro' : 'Modo oscuro';
    }
  }

  function applyZoom() {
    zoom = Math.min(1.2, Math.max(.8, zoom));
    root.style.setProperty('--ui-zoom', zoom);
    document.body.style.zoom = zoom;
    localStorage.setItem('tecservis_zoom', String(zoom));
    var label = document.getElementById('zoomValue');
    if (label) label.textContent = Math.round(zoom * 100) + '%';
  }

  window.toggleTheme = function () {
    theme = theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('tecservis_theme', theme);
    applyTheme();
  };
  window.zoomIn = function () { zoom += .1; applyZoom(); };
  window.zoomOut = function () { zoom -= .1; applyZoom(); };
  window.zoomReset = function () { zoom = 1; applyZoom(); };

  applyTheme();
  document.addEventListener('DOMContentLoaded', applyZoom);
})();
