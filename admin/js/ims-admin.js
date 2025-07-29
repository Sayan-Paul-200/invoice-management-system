(function($) {
    $(document).ready(function() {
        // Show/hide the payment date when status changes
        $('#ims-status-select').on('change', function() {
            if ( $(this).val() === 'paid' ) {
                $('#ims-payment-date-wrapper').slideDown();
            } else {
                $('#ims-payment-date-wrapper').slideUp();
            }
        });

        // Initialize on load
        if ( $('#ims-status-select').val() === 'paid' ) {
            $('#ims-payment-date-wrapper').show();
        } else {
            $('#ims-payment-date-wrapper').hide();
        }

        // CC emails repeater
        $('#ims-cc-wrapper').on('click', '.ims-remove-cc', function(e) {
            e.preventDefault();
            $(this).closest('p').remove();
        });

        $('.ims-add-cc').on('click', function(e) {
            e.preventDefault();
            var newField = '<p><input type="email" name="_cc_emails[]" style="width:90%;" /> ' +
                           '<button class="button ims-remove-cc" type="button">&times;</button></p>';
            $('#ims-cc-wrapper').append(newField);
        });
    });
})(jQuery);
