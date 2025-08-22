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

        // GST calculation helpers
        function fmtNumber(n) {
            if ( n === '' || n === null || typeof n === 'undefined' || isNaN(n) ) {
                return '';
            }
            return parseFloat(n).toFixed(2);
        }

        function recalc_gst() {
            var $basic = $('input[name="_invoice_basic_amount"]');
            var $gstRange = $('input[name="_invoice_gst_percent"]');
            var $gstDisplay = $('#ims_gst_amount');
            var $gstHidden = $('#ims_gst_amount_hidden');
            var $totalDisplay = $('#ims_total_amount');
            var $totalHidden = $('#ims_total_amount_hidden');

            if ( !$basic.length || !$gstRange.length || !$gstDisplay.length || !$gstHidden.length || !$totalDisplay.length || !$totalHidden.length ) {
                // nothing to do if fields aren't present
                return;
            }

            var basicVal = parseFloat( $basic.val() );
            if ( isNaN(basicVal) ) basicVal = 0;

            var gstPct = parseFloat( $gstRange.val() );
            if ( isNaN(gstPct) ) gstPct = 0;

            // clamp percent defensively 0..30
            if ( gstPct < 0 ) gstPct = 0;
            if ( gstPct > 30 ) gstPct = 30;

            var gstAmt = ( basicVal * gstPct ) / 100;
            gstAmt = Math.round( gstAmt * 100 ) / 100;

            var totalAmt = basicVal + gstAmt;
            totalAmt = Math.round( totalAmt * 100 ) / 100;

            $gstDisplay.val( fmtNumber(gstAmt) );
            $gstHidden.val( fmtNumber(gstAmt) );

            $totalDisplay.val( fmtNumber(totalAmt) );
            $totalHidden.val( fmtNumber(totalAmt) );

            // update net after totals change
            calcNetPayable();
        }


        // run on load
        recalc_gst();

        // when basic amount changes (typing / blur)
        $(document).on('input change', 'input[name="_invoice_basic_amount"]', function() {
            recalc_gst();
        });

        // when GST range changes (drag or change)
        $(document).on('input change', 'input[name="_invoice_gst_percent"]', function() {
            // inline oninput in markup may update the percent span already;
            // we still trigger recalculation to update GST amount fields.
            recalc_gst();
        });

        // If the Invoice edit form is dynamically reloaded or new fields injected,
        // you can call recalc_gst() from other scripts after injection.

        // Names of fields to include in the deduction total
        var fields = [
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

        function calcTotalDeductions() {
            var total = 0;
            fields.forEach(function(name){
                var el = $('[name="' + name + '"]');
                if ( el.length ) {
                    // parseFloat of empty string => NaN, so coerce
                    var v = parseFloat( el.val() );
                    if ( !isNaN(v) ) {
                        total += v;
                    }
                }
            });
            // round to 2 decimals
            total = Math.round( total * 100 ) / 100;
            $('#ims_total_deduction').val( total );
            $('#ims_total_deduction_hidden').val( total );

            // update net after deductions change
            calcNetPayable();
        }

        // Attach event listeners (delegate to capture dynamically added inputs if any)
        fields.forEach(function(name){
            $(document).on('input change', '[name="' + name + '"]', calcTotalDeductions);
        });

        
        function calcNetPayable() {
            // Prefer the hidden (submitted) fields, fall back to visible ones
            var totalAmt = parseFloat( $('#ims_total_amount_hidden').val() || $('#ims_total_amount').val() );
            var totalDed = parseFloat( $('#ims_total_deduction_hidden').val() || $('#ims_total_deduction').val() );

            if ( isNaN(totalAmt) ) totalAmt = 0;
            if ( isNaN(totalDed) ) totalDed = 0;

            var net = totalAmt - totalDed;
            // round to 2 decimals
            net = Math.round( net * 100 ) / 100;

            $('#ims_net_payable').val( net.toFixed(2) );
            $('#ims_net_payable_hidden').val( net.toFixed(2) );

            // compute balance = net - amountPaid (amount paid input can be empty)
            var paid = parseFloat( $('[name="_invoice_amount_paid"]').val() );
            if ( isNaN(paid) ) paid = 0;

            var balance = net - paid;
            balance = Math.round( balance * 100 ) / 100;

            // update balance fields (visible readonly + hidden)
            if ( $('#ims_balance').length ) {
                $('#ims_balance').val( balance.toFixed(2) );
            }
            if ( $('#ims_balance_hidden').length ) {
                $('#ims_balance_hidden').val( balance.toFixed(2) );
            }
        }

        // When amount paid changes, recalc balance
        $(document).on('input change', 'input[name="_invoice_amount_paid"]', function() {
            // update both server-visible and visible values
            // (saving to hidden field will happen on form submit; here we update hidden field for correctness)
            var v = parseFloat( $(this).val() );
            if ( isNaN(v) ) v = '';
            $('#ims_balance_hidden').val( $('#ims_balance_hidden').length ? $('#ims_balance_hidden').val() : '' ); // noop to ensure existence
            calcNetPayable();
        });
        
        // initial calc when page loads
        calcTotalDeductions();
        recalc_gst();
        // ensure net computed after both
        calcNetPayable();

    });
})(jQuery);



/* admin/js/ims-admin.js
   Lightweight UI enhancements for the Invoice metaboxes.
   - File preview for uploaded file inputs
   - Better balance live update when net/paid change
   - Small improvements for range display
*/

(function($){
  $(function(){
    // --- File preview helper ---
    var $fileInput = $('input[name="_invoice_file"]');
    if ( $fileInput.length ) {
      $fileInput.each(function(){
        var $f = $(this);
        // Create preview container if not present
        if ( $f.next('.ims-file-preview').length === 0 ) {
          $f.after('<div class="ims-file-preview" aria-hidden="false" style="display:none;"></div>');
        }
        var $preview = $f.next('.ims-file-preview');

        // If there's already a View Current File link rendered server-side, attach it to preview
        // We expect markup: a link to attachment (we placed it after input in PHP earlier)
        var $anchor = $f.parent().find('a[target="_blank"]').first();
        if ( $anchor && $anchor.length ) {
          var href = $anchor.attr('href');
          var text = $anchor.text() || href.split('/').pop();
          renderPreview($preview, href, text);
          $preview.show();
        }

        // When user selects a new file, show preview using object URL
        $f.on('change', function(ev){
          var file = this.files && this.files[0];
          if ( ! file ) return;
          var url = URL.createObjectURL(file);
          var name = file.name || url.split('/').pop();
          renderPreview($preview, url, name, file.type);
          $preview.show();
        });

      });
    }

    function renderPreview($preview, url, filename, mime){
      // Clear
      $preview.empty();
      mime = (mime || '').toLowerCase();

      // If image show img, if pdf show small iframe/embed, else show link
      if ( mime.indexOf('image/') === 0 || /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(filename) ) {
        var $img = $('<img alt="Invoice attachment preview"/>').attr('src', url);
        var $meta = $('<div class="ims-file-meta"></div>');
        $meta.append( $('<div/>').text(filename) );
        $meta.append( $('<div/>').html('<a target="_blank" rel="noopener noreferrer">Open file</a>').attr('href', url) );
        $preview.append($img).append($meta);
        return;
      }

      if ( mime === 'application/pdf' || /\.pdf$/i.test(filename) ) {
        // small preview: embedded pdf if same-origin or blob URL
        var $img = $('<img alt="PDF icon" src="' + IMS_URL + 'admin/img/pdf-icon.png" onerror="this.style.display=\'none\'"/>');
        var $meta = $('<div class="ims-file-meta"></div>');
        $meta.append( $('<div/>').text(filename) );
        $meta.append( $('<div/>').html('<a target="_blank" rel="noopener noreferrer">Open PDF</a>').attr('href', url) );
        $preview.append($img).append($meta);
        return;
      }

      // fallback: show filename + link
      var $meta = $('<div class="ims-file-meta"></div>');
      $meta.append( $('<div/>').text(filename) );
      $meta.append( $('<div/>').html('<a target="_blank" rel="noopener noreferrer">Download / Open</a>').attr('href', url) );
      $preview.append($meta);
    }

    // --- Balance live update ---
    function updateBalance() {
      var net = parseFloat( $('#ims_net_payable_hidden').val() || $('#ims_net_payable').val() || 0 );
      var paid = parseFloat( $('input[name="_invoice_amount_paid"]').val() || 0 );
      if ( isNaN(net) ) net = 0;
      if ( isNaN(paid) ) paid = 0;
      var bal = Math.round( (net - paid) * 100 ) / 100;
      // update both visible and hidden
      var formatted = ( (isNaN(bal)) ? '0.00' : bal.toFixed(2) );
      $('#ims_balance').val( formatted );
      $('#ims_balance_hidden').val( formatted );
    }

    // Bind to changes: amount paid input and when net hidden changes
    $(document).on('input change', 'input[name="_invoice_amount_paid"]', updateBalance);

    // If other scripts update the hidden net value, observe it
    var netHidden = document.getElementById('ims_net_payable_hidden');
    if ( netHidden ) {
      var mo = new MutationObserver(function(){ updateBalance(); });
      mo.observe(netHidden, { attributes: true, childList: true, subtree: true, characterData: true });
    }

    // run once
    updateBalance();

    // --- range styling: wrap range and value into a flex row for nicer layout ---
    $('input[type="range"][name="_invoice_milestone"], input[type="range"][name="_invoice_gst_percent"]').each(function(){
      var $r = $(this);
      if ( $r.parent().hasClass('ims-range-wrap') ) return;
      var valSpanId = $r.attr('id') ? $r.attr('id').replace(/range/i,'val') : ('ims_range_val_' + Math.floor(Math.random()*10000));
      var $wrap = $('<div class="ims-range-wrap" />');
      var $val = $('<div class="ims-range-value" id="'+valSpanId+'">').text( $r.val() + '%' );
      $r.after($val);
      $r.wrap($wrap);
      // bind update
      $r.on('input change', function(){
        var v = $r.val();
        $val.text( v + '%');
      });
    });

  });
})(jQuery);