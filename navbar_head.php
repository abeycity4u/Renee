<?php
// navbar_head.php - Contains only the head section for reuse
?>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">

<!-- Custom CSS -->
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/dashboard.css">

<!-- Favicon -->
<link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">

<!-- JavaScript Data -->
<script>
    window.farmData = {
        userId: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
        userType: <?php echo json_encode($_SESSION['user_type'] ?? null); ?>,
        farmType: <?php echo json_encode(getUserFarmType()); ?>,
        csrfToken: <?php echo json_encode(bin2hex(random_bytes(32))); ?>
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('input[type="month"]').forEach(function (input) {
            const initialValue = input.value;
            input.type = 'text';
            input.setAttribute('readonly', 'readonly');

            flatpickr(input, {
                dateFormat: 'Y-m',
                defaultDate: initialValue || new Date(),
                allowInput: false,
                plugins: [
                    new monthSelectPlugin({
                        shorthand: true,
                        dateFormat: "Y-m",
                        altFormat: "F Y"
                    })
                ]
            });
        });
    });
</script>
