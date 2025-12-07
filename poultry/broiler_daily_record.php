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

// Get all records for the month
$query = "SELECT * FROM broiler_daily_records 
          WHERE DATE_FORMAT(record_date, '%Y-m') = ? 
          ORDER BY record_date";
$stmt = $pdo->prepare($query);
$stmt->execute([$yearMonth]);
$records = $stmt->fetchAll();

// Calculate monthly totals
$monthlyTotals = [
    'opening_stock' => 0,
    'mortality' => 0,
    'feed_consumption' => 0,
    'water_consumption' => 0
];

foreach ($records as $record) {
    $monthlyTotals['mortality'] += $record['mortality'];
    $monthlyTotals['feed_consumption'] += $record['feed_consumption_bags'];
    $monthlyTotals['water_consumption'] += $record['water_consumption_liters'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_record'])) {
    $recordDate = $_POST['record_date'];
    
    // Check if record exists
    $checkStmt = $pdo->prepare("SELECT id FROM broiler_daily_records WHERE record_date = ?");
    $checkStmt->execute([$recordDate]);
    
    if ($checkStmt->fetch()) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE broiler_daily_records SET 
            opening_stock = ?, mortality = ?, feed_consumption_bags = ?, 
            water_consumption_liters = ?, medications = ?, birds_age = ?, remarks = ?
            WHERE record_date = ?");
        $stmt->execute([
            $_POST['opening_stock'],
            $_POST['mortality'],
            $_POST['feed_consumption'],
            $_POST['water_consumption'],
            $_POST['medications'],
            $_POST['birds_age'],
            $_POST['remarks'],
            $recordDate
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO broiler_daily_records 
            (record_date, opening_stock, mortality, feed_consumption_bags, 
             water_consumption_liters, medications, birds_age, remarks, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $recordDate,
            $_POST['opening_stock'],
            $_POST['mortality'],
            $_POST['feed_consumption'],
            $_POST['water_consumption'],
            $_POST['medications'],
            $_POST['birds_age'],
            $_POST['remarks'],
            $_SESSION['user_id']
        ]);
    }
    
    $_SESSION['success'] = "Broiler daily record saved successfully!";
    header("Location: broiler_daily_record.php?month=" . $month);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <?php include 'navbar_head.php'; ?> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broiler Daily Record - Renee Farms</title>
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
                            <i class="bi bi-calendar-check"></i> 
                            Broiler Daily Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <input type="month" class="form-control" id="monthSelector" 
                                   value="<?php echo $yearMonth; ?>" style="width: 200px;">
                            <button class="btn btn-primary" onclick="openRecordModal()">
                                <i class="bi bi-plus-circle"></i> Add Today's Record
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Monthly Summary -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card text-white bg-primary">
                                    <div class="card-body text-center">
                                        <h6>Total Mortality</h6>
                                        <h3><?php echo $monthlyTotals['mortality']; ?></h3>
                                        <small>This Month</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-white bg-success">
                                    <div class="card-body text-center">
                                        <h6>Feed Consumed</h6>
                                        <h3><?php echo number_format($monthlyTotals['feed_consumption'], 2); ?></h3>
                                        <small>Bags (25kg)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-white bg-warning">
                                    <div class="card-body text-center">
                                        <h6>Water Consumed</h6>
                                        <h3><?php echo number_format($monthlyTotals['water_consumption']); ?></h3>
                                        <small>Liters</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-white bg-info">
                                    <div class="card-body text-center">
                                        <h6>Days Recorded</h6>
                                        <h3><?php echo count($records); ?></h3>
                                        <small>Out of <?php echo date('t', strtotime($yearMonth)); ?> days</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Records Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Date</th>
                                        <th>Opening Stock</th>
                                        <th>Mortality</th>
                                        <th>Feed (bags)</th>
                                        <th>Water (L)</th>
                                        <th>Medications</th>
                                        <th>Birds Age</th>
                                        <th>Remarks</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($records)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No records found for this month
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($records as $record): 
                                            $closingStock = $record['opening_stock'] - $record['mortality'];
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></strong>
                                            </td>
                                            <td><?php echo $record['opening_stock']; ?></td>
                                            <td class="text-danger fw-bold">
                                                <?php echo $record['mortality']; ?>
                                                <?php if ($record['mortality'] > 0): ?>
                                                <small class="d-block text-muted">
                                                    Closing: <?php echo $closingStock; ?>
                                                </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $record['feed_consumption_bags']; ?></td>
                                            <td><?php echo number_format($record['water_consumption_liters']); ?></td>
                                            <td>
                                                <?php if ($record['medications']): ?>
                                                <small><?php echo substr($record['medications'], 0, 20); ?>...</small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo $record['birds_age']; ?> days
                                            </td>
                                            <td>
                                                <?php if ($record['remarks']): ?>
                                                <small class="text-muted"><?php echo substr($record['remarks'], 0, 30); ?>...</small>
                                                <?php else: ?>
                                                <span class="text-muted">--</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="editRecord('<?php echo $record['record_date']; ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <td><strong>TOTAL</strong></td>
                                        <td>--</td>
                                        <td class="text-danger fw-bold"><?php echo $monthlyTotals['mortality']; ?></td>
                                        <td class="fw-bold"><?php echo number_format($monthlyTotals['feed_consumption'], 2); ?></td>
                                        <td class="fw-bold"><?php echo number_format($monthlyTotals['water_consumption']); ?></td>
                                        <td colspan="4">Monthly Summary</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="recordForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Broiler Daily Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="record_date" id="recordDate">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Date</label>
                                <input type="date" class="form-control" id="selectedDate" 
                                       onchange="checkExistingRecord()" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Birds Age (days)</label>
                                <input type="number" name="birds_age" class="form-control" 
                                       id="birdsAge" min="1" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Opening Stock</label>
                                <input type="number" name="opening_stock" class="form-control" 
                                       id="openingStock" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Mortality</label>
                                <input type="number" name="mortality" class="form-control" 
                                       id="mortality" min="0" value="0">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Feed Consumption (25kg/bag)</label>
                                <input type="number" name="feed_consumption" class="form-control" 
                                       id="feedConsumption" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Water Consumption (liters)</label>
                                <input type="number" name="water_consumption" class="form-control" 
                                       id="waterConsumption" step="0.1" min="0" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Medications</label>
                            <textarea name="medications" class="form-control" 
                                     id="medications" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label>Remarks / Causes of Mortality</label>
                            <textarea name="remarks" class="form-control" 
                                     id="remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_record" class="btn btn-primary">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Month selector
    document.getElementById('monthSelector').addEventListener('change', function() {
        window.location.href = 'broiler_daily_record.php?month=' + this.value;
    });
    
    // Open modal for new record
    function openRecordModal(date = null) {
        const modal = new bootstrap.Modal(document.getElementById('recordModal'));
        const today = new Date().toISOString().split('T')[0];
        
        if (date) {
            document.getElementById('modalTitle').textContent = 'Edit Record';
            document.getElementById('selectedDate').value = date;
            document.getElementById('recordDate').value = date;
            fetchRecordData(date);
        } else {
            document.getElementById('modalTitle').textContent = 'Add Daily Record';
            document.getElementById('selectedDate').value = today;
            document.getElementById('recordDate').value = today;
            resetForm();
            
            // Get yesterday's closing stock
            fetchYesterdayStock(today);
        }
        
        modal.show();
    }
    
    // Edit record
    function editRecord(date) {
        openRecordModal(date);
    }
    
    // Fetch record data
    function fetchRecordData(date) {
        fetch(`api/get_broiler_record.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('birdsAge').value = data.birds_age || '';
                    document.getElementById('openingStock').value = data.opening_stock || '';
                    document.getElementById('mortality').value = data.mortality || 0;
                    document.getElementById('feedConsumption').value = data.feed_consumption_bags || '';
                    document.getElementById('waterConsumption').value = data.water_consumption_liters || '';
                    document.getElementById('medications').value = data.medications || '';
                    document.getElementById('remarks').value = data.remarks || '';
                }
            });
    }
    
    // Fetch yesterday's stock
    function fetchYesterdayStock(today) {
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        const yesterdayStr = yesterday.toISOString().split('T')[0];
        
        fetch(`api/get_broiler_record.php?date=${yesterdayStr}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.opening_stock) {
                    const openingStock = data.opening_stock - (data.mortality || 0);
                    document.getElementById('openingStock').value = openingStock > 0 ? openingStock : '';
                }
            });
    }
    
    // Check existing record
    function checkExistingRecord() {
        const date = document.getElementById('selectedDate').value;
        fetch(`api/check_record.php?type=broiler&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    fetchRecordData(date);
                } else {
                    document.getElementById('modalTitle').textContent = 'Add Daily Record';
                    resetForm();
                }
            });
    }
    
    // Reset form
    function resetForm() {
        document.getElementById('recordForm').reset();
        document.getElementById('mortality').value = 0;
    }
    
    // Show messages
    <?php if (isset($_SESSION['success'])): ?>
    alert('Success: <?php echo $_SESSION['success']; ?>');
    <?php unset($_SESSION['success']); endif; ?>
    </script>
</body>
</html>