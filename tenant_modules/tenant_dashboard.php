<?php
// Assuming session_start() and database connection are handled in tenant.php
$tenant_id = 8; 

// 1. Fetch Tenant Personal Info
$tenant_query = $conn->query("SELECT * FROM tenant WHERE tenant_id = $tenant_id");
$tenant_data = $tenant_query->fetch_assoc();

// 2. Fetch Active Rental Assignment
$rental_query = $conn->query("
    SELECT au.*, u.unit_number, u.monthly_rent, p.property_name, u.unit_id 
    FROM assigned_units au
    JOIN units u ON au.unit_id = u.unit_id
    JOIN properties p ON u.property_id = p.property_id
    WHERE au.tenant_id = $tenant_id AND au.status IN ('occupied', 'pending downpayment')
    LIMIT 1
");
$rental = $rental_query->fetch_assoc();

// 3. Calculate Stats
$overdue_total = 0;
$next_due_date = "N/A";
$next_due_amount = 0;
$aid = 0; // Default to 0 to avoid SQL errors

if ($rental) {
    $aid = (int)$rental['assigned_units_id'];

    // FIX: Total Overdue (Unpaid bills where the due date has already passed)
    $overdue_res = $conn->query("SELECT SUM(amount_due) as total FROM payment_schedule WHERE rent_id = $aid AND status = 'unpaid' AND due_date < CURDATE()");
    $overdue_total = $overdue_res->fetch_assoc()['total'] ?? 0;

    // FIX: Next Upcoming Payment (The very next unpaid bill, even if it's the overdue one)
    $next_res = $conn->query("SELECT due_date, amount_due FROM payment_schedule WHERE rent_id = $aid AND status = 'unpaid' ORDER BY due_date ASC LIMIT 1");
    if ($next_row = $next_res->fetch_assoc()) {
        $next_due_date = date('M d, Y', strtotime($next_row['due_date']));
        $next_due_amount = $next_row['amount_due'];
    }
}
?>

<div class="pagetitle">
    <h1>Welcome, <?= htmlspecialchars($tenant_data['firstname']) ?>!</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="tenant.php">Home</a></li>
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
</div>

<section class="section dashboard">
    <div class="row">

        <div class="col-lg-8">
            <div class="row">

                <div class="col-xxl-4 col-md-6">
                    <div class="card info-card sales-card">
                        <div class="card-body">
                            <h5 class="card-title">Monthly Rent <span>|
                                    <?= $rental['property_name'] ?? 'No Unit' ?></span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-house-door"></i>
                                </div>
                                <div class="ps-3">
                                    <h6>₱<?= number_format($rental['monthly_rent'] ?? 0, 2) ?></h6>
                                    <span class="text-muted small pt-2">Unit:</span> <span
                                        class="text-primary small pt-1 fw-bold"><?= $rental['unit_number'] ?? 'N/A' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-md-6">
                    <div class="card info-card revenue-card">
                        <div class="card-body">
                            <h5 class="card-title">Overdue Balance <span>| Total</span></h5>
                            <div class="d-flex align-items-center">
                                <div
                                    class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-danger-light">
                                    <i class="bi bi-exclamation-octagon text-danger"></i>
                                </div>
                                <div class="ps-3">
                                    <h6 class="text-danger">₱<?= number_format($overdue_total, 2) ?></h6>
                                    <span class="text-muted small pt-2 ps-1">Immediate action required</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-12">
                    <div class="card info-card customers-card">
                        <div class="card-body">
                            <h5 class="card-title">Next Payment <span>| Upcoming</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-calendar-event"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $next_due_date ?></h6>
                                    <span class="text-muted small pt-2">Amount:</span> <span
                                        class="text-success small pt-1 fw-bold">₱<?= number_format($next_due_amount, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card recent-sales overflow-auto">
                        <div class="card-body">
                            <h5 class="card-title">Recent Payments <span>| Last 5</span></h5>
                            <table class="table table-borderless datatable">
                                <thead>
                                    <tr>
                                        <th scope="col">Date</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Amount</th>
                                        <th scope="col">Method</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $history = $conn->query("SELECT * FROM payments WHERE rent_id = " . ($aid ?? 0) . " ORDER BY datetime_paid DESC LIMIT 5");
                                    while ($h = $history->fetch_assoc()):
                                        ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($h['datetime_paid'])) ?></td>
                                            <td><?= ucfirst($h['type']) ?></td>
                                            <td class="fw-bold">₱<?= number_format($h['amount'], 2) ?></td>
                                            <td><?= ucfirst($h['method']) ?></td>
                                            <td><span class="badge bg-success">Success</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary py-3" data-bs-toggle="modal"
                            data-bs-target="#payAllModal<?= $aid ?>">
                            <i class="bi bi-credit-card me-2"></i> Pay Overdue Dues
                        </button>
                        <button class="btn btn-outline-secondary py-3" type="button">
                            <i class="bi bi-tools me-2"></i> Request Maintenance
                        </button>
                    </div>
                </div>
            </div>
<div class="modal fade" id="payAllModal<?= $aid ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="tenant.php?page=rent_onboarding" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 bg-primary-subtle py-3">
                <h5 class="modal-title fw-bold text-primary">Settlement of Dues</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" value="process_pay_all"> 
                <input type="hidden" name="rent_id" value="<?= $aid ?>">

                <div class="alert alert-info small">
                    Your current overdue total is: <b>₱<?= number_format($overdue_total, 2) ?></b>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small text-uppercase">Payment Amount</label>
                    <div class="input-group input-group-lg border rounded-3 overflow-hidden shadow-sm">
                        <span class="input-group-text bg-white border-0 text-primary fw-bold">₱</span>
                        <input type="number" name="amount" class="form-control border-0 fw-bold text-primary" 
                               value="<?= $overdue_total ?>" step="0.01" required>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Method</label>
                    <select name="method" class="form-select border shadow-sm" required>
                        <option value="gcash">GCash</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="submit" class="btn btn-primary w-100 rounded-pill shadow fw-bold">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>
            <div class="card mt-3">
                <div class="card-body pb-0">
                    <h5 class="card-title">Property Management</h5>
                    <div class="news">
                        <div class="post-item clearfix">
                            <img src="../assets/img/logo3.png" alt="">
                            <h4><a href="#">KCI Inc. Management</a></h4>
                            <p>Contact us for any concerns regarding your unit or utilities.</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<style>
    .bg-danger-light {
        background-color: #fee2e2 !important;
    }

    .info-card h6 {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        padding: 0;
    }
</style>