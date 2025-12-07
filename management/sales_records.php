<?php
require_once 'config.php';
requireLogin();

// Only owner can access sales
if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$month = $_GET['month'] ?? date('Y-m');
$farmType = $_GET['farm_type'] ?? 'all';

// Build query based on filters
if ($farmType === 'all') {
    $salesQuery = "SELECT s.*, u.full_name as seller 
                   FROM sales_records s 
                   LEFT JOIN users u ON s.user_id = u.id 
                   WHERE DATE_FORMAT(s.sale_date, '%Y-m') = ? 
                   ORDER BY s.sale_date DESC";
    $salesStmt = $pdo->prepare($salesQuery);
    $salesStmt->execute([$month]);
} else {
    $salesQuery = "SELECT s.*, u.full_name as seller 
                   FROM sales_records s 
                   LEFT JOIN users u ON s.user_id = u.id 
                   WHERE DATE_FORMAT(s.sale_date, '%Y-m') = ? 
                   AND s.farm_type = ? 
                   ORDER BY s.sale_date DESC";
    $salesStmt = $pdo->prepare($salesQuery);
    $salesStmt->execute([$month, $farmType]);
}

$salesRecords = $salesStmt->fetchAll();

// Get sales summary
if ($farmType === 'all') {
    $summaryQuery = "SELECT 
                     SUM(total_amount) as total_sales,
                     COUNT(*) as transaction_count,
                     AVG(unit_price) as avg_price,
                     farm_type
                     FROM sales_records 
                     WHERE DATE_FORMAT(sale_date, '%Y-m') = ? 
                     GROUP BY farm_type";
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute([$month]);
    $summaries = $summaryStmt->fetchAll();
} else {
    $summaryQuery = "SELECT 
                     SUM(total_amount) as total_sales,
                     COUNT(*) as transaction_count,
                     AVG(unit_price) as avg_price
                     FROM sales_records 
                     WHERE DATE_FORMAT(sale_date, '%Y-m') = ? 
                     AND farm_type = ?";
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute([$month, $farmType]);
    $summary = $summaryStmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_sale'])) {
        $stmt = $pdo->prepare("INSERT INTO sales_records 
            (sale_date, farm_type, product_type, quantity, unit_price, 
             customer_name, remarks, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['sale_date'],
            $_POST['farm_type'],
            $_POST['product_type'],
            $_POST['quantity'],
            $_POST['unit_price'],
            $_POST['customer_name'],
            $_POST['remarks'],
            $_SESSION['user_id']
        ]);
        
        $_SESSION['success'] = "Sale recorded successfully!";
        header("Location: sales_records.php?month={$month}&farm_type={$farmType}");
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
    <title>Sales Records - Renee Farms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>
                            <i class="bi bi-graph-up"></i> 
                            Sales Records - <?php echo date('F Y', strtotime($month)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <select class="form-select" id="farmTypeFilter" style="width: 150px;">
                                <option value="all" <?php echo $farmType == 'all' ? 'selected' : ''; ?>>All Farms</option>
                                <option value="poultry" <?php echo $farmType == 'poultry' ? 'selected' : ''; ?>>Poultry</option>
                                <option value="ruminant" <?php echo $farmType == 'ruminant' ? 'selected' : ''; ?>>Ruminant</option>
                            </select>
                            <input type="month" class="form-control" id="monthFilter" 
                                   value="<?php echo $month; ?>" style="width: 200px;">
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSaleModal">
                                <i class="bi bi-plus-circle"></i> Add Sale
                            </button>
                        </div>
                    </div>
                    
                    <!-- Sales Summary -->
                    <div class="card-body bg-light">
                        <?php if ($farmType === 'all' && !empty($summaries)): ?>
                        <div class="row mb-4">
                            <?php foreach ($summaries as $summary): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card <?php echo $summary['farm_type'] == 'poultry' ? 'border-primary' : 'border-warning'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="card-title text-uppercase">
                                                    <?php echo $summary['farm_type']; ?> Sales
                                                </h6>
                                                <h3 class="text-success">₦<?php echo number_format($summary['total_sales'], 2); ?></h3>
                                                <small class="text-muted">
                                                    <?php echo $summary['transaction_count']; ?> transactions
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="d-block">Avg Price</small>
                                                <h5>₦<?php echo number_format($summary['avg_price'], 2); ?></h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php 
                            $totalAllSales = array_sum(array_column($summaries, 'total_sales'));
                            $totalAllTransactions = array_sum(array_column($summaries, 'transaction_count'));
                            ?>
                            <div class="col-md-12 mt-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h2>TOTAL SALES: ₦<?php echo number_format($totalAllSales, 2); ?></h2>
                                        <h5><?php echo $totalAllTransactions; ?> Total Transactions</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php elseif (isset($summary)): ?>
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card text-white bg-success">
                                    <div class="card-body text-center">
                                        <h6>Total Sales</h6>
                                        <h2>₦<?php echo number_format($summary['total_sales'], 2); ?></h2>
                                        <small>For <?php echo date('F Y', strtotime($month)); ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-white bg-info">
                                    <div class="card-body text-center">
                                        <h6>Transactions</h6>
                                        <h2><?php echo $summary['transaction_count']; ?></h2>
                                        <small>Sales recorded</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card text-white bg-warning">
                                    <div class="card-body text-center">
                                        <h6>Average Price</h6>
                                        <h2>₦<?php echo number_format($summary['avg_price'], 2); ?></h2>
                                        <small>Per unit</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Sales Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Farm Type</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Amount</th>
                                        <th>Customer</th>
                                        <th>Remarks</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($salesRecords)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-4">
                                            <i class="bi bi-cart display-4 d-block mb-2"></i>
                                            No sales recorded for this period
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($salesRecords as $sale): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $sale['farm_type'] == 'poultry' ? 'info' : 'warning'; ?>">
                                                    <?php echo ucfirst($sale['farm_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo $sale['product_type']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $sale['quantity']; ?></td>
                                            <td>₦<?php echo number_format($sale['unit_price'], 2); ?></td>
                                            <td class="text-success fw-bold">
                                                ₦<?php echo number_format($sale['total_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo $sale['customer_name'] ?: '--'; ?>
                                            </td>
                                            <td>
                                                <?php if ($sale['remarks']): ?>
                                                <small class="text-muted"><?php echo substr($sale['remarks'], 0, 20); ?>...</small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo $sale['seller']; ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editSale(<?php echo $sale['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteSale(<?php echo $sale['id']; ?>)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
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

    <!-- Add Sale Modal -->
    <div class="modal fade" id="addSaleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Record New Sale</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Sale Date</label>
                                <input type="date" name="sale_date" class="form-control" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Farm Type</label>
                                <select name="farm_type" class="form-select" required>
                                    <option value="poultry">Poultry</option>
                                    <option value="ruminant">Ruminant</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Product Type</label>
                                <input type="text" name="product_type" class="form-control" 
                                       placeholder="e.g., Eggs, Broilers, Milk, Meat" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Quantity</label>
                                <input type="number" name="quantity" class="form-control" 
                                       step="0.01" min="0.01" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Unit Price (₦)</label>
                                <input type="number" name="unit_price" class="form-control" 
                                       step="0.01" min="0.01" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Total Amount</label>
                                <input type="text" class="form-control" id="totalAmount" 
                                       value="₦0.00" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   placeholder="Optional">
                        </div>
                        
                        <div class="mb-3">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_sale" class="btn btn-primary">Record Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    $(document).ready(function() {
        // Filter change
        $('#farmTypeFilter, #monthFilter').change(function() {
            const farmType = $('#farmTypeFilter').val();
            const month = $('#monthFilter').val();
            window.location.href = `sales_records.php?month=${month}&farm_type=${farmType}`;
        });
        
        // Auto-calculate total amount
        $('input[name="quantity"], input[name="unit_price"]').on('input', function() {
            const quantity = parseFloat($('input[name="quantity"]').val()) || 0;
            const unitPrice = parseFloat($('input[name="unit_price"]').val()) || 0;
            const total = quantity * unitPrice;
            $('#totalAmount').val('₦' + total.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));
        });
    });
    
    function editSale(saleId) {
        alert('Edit functionality coming soon for sale ID: ' + saleId);
    }
    
    function deleteSale(saleId) {
        if (confirm('Are you sure you want to delete this sale record?')) {
            fetch('api/delete_sale.php?id=' + saleId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
        }
    }
    
    // Show messages
    <?php if (isset($_SESSION['success'])): ?>
    alert('Success: <?php echo $_SESSION['success']; ?>');
    <?php unset($_SESSION['success']); endif; ?>
    </script>
</body>
</html>