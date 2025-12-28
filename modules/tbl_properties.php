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
                      <option value="under maintenance" <?= $r['status'] == "under maintenance" ? "selected" : "" ?>>under maintenance</option>
                      <option value="renovation" <?= $r['status'] == "renovation" ? "selected" : "" ?>>renovation</option>
                    </select>
                    <p class="text-muted">Last Updated: <?= date("M d, Y h:i A", strtotime($r['updated_at'])) ?></p>
                  </div>
                  <div class="modal-footer"><button class="btn btn-success">Save</button></div>
                </form>
              </div>
            </div>
          </div>
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