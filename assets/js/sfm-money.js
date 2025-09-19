/**
 * Frontend JavaScript for Strategicli Family Money plugin.
 * Handles transaction form submission, modal display, and allowance updates.
 *
 * @package StrategicliFamilyMoney
 */

jQuery(document).ready(function($) {
    // Ensure sfm_ajax_object is defined by wp_localize_script.
    if (typeof sfm_ajax_object === 'undefined') {
        console.error('sfm_ajax_object is not defined. Localization failed.');
        return;
    }

    const ajaxurl = sfm_ajax_object.ajax_url;
    const addTransactionNonce = sfm_ajax_object.add_transaction_nonce;
    const getTransactionsNonce = sfm_ajax_object.get_transactions_nonce;
    const getBalanceNonce = sfm_ajax_object.get_balance_nonce;

    // --- Element Selectors ---
    const transactionForm = $('#sfm-transaction-form');
    const formMessage = $('#sfm-form-message');
    const refreshButton = $('#sfm-refresh-balances');
    const historyModal = $('#sfm-transaction-history-modal');
    const confirmationModal = $('#sfm-confirmation-modal');

    // --- Transaction Form Submission ---
    if (transactionForm.length) {
        transactionForm.on('submit', function(e) {
            e.preventDefault();

            const childId = $('#sfm-child-select').val();
            const amount = $('#sfm-amount-input').val();
            const reason = $('#sfm-reason-input').val();

            if (!childId || !amount || !reason.trim()) {
                displayMessage(formMessage, 'Please fill out all fields.', 'error');
                return;
            }

            transactionForm.find('button[type="submit"]').prop('disabled', true).text('Adding...');
            formMessage.stop(true, true).fadeOut();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sfm_add_transaction',
                    nonce: addTransactionNonce,
                    child_id: childId,
                    amount: amount,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        transactionForm[0].reset();
                        refreshBalances();
                        showConfirmationModal(response.data.message);
                    } else {
                        displayMessage(formMessage, response.data.message || 'An unknown error occurred.', 'error');
                    }
                },
                error: function() {
                    displayMessage(formMessage, 'A network error occurred. Please try again.', 'error');
                },
                complete: function() {
                    transactionForm.find('button[type="submit"]').prop('disabled', false).text('Add Transaction');
                }
            });
        });
    }

    function displayMessage(element, message, type) {
        element.removeClass('sfm-success sfm-error sfm-info').addClass('sfm-' + type).html(message).stop(true, true).fadeIn();
        if (type !== 'error') {
            setTimeout(function() {
                element.fadeOut();
            }, 5000);
        }
    }

    // --- Confirmation Modal ---
    function showConfirmationModal(message) {
        $('#sfm-confirmation-message').text(message);
        confirmationModal.css('display', 'flex').hide().fadeIn(300);
        setTimeout(closeConfirmationModal, 3500);
    }

    function closeConfirmationModal() {
        confirmationModal.fadeOut(300);
    }

    confirmationModal.on('click', '.sfm-modal-close', closeConfirmationModal);
    $(window).on('click', function(e) {
        if ($(e.target).is(confirmationModal)) {
            closeConfirmationModal();
        }
    });

    // --- Balance Refresh ---
    if (refreshButton.length) {
        refreshButton.on('click', refreshBalances);
    }

    function refreshBalances() {
        if (refreshButton.hasClass('sfm-is-refreshing')) return;
        refreshButton.prop('disabled', true).addClass('sfm-is-refreshing');

        const balanceSpans = $('.sfm-child-balance');
        balanceSpans.each(function() { $(this).text('...'); });

        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: { action: 'sfm_get_all_balances', nonce: getBalanceNonce },
            success: function(response) {
                if (response.success && response.data.balances) {
                    $.each(response.data.balances, function(childId, newBalance) {
                        const targetSpan = $(`.sfm-child-balance[data-child-id="${childId}"]`);
                        if (targetSpan.length) {
                            targetSpan.text(newBalance);
                            const isNegative = parseFloat(newBalance.replace(/[$,]/g, '')) < 0;
                            targetSpan.toggleClass('sfm-negative-balance', isNegative);
                        }
                    });
                }
            },
            error: function() {
                console.error('Failed to refresh balances.');
            },
            complete: function() {
                refreshButton.prop('disabled', false).removeClass('sfm-is-refreshing');
            }
        });
    }

    // --- Transaction History Modal ---
    const transactionList = $('#sfm-modal-transactions');
    let currentChildId = null;
    let currentPage = 1;
    let totalPages = 1;

    $('.sfm-allowance-display-wrapper').on('click', '.sfm-child-balance', function() {
        currentChildId = $(this).data('child-id');
        const childName = $(this).data('child-name');
        currentPage = 1;

        $('#sfm-modal-title').text(`Transactions for ${childName}`);
        loadTransactions(currentChildId, currentPage);
        historyModal.css('display', 'flex').hide().fadeIn(300);
        $('body').addClass('sfm-modal-open');
    });

    function closeHistoryModal() {
        historyModal.fadeOut(300, function() {
            $('body').removeClass('sfm-modal-open');
            transactionList.empty();
        });
    }

    historyModal.on('click', '.sfm-modal-close', closeHistoryModal);
    $(window).on('click', function(e) {
        if ($(e.target).is(historyModal)) {
            closeHistoryModal();
        }
    });

    $('#sfm-modal-prev').on('click', function() {
        if (currentPage > 1) {
            loadTransactions(currentChildId, --currentPage);
        }
    });

    $('#sfm-modal-next').on('click', function() {
        if (currentPage < totalPages) {
            loadTransactions(currentChildId, ++currentPage);
        }
    });

    function loadTransactions(childId, page) {
        transactionList.html(`<li class="sfm-loading-message">Loading...</li>`);
        updatePaginationButtons(false, false);

        $.ajax({
            url: ajaxurl,
            type: 'GET',
            data: {
                action: 'sfm_get_transactions',
                nonce: getTransactionsNonce,
                child_id: childId,
                page: page
            },
            success: function(response) {
                transactionList.empty();
                if (response.success && response.data.transactions.length > 0) {
                    totalPages = response.data.total_pages;
                    $.each(response.data.transactions, function(index, transaction) {
                        const amountClass = transaction.is_positive ? 'sfm-positive' : 'sfm-negative';
                        transactionList.append(
                            `<li class="sfm-transaction-item">
                                <span class="sfm-transaction-date">${transaction.date}</span>
                                <span class="sfm-transaction-reason">${transaction.reason}</span>
                                <span class="sfm-transaction-amount ${amountClass}">${transaction.amount}</span>
                            </li>`
                        );
                    });
                } else {
                    totalPages = 1;
                    transactionList.append(`<li class="sfm-no-transactions">${response.data.message || 'No transactions found.'}</li>`);
                }
                updatePaginationButtons(currentPage > 1, currentPage < totalPages);
            },
            error: function() {
                transactionList.html(`<li class="sfm-error-message">Error loading transactions.</li>`);
            }
        });
    }

    function updatePaginationButtons(enablePrev, enableNext) {
        $('#sfm-modal-prev').prop('disabled', !enablePrev);
        $('#sfm-modal-next').prop('disabled', !enableNext);
    }
});
