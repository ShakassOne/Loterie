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

    function collectWrapperConfig($wrapper) {
        var data = {};

        if (!$wrapper || !$wrapper.length) {
            return data;
        }

        var layout = $wrapper.data('layout');
        if (layout) {
            data.layout = String(layout);
        }

        var columns = $wrapper.data('columns');
        if (typeof columns !== 'undefined') {
            data.columns = columns;
        }

        var columnsTablet = $wrapper.data('columnsTablet');
        if (typeof columnsTablet !== 'undefined') {
            data.columns_tablet = columnsTablet;
        }

        var columnsMobile = $wrapper.data('columnsMobile');
        if (typeof columnsMobile !== 'undefined') {
            data.columns_mobile = columnsMobile;
        }

        var manualOrder = $wrapper.data('manualOrder');
        if (typeof manualOrder !== 'undefined') {
            data.manual_order = manualOrder ? 1 : 0;
        }

        var emptyMessage = $wrapper.attr('data-empty-message');
        if (typeof emptyMessage === 'string') {
            data.empty_message = emptyMessage;
        }

        var upcomingDate = $wrapper.data('upcomingDate');
        if (typeof upcomingDate !== 'undefined') {
            data.upcoming_date = String(upcomingDate);
        }

        var queryArgsAttr = $wrapper.attr('data-query-args');
        if (queryArgsAttr) {
            data.query_args = queryArgsAttr;
        }

        return data;
    }

    function collectFormData($form, $wrapper) {
        var payload = {
            status: $.trim(String($form.find('[name="status"]').val() || '')),
            category: $.trim(String($form.find('[name="category"]').val() || '')),
            search: $.trim(String($form.find('[name="search"]').val() || '')),
            sort: $.trim(String($form.find('[name="sort"]').val() || ''))
        };

        if ($wrapper && $wrapper.length) {
            $.extend(payload, collectWrapperConfig($wrapper));
        }

        return payload;
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

        var payload = collectFormData($form, $wrapper);
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
