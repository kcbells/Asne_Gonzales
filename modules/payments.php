<?php
require_once "conn.php";

// --- 1. UNIFIED PAYMENT PROCESSING ---
$msg = "";
if (isset($_POST['action']) && $_POST['action'] == "process_payment") {
    $rent_id = intval($_POST['rent_id']);
    $amount = floatval($_POST['amount']); // Final amount confirmed/edited in the modal
    $method = $_POST['method'];
    $type = $_POST['type']; 
    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : null;
    $date_paid = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        // Record payment in history
        $stmt = $conn->prepare("INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status) VALUES (?, ?, ?, ?, ?, 'success')");
        $stmt->bind_param("issss", $rent_id, $type, $amount, $date_paid, $method);
        $stmt->execute();

        if ($type == 'downpayment') {
            // Update assignment to occupied and sync the FINAL edited amount
            $stmt_upd = $conn->prepare("UPDATE assigned_units SET status = 'occupied', downpayment = ? WHERE assigned_units_id = ?");
            $stmt_upd->bind_param("di", $amount, $rent_id);
            $stmt_upd->execute();
            
            // Update physical unit to occupied
            $conn->query("UPDATE units SET status = 'occupied' WHERE unit_id = (SELECT unit_id FROM assigned_units WHERE assigned_units_id = $rent_id)");
            
            // Generate 12-Month Schedule
            $info = $conn->query("SELECT au.start_date, u.monthly_rent FROM assigned_units au JOIN units u ON au.unit_id = u.unit_id WHERE au.assigned_units_id = $rent_id")->fetch_assoc();
            $curr_date = new DateTime($info['start_date']);
            for ($i = 1; $i <= 12; $i++) {
                $due_date = $curr_date->add(new DateInterval('P1M'))->format('Y-m-d');
                $m_rent = $info['monthly_rent'];
                $conn->query("INSERT INTO payment_schedule (rent_id, due_date, amount_due, status) VALUES ($rent_id, '$due_date', $m_rent, 'unpaid')");
            }
        } else {
            $conn->query("UPDATE payment_schedule SET status = 'paid' WHERE schedule_id = $schedule_id");
        }

        $conn->commit();
        $msg = "Payment recorded successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

// --- 2. DATA FETCHING ---
$pending_sql = "
    (SELECT au.assigned_units_id as id, NULL as sched_id, 'downpayment' as pay_type, t.firstname, t.lastname, u.unit_number, au.downpayment as amount, au.start_date as due_date
    FROM assigned_units au 
    JOIN tenant t ON au.tenant_id = t.tenant_id 
    JOIN units u ON au.unit_id = u.unit_id 
    WHERE au.status = 'active' OR au.status = 'pending downpayment')
    UNION ALL
    (SELECT au.assigned_units_id as id, ps.schedule_id as sched_id, 'monthly' as pay_type, t.firstname, t.lastname, u.unit_number, ps.amount_due as amount, ps.due_date
    FROM payment_schedule ps 
    JOIN assigned_units au ON ps.rent_id = au.assigned_units_id 
    JOIN tenant t ON au.tenant_id = t.tenant_id 
    JOIN units u ON au.unit_id = u.unit_id
    WHERE ps.status = 'unpaid' AND ps.due_date <= CURDATE())
    ORDER BY due_date ASC";
$pending_list = $conn->query($pending_sql);

$history_sql = "
    SELECT p.*, t.firstname, t.lastname, u.unit_number, prop.property_name 
    FROM payments p
    JOIN assigned_units au ON p.rent_id = au.assigned_units_id
    JOIN tenant t ON au.tenant_id = t.tenant_id
    JOIN units u ON au.unit_id = u.unit_id
    JOIN properties prop ON u.property_id = prop.property_id
    ORDER BY p.datetime_paid DESC";
$history_list = $conn->query($history_sql);

$modals = [];
?>

<div class="container-fluid py-4 bg-light min-vh-100">
    <?php if($msg): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h4 class="fw-bold mb-4 text-primary">Financial Management</h4>

    <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm" id="payTabs" style="width: fit-content;">
        <li class="nav-item">
            <button class="nav-link active fw-bold px-4" data-bs-toggle="pill" data-bs-target="#tab-pending">Pending</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold px-4 text-secondary" data-bs-toggle="pill" data-bs-target="#tab-history">History</button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-pending">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-white border-bottom text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">Type</th>
                                <th>Tenant</th>
                                <th>Unit</th>
                                <th>Expected</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($p = $pending_list->fetch_assoc()): 
                                $modals[] = $p;
                                $unique_id = $p['pay_type'] . $p['id'] . ($p['sched_id'] ?? '0');
                            ?>
                            <tr class="bg-white">
                                <td class="ps-4">
                                    <span class="badge rounded-pill py-2 px-3 <?= $p['pay_type'] == 'downpayment' ? 'bg-info-subtle text-info border border-info-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' ?>">
                                        <?= strtoupper($p['pay_type']) ?>
                                    </span>
                                </td>
                                <td class="fw-bold text-dark"><?= $p['lastname'] ?>, <?= $p['firstname'] ?></td>
                                <td class="text-secondary">Unit <?= $p['unit_number'] ?></td>
                                <td class="text-primary fw-bold">₱<?= number_format($p['amount'], 2) ?></td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold" data-bs-toggle="modal" data-bs-target="#payModal<?= $unique_id ?>">Confirm Pay</button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-history">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-white border-bottom text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4 py-3">Date</th>
                                <th>Tenant</th>
                                <th>Property - Unit</th>
                                <th>Type</th>
                                <th class="text-end pe-4">Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($h = $history_list->fetch_assoc()): ?>
                            <tr class="bg-white">
                                <td class="ps-4 text-muted small"><?= date('M d, Y', strtotime($h['datetime_paid'])) ?></td>
                                <td class="fw-bold text-dark"><?= $h['lastname'] ?>, <?= $h['firstname'] ?></td>
                                <td class="text-secondary small"><?= $h['property_name'] ?> - Unit <?= $h['unit_number'] ?></td>
                                <td><span class="badge bg-light text-secondary border px-3"><?= ucfirst($h['type']) ?></span></td>
                                <td class="text-end pe-4 fw-bold text-success">₱<?= number_format($h['amount'], 2) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach($modals as $m): 
    $m_unique_id = $m['pay_type'] . $m['id'] . ($m['sched_id'] ?? '0');
?>
<div class="modal fade" id="payModal<?= $m_unique_id ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-primary-subtle py-3">
                <h5 class="modal-title fw-bold text-primary">Process <?= ucfirst($m['pay_type']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="rent_id" value="<?= $m['id'] ?>">
                <input type="hidden" name="schedule_id" value="<?= $m['sched_id'] ?>">
                <input type="hidden" name="type" value="<?= $m['pay_type'] ?>">
                
                <div class="text-center mb-4 p-3 bg-light rounded-3">
                    <p class="text-muted small mb-1 fw-bold text-uppercase">Receiving From</p>
                    <h5 class="mb-0 text-dark fw-bold"><?= $m['firstname'] ?> <?= $m['lastname'] ?></h5>
                    <p class="mb-0 text-secondary small">Unit <?= $m['unit_number'] ?></p>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small text-uppercase">Verify Payment Amount</label>
                    <div class="input-group input-group-lg border rounded-3 overflow-hidden shadow-sm">
                        <span class="input-group-text bg-white border-0 text-primary fw-bold">₱</span>
                        <input type="number" name="amount" class="form-control border-0 fw-bold text-primary" 
                               value="<?= $m['amount'] ?>" step="0.01" required>
                    </div>
                    <div class="form-text text-center mt-2 small">You can re-type the amount if it differs from the expected.</div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Payment Method</label>
                    <select name="method" class="form-select border shadow-sm" required>
                        <option value="cash">Cash Payment</option>
                        <option value="gcash">GCash Transfer</option>
                        <option value="bank">Bank Deposit</option>
                        <option value="card">Card Payment</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-link text-secondary text-decoration-none fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-5 rounded-pill shadow fw-bold">Post Payment</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<style>
    /* Light Theme Color Overrides */
    .bg-info-subtle { background-color: #e0f2fe !important; }
    .text-info { color: #0284c7 !important; }
    .bg-warning-subtle { background-color: #fffbeb !important; }
    .text-warning { color: #d97706 !important; }
    .bg-primary-subtle { background-color: #eff6ff !important; }
    .text-primary { color: #2563eb !important; }
    .btn-primary { background-color: #2563eb; border-color: #2563eb; }
    .btn-outline-primary { color: #2563eb; border-color: #2563eb; }
    .nav-pills .nav-link.active { background-color: #2563eb; color: #fff !important; }
    .form-control:focus, .form-select:focus { border-color: #2563eb; box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1); }
</style>