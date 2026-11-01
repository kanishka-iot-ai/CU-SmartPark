<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Payment Management & Financial Audit Page
 */

$pageTitle = 'Payments & Settlements';
$activePage = 'payments';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filters
$search = trim($_GET['search'] ?? '');
$method = trim($_GET['method'] ?? '');
$date   = trim($_GET['date'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

// Base Filter
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (v.registration_number LIKE ? OR r.ticket_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($method)) {
    $whereClause .= " AND p.payment_method = ?";
    $params[] = $method;
}

if (!empty($date)) {
    $whereClause .= " AND p.payment_date >= ? AND p.payment_date <= ?";
    $params[] = $date . " 00:00:00";
    $params[] = $date . " 23:59:59";
}

// Summary Statistics
$totalRev = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'")->fetchColumn();
$cashRev  = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'Cash' AND payment_status = 'Paid'")->fetchColumn();
$upiRev   = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'UPI' AND payment_status = 'Paid'")->fetchColumn();
$cardRev  = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_method = 'Card' AND payment_status = 'Paid'")->fetchColumn();

// Count for pagination
$countSql = "
    SELECT COUNT(*)
    FROM payments p
    JOIN parking_records r ON p.parking_record_id = r.id
    JOIN vehicles v ON r.vehicle_id = v.id
    $whereClause
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));

// Fetch Records
$sql = "
    SELECT p.id as payment_id, p.amount, p.payment_method, p.payment_status, p.payment_date,
           r.ticket_id, r.entry_time, r.exit_time,
           v.registration_number, v.owner_name, v.vehicle_type,
           s.slot_number
    FROM payments p
    JOIN parking_records r ON p.parking_record_id = r.id
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    $whereClause
    ORDER BY p.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

function payment_page_url($pageNum) {
    $query = $_GET;
    $query['page'] = $pageNum;
    return 'payments.php?' . http_build_query($query);
}
?>

<!-- Header -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Payment Audit &amp; Settlement Log</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Track manual settlements, reconcile collection channels, and review financial history.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="../actions/export-payments.php?<?php echo http_build_query($_GET); ?>" class="btn btn-secondary">
            <i class="fas fa-file-csv"></i> Export Payments CSV
        </a>
    </div>
</div>

<!-- Financial Summary Cards -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Total Revenue</span>
            <span class="stat-value"><?php echo format_currency($totalRev); ?></span>
            <span class="stat-subtitle">All-time settlement</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Cash Collections</span>
            <span class="stat-value"><?php echo format_currency($cashRev); ?></span>
            <span class="stat-subtitle">Over-the-counter</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-qrcode"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">UPI Collections</span>
            <span class="stat-value"><?php echo format_currency($upiRev); ?></span>
            <span class="stat-subtitle">QR code scan</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-credit-card"></i>
        </div>
        <div class="stat-details">
            <span class="stat-title">Card Collections</span>
            <span class="stat-value"><?php echo format_currency($cardRev); ?></span>
            <span class="stat-subtitle">POS terminal</span>
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 18px 20px;">
        <form action="payments.php" method="GET" class="toolbar" style="margin-bottom: 0;">
            <div class="filter-group">
                <input type="text" name="search" class="filter-input" placeholder="Search Reg No or Ticket ID..." 
                       value="<?php echo sanitize($search); ?>" style="width: 250px;">

                <input type="date" name="date" class="filter-input" value="<?php echo sanitize($date); ?>" title="Filter by Payment Date">

                <select name="method" class="filter-input">
                    <option value="">All Payment Methods</option>
                    <option value="Cash" <?php echo ($method === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="UPI" <?php echo ($method === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                    <option value="Card" <?php echo ($method === 'Card') ? 'selected' : ''; ?>>Card</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <?php if (!empty($search) || !empty($method) || !empty($date)): ?>
                    <a href="payments.php" class="btn btn-secondary btn-sm" style="color: var(--text-muted);">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Payments Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-receipt"></i> Payment Transactions (<?php echo $totalRecords; ?> Found)
        </div>
        <span style="font-size: 12px; color: var(--text-muted);">
            Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <th>Ticket ID</th>
                        <th>Vehicle Number</th>
                        <th>Owner</th>
                        <th>Bay</th>
                        <th>Payment Date &amp; Time</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-receipt empty-state-icon"></i>
                                <div class="empty-state-title">No payment records found</div>
                                <div class="empty-state-text">No payment settlements match the given filter criteria.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 13px; color: var(--text-primary);">
                                        #PAY-<?php echo str_pad($p['payment_id'], 4, '0', STR_PAD_LEFT); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="font-family: monospace; font-size: 12px; color: var(--primary-blue);">
                                        <?php echo sanitize($p['ticket_id']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="font-family: monospace; font-weight: 700;"><?php echo sanitize($p['registration_number']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo sanitize($p['vehicle_type']); ?></div>
                                </td>
                                <td><?php echo sanitize($p['owner_name']); ?></td>
                                <td>
                                    <span class="badge" style="background: #F1F5F9; color: #334155; font-weight: 700;">
                                        <?php echo sanitize($p['slot_number']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 12.5px;">
                                    <?php echo date('d M Y, h:i A', strtotime($p['payment_date'])); ?>
                                </td>
                                <td>
                                    <?php
                                    $methodColor = '#0284C7';
                                    $methodBg = '#E0F2FE';
                                    $icon = 'fa-money-bill-wave';
                                    if ($p['payment_method'] === 'UPI') {
                                        $methodColor = '#7C3AED';
                                        $methodBg = '#EDE9FE';
                                        $icon = 'fa-qrcode';
                                    } elseif ($p['payment_method'] === 'Card') {
                                        $methodColor = '#D97706';
                                        $methodBg = '#FEF3C7';
                                        $icon = 'fa-credit-card';
                                    }
                                    ?>
                                    <span class="badge" style="background: <?php echo $methodBg; ?>; color: <?php echo $methodColor; ?>; font-weight: 700;">
                                        <i class="fas <?php echo $icon; ?>" style="margin-right: 4px;"></i>
                                        <?php echo sanitize($p['payment_method']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 15px; font-weight: 800; color: var(--success-green);">
                                    <?php echo format_currency($p['amount']); ?>
                                </td>
                                <td>
                                    <span class="badge badge-available">
                                        <i class="fas fa-check"></i> <?php echo sanitize($p['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div style="font-size: 13px; color: var(--text-muted);">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> of <strong><?php echo $totalRecords; ?></strong> transactions
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <a href="<?php echo payment_page_url($page - 1); ?>" class="btn btn-secondary btn-sm">Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?php echo payment_page_url($i); ?>" class="btn btn-sm <?php echo ($i === $page) ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo payment_page_url($page + 1); ?>" class="btn btn-secondary btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
