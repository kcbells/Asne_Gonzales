<?php
require_once "conn.php";

// Handle owner edit and delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
  if ($_POST['action'] == 'edit_owner') {
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, middle_name=?, username=?, email=? WHERE user_id = ? AND role = 'Owner'");
    $stmt->bind_param("sssssi", $_POST['firstname'], $_POST['lastname'], $_POST['middlename'], $_POST['username'], $_POST['email'], $_POST['user_id']);
    $stmt->execute();
  }
  if ($_POST['action'] == 'delete_owner') {
    $id = intval($_POST['user_id']);
    // prevent deleting if there are properties owned? For now, delete directly
    $conn->query("DELETE FROM users WHERE user_id = $id AND role = 'Owner'");
  }
  if ($_POST['action'] == 'add_owner') {
    $password = $_POST['password'];
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    $exists = false;
    $dup_users = $conn->prepare("SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1");
    $dup_users->bind_param("ss", $username, $email);
    $dup_users->execute();
    $dup_users->store_result();
    if ($dup_users->num_rows > 0) $exists = true;
    $dup_users->close();

    if (!$exists) {
      $dup_tenant = $conn->prepare("SELECT 1 FROM tenant WHERE username = ? OR email = ? LIMIT 1");
      $dup_tenant->bind_param("ss", $username, $email);
      $dup_tenant->execute();
      $dup_tenant->store_result();
      if ($dup_tenant->num_rows > 0) $exists = true;
      $dup_tenant->close();
    }

    // Regex for: Min 8 chars, 1 Uppercase, 1 Lowercase, 1 Number, 1 Special Char
    $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

    if ($exists) {
      echo "<script>alert('Username or email already exists.');</script>";
    } elseif (preg_match($regex, $password)) {
      // Securely hash the password
      $hashed_password = password_hash($password, PASSWORD_DEFAULT);

      $stmt = $conn->prepare("INSERT INTO users (username, first_name, middle_name, last_name, role, email, password_hash, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'Owner', ?, ?, 'active', NOW(), NOW())");
      $stmt->bind_param("ssssss", $username, $_POST['firstname'], $_POST['middlename'], $_POST['lastname'], $email, $hashed_password);
      $stmt->execute();
      echo "<script>alert('Owner registered successfully!');</script>";
    } else {
      echo "<script>alert('Error: Password does not meet security requirements.');</script>";
    }
  }
}

// Fetch owners
$owners = $conn->query("SELECT * FROM users WHERE role = 'Owner' ORDER BY user_id DESC");
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
    </div>
    <div></div>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-striped">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($r = $owners->fetch_assoc()): ?>
          <tr>
            <td><?= $r['user_id'] ?></td>
            <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td><?= htmlspecialchars($r['email']) ?></td>
            <td>
              <button class="btn btn-warning btn-sm" data-bs-toggle="modal"
                data-bs-target="#editOwnerModal<?= $r['user_id'] ?>">Edit</button>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this owner?')">
                <input type="hidden" name="action" value="delete_owner">
                <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>

          <!-- Edit Owner Modal -->
          <div class="modal fade" id="editOwnerModal<?= $r['user_id'] ?>">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST">
                  <div class="modal-header">
                    <h5>Edit Owner</h5>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" name="action" value="edit_owner">
                    <input type="hidden" name="user_id" value="<?= $r['user_id'] ?>">
                    <input class="form-control mb-2" name="firstname" value="<?= htmlspecialchars($r['first_name']) ?>"
                      required>
                    <input class="form-control mb-2" name="lastname" value="<?= htmlspecialchars($r['last_name']) ?>"
                      required>
                    <input class="form-control mb-2" name="middlename" value="<?= htmlspecialchars($r['middle_name']) ?>">
                    <input class="form-control mb-2" name="username" value="<?= htmlspecialchars($r['username']) ?>"
                      required>
                    <input class="form-control mb-2" name="email" value="<?= htmlspecialchars($r['email']) ?>">
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
          <div class="col-md-4"><label class="small fw-bold">First Name</label><input type="text" name="firstname"
              class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Last Name</label><input type="text" name="lastname"
              class="form-control bg-light border-1.5" required></div>
          <div class="col-md-4"><label class="small fw-bold">Middle Name</label><input type="text" name="middlename"
              class="form-control bg-light border-1.5"></div>
          <div class="col-md-6"><label class="small fw-bold">Username</label><input type="text" name="username"
              class="form-control bg-light border-1.5" required></div>
          <div class="col-md-6">
            <label class="small fw-bold">Password</label>
            <input type="password" name="password" class="form-control bg-light border-1.5" required
              pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
              title="Must contain at least 8 characters, including one uppercase letter, one lowercase letter, one number, and one special character.">
          </div>
          <div class="col-md-8"><label class="small fw-bold">Email</label><input type="email" name="email"
              class="form-control bg-light border-1.5"></div>
        </div>
      </div>
      <div class="modal-footer border-0"><button type="submit" class="btn btn-success px-4 rounded-pill">Register
          Owner</button></div>
    </form>
  </div>
</div>
