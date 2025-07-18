// Pastikan kod hanya berjalan selepas keseluruhan halaman siap dimuatkan
jQuery(document).ready(function ($) {
    // 1. Apabila butang #whc_run_audit_button diklik
    $('#whc_run_audit_button').on('click', function () {

        // Dapatkan butang, spinner, dan jadual
        var $button = $(this);
        var $spinner = $button.siblings('.spinner');
        var $tableBody = $('#whc-frontend-table tbody');
        
        // Dapatkan URL dari medan input
        var urlToAudit = $('#whc_url_to_audit').val();

        // 2. Paparkan status 'loading' dan nyahaktifkan butang
        $spinner.addClass('is-active');
        $button.prop('disabled', true);
        $tableBody.html('<tr><td colspan="5" style="text-align: center;">Menganalisis URL, sila tunggu...</td></tr>');

        // 3. Hantar permintaan AJAX
        $.ajax({
            url: whc_ajax_object.ajax_url, // URL yang kita hantar dari PHP
            type: 'POST',
            data: {
                action: 'run_frontend_audit', // Nama tindakan AJAX kita
                _ajax_nonce: whc_ajax_object.nonce, // Kunci keselamatan
                url: urlToAudit
            }
        })
        .done(function (response) {
            // 4a. Jika berjaya, 'lukis' semula jadual
            if (response.success) {
                $tableBody.empty(); // Kosongkan jadual sedia ada
                
                // wp_send_json_success membungkus data dalam 'response.data'
                var dataItems = response.data;

                // Loop setiap item data dan bina baris jadual (<tr>)
                $.each(dataItems, function (key, item) {
                    var statusClass = 'status-' + item.status;
                    var tableRow = `
                        <tr>
                            <td><strong>${item.label}</strong></td>
                            <td>${item.value}</td>
                            <td>${item.recommended}</td>
                            <td class="whc-status"><span class="${statusClass}">${item.status}</span></td>
                            <td>${item.notes}</td>
                        </tr>
                    `;
                    $tableBody.append(tableRow);
                });

            } else {
                // Jika ada ralat dari pihak server
                $tableBody.html('<tr><td colspan="5" style="text-align: center;">Ralat: ' + response.data.message + '</td></tr>');
            }
        })
        .fail(function () {
            // 4b. Jika panggilan AJAX itu sendiri gagal (cth: masalah rangkaian)
            $tableBody.html('<tr><td colspan="5" style="text-align: center;">Gagal menghubungi server. Sila cuba lagi.</td></tr>');
        })
        .always(function () {
            // 5. Sembunyikan 'loading' dan aktifkan semula butang
            $spinner.removeClass('is-active');
            $button.prop('disabled', false);
        });
    });
});