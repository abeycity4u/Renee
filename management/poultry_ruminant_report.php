<?php
require_once 'config.php';
requireLogin();

if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$period = $_GET['period'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');

if ($period === 'yearly') {
    $layerWhere = 'YEAR(record_date) = ?';
    $broilerWhere = 'YEAR(record_date) = ?';
    $ruminantWhere = 'YEAR(record_date) = ?';
    $feedWhere = 'YEAR(transaction_date) = ?';
    $params = [$year];
    $periodLabel = "Year {$year}";
} else {
    $layerWhere = "DATE_FORMAT(record_date, '%Y-%m') = ?";
    $broilerWhere = "DATE_FORMAT(record_date, '%Y-%m') = ?";
    $ruminantWhere = "DATE_FORMAT(record_date, '%Y-%m') = ?";
    $feedWhere = "DATE_FORMAT(transaction_date, '%Y-%m') = ?";
    $params = [$month];
    $periodLabel = date('F Y', strtotime($month . '-01'));
}

$layerStmt = $pdo->prepare("SELECT
    SUM(opening_stock) opening_stock,
    SUM(mortality) mortality,
    SUM(feed_consumption_bags) feed_consumption,
    SUM(egg_production) egg_production,
    AVG(laying_rate) avg_laying_rate,
    COUNT(*) records_count
    FROM layer_daily_records WHERE {$layerWhere}");
$layerStmt->execute($params);
$layerSummary = $layerStmt->fetch();

$broilerStmt = $pdo->prepare("SELECT
    SUM(opening_stock) opening_stock,
    SUM(mortality) mortality,
    SUM(feed_consumption_bags) feed_consumption,
    COUNT(*) records_count
    FROM broiler_daily_records WHERE {$broilerWhere}");
$broilerStmt->execute($params);
$broilerSummary = $broilerStmt->fetch();

$ruminantStmt = $pdo->prepare("SELECT
    SUM(opening_stock) opening_stock,
    SUM(mortality) mortality,
    SUM(feed_consumption_kg) feed_consumption,
    COUNT(*) records_count
    FROM ruminant_daily_records WHERE {$ruminantWhere}");
$ruminantStmt->execute($params);
$ruminantSummary = $ruminantStmt->fetch();

$feedsStmt = $pdo->prepare("SELECT
    SUM(CASE WHEN transaction_type='used' THEN quantity ELSE 0 END) total_used,
    SUM(CASE WHEN transaction_type='received' THEN quantity ELSE 0 END) total_received
    FROM stock_transactions WHERE {$feedWhere}");
$feedsStmt->execute($params);
$feedFlow = $feedsStmt->fetch();

$stockStmt = $pdo->query("SELECT farm_type, COUNT(*) total_items, SUM(current_stock) total_stock
    FROM stock_items GROUP BY farm_type");
$stocks = $stockStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'navbar_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poultry & Ruminant Report - Renee Farms</title>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4><i class="bi bi-file-earmark-bar-graph"></i> Poultry & Ruminant Report - <?php echo htmlspecialchars($periodLabel); ?></h4>
            <div class="d-flex gap-2">
                <select class="form-select" id="periodType" style="width:140px;">
                    <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                    <option value="yearly" <?php echo $period === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                </select>
                <input type="month" class="form-control" id="monthFilter" style="width:180px; <?php echo $period === 'yearly' ? 'display:none;' : ''; ?>" value="<?php echo htmlspecialchars($month); ?>">
                <select class="form-select" id="yearFilter" style="width:140px; <?php echo $period === 'yearly' ? '' : 'display:none;'; ?>">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo (string)$y === (string)$year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print Report</button>
            </div>
        </div>
        <div class="card-body bg-light">
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="card text-white bg-success"><div class="card-body"><small>Layer Eggs Produced</small><h3><?php echo number_format((float)$layerSummary['egg_production']); ?></h3></div></div></div>
                <div class="col-md-3"><div class="card text-white bg-danger"><div class="card-body"><small>Total Mortality</small><h3><?php echo number_format((float)$layerSummary['mortality'] + (float)$broilerSummary['mortality'] + (float)$ruminantSummary['mortality']); ?></h3></div></div></div>
                <div class="col-md-3"><div class="card text-white bg-primary"><div class="card-body"><small>Feed Used (all records)</small><h3><?php echo number_format((float)$feedFlow['total_used'], 2); ?></h3></div></div></div>
                <div class="col-md-3"><div class="card text-white bg-info"><div class="card-body"><small>Layer Avg Laying Rate</small><h3><?php echo number_format((float)$layerSummary['avg_laying_rate'], 1); ?>%</h3></div></div></div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">Layer Summary</div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Records:</strong> <?php echo (int)$layerSummary['records_count']; ?></p>
                            <p class="mb-1"><strong>Opening Stock (sum):</strong> <?php echo number_format((float)$layerSummary['opening_stock']); ?></p>
                            <p class="mb-1"><strong>Mortality:</strong> <?php echo number_format((float)$layerSummary['mortality']); ?></p>
                            <p class="mb-1"><strong>Feed (bags):</strong> <?php echo number_format((float)$layerSummary['feed_consumption'], 2); ?></p>
                            <p class="mb-0"><strong>Eggs:</strong> <?php echo number_format((float)$layerSummary['egg_production']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">Broiler Summary</div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Records:</strong> <?php echo (int)$broilerSummary['records_count']; ?></p>
                            <p class="mb-1"><strong>Opening Stock (sum):</strong> <?php echo number_format((float)$broilerSummary['opening_stock']); ?></p>
                            <p class="mb-1"><strong>Mortality:</strong> <?php echo number_format((float)$broilerSummary['mortality']); ?></p>
                            <p class="mb-0"><strong>Feed (bags):</strong> <?php echo number_format((float)$broilerSummary['feed_consumption'], 2); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">Ruminant Summary</div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Records:</strong> <?php echo (int)$ruminantSummary['records_count']; ?></p>
                            <p class="mb-1"><strong>Opening Stock (sum):</strong> <?php echo number_format((float)$ruminantSummary['opening_stock']); ?></p>
                            <p class="mb-1"><strong>Mortality:</strong> <?php echo number_format((float)$ruminantSummary['mortality']); ?></p>
                            <p class="mb-0"><strong>Feed (kg):</strong> <?php echo number_format((float)$ruminantSummary['feed_consumption'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">Stock Overview</div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead class="table-dark"><tr><th>Farm Type</th><th>Total Items</th><th>Total Stock</th></tr></thead>
                        <tbody>
                        <?php foreach ($stocks as $stock): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(ucfirst($stock['farm_type'])); ?></td>
                                <td><?php echo (int)$stock['total_items']; ?></td>
                                <td><?php echo number_format((float)$stock['total_stock'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function refreshReport() {
        const period = document.getElementById('periodType').value;
        const month = document.getElementById('monthFilter').value;
        const year = document.getElementById('yearFilter').value;
        const url = period === 'yearly'
            ? `poultry_ruminant_report.php?period=yearly&year=${year}`
            : `poultry_ruminant_report.php?period=monthly&month=${month}`;
        window.location.href = url;
    }

    document.getElementById('periodType').addEventListener('change', refreshReport);
    document.getElementById('monthFilter').addEventListener('change', refreshReport);
    document.getElementById('yearFilter').addEventListener('change', refreshReport);
</script>
</body>
</html>
