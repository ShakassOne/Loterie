(function ($) {
    'use strict';

    var pendingForm = null;
    var bypassModal = false;

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

    function openModal(lotteries, form) {
        pendingForm = form;
        pendingForm.find('.lm-lottery-selection').val('');
        var modal = buildModal(lotteries);
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
        openModal(lotteries, $form);
        return false;
    });

    $(document).on('click', '.lm-modal-cancel', function () {
        closeModal();
        pendingForm = null;
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
