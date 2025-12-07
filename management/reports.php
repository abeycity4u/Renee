<?php
require_once 'config.php';
requireLogin();

// Only owner can access reports
if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$year = $_GET['year'] ?? date('Y');
$farmType = $_GET['farm_type'] ?? 'all';

// Get profit/loss data
if ($farmType == 'all') {
    $profitQuery = "SELECT * FROM profit_loss_summary WHERE LEFT(month, 4) = ? ORDER BY month";
    $profitStmt = $pdo->prepare($profitQuery);
    $profitStmt->execute([$year]);
} else {
    $profitQuery = "SELECT * FROM profit_loss_summary WHERE LEFT(month, 4) = ? AND farm_type = ? ORDER BY month";
    $profitStmt = $pdo->prepare($profitQuery);
    $profitStmt->execute([$year, $farmType]);
}
$profitData = $profitStmt->fetchAll();

// Get top selling products
$topProductsQuery = "SELECT product_type, SUM(quantity) as total_quantity, 
                     SUM(total_amount) as total_revenue
                     FROM sales_records 
                     WHERE YEAR(sale_date) = ?
                     GROUP BY product_type 
                     ORDER BY total_revenue DESC 
                     LIMIT 10";
$topProductsStmt = $pdo->prepare($topProductsQuery);
$topProductsStmt->execute([$year]);
$topProducts = $topProductsStmt->fetchAll();

// Get expense breakdown
$expenseQuery = "SELECT category, SUM(amount) as total_amount
                 FROM farm_expenses 
                 WHERE YEAR(expense_date) = ?
                 GROUP BY category 
                 ORDER BY total_amount DESC";
$expenseStmt = $pdo->prepare($expenseQuery);
$expenseStmt->execute([$year]);
$expenses = $expenseStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'navbar_head.php'; ?> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Farm Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="bi bi-graph-up-arrow"></i> Farm Reports & Analytics</h4>
                        <div class="d-flex gap-2 mt-2">
                            <select class="form-select" id="yearFilter" style="width: 150px;">
                                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                            <select class="form-select" id="farmTypeFilter" style="width: 200px;">
                                <option value="all" <?php echo $farmType == 'all' ? 'selected' : ''; ?>>All Farms</option>
                                <option value="poultry" <?php echo $farmType == 'poultry' ? 'selected' : ''; ?>>Poultry Only</option>
                                <option value="ruminant" <?php echo $farmType == 'ruminant' ? 'selected' : ''; ?>>Ruminant Only</option>
                            </select>
                            <button class="btn btn-primary" onclick="printReport()">
                                <i class="bi bi-printer"></i> Print Report
                            </button>
                            <button class="btn btn-success" onclick="exportToExcel()">
                                <i class="bi bi-file-earmark-excel"></i> Export Excel
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Profit/Loss Chart -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Monthly Profit/Loss Analysis</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="profitChart" height="100"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Yearly Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $yearlyTotals = [
                                            'sales' => 0,
                                            'expenses' => 0,
                                            'profit' => 0
                                        ];
                                        
                                        foreach ($profitData as $data) {
                                            $yearlyTotals['sales'] += $data['total_sales'];
                                            $yearlyTotals['expenses'] += ($data['feed_expenses'] + 
                                                                          $data['medication_expenses'] + 
                                                                          $data['salary_expenses'] + 
                                                                          $data['logistic_expenses'] + 
                                                                          $data['fuel_expenses'] + 
                                                                          $data['misc_expenses']);
                                            $yearlyTotals['profit'] += $data['net_profit'];
                                        }
                                        ?>
                                        <div class="mb-3">
                                            <h6>Total Sales</h6>
                                            <h3 class="text-success">₦<?php echo number_format($yearlyTotals['sales'], 2); ?></h3>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Total Expenses</h6>
                                            <h3 class="text-danger">₦<?php echo number_format($yearlyTotals['expenses'], 2); ?></h3>
                                        </div>
                                        <div class="mb-3">
                                            <h6>Net Profit</h6>
                                            <h3 class="<?php echo $yearlyTotals['profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                ₦<?php echo number_format($yearlyTotals['profit'], 2); ?>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detailed Profit/Loss Table -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Detailed Profit/Loss Statement</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Month</th>
                                                <th>Farm Type</th>
                                                <th>Sales</th>
                                                <th>Feed Expenses</th>
                                                <th>Medication</th>
                                                <th>Salary</th>
                                                <th>Logistic</th>
                                                <th>Fuel</th>
                                                <th>Misc</th>
                                                <th>Total Expenses</th>
                                                <th>Net Profit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($profitData as $data): 
                                                $totalExpenses = $data['feed_expenses'] + 
                                                                $data['medication_expenses'] + 
                                                                $data['salary_expenses'] + 
                                                                $data['logistic_expenses'] + 
                                                                $data['fuel_expenses'] + 
                                                                $data['misc_expenses'];
                                            ?>
                                            <tr>
                                                <td><?php echo date('M Y', strtotime($data['month'] . '-01')); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $data['farm_type'] == 'poultry' ? 'info' : 'warning'; ?>">
                                                        <?php echo ucfirst($data['farm_type']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-success">₦<?php echo number_format($data['total_sales'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['feed_expenses'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['medication_expenses'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['salary_expenses'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['logistic_expenses'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['fuel_expenses'], 2); ?></td>
                                                <td>₦<?php echo number_format($data['misc_expenses'], 2); ?></td>
                                                <td class="text-danger">₦<?php echo number_format($totalExpenses, 2); ?></td>
                                                <td class="fw-bold <?php echo $data['net_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    ₦<?php echo number_format($data['net_profit'], 2); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Charts -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Top Selling Products</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="productsChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Expense Breakdown</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="expensesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Filter change
    document.getElementById('yearFilter').addEventListener('change', function() {
        updateReport();
    });
    
    document.getElementById('farmTypeFilter').addEventListener('change', function() {
        updateReport();
    });
    
    function updateReport() {
        const year = document.getElementById('yearFilter').value;
        const farmType = document.getElementById('farmTypeFilter').value;
        window.location.href = `reports.php?year=${year}&farm_type=${farmType}`;
    }
    
    function printReport() {
        window.print();
    }
    
    function exportToExcel() {
        // This would require a server-side script to generate Excel
        alert('Excel export feature coming soon!');
    }
    
    // Initialize charts when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Profit/Loss Chart
        const profitCtx = document.getElementById('profitChart').getContext('2d');
        const profitChart = new Chart(profitCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($d) { 
                    return date('M', strtotime($d['month'] . '-01')); 
                }, $profitData)); ?>,
                datasets: [{
                    label: 'Net Profit (₦)',
                    data: <?php echo json_encode(array_column($profitData, 'net_profit')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Top Products Chart
        const productsCtx = document.getElementById('productsChart').getContext('2d');
        const productsChart = new Chart(productsCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($topProducts, 'product_type')); ?>,
                datasets: [{
                    label: 'Revenue (₦)',
                    data: <?php echo json_encode(array_column($topProducts, 'total_revenue')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Expenses Chart
        const expensesCtx = document.getElementById('expensesChart').getContext('2d');
        const expensesChart = new Chart(expensesCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($expenses, 'category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($expenses, 'total_amount')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 159, 64, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += '₦' + context.parsed.toLocaleString();
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
</body>
</html>