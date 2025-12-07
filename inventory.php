<?php
require_once 'config.php';
requireLogin();

// Check access
if (!checkAccess('poultry') && !checkAccess('ruminant') && getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$userType = getUserType();
$farmType = $userType === 'owner' ? 'all' : ($userType === 'poultry_manager' ? 'poultry' : 'ruminant');

// Get inventory items
if ($farmType === 'all') {
    $query = "SELECT si.*, ic.category_name, 
              CASE 
                WHEN si.current_stock <= si.min_stock_level THEN 'danger'
                WHEN si.current_stock <= si.min_stock_level * 2 THEN 'warning'
                ELSE 'success'
              END as status_class
              FROM stock_items si 
              JOIN inventory_categories ic ON si.category_id = ic.id 
              ORDER BY si.current_stock ASC";
    $stmt = $pdo->query($query);
} else {
    $query = "SELECT si.*, ic.category_name,
              CASE 
                WHEN si.current_stock <= si.min_stock_level THEN 'danger'
                WHEN si.current_stock <= si.min_stock_level * 2 THEN 'warning'
                ELSE 'success'
              END as status_class
              FROM stock_items si 
              JOIN inventory_categories ic ON si.category_id = ic.id 
              WHERE si.farm_type IN (?, 'both') 
              ORDER BY si.current_stock ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$farmType]);
}

$inventoryItems = $stmt->fetchAll();

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM inventory_categories ORDER BY category_name")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_item'])) {
        $stmt = $pdo->prepare("INSERT INTO stock_items 
            (item_name, category_id, current_stock, min_stock_level, unit, farm_type) 
            VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['item_name'],
            $_POST['category_id'],
            $_POST['initial_stock'],
            $_POST['min_stock'],
            $_POST['unit'],
            $_POST['farm_type']
        ]);
        
        $_SESSION['success'] = "Item added successfully!";
        header('Location: inventory.php');
        exit();
    }
    
    if (isset($_POST['update_stock'])) {
        $itemId = $_POST['item_id'];
        $type = $_POST['transaction_type'];
        $quantity = $_POST['quantity'];
        
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
                    $_SESSION['error'] = "Insufficient stock. Available: {$previousStock}";
                    header('Location: inventory.php');
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
                VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)");
            $transStmt->execute([
                $itemId,
                $type,
                $quantity,
                $previousStock,
                $newStock,
                $_POST['remarks'],
                $_SESSION['user_id'],
                $item['farm_type']
            ]);
            
            $_SESSION['success'] = "Stock updated successfully!";
            header('Location: inventory.php');
            exit();
        }
    }
    
    if (isset($_POST['delete_item'])) {
        $itemId = $_POST['item_id'];
        
        // Check if item has transactions
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_transactions WHERE stock_item_id = ?");
        $checkStmt->execute([$itemId]);
        $hasTransactions = $checkStmt->fetchColumn() > 0;
        
        if ($hasTransactions) {
            $_SESSION['error'] = "Cannot delete item with transaction history. Please deactivate instead.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ?");
            $stmt->execute([$itemId]);
            $_SESSION['success'] = "Item deleted successfully!";
        }
        
        header('Location: inventory.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Renee Farms</title>
    
    <!-- Include CSS -->
    <?php include 'navbar_head.php'; ?>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <style>
        .stock-progress {
            height: 20px;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .stock-item-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        
        .stock-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stock-status {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .stock-status-low {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .stock-status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .stock-status-good {
            background-color: #d4edda;
            color: #155724;
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
                        <h4><i class="bi bi-box-seam"></i> Inventory Management</h4>
                        <div>
                            <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="bi bi-plus-circle"></i> Add New Item
                            </button>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#updateStockModal">
                                <i class="bi bi-arrow-up-down"></i> Update Stock
                            </button>
                        </div>
                    </div>
                    
                    <!-- Inventory Summary -->
                    <div class="card-body">
                        <div class="row mb-4">
                            <?php
                            $totalItems = count($inventoryItems);
                            $lowStockItems = 0;
                            $moderateStockItems = 0;
                            $goodStockItems = 0;
                            
                            foreach ($inventoryItems as $item) {
                                if ($item['current_stock'] <= $item['min_stock_level']) {
                                    $lowStockItems++;
                                } elseif ($item['current_stock'] <= $item['min_stock_level'] * 2) {
                                    $moderateStockItems++;
                                } else {
                                    $goodStockItems++;
                                }
                            }
                            ?>
                            
                            <div class="col-md-3">
                                <div class="card text-white bg-primary">
                                    <div class="card-body text-center">
                                        <h6>Total Items</h6>
                                        <h2><?php echo $totalItems; ?></h2>
                                        <small>In Inventory</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card text-white bg-danger">
                                    <div class="card-body text-center">
                                        <h6>Low Stock</h6>
                                        <h2><?php echo $lowStockItems; ?></h2>
                                        <small>Need Reorder</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card text-white bg-warning">
                                    <div class="card-body text-center">
                                        <h6>Moderate</h6>
                                        <h2><?php echo $moderateStockItems; ?></h2>
                                        <small>Monitor Closely</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card text-white bg-success">
                                    <div class="card-body text-center">
                                        <h6>Good Stock</h6>
                                        <h2><?php echo $goodStockItems; ?></h2>
                                        <small>Adequate Supply</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Inventory Table -->
                        <div class="table-responsive">
                            <table id="inventoryTable" class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Min Level</th>
                                        <th>Unit</th>
                                        <th>Farm Type</th>
                                        <th>Status</th>
                                        <th>Stock Level</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventoryItems as $item): 
                                        $stockPercentage = ($item['current_stock'] / ($item['min_stock_level'] * 3)) * 100;
                                        $statusClass = $item['status_class'];
                                        $statusText = $statusClass == 'danger' ? 'Low Stock' : 
                                                    ($statusClass == 'warning' ? 'Moderate' : 'Good');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                        <td>
                                            <span class="fw-bold <?php echo "text-$statusClass"; ?>">
                                                <?php echo $item['current_stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['min_stock_level']; ?></td>
                                        <td><?php echo $item['unit']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['farm_type'] == 'poultry' ? 'info' : 'warning'; ?>">
                                                <?php echo ucfirst($item['farm_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="stock-status stock-status-<?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="progress stock-progress">
                                                <div class="progress-bar bg-<?php echo $statusClass; ?>" 
                                                     style="width: <?php echo min($stockPercentage, 100); ?>%"
                                                     role="progressbar">
                                                    <?php echo round($stockPercentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="quickUpdateStock(<?php echo $item['id']; ?>, '<?php echo $item['item_name']; ?>')"
                                                    title="Quick Update">
                                                <i class="bi bi-arrow-up-down"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="viewHistory(<?php echo $item['id']; ?>)"
                                                    title="View History">
                                                <i class="bi bi-clock-history"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="deleteItem(<?php echo $item['id']; ?>)"
                                                    title="Delete Item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
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

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Inventory Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Item Name</label>
                            <input type="text" name="item_name" class="form-control" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Category</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Farm Type</label>
                                <select name="farm_type" class="form-select" required>
                                    <option value="poultry">Poultry</option>
                                    <option value="ruminant">Ruminant</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Initial Stock</label>
                                <input type="number" name="initial_stock" class="form-control" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Minimum Stock Level</label>
                                <input type="number" name="min_stock" class="form-control" 
                                       step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Unit</label>
                                <input type="text" name="unit" class="form-control" 
                                       placeholder="bags, kg, vials, etc." required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_item" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Stock Modal -->
    <div class="modal fade" id="updateStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="updateStockForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="item_id" id="updateItemId">
                        
                        <div class="mb-3">
                            <label>Item</label>
                            <select class="form-select" id="updateItemSelect" required 
                                    onchange="updateItemInfo(this.value)">
                                <option value="">Select Item</option>
                                <?php foreach ($inventoryItems as $item): ?>
                                <option value="<?php echo $item['id']; ?>" 
                                        data-stock="<?php echo $item['current_stock']; ?>"
                                        data-unit="<?php echo $item['unit']; ?>">
                                    <?php echo htmlspecialchars($item['item_name']); ?> 
                                    (Current: <?php echo $item['current_stock']; ?> <?php echo $item['unit']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="itemInfo" class="mt-2 small text-muted"></div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Transaction Type</label>
                                <select name="transaction_type" class="form-select" required 
                                        onchange="updateQuantityLabel()">
                                    <option value="received">Received Stock (+)</option>
                                    <option value="used">Used Stock (-)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label id="quantityLabel">Quantity</label>
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
                        <button type="submit" name="update_stock" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item?</p>
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="item_id" id="deleteItemId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="deleteForm" name="delete_item" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#inventoryTable').DataTable({
            pageLength: 25,
            order: [[2, 'asc']]
        });
        
        // Show messages
        <?php if (isset($_SESSION['success'])): ?>
        showAlert('success', '<?php echo addslashes($_SESSION['success']); ?>');
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        showAlert('danger', '<?php echo addslashes($_SESSION['error']); ?>');
        <?php unset($_SESSION['error']); endif; ?>
    });
    
    // Update item info when select changes
    function updateItemInfo(itemId) {
        const selectedOption = document.querySelector(`#updateItemSelect option[value="${itemId}"]`);
        if (selectedOption) {
            const currentStock = selectedOption.dataset.stock;
            const unit = selectedOption.dataset.unit;
            document.getElementById('updateItemId').value = itemId;
            document.getElementById('itemInfo').innerHTML = `
                Current stock: <strong>${currentStock} ${unit}</strong><br>
                Selected item: <strong>${selectedOption.textContent.split(' (Current:')[0]}</strong>
            `;
        }
    }
    
    // Update quantity label based on transaction type
    function updateQuantityLabel() {
        const type = document.querySelector('select[name="transaction_type"]').value;
        const label = document.getElementById('quantityLabel');
        const input = document.querySelector('input[name="quantity"]');
        
        if (type === 'used') {
            label.innerHTML = 'Quantity <small class="text-danger">(will be subtracted)</small>';
            input.min = 0.01;
        } else {
            label.innerHTML = 'Quantity <small class="text-success">(will be added)</small>';
            input.min = 0.01;
        }
    }
    
    // Quick update stock
    function quickUpdateStock(itemId, itemName) {
        document.getElementById('updateItemId').value = itemId;
        document.getElementById('updateItemSelect').value = itemId;
        updateItemInfo(itemId);
        
        const modal = new bootstrap.Modal(document.getElementById('updateStockModal'));
        modal.show();
    }
    
    // View stock history
    function viewHistory(itemId) {
        window.location.href = `stock_history.php?item_id=${itemId}`;
    }
    
    // Delete item confirmation
    function deleteItem(itemId) {
        document.getElementById('deleteItemId').value = itemId;
        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        modal.show();
    }
    
    // Initialize quantity label
    document.addEventListener('DOMContentLoaded', updateQuantityLabel);
    </script>
</body>
</html>