<?php
require_once "conn.php";

// Handle Add/Edit/Delete
if ($_SERVER["REQUEST_METHOD"]=="POST") {
  if ($_POST['action']=="add") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    $exists = false;
    $dup = $conn->prepare("SELECT 1 FROM tenant WHERE username = ? OR email = ? LIMIT 1");
    $dup->bind_param("ss", $username, $email);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) $exists = true;
    $dup->close();

    if (!$exists) {
      $dup_users = $conn->prepare("SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1");
      $dup_users->bind_param("ss", $username, $email);
      $dup_users->execute();
      $dup_users->store_result();
      if ($dup_users->num_rows > 0) $exists = true;
      $dup_users->close();
    }

    if ($exists) {
      echo "<script>alert('Username or email already exists.');</script>";
    } else {
      $hashed_password = password_hash($_POST['password'],PASSWORD_DEFAULT);
      $stmt=$conn->prepare("INSERT INTO tenant(firstname,lastname,middlename,username,password,email,contact_no) VALUES(?,?,?,?,?,?,?)");
      $stmt->bind_param("sssssss",$_POST['firstname'],$_POST['lastname'],$_POST['middlename'],$username,$hashed_password,$email,$_POST['contact_no']);
      $stmt->execute();
    }
  }
  if ($_POST['action']=="edit") {
    $stmt=$conn->prepare("UPDATE tenant SET firstname=?,lastname=?,middlename=?,username=?,email=?,contact_no=? WHERE tenant_id=?");
    $stmt->bind_param("ssssssi",$_POST['firstname'],$_POST['lastname'],$_POST['middlename'],$_POST['username'],$_POST['email'],$_POST['contact_no'],$_POST['tenant_id']);
    $stmt->execute();
  }
  if ($_POST['action']=="delete") $conn->query("DELETE FROM tenant WHERE tenant_id=".$_POST['tenant_id']);
}

$result=$conn->query("SELECT * FROM tenant ORDER BY tenant_id DESC");
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-secondary">Tenant Directory</h4>
    <div>
      <button class="btn btn-success shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#regTenantModal">
        <i class="bi bi-person-plus-fill me-1"></i> Register New Tenant
      </button>
    </div>
  </div>
</div>


<div class="card mt-4">
  <div class="card-header bg-primary text-white d-flex justify-content-between">
    <h5>Tenant Records</h5>
    
  </div>
  <div class="card-body table-responsive">
    <table class="table table-striped">
      <thead><tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
      <tbody>
        <?php while($r=$result->fetch_assoc()): ?>
        <tr>
          <td><?= $r['tenant_id'] ?></td>
          <td><?= $r['firstname']." ".$r['lastname'] ?></td>
          <td><?= $r['username'] ?></td>
          <td><?= $r['email'] ?></td>
          <td><?= $r['contact_no'] ?></td>
          <td>
            <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $r['tenant_id'] ?>">Edit</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="tenant_id" value="<?= $r['tenant_id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>

        <!-- Edit Modal -->
        <div class="modal fade" id="editModal<?= $r['tenant_id'] ?>">
          <div class="modal-dialog"><div class="modal-content">
            <form method="POST">
              <div class="modal-header"><h5>Edit Tenant</h5></div>
              <div class="modal-body">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="tenant_id" value="<?= $r['tenant_id'] ?>">
                <input class="form-control mb-2" name="firstname" value="<?= $r['firstname'] ?>">
                <input class="form-control mb-2" name="lastname" value="<?= $r['lastname'] ?>">
                <input class="form-control mb-2" name="middlename" value="<?= $r['middlename'] ?>">
                <input class="form-control mb-2" name="username" value="<?= $r['username'] ?>">
                <input class="form-control mb-2" name="email" value="<?= $r['email'] ?>">
                <input class="form-control mb-2" name="contact_no" value="<?= $r['contact_no'] ?>">
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

<!-- Register Tenant Modal (copied from rent_onboarding) -->
<div class="modal fade" id="regTenantModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="POST" class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-primary text-white border-0">
        <h5 class="fw-bold mb-0">New Tenant Registration</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="hidden" name="action" value="add">
        <div class="row g-3">
          <div class="col-md-4"><label class="small fw-bold">First Name</label><input type="text" name="firstname" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Last Name</label><input type="text" name="lastname" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Middle Name</label><input type="text" name="middlename" class="form-control bg-light border-1.5"></div>
          <div class="col-md-6"><label class="small fw-bold">Username</label><input type="text" name="username" class="form-control bg-light border-1.5" required></div>
           <div class="col-md-6">
            <label class="small fw-bold">Password</label>
            <input type="password" name="password" class="form-control bg-light border-1.5" required
              pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
              title="Must contain at least 8 characters, including one uppercase letter, one lowercase letter, one number, and one special character.">
          </div>
          <div class="col-md-8"><label class="small fw-bold">Email</label><input type="email" name="email" class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Contact No</label><input type="text" name="contact_no" class="form-control bg-light border-1.5"></div>
        </div>
      </div>
      <div class="modal-footer border-0"><button type="submit" class="btn btn-success px-4 rounded-pill">Register Tenant</button></div>
    </form>
  </div>
</div>

<!-- Register Owner Modal -->
<!-- owner registration removed from tenant page - moved to tbl_owner.php -->

<?php $conn->close(); ?>
