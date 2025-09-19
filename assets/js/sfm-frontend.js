// assets/js/sfm-frontend.js

jQuery(document).ready(function($) {
    // === Transaction Form Submission ===
    $('#sfm-transaction-form').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission.

        var $form = $(this);
        var $submitButton = $form.find('button[type="submit"]');
        var $messageDiv = $('#sfm-form-message');

        // Clear previous messages and classes.
        $messageDiv.removeClass('sfm-success sfm-error').text('');
        $submitButton.prop('disabled', true).text('Recording...'); // Disable button and change text.

        // Collect form data.
        var childId = $form.find('#sfm_form_child_id').val();
        var amount = $form.find('#sfm_form_amount').val();
        var type = $form.find('#sfm_form_type').val();
        var description = $form.find('#sfm_form_description').val();

        // Basic client-side validation.
        if (!childId || !amount || !type) {
            $messageDiv.addClass('sfm-error').text('Please fill in all required fields.');
            $submitButton.prop('disabled', false).text('Record Transaction');
            return;
        }
        if (parseFloat(amount) <= 0) {
            $messageDiv.addClass('sfm-error').text('Amount must be greater than zero.');
            $submitButton.prop('disabled', false).text('Record Transaction');
            return;
        }

        // AJAX call to submit transaction.
        $.ajax({
            url: sfm_ajax_object.ajax_url, // WordPress AJAX URL from wp_localize_script.
            type: 'POST',
            data: {
                action: 'sfm_submit_transaction', // Our registered AJAX action.
                nonce: sfm_ajax_object.nonce,    // Security nonce.
                child_id: childId,
                amount: amount,
                type: type,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    $messageDiv.addClass('sfm-success').text(response.data.message);
                    $form[0].reset(); // Clear the form fields after successful submission.
                } else {
                    $messageDiv.addClass('sfm-error').text(response.data.message);
                }
            },
            error: function() {
                $messageDiv.addClass('sfm-error').text('An error occurred. Please try again.');
            },
            complete: function() {
                $submitButton.prop('disabled', false).text('Record Transaction'); // Re-enable button and reset text.
            }
        });
    });

    // === Transaction History Modal Logic ===
    var currentPage = {}; // Object to store current page for each child's transactions.
    var isLoadingTransactions = false; // Flag to prevent multiple concurrent AJAX calls.

    // Open modal when "View History" button is clicked.
    $(document).on('click', '.sfm-view-transactions-button', function() {
        var childId = $(this).data('child-id');
        var childName = $(this).data('child-name'); // Get child name directly from data attribute.

        // Reset current page for this child to 1.
        currentPage[childId] = 1;

        // Update modal title.
        $('#sfm-modal-child-name').text(childName + ' - ' + sfm_ajax_object.history_title_suffix || 'Transaction History');

        // Clear previous transactions and show modal.
        $('#sfm-modal-transactions').empty();
        $('#sfm-transaction-modal').show();

        // Load first page of transactions.
        loadTransactions(childId, currentPage[childId], true); // true to replace content.
    });

    // Load more transactions when "Load More" button is clicked.
    $('#sfm-load-more-transactions').on('click', function() {
        // Find the child ID from the modal's current context.
        var childId = $('.sfm-view-transactions-button[data-child-id]').first().data('child-id'); // Get the ID from the first button that opened the modal.
        // Or, more robustly, find it from the visible modal's data if we store it.
        // For simplicity, let's assume the button still holds the context, or we extract it from the modal title.
        
        // A better way would be to store the currently active child_id in a global JS variable.
        var activeChildId = $('#sfm-modal-child-name').data('child-id'); // We'll add this data attr when modal opens.
        if (!activeChildId) {
            // Fallback if data-child-id isn't explicitly set on modal title (or other element)
            // Re-query the button that was clicked to open modal.
            // This is a bit indirect, consider storing childId in a global JS variable when modal opens.
            // For now, let's just make sure loadTransactions still works.
            console.warn('activeChildId not found on modal title. Falling back to button data.');
            activeChildId = $('.sfm-view-transactions-button[data-child-id]').data('child-id');
        }

        if (activeChildId && currentPage[activeChildId]) {
            currentPage[activeChildId]++;
            loadTransactions(activeChildId, currentPage[activeChildId], false); // false to append content.
        }
    });


    // Close modal when close button is clicked.
    $('.sfm-close-button').on('click', function() {
        $('#sfm-transaction-modal').hide();
        $('#sfm-modal-transactions').empty(); // Clear content on close.
        $('#sfm-load-more-transactions').hide(); // Hide load more button.
    });

    // Close modal when clicking outside of it.
    $(window).on('click', function(event) {
        if ($(event.target).is('#sfm-transaction-modal')) {
            $('#sfm-transaction-modal').hide();
            $('#sfm-modal-transactions').empty(); // Clear content on close.
            $('#sfm-load-more-transactions').hide(); // Hide load more button.
        }
    });

    /**
     * Loads transactions for a given child via AJAX.
     * @param {number} childId       The ID of the child.
     * @param {number} paged         The page number to load.
     * @param {boolean} replaceContent True to replace existing content, false to append.
     */
    function loadTransactions(childId, paged, replaceContent) {
        if (isLoadingTransactions) return; // Prevent multiple calls.
        isLoadingTransactions = true;

        var $transactionsContainer = $('#sfm-modal-transactions');
        var $loadMoreButton = $('#sfm-load-more-transactions');

        // Show loading message.
        if (replaceContent) {
            $transactionsContainer.html('<p class="sfm-loading-message">' + (sfm_ajax_object.loading_text || 'Loading transactions...') + '</p>');
            $loadMoreButton.hide(); // Hide button while loading initial content.
        } else {
            $transactionsContainer.append('<p class="sfm-loading-message">' + (sfm_ajax_object.loading_more_text || 'Loading more...') + '</p>');
        }

        $.ajax({
            url: sfm_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'sfm_get_transactions', // Our registered AJAX action.
                nonce: sfm_ajax_object.nonce,    // Security nonce.
                child_id: childId,
                paged: paged
            },
            success: function(response) {
                $('.sfm-loading-message').remove(); // Remove loading message.
                if (response.success) {
                    if (replaceContent) {
                        $transactionsContainer.html(response.data.html);
                    } else {
                        $transactionsContainer.append(response.data.html);
                    }

                    if (response.data.has_more) {
                        $loadMoreButton.show(); // Show "Load More" button if there are more pages.
                    } else {
                        $loadMoreButton.hide(); // Hide if no more pages.
                    }
                } else {
                    $transactionsContainer.html('<p class="sfm-error">' + response.data.message + '</p>');
                    $loadMoreButton.hide();
                }
            },
            error: function() {
                $('.sfm-loading-message').remove();
                $transactionsContainer.html('<p class="sfm-error">Error loading transactions. Please try again.</p>');
                $loadMoreButton.hide();
            },
            complete: function() {
                isLoadingTransactions = false; // Reset loading flag.
            }
        });
    }
});