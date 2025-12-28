<?php
require_once "conn.php";

// --- 1. AJAX HANDLER (Must be at the very top) ---
if (isset($_GET['action']) && $_GET['action'] == 'get_units') {
    $pid = intval($_GET['property_id']);
    $stmt = $conn->prepare("SELECT unit_id, unit_number FROM units WHERE property_id = ? AND status = 'active'");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<option value="">-- Select Unit --</option>';
        while ($row = $result->fetch_assoc()) {
            echo "<option value='{$row['unit_id']}'>Unit {$row['unit_number']}</option>";
        }
    } else {
        echo '<option value="">No vacant units found</option>';
    }
    exit; 
}

// --- 2. LOGIC HANDLERS (Registration, Assignment) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action == "register_tenant") {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO tenant (firstname, lastname, middlename, username, password, email, contact_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $_POST['firstname'], $_POST['lastname'], $_POST['middlename'], $_POST['username'], $password, $_POST['email'], $_POST['contact_no']);
        if ($stmt->execute()) { $success_msg = "Tenant registered successfully!"; }
    }

    if ($action == "assign_tenant") {
        $unit_id = intval($_POST['unit_id']);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO assigned_units (unit_id, tenant_id, start_date, status) VALUES (?, ?, ?, 'active')");
            $stmt->bind_param("iis", $unit_id, $_POST['tenant_id'], $_POST['start_date']);
            $stmt->execute();
            $rent_id = $conn->insert_id;

            $stmt = $conn->prepare("INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status) VALUES (?, 'downpayment', ?, NOW(), ?, 'success')");
            $stmt->bind_param("ids", $rent_id, $_POST['downpayment'], $_POST['method']);
            $stmt->execute();

            $conn->query("UPDATE units SET status = 'inactive' WHERE unit_id = $unit_id");
            $conn->commit();
            $success_msg = "Tenant assigned successfully!";
        } catch (Exception $e) { $conn->rollback(); $error_msg = "Error: " . $e->getMessage(); }
    }
}

// --- 3. DATA FETCHING ---
$tenants = $conn->query("SELECT tenant_id, firstname, lastname FROM tenant ORDER BY lastname ASC");
$tenant_options = "";
while ($t = $tenants->fetch_assoc()) {
    $tenant_options .= "<option value='{$t['tenant_id']}'>{$t['lastname']}, {$t['firstname']}</option>";
}
// Using '*' ensures we get the 'address' or 'location' field
$properties = $conn->query("SELECT * FROM properties ORDER BY property_name ASC");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">Occupancy & Rent Tracker</h4>
            <p class="text-muted small">Manage property units and tenant assignments.</p>
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
        // Check if field is named 'address' or 'location' in your DB
        $display_address = !empty($p['address']) ? $p['address'] : (!empty($p['location']) ? $p['location'] : 'N/A');
    ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 border-bottom-0 d-flex justify-content-between align-items-center" 
                 data-bs-toggle="collapse" data-bs-target="#propertyCollapse<?= $pid ?>" style="cursor: pointer;">
                <div>
                    <h6 class="mb-0 fw-bold text-primary"><?= htmlspecialchars($p['property_name']) ?></h6>
                    <small class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i>Address: <?= htmlspecialchars($display_address) ?></small>
                </div>
                <i class="bi bi-chevron-down text-muted"></i>
            </div>

            <div id="propertyCollapse<?= $pid ?>" class="collapse show">
                <div class="card-body p-0 border-top">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Unit #</th>
                                    <th>Rent</th>
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
                                    <td class="ps-4 fw-bold"><?= $u['unit_number'] ?></td>
                                    <td>₱<?= number_format($u['monthly_rent'], 2) ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?= $is_occupied ? 'bg-danger-soft text-danger' : 'bg-success-soft text-success' ?>">
                                            <?= $is_occupied ? 'Occupied' : 'Vacant' ?>
                                        </span>
                                    </td>
                                    <td class="small"><?= !empty($u['firstname']) ? $u['firstname'].' '.$u['lastname'] : 'Available' ?></td>
                                    <td class="text-end pe-4">
                                        <?php if (!$is_occupied): ?>
                                            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#assignModal">Assign</button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" disabled>Occupied</button>
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

<div class="modal fade" id="regModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="fw-bold mb-0">New Tenant Registration</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="register_tenant">
                <div class="row g-3">
                    <div class="col-md-4"><label class="small fw-bold">First Name</label><input type="text" name="firstname" class="form-control" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Last Name</label><input type="text" name="lastname" class="form-control" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Middle Name</label><input type="text" name="middlename" class="form-control"></div>
                    <div class="col-md-6"><label class="small fw-bold">Username</label><input type="text" name="username" class="form-control" required></div>
                    <div class="col-md-6"><label class="small fw-bold">Password</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-md-8"><label class="small fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-4"><label class="small fw-bold">Contact No</label><input type="text" name="contact_no" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="submit" class="btn btn-success px-5 rounded-pill">Register Tenant</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 shadow-sm">
                <h5 class="fw-bold mb-0">Assign Tenant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="assign_tenant">
                <div class="mb-3">
                    <label class="small fw-bold">1. Select Tenant</label>
                    <select name="tenant_id" class="form-select border-0 bg-light" required>
                        <option value="">-- Choose Tenant --</option>
                        <?= $tenant_options ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">2. Choose Property</label>
                    <select class="form-select border-0 bg-light propertySelect" required>
                        <option value="">-- Select Property --</option>
                        <?php 
                        $p_res = $conn->query("SELECT property_id, property_name FROM properties ORDER BY property_name ASC");
                        while($pr = $p_res->fetch_assoc()){ echo "<option value='{$pr['property_id']}'>{$pr['property_name']}</option>"; }
                        ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="small fw-bold">3. Select Available Unit</label>
                    <select name="unit_id" class="form-select border-0 bg-light unitSelector" required>
                        <option value="">-- Select property first --</option>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="small fw-bold">Downpayment</label>
                        <input type="number" name="downpayment" class="form-control border-0 bg-light" placeholder="0.00" required>
                    </div>
                    <div class="col-4">
                        <label class="small fw-bold">Method</label>
                        <select name="method" class="form-select border-0 bg-light">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="small fw-bold">Move-in Date</label>
                    <input type="date" name="start_date" class="form-control border-0 bg-light" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow">Confirm Assignment</button>
            </div>
        </form>
    </div>
</div>

<style>
    .bg-success-soft { background-color: #e6f7ef; color: #198754; }
    .bg-danger-soft { background-color: #fceaea; color: #dc3545; }
    
    /* These fixes prevent the issues seen in your screenshots */
    .modal-backdrop { z-index: 1040 !important; }
    .modal { z-index: 1050 !important; }
    .form-control, .form-select { 
        border-radius: 8px; 
        padding: 0.6rem 1rem;
        border: 1px solid #eee;
    }
    .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
        border-color: #0d6efd;
    }
</style>

<script>
document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('propertySelect')) {
        const propertyId = e.target.value;
        const modal = e.target.closest('.modal');
        const unitSelect = modal.querySelector('.unitSelector');

        if (propertyId) {
            unitSelect.innerHTML = '<option>Loading...</option>';
            fetch(`${window.location.pathname}?action=get_units&property_id=${propertyId}`)
                .then(r => r.text())
                .then(data => { unitSelect.innerHTML = data; });
        }
    }
});
</script>