        </div><!-- End page-content -->

        <!-- v9.6 Mobile bottom-nav (admin) -->
        <nav class="mob-bottomnav" aria-label="Admin quick nav">
            <a href="<?php echo ADMIN_URL; ?>dashboard.php" class="mob-bn-item <?php echo ($currentPage??'')==='dashboard'?'active':''; ?>"><i class="fas fa-gauge-high"></i><span>ड्यासबोर्ड</span></a>
            <a href="<?php echo ADMIN_URL; ?>notices.php" class="mob-bn-item"><i class="fas fa-bullhorn"></i><span>सूचना</span></a>
            <a href="<?php echo ADMIN_URL; ?>members.php" class="mob-bn-item"><i class="fas fa-users"></i><span>सदस्य</span></a>
            <a href="<?php echo ADMIN_URL; ?>settings.php" class="mob-bn-item"><i class="fas fa-gear"></i><span>सेटिङ</span></a>
            <a href="<?php echo ADMIN_URL; ?>logout.php" class="mob-bn-item"><i class="fas fa-right-from-bracket"></i><span>लगआउट</span></a>
        </nav>
        <script>document.body.classList.add('has-bottomnav');</script>
    </main><!-- End main-content -->
    </div><!-- End admin-wrapper -->

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery (Bootstrap भन्दा पछि, DataTables र Nepali Datepicker को लागि) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <!-- CKEditor for rich text editing -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

    <!-- Nepali Datepicker JS v5 (self-hosted — CSS admin-header.php मा load भएको छ) -->
    <script src="../assets/js/nepali.datepicker.min.js"></script>

    <!-- Admin JS -->
    <script src="assets/admin.js"></script>
    <script src="../assets/js/v9-mobile-fix.js?v=9.7" defer></script>

    <script>
    /* =====================================================
       ADMIN FOOTER SCRIPTS
       यहाँ admin panel का सबै global JS functions छन्।
       ===================================================== */

    /* ─────────────────────────────────────────────────────
       Nepali Datepicker initialize गर्ने function
       — Page load र Bootstrap Modal open दुवैमा काम गर्छ
       —
       FIX: jQuery plugin हो — $(input).nepaliDatePicker()
            DOM element.nepaliDatePicker() गर्दा काम गर्दैन
       ───────────────────────────────────────────────────── */
    function initNepaliDatepickers(container) {
        /* jQuery र nepaliDatePicker library दुवै load भएको हुनुपर्छ */
        if (typeof $ === 'undefined' || typeof $.fn.nepaliDatePicker === 'undefined') return;

        var scope  = $(container || document);
        var inputs = scope.find('.nepali-datepicker').addBack('.nepali-datepicker');

        inputs.each(function() {
            var $inp = $(this);

            /* Already initialized? Skip — double-init बाट जोगाउन */
            if ($inp.data('ndp-ready')) return;
            $inp.data('ndp-ready', true);

            /* Nepali datepicker v5 initialize */
            $inp.nepaliDatePicker({
                dateFormat : 'YYYY-MM-DD',
                language   : 'nepali'
            });

            /* Calendar icon button छ भने — click गर्दा datepicker open हुन्छ (v5: focus event) */
            $inp.closest('.input-group, .nepali-datepicker-wrapper')
                .find('.ndp-trigger, .input-group-text').on('click.ndp', function() {
                    $inp.trigger('focus');
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {

        /* --- Page load मा datepicker init --- */
        initNepaliDatepickers(document);

        /* --- Bootstrap modal खुल्दा modal भित्रका datepickers पनि init --- */
        document.addEventListener('shown.bs.modal', function(e) {
            if (e.target && e.target.classList.contains('modal')) {
                initNepaliDatepickers(e.target);
            }
        });

        /* ─────────────────────────────────────────────────────
           CSRF: हरेक POST form मा CSRF token auto-inject गर्नुहोस्
           — JS injection: PHP embedded token नभएका forms को लागि
           ───────────────────────────────────────────────────── */
        var csrfToken = '<?php echo htmlspecialchars($csrfToken ?? generateCSRFToken(), ENT_QUOTES, "UTF-8"); ?>';

        function injectCsrf() {
            document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(function(form) {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    var field   = document.createElement('input');
                    field.type  = 'hidden';
                    field.name  = 'csrf_token';
                    field.value = csrfToken;
                    form.appendChild(field);
                }
            });
        }
        injectCsrf();
        /* Modal खुलेपछि पनि CSRF inject गर्नुहोस् */
        document.addEventListener('shown.bs.modal', injectCsrf);

        /* ─────────────────────────────────────────────────────
           DELETE links: GET delete link → safe POST+CSRF form
           — a[href*="action=delete"] ले GET delete बाट जोगाउँछ
           ───────────────────────────────────────────────────── */
        document.querySelectorAll('a[href*="action=delete"], a[href*="delete="]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('के तपाईं यो मेटाउन निश्चित हुनुहुन्छ?')) return;
                var form = document.createElement('form');
                form.method = 'POST';
                var url = new URL(link.href, window.location.origin);
                form.action = url.pathname;
                url.searchParams.forEach(function(val, key) {
                    var inp   = document.createElement('input');
                    inp.type  = 'hidden';
                    inp.name  = key;
                    inp.value = val;
                    form.appendChild(inp);
                });
                var csrf   = document.createElement('input');
                csrf.type  = 'hidden';
                csrf.name  = 'csrf_token';
                csrf.value = csrfToken;
                form.appendChild(csrf);
                document.body.appendChild(form);
                form.submit();
            });
        });

        /* ─────────────────────────────────────────────────────
           DataTable: Auto-initialize all .data-table tables
           ───────────────────────────────────────────────────── */
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            /* colspan rows in empty tbody ले tn/18 alert दिन्छ — suppress गर्छौं */
            $.fn.dataTable.ext.errMode = 'none';
            document.querySelectorAll('table.data-table:not(.dataTable)').forEach(function(tbl) {
                try {
                    $(tbl).DataTable({
                        language: {
                            search    : 'खोज्नुहोस्:',
                            lengthMenu: '_MENU_ पङ्क्ति प्रति पृष्ठ',
                            info      : '_START_–_END_ / जम्मा _TOTAL_',
                            paginate  : { previous: '‹', next: '›' },
                            emptyTable: 'कुनै डाटा छैन'
                        },
                        pageLength: 10,
                        lengthMenu: [[10, 20, 50, 100], [10, 20, 50, 100]],
                        responsive: true
                    });
                } catch(dtErr) {
                    console.warn('DataTables init skipped for', tbl.id || tbl.className, ':', dtErr.message);
                }
            });
        }

        /* ─────────────────────────────────────────────────────
           Flash message: 5 सेकेन्डमा auto-hide
           ───────────────────────────────────────────────────── */
        var flash = document.querySelector('.alert-dismissible');
        if (flash) {
            setTimeout(function() {
                flash.style.transition = 'opacity 0.5s';
                flash.style.opacity    = '0';
                setTimeout(function() { flash.remove(); }, 500);
            }, 5000);
        }
    });
    </script>
</body>
</html>
