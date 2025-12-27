<?php
// DB connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'register_db';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

// ✅ Function to export users to Excel
function exportToExcel($conn) {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=users_export.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

    echo "<table border='1'>";
    echo "<tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Address</th>
            <th>City</th>
            <th>State</th>
            <th>Zip</th>
            <th>Created At</th>
          </tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['id']}</td>
                <td>{$row['name']}</td>
                <td>{$row['email']}</td>
                <td>{$row['address']}</td>
                <td>{$row['city']}</td>
                <td>{$row['state']}</td>
                <td>{$row['zip']}</td>
                <td>{$row['created_at']}</td>
              </tr>";
    }
    echo "</table>";
    exit(); // stop execution after export
}

// ✅ If export button clicked
if (isset($_GET['action']) && $_GET['action'] == 'export') {
    exportToExcel($conn);
}

// ✅ Otherwise, show the table
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html>
<head>
  <title>Registered Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
</head>
<body>
<div class="container mt-5">
  <h2>Registered Users</h2>
  
  <!-- Export button calls ?action=export -->
  <form method="get" action="general_table.php">
   
  </form>

  <table id="usersTable" class="table table-striped">
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Email</th><th>Address</th>
        <th>City</th><th>State</th><th>Zip</th><th>Created At</th>
      </tr>
    </thead>
    <tbody>
      <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= $row['name'] ?></td>
        <td><?= $row['email'] ?></td>
        <td><?= $row['address'] ?></td>
        <td><?= $row['city'] ?></td>
        <td><?= $row['state'] ?></td>
        <td><?= $row['zip'] ?></td>
        <td><?= $row['created_at'] ?></td>
      </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
  $(document).ready(function() {
    $('#usersTable').DataTable();
  });
</script>
</body>
</html>
<?php $conn->close(); ?>