/* FoodSave app shell: mobile top bar + off-canvas sidebar. */
(function () {
    'use strict';

    function init() {
        var sidebar = document.querySelector('nav.sidebar');
        if (!sidebar || document.querySelector('.fs-topbar')) return;

        var brandText = (sidebar.querySelector('h5') || {}).textContent || 'FoodSave';

        var topbar = document.createElement('header');
        topbar.className = 'fs-topbar';
        topbar.innerHTML =
            '<button type="button" class="fs-topbar__toggle" aria-label="Open menu" aria-expanded="false">' +
            '<i class="fas fa-bars"></i></button>' +
            '<span class="fs-topbar__brand"><i class="fas fa-leaf"></i><span></span></span>';
        topbar.querySelector('.fs-topbar__brand span').textContent = brandText.trim();

        var backdrop = document.createElement('div');
        backdrop.className = 'fs-backdrop';

        document.body.insertBefore(topbar, document.body.firstChild);
        document.body.appendChild(backdrop);

        var toggle = topbar.querySelector('.fs-topbar__toggle');

        function setOpen(open) {
            document.body.classList.toggle('fs-nav-open', open);
            toggle.setAttribute('aria-expanded', String(open));
        }

        toggle.addEventListener('click', function () {
            setOpen(!document.body.classList.contains('fs-nav-open'));
        });
        backdrop.addEventListener('click', function () { setOpen(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setOpen(false);
        });
        sidebar.addEventListener('click', function (e) {
            var link = e.target.closest('a.nav-link:not(.dropdown-toggle)');
            if (link) setOpen(false);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
