<?php
require_once "conn.php";

// fetch owners for selects
$owners_res = $conn->query("SELECT user_id, first_name, last_name FROM users WHERE role = 'Owner' ORDER BY last_name ASC");
$owners = [];
while ($ow = $owners_res->fetch_assoc()) {
    $owners[] = $ow;
}

// --- LOGIC HANDLING SECTION ---

// HANDLE ADD PROPERTY
if (isset($_POST['action']) && $_POST['action'] == "add") {
    $owner_id = intval($_POST['owner_id'] ?? 0);
    $stmt = $conn->prepare("INSERT INTO properties(user_id, property_name, type, address, status) VALUES(?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $owner_id, $_POST['property_name'], $_POST['type'], $_POST['address'], $_POST['status']);
    $stmt->execute();
}

// HANDLE EDIT PROPERTY
if (isset($_POST['action']) && $_POST['action'] == "edit") {
    $owner_id = intval($_POST['owner_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE properties SET user_id=?, property_name=?, type=?, address=?, status=? WHERE property_id=?");
    $stmt->bind_param("issssi", $owner_id, $_POST['property_name'], $_POST['type'], $_POST['address'], $_POST['status'], $_POST['property_id']);
    $stmt->execute();
}

// HANDLE DELETE PROPERTY
if (isset($_POST['action']) && $_POST['action'] == "delete") {
    $conn->query("DELETE FROM properties WHERE property_id=" . intval($_POST['property_id']));
}

// HANDLE DELETE ALL UNITS FOR A PROPERTY
if (isset($_POST['action']) && $_POST['action'] == "delete_units") {
    $property_id = intval($_POST['property_id']);
    $stmt = $conn->prepare("DELETE FROM units WHERE property_id = ?");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
}

// HANDLE DELETE SINGLE UNIT
if (isset($_POST['action']) && $_POST['action'] == "delete_unit") {
    $property_id = intval($_POST['property_id']);
    $unit_id = intval($_POST['unit_id']);
    $stmt = $conn->prepare("DELETE FROM units WHERE unit_id = ? AND property_id = ?");
    $stmt->bind_param("ii", $unit_id, $property_id);
    $stmt->execute();
}

// HANDLE ADD UNITS
if (isset($_POST['action']) && $_POST['action'] == "add_unit") {
    $property_id = $_POST['property_id'];
    $mode = $_POST['mode'] ?? '';

    if ($mode == "bulk" && !empty($_POST['number_of_units'])) {
        $num_units = intval($_POST['number_of_units']);
        $floor = $_POST['bulk_floor'] ?? '';
        $size = $_POST['bulk_size'] ?? 0;
        $rent = $_POST['bulk_monthly_rent'] ?? 0;
        $status = $_POST['bulk_status'] ?? 'active';

        for ($i = 1; $i <= $num_units; $i++) {
            $stmt = $conn->prepare("INSERT INTO units(property_id, unit_number, floor, size, monthly_rent, status) VALUES(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdds", $property_id, $i, $floor, $size, $rent, $status);
            $stmt->execute();
        }
    }

    if ($mode == "manual" && !empty($_POST['unit_number'])) {
        foreach ($_POST['unit_number'] as $index => $unit_number) {
            if (trim($unit_number) == "")
                continue;
            $floor = $_POST['floor'][$index] ?? '';
            $size = $_POST['size'][$index] ?? 0;
            $rent = $_POST['monthly_rent'][$index] ?? 0;
            $status = $_POST['status'][$index] ?? 'active';

            $stmt = $conn->prepare("INSERT INTO units(property_id, unit_number, floor, size, monthly_rent, status) VALUES(?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issdds", $property_id, $unit_number, $floor, $size, $rent, $status);
            $stmt->execute();
        }
    }
}

// FETCH PROPERTIES
$result = $conn->query("
    SELECT p.*, u.user_id, CONCAT(u.first_name, ' ', u.last_name) AS owner_fullname
    FROM properties p
    JOIN users u ON p.user_id = u.user_id
    WHERE u.role = 'Owner'
    ORDER BY p.property_id DESC
");
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-secondary">Property & Unit Directory</h4>
        <button class="btn btn-success shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-lg me-1"></i> Add New Property
        </button>
    </div>

    <div class="card shadow">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Property & Unit Directory</h5>
        
    </div>
    <div class="card-body">
        <div class="accordion" id="propAccordion">
            <?php while ($r = $result->fetch_assoc()): ?>
                <div class="accordion-item mb-2 shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapse<?= $r['property_id'] ?>">
                            <div class="d-flex justify-content-between w-100 pe-3">
                                <span>
                                    <strong><?= htmlspecialchars($r['property_name']) ?></strong>
                                    <span class="badge bg-info ms-2"><?= $r['type'] ?></span>
                                </span>
                                <small class="text-muted"><?= htmlspecialchars($r['address']) ?></small>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse<?= $r['property_id'] ?>" class="accordion-collapse collapse"
                        data-bs-parent="#propAccordion">
                        <div class="accordion-body">
                            <div class="row mb-3 align-items-center">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Owner:</strong> <?= $r['owner_fullname'] ?></p>
                                    <p class="mb-1"><strong>Status:</strong> <?= ucfirst($r['status']) ?></p>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?= $r['property_id'] ?>">Edit Property</button>
                                    <button class="btn btn-info btn-sm text-white" data-bs-toggle="modal"
                                        data-bs-target="#addUnitModal<?= $r['property_id'] ?>">+ Add Units</button>
                                    <form method="POST" class="d-inline"
                                        onsubmit="return confirm('Remove all units under this property?')">
                                        <input type="hidden" name="action" value="delete_units">
                                        <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Remove All Units</button>
                                    </form>
                                    <form method="POST" class="d-inline"
                                        onsubmit="return confirm('Delete this property and all its units?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                </div>
                            </div>

                            <h6 class="border-bottom pb-2">Units in this Property</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Unit #</th>
                                            <th>Floor</th>
                                            <th>Size (sqm)</th>
                                            <th>Rent</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $pid = $r['property_id'];
                                        // FIXED QUERY: JOIN with assigned_units to check current occupancy
                                        $u_res = $conn->query("SELECT u.*, au.status AS assignment_status 
                                            FROM units u 
                                            LEFT JOIN assigned_units au 
                                                ON u.unit_id = au.unit_id 
                                                AND au.status IN ('occupied', 'pending downpayment')
                                            WHERE u.property_id = $pid 
                                            ORDER BY u.unit_number ASC
                                        ");

                                        if ($u_res->num_rows > 0):
                                            while ($u = $u_res->fetch_assoc()):
                                                // FIXED LOGIC: If assignment status is active, it is Occupied
                                                $is_occupied = in_array($u['assignment_status'], ['occupied', 'pending downpayment']);
                                                if ($u['assignment_status'] == 'pending downpayment') {
                                                    $status_text = 'Pending Downpayment';
                                                    $status_class = 'bg-warning text-dark';
                                                } elseif ($u['assignment_status'] == 'occupied') {
                                                    $status_text = 'Occupied';
                                                    $status_class = 'bg-secondary';
                                                } else {
                                                    $status_text = 'Vacant';
                                                    $status_class = 'bg-success';
                                                }

                                                ?>
                                                <tr>
                                                    <td><?= $u['unit_number'] ?></td>
                                                    <td><?= $u['floor'] ?></td>
                                                    <td><?= $u['size'] ?></td>
                                                    <td><?= number_format($u['monthly_rent'], 2) ?></td>
                                                    <td>
                                                        <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                                    </td>
                                                    <td>
                                                        <form method="POST" class="d-inline"
                                                            onsubmit="return confirm('Remove this unit?')">
                                                            <input type="hidden" name="action" value="delete_unit">
                                                            <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                                                            <input type="hidden" name="unit_id" value="<?= $u['unit_id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No units added yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="editModal<?= $r['property_id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST" class="modal-content">
                            <div class="modal-header">
                                <h5>Edit Property</h5>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                                <label>Owner</label>
                                <select class="form-control mb-2" name="owner_id" required>
                                    <option value="">-- Choose Owner --</option>
                                    <?php foreach ($owners as $ow): ?>
                                        <option value="<?= $ow['user_id'] ?>" <?= $ow['user_id'] == $r['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($ow['last_name'] . ', ' . $ow['first_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Property Name</label>
                                <input class="form-control mb-2" name="property_name" value="<?= $r['property_name'] ?>"
                                    required>
                                <label>Type</label>
                                <select class="form-control mb-2" name="type" required>
                                    <option value="Condominium" <?= $r['type'] == "Condominium" ? "selected" : "" ?>>
                                        Condominium
                                    </option>
                                    <option value="Boarding House" <?= $r['type'] == "Boarding House" ? "selected" : "" ?>>
                                        Boarding
                                        House</option>
                                    <option value="Rental Home" <?= $r['type'] == "Rental Home" ? "selected" : "" ?>>Rental
                                        Home
                                    </option>
                                </select>
                                <label>Address</label>
                                <input class="form-control mb-2" name="address" value="<?= $r['address'] ?>">
                                <label>Status</label>
                                <select class="form-control mb-2" name="status">
                                    <option value="available" <?= $r['status'] == "available" ? "selected" : "" ?>>Available
                                    </option>
                                    <option value="unavailable" <?= $r['status'] == "unavailable" ? "selected" : "" ?>>
                                        Unavailable
                                    </option>
                                </select>
                            </div>
                            <div class="modal-footer"><button type="submit" class="btn btn-success">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal fade" id="addUnitModal<?= $r['property_id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <form method="POST" class="modal-content">
                            <div class="modal-header">
                                <h5>Add Units for <?= htmlspecialchars($r['property_name']) ?></h5>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="action" value="add_unit">
                                <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                                <input type="hidden" name="mode" id="modeField<?= $r['property_id'] ?>" value="bulk">

                                <ul class="nav nav-tabs mb-3" role="tablist">
                                    <li class="nav-item">
                                        <button type="button" class="nav-link active"
                                            onclick="document.getElementById('modeField<?= $r['property_id'] ?>').value='bulk'"
                                            data-bs-toggle="tab" data-bs-target="#bulk<?= $r['property_id'] ?>">Bulk
                                            Add</button>
                                    </li>
                                    <li class="nav-item">
                                        <button type="button" class="nav-link"
                                            onclick="document.getElementById('modeField<?= $r['property_id'] ?>').value='manual'"
                                            data-bs-toggle="tab" data-bs-target="#manual<?= $r['property_id'] ?>">Manual
                                            Add</button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="bulk<?= $r['property_id'] ?>">
                                        <div class="row g-2">
                                            <div class="col-md-6"><input type="number" name="number_of_units"
                                                    class="form-control" placeholder="How many units?"></div>
                                            <div class="col-md-6"><input type="text" name="bulk_floor" class="form-control"
                                                    placeholder="Floor"></div>
                                            <div class="col-md-4"><input type="number" step="0.01" name="bulk_monthly_rent"
                                                    class="form-control" placeholder="Rent"></div>
                                            <div class="col-md-4"><input type="number" step="0.01" name="bulk_size"
                                                    class="form-control" placeholder="Size (sqm)"></div>
                                            <div class="col-md-4">
                                                <select name="bulk_status" class="form-control">
                                                    <option value="active">Active</option>
                                                    <option value="inactive">Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="manual<?= $r['property_id'] ?>">
                                        <div id="manualContainer<?= $r['property_id'] ?>">
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-3"><input name="unit_number[]" class="form-control"
                                                        placeholder="Unit #"></div>
                                                <div class="col-md-3"><input name="floor[]" class="form-control"
                                                        placeholder="Floor"></div>
                                                <div class="col-md-3"><input name="monthly_rent[]" class="form-control"
                                                        placeholder="Rent"></div>
                                                <div class="col-md-3"><button type="button"
                                                        class="btn btn-outline-primary btn-sm w-100"
                                                        onclick="addRow(<?= $r['property_id'] ?>)">+ Row</button></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Units</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5>Add New Property</h5>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="add">
                <label>Owner</label>
                <select class="form-control mb-2" name="owner_id" required>
                    <option value="">-- Choose Owner --</option>
                    <?php foreach ($owners as $ow): ?>
                        <option value="<?= $ow['user_id'] ?>"><?= htmlspecialchars($ow['last_name'] . ', ' . $ow['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mb-2" name="property_name" placeholder="Property Name" required>
                <select class="form-control mb-2" name="type" required>
                    <option value="Condominium">Condominium</option>
                    <option value="Boarding House">Boarding House</option>
                    <option value="Rental Home">Rental Home</option>
                </select>
                <input class="form-control mb-2" name="address" placeholder="Address">
                <select class="form-control mb-2" name="status" required>
                    <option value="available">Available</option>
                    <option value="unavailable">Unavailable</option>
                </select>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success">Add Property</button></div>
        </form>
    </div>
</div>

<script>
    function addRow(id) {
        const container = document.getElementById('manualContainer' + id);
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2';
        div.innerHTML = `
        <div class="col-md-3"><input name="unit_number[]" class="form-control" placeholder="Unit #"></div>
        <div class="col-md-3"><input name="floor[]" class="form-control" placeholder="Floor"></div>
        <div class="col-md-3"><input name="monthly_rent[]" class="form-control" placeholder="Rent"></div>
        <div class="col-md-3"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.parentElement.parentElement.remove()">Remove</button></div>
    `;
        container.appendChild(div);
    }
</script>
