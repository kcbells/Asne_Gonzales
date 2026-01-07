<?php
require_once "conn.php";

/* =======================
   BASIC COUNTS
======================= */

// Total properties
$property_count = $conn->query("SELECT COUNT(*) total FROM properties")
    ->fetch_assoc()['total'];

// Total units
$unit_count = $conn->query("SELECT COUNT(*) total FROM units")
    ->fetch_assoc()['total'];

// Available units
$available_units = $conn->query("
    SELECT COUNT(*) total 
    FROM units u
    LEFT JOIN assigned_units au ON u.unit_id = au.unit_id 
    WHERE au.assigned_units_id IS NULL OR au.status = 'available'
")->fetch_assoc()['total'];

// Occupied units
$occupied_units = $conn->query("
    SELECT COUNT(*) total 
    FROM assigned_units 
    WHERE status IN ('occupied','pending downpayment')
")->fetch_assoc()['total'];


/* =======================
   REVENUE
======================= */

// Total revenue (successful payments only)
$total_revenue = $conn->query("
    SELECT IFNULL(SUM(amount),0) total 
    FROM payments 
    WHERE status = 'success'
")->fetch_assoc()['total'];

// Revenue by year for modal
$revenue_by_year = $conn->query("SELECT YEAR(datetime_paid) year, IFNULL(SUM(amount),0) total FROM payments WHERE status = 'success' GROUP BY YEAR(datetime_paid) ORDER BY YEAR(datetime_paid) DESC");


/* =======================
   MONTHLY REVENUE (LINE CHART)
======================= */

$monthly_revenue = [];
$months = [];

$res = $conn->query("
    SELECT 
        DATE_FORMAT(datetime_paid, '%Y-%m') month,
        SUM(amount) total
    FROM payments
    WHERE status = 'success'
    GROUP BY month
    ORDER BY month ASC
");

while ($row = $res->fetch_assoc()) {
    $months[] = $row['month'];
    $monthly_revenue[] = $row['total'];
}


/* =======================
   DUE TENANTS
======================= */

$due_tenants = $conn->query("
    SELECT 
        t.firstname,
        t.lastname,
        u.unit_number,
        au.start_date,
        u.monthly_rent,
        IFNULL(SUM(p.amount),0) paid
    FROM assigned_units au
    JOIN units u ON au.unit_id = u.unit_id
    JOIN tenant t ON au.tenant_id = t.tenant_id
    LEFT JOIN payments p 
        ON p.rent_id = au.assigned_units_id 
        AND p.status = 'success'
        AND MONTH(p.datetime_paid) = MONTH(CURRENT_DATE())
        AND YEAR(p.datetime_paid) = YEAR(CURRENT_DATE())
    WHERE au.status IN ('occupied','pending downpayment')
    GROUP BY au.assigned_units_id
    HAVING paid < u.monthly_rent
");

$due_count = $due_tenants->num_rows;
?>
<div class="container-fluid py-4">

    <h4 class="fw-bold text-secondary mb-4">Dashboard Overview</h4>

    <!-- STATS -->
    <div class="row row-cols-1 row-cols-md-5 g-3 mb-4">

        <div class="col">
            <div class="card shadow-md border-0 text-center p-3 h-100 justify-content-center" role="button" data-bs-toggle="modal" data-bs-target="#revenueModal" style="cursor:pointer;" title="Click to view revenue by year">
                <small class="text-muted">Total Revenue</small>
                <h4 class="fw-bold text-primary">₱<?= number_format($total_revenue, 2) ?></h4>
            </div>
        </div>

        <div class="col">
            <a href="admin.php?page=add_asset" class="text-decoration-none text-reset">
                <div class="card shadow-md border-0 text-center p-3 h-100 justify-content-center" title="Go to Add Asset">
                    <small class="text-muted">Properties</small>
                    <h4 class="fw-bold"><?= $property_count ?></h4>
                </div>
            </a>
        </div>

        <div class="col">
            <div class="card shadow-md border-0 text-center p-3 h-100 justify-content-center">
                <small class="text-muted">Units</small>
                <h4 class="fw-bold"><?= $unit_count ?></h4>
            </div>
        </div>

        <div class="col">
            <div class="card shadow-md border-0 text-center p-3 h-100 justify-content-center">
                <small class="text-muted">Available Units</small>
                <h4 class="fw-bold text-success"><?= $available_units ?></h4>
            </div>
        </div>

        <div class="col">
            <div class="card shadow-md border-0 text-center p-3 h-100 justify-content-center">
                <small class="text-muted">Occupied</small>
                <h4 class="fw-bold text-danger"><?= $occupied_units ?></h4>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <!-- REVENUE -->
        <div class="col-md-8">
            <div class="card shadow border-0 p-3">
                <canvas id="revenueLine"></canvas>
            </div>
        </div>

        <!-- OCCUPANCY PIE -->
        <div class="col-md-4">
            <div class="card shadow border-0 p-3">
                <canvas id="occupancyPie"></canvas>
            </div>
        </div>

    </div>

    <div class="row g-3 mb-4">

        <!-- DUE TENANTS -->
        <div class="col-12">
            <div class="card shadow border-0">
                <div class="card-header bg-primary text-white fw-bold">
                    Tenants with Due Payments (<?= $due_count ?>)
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Tenant</th>
                                <th>Unit</th>
                                <th>Start Date</th>
                                <th>Paid</th>
                                <th>Monthly Rent</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($d = $due_tenants->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $d['firstname'] . " " . $d['lastname'] ?></td>
                                    <td><?= $d['unit_number'] ?></td>
                                    <td><?= date('M d, Y', strtotime($d['start_date'])) ?></td>
                                    <td>₱<?= number_format($d['paid'], 2) ?></td>
                                    <td>₱<?= number_format($d['monthly_rent'], 2) ?></td>
                                    <td class="text-danger fw-bold">
                                        ₱<?= number_format($d['monthly_rent'] - $d['paid'], 2) ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                            <?php if ($due_count == 0): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        No dues 🎉
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>


</div>
<!-- Revenue by Year Modal -->
<div class="modal fade" id="revenueModal" tabindex="-1" aria-labelledby="revenueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="revenueModalLabel">Total Revenue by Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-borderless table-sm mb-0 small">
                        <thead class="visually-hidden">
                            <tr>
                                <th>Year</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($revenue_by_year && $revenue_by_year->num_rows > 0): ?>
                                <?php while ($y = $revenue_by_year->fetch_assoc()): ?>
                                    <tr class="align-middle">
                                        <td class="text-muted" style="padding-left:0; width:1px; white-space:nowrap"><?= htmlspecialchars($y['year']) ?></td>
                                        <td class="text-end fw-semibold">₱<?= number_format($y['total'], 2) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center text-muted">No revenue records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    new Chart(document.getElementById('occupancyPie'), {
        type: 'pie',
        data: {
            labels: ['Available', 'Occupied'],
            datasets: [{
                data: [<?= $available_units ?>, <?= $occupied_units ?>],
                backgroundColor: ['#198754', '#dc3545']
            }]
        }
    });

    new Chart(document.getElementById('revenueLine'), {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Monthly Revenue',
                data: <?= json_encode($monthly_revenue) ?>,
                borderColor: '#0d6efd',
                fill: false,
                tension: 0.3
            }]
        }
    });
</script>