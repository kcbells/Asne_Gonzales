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

    if (!in_array($method, ['cash', 'card'])) { $method = 'cash'; }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO assigned_units (unit_id, tenant_id, start_date, status) VALUES (?, ?, ?, 'active')");
        $stmt->bind_param("iis", $unit_id, $tenant_id, $start_date);
        $stmt->execute();
        $rent_id = $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status) VALUES (?, 'downpayment', ?, NOW(), ?, 'success')");
        $stmt->bind_param("ids", $rent_id, $downpayment, $method);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE units SET status = 'inactive' WHERE unit_id = ?");
        $stmt->bind_param("i", $unit_id);
        $stmt->execute();

        $conn->commit();
        $success_msg = "Tenant assigned and downpayment recorded!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_msg = "Transaction failed: " . $e->getMessage();
    }
}

// --- 3. HANDLE EDIT UNIT LOGIC ---
if (isset($_POST['action']) && $_POST['action'] == "edit_unit") {
    $unit_id = intval($_POST['unit_id']);
    $status = $_POST['status'];
    $rent = floatval($_POST['monthly_rent']);

    $stmt = $conn->prepare("UPDATE units SET status = ?, monthly_rent = ? WHERE unit_id = ?");
    $stmt->bind_param("sdi", $status, $rent, $unit_id);

    if ($stmt->execute()) {
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
        <div>
            <h4 class="fw-bold text-dark mb-1">Occupancy & Rent Tracker</h4>
            <p class="text-muted small mb-0">Manage property units and tenant assignments.</p>
        </div>
        <button class="btn btn-success shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#regModal">
            <i class="bi bi-person-plus-fill me-1"></i> Register New Tenant
        </button>
    </div>

    <?php if (isset($success_msg)): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show"><?= $success_msg ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php while ($p = $properties->fetch_assoc()): 
        $pid = $p['property_id'];
        $count_res = $conn->query("SELECT COUNT(*) as total FROM units WHERE property_id = $pid");
        $count_data = $count_res->fetch_assoc();
    ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center cursor-pointer" 
                 data-bs-toggle="collapse" 
                 data-bs-target="#propertyCollapse<?= $pid ?>" 
                 style="cursor: pointer;">
                <div class="d-flex align-items-center ps-2">
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($p['property_name']) ?></h6>
                        <small class="text-muted"><?= $count_data['total'] ?> Total Units</small>
                    </div>
                </div>
                <i class="bi bi-chevron-down text-muted transition-icon"></i>
            </div>

            <div id="propertyCollapse<?= $pid ?>" class="collapse">
                <div class="card-body p-0 border-top">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Unit No.</th>
                                    <th>Monthly Rent</th>
                                    <th>Status</th>
                                    <th>Current Tenant</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
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
                                        <span class="badge rounded-pill <?= $is_occupied ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?>">
                                            <?= $is_occupied ? 'Occupied' : 'Vacant' ?>
                                        </span>
                                    </td>
                                    <td><?= !empty($u['firstname']) ? $u['firstname'] . ' ' . $u['lastname'] : '<span class="text-muted small">Available</span>' ?></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-light btn-sm rounded-circle me-1" data-bs-toggle="modal" data-bs-target="#editUnitModal<?= $u['unit_id'] ?>">
                                            <i class="bi bi-gear-fill text-secondary"></i>
                                        </button>
                                        <?php if (!$is_occupied): ?>
                                            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#assignModal<?= $u['unit_id'] ?>">Assign</button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-danger btn-sm rounded-pill px-3" disabled>Occupied</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<style>
    .bg-success-soft { background-color: #e6f7ef; color: #198754; }
    .bg-danger-soft { background-color: #fceaea; color: #dc3545; }
    .bg-primary-soft { background-color: #e7f0ff; color: #0d6efd; }
    .transition-icon { transition: transform 0.3s ease; }
    .collapsed .transition-icon { transform: rotate(-90deg); }
    .card-header:hover { background-color: #f8f9fa !important; }
    .table thead th { font-size: 0.7rem; border-top: 0; }
</style>