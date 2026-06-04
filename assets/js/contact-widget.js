(function () {
  var storageKey = 'linsyContactWidgetCollapsed';

  function getStoredCollapsed() {
    try {
      return window.localStorage.getItem(storageKey) === '1';
    } catch (e) {
      return false;
    }
  }

  function setStoredCollapsed(val) {
    try {
      window.localStorage.setItem(storageKey, val ? '1' : '0');
    } catch (e) {}
  }

  function init() {
    var desktop = document.querySelector('.linsy-contact-widget__desktop');
    if (!desktop) return;

    var toggle = desktop.querySelector('.linsy-contact-widget__toggle');
    if (!toggle) return;

    var collapsed = getStoredCollapsed();
    if (collapsed) {
      desktop.classList.add('is-collapsed');
      toggle.setAttribute('aria-expanded', 'false');
    } else {
      toggle.setAttribute('aria-expanded', 'true');
    }

    toggle.addEventListener('click', function () {
      var isCollapsed = desktop.classList.toggle('is-collapsed');
      toggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
      setStoredCollapsed(isCollapsed);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

