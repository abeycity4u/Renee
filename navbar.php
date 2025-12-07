<?php
// navbar.php - Main navigation bar
if (!isLoggedIn()) {
    return;
}
?>
<!-- Alert Container -->
<div id="alert-container"></div>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="assets/images/logo.png" alt="Logo" height="30" class="d-inline-block align-text-top me-2">
            Renee Farms Ltd
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                       href="dashboard.php">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                
                <?php if (checkAccess('poultry') || getUserType() === 'owner'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="poultryDropdown" role="button" 
                       data-bs-toggle="dropdown">
                        <i class="bi bi-egg-fried"></i> Poultry
                    </a>
                    <ul class="dropdown-menu">
                        <!-- Layer Section -->
                        <li><h6 class="dropdown-header">Layer Management</h6></li>
                        <li><a class="dropdown-item" href="layers_daily_record.php">
                            <i class="bi bi-calendar-check"></i> Layer Daily Record
                        </a></li>
                        <li><a class="dropdown-item" href="layer_feeds.php">
                            <i class="bi bi-bucket"></i> Layer Feeds
                        </a></li>
                        <li><a class="dropdown-item" href="layer_expenses.php">
                            <i class="bi bi-cash-stack"></i> Layer Expenses
                        </a></li>
                        
                        <li><hr class="dropdown-divider"></li>
                        
                        <!-- Broiler Section -->
                        <li><h6 class="dropdown-header">Broiler Management</h6></li>
                        <li><a class="dropdown-item" href="broiler_daily_record.php">
                            <i class="bi bi-calendar-check"></i> Broiler Daily Record
                        </a></li>
                        <li><a class="dropdown-item" href="broiler_feeds.php">
                            <i class="bi bi-basket"></i> Broiler Feeds
                        </a></li>
                        <li><a class="dropdown-item" href="broiler_expenses.php">
                            <i class="bi bi-cash-stack"></i> Broiler Expenses
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (checkAccess('ruminant') || getUserType() === 'owner'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="ruminantDropdown" role="button" 
                       data-bs-toggle="dropdown">
                        <i class="bi bi-shield-plus"></i> Ruminant
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="ruminant_daily_record.php">
                            <i class="bi bi-calendar-check"></i> Ruminant Daily Record
                        </a></li>
                        <li><a class="dropdown-item" href="ruminant_expenses.php">
                            <i class="bi bi-cash-coin"></i> Ruminant Expenses
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if (getUserType() === 'owner'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" 
                       data-bs-toggle="dropdown">
                        <i class="bi bi-gear"></i> Management
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="sales_records.php">
                            <i class="bi bi-graph-up"></i> Sales Records
                        </a></li>
                        <li><a class="dropdown-item" href="expenses.php">
                            <i class="bi bi-cash-stack"></i> All Expenses
                        </a></li>
                        <li><a class="dropdown-item" href="reports.php">
                            <i class="bi bi-file-earmark-text"></i> Reports
                        </a></li>
                        <li><a class="dropdown-item" href="users.php">
                            <i class="bi bi-people"></i> Users
                        </a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>" 
                       href="inventory.php">
                        <i class="bi bi-box-seam"></i> Inventory
                    </a>
                </li>
            </ul>
            
            <div class="d-flex align-items-center text-white">
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" 
                       id="userDropdown" data-bs-toggle="dropdown">
                        <img src="assets/images/default-avatar.png" alt="User" width="32" height="32" 
                             class="rounded-circle me-2">
                        <span>
                            <?php echo $_SESSION['full_name']; ?><br>
                            <small class="opacity-75">
                                <?php echo ucfirst(str_replace('_', ' ', $_SESSION['user_type'])); ?>
                            </small>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="#">
                            <i class="bi bi-person"></i> Profile
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="bi bi-gear"></i> Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>