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
$query = "SELECT * FROM layer_daily_records 
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
    'water_consumption' => 0,
    'egg_production' => 0,
    'crates_count' => 0,
    'laying_rate' => 0
];

$recordCount = 0;
foreach ($records as $record) {
    $monthlyTotals['mortality'] += $record['mortality'];
    $monthlyTotals['feed_consumption'] += $record['feed_consumption_bags'];
    $monthlyTotals['water_consumption'] += $record['water_consumption_liters'];
    $monthlyTotals['egg_production'] += $record['egg_production'];
    $monthlyTotals['crates_count'] += $record['crates_count'];
    $monthlyTotals['laying_rate'] += $record['laying_rate'];
    $recordCount++;
}

if ($recordCount > 0) {
    $monthlyTotals['laying_rate'] = $monthlyTotals['laying_rate'] / $recordCount;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_record'])) {
        $recordDate = $_POST['record_date'];
        
        // Check if record exists
        $checkStmt = $pdo->prepare("SELECT id FROM layer_daily_records WHERE record_date = ?");
        $checkStmt->execute([$recordDate]);
        
        if ($checkStmt->fetch()) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE layer_daily_records SET 
                opening_stock = ?, mortality = ?, feed_consumption_bags = ?, 
                water_consumption_liters = ?, medications = ?, egg_production = ?,
                crates_count = ?, laying_rate = ?, birds_age = ?, remarks = ?
                WHERE record_date = ?");
            $stmt->execute([
                $_POST['opening_stock'],
                $_POST['mortality'],
                $_POST['feed_consumption'],
                $_POST['water_consumption'],
                $_POST['medications'],
                $_POST['egg_production'],
                $_POST['crates_count'],
                $_POST['laying_rate'],
                $_POST['birds_age'],
                $_POST['remarks'],
                $recordDate
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO layer_daily_records 
                (record_date, opening_stock, mortality, feed_consumption_bags, 
                 water_consumption_liters, medications, egg_production, 
                 crates_count, laying_rate, birds_age, remarks, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $recordDate,
                $_POST['opening_stock'],
                $_POST['mortality'],
                $_POST['feed_consumption'],
                $_POST['water_consumption'],
                $_POST['medications'],
                $_POST['egg_production'],
                $_POST['crates_count'],
                $_POST['laying_rate'],
                $_POST['birds_age'],
                $_POST['remarks'],
                $_SESSION['user_id']
            ]);
        }
        
        $_SESSION['success'] = "Daily record saved successfully!";
        header("Location: layers_daily_record.php?month=" . $month);
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
    <title>Layer Daily Record - Renee Farms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .calendar-day {
            cursor: pointer;
            transition: all 0.2s;
        }
        .calendar-day:hover {
            background-color: #f8f9fa;
            transform: scale(1.05);
        }
        .calendar-day.has-record {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .calendar-day.has-mortality {
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        .record-card {
            transition: transform 0.2s;
        }
        .record-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
                            <i class="bi bi-calendar-check"></i> 
                            Layer Daily Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <input type="month" class="form-control" id="monthSelector" 
                                   value="<?php echo $yearMonth; ?>" style="width: 200px;">
                            <button class="btn btn-primary" onclick="openRecordModal()">
                                <i class="bi bi-plus-circle"></i> Add Today's Record
                            </button>
                        </div>
                    </div>
                    
                    <!-- Monthly Summary Cards -->
                    <div class="card-body bg-light">
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <div class="card text-white bg-primary">
                                    <div class="card-body text-center">
                                        <h6>Total Eggs</h6>
                                        <h3><?php echo number_format($monthlyTotals['egg_production']); ?></h3>
                                        <small>This Month</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-white bg-success">
                                    <div class="card-body text-center">
                                        <h6>Avg Laying Rate</h6>
                                        <h3><?php echo number_format($monthlyTotals['laying_rate'], 1); ?>%</h3>
                                        <small>Monthly Average</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="card text-white bg-danger">
                                    <div class="card-body text-center">
                                        <h6>Total Mortality</h6>
                                        <h3><?php echo number_format($monthlyTotals['mortality']); ?></h3>
                                        <small>Birds Lost</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-white bg-warning">
                                    <div class="card-body text-center">
                                        <h6>Feed Consumption</h6>
                                        <h3><?php echo number_format($monthlyTotals['feed_consumption'], 2); ?></h3>
                                        <small>Bags (25kg each)</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-white bg-info">
                                    <div class="card-body text-center">
                                        <h6>Water Consumption</h6>
                                        <h3><?php echo number_format($monthlyTotals['water_consumption']); ?></h3>
                                        <small>Liters</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Calendar View -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Monthly Calendar View</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <?php
                                    // Generate calendar
                                    $firstDay = date('N', strtotime($yearMonth . '-01'));
                                    $daysInMonth = date('t', strtotime($yearMonth));
                                    $currentDay = 1;
                                    
                                    // Week days header
                                    $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                                    ?>
                                    
                                    <?php foreach ($weekDays as $day): ?>
                                    <div class="col text-center fw-bold text-muted mb-2">
                                        <?php echo $day; ?>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Empty cells for first week -->
                                    <?php for ($i = 1; $i < $firstDay; $i++): ?>
                                    <div class="col"></div>
                                    <?php endfor; ?>
                                    
                                    <!-- Days of the month -->
                                    <?php while ($currentDay <= $daysInMonth): ?>
                                        <?php if (($currentDay + $firstDay - 2) % 7 == 0 && $currentDay > 1): ?>
                                            </div><div class="row mb-2">
                                        <?php endif; ?>
                                        
                                        <?php
                                        $currentDate = $yearMonth . '-' . sprintf('%02d', $currentDay);
                                        $record = null;
                                        $hasRecord = false;
                                        $hasMortality = false;
                                        
                                        foreach ($records as $rec) {
                                            if (date('Y-m-d', strtotime($rec['record_date'])) == $currentDate) {
                                                $record = $rec;
                                                $hasRecord = true;
                                                if ($rec['mortality'] > 0) {
                                                    $hasMortality = true;
                                                }
                                                break;
                                            }
                                        }
                                        ?>
                                        
                                        <div class="col p-1">
                                            <div class="calendar-day border rounded p-2 text-center 
                                                <?php echo $hasRecord ? 'has-record' : ''; ?>
                                                <?php echo $hasMortality ? 'has-mortality' : ''; ?>"
                                                onclick="editRecord('<?php echo $currentDate; ?>')">
                                                <div class="fw-bold"><?php echo $currentDay; ?></div>
                                                <?php if ($hasRecord): ?>
                                                <div class="small">
                                                    <span class="badge bg-success"><?php echo $record['egg_production']; ?> eggs</span>
                                                    <?php if ($record['mortality'] > 0): ?>
                                                    <span class="badge bg-danger mt-1"><?php echo $record['mortality']; ?> dead</span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php else: ?>
                                                <div class="small text-muted">No record</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php $currentDay++; ?>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detailed Records Table -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Detailed Daily Records</h5>
                            </div>
                            <div class="card-body">
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
                                                <th>Egg Production</th>
                                                <th>Crates</th>
                                                <th>Laying Rate</th>
                                                <th>Birds Age</th>
                                                <th>Remarks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($records)): ?>
                                            <tr>
                                                <td colspan="12" class="text-center text-muted py-4">
                                                    <i class="bi bi-calendar-x display-4 d-block mb-2"></i>
                                                    No records found for this month
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($records as $record): ?>
                                                <tr class="record-card">
                                                    <td>
                                                        <strong><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></strong>
                                                    </td>
                                                    <td><?php echo $record['opening_stock']; ?></td>
                                                    <td class="<?php echo $record['mortality'] > 0 ? 'text-danger fw-bold' : ''; ?>">
                                                        <?php echo $record['mortality']; ?>
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
                                                    <td class="text-success fw-bold">
                                                        <?php echo $record['egg_production']; ?>
                                                    </td>
                                                    <td><?php echo $record['crates_count']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $record['laying_rate'] > 80 ? 'success' : 
                                                                ($record['laying_rate'] > 60 ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo $record['laying_rate']; ?>%
                                                        </span>
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
                                                                onclick="editRecord('<?php echo $record['record_date']; ?>')"
                                                                title="Edit Record">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteRecord(<?php echo $record['id']; ?>)"
                                                                title="Delete Record">
                                                            <i class="bi bi-trash"></i>
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
                                                <td>--</td>
                                                <td class="text-success fw-bold"><?php echo $monthlyTotals['egg_production']; ?></td>
                                                <td class="fw-bold"><?php echo $monthlyTotals['crates_count']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $monthlyTotals['laying_rate'] > 80 ? 'success' : 
                                                            ($monthlyTotals['laying_rate'] > 60 ? 'warning' : 'danger'); 
                                                    ?>">
                                                        <?php echo number_format($monthlyTotals['laying_rate'], 1); ?>%
                                                    </span>
                                                </td>
                                                <td>--</td>
                                                <td colspan="2">Monthly Summary</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Record Modal -->
    <div class="modal fade" id="recordModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="recordForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Layer Daily Record</h5>
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
                            <div class="col-md-4 mb-3">
                                <label>Opening Stock</label>
                                <input type="number" name="opening_stock" class="form-control" 
                                       id="openingStock" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Mortality</label>
                                <input type="number" name="mortality" class="form-control" 
                                       id="mortality" min="0" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Egg Production</label>
                                <input type="number" name="egg_production" class="form-control" 
                                       id="eggProduction" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Feed Consumption (25kg/bag)</label>
                                <input type="number" name="feed_consumption" class="form-control" 
                                       id="feedConsumption" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Water Consumption (liters)</label>
                                <input type="number" name="water_consumption" class="form-control" 
                                       id="waterConsumption" step="0.1" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label>Number of Crates</label>
                                <input type="number" name="crates_count" class="form-control" 
                                       id="cratesCount" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Laying Rate (%)</label>
                                <div class="input-group">
                                    <input type="number" name="laying_rate" class="form-control" 
                                           id="layingRate" step="0.1" min="0" max="100" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <small class="text-muted">Calculated automatically from eggs/stock</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Medications</label>
                                <textarea name="medications" class="form-control" 
                                         id="medications" rows="2" 
                                         placeholder="List medications given"></textarea>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Remarks / Causes of Mortality</label>
                            <textarea name="remarks" class="form-control" 
                                     id="remarks" rows="3" 
                                     placeholder="Enter any remarks or causes of mortality"></textarea>
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
        window.location.href = 'layers_daily_record.php?month=' + this.value;
    });
    
    // Auto-calculate laying rate
    function calculateLayingRate() {
        const openingStock = parseInt(document.getElementById('openingStock').value) || 0;
        const eggProduction = parseInt(document.getElementById('eggProduction').value) || 0;
        
        if (openingStock > 0) {
            const layingRate = (eggProduction / openingStock) * 100;
            document.getElementById('layingRate').value = layingRate.toFixed(1);
        }
    }
    
    // Set up auto-calculation
    document.getElementById('openingStock').addEventListener('input', calculateLayingRate);
    document.getElementById('eggProduction').addEventListener('input', calculateLayingRate);
    
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
            
            // Get yesterday's closing stock for today's opening
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
        fetch(`api/get_layer_record.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('birdsAge').value = data.birds_age || '';
                    document.getElementById('openingStock').value = data.opening_stock || '';
                    document.getElementById('mortality').value = data.mortality || 0;
                    document.getElementById('eggProduction').value = data.egg_production || '';
                    document.getElementById('feedConsumption').value = data.feed_consumption_bags || '';
                    document.getElementById('waterConsumption').value = data.water_consumption_liters || '';
                    document.getElementById('cratesCount').value = data.crates_count || '';
                    document.getElementById('layingRate').value = data.laying_rate || '';
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
        
        fetch(`api/get_layer_record.php?date=${yesterdayStr}`)
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
        fetch(`api/check_record.php?type=layer&date=${date}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    fetchRecordData(date);
                } else {
                    document.getElementById('modalTitle').textContent = 'Add Daily Record';
                    resetForm();
                    
                    // If it's today, try to get yesterday's stock
                    const today = new Date().toISOString().split('T')[0];
                    if (date === today) {
                        fetchYesterdayStock(today);
                    }
                }
            });
    }
    
    // Reset form
    function resetForm() {
        document.getElementById('recordForm').reset();
        document.getElementById('mortality').value = 0;
    }
    
    // Delete record
    function deleteRecord(recordId) {
        if (confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
            fetch(`api/delete_record.php?type=layer&id=${recordId}`)
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