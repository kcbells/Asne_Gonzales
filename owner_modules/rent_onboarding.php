<?php
require_once "conn.php";

/**
 * AUTHENTICATION & FILTERING
 */
$owner_id = $_SESSION['user_id'] ?? 0;

// --- 1. HANDLE PROPERTY & UNIT ACTIONS ---

// Logic for Editing Unit Details and Status
if (isset($_POST['action']) && $_POST['action'] === "edit_unit") {
    $unit_id = intval($_POST['unit_id']);
    $new_status = $_POST['status']; // 'active' (Vacant) or 'inactive' (Occupied)
    $monthly_rent = floatval($_POST['monthly_rent']);

    // Update the unit details in the database
    $stmt = $conn->prepare("UPDATE units SET status = ?, monthly_rent = ? WHERE unit_id = ?");
    $stmt->bind_param("sdi", $new_status, $monthly_rent, $unit_id);
    
    if ($stmt->execute()) {
        // If the owner manually sets a unit to 'active' (Vacant), 
        // we should clear any current tenant assignments to update the table status.
        if ($new_status === 'active') {
            $clear_stmt = $conn->prepare("DELETE FROM assigned_units WHERE unit_id = ?");
            $clear_stmt->bind_param("i", $unit_id);
            $clear_stmt->execute();
        }
        $success_msg = "Unit updated successfully!";
    }
}

// --- 2. HANDLE ASSIGNMENT LOGIC ---
if (isset($_POST['action']) && $_POST['action'] === "assign_tenant") {
    $unit_id = intval($_POST['unit_id']);
    $tenant_id = intval($_POST['tenant_id']);
    $start_date = $_POST['start_date'];
    $downpayment = floatval($_POST['downpayment']);

    // Security Check: Ensure the unit belongs to one of Ivan's properties
    $verify = $conn->prepare("SELECT u.unit_id FROM units u JOIN properties p ON u.property_id = p.property_id WHERE u.unit_id = ? AND p.user_id = ?");
    $verify->bind_param("ii", $unit_id, $owner_id);
    $verify->execute();
    
    if ($verify->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO assigned_units (unit_id, tenant_id, start_date, status, downpayment) VALUES (?, ?, ?, 'pending downpayment', ?)");
        $stmt->bind_param("iisd", $unit_id, $tenant_id, $start_date, $downpayment);
        if ($stmt->execute()) { 
            $success_msg = "Tenant successfully assigned to unit!"; 
        }
    }
}

// --- 3. FETCH FILTERED DATA ---

// Fetch only Ivan's properties
$stmt = $conn->prepare("SELECT * FROM properties WHERE user_id = ? ORDER BY property_name ASC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$properties = $stmt->get_result();

// Fetch ALL tenants for the selection dropdown
$tenants_query = $conn->query("SELECT tenant_id, firstname, lastname FROM tenant ORDER BY lastname ASC");
$tenant_options = "";
while ($t = $tenants_query->fetch_assoc()) {
    $tenant_options .= "<option value='{$t['tenant_id']}'>{$t['lastname']}, {$t['firstname']}</option>";
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-secondary">Occupancy Assignment and Tenant Registration</h4>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <?= $success_msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Properties & Units</h5>
            <small class="text-white-50">Occupancy overview</small>
        </div>
        <div class="card-body">
            <div class="accordion" id="propAccordion">
                <?php while ($p = $properties->fetch_assoc()): ?>
                    <div class="accordion-item mb-2 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                                data-bs-target="#collapse<?= $p['property_id'] ?>">
                                <div class="d-flex justify-content-between w-100 pe-3">
                                    <span><strong><?= htmlspecialchars($p['property_name']) ?></strong></span>
                                    <small class="text-muted"><?= htmlspecialchars($p['address']) ?></small>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse<?= $p['property_id'] ?>" class="accordion-collapse collapse" data-bs-parent="#propAccordion">
                            <div class="accordion-body">
                                <h6 class="border-bottom pb-2">Units in this Property</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover table-bordered mb-0">
                                        <thead class="table-light small text-uppercase">
                                            <tr>
                                                <th>Unit</th>
                                                <th>Monthly Rent</th>
                                                <th>Status</th>
                                                <th>Tenant</th>
                                                <th>Downpayment</th>
                                                <th class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $pid = $p['property_id'];
                                            $units = $conn->query("SELECT u.*, au.status AS assignment_status, au.downpayment AS assigned_downpayment, t.firstname, t.lastname FROM units u LEFT JOIN (SELECT a1.* FROM assigned_units a1 INNER JOIN (SELECT unit_id, MAX(start_date) AS max_date FROM assigned_units GROUP BY unit_id) a2 ON a1.unit_id = a2.unit_id AND a1.start_date = a2.max_date) au ON u.unit_id = au.unit_id LEFT JOIN tenant t ON au.tenant_id = t.tenant_id WHERE u.property_id = $pid ORDER BY u.unit_number ASC");

                                            if ($units && $units->num_rows > 0):
                                                while ($u = $units->fetch_assoc()):
                                                    // Determine display status based on both assignment and manual unit status
                                                    if (!empty($u['assignment_status'])) {
                                                        $status_text = ucfirst($u['assignment_status']);
                                                    } else {
                                                        $status_text = ($u['status'] == 'inactive') ? 'Occupied (Manual)' : 'Available';
                                                    }

                                                    switch ($status_text) {
                                                        case 'Occupied':
                                                        case 'Occupied (Manual)':
                                                            $status_class = 'bg-secondary';
                                                            break;
                                                        case 'Pending downpayment':
                                                            $status_class = 'bg-warning text-dark';
                                                            break;
                                                        default:
                                                            $status_class = 'bg-success';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?= $u['unit_number'] ?></td>
                                                        <td>₱<?= number_format($u['monthly_rent'], 2) ?></td>
                                                        <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                                        <td><?= !empty($u['firstname']) ? $u['firstname'] . ' ' . $u['lastname'] : '<span class="text-muted small">None</span>' ?></td>
                                                        <td>₱<?= !empty($u['assigned_downpayment']) ? number_format($u['assigned_downpayment'], 2) : '0.00' ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-outline-secondary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editUnitModal<?= $u['unit_id'] ?>"><i class="bi bi-gear"></i></button>
                                                            <?php if ($u['assignment_status'] !== 'occupied'): ?>
                                                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal<?= $u['unit_id'] ?>">Assign</button>
                                                            <?php else: ?>
                                                                <button class="btn btn-light btn-sm border" disabled>In Use</button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>

                                                    <div class="modal fade" id="editUnitModal<?= $u['unit_id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered modal-sm">
                                                            <div class="modal-content border-0 shadow">
                                                                <form method="POST">
                                                                    <div class="modal-header border-0">
                                                                        <h5 class="fw-bold">Edit Unit <?= $u['unit_number'] ?></h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="action" value="edit_unit">
                                                                        <input type="hidden" name="unit_id" value="<?= $u['unit_id'] ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold">Availability Status</label>
                                                                            <select name="status" class="form-select bg-light border-0">
                                                                                <option value="active" <?= $u['status'] == 'active' ? 'selected' : '' ?>>Vacant (Available)</option>
                                                                                <option value="inactive" <?= $u['status'] == 'inactive' ? 'selected' : '' ?>>Occupied (Manual)</option>
                                                                            </select>
                                                                            <small class="text-muted" style="font-size: 0.7rem;">Setting to Vacant will remove current tenant assignment.</small>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold">Monthly Rent</label>
                                                                            <input type="number" step="0.01" name="monthly_rent" class="form-control bg-light border-0" value="<?= $u['monthly_rent'] ?>" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer border-0">
                                                                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Save Changes</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal fade" id="assignModal<?= $u['unit_id'] ?>" tabindex="-1" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content border-0 shadow">
                                                                <form method="POST">
                                                                    <div class="modal-header border-0">
                                                                        <h5 class="fw-bold">Information</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                    </div>
                                                                    <div class="modal-body py-0">
                                                                        <div class="card bg-light border-0 mb-3">
                                                                            <div class="card-body p-3">
                                                                                <small class="text-muted d-block">Assigning to:</small>
                                                                                <span class="fw-bold text-primary">Unit <?= $u['unit_number'] ?> - <?= $p['property_name'] ?></span>
                                                                            </div>
                                                                        </div>
                                                                        <input type="hidden" name="action" value="assign_tenant">
                                                                        <input type="hidden" name="unit_id" value="<?= $u['unit_id'] ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold">Select Tenant</label>
                                                                            <select name="tenant_id" class="form-select border-0 bg-white shadow-sm" required>
                                                                                <option value="">-- Choose Tenant --</option>
                                                                                <?= $tenant_options ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold">Downpayment</label>
                                                                            <div class="d-flex gap-2">
                                                                                <input type="number" name="downpayment" class="form-control border-0 bg-white shadow-sm" placeholder="Enter downpayment" required>
                                                                                <select name="method" class="form-select border-0 bg-white shadow-sm" required>
                                                                                    <option value="cash">Cash</option>
                                                                                    <option value="card">Card</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label class="form-label small fw-bold">Move-in Date</label>
                                                                            <input type="date" name="start_date" class="form-control border-0 bg-white shadow-sm" value="<?= date('Y-m-d') ?>" required>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer border-0">
                                                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-primary px-4 shadow-sm">Confirm</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                <?php endwhile; 
                                            else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">No units added yet.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-success-soft { background-color: #e6f7ef; color: #198754; }
    .bg-danger-soft { background-color: #fceaea; color: #dc3545; }
    .modal-backdrop { opacity: 0.5 !important; }
    .table thead th { font-size: 0.75rem; letter-spacing: 0.05em; }
</style>
