<?php
// Display a simple system logs table
include_once __DIR__ . '/../conn.php';

// Fetch logs
$logs = [];
$error = '';
try {
	$sql = "SELECT * FROM `system_logs` ORDER BY `datetime` DESC LIMIT 500";
	$res = $conn->query($sql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$logs[] = $row;
		}
		$res->free();
	}
} catch (Exception $e) {
	$error = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>System Logs</title>
	<style>
		body { font-family: Arial, Helvetica, sans-serif; padding: 18px; }
		table { border-collapse: collapse; width: 100%; }
		th, td { border: 1px solid #ddd; padding: 8px; font-size: 14px; }
		th { background:#f4f4f4; text-align: left; }
		tr:nth-child(even){background-color: #fbfbfb}
		.muted { color: #666; font-size: 13px }
		.wrap { max-width: 1200px; margin: 0 auto }
	</style>
</head>
<body>
<div class="wrap">
	<h2>System Logs</h2>
	<?php if ($error): ?>
		<p class="muted">Error loading logs: <?php echo htmlspecialchars($error); ?></p>
	<?php endif; ?>

	<?php if (empty($logs)): ?>
		<p class="muted">No logs found. Ensure the <strong>system_logs</strong> table exists.</p>
	<?php else: ?>
		<table>
			<thead>
				<tr>
					<?php
					// Determine columns from first row
					$cols = array_keys($logs[0]);
					foreach ($cols as $col) {
						echo '<th>' . htmlspecialchars($col) . '</th>';
					}
					?>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($logs as $r): ?>
				<tr>
					<?php foreach ($cols as $col): ?>
						<td><?php echo nl2br(htmlspecialchars((string)($r[$col] ?? ''))); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
</body>
</html>

