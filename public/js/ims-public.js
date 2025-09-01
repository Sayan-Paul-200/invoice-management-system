(function($){
    $(function(){
        var $form = $('#ims-invoice-form');
        if (!$form.length) return;

        // init flatpickr on date fields
        function initDatepickers(){
            if ( typeof flatpickr === 'undefined' ) return;
            var inv = flatpickr('#_invoice_date', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                onChange: function(selectedDates, dateStr){
                    // update min for submission
                    try {
                        sub.set('minDate', dateStr || null);
                        // adjust submission if needed
                        var sVal = $('#_submission_date').val();
                        if ( sVal && dateStr && new Date(sVal) < new Date(dateStr) ) {
                            $('#_submission_date').val(dateStr);
                            sub.set('minDate', dateStr);
                        }
                    } catch(e){}
                }
            });
            var sub = flatpickr('#_submission_date', {
                dateFormat: 'Y-m-d',
                allowInput: true,
                onChange: function(selectedDates, dateStr){
                    try {
                        pay.set('minDate', dateStr || null);
                        var pVal = $('#_payment_date').val();
                        if ( pVal && dateStr && new Date(pVal) < new Date(dateStr) ) {
                            $('#_payment_date').val(dateStr);
                            pay.set('minDate', dateStr);
                        }
                    } catch(e){}
                }
            });
            var pay = flatpickr('#_payment_date', {
                dateFormat: 'Y-m-d',
                allowInput: true
            });
        }

        // numeric helpers
        function parseNumInput(selector){
            var v = $(selector).val();
            if (v === '' || v === null || typeof v === 'undefined') return 0;
            v = String(v).replace(/[, ]+/g,'');
            var n = parseFloat(v);
            if (isNaN(n)) return 0;
            return n;
        }

        function fmt(n){
            return (Math.round(n*100)/100).toFixed(2);
        }

        function recalcGST(){
            var basic = parseNumInput('#_invoice_basic_amount');
            var pct = parseFloat($('#_invoice_gst_percent').val()) || 0;
            pct = Math.max(0, Math.min(30, pct));
            var gst = Math.round((basic * pct / 100) * 100) / 100;
            var total = Math.round((basic + gst) * 100) / 100;
            $('#_invoice_gst_amount').val(fmt(gst));
            $('#_invoice_total_amount').val(fmt(total));
            calcTotals();
        }

        function calcTotals(){
            var ded_keys = [
                '#_invoice_retention_amount',
                '#_invoice_gst_withheld',
                '#_invoice_tds_amount',
                '#_invoice_gst_tds_amount',
                '#_invoice_bocw_amount',
                '#_invoice_low_depth_deduction_amount',
                '#_invoice_liquidated_damages_amount',
                '#_invoice_sla_penalty_amount',
                '#_invoice_penalty_amount',
                '#_invoice_other_deduction_amount'
            ];
            var totalDed = 0;
            ded_keys.forEach(function(sel){
                totalDed += parseNumInput(sel);
            });
            $('#_invoice_total_deduction_amount').val(fmt(totalDed));

            var totalAmt = parseNumInput('#_invoice_total_amount');
            var net = Math.round((totalAmt - totalDed) * 100) / 100;
            $('#_invoice_net_payable_amount').val(fmt(net));

            var paid = parseNumInput('#_invoice_amount_paid');
            var balance = Math.round((net - paid) * 100) / 100;
            $('#_invoice_balance_amount').val(fmt(balance));
        }

        // toggle payment_date visibility: keep column space by using visibility
        function showHidePayment(show){
            var $wrap = $('#payment-wrapper');
            if ( ! $wrap.length ) return;
            if ( show ) {
                $wrap.css('visibility', 'visible');
            } else {
                $wrap.css('visibility', 'hidden');
                // optional: clear payment value when hiding:
                // $('#_payment_date').val('');
            }
        }

        // Bind events
        $form.on('input change', '#_invoice_basic_amount, #_invoice_gst_percent', recalcGST);
        $form.on('input change', '#_invoice_amount_paid', calcTotals);
        // also recalc when any deduction input changes
        $form.on('input change', 'input[id^="_invoice_"], select[id^="_invoice_"]', function(){
            // only run calc for relevant fields to avoid overwork
            calcTotals();
        });

        // status toggle: show payment_date when status == paid
        $form.on('change', '#_invoice_status', function(){
            var v = $(this).val();
            if ( v === 'paid' ) {
                showHidePayment(true);
                $('#_payment_date').prop('required', true);
            } else {
                showHidePayment(false);
                $('#_payment_date').prop('required', false);
            }
        });

        // CC repeater add/remove
        $('#ims-add-cc').on('click', function(e){
            e.preventDefault();
            var newField = $('<p><input type="email" name="_cc_emails[]" value="" /> <button class="ims-btn secondary ims-remove-cc" type="button">remove</button></p>');
            $('#ims-cc-list').append(newField);
        });
        $('#ims-cc-list').on('click', '.ims-remove-cc', function(e){
            e.preventDefault();
            $(this).closest('p').remove();
        });

        // File preview & size check
        $('#_invoice_file_id').on('change', function(){
            var f = this.files && this.files[0];
            if (!f) return;
            var maxSize = (typeof imsPublic !== 'undefined' && imsPublic.max_file_size) ? imsPublic.max_file_size : 5242880;
            if ( f.size > maxSize ) {
                alert((typeof imsPublic !== 'undefined' && imsPublic.i18n && imsPublic.i18n.file_too_large) ? imsPublic.i18n.file_too_large : 'File is too large');
                $(this).val('');
                return;
            }
        });

        // init
        initDatepickers();

        // --- set initial 0.00 on certain amount fields and bind focus/blur handlers ---
        // Place this AFTER initDatepickers() and BEFORE recalcGST()/calcTotals() calls.
        var zeroFields = [
            '_invoice_client_amount',
            '_invoice_retention_amount',
            '_invoice_gst_withheld',
            '_invoice_tds_amount',
            '_invoice_gst_tds_amount',
            '_invoice_bocw_amount',
            '_invoice_low_depth_deduction_amount',
            '_invoice_liquidated_damages_amount',
            '_invoice_sla_penalty_amount',
            '_invoice_penalty_amount',
            '_invoice_other_deduction_amount'
        ];

        zeroFields.forEach(function(id){
            var $f = $('#' + id);
            if (!$f.length) return;

            // If field empty (or blank string) set default '0.00'
            if ( $f.val() === '' || $f.val() === null ) {
                $f.val('0.00');
            }

            // Clear default on focus (only if it is the placeholder default)
            $f.on('focus', function(){
                if ( $f.val() === '0.00' ) {
                    $f.val('');
                }
            });

            // If left empty, restore default on blur and trigger recalculation
            $f.on('blur', function(){
                if ( $f.val() === '' ) {
                    $f.val('0.00');
                }
                // ensure totals update after user leaves a field
                calcTotals();
            });
        });

        // ensure initial calculations
        recalcGST();
        calcTotals();

        // initial payment_date visibility based on status value
        var initialStatus = $('#_invoice_status').val();
        showHidePayment( initialStatus === 'paid' );

        // when invoice_date changes (plain input fallback), update submission min
        $form.on('input change', '#_invoice_date', function(){
            try {
                var inv = $(this).val();
                $('#_submission_date').attr('min', inv || null);
            } catch(e){}
        });

        // when submission_date changes enforce min on payment_date
        $form.on('input change', '#_submission_date', function(){
            try {
                var sub = $(this).val();
                $('#_payment_date').attr('min', sub || null);
            } catch(e){}
        });

    });
})(jQuery);
