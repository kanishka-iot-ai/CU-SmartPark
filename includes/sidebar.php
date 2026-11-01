<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Left Navigation Sidebar
 */

$activePage = $activePage ?? 'dashboard';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand" style="gap: 12px; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="../assets/images/logo.png" alt="Chandigarh University Logo" style="width: 44px; height: 44px; object-fit: contain; background: #FFFFFF; border-radius: var(--radius-md); padding: 3px; box-shadow: 0 4px 10px rgba(0,0,0,0.25);">
            <div class="brand-text">
                <h1 style="font-size: 17px; line-height: 1.2; font-weight: 800;">CU SmartPark</h1>
                <span style="font-size: 10px; color: #F87171; letter-spacing: 0.05em; font-weight: 700;">CHANDIGARH UNIVERSITY</span>
            </div>
        </div>
        <button type="button" class="sidebar-close-btn" id="sidebarClose" aria-label="Close Navigation">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-category">Main Management</li>
        
        <li class="menu-item">
            <a href="dashboard.php" class="menu-link <?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="parking-slots.php" class="menu-link <?php echo ($activePage === 'parking-slots') ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span>Parking Slots</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="bookings.php" class="menu-link <?php echo ($activePage === 'bookings') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Online Bookings</span>
            </a>
        </li>

        <li class="menu-category">Vehicles & Operations</li>

        <li class="menu-item">
            <a href="vehicle-entry.php" class="menu-link <?php echo ($activePage === 'vehicle-entry') ? 'active' : ''; ?>">
                <i class="fas fa-sign-in-alt"></i>
                <span>Vehicle Entry</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="vehicle-exit.php" class="menu-link <?php echo ($activePage === 'vehicle-exit') ? 'active' : ''; ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span>Vehicle Exit</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="vehicle-records.php" class="menu-link <?php echo ($activePage === 'vehicle-records') ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Vehicle Records</span>
            </a>
        </li>

        <li class="menu-category">Finance & Reports</li>

        <li class="menu-item">
            <a href="payments.php" class="menu-link <?php echo ($activePage === 'payments') ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span>Payments</span>
            </a>
        </li>

        <li class="menu-item">
            <a href="reports.php" class="menu-link <?php echo ($activePage === 'reports') ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports & Analytics</span>
            </a>
        </li>

        <li class="menu-category">System</li>

        <li class="menu-item">
            <a href="settings.php" class="menu-link <?php echo ($activePage === 'settings') ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>

    <div class="sidebar-footer">
        <div class="admin-brief">
            <div class="admin-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="admin-info">
                <div class="name"><?php echo sanitize($_SESSION['admin_name'] ?? 'Administrator'); ?></div>
                <div class="role">System Admin</div>
            </div>
            <a href="../logout.php" class="logout-btn-sidebar" title="Sign Out">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </div>
</aside>
