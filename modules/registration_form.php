<?php
// Enable detailed error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// DB connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'register_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

// Check if form submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize inputs
    $name     = $conn->real_escape_string($_POST['name']);
    $email    = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // hash password
    $address  = $conn->real_escape_string($_POST['address']);
    $city     = $conn->real_escape_string($_POST['city']);
    $state    = $conn->real_escape_string($_POST['state']);
    $zip      = $conn->real_escape_string($_POST['zip']);

    // Insert into database
    $sql = "INSERT INTO users (name, email, password, address, city, state, zip) 
            VALUES ('$name', '$email', '$password', '$address', '$city', '$state', '$zip')";

    if ($conn->query($sql) === TRUE) {
        echo "<div class='alert alert-success'>Registration successful!</div>";
    } else {
        echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
}
$conn->close();
?>

<div class="card">
  <div class="card-body">
    <h5 class="card-title">Floating Labels Form</h5>
    <!-- Floating Labels Form -->
    <form class="row g-3" method="POST" action="">
      <div class="col-md-12">
        <div class="form-floating">
          <input type="text" class="form-control" id="floatingName" name="name" placeholder="Your Name" required>
          <label for="floatingName">Your Name</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating">
          <input type="email" class="form-control" id="floatingEmail" name="email" placeholder="Your Email" required>
          <label for="floatingEmail">Your Email</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating">
          <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" required>
          <label for="floatingPassword">Password</label>
        </div>
      </div>

      <div class="col-12">
        <div class="form-floating">
          <textarea class="form-control" id="floatingTextarea" name="address" placeholder="Address" style="height: 100px;"></textarea>
          <label for="floatingTextarea">Address</label>
        </div>
      </div>

      <div class="col-md-6">
        <div class="form-floating">
          <input type="text" class="form-control" id="floatingCity" name="city" placeholder="City">
          <label for="floatingCity">City</label>
        </div>
      </div>

      <div class="col-md-4">
        <div class="form-floating mb-3">
          <select class="form-select" id="floatingSelect" name="state" aria-label="State">
            <option value="">Select State</option>
            <option value="Philippines">Philippines</option>
            <option value="Thailand">Thailand</option>
            <option value="South Korea">South Korea</option>
          </select>
          <label for="floatingSelect">State</label>
        </div>
      </div>

      <div class="col-md-2">
        <div class="form-floating">
          <input type="text" class="form-control" id="floatingZip" name="zip" placeholder="Zip">
          <label for="floatingZip">Zip</label>
        </div>
      </div>

      <div class="text-center">
        <button type="submit" class="btn btn-primary">Submit</button>
        <button type="reset" class="btn btn-secondary">Reset</button>
      </div>
    </form>
    <!-- End floating Labels Form -->
  </div>
</div>