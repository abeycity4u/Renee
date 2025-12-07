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
          WHERE DATE_FORMAT(t.transaction_date, '%Y-%m') = ?
          AND s.farm_type IN ('poultry', 'both')
          AND s.item_name LIKE '%layer%'
          ORDER BY t.transaction_date DESC, t.id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$yearMonth]);
$transactions = $stmt->fetchAll();

// Get current layer feed stock
$stockQuery = "SELECT * FROM stock_items 
               WHERE farm_type IN ('poultry', 'both') 
               AND item_name LIKE '%layer%'
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
                header("Location: layer_feeds.php?month={$month}");
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
        header("Location: layer_feeds.php?month={$month}");
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
    <title>Layer Feeds Record - Renee Farms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="bi bi-bucket"></i> 
                            Layer Feeds Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
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
                    <div class="card-body bg-light">
                        <h5>Current Feed Stock</h5>
                        <div class="row mb-4">
                            <?php foreach ($feedItems as $item): ?>
                            <div class="col-md-3 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?php echo $item['item_name']; ?></h6>
                                        <h2 class="<?php echo $item['current_stock'] <= $item['min_stock_level'] ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $item['current_stock']; ?>
                                        </h2>
                                        <small class="text-muted"><?php echo $item['unit']; ?></small>
                                        <?php if ($item['current_stock'] <= $item['min_stock_level']): ?>
                                        <div class="mt-2">
                                            <span class="badge bg-danger">Low Stock!</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Transactions Table -->
                        <h5>Monthly Transactions</h5>
                        <div class="table-responsive">
                            <table id="feedsTable" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Feed Item</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Previous Stock</th>
                                        <th>New Stock</th>
                                        <th>Remarks</th>
                                        <th>Recorded By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $trans): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($trans['transaction_date'])); ?></td>
                                        <td><?php echo $trans['item_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $trans['transaction_type'] == 'received' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($trans['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold <?php echo $trans['transaction_type'] == 'received' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $trans['transaction_type'] == 'received' ? '+' : '-'; ?>
                                                <?php echo $trans['quantity']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $trans['previous_stock']; ?></td>
                                        <td class="fw-bold"><?php echo $trans['new_stock']; ?></td>
                                        <td><?php echo $trans['remarks'] ?: '--'; ?></td>
                                        <td><?php echo $trans['full_name']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
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
                                    (Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Transaction Type</label>
                                <select name="transaction_type" class="form-select" required>
                                    <option value="received">Received Stock (+)</option>
                                    <option value="used">Used Stock (-)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Quantity</label>
                                <input type="number" name="quantity" class="form-control" 
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Remarks</label>
                            <input type="text" name="remarks" class="form-control" 
                                   placeholder="Optional remarks">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_transaction" class="btn btn-primary">Save Transaction</button>
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
            order: [[0, 'desc']]
        });
        
        // Month selector
        $('#monthSelector').change(function() {
            window.location.href = 'layer_feeds.php?month=' + this.value;
        });
        
        // Show success/error messages
        <?php if (isset($_SESSION['success'])): ?>
        alert('<?php echo $_SESSION['success']; unset($_SESSION['success']); ?>');
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        alert('Error: <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>');
        <?php endif; ?>
    });
    </script>
</body>
</html>