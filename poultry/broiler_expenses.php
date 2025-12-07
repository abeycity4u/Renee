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

// Get expenses for the month
$query = "SELECT e.*, u.full_name 
          FROM farm_expenses e
          LEFT JOIN users u ON e.user_id = u.id
          WHERE DATE_FORMAT(e.expense_date, '%Y-m') = ?
          AND e.farm_type IN ('poultry', 'both')
          AND (e.description LIKE '%broiler%' OR e.category = 'feeds')
          ORDER BY e.expense_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$yearMonth]);
$expenses = $stmt->fetchAll();

// Calculate totals
$totalExpenses = 0;
$categoryTotals = [];

foreach ($expenses as $expense) {
    $totalExpenses += $expense['amount'];
    $categoryTotals[$expense['category']] = ($categoryTotals[$expense['category']] ?? 0) + $expense['amount'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_expense'])) {
    $stmt = $pdo->prepare("INSERT INTO farm_expenses 
        (expense_date, farm_type, category, amount, description, user_id) 
        VALUES (?, 'poultry', ?, ?, ?, ?)");
    
    $stmt->execute([
        $_POST['expense_date'],
        $_POST['category'],
        $_POST['amount'],
        $_POST['description'],
        $_SESSION['user_id']
    ]);
    
    $_SESSION['success'] = "Broiler expense recorded successfully!";
    header("Location: broiler_expenses.php?month=" . $month);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'navbar_head.php'; ?> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broiler Expenses Record - Renee Farms</title>
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
                            <i class="bi bi-cash-stack"></i> 
                            Broiler Expenses - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <input type="month" class="form-control" id="monthSelector" 
                                   value="<?php echo $yearMonth; ?>" style="width: 200px;">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                                <i class="bi bi-plus-circle"></i> Add Expense
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Summary -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h2>TOTAL BROILER EXPENSES: ₦<?php echo number_format($totalExpenses, 2); ?></h2>
                                        <h5>For <?php echo date('F Y', strtotime($yearMonth)); ?></h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Breakdown -->
                        <div class="row mb-4">
                            <?php foreach ($categoryTotals as $category => $total): ?>
                            <div class="col-md-2 mb-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h6 class="card-title text-uppercase"><?php echo $category; ?></h6>
                                        <h4 class="text-danger">₦<?php echo number_format($total, 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Expenses Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                        <th>Recorded By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No expenses recorded for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo ucfirst($expense['category']); ?>
                                                </span>
                                            </td>
                                            <td class="text-danger fw-bold">
                                                ₦<?php echo number_format($expense['amount'], 2); ?>
                                            </td>
                                            <td><?php echo $expense['description'] ?: '--'; ?></td>
                                            <td><small><?php echo $expense['full_name']; ?></small></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i>
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

    <!-- Add Expense Modal -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Broiler Expense</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Date</label>
                            <input type="date" name="expense_date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Category</label>
                            <select name="category" class="form-select" required>
                                <option value="feeds">Feeds</option>
                                <option value="medication">Medication</option>
                                <option value="salary">Salary</option>
                                <option value="logistic">Logistic</option>
                                <option value="fuel">Fuel</option>
                                <option value="misc">Miscellaneous</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Amount (₦)</label>
                            <input type="number" name="amount" class="form-control" 
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('monthSelector').addEventListener('change', function() {
        window.location.href = 'broiler_expenses.php?month=' + this.value;
    });
    
    <?php if (isset($_SESSION['success'])): ?>
    alert('Success: <?php echo $_SESSION['success']; ?>');
    <?php unset($_SESSION['success']); endif; ?>
    </script>
</body>
</html>