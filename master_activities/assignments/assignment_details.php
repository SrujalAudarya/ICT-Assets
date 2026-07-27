<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ---------- ASSIGNMENT RECORD INFO ---------- */
$query = "
SELECT aa.*, a.asset_name, a.serial_number, a.asset_id, u.name as user_name, u.email as user_email, u.phone as user_phone, u.role as user_role, c.category_name, m.model_name
FROM asset_assignments aa
JOIN assets a ON aa.asset_id = a.asset_id
JOIN users u ON aa.user_id = u.user_id
LEFT JOIN asset_categories c ON a.category_id = c.category_id
LEFT JOIN asset_models m ON a.model_id = m.model_id
WHERE aa.assignment_id = '$id'
";
$result = mysqli_query($conn, $query);
$record = $result ? mysqli_fetch_assoc($result) : null;

if (!$record) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assignment record not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* ---------- FETCH ENTIRE ASSET HISTORY ---------- */
$asset_id = $record['asset_id'];
$history_query = "
    SELECT asn.*, u.name as user_name 
    FROM asset_assignments asn
    JOIN users u ON asn.user_id = u.user_id
    WHERE asn.asset_id = '$asset_id'
    ORDER BY asn.assigned_date DESC, asn.assignment_id DESC
";
$history_res = mysqli_query($conn, $history_query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Assignment Record Details</h2>
        <div>
            <?php if(!$record['returned_date']): ?>
                <a href="return_asset.php?id=<?= $id ?>" class="btn btn-danger"><i class="bi bi-arrow-return-left"></i> Mark as Returned</a>
            <?php endif; ?>
            <a href="assignments_list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: ASSIGNMENT SUMMARY -->
        <div class="col-lg-4 col-md-5">
            <div class="card shadow-sm mb-4 border-success border-top border-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-success"><i class="bi bi-info-circle me-2"></i> Assignment Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block fw-bold">Status</label>
                        <?php if($record['returned_date']): ?>
                            <span class="h5 fw-bold text-secondary"><i class="bi bi-check-circle-fill"></i> Returned</span>
                        <?php else: ?>
                            <span class="h5 fw-bold text-success"><i class="bi bi-person-workspace"></i> Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block fw-bold">Assigned Date</label>
                        <span class="fw-bold fs-5 text-dark"><?= date('d M Y', strtotime($record['assigned_date'])) ?></span>
                    </div>
                    <?php if($record['returned_date']): ?>
                        <div class="mb-3">
                            <label class="text-muted small d-block fw-bold">Returned Date</label>
                            <span class="fw-bold fs-5 text-danger"><?= date('d M Y', strtotime($record['returned_date'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <label class="text-muted small d-block fw-bold">Record ID</label>
                        <span class="badge bg-dark fs-6">#<?= $record['assignment_id'] ?></span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-primary border-top border-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-primary"><i class="bi bi-person-fill me-2"></i> User Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block fw-bold">Full Name</label>
                        <a href="../users/users_view.php?id=<?= $record['user_id'] ?>" class="fw-bold fs-5 text-decoration-none">
                            <?= htmlspecialchars($record['user_name']) ?>
                        </a>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block fw-bold">Email Address</label>
                        <span><?= htmlspecialchars($record['user_email'] ?: 'N/A') ?></span>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted small d-block fw-bold">Role</label>
                        <span class="badge bg-info text-dark"><?= htmlspecialchars($record['user_role']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ASSET, REMARKS & HISTORY -->
        <div class="col-lg-8 col-md-7">
            <div class="card shadow-sm mb-4 border-dark border-top border-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0 text-dark"><i class="bi bi-pc-display me-2"></i> Asset Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block fw-bold">Asset Name</label>
                            <a href="../assets/asset_details.php?id=<?= $record['asset_id'] ?>" class="h5 fw-bold text-decoration-none">
                                <?= htmlspecialchars($record['asset_name']) ?>
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block fw-bold">Serial Number</label>
                            <code class="fs-5 text-dark bg-light px-2 py-1 rounded"><?= htmlspecialchars($record['serial_number']) ?></code>
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="text-muted small d-block fw-bold">Category</label>
                            <span class="badge bg-secondary"><?= htmlspecialchars($record['category_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="text-muted small d-block fw-bold">Model</label>
                            <span><?= htmlspecialchars($record['model_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0 text-secondary"><i class="bi bi-journal-text me-2"></i> Assignment Remarks / Handover Notes</h5>
                </div>
                <div class="card-body">
                    <p class="fs-6 mb-0"><?= nl2br(htmlspecialchars($record['remarks'] ?: 'No remarks provided.')) ?></p>
                </div>
            </div>

            <!-- ASSET ASSIGNMENT HISTORY TIMELINE -->
            <div class="card shadow-sm mb-4 border-info border-top border-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-info"><i class="bi bi-clock-history me-2"></i> Full History for this Asset</h5>
                    <span class="badge bg-info text-dark"><?= mysqli_num_rows($history_res) ?> Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Assigned To</th>
                                    <th>Assigned Date</th>
                                    <th>Returned Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($hist = mysqli_fetch_assoc($history_res)): ?>
                                    <tr class="<?= ($hist['assignment_id'] == $id) ? 'table-primary' : '' ?>">
                                        <td class="fw-bold">
                                            <i class="bi bi-person-fill text-muted me-1"></i> 
                                            <?= htmlspecialchars($hist['user_name']) ?>
                                            <?php if($hist['assignment_id'] == $id): ?>
                                                <span class="badge bg-primary ms-1">Current View</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d M Y', strtotime($hist['assigned_date'])) ?></td>
                                        <td>
                                            <?= $hist['returned_date'] ? date('d M Y', strtotime($hist['returned_date'])) : '<span class="text-muted">-</span>' ?>
                                        </td>
                                        <td>
                                            <?php if(!$hist['returned_date']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Returned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($hist['assignment_id'] != $id): ?>
                                                <!-- FIXED: Points directly to this page with the correct assignment ID -->
                                                <a href="?id=<?= $hist['assignment_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>