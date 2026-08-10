<?php
require_once 'config.php';
requireLogin();

// Only owner can access all expenses
if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$month = $_GET['month'] ?? date('Y-m');
$farmType = $_GET['farm_type'] ?? 'all';
$category = $_GET['category'] ?? 'all';

// Build query based on filters
$whereClause = "WHERE DATE_FORMAT(e.expense_date, '%Y-m') = ?";
$params = [$month];

if ($farmType !== 'all') {
    $whereClause .= " AND e.farm_type = ?";
    $params[] = $farmType;
}

if ($category !== 'all') {
    $whereClause .= " AND e.category = ?";
    $params[] = $category;
}

$query = "SELECT e.*, u.full_name 
          FROM farm_expenses e
          LEFT JOIN users u ON e.user_id = u.id
          {$whereClause}
          ORDER BY e.expense_date DESC";
          
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Calculate totals
$totalExpenses = 0;
$categoryTotals = [];
$farmTypeTotals = [];

foreach ($expenses as $expense) {
    $totalExpenses += $expense['amount'];
    $categoryTotals[$expense['category']] = ($categoryTotals[$expense['category']] ?? 0) + $expense['amount'];
    $farmTypeTotals[$expense['farm_type']] = ($farmTypeTotals[$expense['farm_type']] ?? 0) + $expense['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'navbar_head.php'; ?> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Report - Renee Farms</title>
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
                            Expense Report - <?php echo date('F Y', strtotime($month)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <select class="form-select" id="farmTypeFilter" style="width: 150px;">
                                <option value="all" <?php echo $farmType == 'all' ? 'selected' : ''; ?>>All Farms</option>
                                <option value="poultry" <?php echo $farmType == 'poultry' ? 'selected' : ''; ?>>Poultry</option>
                                <option value="ruminant" <?php echo $farmType == 'ruminant' ? 'selected' : ''; ?>>Ruminant</option>
                                <option value="both" <?php echo $farmType == 'both' ? 'selected' : ''; ?>>Both</option>
                            </select>
                            <select class="form-select" id="categoryFilter" style="width: 150px;">
                                <option value="all" <?php echo $category == 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="feeds" <?php echo $category == 'feeds' ? 'selected' : ''; ?>>Feeds</option>
                                <option value="medication" <?php echo $category == 'medication' ? 'selected' : ''; ?>>Medication</option>
                                <option value="salary" <?php echo $category == 'salary' ? 'selected' : ''; ?>>Salary</option>
                                <option value="logistic" <?php echo $category == 'logistic' ? 'selected' : ''; ?>>Logistic</option>
                                <option value="fuel" <?php echo $category == 'fuel' ? 'selected' : ''; ?>>Fuel</option>
                                <option value="misc" <?php echo $category == 'misc' ? 'selected' : ''; ?>>Misc</option>
                            </select>
                            <input type="month" class="form-control" id="monthFilter" 
                                   value="<?php echo $month; ?>" style="width: 200px;">
                            <a class="btn btn-outline-primary"
                               href="print_expense_report.php?period=monthly&month=<?php echo urlencode($month); ?>&farm_type=<?php echo urlencode($farmType); ?>&category=<?php echo urlencode($category); ?>"
                               target="_blank">
                                <i class="bi bi-printer"></i> Print Monthly
                            </a>
                            <a class="btn btn-outline-secondary"
                               href="print_expense_report.php?period=yearly&year=<?php echo date('Y', strtotime($month)); ?>&farm_type=<?php echo urlencode($farmType); ?>&category=<?php echo urlencode($category); ?>"
                               target="_blank">
                                <i class="bi bi-printer"></i> Print Yearly
                            </a>
                        </div>
                    </div>
                    
                    <!-- Summary Cards -->
                    <div class="card-body bg-light">
                        <!-- Total Expenses -->
                        <div class="card bg-danger text-white mb-4">
                            <div class="card-body text-center">
                                <h1>TOTAL EXPENSES: ₦<?php echo number_format($totalExpenses, 2); ?></h1>
                                <h5>For <?php echo date('F Y', strtotime($month)); ?></h5>
                            </div>
                        </div>
                        
                        <!-- Breakdown -->
                        <div class="row mb-4">
                            <!-- Farm Type Breakdown -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>By Farm Type</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($farmTypeTotals as $type => $total): 
                                            $percentage = $totalExpenses > 0 ? ($total / $totalExpenses * 100) : 0;
                                        ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>
                                                    <span class="badge bg-<?php 
                                                        echo $type == 'poultry' ? 'info' : 
                                                             ($type == 'ruminant' ? 'warning' : 'secondary'); 
                                                    ?>">
                                                        <?php echo ucfirst($type); ?>
                                                    </span>
                                                </span>
                                                <span>₦<?php echo number_format($total, 2); ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-<?php 
                                                    echo $type == 'poultry' ? 'info' : 
                                                         ($type == 'ruminant' ? 'warning' : 'secondary'); 
                                                ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Category Breakdown -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6>By Category</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($categoryTotals as $cat => $total): 
                                            $percentage = $totalExpenses > 0 ? ($total / $totalExpenses * 100) : 0;
                                        ?>
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>
                                                    <span class="badge bg-<?php 
                                                        switch($cat) {
                                                            case 'feeds': echo 'primary'; break;
                                                            case 'medication': echo 'success'; break;
                                                            case 'salary': echo 'warning'; break;
                                                            case 'logistic': echo 'info'; break;
                                                            case 'fuel': echo 'secondary'; break;
                                                            default: echo 'dark';
                                                        }
                                                    ?>">
                                                        <?php echo ucfirst($cat); ?>
                                                    </span>
                                                </span>
                                                <span>₦<?php echo number_format($total, 2); ?></span>
                                            </div>
                                            <div class="progress" style="height: 10px;">
                                                <div class="progress-bar bg-<?php 
                                                    switch($cat) {
                                                        case 'feeds': echo 'primary'; break;
                                                        case 'medication': echo 'success'; break;
                                                        case 'salary': echo 'warning'; break;
                                                        case 'logistic': echo 'info'; break;
                                                        case 'fuel': echo 'secondary'; break;
                                                        default: echo 'dark';
                                                    }
                                                ?>" style="width: <?php echo $percentage; ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detailed Expenses Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Farm Type</th>
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
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-receipt display-4 d-block mb-2"></i>
                                            No expenses recorded for this period
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $expense): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $expense['farm_type'] == 'poultry' ? 'info' : 
                                                         ($expense['farm_type'] == 'ruminant' ? 'warning' : 'secondary'); 
                                                ?>">
                                                    <?php echo ucfirst($expense['farm_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    switch($expense['category']) {
                                                        case 'feeds': echo 'primary'; break;
                                                        case 'medication': echo 'success'; break;
                                                        case 'salary': echo 'warning'; break;
                                                        case 'logistic': echo 'info'; break;
                                                        case 'fuel': echo 'secondary'; break;
                                                        default: echo 'dark';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($expense['category']); ?>
                                                </span>
                                            </td>
                                            <td class="text-danger fw-bold">
                                                ₦<?php echo number_format($expense['amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo $expense['description'] ?: '--'; ?>
                                            </td>
                                            <td>
                                                <small><?php echo $expense['full_name']; ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editExpense(<?php echo $expense['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="deleteExpense(<?php echo $expense['id']; ?>)">
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Filter change
    $('#farmTypeFilter, #categoryFilter, #monthFilter').change(function() {
        const farmType = $('#farmTypeFilter').val();
        const category = $('#categoryFilter').val();
        const month = $('#monthFilter').val();
        window.location.href = `expenses.php?month=${month}&farm_type=${farmType}&category=${category}`;
    });
    
    function editExpense(expenseId) {
        alert('Edit functionality coming soon for expense ID: ' + expenseId);
    }
    
    function deleteExpense(expenseId) {
        if (confirm('Are you sure you want to delete this expense record?')) {
            fetch('api/delete_expense.php?id=' + expenseId)
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
    </script>
</body>
</html>
