<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Vehicle Records & History Page
 */

$pageTitle = 'Vehicle Records';
$activePage = 'vehicle-records';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filters & Sorting parameters
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$type   = trim($_GET['type'] ?? '');
$date   = trim($_GET['date'] ?? '');
$sort   = trim($_GET['sort'] ?? 'newest');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Base Query
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (v.registration_number LIKE ? OR r.ticket_id LIKE ? OR v.owner_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $whereClause .= " AND r.status = ?";
    $params[] = $status;
}

if (!empty($type)) {
    $whereClause .= " AND v.vehicle_type = ?";
    $params[] = $type;
}

if (!empty($date)) {
    $whereClause .= " AND r.entry_time >= ? AND r.entry_time <= ?";
    $params[] = $date . " 00:00:00";
    $params[] = $date . " 23:59:59";
}

// Count total records for pagination
$countSql = "
    SELECT COUNT(*) 
    FROM parking_records r
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    $whereClause
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));

// Sorting
$orderBy = "r.id DESC";
if ($sort === 'oldest') {
    $orderBy = "r.id ASC";
} elseif ($sort === 'fee_high') {
    $orderBy = "r.total_fee DESC";
} elseif ($sort === 'duration') {
    $orderBy = "r.duration_minutes DESC";
}

// Fetch Paginated Records
$recordsSql = "
    SELECT r.id, r.ticket_id, r.entry_time, r.exit_time, r.duration_minutes, r.total_fee, r.status,
           v.registration_number, v.owner_name, v.owner_phone, v.vehicle_type,
           s.slot_number,
           p.payment_method, p.amount as payment_amount, p.payment_date
    FROM parking_records r
    JOIN vehicles v ON r.vehicle_id = v.id
    JOIN parking_slots s ON r.slot_id = s.id
    LEFT JOIN payments p ON p.parking_record_id = r.id
    $whereClause
    ORDER BY $orderBy
    LIMIT $limit OFFSET $offset
";
$stmtRecords = $pdo->prepare($recordsSql);
$stmtRecords->execute($params);
$records = $stmtRecords->fetchAll();

// URL builder for pagination preserving filters
function pagination_url($pageNum) {
    $query = $_GET;
    $query['page'] = $pageNum;
    return 'vehicle-records.php?' . http_build_query($query);
}
?>

<!-- Header Toolbar -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Vehicle Parking History</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Comprehensive audit logs of all parking transactions, durations, and payment settlements.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="../actions/export-records.php?<?php echo http_build_query($_GET); ?>" class="btn btn-secondary">
            <i class="fas fa-file-csv"></i> Export to CSV
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 18px 20px;">
        <form action="vehicle-records.php" method="GET" class="toolbar" style="margin-bottom: 0;">
            <div class="filter-group">
                <input type="text" name="search" class="filter-input" placeholder="Search Reg No / Ticket / Owner..." 
                       value="<?php echo sanitize($search); ?>" style="width: 240px;">

                <input type="date" name="date" class="filter-input" value="<?php echo sanitize($date); ?>" title="Filter by Entry Date">

                <select name="type" class="filter-input">
                    <option value="">All Categories</option>
                    <option value="Two Wheeler" <?php echo ($type === 'Two Wheeler') ? 'selected' : ''; ?>>Two Wheeler</option>
                    <option value="Car" <?php echo ($type === 'Car') ? 'selected' : ''; ?>>Car</option>
                    <option value="SUV" <?php echo ($type === 'SUV') ? 'selected' : ''; ?>>SUV</option>
                    <option value="Other" <?php echo ($type === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>

                <select name="status" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="Parked" <?php echo ($status === 'Parked') ? 'selected' : ''; ?>>Parked (Active)</option>
                    <option value="Completed" <?php echo ($status === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                </select>

                <select name="sort" class="filter-input">
                    <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Sort: Newest First</option>
                    <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Sort: Oldest First</option>
                    <option value="fee_high" <?php echo ($sort === 'fee_high') ? 'selected' : ''; ?>>Sort: Fee (High to Low)</option>
                    <option value="duration" <?php echo ($sort === 'duration') ? 'selected' : ''; ?>>Sort: Duration (Longest)</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <?php if (!empty($search) || !empty($status) || !empty($type) || !empty($date) || $sort !== 'newest'): ?>
                    <a href="vehicle-records.php" class="btn btn-secondary btn-sm" style="color: var(--text-muted);">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Records Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-database"></i> Parking Records (<?php echo $totalRecords; ?> Total Entries)
        </div>
        <span style="font-size: 12px; color: var(--text-muted);">
            Showing page <?php echo $page; ?> of <?php echo $totalPages; ?>
        </span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="recordsTable">
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Vehicle Number</th>
                        <th>Owner Name</th>
                        <th>Category</th>
                        <th>Bay</th>
                        <th>Entry Time</th>
                        <th>Exit Time</th>
                        <th>Duration</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="11" class="empty-state">
                                <i class="fas fa-folder-open empty-state-icon"></i>
                                <div class="empty-state-title">No parking records found</div>
                                <div class="empty-state-text">No parking sessions match your search or filter parameters.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($records as $r): ?>
                            <?php
                            $durationText = 'In Progress';
                            if ($r['status'] === 'Completed' && $r['duration_minutes'] !== null) {
                                $h = floor($r['duration_minutes'] / 60);
                                $m = $r['duration_minutes'] % 60;
                                $durationText = ($h > 0 ? $h . ' hr ' : '') . $m . ' min';
                            } elseif ($r['status'] === 'Parked') {
                                $currentMins = ceil((time() - strtotime($r['entry_time'])) / 60);
                                $h = floor($currentMins / 60);
                                $m = $currentMins % 60;
                                $durationText = ($h > 0 ? $h . ' hr ' : '') . $m . ' min (Active)';
                            }
                            ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 12px; color: var(--primary-blue);">
                                        <?php echo sanitize($r['ticket_id']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong style="font-family: monospace; font-size: 13.5px;">
                                        <?php echo sanitize($r['registration_number']); ?>
                                    </strong>
                                </td>
                                <td><?php echo sanitize($r['owner_name']); ?></td>
                                <td>
                                    <span class="badge" style="background: #F1F5F9; color: #334155;">
                                        <?php echo sanitize($r['vehicle_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
                                        <?php echo sanitize($r['slot_number']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px;">
                                    <?php echo date('d M Y', strtotime($r['entry_time'])); ?><br>
                                    <span style="color: var(--text-muted);"><?php echo date('h:i A', strtotime($r['entry_time'])); ?></span>
                                </td>
                                <td style="font-size: 12px;">
                                    <?php if ($r['exit_time']): ?>
                                        <?php echo date('d M Y', strtotime($r['exit_time'])); ?><br>
                                        <span style="color: var(--text-muted);"><?php echo date('h:i A', strtotime($r['exit_time'])); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--warning-orange); font-weight: 600;">-- Active --</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; font-weight: 500;">
                                    <?php echo $durationText; ?>
                                </td>
                                <td style="font-weight: 700; color: var(--text-primary);">
                                    <?php echo $r['total_fee'] !== null ? format_currency($r['total_fee']) : '<span style="color: var(--text-muted);">TBD</span>'; ?>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'Parked'): ?>
                                        <span class="badge badge-parked"><i class="fas fa-clock"></i> Parked</span>
                                    <?php else: ?>
                                        <span class="badge badge-completed"><i class="fas fa-check"></i> Completed</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <button type="button" class="btn btn-secondary btn-sm" 
                                            onclick="viewRecordDetails(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Footer -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div style="font-size: 13px; color: var(--text-muted);">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> of <strong><?php echo $totalRecords; ?></strong> entries
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <a href="<?php echo pagination_url($page - 1); ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-chevron-left"></i> Prev
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?php echo pagination_url($i); ?>" 
                       class="btn btn-sm <?php echo ($i === $page) ? 'btn-primary' : 'btn-secondary'; ?>" 
                       style="min-width: 34px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo pagination_url($page + 1); ?>" class="btn btn-secondary btn-sm">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: View Record Details -->
<div class="modal-overlay" id="recordDetailsModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle" style="color: var(--primary-blue); margin-right: 6px;"></i> Parking Session Details</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('recordDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" id="modalRecordContent">
            <!-- Populated dynamically via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('recordDetailsModal')">Close</button>
        </div>
    </div>
</div>

<script>
function viewRecordDetails(rec) {
    const content = `
        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13.5px;">
            <div style="background: #F8FAFC; padding: 12px; border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Ticket ID</div>
                <div style="font-family: monospace; font-size: 18px; font-weight: 800; color: var(--primary-blue);">${rec.ticket_id}</div>
            </div>
            
            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Vehicle Registration:</span>
                <strong style="font-family: monospace;">${rec.registration_number}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Vehicle Category:</span>
                <strong>${rec.vehicle_type}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Owner / Driver Name:</span>
                <strong>${rec.owner_name}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Contact Phone:</span>
                <strong>${rec.owner_phone}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Assigned Bay:</span>
                <span class="badge" style="background: #DBEAFE; color: #1E40AF; font-weight: 700;">${rec.slot_number}</span>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Check-In Time:</span>
                <strong>${rec.entry_time}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Check-Out Time:</span>
                <strong>${rec.exit_time ? rec.exit_time : 'Still Parked'}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Total Duration:</span>
                <strong>${rec.duration_minutes ? rec.duration_minutes + ' minutes' : 'Active Session'}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                <span style="color: var(--text-muted);">Total Fee Charged:</span>
                <strong style="color: var(--success-green); font-size: 16px;">${rec.total_fee ? '₹' + parseFloat(rec.total_fee).toFixed(2) : 'Pending Exit'}</strong>
            </div>

            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Payment Method:</span>
                <strong>${rec.payment_method ? rec.payment_method : 'Not Billed Yet'}</strong>
            </div>
        </div>
    `;
    document.getElementById('modalRecordContent').innerHTML = content;
    openModal('recordDetailsModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
