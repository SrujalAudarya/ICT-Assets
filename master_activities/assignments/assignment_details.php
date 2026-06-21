<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

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
$record = mysqli_fetch_assoc(mysqli_query($conn, $query));

if (!$record) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Assignment record not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Assignment Record Details</h2>
        <div>
            <?php if(!$record['returned_date']): ?>
                <a href="return_asset.php?id=<?= $id ?>" class="btn btn-danger">Mark as Returned</a>
            <?php endif; ?>
            <a href="assignments_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: ASSIGNMENT SUMMARY -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Assignment Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Status</label>
                        <?php if($record['returned_date']): ?>
                            <span class="h5 fw-bold text-secondary">Returned</span>
                        <?php else: ?>
                            <span class="h5 fw-bold text-success">Active</span>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Assigned Date</label>
                        <span class="fw-bold"><?= date('d M Y', strtotime($record['assigned_date'])) ?></span>
                    </div>
                    <?php if($record['returned_date']): ?>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Returned Date</label>
                            <span class="fw-bold text-danger"><?= date('d M Y', strtotime($record['returned_date'])) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-0">
                        <label class="text-muted small d-block">Record ID</label>
                        <span>#<?= $record['assignment_id'] ?></span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">User Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Full Name</label>
                        <a href="../users/users_view.php?id=<?= $record['user_id'] ?>" class="fw-bold text-decoration-none">
                            <?= htmlspecialchars($record['user_name']) ?>
                        </a>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Email Address</label>
                        <span><?= htmlspecialchars($record['user_email']) ?></span>
                    </div>
                    <div class="mb-0">
                        <label class="text-muted small d-block">Role</label>
                        <span class="badge bg-info"><?= htmlspecialchars($record['user_role']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: ASSET & REMARKS -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Asset Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Asset Name</label>
                            <a href="../assets/asset_details.php?id=<?= $record['asset_id'] ?>" class="h5 fw-bold text-decoration-none">
                                <?= htmlspecialchars($record['asset_name']) ?>
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Serial Number</label>
                            <code><?= htmlspecialchars($record['serial_number']) ?></code>
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="text-muted small d-block">Category</label>
                            <span><?= htmlspecialchars($record['category_name']) ?></span>
                        </div>
                        <div class="col-md-6 mb-0">
                            <label class="text-muted small d-block">Model</label>
                            <span><?= htmlspecialchars($record['model_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Assignment Remarks / Handover Notes</h5>
                </div>
                <div class="card-body">
                    <p class="fs-5"><?= nl2br(htmlspecialchars($record['remarks'] ?: 'No remarks provided.')) ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>
