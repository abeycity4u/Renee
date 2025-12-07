<?php
require_once 'config.php';
requireLogin();

// Check access
if (!checkAccess('poultry') && getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$month = $_GET['month'] ?? date('Y-m');
$yearMonth = date('Y-m', strtotime($month));

// Get feed transactions for the month
$query = "SELECT t.*, s.item_name, s.unit, u.full_name 
          FROM stock_transactions t
          JOIN stock_items s ON t.stock_item_id = s.id
          LEFT JOIN users u ON t.user_id = u.id
          WHERE DATE_FORMAT(t.transaction_date, '%Y-m') = ?
          AND s.farm_type IN ('poultry', 'both')
          AND s.item_name LIKE '%broiler%'
          ORDER BY t.transaction_date DESC, t.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$yearMonth]);
$transactions = $stmt->fetchAll();

// Get current broiler feed stock
$stockQuery = "SELECT * FROM stock_items 
               WHERE farm_type IN ('poultry', 'both') 
               AND item_name LIKE '%broiler%'
               ORDER BY current_stock ASC";
$stockStmt = $pdo->query($stockQuery);
$feedItems = $stockStmt->fetchAll();

// Handle new transaction
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_transaction'])) {
    $itemId = $_POST['feed_item'];
    $type = $_POST['transaction_type'];
    $quantity = $_POST['quantity'];
    $date = $_POST['transaction_date'];
    
    // Get current stock
    $itemStmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ?");
    $itemStmt->execute([$itemId]);
    $item = $itemStmt->fetch();
    
    if ($item) {
        $previousStock = $item['current_stock'];
        
        if ($type == 'received') {
            $newStock = $previousStock + $quantity;
        } else {
            if ($quantity > $previousStock) {
                $_SESSION['error'] = "Insufficient stock. Available: {$previousStock} {$item['unit']}";
                header("Location: broiler_feeds.php?month={$month}");
                exit();
            }
            $newStock = $previousStock - $quantity;
        }
        
        // Update stock
        $updateStmt = $pdo->prepare("UPDATE stock_items SET current_stock = ? WHERE id = ?");
        $updateStmt->execute([$newStock, $itemId]);
        
        // Record transaction
        $transStmt = $pdo->prepare("INSERT INTO stock_transactions 
            (stock_item_id, transaction_type, quantity, previous_stock, new_stock, 
             transaction_date, remarks, user_id, farm_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'poultry')");
        $transStmt->execute([
            $itemId,
            $type,
            $quantity,
            $previousStock,
            $newStock,
            $date,
            $_POST['remarks'],
            $_SESSION['user_id']
        ]);
        
        $_SESSION['success'] = "Feed transaction recorded successfully!";
        header("Location: broiler_feeds.php?month={$month}");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <?php include 'navbar_head.php'; ?> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broiler Feeds Record - Renee Farms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .stock-card {
            transition: transform 0.2s;
        }
        .stock-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="bi bi-basket"></i> 
                            Broiler Feeds Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <input type="month" class="form-control" id="monthSelector" 
                                   value="<?php echo $yearMonth; ?>" style="width: 200px;">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTransactionModal">
                                <i class="bi bi-plus-circle"></i> New Transaction
                            </button>
                        </div>
                    </div>
                    
                    <!-- Current Stock Summary -->
                    <div class="card-body">
                        <h5 class="mb-3">Current Feed Stock</h5>
                        <div class="row mb-4">
                            <?php foreach ($feedItems as $item): 
                                $stockPercent = ($item['current_stock'] / ($item['min_stock_level'] * 2)) * 100;
                                $cardClass = $item['current_stock'] <= $item['min_stock_level'] ? 'border-danger' : 
                                            ($stockPercent <= 50 ? 'border-warning' : 'border-success');
                            ?>
                            <div class="col-md-3 mb-3">
                                <div class="card stock-card <?php echo $cardClass; ?>">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?php echo $item['item_name']; ?></h6>
                                        <div class="mb-2">
                                            <span class="display-6 fw-bold <?php echo $item['current_stock'] <= $item['min_stock_level'] ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo $item['current_stock']; ?>
                                            </span>
                                            <small class="text-muted d-block"><?php echo $item['unit']; ?></small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo $item['current_stock'] <= $item['min_stock_level'] ? 'bg-danger' : 'bg-success'; ?>" 
                                                 style="width: <?php echo min($stockPercent, 100); ?>%"></div>
                                        </div>
                                        <small class="text-muted">Min: <?php echo $item['min_stock_level']; ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Monthly Summary -->
                        <?php
                        $monthlySummary = [
                            'received' => 0,
                            'used' => 0,
                            'balance' => 0
                        ];
                        
                        foreach ($transactions as $trans) {
                            if ($trans['transaction_type'] == 'received') {
                                $monthlySummary['received'] += $trans['quantity'];
                            } else {
                                $monthlySummary['used'] += $trans['quantity'];
                            }
                        }
                        $monthlySummary['balance'] = $monthlySummary['received'] - $monthlySummary['used'];
                        ?>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h6>Received This Month</h6>
                                        <h3>+<?php echo number_format($monthlySummary['received'], 2); ?></h3>
                                        <small>Bags</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h6>Used This Month</h6>
                                        <h3>-<?php echo number_format($monthlySummary['used'], 2); ?></h3>
                                        <small>Bags</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h6>Net Change</h6>
                                        <h3><?php echo $monthlySummary['balance'] >= 0 ? '+' : ''; ?><?php echo number_format($monthlySummary['balance'], 2); ?></h3>
                                        <small>Bags</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Transactions Table -->
                        <h5>Monthly Transactions</h5>
                        <div class="table-responsive">
                            <table id="feedsTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Feed Item</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Previous</th>
                                        <th>New</th>
                                        <th>Remarks</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No transactions recorded for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($transactions as $trans): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo date('d/m', strtotime($trans['transaction_date'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $trans['item_name']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $trans['transaction_type'] == 'received' ? 'success' : 'danger'; ?>">
                                                    <?php echo strtoupper($trans['transaction_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold <?php echo $trans['transaction_type'] == 'received' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $trans['transaction_type'] == 'received' ? '⬆ +' : '⬇ -'; ?>
                                                    <?php echo $trans['quantity']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $trans['previous_stock']; ?></td>
                                            <td class="fw-bold"><?php echo $trans['new_stock']; ?></td>
                                            <td>
                                                <?php if ($trans['remarks']): ?>
                                                <small class="text-muted"><?php echo $trans['remarks']; ?></small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo $trans['full_name']; ?></small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Transaction Modal -->
    <div class="modal fade" id="addTransactionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Feed Transaction</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="transaction_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label>Feed Item</label>
                            <select name="feed_item" class="form-select" required>
                                <option value="">Select Feed</option>
                                <?php foreach ($feedItems as $item): ?>
                                <option value="<?php echo $item['id']; ?>">
                                    <?php echo $item['item_name']; ?> 
                                    (Available: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Transaction Type</label>
                                <select name="transaction_type" class="form-select" required onchange="updateQuantityLabel()">
                                    <option value="received">⬆ Received Stock (+)</option>
                                    <option value="used">⬇ Used Stock (-)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label id="quantityLabel">Quantity</label>
                                <input type="number" name="quantity" class="form-control" 
                                       step="0.01" min="0.01" required id="quantityInput">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Remarks</label>
                            <input type="text" name="remarks" class="form-control" 
                                   placeholder="e.g., From supplier, For broiler house A, etc.">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_transaction" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#feedsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25
        });
        
        // Month selector
        $('#monthSelector').change(function() {
            window.location.href = 'broiler_feeds.php?month=' + this.value;
        });
        
        // Show messages
        <?php if (isset($_SESSION['success'])): ?>
        showAlert('success', '<?php echo $_SESSION['success']; ?>');
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        showAlert('danger', '<?php echo $_SESSION['error']; ?>');
        <?php unset($_SESSION['error']); endif; ?>
    });
    
    function updateQuantityLabel() {
        const type = document.querySelector('select[name="transaction_type"]').value;
        const label = document.getElementById('quantityLabel');
        const input = document.getElementById('quantityInput');
        
        if (type === 'used') {
            label.innerHTML = 'Quantity <small class="text-danger">(will be subtracted)</small>';
            input.min = 0.01;
        } else {
            label.innerHTML = 'Quantity <small class="text-success">(will be added)</small>';
            input.min = 0.01;
        }
    }
    
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
    
    // Initialize quantity label
    document.addEventListener('DOMContentLoaded', updateQuantityLabel);
    </script>
</body>
</html>