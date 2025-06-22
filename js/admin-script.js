jQuery(document).ready(function ($) {
    // UI-Elemente
    var $scanButton = $('#sawc-scan-start'), $scanProgressContainer = $('#sawc-scan-progress'), $scanProgressBar = $('#sawc-scan-progress-bar'), $scanProgressText = $('#sawc-scan-progress-text');
    var $resultsWrapper = $('#sawc-results-wrapper'), $bulkButton = $('#sawc-bulk-start'), $bulkProgressContainer = $('#sawc-bulk-progress'), $bulkProgressBar = $('#sawc-bulk-progress-bar'), $bulkProgressText = $('#sawc-bulk-progress-text');
    var $debugLogWrapper = $('#sawc-debug-log-wrapper'), $debugLog = $('#sawc-debug-log');
    var circleWebp = $('#sawc-donut-webp'), circleAvif = $('#sawc-donut-avif');
    var circumference = 2 * Math.PI * circleWebp.attr('r');
    var totalImagesInLibrary = 0, totalToConvertWebp = 0, totalToConvertAvif = 0;

    // SETUP: Sync range sliders with number inputs
    $('#sawc_quality_webp_range').on('input', function() { $('#sawc_quality_webp_number').val($(this).val()); });
    $('#sawc_quality_webp_number').on('input', function() { $('#sawc_quality_webp_range').val($(this).val()); });
    $('#sawc_quality_avif_range').on('input', function() { $('#sawc_quality_avif_number').val($(this).val()); });
    $('#sawc_quality_avif_number').on('input', function() { $('#sawc_quality_avif_range').val($(this).val()); });

    // Initial UI State
    function initializeUIState() {
        if (!sawc_ajax.avif_supported) {
            var $avifWrapper = circleAvif.closest('.sawc-donut-chart-wrapper');
            $avifWrapper.css('opacity', 0.5);
            $('#sawc-chart-percent-avif').text(sawc_ajax.text.not_available);
            circleAvif.css('stroke', '#e6e6e6');
            $('#sawc_quality_avif_range, #sawc_quality_avif_number').prop('disabled', true).closest('tr').css('opacity', 0.5);
        }
    }
    initializeUIState();

    // SCAN PROCESS
    $scanButton.on('click', function() {
        $scanButton.prop('disabled', true);
        $resultsWrapper.hide();
        $scanProgressBar.css('width', '0%').text('0%');
        $scanProgressContainer.show();
        $scanProgressText.text(sawc_ajax.text.preparing_scan);
        scanBatch(1);
    });

    function scanBatch(page) {
        $.ajax({
            url: sawc_ajax.ajax_url, type: 'POST', data: { action: sawc_ajax.scan_action, nonce: sawc_ajax.nonce, page: page },
            success: function(response) {
                if (!response.success) {
                    alert(sawc_ajax.text.scan_error + (response.data.message || sawc_ajax.text.unknown_error));
                    $scanButton.prop('disabled', false); $scanProgressContainer.hide(); return;
                }
                if (page === 1) {
                    totalImagesInLibrary = response.data.total_to_scan;
                    if (totalImagesInLibrary === 0) {
                        $scanProgressText.text(sawc_ajax.text.no_images_found);
                        updateStatusUI(0, 0, 0); $resultsWrapper.slideDown(); $scanButton.prop('disabled', false); return;
                    }
                    scanBatch(2); return;
                }
                var scanBatchSize = 50;
                var scannedImagesCount = (page - 2) * scanBatchSize;
                scannedImagesCount = Math.min(scannedImagesCount, totalImagesInLibrary);
                var percentage = totalImagesInLibrary > 0 ? (scannedImagesCount / totalImagesInLibrary) * 100 : 100;
                $scanProgressBar.css('width', percentage + '%').text(Math.round(percentage) + '%');
                $scanProgressText.text(sawc_ajax.text.scanning + ' ' + scannedImagesCount + ' / ' + totalImagesInLibrary);
                if (response.data.done) {
                    $scanProgressContainer.hide();
                    $scanProgressText.text(sawc_ajax.text.scan_complete);
                    updateStatusUI(response.data.total_images, response.data.unconverted_webp, response.data.unconverted_avif);
                    $resultsWrapper.slideDown(); $scanButton.prop('disabled', false);
                } else { scanBatch(page + 1); }
            },
            error: function() {
                alert(sawc_ajax.text.critical_error); $scanButton.prop('disabled', false); $scanProgressContainer.hide();
            }
        });
    }

    // BULK CONVERSION PROCESS
    $bulkButton.on('click', function () {
        $bulkButton.prop('disabled', true); $scanButton.prop('disabled', true);
        $bulkProgressText.text(sawc_ajax.text.preparing_conversion);
        $bulkProgressBar.css('width', '0%').text('0%');
        $bulkProgressContainer.show();
        if (sawc_ajax.debug_mode) { $debugLog.empty(); $debugLogWrapper.show(); }
        processConvertBatch(0);
    });

    function processConvertBatch(totalConvertedInRun) {
        var totalToConvert = Math.max(totalToConvertWebp, sawc_ajax.avif_supported ? totalToConvertAvif : 0);
        $.ajax({
            url: sawc_ajax.ajax_url, type: 'POST', data: { action: sawc_ajax.process_action, nonce: sawc_ajax.nonce },
            success: function (response) {
                if (response.success) {
                    if (sawc_ajax.debug_mode && response.data.log) { $debugLog.prepend(response.data.log.replace(/\n/g, '<br>') + '<br>'); }
                    totalConvertedInRun += response.data.processed_in_batch;
                    var percentage = totalToConvert > 0 ? (totalConvertedInRun / totalToConvert) * 100 : 100;
                    $bulkProgressBar.css('width', percentage + '%').text(Math.round(percentage) + '%');
                    $bulkProgressText.text(sawc_ajax.text.converting + ' ' + totalConvertedInRun + ' / ' + totalToConvert);
                    if (response.data.done) {
                        $bulkProgressText.text(sawc_ajax.text.finishing_up);
                        setTimeout(function() { $bulkProgressContainer.hide(); $scanButton.prop('disabled', false).click(); }, 1500);
                    } else { processConvertBatch(totalConvertedInRun); }
                } else {
                    alert(sawc_ajax.text.conversion_error + (response.data.message || sawc_ajax.text.unknown_error));
                    $scanButton.prop('disabled', false); $bulkButton.prop('disabled', false);
                }
            },
            error: function () {
                alert(sawc_ajax.text.critical_error); $scanButton.prop('disabled', false); $bulkButton.prop('disabled', false);
            }
        });
    }

    // UI HELPER
    function updateStatusUI(total, unconvertedWebp, unconvertedAvif) {
        totalImagesInLibrary = total;
        totalToConvertWebp = unconvertedWebp;
        totalToConvertAvif = unconvertedAvif;

        var stats = [
            { format: 'webp', circle: circleWebp, percentEl: $('#sawc-chart-percent-webp'), unconverted: unconvertedWebp, completeClass: 'is-complete' },
            { format: 'avif', circle: circleAvif, percentEl: $('#sawc-chart-percent-avif'), unconverted: unconvertedAvif, completeClass: 'is-complete' }
        ];

        stats.forEach(function(s) {
            var $wrapper = s.circle.closest('.sawc-donut-chart-wrapper');
            if (s.format === 'avif' && !sawc_ajax.avif_supported) {
                s.percentEl.text(sawc_ajax.text.not_available);
                s.circle.css({ 'stroke-dashoffset': circumference, 'stroke': '#e6e6e6' }).removeClass(s.completeClass);
                $wrapper.css('opacity', 0.5);
                return; // Nächste Iteration
            }
            
            $wrapper.css('opacity', 1);
            var converted = total - s.unconverted;
            var percentage = total > 0 ? (converted / total) * 100 : 100;
            var offset = circumference - (percentage / 100) * circumference;
            s.circle.css('stroke-dashoffset', offset);
            s.percentEl.text(Math.round(percentage) + '%');
            
            if (s.unconverted === 0 && total > 0) {
                s.circle.addClass(s.completeClass);
                s.circle.css('stroke', ''); // remove inline style to use class style
            } else {
                s.circle.removeClass(s.completeClass);
                s.circle.css('stroke', ''); // remove inline style to use class style
            }
        });

        if (totalToConvertWebp > 0 || (sawc_ajax.avif_supported && totalToConvertAvif > 0)) {
            $bulkButton.prop('disabled', false);
        } else {
            $bulkButton.prop('disabled', true);
        }
    }
});