<?php
require_once "conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $firstname = $_POST['firstname'];
  $lastname = $_POST['lastname'];
  $middlename = $_POST['middlename'];
  $username = $_POST['username'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // secure hash
  $email = $_POST['email'];
  $contact_no = $_POST['contact_no'];

  $stmt = $conn->prepare("INSERT INTO tenant 
        (firstname, lastname, middlename, username, password, email, contact_no) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("sssssss", $firstname, $lastname, $middlename, $username, $password, $email, $contact_no);

  if ($stmt->execute()) {
    echo "<div class='alert alert-success'>Tenant registered successfully!</div>";
  } else {
    echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
  }
}
?>

<div class="card">
  <div class="card-header bg-primary text-white">
    <h5>Tenant Registration</h5>
  </div>
  <div class="card-body">
    <form method="POST" action="">
      <div class="row mb-3">
        <div class="col-md-4">
          <label class="form-label">First Name</label>
          <input type="text" name="firstname" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Last Name</label>
          <input type="text" name="lastname" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Middle Name</label>
          <input type="text" name="middlename" class="form-control">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Contact No</label>
        <input type="text" name="contact_no" class="form-control">
      </div>

      <button type="submit" class="btn btn-success">Register</button>
    </form>
  </div>
</div>