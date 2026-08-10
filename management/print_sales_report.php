<?php
require_once 'config.php';
requireLogin();

if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$period = $_GET['period'] ?? 'monthly';
$farmType = $_GET['farm_type'] ?? 'all';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');

$where = [];
$params = [];

if ($period === 'yearly') {
    $where[] = 'YEAR(s.sale_date) = ?';
    $params[] = $year;
} else {
    $where[] = "DATE_FORMAT(s.sale_date, '%Y-%m') = ?";
    $params[] = $month;
}

if ($farmType !== 'all') {
    $where[] = 's.farm_type = ?';
    $params[] = $farmType;
}

$whereSql = implode(' AND ', $where);

$recordsStmt = $pdo->prepare("SELECT s.*, u.full_name as seller FROM sales_records s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE {$whereSql}
    ORDER BY s.sale_date DESC");
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll();

$summaryStmt = $pdo->prepare("SELECT SUM(total_amount) total_sales, SUM(quantity) total_qty, COUNT(*) total_txn
    FROM sales_records s WHERE {$whereSql}");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$titleRange = $period === 'yearly' ? "Year {$year}" : date('F Y', strtotime($month . '-01'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Report Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="p-4">
<div class="no-print mb-3">
    <button class="btn btn-primary" onclick="window.print()">Print</button>
    <a class="btn btn-secondary" href="sales_records.php">Back</a>
</div>
<h3>Sales Report</h3>
<p><strong>Period:</strong> <?php echo htmlspecialchars($titleRange); ?> | <strong>Farm:</strong> <?php echo htmlspecialchars(ucfirst($farmType)); ?></p>
<div class="row mb-3">
    <div class="col-md-4"><div class="alert alert-success mb-0">Total Sales: ₦<?php echo number_format((float)$summary['total_sales'], 2); ?></div></div>
    <div class="col-md-4"><div class="alert alert-info mb-0">Total Quantity: <?php echo number_format((float)$summary['total_qty'], 2); ?></div></div>
    <div class="col-md-4"><div class="alert alert-secondary mb-0">Transactions: <?php echo (int)$summary['total_txn']; ?></div></div>
</div>
<table class="table table-bordered table-sm">
    <thead class="table-dark">
    <tr>
        <th>Date</th><th>Farm Type</th><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Total</th><th>Customer</th><th>Recorded By</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($records)): ?>
        <tr><td colspan="8" class="text-center">No records found.</td></tr>
    <?php else: foreach ($records as $row): ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($row['sale_date'])); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($row['farm_type'])); ?></td>
            <td><?php echo htmlspecialchars($row['product_type']); ?></td>
            <td><?php echo number_format((float)$row['quantity'], 2); ?></td>
            <td>₦<?php echo number_format((float)$row['unit_price'], 2); ?></td>
            <td>₦<?php echo number_format((float)$row['total_amount'], 2); ?></td>
            <td><?php echo htmlspecialchars($row['customer_name'] ?: '--'); ?></td>
            <td><?php echo htmlspecialchars($row['seller'] ?: '--'); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</body>
</html>
