<?php
require_once "conn.php";

// Basic totals
$total_revenue = $conn->query("SELECT IFNULL(SUM(amount),0) total FROM payments WHERE status = 'success'")
	->fetch_assoc()['total'];

$total_units = $conn->query("SELECT COUNT(*) total FROM units")
	->fetch_assoc()['total'];

// Occupied (consider pending downpayment as occupied for occupancy analytics)
$occupied_units = $conn->query("SELECT COUNT(*) total FROM assigned_units WHERE status IN ('occupied','pending downpayment')")
	->fetch_assoc()['total'];

$occupancy_rate = $total_units > 0 ? ($occupied_units / $total_units) * 100 : 0;

// Monthly revenue for the past 18 months
$months = [];
$monthly_revenue = [];

$res = $conn->query("SELECT DATE_FORMAT(datetime_paid, '%Y-%m') month, SUM(amount) total
	FROM payments
	WHERE status = 'success'
	  AND datetime_paid >= DATE_SUB(CURDATE(), INTERVAL 18 MONTH)
	GROUP BY month
	ORDER BY month ASC");

while ($r = $res->fetch_assoc()) {
	$months[] = $r['month'];
	$monthly_revenue[] = (float)$r['total'];
}

// If there are missing months between results, fill them with 0s to have contiguous timeline
function fill_months(array $months, array $revenue, int $span_months = 18) {
	if (empty($months)) {
		// generate last $span_months months labels with zeros
		$labels = [];
		$vals = [];
		for ($i = $span_months-1; $i >= 0; $i--) {
			$labels[] = date('Y-m', strtotime("-{$i} months"));
			$vals[] = 0.0;
		}
		return [$labels, $vals];
	}

	// build map
	$map = [];
	foreach ($months as $i => $m) $map[$m] = $revenue[$i];

	$labels = [];
	$vals = [];
	$start = strtotime($months[0] . '-01');
	$now = strtotime(date('Y-m-01'));
	// ensure at least $span_months ending on current month
	$start = min($start, strtotime("-" . ($span_months-1) . " months", $now));

	for ($t = $start; $t <= $now; $t = strtotime('+1 month', $t)) {
		$label = date('Y-m', $t);
		$labels[] = $label;
		$vals[] = isset($map[$label]) ? (float)$map[$label] : 0.0;
	}

	return [$labels, $vals];
}

list($months_filled, $monthly_revenue_filled) = fill_months($months, $monthly_revenue, 18);

// Occupancy per month (based on assigned_units.start_date <= month_end and status)
$occupancy_rates = [];
foreach ($months_filled as $m) {
	$month_end = date('Y-m-t', strtotime($m . '-01'));
	$occ_q = $conn->prepare("SELECT COUNT(*) total FROM assigned_units WHERE start_date <= ? AND status IN ('occupied','pending downpayment')");
	$occ_q->bind_param('s', $month_end);
	$occ_q->execute();
	$occ_count = $occ_q->get_result()->fetch_assoc()['total'];
	$occ_q->close();

	$rate = $total_units > 0 ? ($occ_count / $total_units) * 100 : 0;
	$occupancy_rates[] = round($rate, 2);
}

// Simple linear regression (least-squares) utility
function linear_regression(array $xs, array $ys) {
	$n = count($xs);
	if ($n === 0) return [0, 0];
	if ($n === 1) return [0, $ys[0]];

	$sumx = array_sum($xs);
	$sumy = array_sum($ys);
	$sumxy = 0;
	$sumxx = 0;
	for ($i = 0; $i < $n; $i++) {
		$x = $xs[$i];
		$y = $ys[$i];
		$sumxy += $x * $y;
		$sumxx += $x * $x;
	}
	$den = ($n * $sumxx) - ($sumx * $sumx);
	if (abs($den) < 1e-9) return [0, $sumy / $n];

	$slope = (($n * $sumxy) - ($sumx * $sumy)) / $den;
	$intercept = ($sumy - $slope * $sumx) / $n;
	return [$slope, $intercept];
}

// Prepare x values (0..n-1)
$n = count($monthly_revenue_filled);
$xs = range(0, $n-1);

if ($n >= 2) {
	list($slope_r, $intercept_r) = linear_regression($xs, $monthly_revenue_filled);
} else {
	$slope_r = 0; $intercept_r = $n ? $monthly_revenue_filled[0] : 0;
}

// predict next 6 months revenue
$predict_count = 6;
$pred_months = [];
$pred_revenue = [];
$last_month_timestamp = strtotime(end($months_filled) . '-01');
for ($i = 1; $i <= $predict_count; $i++) {
	$ts = strtotime("+{$i} month", $last_month_timestamp);
	$pred_months[] = date('Y-m', $ts);
	$x = $n - 1 + $i;
	$y = $intercept_r + $slope_r * $x;
	$pred_revenue[] = max(0, round($y, 2));
}

// occupancy regression and prediction
if (count($occupancy_rates) >= 2) {
	list($slope_o, $intercept_o) = linear_regression($xs, $occupancy_rates);
} else {
	$slope_o = 0; $intercept_o = count($occupancy_rates) ? $occupancy_rates[0] : 0;
}

$pred_occupancy = [];
foreach (range(1, $predict_count) as $i) {
	$x = $n - 1 + $i;
	$y = $intercept_o + $slope_o * $x;
	$pred_occupancy[] = round(max(0, min(100, $y)), 2);
}

// present
?>
<div class="container-fluid py-4">

	<h4 class="fw-bold text-secondary mb-4">Forecasting & Analytics</h4>

	<div class="row g-3 mb-4">
		<div class="col-md-3">
			<div class="card shadow-sm border-0 p-3 text-center">
				<small class="text-muted">Total Revenue</small>
				<h4 class="fw-bold text-primary">₱<?= number_format($total_revenue,2) ?></h4>
			</div>
		</div>

		<div class="col-md-3">
			<div class="card shadow-sm border-0 p-3 text-center">
				<small class="text-muted">Monthly Avg</small>
				<h4 class="fw-bold">₱<?= number_format($n? array_sum($monthly_revenue_filled)/$n : 0,2) ?></h4>
			</div>
		</div>

		<div class="col-md-3">
			<div class="card shadow-sm border-0 p-3 text-center">
				<small class="text-muted">Occupancy Rate</small>
				<h4 class="fw-bold"><?= round($occupancy_rate,2) ?>%</h4>
			</div>
		</div>

		<div class="col-md-3">
			<div class="card shadow-sm border-0 p-3 text-center">
				<small class="text-muted">Predicted Revenue (next month)</small>
				<h4 class="fw-bold">₱<?= number_format(isset($pred_revenue[0])? $pred_revenue[0] : 0,2) ?></h4>
			</div>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-md-8">
			<div class="card shadow-sm border-0 p-3">
				<canvas id="forecastRevenue"></canvas>
			</div>
		</div>

		<div class="col-md-4">
			<div class="card shadow-sm border-0 p-3">
				<canvas id="occupancyForecast"></canvas>
			</div>
		</div>
	</div>

	<div class="row g-3">
		<div class="col-12">
			<div class="card shadow-sm border-0">
				<div class="card-header bg-primary text-white fw-bold">Revenue Predictions (Next <?= $predict_count ?> months)</div>
				<div class="table-responsive">
					<table class="table table-sm mb-0">
						<thead class="table-light small">
							<tr>
								<th>Month</th>
								<th class="text-end">Predicted Revenue</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($pred_months as $i => $pm): ?>
								<tr>
									<td><?= date('M Y', strtotime($pm . '-01')) ?></td>
									<td class="text-end">₱<?= number_format($pred_revenue[$i],2) ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
	const revenueCtx = document.getElementById('forecastRevenue');
	new Chart(revenueCtx, {
		type: 'line',
		data: {
			labels: <?= json_encode(array_merge($months_filled, $pred_months)) ?>,
			datasets: [
				{
					label: 'Actual Revenue',
					data: <?= json_encode($monthly_revenue_filled) ?>,
					borderColor: '#0d6efd',
					tension: 0.3,
					fill: false,
				},
				{
					label: 'Predicted Revenue',
					data: <?= json_encode(array_merge(array_fill(0, count($monthly_revenue_filled), null), $pred_revenue)) ?>,
					borderColor: '#198754',
					borderDash: [6,4],
					tension: 0.3,
					fill: false,
				}
			]
		}
	});

	const occCtx = document.getElementById('occupancyForecast');
	new Chart(occCtx, {
		type: 'line',
		data: {
			labels: <?= json_encode(array_merge($months_filled, $pred_months)) ?>,
			datasets: [
				{
					label: 'Actual Occupancy %',
					data: <?= json_encode($occupancy_rates) ?>,
					borderColor: '#dc3545',
					tension: 0.3,
					fill: false,
				},
				{
					label: 'Predicted Occupancy %',
					data: <?= json_encode(array_merge(array_fill(0, count($occupancy_rates), null), $pred_occupancy)) ?>,
					borderColor: '#fd7e14',
					borderDash: [6,4],
					tension: 0.3,
					fill: false,
				}
			]
		}
	});
</script>

<?php
// mark TODO step as completed in the workspace TODO (not automated here)

