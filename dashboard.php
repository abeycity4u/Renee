<?php
require_once 'config.php';
requireLogin();

$userType = getUserType();
$farmAccess = $userType === 'owner' ? 'both' : ($userType === 'poultry_manager' ? 'poultry' : 'ruminant');

// Get current stock levels
$stockQuery = "SELECT * FROM stock_items WHERE farm_type IN (?, 'both') ORDER BY current_stock ASC";
$stockStmt = $pdo->prepare($stockQuery);
$stockStmt->execute([$farmAccess]);
$stockItems = $stockStmt->fetchAll();

// Get today's transactions
$today = date('Y-m-d');
$transQuery = "SELECT t.*, s.item_name, s.unit FROM stock_transactions t 
               JOIN stock_items s ON t.stock_item_id = s.id 
               WHERE t.farm_type = ? AND t.transaction_date = ? 
               ORDER BY t.id DESC LIMIT 10";
$transStmt = $pdo->prepare($transQuery);
$transStmt->execute([$farmAccess, $today]);
$todayTransactions = $transStmt->fetchAll();

// Get low stock items
$lowStockQuery = "SELECT * FROM stock_items 
                  WHERE farm_type IN (?, 'both') 
                  AND current_stock <= min_stock_level";
$lowStockStmt = $pdo->prepare($lowStockQuery);
$lowStockStmt->execute([$farmAccess]);
$lowStockItems = $lowStockStmt->fetchAll();

// Get recent sales
$salesQuery = "SELECT s.*, u.full_name as seller 
               FROM sales_records s 
               LEFT JOIN users u ON s.user_id = u.id 
               WHERE s.farm_type = ? 
               ORDER BY s.sale_date DESC, s.id DESC 
               LIMIT 5";
$salesStmt = $pdo->prepare($salesQuery);
$salesStmt->execute([$farmAccess]);
$recentSales = $salesStmt->fetchAll();

// Get recent expenses
$expenseQuery = "SELECT e.*, u.full_name 
                 FROM farm_expenses e
                 LEFT JOIN users u ON e.user_id = u.id
                 WHERE e.farm_type = ? 
                 ORDER BY e.expense_date DESC, e.id DESC 
                 LIMIT 5";
$expenseStmt = $pdo->prepare($expenseQuery);
$expenseStmt->execute([$farmAccess]);
$recentExpenses = $expenseStmt->fetchAll();

// Get recent daily records
if ($farmAccess === 'poultry' || $farmAccess === 'both') {
    $layerQuery = "SELECT * FROM layer_daily_records 
                   ORDER BY record_date DESC LIMIT 1";
    $layerStmt = $pdo->query($layerQuery);
    $latestLayerRecord = $layerStmt->fetch();
    
    $broilerQuery = "SELECT * FROM broiler_daily_records 
                     ORDER BY record_date DESC LIMIT 1";
    $broilerStmt = $pdo->query($broilerQuery);
    $latestBroilerRecord = $broilerStmt->fetch();
}

if ($farmAccess === 'ruminant' || $farmAccess === 'both') {
    $ruminantQuery = "SELECT * FROM ruminant_daily_records 
                      ORDER BY record_date DESC LIMIT 1";
    $ruminantStmt = $pdo->query($ruminantQuery);
    $latestRuminantRecord = $ruminantStmt->fetch();
}

// Get profit/loss for current month
$month = date('Y-m');
$profitQuery = "SELECT * FROM profit_loss_summary WHERE month = ? AND farm_type = ?";
$profitStmt = $pdo->prepare($profitQuery);
$profitStmt->execute([$month, $farmAccess]);
$profitData = $profitStmt->fetch();

// Calculate dashboard statistics
$totalStockValue = 0;
$totalStockItems = count($stockItems);
$lowStockCount = count($lowStockItems);

foreach ($stockItems as $item) {
    $totalStockValue += $item['current_stock'] * 100; // Assuming average value
}

// Get activity count for today
$activityQuery = "SELECT COUNT(*) as activity_count FROM (
                  SELECT id FROM stock_transactions WHERE farm_type = ? AND transaction_date = ?
                  UNION ALL
                  SELECT id FROM layer_daily_records WHERE record_date = ?
                  UNION ALL
                  SELECT id FROM broiler_daily_records WHERE record_date = ?
                  UNION ALL
                  SELECT id FROM ruminant_daily_records WHERE record_date = ?
                  UNION ALL
                  SELECT id FROM farm_expenses WHERE farm_type = ? AND expense_date = ?
                  UNION ALL
                  SELECT id FROM sales_records WHERE farm_type = ? AND sale_date = ?
                  ) as activities";
$activityStmt = $pdo->prepare($activityQuery);
$activityStmt->execute([
    $farmAccess, $today,
    $today, $today, $today,
    $farmAccess, $today,
    $farmAccess, $today
]);
$todayActivity = $activityStmt->fetchColumn();

// Set page title
$pageTitle = "Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'navbar_head.php'; ?>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Dashboard Specific CSS -->
    <style>
        .dashboard-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        
        .activity-item {
            border-left: 3px solid transparent;
            padding: 10px 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            transition: all 0.2s;
        }
        
        .activity-item:hover {
            background: #e9ecef;
            border-left-color: #28a745;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .stock-low {
            background-color: #dc3545;
        }
        
        .stock-moderate {
            background-color: #ffc107;
        }
        
        .stock-good {
            background-color: #28a745;
        }
        
        .quick-action-btn {
            padding: 10px 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            transform: scale(1.05);
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .dashboard-card .card-body {
                padding: 15px;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <!-- Welcome Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card dashboard-card bg-light">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1">Welcome back, <?php echo $_SESSION['full_name']; ?>! 👋</h2>
                                <p class="text-muted mb-0">
                                    <?php echo date('l, F j, Y'); ?> • 
                                    Last login: <?php echo date('M j, g:i a'); ?>
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success fs-6">
                                    <?php echo ucfirst(str_replace('_', ' ', $userType)); ?>
                                </span>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Farm Access: 
                                        <span class="badge bg-info">
                                            <?php echo ucfirst($farmAccess); ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dashboard Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal">Total Stock Value</h6>
                                <h2 class="mb-0 fw-bold">₦<?php echo number_format($totalStockValue); ?></h2>
                                <small class="text-success">
                                    <i class="bi bi-arrow-up"></i> Updated today
                                </small>
                            </div>
                            <div class="stat-icon text-primary">
                                <i class="bi bi-box-seam"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card border-start border-success border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal">Items in Stock</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $totalStockItems; ?></h2>
                                <small class="text-muted">
                                    <?php echo $lowStockCount; ?> need reorder
                                </small>
                            </div>
                            <div class="stat-icon text-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card border-start border-warning border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal">Today's Activities</h6>
                                <h2 class="mb-0 fw-bold"><?php echo $todayActivity; ?></h2>
                                <small class="text-warning">
                                    <i class="bi bi-clock-history"></i> Live updates
                                </small>
                            </div>
                            <div class="stat-icon text-warning">
                                <i class="bi bi-activity"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card dashboard-card border-start border-info border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted fw-normal">Current Month</h6>
                                <h2 class="mb-0 fw-bold <?php echo ($profitData['net_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    ₦<?php echo number_format($profitData['net_profit'] ?? 0, 2); ?>
                                </h2>
                                <small class="text-info">
                                    <?php echo date('F Y'); ?> Profit/Loss
                                </small>
                            </div>
                            <div class="stat-icon text-info">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="row">
            <!-- Left Column: Stock & Quick Actions -->
            <div class="col-xl-8">
                <!-- Current Stock Levels -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-box-seam text-primary"></i> 
                            Current Stock Levels
                        </h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="filterStock('all')">All Items</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterStock('low')">Low Stock Only</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterStock('poultry')">Poultry Only</a></li>
                                <li><a class="dropdown-item" href="#" onclick="filterStock('ruminant')">Ruminant Only</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="stockTable">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Current Stock</th>
                                        <th>Min Level</th>
                                        <th>Unit</th>
                                        <th>Status</th>
                                        <th>Farm Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stockItems as $item): 
                                        $stockPercent = ($item['current_stock'] / $item['min_stock_level']) * 100;
                                        if ($item['current_stock'] <= $item['min_stock_level']) {
                                            $statusClass = 'danger';
                                            $statusText = 'Low Stock';
                                            $indicatorClass = 'stock-low';
                                        } elseif ($stockPercent <= 150) {
                                            $statusClass = 'warning';
                                            $statusText = 'Moderate';
                                            $indicatorClass = 'stock-moderate';
                                        } else {
                                            $statusClass = 'success';
                                            $statusText = 'Good';
                                            $indicatorClass = 'stock-good';
                                        }
                                    ?>
                                    <tr data-farm-type="<?php echo $item['farm_type']; ?>" 
                                        data-stock-status="<?php echo $statusClass; ?>">
                                        <td>
                                            <strong><?php echo $item['item_name']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?php echo "text-$statusClass"; ?>">
                                                <?php echo $item['current_stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['min_stock_level']; ?></td>
                                        <td><?php echo $item['unit']; ?></td>
                                        <td>
                                            <span class="stock-indicator <?php echo $indicatorClass; ?>"></span>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['farm_type'] == 'poultry' ? 'info' : 'warning'; ?>">
                                                <?php echo ucfirst($item['farm_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="quickStockUpdate(<?php echo $item['id']; ?>)"
                                                    title="Quick Update">
                                                <i class="bi bi-arrow-up-down"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($stockItems)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No stock items found
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card dashboard-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-lightning-charge text-warning"></i> 
                                    Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php if ($farmAccess === 'poultry' || $farmAccess === 'both'): ?>
                                    <div class="col-md-3 mb-3">
                                        <a href="layers_daily_record.php" class="card quick-action-btn text-decoration-none border-primary text-center">
                                            <div class="card-body">
                                                <i class="bi bi-egg-fried display-4 text-primary mb-2"></i>
                                                <h6>Layer Daily</h6>
                                                <small class="text-muted">Record today's data</small>
                                            </div>
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <a href="broiler_daily_record.php" class="card quick-action-btn text-decoration-none border-info text-center">
                                            <div class="card-body">
                                                <i class="bi bi-basket display-4 text-info mb-2"></i>
                                                <h6>Broiler Daily</h6>
                                                <small class="text-muted">Update broiler records</small>
                                            </div>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($farmAccess === 'ruminant' || $farmAccess === 'both'): ?>
                                    <div class="col-md-3 mb-3">
                                        <a href="ruminant_daily_record.php" class="card quick-action-btn text-decoration-none border-warning text-center">
                                            <div class="card-body">
                                                <i class="bi bi-shield-plus display-4 text-warning mb-2"></i>
                                                <h6>Ruminant Daily</h6>
                                                <small class="text-muted">Update ruminant data</small>
                                            </div>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-md-3 mb-3">
                                        <a href="inventory.php" class="card quick-action-btn text-decoration-none border-success text-center">
                                            <div class="card-body">
                                                <i class="bi bi-box-arrow-in-down display-4 text-success mb-2"></i>
                                                <h6>Update Stock</h6>
                                                <small class="text-muted">Add/remove inventory</small>
                                            </div>
                                        </a>
                                    </div>
                                    
                                    <?php if (getUserType() === 'owner'): ?>
                                    <div class="col-md-3 mb-3">
                                        <a href="sales_records.php" class="card quick-action-btn text-decoration-none border-danger text-center">
                                            <div class="card-body">
                                                <i class="bi bi-cart-plus display-4 text-danger mb-2"></i>
                                                <h6>Record Sale</h6>
                                                <small class="text-muted">Add new sale</small>
                                            </div>
                                        </a>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <a href="expenses.php" class="card quick-action-btn text-decoration-none border-secondary text-center">
                                            <div class="card-body">
                                                <i class="bi bi-cash-coin display-4 text-secondary mb-2"></i>
                                                <h6>Add Expense</h6>
                                                <small class="text-muted">Record expense</small>
                                            </div>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Recent Activity & Alerts -->
            <div class="col-xl-4">
                <!-- Today's Transactions -->
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-info"></i> 
                            Today's Transactions
                        </h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($todayTransactions)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-check-circle display-4 d-block mb-2"></i>
                            No transactions today
                        </div>
                        <?php else: ?>
                            <?php foreach ($todayTransactions as $trans): ?>
                            <div class="activity-item">
                                <div class="d-flex align-items-center">
                                    <div class="activity-icon bg-<?php echo $trans['transaction_type'] == 'received' ? 'success' : 'danger'; ?> text-white">
                                        <i class="bi bi-<?php echo $trans['transaction_type'] == 'received' ? 'arrow-down-left' : 'arrow-up-right'; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <strong><?php echo $trans['item_name']; ?></strong>
                                            <span class="fw-bold <?php echo $trans['transaction_type'] == 'received' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $trans['transaction_type'] == 'received' ? '+' : '-'; ?>
                                                <?php echo $trans['quantity']; ?> <?php echo $trans['unit']; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            Stock: <?php echo $trans['new_stock']; ?> <?php echo $trans['unit']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Low Stock Alerts -->
                <?php if (!empty($lowStockItems)): ?>
                <div class="card dashboard-card border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Low Stock Alerts
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($lowStockItems as $item): ?>
                        <div class="alert alert-warning d-flex align-items-center mb-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div class="flex-grow-1">
                                <strong><?php echo $item['item_name']; ?></strong><br>
                                <small>
                                    Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?> • 
                                    Min: <?php echo $item['min_stock_level']; ?> <?php echo $item['unit']; ?>
                                </small>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="quickStockUpdate(<?php echo $item['id']; ?>)">
                                Reorder
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Sales -->
                <?php if (!empty($recentSales)): ?>
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up text-success"></i> 
                            Recent Sales
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentSales as $sale): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                            <div>
                                <strong><?php echo $sale['product_type']; ?></strong>
                                <div class="small text-muted">
                                    <?php echo date('M d', strtotime($sale['sale_date'])); ?> • 
                                    <?php echo $sale['quantity']; ?> units
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-success">
                                    ₦<?php echo number_format($sale['total_amount'], 2); ?>
                                </span>
                                <div class="small text-muted">
                                    <?php echo $sale['seller']; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Latest Production Summary -->
                <div class="card dashboard-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart text-primary"></i> 
                            Latest Production
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($farmAccess === 'poultry' || $farmAccess === 'both'): ?>
                            <?php if ($latestLayerRecord): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-egg-fried text-primary me-2"></i>
                                        <strong>Layers</strong>
                                    </span>
                                    <span class="badge bg-primary">
                                        <?php echo date('M d', strtotime($latestLayerRecord['record_date'])); ?>
                                    </span>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Eggs</small>
                                        <div class="fw-bold text-success"><?php echo $latestLayerRecord['egg_production']; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Rate</small>
                                        <div class="fw-bold <?php echo $latestLayerRecord['laying_rate'] > 80 ? 'text-success' : ($latestLayerRecord['laying_rate'] > 60 ? 'text-warning' : 'text-danger'); ?>">
                                            <?php echo $latestLayerRecord['laying_rate']; ?>%
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($latestBroilerRecord): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-basket text-info me-2"></i>
                                        <strong>Broilers</strong>
                                    </span>
                                    <span class="badge bg-info">
                                        <?php echo date('M d', strtotime($latestBroilerRecord['record_date'])); ?>
                                    </span>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Stock</small>
                                        <div class="fw-bold"><?php echo $latestBroilerRecord['opening_stock']; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Age</small>
                                        <div class="fw-bold"><?php echo $latestBroilerRecord['birds_age']; ?> days</div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($farmAccess === 'ruminant' || $farmAccess === 'both'): ?>
                            <?php if ($latestRuminantRecord): ?>
                            <div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <i class="bi bi-shield-plus text-warning me-2"></i>
                                        <strong>Ruminant</strong>
                                    </span>
                                    <span class="badge bg-warning">
                                        <?php echo date('M d', strtotime($latestRuminantRecord['record_date'])); ?>
                                    </span>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <small class="text-muted">Stock</small>
                                        <div class="fw-bold"><?php echo $latestRuminantRecord['opening_stock']; ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Type</small>
                                        <div class="fw-bold"><?php echo $latestRuminantRecord['animal_type']; ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Stock Update Modal -->
        <div class="modal fade" id="quickStockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form id="quickStockForm">
                        <div class="modal-header">
                            <h5 class="modal-title">Quick Stock Update</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="stockItemId">
                            
                            <div class="mb-3">
                                <label>Item</label>
                                <input type="text" class="form-control" id="stockItemName" readonly>
                                <small class="text-muted" id="stockItemDetails"></small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label>Transaction Type</label>
                                    <select class="form-select" id="transType" required>
                                        <option value="received">⬆ Received Stock (+)</option>
                                        <option value="used">⬇ Used Stock (-)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Quantity</label>
                                    <input type="number" class="form-control" id="quantity" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label>Remarks (Optional)</label>
                                <input type="text" class="form-control" id="remarks" placeholder="Enter remarks">
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <small>This will update stock in real-time and record the transaction.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
    // Initialize dashboard
    $(document).ready(function() {
        // Initialize tooltips
        $('[title]').tooltip();
        
        // Auto-refresh stock every 30 seconds
        setInterval(refreshStockData, 30000);
        
        // Check for new notifications
        checkNotifications();
    });
    
    // Filter stock table
    function filterStock(filterType) {
        const rows = document.querySelectorAll('#stockTable tbody tr');
        
        rows.forEach(row => {
            let showRow = true;
            
            if (filterType === 'low') {
                showRow = row.getAttribute('data-stock-status') === 'danger';
            } else if (filterType === 'poultry') {
                showRow = row.getAttribute('data-farm-type') === 'poultry';
            } else if (filterType === 'ruminant') {
                showRow = row.getAttribute('data-farm-type') === 'ruminant';
            }
            
            row.style.display = showRow ? '' : 'none';
        });
    }
    
    // Quick stock update
    function quickStockUpdate(itemId) {
        // Fetch item details
        fetch(`api/get_item_details.php?id=${itemId}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('stockItemId').value = itemId;
                    document.getElementById('stockItemName').value = data.item_name;
                    document.getElementById('stockItemDetails').textContent = 
                        `Current stock: ${data.current_stock} ${data.unit} • Min: ${data.min_stock_level} ${data.unit}`;
                    
                    const modal = new bootstrap.Modal(document.getElementById('quickStockModal'));
                    modal.show();
                }
            })
            .catch(error => {
                showAlert('danger', 'Error loading item details: ' + error.message);
            });
    }
    
    // Handle quick stock form submission
    document.getElementById('quickStockForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            item_id: document.getElementById('stockItemId').value,
            type: document.getElementById('transType').value,
            quantity: document.getElementById('quantity').value,
            remarks: document.getElementById('remarks').value,
            farm_type: '<?php echo $farmAccess; ?>'
        };
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Updating...';
        submitBtn.disabled = true;
        
        // Send request
        fetch('api/update_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', 'Stock updated successfully!');
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('quickStockModal')).hide();
                
                // Reload page after 1 second
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('danger', 'Error: ' + data.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            showAlert('danger', 'Network error: ' + error.message);
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
    
    // Refresh stock data
    function refreshStockData() {
        fetch(`api/get_stock_summary.php?farm_type=<?php echo $farmAccess; ?>`)
            .then(response => response.json())
            .then(data => {
                if (data && data.updated) {
                    // Update stock counters if changed significantly
                    const lowStockCount = data.low_stock_count || 0;
                    const currentLowCount = <?php echo $lowStockCount; ?>;
                    
                    if (Math.abs(lowStockCount - currentLowCount) > 0) {
                        // Show notification
                        if (lowStockCount > currentLowCount) {
                            showAlert('warning', `${lowStockCount - currentLowCount} new items are now low on stock!`);
                        }
                        
                        // Reload the page to show updated data
                        location.reload();
                    }
                }
            });
    }
    
    // Check for notifications
    function checkNotifications() {
        // Check for low stock notifications
        const lowStockItems = <?php echo json_encode($lowStockItems); ?>;
        if (lowStockItems.length > 0) {
            const notificationCount = lowStockItems.length;
            if (notificationCount > 0) {
                // Show persistent notification badge
                updateNotificationBadge(notificationCount);
                
                // Show initial alert if first visit
                if (!sessionStorage.getItem('stockAlertShown')) {
                    showAlert('warning', 
                        `You have ${notificationCount} item${notificationCount > 1 ? 's' : ''} with low stock. ` +
                        `Please reorder soon.`, 
                        10000);
                    sessionStorage.setItem('stockAlertShown', 'true');
                }
            }
        }
        
        // Check for pending tasks
        const today = '<?php echo date('Y-m-d'); ?>';
        fetch(`api/check_pending_tasks.php?farm_type=<?php echo $farmAccess; ?>&date=${today}`)
            .then(response => response.json())
            .then(data => {
                if (data.pending_tasks > 0) {
                    showAlert('info', 
                        `You have ${data.pending_tasks} pending task${data.pending_tasks > 1 ? 's' : ''} for today.`, 
                        8000);
                }
            });
    }
    
    // Update notification badge
    function updateNotificationBadge(count) {
        let badge = document.getElementById('notificationBadge');
        if (!badge) {
            badge = document.createElement('span');
            badge.id = 'notificationBadge';
            badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
            badge.style.fontSize = '0.6rem';
            
            const bellIcon = document.querySelector('.bi-bell');
            if (bellIcon) {
                bellIcon.parentElement.style.position = 'relative';
                bellIcon.parentElement.appendChild(badge);
            }
        }
        
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
    
    // Show alert message
    function showAlert(type, message, duration = 5000) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 350px;';
        alertDiv.innerHTML = `
            <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after duration
        setTimeout(() => {
            if (alertDiv.parentNode) {
                const bsAlert = new bootstrap.Alert(alertDiv);
                bsAlert.close();
            }
        }, duration);
    }
    
    // Auto-update time
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
        const dateString = now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        const timeElement = document.querySelector('.current-time');
        if (timeElement) {
            timeElement.textContent = `${dateString} • ${timeString}`;
        }
    }
    
    // Update time every minute
    setInterval(updateCurrentTime, 60000);
    updateCurrentTime(); // Initial call
    </script>
</body>
</html>