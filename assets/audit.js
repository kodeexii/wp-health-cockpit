jQuery(document).ready(function($) {
    // 1. Frontend Audit Logic (Existing)
    $('#run-frontend-audit').on('click', function() {
        const url = $('#target-url').val();
        const btn = $(this);
        const resultContainer = $('#frontend-audit-results');

        if (!url) {
            alert('Sila masukkan URL!');
            return;
        }

        btn.prop('disabled', true).text('Menjalankan Imbasan...');
        resultContainer.html('<p>Sila tunggu, sedang menganalisis...</p>');

        $.post(whc_ajax_object.ajax_url, {
            action: 'run_frontend_audit',
            nonce: whc_ajax_object.nonce,
            url: url
        }, function(response) {
            btn.prop('disabled', false).text('Jalankan Audit');
            if (response.success) {
                let html = '<table class="whc-table" style="width:100%; border:1px solid #ddd; margin-top:10px;">';
                $.each(response.data, function(key, data) {
                    let dotColor = '#ccc';
                    if (data.status === 'ok') dotColor = '#46b450';
                    if (data.status === 'warning') dotColor = '#ffb900';
                    if (data.status === 'critical') dotColor = '#dc3232';

                    html += `<tr>
                        <td style="padding:10px; border-bottom:1px solid #eee;"><strong>${data.label}</strong></td>
                        <td style="padding:10px; border-bottom:1px solid #eee;">
                            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:${dotColor}; margin-right:8px;"></span>
                            ${data.value}
                        </td>
                        <td style="padding:10px; border-bottom:1px solid #eee; font-size:0.9em;">${data.notes}</td>
                    </tr>`;
                });
                html += '</table>';
                resultContainer.html(html);
            } else {
                resultContainer.html('<p style="color:red;">Ralat: Gagal mendapatkan data.</p>');
            }
        });
    });

    // 2. Full Audit Logic
    $('#whc-run-full-audit').on('click', function() {
        const btn = $(this);
        
        if (!confirm('Adakah anda pasti mahu menjalankan imbasan penuh? Ini mungkin memakan masa beberapa saat.')) return;

        btn.prop('disabled', true).text('Menjalankan Audit...');
        $('#whc-audit-results-container').css('opacity', '0.5');

        $.post(whc_ajax_object.ajax_url, {
            action: 'whc_run_full_audit',
            nonce: whc_ajax_object.nonce
        }, function(response) {
            if (response.success) {
                btn.text('Siap!');
                setTimeout(() => {
                    location.reload(); // Reload to show fresh data from storage
                }, 500);
            } else {
                alert('Ralat: ' + response.data);
                btn.prop('disabled', false).text('Jalankan Audit Penuh');
                $('#whc-audit-results-container').css('opacity', '1');
            }
        });
    });
});
