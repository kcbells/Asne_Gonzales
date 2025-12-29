<?php
require_once "conn.php";

// --- 1. Handle preselection ---
$selected_tenant_id = isset($_POST['tenant_id']) ? intval($_POST['tenant_id']) : 0;
$selected_unit_id = isset($_POST['unit_id']) ? intval($_POST['unit_id']) : 0;

// --- 2. Fetch tenants ---
$tenants = $conn->query("SELECT tenant_id, firstname, lastname FROM tenant ORDER BY lastname ASC");

// --- 3. Fetch units ---
if ($selected_tenant_id) {
    // Fetch units assigned to tenant (active or pending downpayment)
    $units = $conn->query("
        SELECT u.unit_id, u.unit_number, au.tenant_id
        FROM units u
        LEFT JOIN assigned_units au ON u.unit_id = au.unit_id AND au.status IN ('active','pending downpayment')
        WHERE u.unit_id IN (
            SELECT unit_id FROM assigned_units WHERE tenant_id = $selected_tenant_id AND status IN ('active','pending downpayment')
        )
        ORDER BY u.unit_number ASC
    ");
} else {
    $units = []; // empty until tenant is selected
}

// --- 4. Fetch unit-tenant mapping for preselection ---
$unit_mapping = [];
foreach ($units as $u) {
    if ($u['tenant_id'])
        $unit_mapping[$u['unit_id']] = $u['tenant_id'];
}

// --- 5. Handle form submission ---
if (isset($_POST['action']) && $_POST['action'] === "record_payment") {
    $tenant_id = intval($_POST['tenant_id']);
    $unit_id = intval($_POST['unit_id']);
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    $datetime_paid = $_POST['datetime_paid'];

    // --- Fetch first assigned unit for tenant if unit_id not explicitly selected ---
    if (!$unit_id) {
        $stmt = $conn->prepare("
            SELECT assigned_units_id, unit_id 
            FROM assigned_units 
            WHERE tenant_id = ? AND status IN ('active','pending downpayment')
            ORDER BY start_date ASC LIMIT 1
        ");
        $stmt->bind_param("i", $tenant_id);
    } else {
        $stmt = $conn->prepare("
            SELECT assigned_units_id, unit_id 
            FROM assigned_units 
            WHERE tenant_id = ? AND unit_id = ? AND status IN ('active','pending downpayment')
            ORDER BY start_date ASC LIMIT 1
        ");
        $stmt->bind_param("ii", $tenant_id, $unit_id);
    }

    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $rent_id = $res['assigned_units_id'];
        $unit_id = $res['unit_id']; // make sure unit_id is set

        // --- Handle downpayment logic ---
        if ($type === 'downpayment') {
            // Check existing downpayment for this unit
            $unit_check = $conn->prepare("SELECT downpayment, monthly_rent FROM units WHERE unit_id = ?");
            $unit_check->bind_param("i", $unit_id);
            $unit_check->execute();
            $unit_res = $unit_check->get_result()->fetch_assoc();
            $existing_dp = floatval($unit_res['downpayment']);
            $monthly_rent = floatval($unit_res['monthly_rent']);

            if ($existing_dp + $amount > $monthly_rent) {
                $error_msg = "Downpayment exceeds required monthly rent. Please pay the exact balance or proceed to monthly payment.";
            } else {
                if ($existing_dp == 0) {
                    // First downpayment, update units table
                    $update = $conn->prepare("UPDATE units SET downpayment = ? WHERE unit_id = ?");
                    $update->bind_param("di", $amount, $unit_id);
                    $update->execute();
                }
                // Always insert into payments table
                $insert = $conn->prepare("
                    INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status)
                    VALUES (?, ?, ?, ?, ?, 'success')
                ");
                $insert->bind_param("isdss", $rent_id, $type, $amount, $datetime_paid, $method);
                if ($insert->execute()) {
                    $success_msg = "Payment recorded successfully!";
                } else {
                    $error_msg = "Failed to record payment: " . $conn->error;
                }
            }
        } else {
            // Monthly payment or others
            $insert = $conn->prepare("
                INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status)
                VALUES (?, ?, ?, ?, ?, 'success')
            ");
            $insert->bind_param("isdss", $rent_id, $type, $amount, $datetime_paid, $method);
            if ($insert->execute()) {
                $success_msg = "Payment recorded successfully!";
            } else {
                $error_msg = "Failed to record payment: " . $conn->error;
            }
        }

    } else {
        $error_msg = "No active assignment found for this tenant and unit.";
    }
}

// --- FETCH PAYMENT HISTORY ---
$payments = $conn->query("
    SELECT 
        p.payment_id, 
        p.datetime_paid, 
        t.firstname, 
        t.lastname, 
        u.unit_number, 
        pr.property_name, 
        p.type, 
        p.amount, 
        p.method
    FROM payments p
    INNER JOIN assigned_units au ON p.rent_id = au.assigned_units_id
    INNER JOIN tenant t ON au.tenant_id = t.tenant_id
    INNER JOIN units u ON au.unit_id = u.unit_id
    INNER JOIN properties pr ON u.property_id = pr.property_id
    ORDER BY p.datetime_paid DESC
");

// Outstanding Balances
$outstanding_balances = $conn->query("
    SELECT t.firstname, t.lastname, u.unit_number, 
           (u.monthly_rent - SUM(CASE WHEN p.type='monthly' THEN p.amount ELSE 0 END)) AS balance
    FROM tenant t
    LEFT JOIN assigned_units au ON t.tenant_id = au.tenant_id AND au.status='active'
    LEFT JOIN units u ON au.unit_id = u.unit_id
    LEFT JOIN payments p ON au.assigned_units_id = p.rent_id
    GROUP BY t.tenant_id, u.unit_number
    HAVING balance > 0
");
?>

<div class="card shadow-sm">
    <div class="card-header" style="background-color: #f8f9fa;">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Payments</h5>
            <ul class="nav nav-pills nav-pills-sm">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pay-record">Record</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pay-history">History</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pay-balance">Balances</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pay-invoice">Invoices</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="pill" data-bs-target="#pay-reports">Reports</button>
                </li>
            </ul>
        </div>
    </div>

    <style>
        /* NAVBAR PILL STYLING */
        .nav-pills .nav-link {
            background-color: #e9ecef;
            /* light gray background */
            color: #212529;
            /* dark text */
            margin-right: 5px;
            border-radius: 50px;
            /* pill shape */
            transition: all 0.2s ease-in-out;
            /* smooth hover & active */
        }

        .nav-pills .nav-link:hover {
            background-color: #adb5bd;
            /* obvious hover color */
            color: #fff;
            /* white text on hover */
        }

        .nav-pills .nav-link.active {
            background-color: #6c757d;
            /* dark gray when clicked/active */
            color: #fff;
            /* white text */
        }
    </style>

    <div class="card-body">
        <div class="tab-content">

            <!-- RECORD PAYMENT -->
            <div class="tab-pane fade show active" id="pay-record">
                <h6 class="fw-bold mb-3">Record Payment</h6>

                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success"><?= $success_msg ?></div>
                <?php endif; ?>
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger"><?= $error_msg ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="record_payment">
                    <div class="row g-3">

                        <!-- TENANT -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Tenant</label>
                            <select name="tenant_id" class="form-select bg-light border-0">
                                <option value="">Select Tenant</option>
                                <?php while ($t = $tenants->fetch_assoc()): ?>
                                    <option value="<?= $t['tenant_id'] ?>" <?= $selected_tenant_id == $t['tenant_id'] ? 'selected' : '' ?>>
                                        <?= $t['lastname'] ?>, <?= $t['firstname'] ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- UNIT -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Unit</label>
                            <select name="unit_id" class="form-select bg-light border-0" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['unit_id'] ?>" <?=
                                          ($selected_unit_id == $u['unit_id'] ||
                                              (isset($unit_mapping[$u['unit_id']]) && $unit_mapping[$u['unit_id']] == $selected_tenant_id))
                                          ? 'selected' : '' ?>>
                                        <?= $u['unit_number'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- PAYMENT TYPE -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Payment Type</label>
                            <select name="type" class="form-select bg-light border-0" required>
                                <option value="downpayment">Downpayment</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </div>

                        <!-- AMOUNT -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Amount</label>
                            <input type="number" name="amount" class="form-control bg-light border-0" required>
                        </div>

                        <!-- METHOD -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Method</label>
                            <select name="method" class="form-select bg-light border-0" required>
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                            </select>
                        </div>

                        <!-- DATE -->
                        <div class="col-md-4">
                            <label class="small fw-bold">Date</label>
                            <input type="date" name="datetime_paid" class="form-control bg-light border-0"
                                value="<?= date('Y-m-d') ?>" required>
                        </div>

                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary px-4 rounded-pill">Save Payment</button>
                    </div>
                </form>
            </div>



            <!-- PAYMENT HISTORY -->
            <div class="tab-pane fade" id="pay-history">
                <h6 class="fw-bold mb-3">Payment History</h6>

                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered">
                        <thead class="table-light text-uppercase small">
                            <tr>
                                <th>Date</th>
                                <th>Tenant</th>
                                <th>Property</th>
                                <th>Unit/Room No.</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $payments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['datetime_paid'] ?></td>
                                    <td><?= $row['lastname'] ?>, <?= $row['firstname'] ?></td>
                                    <td><?= $row['property_name'] ?></td>
                                    <td><?= $row['unit_number'] ?></td>
                                    <td><?= ucfirst($row['type']) ?></td>
                                    <td>₱<?= number_format($row['amount'], 2) ?></td>
                                    <td><?= ucfirst($row['method']) ?></td>
                                </tr>
                            <?php endwhile; ?>

                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TENANT BALANCES FOR DUE PAYMENTS -->
            <div class="tab-pane fade" id="pay-balance">
                <h6 class="fw-bold mb-3">Tenant Balances</h6>

                <table class="table table-sm table-bordered">
                    <thead class="table-light text-uppercase small">
                        <tr>
                            <th>Tenant</th>
                            <th>Unit</th>
                            <th>Amount Due</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $current_month = date('Y-m');

                        $balances = $conn->query("
                            SELECT t.tenant_id, t.firstname, t.lastname, u.unit_id, u.unit_number, u.monthly_rent
                            FROM tenant t
                            INNER JOIN assigned_units au ON t.tenant_id = au.tenant_id AND au.status='active'
                            INNER JOIN units u ON au.unit_id = u.unit_id
                            LEFT JOIN payments p ON p.rent_id = au.assigned_units_id 
                                AND p.type='monthly' 
                                AND DATE_FORMAT(p.datetime_paid, '%Y-%m') = '$current_month'
                            WHERE p.payment_id IS NULL
                            ORDER BY t.lastname, t.firstname
                        ");

                        while ($row = $balances->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $row['lastname'] ?>, <?= $row['firstname'] ?></td>
                                <td>Unit <?= $row['unit_number'] ?></td>
                                <td>₱<?= number_format($row['monthly_rent'], 2) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#payModal<?= $row['tenant_id'] ?>">
                                        Pay
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- MODAL FOR PAYMENT IN DUE BALANCES -->
            <?php
            $balances->data_seek(0); // Reset result pointer to loop again for modals
            while ($row = $balances->fetch_assoc()):
                ?>
                <div class="modal fade" id="payModal<?= $row['tenant_id'] ?>" tabindex="-1"
                    aria-labelledby="payModalLabel<?= $row['tenant_id'] ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" class="modal-content border-0 shadow-sm p-3" style="background-color:#f8f9fa;">
                            <div class="modal-header border-0">
                                <h5 class="modal-title fw-bold" id="payModalLabel<?= $row['tenant_id'] ?>">Record Payment
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="record_payment">
                                <input type="hidden" name="tenant_id" value="<?= $row['tenant_id'] ?>">
                                <input type="hidden" name="unit_id" value="<?= $row['unit_id'] ?>">

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Tenant</label>
                                    <input type="text" class="form-control bg-white"
                                        value="<?= $row['firstname'] ?> <?= $row['lastname'] ?>" disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Unit</label>
                                    <input type="text" class="form-control bg-white" value="Unit <?= $row['unit_number'] ?>"
                                        disabled>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Amount</label>
                                    <input type="number" name="amount" class="form-control bg-white"
                                        value="<?= $row['monthly_rent'] ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Payment Type</label>
                                    <select name="type" class="form-select bg-white" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="downpayment">Downpayment</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Method</label>
                                    <select name="method" class="form-select bg-white" required>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-bold">Date</label>
                                    <input type="date" name="datetime_paid" class="form-control bg-white"
                                        value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary px-4">Record Payment</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>


            <!-- INVOICES -->
            <div class="tab-pane fade" id="pay-invoice">
                <h6 class="fw-bold mb-3">Invoices & Receipts</h6>

                <table class="table table-sm table-hover table-bordered">
                    <thead class="table-light text-uppercase small">
                        <tr>
                            <th>sample</th>
                            <th>sample</th>
                            <th>sample</th>
                            <th>sample</th>
                            <th>ikaw na bahala</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- loops for table rows -->
                    </tbody>
                </table>
            </div>

            <!-- REPORTS -->
            <div class="tab-pane fade" id="pay-reports">
                <h6 class="fw-bold mb-3">Reports</h6>

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold">Monthly Collection</h6>
                                <small class="text-muted">Total payments per month</small>
                                <button class="btn btn-sm btn-outline-primary mt-2">View</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold">Outstanding Balances</h6>
                                <small class="text-muted">Unpaid tenants</small>
                                <button class="btn btn-sm btn-outline-primary mt-2">View</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold">Property Summary</h6>
                                <small class="text-muted">Payments per property</small>
                                <button class="btn btn-sm btn-outline-primary mt-2">View</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>