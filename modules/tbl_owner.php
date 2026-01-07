<?php
require_once "conn.php";

// Handle owner edit and delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
  if ($_POST['action'] == 'edit_owner') {
    $stmt = $conn->prepare("UPDATE owner SET firstname=?, lastname=?, middlename=?, username=?, email=?, contact_no=? WHERE owner_id = ?");
    $stmt->bind_param("ssssssi", $_POST['firstname'], $_POST['lastname'], $_POST['middlename'], $_POST['username'], $_POST['email'], $_POST['contact_no'], $_POST['owner_id']);
    $stmt->execute();
  }
  if ($_POST['action'] == 'delete_owner') {
    $id = intval($_POST['owner_id']);
    // prevent deleting if there are properties owned? For now, delete directly
    $conn->query("DELETE FROM owner WHERE owner_id = $id");
  }
  if ($_POST['action'] == 'add_owner') {
    $stmt = $conn->prepare("INSERT INTO owner(firstname,lastname,middlename,username,password,email,contact_no) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $_POST['firstname'], $_POST['lastname'], $_POST['middlename'], $_POST['username'], $_POST['password'], $_POST['email'], $_POST['contact_no']);
    $stmt->execute();
  }
}

// Fetch owners
$owners = $conn->query("SELECT * FROM owner ORDER BY owner_id DESC");
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-secondary">Owner Directory</h4>
    <button class="btn btn-success shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#regOwnerModal">
      <i class="bi bi-person-plus-fill me-1"></i> Register New Owner
    </button>
  </div>
</div>

<div class="card mt-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
    <div>
      <h5 class="mb-0">Owner Records</h5>
      <div class="small text-white-50">Manage property owners (edit only)</div>
    </div>
    <div></div>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while($r = $owners->fetch_assoc()): ?>
        <tr>
          <td><?= $r['owner_id'] ?></td>
          <td><?= htmlspecialchars($r['firstname'].' '.$r['lastname']) ?></td>
          <td><?= htmlspecialchars($r['username']) ?></td>
          <td><?= htmlspecialchars($r['email']) ?></td>
          <td><?= htmlspecialchars($r['contact_no']) ?></td>
          <td>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editOwnerModal<?= $r['owner_id'] ?>">Edit</button>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete this owner?')">
              <input type="hidden" name="action" value="delete_owner">
              <input type="hidden" name="owner_id" value="<?= $r['owner_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>

        <!-- Edit Owner Modal -->
        <div class="modal fade" id="editOwnerModal<?= $r['owner_id'] ?>">
          <div class="modal-dialog"><div class="modal-content">
            <form method="POST">
              <div class="modal-header"><h5>Edit Owner</h5></div>
              <div class="modal-body">
                <input type="hidden" name="action" value="edit_owner">
                <input type="hidden" name="owner_id" value="<?= $r['owner_id'] ?>">
                <input class="form-control mb-2" name="firstname" value="<?= htmlspecialchars($r['firstname']) ?>" required>
                <input class="form-control mb-2" name="lastname" value="<?= htmlspecialchars($r['lastname']) ?>" required>
                <input class="form-control mb-2" name="middlename" value="<?= htmlspecialchars($r['middlename']) ?>">
                <input class="form-control mb-2" name="username" value="<?= htmlspecialchars($r['username']) ?>" required>
                <input class="form-control mb-2" name="email" value="<?= htmlspecialchars($r['email']) ?>">
                <input class="form-control mb-2" name="contact_no" value="<?= htmlspecialchars($r['contact_no']) ?>">
              </div>
              <div class="modal-footer"><button class="btn btn-success">Save</button></div>
            </form>
          </div></div>
        </div>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<?php $conn->close(); ?>

<!-- Register Owner Modal -->
<div class="modal fade" id="regOwnerModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="POST" class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="fw-bold mb-0">Register New Owner</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action" value="add_owner">
        <div class="row g-3">
          <div class="col-md-4"><label class="small fw-bold">First Name</label><input type="text" name="firstname" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Last Name</label><input type="text" name="lastname" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Middle Name</label><input type="text" name="middlename" class="form-control bg-light border-1.5"></div>
          <div class="col-md-6"><label class="small fw-bold">Username</label><input type="text" name="username" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-6"><label class="small fw-bold">Password</label><input type="text" name="password" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-8"><label class="small fw-bold">Email</label><input type="email" name="email" class="form-control bg-light border-1.5"></div>
          <div class="col-md-4"><label class="small fw-bold">Contact No</label><input type="text" name="contact_no" class="form-control bg-light border-1.5"></div>
        </div>
      </div>
      <div class="modal-footer border-0"><button type="submit" class="btn btn-success px-4 rounded-pill">Register Owner</button></div>
    </form>
  </div>
</div>
