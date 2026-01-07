<?php
require_once "conn.php";

// Fetch current assignments with tenants
$assignments = $conn->query(
	"SELECT au.assigned_units_id, au.unit_id, au.tenant_id, au.start_date, au.status AS assignment_status, au.downpayment,
			t.firstname, t.lastname, u.unit_number, u.monthly_rent, p.property_name
	 FROM assigned_units au
	 JOIN tenant t ON au.tenant_id = t.tenant_id
	 JOIN units u ON au.unit_id = u.unit_id
	 JOIN properties p ON u.property_id = p.property_id
	 WHERE au.status IN ('occupied','pending downpayment')
	 ORDER BY t.lastname ASC"
);
?>

<div class="container-fluid py-4">
	<div class="d-flex justify-content-between align-items-center mb-4">
		<h4 class="fw-bold text-secondary">Rent Tracking</h4>
		<small class="text-muted">View tenants and their payment schedules</small>
	</div>

	<div class="row">
		<?php if ($assignments && $assignments->num_rows > 0):
			while ($a = $assignments->fetch_assoc()):
				$aid = (int)$a['assigned_units_id'];
				$tenant_name = htmlspecialchars($a['lastname'] . ', ' . $a['firstname']);
				$unit_label = htmlspecialchars($a['unit_number']);
				$property = htmlspecialchars($a['property_name']);
				$status = $a['assignment_status'];
				if ($status === 'occupied') {
					$display_status = 'active';
					$status_badge = 'bg-primary';
				} elseif ($status === 'pending downpayment') {
					$display_status = 'Pending Downpayment';
					$status_badge = 'bg-warning text-dark';
				} else {
					$display_status = ucfirst($status);
					$status_badge = 'bg-light text-dark';
				}
		?>
			<div class="col-12 mb-3">
				<div class="card shadow-sm">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-start">
							<div>
								<h6 class="mb-1 fw-bold"><?= $tenant_name ?></h6>
								<div class="small text-muted">Unit: <?= $unit_label ?> • <?= $property ?></div>
								<div class="small mt-1">Move-in: <?= htmlspecialchars($a['start_date']) ?></div>
							</div>
							<div class="text-end">
								<span class="badge <?= $status_badge ?> mb-2"><?= $display_status ?></span>
								<div>
									<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sched<?= $aid ?>" aria-expanded="false" aria-controls="sched<?= $aid ?>">
										View Payment Schedule
									</button>
								</div>
							</div>
						</div>

						<div class="collapse mt-3" id="sched<?= $aid ?>">
							<div class="card card-body p-0 border-0">
								<?php
								$ps = $conn->prepare("SELECT schedule_id, due_date, amount_due, status, payment_id FROM payment_schedule WHERE rent_id = ? ORDER BY due_date ASC");
								$ps->bind_param("i", $aid);
								$ps->execute();
								$res = $ps->get_result();
								if ($res && $res->num_rows > 0):
								?>
									<div class="table-responsive">
										<table class="table table-sm mb-0">
											<thead class="table-light small text-uppercase">
												<tr>
													<th>Due Date</th>
													<th>Amount</th>
													<th>Status</th>
													<th>Paid On</th>
													<th>Method</th>
												</tr>
											</thead>
											<tbody>
											<?php while ($row = $res->fetch_assoc()):
												$paid_on = '-';
												$method = '-';
												$row_status = $row['status'];

												// If there is an associated payment record, use it and mark paid
												if (intval($row['payment_id']) > 0) {
													$pstmt = $conn->prepare("SELECT datetime_paid, method FROM payments WHERE payment_id = ? LIMIT 1");
													$pstmt->bind_param("i", $row['payment_id']);
													$pstmt->execute();
													$pinfo = $pstmt->get_result()->fetch_assoc();
													if ($pinfo) {
														$paid_on = $pinfo['datetime_paid'];
														$method = ucfirst($pinfo['method']);
														$row_status = 'paid';
													}
												} elseif (floatval($row['amount_due']) == 0.0) {
													// No amount to be paid: attempt to use downpayment record as proof
													$dstmt = $conn->prepare("SELECT datetime_paid, method FROM payments WHERE rent_id = ? AND type = 'downpayment' AND status = 'success' ORDER BY datetime_paid ASC LIMIT 1");
													$dstmt->bind_param("i", $aid);
													$dstmt->execute();
													$dinfo = $dstmt->get_result()->fetch_assoc();
													if ($dinfo) {
														$paid_on = $dinfo['datetime_paid'];
														$method = ucfirst($dinfo['method']);
													} else {
														$paid_on = '-';
														$method = 'N/A';
													}
													$row_status = 'paid';
												}

												$badge_class = ($row_status === 'paid') ? 'bg-success' : 'bg-light text-dark';
											?>
												<tr>
													<td><?= htmlspecialchars($row['due_date']) ?></td>
													<td>₱<?= number_format($row['amount_due'], 2) ?></td>
													<td><span class="badge <?= $badge_class ?> small"><?= ucfirst($row_status) ?></span></td>
													<td><?= htmlspecialchars($paid_on) ?></td>
													<td><?= htmlspecialchars($method) ?></td>
												</tr>
											<?php endwhile; ?>
											</tbody>
										</table>
									</div>
								<?php else: ?>
									<div class="p-3 small text-muted">No payment schedule found for this tenant.</div>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		<?php endwhile; else: ?>
			<div class="col-12">
				<div class="card shadow-sm">
					<div class="card-body small text-muted">No current tenant assignments found.</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>

<style>
	.card .small { font-size: 0.85rem; }
</style>

