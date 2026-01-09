<?php
require_once "conn.php"; 

// Ivan Maglupay's ID
$ivan_id = 4; 

// --- CALCULATE DASHBOARD STATS ---
$prop_count_res = $conn->query("SELECT COUNT(*) as total FROM properties WHERE owner_id = $ivan_id");
$total_properties = $prop_count_res->fetch_assoc()['total'];

$unit_count_res = $conn->query("SELECT COUNT(u.unit_id) as total 
                                FROM units u 
                                JOIN properties p ON u.property_id = p.property_id 
                                WHERE p.owner_id = $ivan_id");
$total_units = $unit_count_res->fetch_assoc()['total'];

$revenue_res = $conn->query("SELECT SUM(u.monthly_rent) as total_rev 
                             FROM units u 
                             JOIN properties p ON u.property_id = p.property_id 
                             WHERE p.owner_id = $ivan_id");
$total_potential_revenue = $revenue_res->fetch_assoc()['total_rev'] ?? 0;

$properties = $conn->query("SELECT * FROM properties WHERE owner_id = $ivan_id");
?>

<div class="pagetitle">
    <h1>Dashboard</h1>
    <nav>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Owner</a></li>
            <li class="breadcrumb-item active">Dashboard Overview</li>
        </ol>
    </nav>
</div>

<section class="section dashboard">
    <div class="row">
        <div class="col-lg-12">
            <div class="row">

                <div class="col-xxl-4 col-md-6">
                    <div class="card info-card sales-card">
                        <div class="card-body">
                            <h5 class="card-title">Properties <span>| Total</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-building"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $total_properties ?></h6>
                                    <span class="text-muted small pt-2 ps-1">Registered Assets</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-md-6">
                    <div class="card info-card revenue-card">
                        <div class="card-body">
                            <h5 class="card-title">Units <span>| Total</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-door-open"></i>
                                </div>
                                <div class="ps-3">
                                    <h6><?= $total_units ?></h6>
                                    <span class="text-muted small pt-2 ps-1">Total Rooms/Spaces</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4 col-xl-12">
                    <div class="card info-card customers-card">
                        <div class="card-body">
                            <h5 class="card-title">Potential Revenue <span>| Monthly</span></h5>
                            <div class="d-flex align-items-center">
                                <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="bi bi-currency-dollar fs-4" style="color: #ffbb2c;"></i>
                                </div>
                                <div class="ps-3">
                                    <h6>₱<?= number_format($total_potential_revenue, 2) ?></h6>
                                    <span class="text-muted small pt-2 ps-1">Combined Rent Value</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 mt-3">
                    <div class="card overflow-auto">
                        <div class="card-body pb-0">
                            <h5 class="card-title">My Properties & Units <span>| Detail View</span></h5>
                            
                            <div class="accordion accordion-flush mb-4" id="propertyAccordion">
                                <?php if ($properties->num_rows > 0): ?>
                                    <?php while ($prop = $properties->fetch_assoc()): ?>
                                        <div class="accordion-item mb-2 border rounded">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button collapsed py-3" type="button" data-bs-toggle="collapse" data-bs-target="#prop-<?= $prop['property_id'] ?>">
                                                    <div class="d-flex align-items-center w-100">
                                                        <div class="bg-primary-subtle p-2 rounded-circle me-3">
                                                            <i class="bi bi-house-door text-primary"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <span class="fw-bold text-dark d-block"><?= htmlspecialchars($prop['property_name']) ?></span>
                                                            <small class="text-muted small"><?= $prop['address'] ?></small>
                                                        </div>
                                                        <span class="badge bg-light text-primary border me-3"><?= $prop['type'] ?></span>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="prop-<?= $prop['property_id'] ?>" class="accordion-collapse collapse" data-bs-parent="#propertyAccordion">
                                                <div class="accordion-body bg-light-subtle">
                                                    <table class="table table-borderless align-middle mt-2">
                                                        <thead>
                                                            <tr class="text-muted small uppercase">
                                                                <th scope="col">Unit #</th>
                                                                <th scope="col">Floor</th>
                                                                <th scope="col">Size</th>
                                                                <th scope="col">Rent</th>
                                                                <th scope="col">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            $p_id = $prop['property_id'];
                                                            $units = $conn->query("SELECT * FROM units WHERE property_id = $p_id");
                                                            if ($units->num_rows > 0):
                                                                while ($u = $units->fetch_assoc()): ?>
                                                                    <tr class="bg-white shadow-sm mb-2 rounded">
                                                                        <td class="fw-bold">Unit <?= $u['unit_number'] ?></td>
                                                                        <td><?= $u['floor'] ?></td>
                                                                        <td><?= $u['size'] ?> sqm</td>
                                                                        <td>₱<?= number_format($u['monthly_rent'], 2) ?></td>
                                                                        <td>
                                                                            <span class="badge rounded-pill <?= ($u['status'] == 'active') ? 'bg-success-light text-success' : 'bg-danger-light text-danger' ?>">
                                                                                <?= ($u['status'] == 'active') ? 'Vacant' : 'Occupied' ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                <?php endwhile; 
                                                            else: ?>
                                                                <tr><td colspan="5" class="text-center text-muted py-3 small italic">No units registered for this property.</td></tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">No properties found in your portfolio.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<style>
    /* Mimic NiceAdmin Soft Badges and Backgrounds */
    .bg-success-light { background-color: #e0f8e9; }
    .bg-danger-light { background-color: #ffecdf; }
    .bg-primary-subtle { background-color: #f6f9ff; }
    
    .accordion-button:not(.collapsed) {
        background-color: #f6f9ff;
        color: #4154f1;
    }
    
    .table-borderless tbody tr {
        border-bottom: 8px solid transparent; /* Space between rows */
    }
    
    .card-title span {
        font-size: 14px;
        font-weight: 400;
        color: #899bbd;
    }
</style>