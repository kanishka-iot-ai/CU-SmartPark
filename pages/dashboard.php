<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Admin Dashboard
 */

$pageTitle = 'Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();
$todayMidnight = date('Y-m-d 00:00:00');

// 1. Statistics Queries
$totalSlots = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();
$availableSlots = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'Available'")->fetchColumn();
$occupiedSlots = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'Occupied'")->fetchColumn();

// Vehicles Today
$stmtVehToday = $pdo->prepare("SELECT COUNT(*) FROM parking_records WHERE entry_time >= ?");
$stmtVehToday->execute([$todayMidnight]);
$vehiclesToday = (int)$stmtVehToday->fetchColumn();

// Revenue Today
$stmtRevToday = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date >= ? AND payment_status = 'Paid'");
$stmtRevToday->execute([$todayMidnight]);
$revenueToday = (float)$stmtRevToday->fetchColumn();

// 2. Parking Occupancy Slots with Vehicle Info
$slotsQuery = "
    SELECT s.id, s.slot_number, s.vehicle_type, s.status,
           r.ticket_id, r.entry_time,
           v.registration_number, v.owner_name
    FROM parking_slots s
    LEFT JOIN parking_records r ON s.id = r.slot_id AND r.status = 'Parked'
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    ORDER BY s.id ASC
";
$slots = $pdo->query($slotsQuery)->fetchAll();

// 3. Recent 10 Activities (Entries & Exits)
$recentQuery = "
    SELECT r.id, r.ticket_id, r.entry_time, r.exit_time, r.duration_minutes, r.total_fee, r.status,
           v.registration_number, v.owner_name, v.vehicle_type,
           s.slot_number
    FROM parking_records r
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    ORDER BY r.id DESC
    LIMIT 10
";
$recentActivity = $pdo->query($recentQuery)->fetchAll();

// 4. Daily Revenue for past 7 days (for Chart.js)
$chartLabels = [];
$chartRevenue = [];
for ($i = 6; $i >= 0; $i--) {
    $dayStart = date('Y-m-d 00:00:00', strtotime("-$i days"));
    $dayEnd   = date('Y-m-d 23:59:59', strtotime("-$i days"));
    $label    = date('d M', strtotime("-$i days"));
    
    $stmtChart = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date BETWEEN ? AND ? AND payment_status = 'Paid'");
    $stmtChart->execute([$dayStart, $dayEnd]);
    $dayTotal = (float)$stmtChart->fetchColumn();
    
    $chartLabels[] = $label;
    $chartRevenue[] = $dayTotal;
}
?>

<!-- Quick Action Toolbar -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Welcome back, <?php echo sanitize($_SESSION['admin_name'] ?? 'Admin'); ?>!</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Real-time monitoring of parking bays, daily revenue, and active vehicle check-ins.</p>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="bookings.php" class="btn btn-primary" style="background: #4F46E5;">
            <i class="fas fa-calendar-check"></i> Online Bookings
        </a>
        <a href="vehicle-entry.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> New Vehicle Entry
        </a>
        <a href="vehicle-exit.php" class="btn btn-success">
            <i class="fas fa-sign-out-alt"></i> Process Exit &amp; Pay
        </a>
        <a href="parking-slots.php" class="btn btn-secondary">
            <i class="fas fa-th"></i> Manage Slots
        </a>
    </div>
</div>

<!-- Statistics Cards Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-parking"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Total Slots</span>
            <span class="stat-value"><?php echo $totalSlots; ?></span>
            <span class="stat-subtitle">Configured bays</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Available Slots</span>
            <span class="stat-value"><?php echo $availableSlots; ?></span>
            <span class="stat-subtitle"><?php echo ($totalSlots > 0) ? round(($availableSlots / $totalSlots) * 100) : 0; ?>% free space</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-car-side"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Occupied Slots</span>
            <span class="stat-value"><?php echo $occupiedSlots; ?></span>
            <span class="stat-subtitle">Currently parked</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Vehicles Today</span>
            <span class="stat-value"><?php echo $vehiclesToday; ?></span>
            <span class="stat-subtitle">Total check-ins</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-indian-rupee-sign"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Today's Revenue</span>
            <span class="stat-value"><?php echo format_currency($revenueToday); ?></span>
            <span class="stat-subtitle">Collected settlements</span>
        </div>
    </div>
</div>

<!-- Parking Occupancy Grid Section -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-border-all"></i> Real-Time Parking Occupancy Grid
        </div>
        <div class="slots-legend" style="margin-bottom: 0;">
            <div class="legend-item">
                <span class="legend-badge available"></span>
                <span>Available (<?php echo $availableSlots; ?>)</span>
            </div>
            <div class="legend-item">
                <span class="legend-badge occupied"></span>
                <span>Occupied (<?php echo $occupiedSlots; ?>)</span>
            </div>
            <div class="legend-item">
                <span class="legend-badge reserved"></span>
                <span>Reserved</span>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="slots-grid">
            <?php foreach ($slots as $slot): ?>
                <?php
                $statusClass = 'status-' . strtolower($slot['status']);
                $icon = 'fa-car';
                if ($slot['vehicle_type'] === 'Two Wheeler') $icon = 'fa-motorcycle';
                elseif ($slot['vehicle_type'] === 'SUV') $icon = 'fa-truck-pickup';
                elseif ($slot['vehicle_type'] === 'Other') $icon = 'fa-truck';
                ?>
                <div class="slot-box <?php echo $statusClass; ?>">
                    <div class="slot-top">
                        <span class="slot-number"><?php echo sanitize($slot['slot_number']); ?></span>
                        <i class="fas <?php echo $icon; ?> slot-type-icon" title="<?php echo sanitize($slot['vehicle_type']); ?>"></i>
                    </div>

                    <div class="slot-middle">
                        <?php if ($slot['status'] === 'Occupied'): ?>
                            <div class="slot-vehicle-number">
                                <?php echo sanitize($slot['registration_number'] ?? 'Occupied'); ?>
                            </div>
                            <div style="font-size: 11px; text-align: center; color: var(--text-muted); margin-top: 4px;">
                                <?php echo sanitize($slot['owner_name'] ?? ''); ?>
                            </div>
                        <?php elseif ($slot['status'] === 'Reserved'): ?>
                            <div style="font-size: 12px; font-weight: 600; color: var(--warning-orange); text-align: center;">
                                <i class="fas fa-lock"></i> Reserved
                            </div>
                        <?php else: ?>
                            <div class="slot-empty-label">
                                <i class="fas fa-check"></i> Vacant
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="slot-bottom">
                        <span><?php echo sanitize($slot['vehicle_type']); ?></span>
                        <span class="badge badge-<?php echo strtolower($slot['status']); ?>">
                            <?php echo sanitize($slot['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Two Column Section: Revenue Chart & Recent Activity -->
<div class="dashboard-split-grid">
    
    <!-- Revenue Chart Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-chart-line"></i> Daily Revenue Trend (Last 7 Days)
            </div>
            <a href="reports.php" class="btn btn-secondary btn-sm">Full Report</a>
        </div>
        <div class="card-body">
            <canvas id="revenueTrendChart" style="max-height: 280px;"></canvas>
        </div>
    </div>

    <!-- Recent Vehicle Activity Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-history"></i> Recent Vehicle Activity (Last 10)
            </div>
            <a href="vehicle-records.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Vehicle</th>
                            <th>Slot</th>
                            <th>Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentActivity)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    <div class="empty-state-text">No recent vehicle activities found.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $act): ?>
                                <tr>
                                    <td>
                                        <strong style="font-family: monospace; font-size: 12px; color: var(--primary-blue);">
                                            <?php echo sanitize($act['ticket_id']); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo sanitize($act['registration_number']); ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo sanitize($act['vehicle_type']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #F1F5F9; color: #334155; font-weight: 700;">
                                            <?php echo sanitize($act['slot_number']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 12px;"><?php echo date('h:i A', strtotime($act['entry_time'])); ?></div>
                                        <div style="font-size: 10px; color: var(--text-muted);"><?php echo date('d M', strtotime($act['entry_time'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($act['status'] === 'Parked'): ?>
                                            <span class="badge badge-parked"><i class="fas fa-clock"></i> Parked</span>
                                        <?php else: ?>
                                            <span class="badge badge-completed"><i class="fas fa-check"></i> Left (<?php echo format_currency($act['total_fee']); ?>)</span>
                                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('revenueTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Revenue (₹)',
                data: <?php echo json_encode($chartRevenue); ?>,
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2563EB',
                pointBorderColor: '#FFFFFF',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return ' Revenue: ₹' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function (value) {
                            return '₹' + value;
                        }
                    },
                    grid: { color: '#F1F5F9' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
