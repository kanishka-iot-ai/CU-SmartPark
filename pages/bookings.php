<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Admin Online Bookings Management Page
 */

$pageTitle = 'Online Bookings';
$activePage = 'bookings';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filters & Search
$search = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (b.booking_id LIKE ? OR b.vehicle_number LIKE ? OR b.customer_name LIKE ? OR b.customer_phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($statusFilter)) {
    $whereClause .= " AND b.booking_status = ?";
    $params[] = $statusFilter;
}

if (!empty($dateFilter)) {
    $whereClause .= " AND b.booking_date = ?";
    $params[] = $dateFilter;
}

// Summary Metrics
$totalBookings    = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$confirmedCount   = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Confirmed'")->fetchColumn();
$checkedInCount   = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Checked In'")->fetchColumn();
$completedCount   = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Completed'")->fetchColumn();
$cancelledCount   = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'Cancelled'")->fetchColumn();

// Count for pagination
$countSql = "SELECT COUNT(*) FROM bookings b JOIN parking_slots s ON b.slot_id = s.id $whereClause";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRecords = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRecords / $limit));

// Fetch Records
$sql = "
    SELECT b.*, s.slot_number
    FROM bookings b
    JOIN parking_slots s ON b.slot_id = s.id
    $whereClause
    ORDER BY b.id DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$csrf_token = get_csrf_token();

function booking_pagination_url($pageNum) {
    $query = $_GET;
    $query['page'] = $pageNum;
    return 'bookings.php?' . http_build_query($query);
}
?>

<!-- Header -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Online Reservations Management</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Verify customer arrival, check in confirmed reservations, and manage advance bookings.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="../booking.php" target="_blank" class="btn btn-secondary">
            <i class="fas fa-external-link-alt"></i> Public Booking Page
        </a>
        <a href="../actions/export-bookings.php?<?php echo http_build_query($_GET); ?>" class="btn btn-secondary">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>
</div>

<!-- Fast Check-In Callout Card -->
<div class="card" style="border: 2px solid #BFDBFE; background: #EFF6FF; margin-bottom: 24px;">
    <div class="card-body" style="padding: 18px 24px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
            <div>
                <h4 style="font-size: 16px; font-weight: 800; color: #1E40AF; margin-bottom: 4px;">
                    <i class="fas fa-barcode"></i> Express Customer Arrival Check-In
                </h4>
                <p style="font-size: 13px; color: #3B82F6; margin: 0;">
                    Enter the customer's Booking ID (e.g. BK-20261101-1001) to verify reservation and immediately assign their bay.
                </p>
            </div>
            <form action="../actions/admin-checkin-booking.php" method="POST" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                <input type="text" name="booking_id" placeholder="Enter Booking ID..." required 
                       style="padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid #93C5FD; font-family: monospace; font-size: 14px; font-weight: 700; text-transform: uppercase; width: 220px; outline: none;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Check In Customer
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Summary Metrics Badges -->
<div style="display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px;">
    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-calendar-alt" style="color: var(--primary-blue); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Reservations</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--text-primary);"><?php echo $totalBookings; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-clock" style="color: var(--warning-orange); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Awaiting Arrival</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--warning-orange);"><?php echo $confirmedCount; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-car" style="color: var(--primary-blue); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Currently Checked In</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--primary-blue);"><?php echo $checkedInCount; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-check-double" style="color: var(--success-green); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Completed</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--success-green);"><?php echo $completedCount; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-times-circle" style="color: var(--danger-red); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Cancelled</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--danger-red);"><?php echo $cancelledCount; ?></div>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form action="bookings.php" method="GET" class="toolbar" style="margin-bottom: 0;">
            <div class="filter-group">
                <input type="text" name="search" class="filter-input" placeholder="Search Booking / Reg No / Phone..." 
                       value="<?php echo sanitize($search); ?>" style="width: 250px;">

                <input type="date" name="date" class="filter-input" value="<?php echo sanitize($dateFilter); ?>" title="Filter by Booking Date">

                <select name="status" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="Confirmed" <?php echo ($statusFilter === 'Confirmed') ? 'selected' : ''; ?>>Confirmed (Awaiting)</option>
                    <option value="Checked In" <?php echo ($statusFilter === 'Checked In') ? 'selected' : ''; ?>>Checked In</option>
                    <option value="Completed" <?php echo ($statusFilter === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo ($statusFilter === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>

                <?php if (!empty($search) || !empty($statusFilter) || !empty($dateFilter)): ?>
                    <a href="bookings.php" class="btn btn-secondary btn-sm" style="color: var(--text-muted);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Bookings Table Card -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-list-check"></i> Customer Reservations (<?php echo $totalRecords; ?> Found)
        </div>
        <span style="font-size: 12px; color: var(--text-muted);">Showing page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Vehicle</th>
                        <th>Bay</th>
                        <th>Date &amp; Schedule</th>
                        <th>Estimated</th>
                        <th>Final Fee</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <i class="fas fa-calendar-times empty-state-icon"></i>
                                <div class="empty-state-title">No bookings found</div>
                                <div class="empty-state-text">No customer reservations match your search or filter parameters.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                            <?php
                            $badgeClass = 'badge-available';
                            if ($b['booking_status'] === 'Checked In') $badgeClass = 'badge-occupied';
                            elseif ($b['booking_status'] === 'Cancelled') $badgeClass = 'badge-reserved';
                            elseif ($b['booking_status'] === 'Completed') $badgeClass = 'badge-completed';
                            ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 12.5px; color: var(--primary-blue);">
                                        <?php echo sanitize($b['booking_id']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo sanitize($b['customer_name']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo sanitize($b['customer_phone']); ?></div>
                                </td>
                                <td>
                                    <div style="font-family: monospace; font-weight: 700;"><?php echo sanitize($b['vehicle_number']); ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?php echo sanitize($b['vehicle_type']); ?></div>
                                </td>
                                <td>
                                    <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">
                                        <?php echo sanitize($b['slot_number']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px;">
                                    <strong><?php echo date('d M Y', strtotime($b['booking_date'])); ?></strong><br>
                                    <span style="color: var(--text-muted);">
                                        <?php echo date('h:i A', strtotime($b['expected_entry_time'])); ?> - <?php echo date('h:i A', strtotime($b['expected_exit_time'])); ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: var(--text-secondary);">
                                    ₹<?php echo number_format($b['estimated_amount'], 2); ?>
                                </td>
                                <td style="font-weight: 700; color: var(--success-green);">
                                    <?php echo $b['final_amount'] !== null ? '₹' . number_format($b['final_amount'], 2) : '<span style="color: var(--text-muted);">--</span>'; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo sanitize($b['booking_status']); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        
                                        <!-- Check-In Action (Only for Confirmed) -->
                                        <?php if ($b['booking_status'] === 'Confirmed'): ?>
                                            <form action="../actions/admin-checkin-booking.php" method="POST" style="display: inline;"
                                                  onsubmit="return confirmAction('Check in customer <?php echo sanitize($b['customer_name']); ?> (<?php echo sanitize($b['vehicle_number']); ?>) into Bay <?php echo sanitize($b['slot_number']); ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo sanitize($b['booking_id']); ?>">
                                                <button type="submit" class="btn btn-primary btn-sm" title="Check In Vehicle">
                                                    <i class="fas fa-sign-in-alt"></i> Check In
                                                </button>
                                            </form>

                                            <!-- Cancel Action -->
                                            <form action="../actions/admin-update-booking-status.php" method="POST" style="display: inline;"
                                                  onsubmit="return confirmAction('Are you sure you want to cancel booking <?php echo sanitize($b['booking_id']); ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="booking_id" value="<?php echo sanitize($b['booking_id']); ?>">
                                                <input type="hidden" name="new_status" value="Cancelled">
                                                <button type="submit" class="btn btn-secondary btn-sm" title="Cancel Booking" style="color: var(--danger-red);">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>

                                        <?php elseif ($b['booking_status'] === 'Checked In'): ?>
                                            <a href="vehicle-exit.php?search=<?php echo urlencode($b['vehicle_number']); ?>" class="btn btn-success btn-sm" title="Process Exit & Billing">
                                                <i class="fas fa-sign-out-alt"></i> Checkout
                                            </a>
                                        <?php endif; ?>

                                        <!-- View Details Modal Trigger -->
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                onclick="viewBookingDetails(<?php echo htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8'); ?>)"
                                                title="View Booking Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <div style="font-size: 13px; color: var(--text-muted);">
                Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + $limit, $totalRecords); ?></strong> of <strong><?php echo $totalRecords; ?></strong> bookings
            </div>
            <div style="display: flex; gap: 6px;">
                <?php if ($page > 1): ?>
                    <a href="<?php echo booking_pagination_url($page - 1); ?>" class="btn btn-secondary btn-sm">Prev</a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="<?php echo booking_pagination_url($i); ?>" class="btn btn-sm <?php echo ($i === $page) ? 'btn-primary' : 'btn-secondary'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo booking_pagination_url($page + 1); ?>" class="btn btn-secondary btn-sm">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Booking Details -->
<div class="modal-overlay" id="bookingDetailModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-check" style="color: var(--primary-blue); margin-right: 6px;"></i> Reservation Details</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('bookingDetailModal')">&times;</button>
        </div>
        <div class="modal-body" id="modalBookingBody">
            <!-- Populated via JS -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('bookingDetailModal')">Close</button>
        </div>
    </div>
</div>

<script>
function viewBookingDetails(b) {
    const html = `
        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 13.5px;">
            <div style="background: #F8FAFC; padding: 14px; border-radius: var(--radius-md); text-align: center;">
                <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;">Booking Reference ID</div>
                <div style="font-family: monospace; font-size: 19px; font-weight: 800; color: var(--primary-blue);">${b.booking_id}</div>
                <div style="margin-top: 6px;"><span class="badge badge-available">${b.booking_status}</span></div>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Customer Name:</span>
                <strong>${b.customer_name}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Contact Phone:</span>
                <strong>${b.customer_phone}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Vehicle Number:</span>
                <strong style="font-family: monospace;">${b.vehicle_number} (${b.vehicle_type})</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Assigned Bay:</span>
                <span class="badge" style="background: #EFF6FF; color: #1E40AF; font-weight: 700;">${b.slot_number}</span>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Scheduled Date:</span>
                <strong>${b.booking_date}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Expected Arrival:</span>
                <strong>${b.expected_entry_time}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Expected Departure:</span>
                <strong>${b.expected_exit_time}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Actual Check-In:</span>
                <strong>${b.actual_entry_time ? b.actual_entry_time : 'Awaiting Arrival'}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Actual Check-Out:</span>
                <strong>${b.actual_exit_time ? b.actual_exit_time : 'Still Parked / Not Entered'}</strong>
            </div>

            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 6px;">
                <span style="color: var(--text-muted);">Estimated Price:</span>
                <strong style="color: var(--text-primary);">₹${parseFloat(b.estimated_amount).toFixed(2)}</strong>
            </div>

            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Final Paid Amount:</span>
                <strong style="color: var(--success-green); font-size: 16px;">${b.final_amount !== null ? '₹' + parseFloat(b.final_amount).toFixed(2) : 'Pending Checkout'}</strong>
            </div>
        </div>
    `;
    document.getElementById('modalBookingBody').innerHTML = html;
    openModal('bookingDetailModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
