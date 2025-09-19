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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTiltCards);
    } else {
        initTiltCards();
    }
})(jQuery);
