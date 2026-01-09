<?php
require_once "conn.php"; 

// --- ADMIN SCOPE: FETCH GLOBAL DATA ---
$total_properties = $conn->query("SELECT COUNT(*) as total FROM properties")->fetch_assoc()['total'];
$total_units = $conn->query("SELECT COUNT(*) as total FROM units")->fetch_assoc()['total'];
$total_revenue = $conn->query("SELECT SUM(monthly_rent) as total FROM units")->fetch_assoc()['total'] ?? 0;

// Chart Data (Global)
$rev_query = $conn->query("SELECT p.property_name, SUM(u.monthly_rent) as total FROM properties p LEFT JOIN units u ON p.property_id = u.property_id GROUP BY p.property_id LIMIT 5");
$p_names = []; $p_revs = [];
while($r = $rev_query->fetch_assoc()){ $p_names[] = $r['property_name']; $p_revs[] = (float)$r['total']; }

$status_query = $conn->query("SELECT status, COUNT(*) as count FROM units GROUP BY status");
$s_labels = []; $s_counts = [];
while($r = $status_query->fetch_assoc()){ $s_labels[] = ucfirst($r['status']); $s_counts[] = (int)$r['count']; }
?>

<div class="container-fluid">
    <div class="pagetitle mb-4">
        <h1 class="fw-bold" style="color: #012970;">Dashboard Overview</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item">Admin</li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card info-card shadow-sm border-0 py-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: #f0f4ff;">
                        <i class="bi bi-building fs-4" style="color: #4154f1;"></i>
                    </div>
                    <div class="ps-3">
                        <h6 class="mb-0 text-muted small">Properties <span class="text-secondary">| Total</span></h6>
                        <h4 class="fw-bold mb-0"><?= $total_properties ?></h4>
                        <span class="text-muted small">Registered Assets</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card info-card shadow-sm border-0 py-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: #e7faf0;">
                        <i class="bi bi-door-open fs-4" style="color: #2eca6a;"></i>
                    </div>
                    <div class="ps-3">
                        <h6 class="mb-0 text-muted small">Units <span class="text-secondary">| Total</span></h6>
                        <h4 class="fw-bold mb-0"><?= $total_units ?></h4>
                        <span class="text-muted small">Total Rooms/Spaces</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card info-card shadow-sm border-0 py-3">
                <div class="card-body d-flex align-items-center">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; background-color: #fff9e6;">
                        <i class="bi bi-currency-dollar fs-4" style="color: #ffbb2c;"></i>
                    </div>
                    <div class="ps-3">
                        <h6 class="mb-0 text-muted small">Potential Revenue <span class="text-secondary">| Monthly</span></h6>
                        <h4 class="fw-bold mb-0">₱<?= number_format($total_revenue, 2) ?></h4>
                        <span class="text-muted small">Combined Rent Value</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold">Revenue Distribution</h5>
                    <div id="revenueChart"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title fw-bold">Occupancy Status</h5>
                    <div id="statusChart"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    new ApexCharts(document.querySelector("#revenueChart"), {
        series: [{ name: 'Revenue', data: <?php echo json_encode($p_revs); ?> }],
        chart: { type: 'bar', height: 350, toolbar: {show: false} },
        colors: ['#4154f1'],
        xaxis: { categories: <?php echo json_encode($p_names); ?> }
    }).render();

    new ApexCharts(document.querySelector("#statusChart"), {
        series: <?php echo json_encode($s_counts); ?>,
        chart: { type: 'donut', height: 350 },
        labels: <?php echo json_encode($s_labels); ?>,
        colors: ['#2eca6a', '#ff771d'],
        legend: { position: 'bottom' }
    }).render();
});
</script>