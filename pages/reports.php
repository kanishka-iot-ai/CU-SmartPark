<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Reports & Analytics Dashboard
 */

$pageTitle = 'Reports & Analytics';
$activePage = 'reports';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// 1. High-level aggregates
$totalRevenue = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'")->fetchColumn();
$totalCompletedSessions = (int)$pdo->query("SELECT COUNT(*) FROM parking_records WHERE status = 'Completed'")->fetchColumn();
$totalActiveSessions = (int)$pdo->query("SELECT COUNT(*) FROM parking_records WHERE status = 'Parked'")->fetchColumn();
$totalVehiclesRegistered = (int)$pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();

// 2. Daily Vehicle Entries for last 14 days
$dailyLabels = [];
$dailyEntries = [];
for ($i = 13; $i >= 0; $i--) {
    $dayStart = date('Y-m-d 00:00:00', strtotime("-$i days"));
    $dayEnd   = date('Y-m-d 23:59:59', strtotime("-$i days"));
    $label    = date('d M', strtotime("-$i days"));
    
    $stmtDaily = $pdo->prepare("SELECT COUNT(*) FROM parking_records WHERE entry_time BETWEEN ? AND ?");
    $stmtDaily->execute([$dayStart, $dayEnd]);
    $count = (int)$stmtDaily->fetchColumn();
    
    $dailyLabels[] = $label;
    $dailyEntries[] = $count;
}

// 3. Monthly Vehicle Entries for past 6 months
$monthLabels = [];
$monthEntries = [];
for ($m = 5; $m >= 0; $m--) {
    $mStart = date('Y-m-01 00:00:00', strtotime("-$m months"));
    $mEnd   = date('Y-m-t 23:59:59', strtotime("-$m months"));
    $label  = date('M Y', strtotime("-$m months"));

    $stmtMonth = $pdo->prepare("SELECT COUNT(*) FROM parking_records WHERE entry_time BETWEEN ? AND ?");
    $stmtMonth->execute([$mStart, $mEnd]);
    $mCount = (int)$stmtMonth->fetchColumn();

    $monthLabels[] = $label;
    $monthEntries[] = $mCount;
}

// 4. Vehicle Type Distribution
$distStmt = $pdo->query("
    SELECT vehicle_type, COUNT(*) as count 
    FROM vehicles 
    GROUP BY vehicle_type
");
$distRows = $distStmt->fetchAll();
$typeLabels = [];
$typeCounts = [];
foreach ($distRows as $dr) {
    $typeLabels[] = $dr['vehicle_type'];
    $typeCounts[] = (int)$dr['count'];
}

// 5. Most-Used Parking Slots Table
$topSlotsSql = "
    SELECT s.slot_number, s.vehicle_type, s.status,
           COUNT(r.id) as usage_count,
           COALESCE(SUM(r.total_fee), 0) as total_revenue,
           COALESCE(SUM(r.duration_minutes), 0) as total_mins
    FROM parking_slots s
    LEFT JOIN parking_records r ON s.id = r.slot_id
    GROUP BY s.id, s.slot_number, s.vehicle_type, s.status
    ORDER BY usage_count DESC, total_revenue DESC
    LIMIT 10
";
$topSlots = $pdo->query($topSlotsSql)->fetchAll();
?>

<!-- Header -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Management Reports &amp; Statistical Insights</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Real-time visual charts, slot turnover statistics, and category distributions derived from MySQL.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="../actions/export-reports.php" class="btn btn-primary">
            <i class="fas fa-file-csv"></i> Export Slot Utilization CSV
        </a>
    </div>
</div>

<!-- Overview Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-sack-dollar"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Total Lifetime Revenue</span>
            <span class="stat-value"><?php echo format_currency($totalRevenue); ?></span>
            <span class="stat-subtitle">All completed sessions</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-flag-checkered"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Completed Sessions</span>
            <span class="stat-value"><?php echo $totalCompletedSessions; ?></span>
            <span class="stat-subtitle">Checked out &amp; settled</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-hourglass-half"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Currently Active</span>
            <span class="stat-value"><?php echo $totalActiveSessions; ?></span>
            <span class="stat-subtitle">Vehicles in bays</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-car-alt"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Registered Fleet</span>
            <span class="stat-value"><?php echo $totalVehiclesRegistered; ?></span>
            <span class="stat-subtitle">Unique vehicles logged</span>
        </div>
    </div>
</div>

<!-- Charts Row 1: Daily Entries & Category Distribution -->
<div class="reports-charts-grid" style="margin-bottom: 24px;">
    
    <!-- Daily Vehicle Entries Chart -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-calendar-day"></i> Daily Vehicle Entries (Past 14 Days)
            </div>
        </div>
        <div class="card-body">
            <canvas id="dailyEntriesChart" style="max-height: 280px;"></canvas>
        </div>
    </div>

    <!-- Vehicle Category Breakdown Doughnut -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <div class="card-title">
                <i class="fas fa-chart-pie"></i> Vehicle Category Share
            </div>
        </div>
        <div class="card-body" style="display: flex; align-items: center; justify-content: center;">
            <div style="width: 100%; max-width: 260px;">
                <canvas id="typeDistributionChart" style="max-height: 260px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2: Monthly Entries Bar Chart -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-calendar-alt"></i> Monthly Vehicle Volume (Past 6 Months)
        </div>
    </div>
    <div class="card-body">
        <canvas id="monthlyVolumeChart" style="max-height: 240px;"></canvas>
    </div>
</div>

<!-- Most-Used Parking Slots Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-ranking-star"></i> Top 10 Most-Utilized Parking Bays
        </div>
        <span style="font-size: 12px; color: var(--text-muted);">Ranked by check-in frequency and turnover</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>Bay Code</th>
                        <th>Designated Category</th>
                        <th>Current Status</th>
                        <th>Completed Sessions</th>
                        <th>Total Time Occupied</th>
                        <th style="text-align: right;">Generated Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topSlots)): ?>
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div class="empty-state-text">No parking slot metrics recorded yet.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($topSlots as $ts): ?>
                            <?php
                            $hours = floor($ts['total_mins'] / 60);
                            $mins = $ts['total_mins'] % 60;
                            $timeStr = ($hours > 0 ? $hours . ' hr ' : '') . $mins . ' min';
                            ?>
                            <tr>
                                <td>
                                    <span class="badge" style="background: #F1F5F9; color: #1E293B; font-weight: 800; min-width: 24px; text-align: center;">
                                        #<?php echo $rank++; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="font-size: 14.5px; color: var(--text-primary); letter-spacing: 0.04em;">
                                        <?php echo sanitize($ts['slot_number']); ?>
                                    </strong>
                                </td>
                                <td><?php echo sanitize($ts['vehicle_type']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($ts['status']); ?>">
                                        <?php echo sanitize($ts['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo $ts['usage_count']; ?></strong> session(s)
                                </td>
                                <td style="font-size: 12.5px; color: var(--text-secondary);">
                                    <?php echo $timeStr; ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; color: var(--primary-blue);">
                                    <?php echo format_currency($ts['total_revenue']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. Daily Entries Chart
    const dailyCtx = document.getElementById('dailyEntriesChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($dailyLabels); ?>,
            datasets: [{
                label: 'Vehicle Entries',
                data: <?php echo json_encode($dailyEntries); ?>,
                backgroundColor: '#3B82F6',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: '#F1F5F9' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Vehicle Type Doughnut Chart
    const typeCtx = document.getElementById('typeDistributionChart').getContext('2d');
    new Chart(typeCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($typeLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($typeCounts); ?>,
                backgroundColor: ['#2563EB', '#16A34A', '#F59E0B', '#9333EA'],
                borderWidth: 2,
                borderColor: '#FFFFFF'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            }
        }
    });

    // 3. Monthly Volume Chart
    const monthCtx = document.getElementById('monthlyVolumeChart').getContext('2d');
    new Chart(monthCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Monthly Entries',
                data: <?php echo json_encode($monthEntries); ?>,
                borderColor: '#16A34A',
                backgroundColor: 'rgba(22, 163, 74, 0.12)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#16A34A',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: '#F1F5F9' }
                },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
