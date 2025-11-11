(function ($) {
    'use strict';

    var pendingForm = null;
    var bypassModal = false;

    function parseTicketLimit(value) {
        var limit = parseInt(value, 10);
        return isNaN(limit) ? 0 : limit;
    }

    function getTicketLimitMessage(limit) {
        if (limit <= 0 || !window.LoterieManager || !LoterieManager.i18n) {
            return '';
        }

        if (limit === 1 && LoterieManager.i18n.ticket_limit_reached_single) {
            return LoterieManager.i18n.ticket_limit_reached_single;
        }

        if (LoterieManager.i18n.ticket_limit_reached_plural) {
            return LoterieManager.i18n.ticket_limit_reached_plural.replace('%s', limit);
        }

        return '';
    }

    function alertTicketLimit(limit) {
        if (limit <= 0) {
            return;
        }

        var message = getTicketLimitMessage(limit);
        if (!message) {
            message = limit === 1
                ? "Vous ne pouvez sélectionner qu'une loterie pour ce produit."
                : 'Vous ne pouvez sélectionner que ' + limit + ' loteries pour ce produit.';
        }

        window.alert(message);
    }

    function formatTicketBadge(limit) {
        if (limit <= 0) {
            return '';
        }

        if (window.LoterieManager && LoterieManager.i18n) {
            if (limit === 1 && LoterieManager.i18n.ticket_badge_single) {
                return LoterieManager.i18n.ticket_badge_single;
            }

            if (LoterieManager.i18n.ticket_badge_plural) {
                return LoterieManager.i18n.ticket_badge_plural.replace('%d', limit);
            }
        }

        return limit === 1 ? '1 ticket' : limit + ' tickets';
    }

    function ensureTicketBadgeTarget($form) {
        var $product = $form.closest('.product');
        if (!$product.length) {
            return { badge: $(), target: $() };
        }

        var $gallery = $product.find('.woocommerce-product-gallery').first();
        if (!$gallery.length) {
            $gallery = $product;
        }

        var $badge = $gallery.find('.lm-product-ticket-badge').first();
        if (!$badge.length) {
            $badge = $('<div>', { 'class': 'lm-product-ticket-badge', 'aria-live': 'polite' }).hide();
            $gallery.append($badge);
        }

        return { badge: $badge, target: $gallery };
    }

    function updateTicketBadge($form, limit) {
        var target = ensureTicketBadgeTarget($form);
        if (!target.badge.length) {
            return;
        }

        if (limit > 0) {
            target.badge.text(formatTicketBadge(limit)).css('display', 'inline-flex');
            target.target.addClass('lm-has-ticket-badge');
        } else {
            target.badge.text('').css('display', 'none');
            target.target.removeClass('lm-has-ticket-badge');
        }
    }

    function setTicketLimit($form, options) {
        options = options || {};

        var $container = $form.find('.lm-lottery-data');
        if (!$container.length) {
            return;
        }

        var defaultRaw = $container.attr('data-default-ticket-limit');
        if (typeof defaultRaw === 'undefined') {
            defaultRaw = $container.attr('data-ticket-limit') || '';
            $container.attr('data-default-ticket-limit', defaultRaw);
        }

        var hasCustom = !!options.hasCustom;
        var customRaw = typeof options.limit !== 'undefined' ? options.limit : null;
        var customValue = parseTicketLimit(customRaw);
        var useCustom = hasCustom && customRaw !== '' && customRaw !== null && customRaw !== undefined && customValue > 0;

        if (useCustom) {
            $container.attr('data-ticket-limit', customValue);
            $form.attr('data-ticket-limit', customValue);
            updateTicketBadge($form, customValue);
            return;
        }

        var fallbackValue = parseTicketLimit(defaultRaw);
        if (defaultRaw !== '' && fallbackValue > 0) {
            $container.attr('data-ticket-limit', fallbackValue);
            $form.attr('data-ticket-limit', fallbackValue);
            updateTicketBadge($form, fallbackValue);
            return;
        }

        $container.attr('data-ticket-limit', '');
        $form.removeAttr('data-ticket-limit');

        var badgeValue = hasCustom ? customValue : fallbackValue;
        updateTicketBadge($form, badgeValue);
    }

    function getVariationTicketMap($form) {
        var $container = $form.find('.lm-lottery-data');
        if (!$container.length) {
            return {};
        }

        var cached = $container.data('lmTicketMap');
        if (cached) {
            return cached;
        }

        var raw = $container.attr('data-variation-ticket-map');
        var map = {};

        if (raw) {
            try {
                var parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') {
                    map = parsed;
                }
            } catch (error) {
                map = {};
            }
        }

        $container.data('lmTicketMap', map);

        return map;
    }

    function resolveVariationTicketLimit($form, variation) {
        if (!variation) {
            return { hasCustom: false, limit: null };
        }

        var hasCustom = false;
        var limitValue = null;

        if (typeof variation.lm_ticket_allocation_defined !== 'undefined') {
            hasCustom = !!variation.lm_ticket_allocation_defined;
        }

        if (typeof variation.lm_ticket_allocation !== 'undefined' && variation.lm_ticket_allocation !== null && variation.lm_ticket_allocation !== '') {
            limitValue = variation.lm_ticket_allocation;
        }

        if (hasCustom && (limitValue === null || limitValue === '' || typeof limitValue === 'undefined')) {
            hasCustom = false;
        }

        if (!hasCustom) {
            var parsedLimit = parseTicketLimit(limitValue);
            if (limitValue !== null && limitValue !== '' && typeof limitValue !== 'undefined' && parsedLimit > 0) {
                hasCustom = true;
                limitValue = parsedLimit;
            } else {
                var variationId = null;
                if (typeof variation.variation_id !== 'undefined' && variation.variation_id !== null && variation.variation_id !== '') {
                    variationId = variation.variation_id;
                } else if (typeof variation.variationId !== 'undefined') {
                    variationId = variation.variationId;
                } else if (typeof variation.variationID !== 'undefined') {
                    variationId = variation.variationID;
                }

                if (variationId !== null && typeof variationId !== 'undefined') {
                    var map = getVariationTicketMap($form);
                    var key = String(variationId);
                    if (Object.prototype.hasOwnProperty.call(map, key)) {
                        hasCustom = true;
                        limitValue = map[key];
                    } else if (Object.prototype.hasOwnProperty.call(map, variationId)) {
                        hasCustom = true;
                        limitValue = map[variationId];
                    }
                }
            }
        }

        return { hasCustom: hasCustom, limit: limitValue };
    }

    function normalizeLotteries(raw) {
        if (!raw) {
            return [];
        }

        if (Array.isArray(raw)) {
            return raw
                .map(function (entry) {
                    if (!entry || typeof entry !== 'object') {
                        return null;
                    }

                    var normalized = {};
                    for (var key in entry) {
                        if (Object.prototype.hasOwnProperty.call(entry, key)) {
                            normalized[key] = entry[key];
                        }
                    }

                    normalized.id = parseInt(normalized.id, 10);

                    if (isNaN(normalized.id)) {
                        return null;
                    }

                    return normalized;
                })
                .filter(function (entry) {
                    return entry !== null;
                });
        }

        if (typeof raw === 'string') {
            var trimmed = raw.trim();

            if (!trimmed.length) {
                return [];
            }

            try {
                return normalizeLotteries(JSON.parse(trimmed));
            } catch (error) {
                var ids = trimmed.split(',').map(function (value) {
                    var id = parseInt(value, 10);
                    return isNaN(id) ? null : id;
                }).filter(function (value) {
                    return value !== null;
                });

                return ids.map(function (id) {
                    return {
                        id: id,
                        title: LoterieManager && LoterieManager.i18n && LoterieManager.i18n.default_lottery_label
                            ? LoterieManager.i18n.default_lottery_label.replace('%d', id)
                            : 'Loterie #' + id
                    };
                });
            }
        }

        if (typeof raw === 'object') {
            if (Array.isArray(raw.lotteries)) {
                return normalizeLotteries(raw.lotteries);
            }

            var extracted = [];

            for (var prop in raw) {
                if (Object.prototype.hasOwnProperty.call(raw, prop)) {
                    extracted.push(raw[prop]);
                }
            }

            return normalizeLotteries(extracted);
        }

        return [];
    }

    function closeModal() {
        $('.lm-modal-overlay').remove();
        $('body').removeClass('lm-modal-open');
    }

    function buildModal(lotteries) {
        var overlay = $('<div>', { 'class': 'lm-modal-overlay', 'aria-modal': 'true', 'role': 'dialog' });
        var modal = $('<div>', { 'class': 'lm-modal' });
        var form = $('<form>', { 'class': 'lm-lottery-form' });

        form.append($('<h2>').text(LoterieManager.i18n.modal_title));

        var optionsWrapper = $('<div>', { 'class': 'lm-modal-options' });
        lotteries.forEach(function (lottery) {
            var option = $('<label>', { 'class': 'lm-modal-option' });
            var checkbox = $('<input>', {
                type: 'checkbox',
                name: 'lm_lottery_choice[]',
                value: lottery.id
            });

            var description = $('<div>');
            var title = lottery.title;

            if (!title && window.LoterieManager && LoterieManager.i18n && LoterieManager.i18n.default_lottery_label) {
                title = LoterieManager.i18n.default_lottery_label.replace('%d', lottery.id);
            }

            description.append($('<strong>').text(title || ''));

            option.append(checkbox).append(description);
            optionsWrapper.append(option);
        });

        form.append(optionsWrapper);

        var actions = $('<div>', { 'class': 'lm-modal-actions' });
        var cancelBtn = $('<button>', { type: 'button', 'class': 'button button-secondary lm-modal-cancel' }).text(LoterieManager.i18n.cancel);
        var confirmBtn = $('<button>', { type: 'submit', 'class': 'button button-primary' }).text(LoterieManager.i18n.confirm);
        actions.append(cancelBtn).append(confirmBtn);
        form.append(actions);

        modal.append(form);
        overlay.append(modal);

        return overlay;
    }

    function openModal(lotteries, form, ticketLimit) {
        pendingForm = form;
        pendingForm.find('.lm-lottery-selection').val('');
        var modal = buildModal(lotteries);
        modal.find('.lm-lottery-form').attr('data-ticket-limit', ticketLimit > 0 ? ticketLimit : '');
        $('body').append(modal).addClass('lm-modal-open');
        modal.find('input[type="checkbox"]').first().focus();
    }

    $(document).on('click', '.single_add_to_cart_button', function (event) {
        if (bypassModal) {
            bypassModal = false;
            return true;
        }

        var $button = $(this);
        var $form = $button.closest('form.cart');
        var dataContainer = $form.find('.lm-lottery-data');

        if (!dataContainer.length) {
            return true;
        }

        var lotteries = normalizeLotteries(dataContainer.data('lotteries'));

        if (!lotteries.length) {
            return true;
        }

        dataContainer.data('lotteries', lotteries);

        if (lotteries.length === 1) {
            $form.find('.lm-lottery-selection').val(lotteries[0].id);
            return true;
        }

        event.preventDefault();
        var ticketLimit = parseTicketLimit(dataContainer.attr('data-ticket-limit'));

        openModal(lotteries, $form, ticketLimit);
        return false;
    });

    $(document).on('click', '.lm-modal-cancel', function () {
        closeModal();
        pendingForm = null;
    });

    $(document).on('change', '.lm-lottery-form input[name="lm_lottery_choice[]"]', function () {
        var $checkbox = $(this);
        var $form = $checkbox.closest('.lm-lottery-form');
        var ticketLimit = parseTicketLimit($form.attr('data-ticket-limit'));

        if (ticketLimit <= 0 || !$checkbox.is(':checked')) {
            return;
        }

        var selectedCount = $form.find('input[name="lm_lottery_choice[]"]:checked').length;
        if (selectedCount > ticketLimit) {
            $checkbox.prop('checked', false);
            alertTicketLimit(ticketLimit);
        }
    });

    $(document).on('submit', '.lm-lottery-form', function (event) {
        event.preventDefault();
        if (!pendingForm) {
            return;
        }

        var selected = [];
        $(this).find('input[name="lm_lottery_choice[]"]:checked').each(function () {
            selected.push($(this).val());
        });

        if (!selected.length) {
            window.alert(LoterieManager.i18n.select_loterie);
            return;
        }

        var ticketLimit = parseTicketLimit($(this).attr('data-ticket-limit'));
        if (ticketLimit > 0 && selected.length > ticketLimit) {
            alertTicketLimit(ticketLimit);
            return;
        }

        pendingForm.find('.lm-lottery-selection').val(selected.join(','));
        closeModal();
        bypassModal = true;
        pendingForm.submit();
        pendingForm = null;
    });

    $(document).on('keyup', function (event) {
        if (event.key === 'Escape') {
            closeModal();
            pendingForm = null;
        }
    });

    function initializeTicketBadges() {
        $('form.cart').each(function () {
            var $form = $(this);
            if (!$form.find('.lm-lottery-data').length) {
                return;
            }

            setTicketLimit($form, {});
        });
    }

    $(document).on('found_variation', 'form.variations_form', function (event, variation) {
        var resolution = resolveVariationTicketLimit($(this), variation);
        setTicketLimit($(this), {
            hasCustom: resolution.hasCustom,
            limit: resolution.limit
        });
    });

    $(document).on('reset_data', 'form.variations_form', function () {
        setTicketLimit($(this), {});
    });

    function initTiltCards() {
        var hoverMedia = window.matchMedia ? window.matchMedia('(hover: hover)') : null;
        if (hoverMedia && !hoverMedia.matches) {
            return;
        }

        var cards = document.querySelectorAll('.tilt-card');
        if (!cards.length) {
            return;
        }

        cards.forEach(function (card) {
            var target = { x: 0, y: 0 };
            var current = { x: 0, y: 0 };
            var raf = null;

            function animate() {
                current.x += (target.x - current.x) * 0.12;
                current.y += (target.y - current.y) * 0.12;

                card.style.transform = 'perspective(1000px) rotateY(' + current.x.toFixed(2) + 'deg) rotateX(' + current.y.toFixed(2) + 'deg) scale3d(1.04,1.04,1)';

                if (Math.abs(current.x - target.x) < 0.05 && Math.abs(current.y - target.y) < 0.05) {
                    current.x = target.x;
                    current.y = target.y;
                    if (target.x === 0 && target.y === 0) {
                        card.style.transform = 'perspective(1000px)';
                    }
                    raf = null;
                    return;
                }

                raf = window.requestAnimationFrame(animate);
            }

            function setTarget(normX, normY) {
                target.x = Math.max(-1, Math.min(1, normX)) * 18;
                target.y = Math.max(-1, Math.min(1, -normY)) * 18;
                if (!raf) {
                    raf = window.requestAnimationFrame(animate);
                }
            }

            card.addEventListener('mousemove', function (event) {
                var rect = card.getBoundingClientRect();
                var offsetX = event.clientX - rect.left;
                var offsetY = event.clientY - rect.top;
                var normX = (offsetX / rect.width) * 2 - 1;
                var normY = (offsetY / rect.height) * 2 - 1;
                setTarget(normX, normY);
            });

            card.addEventListener('mouseenter', function () {
                setTarget(0, 0);
                card.classList.add('flash');
                window.setTimeout(function () {
                    card.classList.remove('flash');
                }, 600);
            });

            card.addEventListener('mouseleave', function () {
                setTarget(0, 0);
            });
        });
    }

    function initFrontendFeatures() {
        initializeTicketBadges();
        initTiltCards();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFrontendFeatures);
    } else {
        initFrontendFeatures();
    }
})(jQuery);
