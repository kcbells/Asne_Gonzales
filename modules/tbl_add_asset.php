<?php
require_once "conn.php";

// Handle Add
if (isset($_POST['action']) && $_POST['action'] == "add") {
  $stmt = $conn->prepare("INSERT INTO properties(owner_id, property_name, type, address, status) VALUES(1, ?, ?, ?, ?)");
  $stmt->bind_param("ssss", $_POST['property_name'], $_POST['type'], $_POST['address'], $_POST['status']);
  $stmt->execute();
}
//Edit
if (isset($_POST['action']) && $_POST['action'] == "edit") {
  $stmt = $conn->prepare("UPDATE properties 
                          SET owner_id=1, property_name=?, type=?, address=?, status=? 
                          WHERE property_id=?");
  $stmt->bind_param("ssssi", $_POST['property_name'], $_POST['type'], $_POST['address'], $_POST['status'], $_POST['property_id']);
  $stmt->execute();
}
//Delete
if (isset($_POST['action']) && $_POST['action'] == "delete") {
  $conn->query("DELETE FROM properties WHERE property_id=" . $_POST['property_id']);
}
// Add Units
if (isset($_POST['action']) && $_POST['action'] == "add_unit") {
  $property_id = $_POST['property_id'];
  $mode = $_POST['mode'] ?? '';

  // Bulk insert
  if ($mode == "bulk" && !empty($_POST['number_of_units'])) {
    $num_units = intval($_POST['number_of_units']);
    $floor = $_POST['bulk_floor'] ?? '';
    $size = $_POST['bulk_size'] ?? 0;
    $rent = $_POST['bulk_monthly_rent'] ?? 0;
    $downpayment = $_POST['bulk_downpayment'] ?? 0;
    $status = $_POST['bulk_status'] ?? 'inactive';

    for ($i = 1; $i <= $num_units; $i++) {
      $unit_number = $i;
      $stmt = $conn->prepare("INSERT INTO units(property_id, unit_number, floor, size, monthly_rent, downpayment, status) 
                                    VALUES(?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("issddds", $property_id, $unit_number, $floor, $size, $rent, $downpayment, $status);
      $stmt->execute();
    }
  }
  // Manual insert
  if ($mode == "manual" && !empty($_POST['unit_number'])) {
    foreach ($_POST['unit_number'] as $index => $unit_number) {
      if (trim($unit_number) == "")
        continue; // skip empty blocks

      $floor = $_POST['floor'][$index] ?? '';
      $size = $_POST['size'][$index] ?? 0;
      $rent = $_POST['monthly_rent'][$index] ?? 0;
      $downpayment = $_POST['downpayment'][$index] ?? 0;
      $status = $_POST['status'][$index] ?? 'inactive';

      $stmt = $conn->prepare("INSERT INTO units(property_id, unit_number, floor, size, monthly_rent, downpayment, status) 
                                    VALUES(?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("issddds", $property_id, $unit_number, $floor, $size, $rent, $downpayment, $status);
      $stmt->execute();
    }
  }
}

$result = $conn->query("
  SELECT p.property_id, 
         p.property_name, 
         p.type, 
         p.address, 
         p.status, 
         p.created_at,
         p.updated_at,
         o.owner_id,
         CONCAT(o.firstname, ' ', o.lastname, ' ', o.middlename) AS owner_fullname
  FROM properties p
  JOIN owner o ON p.owner_id = o.owner_id
  ORDER BY p.property_id DESC
");


?>

<div class="card mt-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between">
    <h5>Properties</h5>
    <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">+ Add Property</button>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Owner</th>
          <th>Property Name</th>
          <th>Type</th>
          <th>Address</th>
          <th>Status</th>
          <th>Date Added</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $result->fetch_assoc()): ?>
          <tr>
            <td><?= $r['property_id'] ?></td>
            <td><?= $r['owner_fullname'] ?></td>
            <td><?= $r['property_name'] ?></td>
            <td><?= $r['type'] ?></td>
            <td><?= $r['address'] ?></td>
            <td><?= $r['status'] ?></td>
            <td><?= $r['created_at'] ?></td>
            <td>
              <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                data-bs-target="#editModal<?= $r['property_id'] ?>">Edit</button>
              <button class="btn btn-info btn-sm" data-bs-toggle="modal"
                data-bs-target="#addUnitModal<?= $r['property_id'] ?>">Add Unit</button>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="editModal<?= $r['property_id'] ?>">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST">
                  <div class="modal-header">
                    <h5>Edit Property</h5>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                    <input class="form-control mb-2" name="property_name" value="<?= $r['property_name'] ?>">
                    <select class="form-control mb-2" name="type" required>
                      <option value="Condominium" <?= $r['type'] == "Condominium" ? "selected" : "" ?>>Condominium</option>
                      <option value="Boarding House" <?= $r['type'] == "Boarding House" ? "selected" : "" ?>>Boarding House
                      </option>
                      <option value="Rental Home" <?= $r['type'] == "Rental Home" ? "selected" : "" ?>>Rental Home</option>
                    </select>
                    <input class="form-control mb-2" name="address" value="<?= $r['address'] ?>">
                    <select class="form-control mb-2" name="status" required>
                      <option value="available" <?= $r['status'] == "available" ? "selected" : "" ?>>available</option>
                      <option value="unavailable" <?= $r['status'] == "unavailable" ? "selected" : "" ?>>unavailable</option>
                      <option value="under maintenance" <?= $r['status'] == "under maintenance" ? "selected" : "" ?>>under
                        maintenance</option>
                      <option value="renovation" <?= $r['status'] == "renovation" ? "selected" : "" ?>>renovation</option>
                    </select>
                    <p class="text-muted">Last Updated: <?= date("M d, Y h:i A", strtotime($r['updated_at'])) ?></p>
                  </div>
                  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
                </form>
              </div>
            </div>
          </div>

          <!-- Add Unit Modal -->
          <div class="modal fade" id="addUnitModal<?= $r['property_id'] ?>">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <form method="POST" id="unitForm">
                  <div class="modal-header">
                    <h5>Add Units for <?= $r['property_name'] ?></h5>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="action" value="add_unit">
                    <input type="hidden" name="property_id" value="<?= $r['property_id'] ?>">
                    <input type="hidden" name="mode" id="modeField" value="">

                    <div class="row">
                      <!-- LEFT SIDE: Bulk Automatic Entry -->
                      <div class="col-md-6 border-end">
                        <h6>Bulk Add Units</h6>
                        <div class="mb-2">
                          <input type="number" class="form-control" name="number_of_units" placeholder="Number of Units">
                        </div>
                        <div class="mb-2">
                          <input type="text" class="form-control" name="bulk_floor" placeholder="Floor">
                        </div>
                        <div class="mb-2">
                          <input type="number" step="0.01" class="form-control" name="bulk_size" placeholder="Size (sqm)">
                        </div>
                        <div class="mb-2">
                          <input type="number" step="0.01" class="form-control" name="bulk_monthly_rent"
                            placeholder="Monthly Rent">
                        </div>
                        <div class="mb-2">
                          <input type="number" step="0.01" class="form-control" name="bulk_downpayment"
                            placeholder="Downpayment">
                        </div>
                        <div class="mb-2">
                          <label>Status</label>
                          <select class="form-control" name="bulk_status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                          </select>
                        </div>
                        <button type="submit" class="btn btn-success" onclick="setMode('bulk')">Save Bulk Units</button>
                      </div>

                      <!-- RIGHT SIDE: Manual Entry -->
                      <div class="col-md-6">
                        <h6>Manual Add Units</h6>
                        <div id="manualUnits">
                          <!-- First unit form block -->
                          <div class="unit-block border p-2 mb-2">
                            <input class="form-control mb-2" name="unit_number[]" placeholder="Unit Number">
                            <input class="form-control mb-2" name="floor[]" placeholder="Floor">
                            <input class="form-control mb-2" name="size[]" placeholder="Size (sqm)">
                            <input class="form-control mb-2" name="monthly_rent[]" placeholder="Monthly Rent">
                            <input class="form-control mb-2" name="downpayment[]" placeholder="Downpayment">
                            <select class="form-control mb-2" name="status[]">
                              <option value="active">Active</option>
                              <option value="inactive">Inactive</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-danger"
                              onclick="removeUnitBlock(this)">Remove</button>
                          </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addUnitBlock()">+ Add Another
                          Unit</button>
                        <button type="submit" class="btn btn-success mt-2" onclick="setMode('manual')">Save Manual
                          Units</button>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <script>
            function setMode(mode) {
              document.getElementById('modeField').value = mode;
            }

            function addUnitBlock() {
              const container = document.getElementById('manualUnits');
              const block = document.createElement('div');
              block.className = 'unit-block border p-2 mb-2';
              block.innerHTML = `
                <input class="form-control mb-2" name="unit_number[]" placeholder="Unit Number">
                <input class="form-control mb-2" name="floor[]" placeholder="Floor">
                <input class="form-control mb-2" name="size[]" placeholder="Size (sqm)">
                <input class="form-control mb-2" name="monthly_rent[]" placeholder="Monthly Rent">
                <input class="form-control mb-2" name="downpayment[]" placeholder="Downpayment">
                <select class="form-control mb-2" name="status[]">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeUnitBlock(this)">Remove</button>
              `;
              container.appendChild(block);
            }

            function removeUnitBlock(button) {
              const block = button.parentElement;
              block.remove();
            }
          </script>

        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- Add Modal -->
<div class="modal fade" id="addModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5>Add Property</h5>
        </div>
        <input type="hidden" name="action" value="add">
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
        <div class="modal-footer"><button class="btn btn-success">Add</button></div>
      </form>

    </div>
  </div>
</div>

<?php $conn->close(); ?>