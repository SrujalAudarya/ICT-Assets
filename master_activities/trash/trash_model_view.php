<?php
ob_start();
global $conn;

include("../../includes/auth.php");
include("../../config/db.php");

$model_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ---------- FETCH MODEL BASIC INFO ---------- */
$model_query = "
    SELECT m.*, 
           c.category_name, 
           pc.category_name AS parent_category_name,
           v.vendor_name,
           s.status_name AS model_status_name
    FROM asset_models m
    LEFT JOIN asset_categories c ON m.category_id = c.category_id
    LEFT JOIN asset_categories pc ON c.parent_id = pc.category_id
    LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
    LEFT JOIN asset_status s ON m.status_id = s.status_id
    WHERE m.model_id = $model_id
";
$model_result = mysqli_query($conn, $model_query);
$model = mysqli_fetch_assoc($model_result);

if (!$model) {
    header("Location: trash_bin.php");
    exit();
}

/* ---------- FETCH ONLY 'Not In Use' ASSETS FOR THIS MODEL ---------- */
$assets_query = "
    SELECT a.*, s.status_name AS asset_status_name, l.dept_name
    FROM assets a
    LEFT JOIN asset_status s ON a.status_id = s.status_id
    LEFT JOIN locations l ON a.location_id = l.location_id
    WHERE a.model_id = $model_id AND a.asset_state = 'Not In Use'
    ORDER BY a.asset_id DESC
";
$assets_result = mysqli_query($conn, $assets_query);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-eye-fill text-danger me-2"></i> Trash View: <?= htmlspecialchars($model['model_name']) ?></h2>
            <p class="text-muted small mb-0">Reviewing inactive / Not In Use assets linked to this decommissioned model.</p>
        </div>
        <div>
            <a href="trash_bin.php?tab=provisional" class="btn btn-secondary fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Back to Trash Bin
            </a>
        </div>
    </div>

    <!-- MODEL SUMMARY CARD -->
    <div class="card shadow-sm mb-4 border-0 border-top border-danger border-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php if (!empty($model['model_image'])): ?>
                        <img src="../../<?= htmlspecialchars($model['model_image']) ?>" class="img-thumbnail" style="max-height: 80px; object-fit: contain;" alt="Logo">
                    <?php else: ?>
                        <div class="bg-light border text-muted d-flex align-items-center justify-content-center rounded mx-auto" style="height: 70px; width: 70px;">
                            <i class="bi bi-image fs-4"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-md-4">
                            <span class="text-muted small d-block">Make / Vendor</span>
                            <strong><?= htmlspecialchars($model['make_name'] ?: 'N/A') ?> / <?= htmlspecialchars($model['vendor_name'] ?: 'N/A') ?></strong>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small d-block">Lifecycle Status</span>
                            <span class="badge bg-dark"><?= htmlspecialchars($model['model_status_name']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <span class="text-muted small d-block">Unit Price</span>
                            <span class="text-success fw-bold">₹ <?= number_format((float)($model['cost'] ?? 0), 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- INACTIVE ASSETS TABLE -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-dash-circle me-1"></i> Inactive Assets ('Not In Use')</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Asset Name</th>
                            <th>Serial Number</th>
                            <th>Status</th>
                            <th>State</th>
                            <th>Last Location</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assets_result && mysqli_num_rows($assets_result) > 0): ?>
                            <?php $sr = 1; while ($asset = mysqli_fetch_assoc($assets_result)): ?>
                                <tr>
                                    <td class="ps-3"><?= $sr++ ?></td>
                                    <td class="fw-bold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($asset['asset_status_name'] ?? 'N/A') ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary text-dark">Not In Use</span>
                                    </td>
                                    <td><?= htmlspecialchars($asset['dept_name'] ?? 'N/A') ?></td>
                                    <td class="text-center">
                                        <a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary fw-bold shadow-sm" target="_blank">
                                            View Asset
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i> No inactive assets found for this model.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>