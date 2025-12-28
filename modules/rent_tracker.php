<?php
require_once "conn.php";

// --- 1. HANDLE REGISTRATION LOGIC ---
if (isset($_POST['action']) && $_POST['action'] == "register_tenant") {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $middlename = $_POST['middlename'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $email = $_POST['email'];
    $contact_no = $_POST['contact_no'];

    $stmt = $conn->prepare("INSERT INTO tenant (firstname, lastname, middlename, username, password, email, contact_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $firstname, $lastname, $middlename, $username, $password, $email, $contact_no);

    if ($stmt->execute()) {
        $success_msg = "Tenant registered successfully!";
    }
}

// --- 2. HANDLE ASSIGNMENT LOGIC ---
if (isset($_POST['action']) && $_POST['action'] === "assign_tenant") {

    $unit_id = intval($_POST['unit_id']);
    $tenant_id = intval($_POST['tenant_id']);
    $start_date = $_POST['start_date'];
    $downpayment = floatval($_POST['downpayment']);
    $method = $_POST['method'];

    // Safety check for payment method
    if (!in_array($method, ['cash', 'card'])) {
        $method = 'cash';
    }

    $conn->begin_transaction();

    try {
        // 1️⃣ Create rent / assignment record
        $stmt = $conn->prepare("
            INSERT INTO assigned_units (unit_id, tenant_id, start_date, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->bind_param("iis", $unit_id, $tenant_id, $start_date);
        $stmt->execute();

        // Get rent_id (FK for payments)
        $rent_id = $conn->insert_id;

        // 2️⃣ Record downpayment
        $stmt = $conn->prepare("
            INSERT INTO payments 
            (rent_id, type, amount, datetime_paid, method, status)
            VALUES (?, 'downpayment', ?, NOW(), ?, 'success')
        ");
        $stmt->bind_param("ids", $rent_id, $downpayment, $method);
        $stmt->execute();

        // 3️⃣ Mark unit as occupied
        $stmt = $conn->prepare("
            UPDATE units SET status = 'inactive'
            WHERE unit_id = ?
        ");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();

        $conn->commit();
        $success_msg = "Tenant assigned and downpayment recorded successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Transaction failed: " . $e->getMessage();
    }
}


// --- 3. NEW: HANDLE EDIT UNIT LOGIC (Status & Rent) ---
if (isset($_POST['action']) && $_POST['action'] == "edit_unit") {
    $unit_id = intval($_POST['unit_id']);
    $status = $_POST['status']; // 'active' (Vacant) or 'inactive' (Occupied)
    $rent = floatval($_POST['monthly_rent']);

    $stmt = $conn->prepare("UPDATE units SET status = ?, monthly_rent = ? WHERE unit_id = ?");
    $stmt->bind_param("sdi", $status, $rent, $unit_id);

    if ($stmt->execute()) {
        // If manually set to 'active' (Vacant), we should deactivate any 'active' assignments for this unit
        if ($status == 'active') {
            $conn->query("UPDATE assigned_units SET status = 'completed' WHERE unit_id = $unit_id AND status = 'active'");
        }
        $success_msg = "Unit updated successfully!";
    }
}

// --- 4. FETCH DATA ---
$tenants = $conn->query("SELECT tenant_id, firstname, lastname FROM tenant ORDER BY lastname ASC");
$tenant_options = "";
while ($t = $tenants->fetch_assoc()) {
    $tenant_options .= "<option value='{$t['tenant_id']}'>{$t['lastname']}, {$t['firstname']}</option>";
}
$properties = $conn->query("SELECT * FROM properties ORDER BY property_name ASC");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-secondary">Occupancy & Rent Tracker</h4>
        <button class="btn btn-success shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#regModal">
            <i class="bi bi-person-plus-fill me-1"></i> Register New Tenant
        </button>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show"><?= $success_msg ?><button
                type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php while ($p = $properties->fetch_assoc()): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between">
                <h5 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($p['property_name']) ?></h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Unit</th>
                                <th>Monthly Rent</th>
                                <th>Status</th>
                                <th>Tenant</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $pid = $p['property_id'];
                            $units = $conn->query("SELECT u.*, t.firstname, t.lastname FROM units u 
                                              LEFT JOIN assigned_units au ON u.unit_id = au.unit_id AND au.status = 'active'
                                              LEFT JOIN tenant t ON au.tenant_id = t.tenant_id
                                              WHERE u.property_id = $pid");
                            while ($u = $units->fetch_assoc()):
                                $is_occupied = ($u['status'] == 'inactive');
                                ?>
                                <tr>
                                    <td class="ps-4 fw-bold">Unit <?= $u['unit_number'] ?></td>
                                    <td>₱<?= number_format($u['monthly_rent'], 2) ?></td>
                                    <td>
                                        <span
                                            class="badge rounded-pill <?= $is_occupied ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?>">
                                            <?= $is_occupied ? 'Occupied' : 'Vacant' ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($u['firstname']) ? $u['firstname'] . ' ' . $u['lastname'] : '<span class="text-muted small">None</span>' ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 me-1"
                                            data-bs-toggle="modal" data-bs-target="#editUnitModal<?= $u['unit_id'] ?>"><i
                                                class="bi bi-gear"></i></button>

                                        <?php if (!$is_occupied): ?>
                                            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal"
                                                data-bs-target="#assignModal<?= $u['unit_id'] ?>">Assign</button>
                                        <?php else: ?>
                                            <button class="btn btn-light btn-sm rounded-pill px-3 border" disabled>In Use</button>
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
                                                        <small class="text-muted" style="font-size: 0.7rem;">Setting to Vacant
                                                            will remove current tenant assignment.</small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Monthly Rent</label>
                                                        <input type="number" step="0.01" name="monthly_rent"
                                                            class="form-control bg-light border-0"
                                                            value="<?= $u['monthly_rent'] ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0">
                                                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Save
                                                        Changes</button>
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
                                                    <h5 class="fw-bold">Assign Tenant</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body py-0">
                                                    <input type="hidden" name="action" value="assign_tenant">

                                                    <!-- Tenant -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Select Tenant</label>
                                                        <select name="tenant_id" class="form-select border-0 bg-white shadow-sm"
                                                            required>
                                                            <option value="">-- Choose Tenant --</option>
                                                            <?= $tenant_options ?>
                                                        </select>
                                                    </div>

                                                    <!-- Property -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Select Property</label>
                                                        <select name="property_id" id="propertySelect<?= $u['unit_id'] ?>"
                                                            class="form-select border-0 bg-white shadow-sm propertySelect"
                                                            required>
                                                            <option value="">-- Choose Property --</option>
                                                            <?php
                                                            $props = $conn->query("SELECT property_id, property_name FROM properties ORDER BY property_name ASC");
                                                            while ($pr = $props->fetch_assoc()):
                                                                ?>
                                                                <option value="<?= $pr['property_id'] ?>">
                                                                    <?= htmlspecialchars($pr['property_name']) ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                    </div>

                                                    <!-- Units (dynamically based on selected property) -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Select Unit</label>
                                                        <select name="unit_id" id="unitSelect<?= $u['unit_id'] ?>"
                                                            class="form-select border-0 bg-white shadow-sm" required>
                                                            <option value="">-- Select property first --</option>
                                                        </select>
                                                    </div>

                                                    <!-- Downpayment -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Downpayment</label>
                                                        <div class="d-flex gap-2">
                                                            <input type="number" name="downpayment"
                                                                class="form-control border-0 bg-white shadow-sm"
                                                                placeholder="Enter downpayment" required>
                                                            <select name="method"
                                                                class="form-select border-0 bg-white shadow-sm" required>
                                                                <option value="cash">Cash</option>
                                                                <option value="card">Card</option>
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <!-- Move-in Date -->
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-bold">Move-in Date</label>
                                                        <input type="date" name="start_date"
                                                            class="form-control border-0 bg-white shadow-sm"
                                                            value="<?= date('Y-m-d') ?>" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-0">
                                                    <button type="button" class="btn btn-light"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary px-4 shadow-sm">Confirm
                                                        Move-in</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<div class="modal fade" id="regModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="fw-bold mb-0">New Tenant Registration</h5><button type="button"
                    class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="register_tenant">
                <div class="row g-3">
                    <div class="col-md-4"><label class="small fw-bold">First Name</label><input type="text"
                            name="firstname" class="form-control bg-light border-1.5" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Last Name</label><input type="text"
                            name="lastname" class="form-control bg-light border-1.5" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Middle Name</label><input type="text"
                            name="middlename" class="form-control bg-light border-1.5"></div>
                    <div class="col-md-6"><label class="small fw-bold">Username</label><input type="text"
                            name="username" class="form-control bg-light border-1.5" required></div>
                    <div class="col-md-6"><label class="small fw-bold">Password</label><input type="password"
                            name="password" class="form-control bg-light border-1.5" required></div>
                    <div class="col-md-8"><label class="small fw-bold">Email</label><input type="email" name="email"
                            class="form-control bg-light border-1.5" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Contact No</label><input type="text"
                            name="contact_no" class="form-control bg-light border-1.5"></div>
                </div>
            </div>
            <div class="modal-footer border-0 justify-content-center"><button type="submit"
                    class="btn btn-success px-4 rounded-pill">Register Tenant</button></div>
        </form>
    </div>
</div>

<style>
    .bg-success-soft {
        background-color: #e6f7ef;
        color: #198754;
    }

    .bg-danger-soft {
        background-color: #fceaea;
        color: #dc3545;
    }

    .modal-backdrop {
        opacity: 0.5 !important;
    }

    .table thead th {
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
</style>