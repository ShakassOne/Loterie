(function ($) {
    'use strict';

    function getConfig() {
        return window.LoterieManagerFilters || {};
    }

    function getString(key, fallback) {
        var config = getConfig();
        if (config.i18n && Object.prototype.hasOwnProperty.call(config.i18n, key) && config.i18n[key]) {
            return config.i18n[key];
        }

        return fallback;
    }

    function setLoading($wrapper, isLoading) {
        var $loading = $wrapper.find('.lm-lottery-list__loading');
        var $results = $wrapper.find('.lm-lottery-list__results');

        if (isLoading) {
            $wrapper.addClass('is-loading');
            $results.attr('aria-busy', 'true');
            $loading.text(getString('loading', 'Chargement…')).attr('aria-hidden', 'false');
        } else {
            $wrapper.removeClass('is-loading');
            $results.attr('aria-busy', 'false');
            $loading.empty().attr('aria-hidden', 'true');
        }
    }

    function renderFallback($wrapper, message) {
        var $results = $wrapper.find('.lm-lottery-list__results');
        var $content = $('<p>', { 'class': 'lm-lottery-list__empty' }).text(message);
        $results.html($content);
    }

    function collectFormData($form) {
        return {
            status: $.trim(String($form.find('[name="status"]').val() || '')),
            category: $.trim(String($form.find('[name="category"]').val() || '')),
            search: $.trim(String($form.find('[name="search"]').val() || '')),
            sort: $.trim(String($form.find('[name="sort"]').val() || ''))
        };
    }

    function submitFilters($form) {
        var config = getConfig();
        if (!config.ajax_url || !config.nonce) {
            return;
        }

        var $wrapper = $form.closest('.lm-lottery-list');
        if (!$wrapper.length) {
            return;
        }

        var payload = collectFormData($form);
        payload.action = 'lm_filter_loteries';
        payload.nonce = config.nonce;

        var previousRequest = $wrapper.data('lmFiltersRequest');
        if (previousRequest && typeof previousRequest.abort === 'function') {
            previousRequest.abort();
        }

        setLoading($wrapper, true);

        var request = $.ajax({
            url: config.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: payload
        });

        $wrapper.data('lmFiltersRequest', request);

        request.done(function (response) {
            if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                renderFallback($wrapper, getString('no_results', 'Aucune loterie ne correspond à votre recherche.'));
                return;
            }

            var html = response.data.html.trim();
            if (!html.length) {
                renderFallback($wrapper, getString('no_results', 'Aucune loterie ne correspond à votre recherche.'));
                return;
            }

            $wrapper.find('.lm-lottery-list__results').html(html);
        });

        request.fail(function () {
            renderFallback($wrapper, getString('error', 'Une erreur est survenue lors du chargement des loteries.'));
        });

        request.always(function () {
            setLoading($wrapper, false);
            $wrapper.removeData('lmFiltersRequest');
        });
    }

    $(document).on('submit', '.lm-lottery-filters', function (event) {
        event.preventDefault();
        submitFilters($(this));
    });

    $(document).on('click', '.lm-lottery-filters__reset', function (event) {
        event.preventDefault();

        var $form = $(this).closest('form');
        if (!$form.length) {
            return;
        }

        if (typeof $form[0].reset === 'function') {
            $form[0].reset();
        }

        submitFilters($form);
    });
})(jQuery);
