<?php
require_once "conn.php";

// Handle Add/Edit/Delete
if ($_SERVER["REQUEST_METHOD"]=="POST") {
  if ($_POST['action']=="add") {
    $stmt=$conn->prepare("INSERT INTO tenant(firstname,lastname,middlename,username,password,email,contact_no) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss",$_POST['firstname'],$_POST['lastname'],$_POST['middlename'],$_POST['username'],password_hash($_POST['password'],PASSWORD_DEFAULT),$_POST['email'],$_POST['contact_no']);
    $stmt->execute();
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


<?php $conn->close(); ?>