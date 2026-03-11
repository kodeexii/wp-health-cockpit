jQuery(document).ready(function($) {
    
    // 1. Jalankan Audit Penuh
    $('#whc-run-full-audit').on('click', function() {
        const btn = $(this);
        const container = $('#whc-audit-results-container');
        
        btn.prop('disabled', true).addClass('updating-message');
        container.css('opacity', '0.5');
        
        $.post(whc_ajax_object.ajax_url, {
            action: 'whc_run_full_audit',
            nonce: whc_ajax_object.nonce
        }, function(r) {
            if (r.success) {
                location.reload(); // Refresh untuk tunjuk table baru
            } else {
                alert('Ralat: ' + r.data);
                btn.prop('disabled', false).removeClass('updating-message');
                container.css('opacity', '1');
            }
        });
    });

    // 2. Jalankan Frontend Audit (URL Dinamik)
    $(document).on('click', '.whc-run-frontend-audit', function() {
        const btn = $(this);
        const url = btn.data('url');
        
        btn.prop('disabled', true).text('Scanning...');
        
        $.post(whc_ajax_object.ajax_url, {
            action: 'run_frontend_audit',
            url: url,
            nonce: whc_ajax_object.nonce
        }, function(r) {
            if (r.success) {
                alert('TTFB: ' + r.data.ttfb + '\nSize: ' + r.data.size);
                btn.prop('disabled', false).text('Scan Again');
            }
        });
    });

    // 3. Purge Orphaned Multisite Tables (New!)
    $(document).on('click', '.whc-purge-orphaned-ms', function() {
        const btn = $(this);
        const siteData = btn.data('sites'); // Objek JSON dari PHP
        
        let confirmMsg = "AMARAN: Anda bakal memadam jadual milik sub-site yang sudah tiada dalam rekod network secara KEKAL!\n\n";
        confirmMsg += "Senarai jadual yang akan dibuang:\n";
        
        Object.keys(siteData).forEach(siteId => {
            confirmMsg += "\n--- SITE ID " + siteId + " ---\n";
            siteData[siteId].tables.forEach(table => {
                confirmMsg += " - " + table + "\n";
            });
        });
        
        confirmMsg += "\nAdakah anda benar-benar pasti? Sila buat backup database terlebih dahulu!";
        
        if (!confirm(confirmMsg)) return;
        if (!confirm("PENGESAHAN TERAKHIR: Anda faham bahawa tindakan ini tidak boleh diundur?")) return;

        btn.prop('disabled', true).text('Purging...');
        
        $.post(whc_ajax_object.ajax_url, {
            action: 'whc_run_optimization',
            nonce: whc_ajax_object.opt_nonce,
            opt_action: 'purge_orphaned_ms',
            sites: JSON.stringify(siteData)
        }, function(r) {
            if (r.success) {
                alert('✅ Selesai! ' + r.data.count + ' jadual telah dihapuskan.');
                location.reload();
            } else {
                alert('Ralat semasa memadam jadual.');
                btn.prop('disabled', false).text('Hapus Jadual Yatim');
            }
        });
    });

});
