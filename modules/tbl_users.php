<?php
require_once "conn.php";

// Handle Add/Edit/Delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
      $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
      $stmt = $conn->prepare("INSERT INTO users(username,first_name,middle_name,last_name,role,email,password_hash,status) VALUES(?,?,?,?,?,?,?,?)");
      $stmt->bind_param("ssssssss", $_POST['username'], $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $_POST['role'], $_POST['email'], $password_hash, $_POST['status']);
      $stmt->execute();
    }

    if ($_POST['action'] === 'edit') {
      $user_id = intval($_POST['user_id']);
      $username = $_POST['username'];
      $first = $_POST['first_name'];
      $middle = $_POST['middle_name'];
      $last = $_POST['last_name'];
      $role = $_POST['role'];
      $email = $_POST['email'];
      $status = $_POST['status'];

      if (!empty($_POST['password'])) {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username=?, first_name=?, middle_name=?, last_name=?, role=?, email=?, password_hash=?, status=? WHERE user_id=?");
        $stmt->bind_param("ssssssssi", $username, $first, $middle, $last, $role, $email, $password_hash, $status, $user_id);
        $stmt->execute();
      } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, first_name=?, middle_name=?, last_name=?, role=?, email=?, status=? WHERE user_id=?");
        $stmt->bind_param("sssssssi", $username, $first, $middle, $last, $role, $email, $status, $user_id);
        $stmt->execute();
      }
    }

    if ($_POST['action'] === 'delete') {
        $id = intval($_POST['user_id']);
        $conn->query("DELETE FROM users WHERE user_id = $id");
    }
}

$result = $conn->query("SELECT * FROM users ORDER BY user_id DESC");
?>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold text-secondary">User Accounts</h4>
    <button class="btn btn-success shadow-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#addUserModal">
      <i class="bi bi-person-plus-fill me-1"></i> Add New User
    </button>
  </div>

  <div class="card mt-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Users</h5>
      <small class="text-white-50">Manage application users</small>
    </div>
    <div class="card-body table-responsive">
      <table class="table table-striped">
        <thead>
          <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($r = $result->fetch_assoc()): ?>
            <tr>
              <td><?= $r['user_id'] ?></td>
              <td><?= htmlspecialchars($r['username']) ?></td>
              <td><?= htmlspecialchars($r['first_name'].' '.($r['middle_name']? $r['middle_name'].' ':'').$r['last_name']) ?></td>
              <td><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['role']) ?></td>
              <td><?= htmlspecialchars($r['status']) ?></td>
              <td><?= htmlspecialchars($r['created_at']) ?></td>
              <td>
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $r['user_id'] ?>">Edit</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this user?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
              </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editUserModal<?= $r['user_id'] ?>">
              <div class="modal-dialog"><div class="modal-content">
                <form method="POST">
                  <div class="modal-header"><h5>Edit User</h5></div>
                  <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                    <input class="form-control mb-2" name="username" value="<?= htmlspecialchars($r['username']) ?>" required>
                    <input class="form-control mb-2" name="first_name" value="<?= htmlspecialchars($r['first_name']) ?>" required>
                    <input class="form-control mb-2" name="middle_name" value="<?= htmlspecialchars($r['middle_name']) ?>">
                    <input class="form-control mb-2" name="last_name" value="<?= htmlspecialchars($r['last_name']) ?>" required>
                    <input class="form-control mb-2" name="role" value="<?= htmlspecialchars($r['role']) ?>" required>
                    <input class="form-control mb-2" name="email" value="<?= htmlspecialchars($r['email']) ?>" required>
                    <input type="password" class="form-control mb-2" name="password" placeholder="Leave blank to keep existing password">
                    <select name="status" class="form-select mb-2">
                      <option value="active" <?= $r['status']==='active' ? 'selected' : '' ?>>Active</option>
                      <option value="inactive" <?= $r['status']==='inactive' ? 'selected' : '' ?>>Inactive</option>
                      <option value="banned" <?= $r['status']==='banned' ? 'selected' : '' ?>>Banned</option>
                    </select>
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

  <!-- Add User Modal -->
  <div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <form method="POST" class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-primary text-white border-0"><h5 class="fw-bold mb-0">Add New User</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body p-4">
          <input type="hidden" name="action" value="add">
          <div class="row g-3">
            <div class="col-md-4"><label class="small fw-bold">Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="col-md-4"><label class="small fw-bold">First Name</label><input type="text" name="first_name" class="form-control" required></div>
            <div class="col-md-4"><label class="small fw-bold">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
            <div class="col-md-4"><label class="small fw-bold">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
            <div class="col-md-4"><label class="small fw-bold">Role</label><input type="text" name="role" class="form-control" required></div>
            <div class="col-md-4"><label class="small fw-bold">Email</label><input type="email" name="email" class="form-control" required></div>
            <div class="col-md-4"><label class="small fw-bold">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-4"><label class="small fw-bold">Status</label>
              <select name="status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="banned">Banned</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-0"><button type="submit" class="btn btn-success px-4 rounded-pill">Create User</button></div>
      </form>
    </div>
  </div>
</div>

<?php $conn->close(); ?>
