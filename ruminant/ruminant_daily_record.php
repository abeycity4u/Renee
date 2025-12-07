<?php
require_once 'config.php';
requireLogin();

// Check access
if (!checkAccess('ruminant') && getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

$month = $_GET['month'] ?? date('Y-m');
$yearMonth = date('Y-m', strtotime($month));

// Get all records for the month
$query = "SELECT * FROM ruminant_daily_records 
          WHERE DATE_FORMAT(record_date, '%Y-m') = ? 
          ORDER BY record_date, animal_type";
$stmt = $pdo->prepare($query);
$stmt->execute([$yearMonth]);
$records = $stmt->fetchAll();

// Group by animal type
$animalTypes = [];
foreach ($records as $record) {
    if (!isset($animalTypes[$record['animal_type']])) {
        $animalTypes[$record['animal_type']] = [];
    }
    $animalTypes[$record['animal_type']][] = $record;
}

// Calculate totals
$monthlyTotals = [
    'opening_stock' => 0,
    'mortality' => 0,
    'feed_consumption' => 0,
    'water_consumption' => 0
];

foreach ($records as $record) {
    $monthlyTotals['opening_stock'] += $record['opening_stock'];
    $monthlyTotals['mortality'] += $record['mortality'];
    $monthlyTotals['feed_consumption'] += $record['feed_consumption_kg'];
    $monthlyTotals['water_consumption'] += $record['water_consumption_liters'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_record'])) {
    $recordDate = $_POST['record_date'];
    $animalType = $_POST['animal_type'];
    
    // Check if record exists for this date and animal type
    $checkStmt = $pdo->prepare("SELECT id FROM ruminant_daily_records 
                                WHERE record_date = ? AND animal_type = ?");
    $checkStmt->execute([$recordDate, $animalType]);
    
    if ($checkStmt->fetch()) {
        // Update existing record
        $stmt = $pdo->prepare("UPDATE ruminant_daily_records SET 
            opening_stock = ?, mortality = ?, feed_consumption_kg = ?, 
            water_consumption_liters = ?, other_details = ?, tag_no = ?,
            medications = ?, reproduction_details = ?, remarks = ?
            WHERE record_date = ? AND animal_type = ?");
        $stmt->execute([
            $_POST['opening_stock'],
            $_POST['mortality'],
            $_POST['feed_consumption'],
            $_POST['water_consumption'],
            $_POST['other_details'],
            $_POST['tag_no'],
            $_POST['medications'],
            $_POST['reproduction_details'],
            $_POST['remarks'],
            $recordDate,
            $animalType
        ]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("INSERT INTO ruminant_daily_records 
            (record_date, animal_type, opening_stock, mortality, 
             feed_consumption_kg, water_consumption_liters, other_details,
             tag_no, medications, reproduction_details, remarks, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $recordDate,
            $animalType,
            $_POST['opening_stock'],
            $_POST['mortality'],
            $_POST['feed_consumption'],
            $_POST['water_consumption'],
            $_POST['other_details'],
            $_POST['tag_no'],
            $_POST['medications'],
            $_POST['reproduction_details'],
            $_POST['remarks'],
            $_SESSION['user_id']
        ]);
    }
    
    $_SESSION['success'] = "Ruminant daily record saved successfully!";
    header("Location: ruminant_daily_record.php?month=" . $month);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'navbar_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruminant Daily Record - Renee Farms</title>
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
                            <i class="bi bi-shield-plus"></i> 
                            Ruminant Daily Record - <?php echo date('F Y', strtotime($yearMonth)); ?>
                        </h4>
                        <div class="d-flex gap-2">
                            <input type="month" class="form-control" id="monthSelector" 
                                   value="<?php echo $yearMonth; ?>" style="width: 200px;">
                            <button class="btn btn-primary" onclick="openRecordModal()">
                                <i class="bi bi-plus-circle"></i> Add Daily Record
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <!-- Animal Type Tabs -->
                        <ul class="nav nav-tabs mb-4" id="animalTabs">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#all">
                                    All Animals
                                </a>
                            </li>
                            <?php foreach (array_keys($animalTypes) as $animalType): ?>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#<?php echo strtolower(str_replace(' ', '', $animalType)); ?>">
                                    <?php echo $animalType; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <!-- Tab Content -->
                        <div class="tab-content">
                            <!-- All Animals Tab -->
                            <div class="tab-pane fade show active" id="all">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Date</th>
                                                <th>Animal Type</th>
                                                <th>Opening Stock</th>
                                                <th>Mortality</th>
                                                <th>Feed (kg)</th>
                                                <th>Water (L)</th>
                                                <th>Tag No</th>
                                                <th>Reproduction</th>
                                                <th>Remarks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($records)): ?>
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-4">
                                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                                    No records found for this month
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($records as $record): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></strong>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo $record['animal_type']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $record['opening_stock']; ?></td>
                                                    <td class="text-danger fw-bold">
                                                        <?php echo $record['mortality']; ?>
                                                    </td>
                                                    <td><?php echo $record['feed_consumption_kg']; ?></td>
                                                    <td><?php echo number_format($record['water_consumption_liters']); ?></td>
                                                    <td>
                                                        <?php if ($record['tag_no']): ?>
                                                        <small class="badge bg-secondary"><?php echo $record['tag_no']; ?></small>
                                                        <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['reproduction_details']): ?>
                                                        <small><?php echo substr($record['reproduction_details'], 0, 20); ?>...</small>
                                                        <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($record['remarks']): ?>
                                                        <small class="text-muted"><?php echo substr($record['remarks'], 0, 20); ?>...</small>
                                                        <?php else: ?>
                                                        <span class="text-muted">--</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="editRecord('<?php echo $record['record_date']; ?>', '<?php echo $record['animal_type']; ?>')">
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
                            
                            <!-- Individual Animal Type Tabs -->
                            <?php foreach ($animalTypes as $animalType => $typeRecords): 
                                $typeTotals = [
                                    'opening_stock' => 0,
                                    'mortality' => 0,
                                    'feed_consumption' => 0,
                                    'water_consumption' => 0
                                ];
                                
                                foreach ($typeRecords as $record) {
                                    $typeTotals['opening_stock'] += $record['opening_stock'];
                                    $typeTotals['mortality'] += $record['mortality'];
                                    $typeTotals['feed_consumption'] += $record['feed_consumption_kg'];
                                    $typeTotals['water_consumption'] += $record['water_consumption_liters'];
                                }
                            ?>
                            <div class="tab-pane fade" id="<?php echo strtolower(str_replace(' ', '', $animalType)); ?>">
                                <!-- Type Summary -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="card text-white bg-primary">
                                            <div class="card-body text-center">
                                                <h6>Total Stock</h6>
                                                <h3><?php echo $typeTotals['opening_stock']; ?></h3>
                                                <small>Animals</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-white bg-danger">
                                            <div class="card-body text-center">
                                                <h6>Mortality</h6>
                                                <h3><?php echo $typeTotals['mortality']; ?></h3>
                                                <small>Losses</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-white bg-warning">
                                            <div class="card-body text-center">
                                                <h6>Feed Used</h6>
                                                <h3><?php echo number_format($typeTotals['feed_consumption'], 1); ?></h3>
                                                <small>Kg</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="card text-white bg-info">
                                            <div class="card-body text-center">
                                                <h6>Water Used</h6>
                                                <h3><?php echo number_format($typeTotals['water_consumption']); ?></h3>
                                                <small>Liters</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Type Records Table -->
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Opening Stock</th>
                                                <th>Mortality</th>
                                                <th>Feed (kg)</th>
                                                <th>Water (L)</th>
                                                <th>Tag No</th>
                                                <th>Medications</th>
                                                <th>Reproduction</th>
                                                <th>Other Details</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($typeRecords as $record): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($record['record_date'])); ?></td>
                                                <td><?php echo $record['opening_stock']; ?></td>
                                                <td class="text-danger"><?php echo $record['mortality']; ?></td>
                                                <td><?php echo $record['feed_consumption_kg']; ?></td>
                                                <td><?php echo number_format($record['water_consumption_liters']); ?></td>
                                                <td><?php echo $record['tag_no'] ?: '--'; ?></td>
                                                <td>
                                                    <?php if ($record['medications']): ?>
                                                    <small><?php echo substr($record['medications'], 0, 15); ?>...</small>
                                                    <?php else: ?>
                                                    <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['reproduction_details']): ?>
                                                    <small><?php echo substr($record['reproduction_details'], 0, 15); ?>...</small>
                                                    <?php else: ?>
                                                    <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['other_details']): ?>
                                                    <small><?php echo substr($record['other_details'], 0, 15); ?>...</small>
                                                    <?php else: ?>
                                                    <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['remarks']): ?>
                                                    <small class="text-muted"><?php echo substr($record['remarks'], 0, 15); ?>...</small>
                                                    <?php else: ?>
                                                    <span class="text-muted">--</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endforeach; ?>
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
                        <h5 class="modal-title" id="modalTitle">Ruminant Daily Record</h5>
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
                                <label>Animal Type</label>
                                <select name="animal_type" class="form-control" id="animalType" required>
                                    <option value="">Select Animal Type</option>
                                    <option value="Cattle">Cattle</option>
                                    <option value="Goat">Goat</option>
                                    <option value="Sheep">Sheep</option>
                                    <option value="Other">Other</option>
                                </select>
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
                                <label>Feed Consumption (kg)</label>
                                <input type="number" name="feed_consumption" class="form-control" 
                                       id="feedConsumption" step="0.1" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Water Consumption (liters)</label>
                                <input type="number" name="water_consumption" class="form-control" 
                                       id="waterConsumption" step="0.1" min="0" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Tag Number</label>
                                <input type="text" name="tag_no" class="form-control" 
                                       id="tagNo" placeholder="Optional tag number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Medications</label>
                                <input type="text" name="medications" class="form-control" 
                                       id="medications" placeholder="Medications given">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label>Reproduction Details</label>
                            <textarea name="reproduction_details" class="form-control" 
                                     id="reproductionDetails" rows="2" 
                                     placeholder="Births, pregnancies, etc."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label>Other Details</label>
                            <textarea name="other_details" class="form-control" 
                                     id="otherDetails" rows="2"></textarea>
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
        window.location.href = 'ruminant_daily_record.php?month=' + this.value;
    });
    
    // Open modal for new record
    function openRecordModal(date = null, animalType = null) {
        const modal = new bootstrap.Modal(document.getElementById('recordModal'));
        const today = new Date().toISOString().split('T')[0];
        
        if (date && animalType) {
            document.getElementById('modalTitle').textContent = 'Edit Record';
            document.getElementById('selectedDate').value = date;
            document.getElementById('recordDate').value = date;
            document.getElementById('animalType').value = animalType;
            document.getElementById('animalType').disabled = true;
            fetchRecordData(date, animalType);
        } else {
            document.getElementById('modalTitle').textContent = 'Add Daily Record';
            document.getElementById('selectedDate').value = today;
            document.getElementById('recordDate').value = today;
            document.getElementById('animalType').disabled = false;
            resetForm();
        }
        
        modal.show();
    }
    
    // Edit record
    function editRecord(date, animalType) {
        openRecordModal(date, animalType);
    }
    
    // Fetch record data
    function fetchRecordData(date, animalType) {
        const params = new URLSearchParams({
            date: date,
            animal_type: animalType
        });
        
        fetch(`api/get_ruminant_record.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data) {
                    document.getElementById('openingStock').value = data.opening_stock || '';
                    document.getElementById('mortality').value = data.mortality || 0;
                    document.getElementById('feedConsumption').value = data.feed_consumption_kg || '';
                    document.getElementById('waterConsumption').value = data.water_consumption_liters || '';
                    document.getElementById('tagNo').value = data.tag_no || '';
                    document.getElementById('medications').value = data.medications || '';
                    document.getElementById('reproductionDetails').value = data.reproduction_details || '';
                    document.getElementById('otherDetails').value = data.other_details || '';
                    document.getElementById('remarks').value = data.remarks || '';
                }
            });
    }
    
    // Check existing record
    function checkExistingRecord() {
        const date = document.getElementById('selectedDate').value;
        const animalType = document.getElementById('animalType').value;
        
        if (!date || !animalType) return;
        
        const params = new URLSearchParams({
            date: date,
            animal_type: animalType
        });
        
        fetch(`api/check_ruminant_record.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    document.getElementById('modalTitle').textContent = 'Edit Record';
                    document.getElementById('animalType').disabled = true;
                    fetchRecordData(date, animalType);
                } else {
                    document.getElementById('modalTitle').textContent = 'Add Daily Record';
                    document.getElementById('animalType').disabled = false;
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