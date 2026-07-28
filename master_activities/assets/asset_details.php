<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Get Asset ID from URL
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '0';

/* =========================================================
   FETCH MAIN ASSET DETAILS (JOINING ALL RELATED TABLES)
   ========================================================= */
$query = "SELECT a.*, 
                 c.category_name, 
                 v.vendor_name, 
                 l.dept_name, l.floor, 
                 s.status_name,
                 m.model_name,
                 m.make_name,
                 m.supply_order_doc,
                 m.model_image
          FROM assets a
          LEFT JOIN asset_categories c ON a.category_id = c.category_id
          LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
          LEFT JOIN locations l ON a.location_id = l.location_id
          LEFT JOIN asset_status s ON a.status_id = s.status_id
          LEFT JOIN asset_models m ON a.model_id = m.model_id
          WHERE a.asset_id = '$id'";

$result = mysqli_query($conn, $query);
$asset = mysqli_fetch_assoc($result);

if (!$asset) {
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>Asset not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* =========================================================
   FETCH ASSIGNMENT HISTORY
   ========================================================= */
$history_query = "SELECT asn.*, u.name as user_name, u.role, l.dept_name
                  FROM asset_assignments asn 
                  LEFT JOIN users u ON asn.user_id = u.user_id 
                  LEFT JOIN locations l ON u.location_id = l.location_id
                  WHERE asn.asset_id = '$id' 
                  ORDER BY asn.assigned_date DESC, asn.assignment_id DESC";
$history_res = mysqli_query($conn, $history_query);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Asset Details</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="assets_list.php" class="text-decoration-none">Assets</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($asset['asset_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="assets_edit.php?id=<?= $asset['asset_id'] ?>" class="btn btn-warning"><i class="bi bi-pencil-square"></i> Edit Asset</a>
            <a href="assets_list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN -->
        <div class="col-xl-8 col-lg-7">
            
            <!-- ASSET INFORMATION CARD -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i> General Information</h5>
                    <span class="badge bg-light text-dark fs-6"><?= htmlspecialchars($asset['status_name'] ?? 'Unknown') ?></span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <?php if (!empty($asset['model_image'])): ?>
                                <img src="../../<?= htmlspecialchars($asset['model_image']) ?>" class="img-fluid rounded border p-2" style="max-height: 120px; object-fit: contain;" alt="Model Logo">
                            <?php else: ?>
                                <div class="bg-light border text-muted d-flex align-items-center justify-content-center rounded mx-auto" style="height: 120px; width: 120px;">
                                    <i class="bi bi-pc-display fs-1"></i>
                                </div>
                            <?php endif; ?>
                            <div class="mt-2 fw-bold text-muted small">Model Logo</div>
                        </div>
                        <div class="col-md-9">
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th style="width: 30%;" class="text-muted">Asset Name:</th>
                                        <td class="fw-bold fs-5"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Serial Number:</th>
                                        <td><code class="fs-6 text-dark bg-light px-2 py-1 rounded"><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Category:</th>
                                        <td><?= htmlspecialchars($asset['category_name'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Model:</th>
                                        <td>
                                            <a href="../models/models_details.php?id=<?= $asset['model_id'] ?>" class="text-decoration-none fw-bold">
                                                <?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Make:</th>
                                        <td><?= htmlspecialchars($asset['make_name'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Current Location:</th>
                                        <td>
                                            <?php 
                                            $loc_label = $asset['dept_name'] ?? 'N/A';
                                            if (!empty($asset['floor'])) $loc_label .= " (" . $asset['floor'] . ")";
                                            echo htmlspecialchars($loc_label);
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROCUREMENT & FINANCIAL CARD -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-currency-rupee me-2"></i> Procurement Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <span class="d-block text-muted small">Vendor</span>
                            <span class="fw-bold"><?= htmlspecialchars($asset['vendor_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="d-block text-muted small">Cost</span>
                            <span class="fw-bold"><?= !empty($asset['cost']) ? '₹ ' . number_format($asset['cost'], 2) : 'N/A' ?></span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="d-block text-muted small">Purchase Date</span>
                            <span class="fw-bold"><?= !empty($asset['purchase_date']) ? date('d M Y', strtotime($asset['purchase_date'])) : 'N/A' ?></span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="d-block text-muted small">Warranty Expiry</span>
                            <?php 
                                if (!empty($asset['warranty_expiry'])) {
                                    // Robust Day Calculation using DateTime
                                    $expiry_dt = new DateTime($asset['warranty_expiry']);
                                    $now_dt = new DateTime();
                                    
                                    // Set both to midnight to count pure days
                                    $expiry_dt->setTime(0, 0, 0);
                                    $now_dt->setTime(0, 0, 0);
                                    
                                    $interval = $now_dt->diff($expiry_dt);
                                    $days = $interval->days;
                                    
                                    // If interval is negative, it's expired (invert = 1)
                                    $is_expired = $interval->invert == 1; 

                                    if ($days == 0) {
                                        $color = 'text-warning';
                                        $status_text = " (Expires Today)";
                                    } elseif ($is_expired) {
                                        $color = 'text-danger';
                                        $status_text = " (Expired $days days ago)";
                                    } else {
                                        $color = 'text-success';
                                        $status_text = " ($days days remaining)";
                                    }

                                    echo "<span class='fw-bold $color'>" . $expiry_dt->format('d M Y') . $status_text . "</span>";
                                } else {
                                    echo "<span class='fw-bold'>N/A</span>";
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ASSIGNMENT HISTORY CARD -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i> Assignment History</h5>
                    <a href="../assignments/assign_asset.php?asset_id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-light text-dark fw-bold">Assign Asset</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Assigned Date</th>
                                    <th>Returned Date</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($history_res) > 0): ?>
                                    <?php while ($h = mysqli_fetch_assoc($history_res)): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($h['user_name'] ?? 'Unknown') ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($h['dept_name'] ?? '') ?></small>
                                            </td>
                                            <td><?= date('d M Y', strtotime($h['assigned_date'])) ?></td>
                                            <td>
                                                <?php if (empty($h['returned_date'])): ?>
                                                    <span class="badge bg-success">Currently In Use</span>
                                                <?php else: ?>
                                                    <?= date('d M Y', strtotime($h['returned_date'])) ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="d-inline-block text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($h['remarks'] ?? 'None') ?>">
                                                    <?= htmlspecialchars($h['remarks'] ?? '-') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">This asset has never been assigned.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-xl-4 col-lg-5">
            <!-- DOCUMENTS CARD -->
            <div class="card shadow-sm mb-4 border-info">
                <div class="card-header bg-info text-dark">
                    <h5 class="mb-0"><i class="bi bi-folder2-open me-2"></i> Documents</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        
                        <!-- 1. DYNAMIC SUPPLY ORDER FROM MODEL -->
                        <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-text fs-3 text-primary me-3"></i>
                                <div>
                                    <h6 class="mb-0 fw-bold">Supply Order</h6>
                                    <small class="text-muted">Attached from Model</small>
                                </div>
                            </div>
                            <?php if (!empty($asset['supply_order_doc'])): ?>
                                <a href="../../<?= htmlspecialchars($asset['supply_order_doc']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                            <?php else: ?>
                                <span class="badge bg-secondary">N/A</span>
                            <?php endif; ?>
                        </li>

                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include("../../includes/footer.php"); ?>