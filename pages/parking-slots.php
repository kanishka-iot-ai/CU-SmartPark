<?php
/**
 * SmartPark – Vehicle Parking Management System
 * Parking Slot Management Page
 */

$pageTitle = 'Parking Slots';
$activePage = 'parking-slots';

require_once __DIR__ . '/../includes/header.php';

$pdo = get_db_connection();

// Filters from GET
$searchQuery = trim($_GET['search'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$typeFilter   = trim($_GET['type'] ?? '');

$sql = "SELECT * FROM parking_slots WHERE 1=1";
$params = [];

if (!empty($searchQuery)) {
    $sql .= " AND slot_number LIKE ?";
    $params[] = "%" . $searchQuery . "%";
}

if (!empty($statusFilter)) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

if (!empty($typeFilter)) {
    $sql .= " AND vehicle_type = ?";
    $params[] = $typeFilter;
}

$sql .= " ORDER BY slot_number ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$slots = $stmt->fetchAll();

// Counts for summary pills
$countTotal = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots")->fetchColumn();
$countAvailable = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'Available'")->fetchColumn();
$countOccupied = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'Occupied'")->fetchColumn();
$countReserved = (int)$pdo->query("SELECT COUNT(*) FROM parking_slots WHERE status = 'Reserved'")->fetchColumn();

$csrf_token = get_csrf_token();
?>

<!-- Header & Add Button -->
<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
    <div>
        <h3 style="font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 4px;">Parking Slot Management</h3>
        <p style="font-size: 13px; color: var(--text-muted);">Configure, monitor, and maintain physical parking bays across all vehicle categories.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openModal('addSlotModal')">
            <i class="fas fa-plus"></i> Add New Slot
        </button>
    </div>
</div>

<!-- Category Stat Badges -->
<div style="display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 24px;">
    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-th" style="color: var(--primary-blue); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Total Slots</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--text-primary);"><?php echo $countTotal; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-check-circle" style="color: var(--success-green); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Available</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--success-green);"><?php echo $countAvailable; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-car" style="color: var(--danger-red); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Occupied</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--danger-red);"><?php echo $countOccupied; ?></div>
        </div>
    </div>

    <div style="background: #FFFFFF; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; display: flex; align-items: center; gap: 12px; box-shadow: var(--shadow-sm);">
        <i class="fas fa-lock" style="color: var(--warning-orange); font-size: 18px;"></i>
        <div>
            <div style="font-size: 11px; color: var(--text-muted); font-weight: 600; text-transform: uppercase;">Reserved</div>
            <div style="font-size: 18px; font-weight: 800; color: var(--warning-orange);"><?php echo $countReserved; ?></div>
        </div>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form action="parking-slots.php" method="GET" class="toolbar" style="margin-bottom: 0;">
            <div class="filter-group">
                <input type="text" name="search" class="filter-input" placeholder="Search slot (e.g. CR-01)..." 
                       value="<?php echo sanitize($searchQuery); ?>" style="width: 200px;">

                <select name="status" class="filter-input">
                    <option value="">All Statuses</option>
                    <option value="Available" <?php echo ($statusFilter === 'Available') ? 'selected' : ''; ?>>Available</option>
                    <option value="Occupied" <?php echo ($statusFilter === 'Occupied') ? 'selected' : ''; ?>>Occupied</option>
                    <option value="Reserved" <?php echo ($statusFilter === 'Reserved') ? 'selected' : ''; ?>>Reserved</option>
                </select>

                <select name="type" class="filter-input">
                    <option value="">All Vehicle Types</option>
                    <option value="Two Wheeler" <?php echo ($typeFilter === 'Two Wheeler') ? 'selected' : ''; ?>>Two Wheeler</option>
                    <option value="Car" <?php echo ($typeFilter === 'Car') ? 'selected' : ''; ?>>Car</option>
                    <option value="SUV" <?php echo ($typeFilter === 'SUV') ? 'selected' : ''; ?>>SUV</option>
                    <option value="Other" <?php echo ($typeFilter === 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>

                <button type="submit" class="btn btn-secondary btn-sm">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>

                <?php if (!empty($searchQuery) || !empty($statusFilter) || !empty($typeFilter)): ?>
                    <a href="parking-slots.php" class="btn btn-secondary btn-sm" style="color: var(--text-muted);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </div>

            <div>
                <input type="text" data-table-search="slotsTable" class="filter-input" placeholder="Instant table search..." style="width: 190px;">
            </div>
        </form>
    </div>
</div>

<!-- Parking Slots Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-list"></i> All Parking Slots (<?php echo count($slots); ?> records)
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table" id="slotsTable">
                <thead>
                    <tr>
                        <th style="width: 70px;">Slot ID</th>
                        <th>Slot Number</th>
                        <th>Vehicle Category</th>
                        <th>Current Status</th>
                        <th>Created Date</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($slots)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-th-large empty-state-icon"></i>
                                <div class="empty-state-title">No parking slots found</div>
                                <div class="empty-state-text">No slots match your selected filters. Try clearing the filter or adding a new slot.</div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($slots as $slot): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-muted); font-size: 12px;">
                                    #<?php echo $slot['id']; ?>
                                </td>
                                <td>
                                    <strong style="font-size: 15px; color: var(--text-primary); letter-spacing: 0.03em;">
                                        <?php echo sanitize($slot['slot_number']); ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $icon = 'fa-car';
                                    if ($slot['vehicle_type'] === 'Two Wheeler') $icon = 'fa-motorcycle';
                                    elseif ($slot['vehicle_type'] === 'SUV') $icon = 'fa-truck-pickup';
                                    elseif ($slot['vehicle_type'] === 'Other') $icon = 'fa-truck';
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas <?php echo $icon; ?>" style="color: var(--primary-blue);"></i>
                                        <span><?php echo sanitize($slot['vehicle_type']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($slot['status']); ?>">
                                        <?php echo sanitize($slot['status']); ?>
                                    </span>
                                </td>
                                <td style="font-size: 12.5px; color: var(--text-secondary);">
                                    <?php echo date('d M Y, h:i A', strtotime($slot['created_at'])); ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <!-- Edit Button -->
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($slot), ENT_QUOTES, 'UTF-8'); ?>)"
                                                title="Edit Slot">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <!-- Delete Button with Occurred Slot Protection -->
                                        <?php if ($slot['status'] === 'Occupied'): ?>
                                            <button type="button" class="btn btn-secondary btn-sm" disabled 
                                                    style="opacity: 0.4; cursor: not-allowed;"
                                                    title="Cannot delete occupied slot">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <form action="../actions/delete-slot.php" method="POST" style="display: inline;" 
                                                  onsubmit="return confirmAction('Are you sure you want to delete parking slot <?php echo sanitize($slot['slot_number']); ?>? This action cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="id" value="<?php echo $slot['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Slot">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Add Parking Slot -->
<div class="modal-overlay" id="addSlotModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle" style="color: var(--primary-blue); margin-right: 6px;"></i> Add New Parking Slot</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addSlotModal')">&times;</button>
        </div>
        <form action="../actions/add-slot.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label for="slot_number">Slot Number / Code <span class="required">*</span></label>
                    <input type="text" name="slot_number" id="slot_number" class="form-control" 
                           placeholder="e.g. CR-09, TW-06, SV-05" required maxlength="15" 
                           style="text-transform: uppercase;">
                    <div class="input-hint">Must be unique across all bays (e.g. TW-06 for two wheelers).</div>
                </div>

                <div class="form-group">
                    <label for="vehicle_type">Vehicle Category <span class="required">*</span></label>
                    <select name="vehicle_type" id="vehicle_type" class="form-control" required>
                        <option value="Two Wheeler">Two Wheeler</option>
                        <option value="Car" selected>Car</option>
                        <option value="SUV">SUV</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Initial Status <span class="required">*</span></label>
                    <select name="status" id="status" class="form-control" required>
                        <option value="Available" selected>Available</option>
                        <option value="Reserved">Reserved</option>
                    </select>
                    <div class="input-hint">New slots can be set as Available or Reserved.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addSlotModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Slot</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Edit Parking Slot -->
<div class="modal-overlay" id="editSlotModal">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fas fa-edit" style="color: var(--primary-blue); margin-right: 6px;"></i> Edit Parking Slot</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('editSlotModal')">&times;</button>
        </div>
        <form action="../actions/update-slot.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
            <input type="hidden" name="id" id="edit_slot_id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_slot_number">Slot Number / Code <span class="required">*</span></label>
                    <input type="text" name="slot_number" id="edit_slot_number" class="form-control" required maxlength="15" style="text-transform: uppercase;">
                </div>

                <div class="form-group">
                    <label for="edit_vehicle_type">Vehicle Category <span class="required">*</span></label>
                    <select name="vehicle_type" id="edit_vehicle_type" class="form-control" required>
                        <option value="Two Wheeler">Two Wheeler</option>
                        <option value="Car">Car</option>
                        <option value="SUV">SUV</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_status">Current Status <span class="required">*</span></label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="Available">Available</option>
                        <option value="Occupied">Occupied</option>
                        <option value="Reserved">Reserved</option>
                    </select>
                    <div class="input-hint" id="edit_status_hint">Changing status of occupied bays requires clearing active vehicle first.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editSlotModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Update Slot</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(slot) {
    document.getElementById('edit_slot_id').value = slot.id;
    document.getElementById('edit_slot_number').value = slot.slot_number;
    document.getElementById('edit_vehicle_type').value = slot.vehicle_type;
    document.getElementById('edit_status').value = slot.status;
    openModal('editSlotModal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
