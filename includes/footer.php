<?php

/**
 * HTML Footer with AdminLTE JS
 * 
 * Include this file at the bottom of every page
 * Handles JavaScript loading, page scripts, and footer content
 */

// Get current year for copyright
$currentYear = date('Y');
$appName = 'WhatsApp Monitor';
$appVersion = '1.0.0';
?>

</div>
<!-- /.wrapper -->

<!-- Main Footer -->
<footer class="main-footer">
    <strong>Copyright &copy; <?php echo $currentYear; ?> <a href="#"><?php echo $appName; ?></a>.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
        <b>Version</b> <?php echo $appVersion; ?>
    </div>
</footer>

<!-- Control Sidebar -->
<aside class="control-sidebar control-sidebar-dark">
    <!-- Control sidebar content goes here -->
    <div class="p-3">
        <h5>Pengaturan Tampilan</h5>

        <!-- Dark Mode Toggle -->
        <div class="mb-4">
            <input type="checkbox" id="darkModeToggle" data-bootstrap-switch data-off-color="light" data-on-color="dark">
            <label for="darkModeToggle" class="ml-2">Dark Mode</label>
        </div>

        <!-- Sidebar Options -->
        <div class="mb-4">
            <h6>Sidebar</h6>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sidebarMini">
                <label class="form-check-label" for="sidebarMini">Sidebar Mini</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="sidebarCollapse">
                <label class="form-check-label" for="sidebarCollapse">Sidebar Collapse</label>
            </div>
        </div>

        <!-- Navbar Options -->
        <div class="mb-4">
            <h6>Navbar</h6>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="navbarFixed">
                <label class="form-check-label" for="navbarFixed">Fixed Navbar</label>
            </div>
        </div>

        <!-- Footer Options -->
        <div class="mb-4">
            <h6>Footer</h6>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="footerFixed">
                <label class="form-check-label" for="footerFixed">Fixed Footer</label>
            </div>
        </div>

        <!-- Refresh Settings -->
        <div class="mb-4">
            <h6>Auto Refresh</h6>
            <select class="form-control form-control-sm" id="autoRefreshInterval">
                <option value="0">Disabled</option>
                <option value="30">30 seconds</option>
                <option value="60" selected>1 minute</option>
                <option value="300">5 minutes</option>
                <option value="600">10 minutes</option>
            </select>
        </div>

        <!-- Reset Settings -->
        <button type="button" class="btn btn-default btn-sm btn-block" onclick="resetSettings()">
            <i class="fas fa-undo"></i> Reset Settings
        </button>
    </div>
</aside>
<!-- /.control-sidebar -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jquery/jquery.min.js"></script>

<!-- jQuery UI 1.11.4 -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jquery-ui/jquery-ui.min.js"></script>

<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
<script>
    $.widget.bridge('uibutton', $.ui.button);
</script>

<!-- Bootstrap 4 -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- ChartJS -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/chart.js/Chart.min.js"></script>

<!-- Sparkline -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/sparklines/sparkline.js"></script>

<!-- JQVMap -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jqvmap/maps/jquery.vmap.usa.js"></script>

<!-- jQuery Knob Chart -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jquery-knob/jquery.knob.min.js"></script>

<!-- daterangepicker -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/moment/moment.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/daterangepicker/daterangepicker.js"></script>

<!-- Tempusdominus Bootstrap 4 -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>

<!-- Summernote -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/summernote/summernote-bs4.min.js"></script>

<!-- overlayScrollbars -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>

<!-- Select2 -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/select2/js/select2.full.min.js"></script>

<!-- Bootstrap4 Duallistbox -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.min.js"></script>

<!-- InputMask -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/moment/moment.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/inputmask/jquery.inputmask.min.js"></script>

<!-- Bootstrap Switch -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>

<!-- BS-Stepper -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/bs-stepper/js/bs-stepper.min.js"></script>

<!-- dropzonejs -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/dropzone/min/dropzone.min.js"></script>

<!-- DataTables & Plugins -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/jszip/jszip.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/pdfmake/pdfmake.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/pdfmake/vfs_fonts.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<!-- SweetAlert2 -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Toastr -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/plugins/toastr/toastr.min.js"></script>

<!-- AdminLTE App -->
<script src="<?php echo ASSETS_URL; ?>/adminlte/dist/js/adminlte.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo ASSETS_URL; ?>/custom/js/api-client.js"></script>
<script src="<?php echo ASSETS_URL; ?>/custom/js/dashboard.js"></script>

<!-- Page-specific JavaScript -->
<?php if (isset($extraJS) && is_array($extraJS)): ?>
    <?php foreach ($extraJS as $js): ?>
        <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Global JavaScript Functions -->
<script>
    $(function() {
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Initialize popovers
        $('[data-toggle="popover"]').popover();

        // Initialize Select2 Elements
        $('.select2').select2();

        // Initialize Select2 Elements with Bootstrap4 theme
        $('.select2bs4').select2({
            theme: 'bootstrap4'
        });

        // Initialize Date range picker
        $('.daterange').daterangepicker();

        // Initialize Date picker
        $('.datepicker').datetimepicker({
            format: 'L'
        });

        // Initialize Time picker
        $('.timepicker').datetimepicker({
            format: 'LT'
        });

        // Initialize Bootstrap Switch
        $("input[data-bootstrap-switch]").each(function() {
            $(this).bootstrapSwitch('state', $(this).prop('checked'));
        });

        // Initialize DataTables with default config
        $('.data-table').DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"],
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json"
            }
        }).buttons().container().appendTo('.data-table_wrapper .col-md-6:eq(0)');
    });

    // Global AJAX setup
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': window.csrfToken
        },
        beforeSend: function() {
            // Show loading spinner
            showLoading();
        },
        complete: function() {
            // Hide loading spinner
            hideLoading();
        },
        error: function(xhr, status, error) {
            handleAjaxError(xhr, status, error);
        }
    });

    // Global loading functions
    function showLoading() {
        if ($('#globalLoading').length === 0) {
            $('body').append('<div id="globalLoading" class="overlay"><div class="spinner-border text-primary" role="status"></div></div>');
        }
        $('#globalLoading').show();
    }

    function hideLoading() {
        $('#globalLoading').hide();
    }

    // Global error handler
    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', xhr.status, xhr.responseText);

        let message = 'Terjadi kesalahan sistem';

        if (xhr.status === 401) {
            message = 'Sesi Anda telah berakhir. Silakan login kembali.';
            setTimeout(function() {
                window.location.href = '/pages/auth/login.php';
            }, 2000);
        } else if (xhr.status === 403) {
            message = 'Anda tidak memiliki akses untuk melakukan operasi ini.';
        } else if (xhr.status === 404) {
            message = 'Resource tidak ditemukan.';
        } else if (xhr.status >= 500) {
            message = 'Terjadi kesalahan server. Silakan coba lagi nanti.';
        } else if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }

        toastr.error(message);
    }

    // Global success handler
    function showSuccess(message) {
        toastr.success(message);
    }

    // Global warning handler
    function showWarning(message) {
        toastr.warning(message);
    }

    // Global error handler
    function showError(message) {
        toastr.error(message);
    }

    // Global info handler
    function showInfo(message) {
        toastr.info(message);
    }

    // Confirmation dialog
    function confirmAction(message, callback) {
        Swal.fire({
            title: 'Konfirmasi',
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    }

    // Delete confirmation
    function confirmDelete(message, callback) {
        Swal.fire({
            title: 'Hapus Data?',
            text: message || 'Data yang dihapus tidak dapat dikembalikan!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed && typeof callback === 'function') {
                callback();
            }
        });
    }

    // Format number with thousands separator
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Time ago function
    function timeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - new Date(date)) / 1000);

        if (diffInSeconds < 60) return 'Baru saja';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' menit yang lalu';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' jam yang lalu';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + ' hari yang lalu';

        return new Date(date).toLocaleDateString('id-ID');
    }

    // Copy to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            toastr.success('Berhasil disalin ke clipboard');
        }, function() {
            toastr.error('Gagal menyalin ke clipboard');
        });
    }

    // Real-time updates
    let autoRefreshInterval = null;

    function startAutoRefresh(intervalSeconds) {
        stopAutoRefresh();

        if (intervalSeconds > 0) {
            autoRefreshInterval = setInterval(function() {
                if (typeof refreshPageData === 'function') {
                    refreshPageData();
                }
            }, intervalSeconds * 1000);
        }
    }

    function stopAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
    }

    // Control sidebar settings
    $('#autoRefreshInterval').on('change', function() {
        const interval = parseInt($(this).val());
        startAutoRefresh(interval);
        localStorage.setItem('autoRefreshInterval', interval);
    });

    $('#sidebarMini').on('change', function() {
        if ($(this).is(':checked')) {
            $('body').addClass('sidebar-mini');
        } else {
            $('body').removeClass('sidebar-mini');
        }
        localStorage.setItem('sidebarMini', $(this).is(':checked'));
    });

    $('#sidebarCollapse').on('change', function() {
        if ($(this).is(':checked')) {
            $('body').addClass('sidebar-collapse');
        } else {
            $('body').removeClass('sidebar-collapse');
        }
        localStorage.setItem('sidebarCollapse', $(this).is(':checked'));
    });

    $('#navbarFixed').on('change', function() {
        if ($(this).is(':checked')) {
            $('body').addClass('layout-navbar-fixed');
        } else {
            $('body').removeClass('layout-navbar-fixed');
        }
        localStorage.setItem('navbarFixed', $(this).is(':checked'));
    });

    $('#footerFixed').on('change', function() {
        if ($(this).is(':checked')) {
            $('body').addClass('layout-footer-fixed');
        } else {
            $('body').removeClass('layout-footer-fixed');
        }
        localStorage.setItem('footerFixed', $(this).is(':checked'));
    });

    // Load saved settings
    function loadSettings() {
        // Auto refresh
        const savedInterval = localStorage.getItem('autoRefreshInterval');
        if (savedInterval) {
            $('#autoRefreshInterval').val(savedInterval);
            startAutoRefresh(parseInt(savedInterval));
        }

        // Sidebar settings
        if (localStorage.getItem('sidebarMini') === 'true') {
            $('#sidebarMini').prop('checked', true);
            $('body').addClass('sidebar-mini');
        }

        if (localStorage.getItem('sidebarCollapse') === 'true') {
            $('#sidebarCollapse').prop('checked', true);
            $('body').addClass('sidebar-collapse');
        }

        if (localStorage.getItem('navbarFixed') === 'true') {
            $('#navbarFixed').prop('checked', true);
            $('body').addClass('layout-navbar-fixed');
        }

        if (localStorage.getItem('footerFixed') === 'true') {
            $('#footerFixed').prop('checked', true);
            $('body').addClass('layout-footer-fixed');
        }
    }

    // Reset settings
    function resetSettings() {
        localStorage.clear();
        location.reload();
    }

    // Load settings on page load
    $(document).ready(function() {
        loadSettings();
    });

    // Prevent form double submission
    $('form').on('submit', function() {
        $(this).find('button[type="submit"]').prop('disabled', true);
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Configure Toastr
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };

    // Heartbeat to keep session alive
    setInterval(function() {
        $.get(window.baseUrl + '/api/auth.php?action=heartbeat');
    }, 300000); // Every 5 minutes
</script>

<!-- Page-specific inline JavaScript -->
<?php if (isset($inlineJS)): ?>
    <script>
        <?php echo $inlineJS; ?>
    </script>
<?php endif; ?>

<!-- Development mode scripts -->
<?php if (defined('APP_DEBUG') && APP_DEBUG): ?>
    <script>
        // Console log for development
        console.log('WhatsApp Monitor - Development Mode');
        console.log('User:', <?php echo json_encode($currentUser ?? []); ?>);
        console.log('CSRF Token:', window.csrfToken);
        console.log('Base URL:', window.baseUrl);

        // Show PHP errors in console (if any)
        <?php if (isset($phpErrors) && !empty($phpErrors)): ?>
            console.group('PHP Errors');
            <?php foreach ($phpErrors as $error): ?>
                console.error(<?php echo json_encode($error); ?>);
            <?php endforeach; ?>
            console.groupEnd();
        <?php endif; ?>
    </script>
<?php endif; ?>

</body>

</html>