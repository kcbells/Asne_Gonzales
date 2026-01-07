<?php
require_once "conn.php";
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


function applyPaymentToSchedules(mysqli $conn, int $rent_id, float $pay_amount, ?int $start_schedule_id = null): float
{
    if ($pay_amount <= 0) return 0.0;

    $due_date_from = null;

    if ($start_schedule_id !== null) {
        $s = $conn->prepare("SELECT due_date FROM payment_schedule WHERE schedule_id=? AND rent_id=?");
        $s->bind_param("ii", $start_schedule_id, $rent_id);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if ($row) $due_date_from = $row['due_date'];
    }

    if ($due_date_from !== null) {
        $q = $conn->prepare("
            SELECT schedule_id, amount_due
            FROM payment_schedule
            WHERE rent_id = ?
              AND status = 'unpaid'
              AND due_date >= ?
            ORDER BY due_date ASC, schedule_id ASC
            FOR UPDATE
        ");
        $q->bind_param("is", $rent_id, $due_date_from);
    } else {
        // IMPORTANT: no due_date filter => overpayment goes to future months automatically
        $q = $conn->prepare("
            SELECT schedule_id, amount_due
            FROM payment_schedule
            WHERE rent_id = ?
              AND status = 'unpaid'
            ORDER BY due_date ASC, schedule_id ASC
            FOR UPDATE
        ");
        $q->bind_param("i", $rent_id);
    }

    $q->execute();
    $res = $q->get_result();

    $applied = 0.0;

    while ($row = $res->fetch_assoc()) {
        if ($pay_amount <= 0) break;

        $sid = (int)$row['schedule_id'];
        $due = (float)$row['amount_due'];

        // safety: treat zero/negative as paid
        if ($due <= 0) {
            $u0 = $conn->prepare("UPDATE payment_schedule SET status='paid', amount_due=0 WHERE schedule_id=?");
            $u0->bind_param("i", $sid);
            $u0->execute();
            $u0->close();
            continue;
        }

        if ($pay_amount >= $due) {
            // fully pay
            $pay_amount -= $due;
            $applied += $due;

            $u = $conn->prepare("UPDATE payment_schedule SET status='paid', amount_due=0 WHERE schedule_id=?");
            $u->bind_param("i", $sid);
            $u->execute();
            $u->close();
        } else {
            // partial pay
            $new_due = $due - $pay_amount;
            $applied += $pay_amount;
            $pay_amount = 0;

            $u = $conn->prepare("UPDATE payment_schedule SET amount_due=? WHERE schedule_id=?");
            $u->bind_param("di", $new_due, $sid);
            $u->execute();
            $u->close();
        }
    }

    $q->close();
    return $applied;
}

// =========================
// 1) PAYMENT PROCESSING
// =========================
$msg = "";

/**
 * PAY ALL (shows overdue total but user can edit amount)
 * - Applies across schedules (including future) to support overpay carry-over.
 */
if (isset($_POST['action']) && $_POST['action'] === "process_pay_all") {
    $rent_id  = intval($_POST['rent_id']);
    $amount   = floatval($_POST['amount']); // editable
    $method   = $_POST['method'];
    $date_paid = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {
        $applied = applyPaymentToSchedules($conn, $rent_id, $amount, null);
        if ($applied <= 0) {
            throw new Exception("No unpaid schedules found to apply payment.");
        }

        $payStmt = $conn->prepare("
            INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status)
            VALUES (?, 'monthly', ?, ?, ?, 'success')
        ");
        $payStmt->bind_param("idss", $rent_id, $amount, $date_paid, $method);
        $payStmt->execute();
        $payStmt->close();

        $conn->commit();
        $msg = "Payment applied successfully! (Any overpayment will reduce future dues.)";
    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}

/**
 * SINGLE PAYMENT
 * - Downpayment: one-time only; generates schedule:
 *      Month 1 billed (start+1 month): monthly_rent - downpayment_paid
 *      Month 2..12 billed: full monthly_rent
 * - Monthly: applies starting from the selected schedule's due_date onward (supports partial + overpay carry-over).
 */
if (isset($_POST['action']) && $_POST['action'] === "process_payment") {
    $rent_id   = intval($_POST['rent_id']);
    $amount    = floatval($_POST['amount']);
    $method    = $_POST['method'];
    $type      = $_POST['type']; // downpayment|monthly
    $schedule_id = isset($_POST['schedule_id']) && $_POST['schedule_id'] !== "" ? intval($_POST['schedule_id']) : null;
    $date_paid = date('Y-m-d H:i:s');

    $conn->begin_transaction();
    try {

        if ($type === 'downpayment') {
            // 1) Prevent duplicate downpayment
            $dpCheck = $conn->prepare("
                SELECT COUNT(*)
                FROM payments
                WHERE rent_id = ?
                  AND type = 'downpayment'
                  AND status = 'success'
            ");
            $dpCheck->bind_param("i", $rent_id);
            $dpCheck->execute();
            $dpCheck->bind_result($dpCount);
            $dpCheck->fetch();
            $dpCheck->close();

            if ($dpCount > 0) {
                throw new Exception("Downpayment was already recorded for this tenant/unit.");
            }

            // 2) Insert payment history (downpayment)
            $stmt = $conn->prepare("INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status)
                VALUES (?, 'downpayment', ?, ?, ?, 'success')
            ");
            $stmt->bind_param("idss", $rent_id, $amount, $date_paid, $method);
            $stmt->execute();
            $stmt->close();

            // 3) Mark assigned unit occupied + save dp paid
            $stmt_upd = $conn->prepare("UPDATE assigned_units
                SET status = 'occupied',
                    downpayment = ?
                WHERE assigned_units_id = ?
            ");
            $stmt_upd->bind_param("di", $amount, $rent_id);
            $stmt_upd->execute();
            $stmt_upd->close();

            // 4) Update unit occupied
            $stmt_unit = $conn->prepare("UPDATE units
                SET status = 'occupied'
                WHERE unit_id = (SELECT unit_id FROM assigned_units WHERE assigned_units_id = ?)
            ");
            $stmt_unit->bind_param("i", $rent_id);
            $stmt_unit->execute();
            $stmt_unit->close();

            // 5) Create schedule once
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM payment_schedule WHERE rent_id = ?");
            $checkStmt->bind_param("i", $rent_id);
            $checkStmt->execute();
            $checkStmt->bind_result($schedCount);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($schedCount == 0) {
                $infoStmt = $conn->prepare("SELECT au.start_date, u.monthly_rent
                    FROM assigned_units au
                    JOIN units u ON au.unit_id = u.unit_id
                    WHERE au.assigned_units_id = ?
                ");
                $infoStmt->bind_param("i", $rent_id);
                $infoStmt->execute();
                $info = $infoStmt->get_result()->fetch_assoc();
                $infoStmt->close();

                if (!$info) {
                    throw new Exception("Unable to load rental info for schedule creation.");
                }

                $start  = new DateTime($info['start_date']);
                $m_rent = (float)$info['monthly_rent'];

                // First billed month due date = start + 1 month (ex: Nov 21 start => Dec 21 due)
                $first_due_date = (clone $start)->add(new DateInterval('P1M'))->format('Y-m-d');

                // First billed month amount = rent - downpayment paid
                $first_month_due = $m_rent - $amount;
                if ($first_month_due < 0) $first_month_due = 0;

                $schStmt = $conn->prepare("INSERT INTO payment_schedule (rent_id, due_date, amount_due, status)
                    VALUES (?, ?, ?, 'unpaid')
                ");

                // Month 1 billed: remaining balance only
                $schStmt->bind_param("isd", $rent_id, $first_due_date, $first_month_due);
                $schStmt->execute();

                // Month 2..12 billed: full rent
                $curr = new DateTime($first_due_date);
                for ($i = 2; $i <= 12; $i++) {
                    $curr->add(new DateInterval('P1M'));
                    $due_date = $curr->format('Y-m-d');
                    $full_due = $m_rent;

                    $schStmt->bind_param("isd", $rent_id, $due_date, $full_due);
                    $schStmt->execute();
                }

                $schStmt->close();
            }

            $conn->commit();
            $msg = "Downpayment recorded and schedule created successfully!";

        } else {
            // MONTHLY PAYMENT
            if (!$schedule_id) {
                throw new Exception("Missing schedule_id for monthly payment.");
            }

            $applied = applyPaymentToSchedules($conn, $rent_id, $amount, $schedule_id);
            if ($applied <= 0) {
                throw new Exception("No unpaid schedules found to apply this payment.");
            }

            // payment history
            $stmt = $conn->prepare("INSERT INTO payments (rent_id, type, amount, datetime_paid, method, status)
                VALUES (?, 'monthly', ?, ?, ?, 'success')
            ");
            $stmt->bind_param("idss", $rent_id, $amount, $date_paid, $method);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $msg = "Monthly payment applied successfully! (Any overpayment will reduce next dues.)";
        }

    } catch (Exception $e) {
        $conn->rollback();
        $msg = "Error: " . $e->getMessage();
    }
}


// =========================
// 2) DATA FETCHING
// =========================

// Pending (ONE ROW per tenant/unit):
// - pending downpayment: assigned_units.status = pending downpayment
// - monthly overdue: sum ONLY overdue (due_date <= today)
$pending_sql = "
    (SELECT
        au.assigned_units_id AS id,
        'downpayment' AS pay_type,
        t.firstname,
        t.lastname,
        u.unit_number,
        au.downpayment AS amount,
        au.start_date AS due_date,
        0 AS overdue_count,
        0 AS overdue_total
     FROM assigned_units au
     JOIN tenant t ON au.tenant_id = t.tenant_id
     JOIN units u ON au.unit_id = u.unit_id
     WHERE au.status = 'pending downpayment'
    )
    UNION ALL
    (SELECT
        au.assigned_units_id AS id,
        'monthly' AS pay_type,
        t.firstname,
        t.lastname,
        u.unit_number,
        SUM(ps.amount_due) AS amount,          -- total overdue
        MIN(ps.due_date) AS due_date,          -- earliest overdue date
        COUNT(*) AS overdue_count,
        SUM(ps.amount_due) AS overdue_total
     FROM payment_schedule ps
     JOIN assigned_units au ON ps.rent_id = au.assigned_units_id
     JOIN tenant t ON au.tenant_id = t.tenant_id
     JOIN units u ON au.unit_id = u.unit_id
     WHERE ps.status='unpaid'
       AND ps.due_date <= CURDATE()
     GROUP BY au.assigned_units_id
    )
    ORDER BY due_date ASC
";
$pending_list = $conn->query($pending_sql);

$history_sql = "
    SELECT p.*, t.firstname, t.lastname, u.unit_number, prop.property_name
    FROM payments p
    JOIN assigned_units au ON p.rent_id = au.assigned_units_id
    JOIN tenant t ON au.tenant_id = t.tenant_id
    JOIN units u ON au.unit_id = u.unit_id
    JOIN properties prop ON u.property_id = prop.property_id
    ORDER BY p.datetime_paid DESC
";
$history_list = $conn->query($history_sql);

// collect modals during rendering
$modal_downpayments = []; // rent_id => info
$modal_payall = [];       // rent_id => info
$modal_monthlies = [];    // list of due rows for individual pay modals

?>

<div class="container-fluid py-4 bg-light min-vh-100">
    <?php if ($msg): ?>
        <div class="alert alert-success border-0 shadow-sm alert-dismissible fade show">
            <?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <h4 class="fw-bold text-secondary">Payments</h4>

    <ul class="nav nav-pills mb-4 bg-white p-2 rounded shadow-sm" id="payTabs" style="width: fit-content;">
        <li class="nav-item">
            <button class="nav-link active fw-bold px-4" data-bs-toggle="pill" data-bs-target="#tab-pending">Pending</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold px-4 text-secondary" data-bs-toggle="pill" data-bs-target="#tab-history">History</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- PENDING TAB -->
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
                            <?php while ($p = $pending_list->fetch_assoc()): ?>
                                <?php
                                    $rent_id = (int)$p['id'];
                                    $pay_type = $p['pay_type'];
                                    $collapseId = "duesCollapse" . $rent_id;
                                ?>

                                <!-- MAIN ROW -->
                                <tr class="bg-white">
                                    <td class="ps-4">
                                        <span class="badge rounded-pill py-2 px-3 <?= $pay_type == 'downpayment'
                                            ? 'bg-info-subtle text-info border border-info-subtle'
                                            : 'bg-warning-subtle text-warning border border-warning-subtle' ?>">
                                            <?= strtoupper($pay_type) ?>
                                        </span>

                                        <?php if ($pay_type == 'monthly'): ?>
                                            <div class="small text-muted mt-1">
                                                Overdue: <?= (int)$p['overdue_count'] ?> month(s)
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <td class="fw-bold text-dark"><?= htmlspecialchars($p['lastname']) ?>, <?= htmlspecialchars($p['firstname']) ?></td>
                                    <td class="text-secondary">Unit <?= htmlspecialchars($p['unit_number']) ?></td>

                                    <td class="text-primary fw-bold">
                                        ₱<?= number_format((float)$p['amount'], 2) ?>
                                        <div class="small text-muted">
                                            Earliest: <?= date('M d, Y', strtotime($p['due_date'])) ?>
                                        </div>
                                    </td>

                                    <td class="text-end pe-4">
                                        <?php if ($pay_type == 'downpayment'): ?>
                                            <?php
                                                $modal_downpayments[$rent_id] = [
                                                    'firstname' => $p['firstname'],
                                                    'lastname' => $p['lastname'],
                                                    'unit_number' => $p['unit_number'],
                                                    'amount' => (float)$p['amount'],
                                                ];
                                            ?>
                                            <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold"
                                                    data-bs-toggle="modal" data-bs-target="#payModal_downpayment<?= $rent_id ?>">
                                                Pay
                                            </button>
                                        <?php else: ?>
                                            <?php
                                                $modal_payall[$rent_id] = [
                                                    'firstname' => $p['firstname'],
                                                    'lastname' => $p['lastname'],
                                                    'unit_number' => $p['unit_number'],
                                                    'overdue_total' => (float)$p['overdue_total'],
                                                ];
                                            ?>
                                            <div class="d-flex justify-content-end gap-2 flex-wrap">
                                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold"
                                                        data-bs-toggle="modal" data-bs-target="#payAllModal<?= $rent_id ?>">
                                                    Pay
                                                </button>

                                                <button class="btn btn-light btn-sm rounded-pill px-3 fw-bold border"
                                                        type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                                                        aria-expanded="false" aria-controls="<?= $collapseId ?>">
                                                    View Dues
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($pay_type == 'monthly'): ?>
                                    <!-- COLLAPSE ROW -->
                                    <tr class="bg-white">
                                        <td colspan="5" class="p-0 border-top-0">
                                            <div class="collapse" id="<?= $collapseId ?>">
                                                <div class="card border-0 border-top rounded-0">
                                                    <div class="card-body p-3">

                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <div>
                                                                <div class="fw-bold">Overdue Dues</div>
                                                                <div class="small text-muted">
                                                                    <?= htmlspecialchars($p['firstname']) ?> <?= htmlspecialchars($p['lastname']) ?> • Unit <?= htmlspecialchars($p['unit_number']) ?>
                                                                </div>
                                                            </div>

                                                            <button class="btn btn-success btn-sm rounded-pill px-3 fw-bold"
                                                                    data-bs-toggle="modal" data-bs-target="#payAllModal<?= $rent_id ?>">
                                                                Pay All (₱<?= number_format((float)$p['overdue_total'], 2) ?>)
                                                            </button>
                                                        </div>

                                                        <div class="table-responsive">
                                                            <table class="table table-sm table-hover table-bordered mb-0">
                                                                <thead class="table-light small text-uppercase">
                                                                    <tr>
                                                                        <th>Due Date</th>
                                                                        <th class="text-end">Amount</th>
                                                                        <th class="text-end">Action</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php
                                                                        // Show EACH overdue due_date row
                                                                        $dueListStmt = $conn->prepare("
                                                                            SELECT schedule_id, due_date, amount_due
                                                                            FROM payment_schedule
                                                                            WHERE rent_id = ?
                                                                              AND status = 'unpaid'
                                                                              AND due_date <= CURDATE()
                                                                            ORDER BY due_date ASC, schedule_id ASC
                                                                        ");
                                                                        $dueListStmt->bind_param("i", $rent_id);
                                                                        $dueListStmt->execute();
                                                                        $dueRes = $dueListStmt->get_result();

                                                                        if ($dueRes->num_rows == 0):
                                                                    ?>
                                                                        <tr>
                                                                            <td colspan="3" class="text-center text-muted small">
                                                                                No overdue dues.
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                        else:
                                                                            while ($d = $dueRes->fetch_assoc()):
                                                                                $sid = (int)$d['schedule_id'];
                                                                                $modalId = "payModal_monthly{$rent_id}_{$sid}";

                                                                                $modal_monthlies[] = [
                                                                                    'rent_id' => $rent_id,
                                                                                    'schedule_id' => $sid,
                                                                                    'due_date' => $d['due_date'],
                                                                                    'amount_due' => (float)$d['amount_due'],
                                                                                    'modal_id' => $modalId,
                                                                                ];
                                                                    ?>
                                                                        <tr>
                                                                            <td><?= date('M d, Y', strtotime($d['due_date'])) ?></td>
                                                                            <td class="text-end fw-bold">₱<?= number_format((float)$d['amount_due'], 2) ?></td>
                                                                            <td class="text-end">
                                                                                <button class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold"
                                                                                        data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                                                                                    Pay
                                                                                </button>
                                                                            </td>
                                                                        </tr>
                                                                    <?php
                                                                            endwhile;
                                                                        endif;

                                                                        $dueListStmt->close();
                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        <div class="small text-muted mt-2">
                                                            If you pay more than the total overdue, the extra will automatically reduce the next month(s).
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- HISTORY TAB -->
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
                            <?php while ($h = $history_list->fetch_assoc()): ?>
                                <tr class="bg-white">
                                    <td class="ps-4 text-muted small"><?= date('M d, Y', strtotime($h['datetime_paid'])) ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($h['lastname']) ?>, <?= htmlspecialchars($h['firstname']) ?></td>
                                    <td class="text-secondary small"><?= htmlspecialchars($h['property_name']) ?> - Unit <?= htmlspecialchars($h['unit_number']) ?></td>
                                    <td><span class="badge bg-light text-secondary border px-3"><?= ucfirst($h['type']) ?></span></td>
                                    <td class="text-end pe-4 fw-bold text-success">₱<?= number_format((float)$h['amount'], 2) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ===================== MODALS ===================== -->

<!-- Downpayment Modals -->
<?php foreach ($modal_downpayments as $rent_id => $m): ?>
    <div class="modal fade" id="payModal_downpayment<?= $rent_id ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 bg-primary-subtle py-3">
                    <h5 class="modal-title fw-bold text-primary">Process Downpayment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="rent_id" value="<?= $rent_id ?>">
                    <input type="hidden" name="type" value="downpayment">

                    <div class="text-center mb-4 p-3 bg-light rounded-3">
                        <p class="text-muted small mb-1 fw-bold text-uppercase">Receiving From</p>
                        <h5 class="mb-0 text-dark fw-bold"><?= htmlspecialchars($m['firstname']) ?> <?= htmlspecialchars($m['lastname']) ?></h5>
                        <p class="mb-0 text-secondary small">Unit <?= htmlspecialchars($m['unit_number']) ?></p>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Downpayment Paid</label>
                        <div class="input-group input-group-lg border rounded-3 overflow-hidden shadow-sm">
                            <span class="input-group-text bg-white border-0 text-primary fw-bold">₱</span>
                            <input type="number" name="amount" class="form-control border-0 fw-bold text-primary"
                                   value="<?= (float)$m['amount'] ?>" step="0.01" required>
                        </div>
                        <div class="form-text text-center mt-2 small">
                            This will be deducted from the first billed month: (Monthly Rent - Downpayment Paid).
                        </div>
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

<!-- Pay All Modals -->
<?php foreach ($modal_payall as $rent_id => $m): ?>
    <div class="modal fade" id="payAllModal<?= $rent_id ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 bg-primary-subtle py-3">
                    <h5 class="modal-title fw-bold text-primary">Pay Dues (Total / Overpay Allowed)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="process_pay_all">
                    <input type="hidden" name="rent_id" value="<?= $rent_id ?>">

                    <div class="text-center mb-4 p-3 bg-light rounded-3">
                        <h5 class="mb-0 text-dark fw-bold"><?= htmlspecialchars($m['firstname']) ?> <?= htmlspecialchars($m['lastname']) ?></h5>
                        <p class="mb-0 text-secondary small">Unit <?= htmlspecialchars($m['unit_number']) ?></p>
                    </div>

                    <div class="alert alert-info small">
                        Current overdue total: <b>₱<?= number_format((float)$m['overdue_total'], 2) ?></b><br>
                        You may enter a higher amount to reduce future dues.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small text-uppercase">Amount to Pay</label>
                        <div class="input-group input-group-lg border rounded-3 overflow-hidden shadow-sm">
                            <span class="input-group-text bg-white border-0 text-primary fw-bold">₱</span>
                            <input type="number" name="amount" class="form-control border-0 fw-bold text-primary"
                                   value="<?= (float)$m['overdue_total'] ?>" step="0.01" required>
                        </div>
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
                    <button type="submit" class="btn btn-primary px-5 rounded-pill shadow fw-bold">Pay & Apply</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<!-- Monthly Individual Pay Modals -->
<?php foreach ($modal_monthlies as $m): ?>
    <div class="modal fade" id="<?= htmlspecialchars($m['modal_id']) ?>" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 bg-primary-subtle py-3">
                    <h5 class="modal-title fw-bold text-primary">
                        Pay Due (<?= date('M d, Y', strtotime($m['due_date'])) ?>)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="process_payment">
                    <input type="hidden" name="rent_id" value="<?= (int)$m['rent_id'] ?>">
                    <input type="hidden" name="schedule_id" value="<?= (int)$m['schedule_id'] ?>">
                    <input type="hidden" name="type" value="monthly">

                    <div class="mb-4">
                        <label class="form-label fw-bold text-muted small text-uppercase">Amount to Pay</label>
                        <div class="input-group input-group-lg border rounded-3 overflow-hidden shadow-sm">
                            <span class="input-group-text bg-white border-0 text-primary fw-bold">₱</span>
                            <input type="number" name="amount" class="form-control border-0 fw-bold text-primary"
                                   value="<?= (float)$m['amount_due'] ?>" step="0.01" required>
                        </div>
                        <div class="form-text small text-muted">
                            You can pay partially or overpay. Overpay will reduce next dues automatically.
                        </div>
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
    .bg-info-subtle { background-color: #e0f2fe !important; }
    .text-info { color: #0284c7 !important; }

    .bg-warning-subtle { background-color: #fffbeb !important; }
    .text-warning { color: #d97706 !important; }

    .bg-primary-subtle { background-color: #eff6ff !important; }
    .text-primary { color: #2563eb !important; }

    .btn-primary { background-color: #2563eb; border-color: #2563eb; }
    .btn-outline-primary { color: #2563eb; border-color: #2563eb; }

    .nav-pills .nav-link.active { background-color: #2563eb; color: #fff !important; }

    .form-control:focus, .form-select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.1);
    }
</style>
