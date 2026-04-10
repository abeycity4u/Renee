<?php
require_once 'config.php';
requireLogin();

if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$period = $_GET['period'] ?? 'monthly';
$farmType = $_GET['farm_type'] ?? 'all';
$category = $_GET['category'] ?? 'all';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');

$where = [];
$params = [];

if ($period === 'yearly') {
    $where[] = 'YEAR(e.expense_date) = ?';
    $params[] = $year;
} else {
    $where[] = "DATE_FORMAT(e.expense_date, '%Y-%m') = ?";
    $params[] = $month;
}

if ($farmType !== 'all') {
    $where[] = 'e.farm_type = ?';
    $params[] = $farmType;
}

if ($category !== 'all') {
    $where[] = 'e.category = ?';
    $params[] = $category;
}

$whereSql = implode(' AND ', $where);

$recordsStmt = $pdo->prepare("SELECT e.*, u.full_name FROM farm_expenses e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE {$whereSql}
    ORDER BY e.expense_date DESC");
$recordsStmt->execute($params);
$records = $recordsStmt->fetchAll();

$summaryStmt = $pdo->prepare("SELECT SUM(amount) total_expenses, COUNT(*) total_txn
    FROM farm_expenses e WHERE {$whereSql}");
$summaryStmt->execute($params);
$summary = $summaryStmt->fetch();

$titleRange = $period === 'yearly' ? "Year {$year}" : date('F Y', strtotime($month . '-01'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Expense Report Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display:none !important; } }</style>
</head>
<body class="p-4">
<div class="no-print mb-3">
    <button class="btn btn-primary" onclick="window.print()">Print</button>
    <a class="btn btn-secondary" href="expenses.php">Back</a>
</div>
<h3>Expense Report</h3>
<p><strong>Period:</strong> <?php echo htmlspecialchars($titleRange); ?> | <strong>Farm:</strong> <?php echo htmlspecialchars(ucfirst($farmType)); ?> | <strong>Category:</strong> <?php echo htmlspecialchars(ucfirst($category)); ?></p>
<div class="row mb-3">
    <div class="col-md-6"><div class="alert alert-danger mb-0">Total Expenses: ₦<?php echo number_format((float)$summary['total_expenses'], 2); ?></div></div>
    <div class="col-md-6"><div class="alert alert-secondary mb-0">Transactions: <?php echo (int)$summary['total_txn']; ?></div></div>
</div>
<table class="table table-bordered table-sm">
    <thead class="table-dark">
    <tr><th>Date</th><th>Farm Type</th><th>Category</th><th>Amount</th><th>Description</th><th>Recorded By</th></tr>
    </thead>
    <tbody>
    <?php if (empty($records)): ?>
        <tr><td colspan="6" class="text-center">No records found.</td></tr>
    <?php else: foreach ($records as $row): ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($row['expense_date'])); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($row['farm_type'])); ?></td>
            <td><?php echo htmlspecialchars(ucfirst($row['category'])); ?></td>
            <td>₦<?php echo number_format((float)$row['amount'], 2); ?></td>
            <td><?php echo htmlspecialchars($row['description'] ?: '--'); ?></td>
            <td><?php echo htmlspecialchars($row['full_name'] ?: '--'); ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</body>
</html>
