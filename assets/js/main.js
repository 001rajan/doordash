(function () {
    'use strict';

    var base = typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : '';

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }
    function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

    window.showToast = function (message, type) {
        type = type || 'info';
        var stack = $('#toastStack');
        if (!stack) return;
        var t = document.createElement('div');
        t.className = 'toast ' + type;
        t.textContent = message;
        stack.appendChild(t);
        setTimeout(function () {
            t.style.opacity = '0';
            setTimeout(function () { t.remove(); }, 300);
        }, 3200);
    };

    window.showLoader = function (show) {
        var el = $('#pageLoader');
        if (!el) return;
        if (show) el.classList.add('is-visible');
        else el.classList.remove('is-visible');
    };

    document.addEventListener('DOMContentLoaded', function () {
        var navToggle = $('#navToggle');
        var mainNav = $('#mainNav');
        if (navToggle && mainNav) {
            navToggle.addEventListener('click', function () {
                mainNav.classList.toggle('is-open');
            });
        }

        window.addEventListener('load', function () {
            showLoader(false);
        });

        // Forms: brief loader on submit
        $$('form[data-loading]').forEach(function (f) {
            f.addEventListener('submit', function () { showLoader(true); });
        });
    });

    window.fetchJSON = function (url, options) {
        options = options || {};
        options.headers = options.headers || {};
        options.headers['X-Requested-With'] = 'XMLHttpRequest';
        return fetch(base + url, options).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) throw new Error(data.error || r.statusText);
                return data;
            });
        });
    };
})();
