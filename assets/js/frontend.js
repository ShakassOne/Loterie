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
            description.append($('<strong>').text(lottery.title));
            if (lottery.prize) {
                description.append($('<div>').text(lottery.prize));
            }

            if (lottery.capacity) {
                var soldText = lottery.sold ? lottery.sold : 0;
                description.append($('<small>').text(soldText + ' / ' + lottery.capacity));
            }

            if (lottery.end_date) {
                description.append($('<small>').text(lottery.end_date));
            }

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

        var lotteries = dataContainer.data('lotteries');
        if (!lotteries || !lotteries.length) {
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
})(jQuery);
