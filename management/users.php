<?php
require_once 'config.php';
requireLogin();

// Only owner can access user management
if (getUserType() !== 'owner') {
    header('Location: dashboard.php');
    exit();
}

// Get all users
$users = $pdo->query("SELECT * FROM users ORDER BY user_type, username")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) 
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['username'],
            $hashedPassword,
            $_POST['user_type'],
            $_POST['full_name']
        ]);
        
        $_SESSION['success'] = "User added successfully!";
        header("Location: users.php");
        exit();
    }
    
    if (isset($_POST['delete_user'])) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        $stmt->execute([$_POST['user_id'], $_SESSION['user_id']]);
        
        $_SESSION['success'] = "User deleted successfully!";
        header("Location: users.php");
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
    <title>User Management - Renee Farms</title>
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
                        <h4><i class="bi bi-people"></i> User Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus"></i> Add User
                        </button>
                    </div>
                    
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>User Type</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $user['username']; ?></strong>
                                            <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $user['full_name']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $user['user_type'] == 'owner' ? 'danger' : 
                                                     ($user['user_type'] == 'poultry_manager' ? 'success' : 'warning'); 
                                            ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($user['user_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Delete this user?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Full Name</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>User Type</label>
                            <select name="user_type" class="form-select" required>
                                <option value="poultry_manager">Poultry Farm Manager</option>
                                <option value="ruminant_manager">Ruminant Farm Manager</option>
                                <option value="owner">Owner (Full Access)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    <?php if (isset($_SESSION['success'])): ?>
    alert('Success: <?php echo $_SESSION['success']; ?>');
    <?php unset($_SESSION['success']); endif; ?>
    </script>
</body>
</html>