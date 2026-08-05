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
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm border-0'><i class='bi bi-exclamation-triangle-fill me-2'></i> Asset not found.</div></div>";
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

// Determine Status Badge Color for the Header
$header_badge_class = 'bg-secondary';
if ($asset['status_name'] == 'Assigned') $header_badge_class = 'bg-primary';
elseif (in_array($asset['status_name'], ['Available', 'Working'])) $header_badge_class = 'bg-success';
elseif ($asset['status_name'] == 'Under Repair') $header_badge_class = 'bg-warning text-dark';
elseif (in_array($asset['status_name'], ['Retired', 'Condemned'])) $header_badge_class = 'bg-danger';

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <!-- PAGE HEADER -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="mb-0 text-dark"><i class="bi bi-info-square-fill text-primary me-2"></i> Asset Details</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="assets_list.php" class="text-decoration-none">Assets Inventory</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($asset['asset_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="assets_edit.php?id=<?= $asset['asset_id'] ?>" class="btn btn-warning fw-bold shadow-sm text-dark">
                <i class="bi bi-pencil-square me-1"></i> Edit Asset
            </a>
            <a href="assets_list.php" class="btn btn-secondary fw-bold shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN -->
        <div class="col-xl-8 col-lg-7 mb-4">
            
            <!-- ASSET INFORMATION CARD -->
            <div class="card shadow-sm border-0 border-top border-primary border-4 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-pc-display me-2 text-primary"></i> General Information</h5>
                    <span class="badge <?= $header_badge_class ?> rounded-pill px-3 py-2 fs-6 shadow-sm">
                        <?= htmlspecialchars($asset['status_name'] ?? 'Unknown') ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center mb-4 mb-md-0">
                            <?php if (!empty($asset['model_image'])): ?>
                                <div class="bg-white border rounded p-2 shadow-sm mx-auto" style="width: 140px; height: 140px;">
                                    <img src="../../<?= htmlspecialchars($asset['model_image']) ?>" class="img-fluid" style="width: 100%; height: 100%; object-fit: contain;" alt="Model Logo">
                                </div>
                            <?php else: ?>
                                <div class="bg-light border text-muted d-flex align-items-center justify-content-center rounded shadow-sm mx-auto" style="height: 140px; width: 140px;">
                                    <i class="bi bi-pc-display" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="mt-3 fw-bold text-muted small text-uppercase">Device Image</div>
                        </div>
                        <div class="col-md-9">
                            <table class="table table-borderless table-sm mb-0 align-middle">
                                <tbody>
                                    <tr>
                                        <th style="width: 35%;" class="text-muted text-uppercase small">Asset Name:</th>
                                        <td class="fw-bold fs-5 text-dark"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Serial Number:</th>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <code class="fs-6 text-primary bg-primary bg-opacity-10 px-2 py-1 rounded me-2"><?= htmlspecialchars($asset['serial_number']) ?></code>
                                                <button class="btn btn-sm btn-light border py-0 px-2 text-muted shadow-sm" onclick="copyToClipboard('<?= htmlspecialchars($asset['serial_number']) ?>')" title="Copy Serial Number">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Category:</th>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($asset['category_name'] ?? 'N/A') ?></span></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Model:</th>
                                        <td>
                                            <a href="../models/models_details.php?id=<?= $asset['model_id'] ?>" class="text-decoration-none fw-bold">
                                                <?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Make:</th>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($asset['make_name'] ?? 'N/A') ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted text-uppercase small">Current Location:</th>
                                        <td class="fw-semibold text-dark">
                                            <i class="bi bi-geo-alt-fill text-danger me-1"></i>
                                            <?php 
                                            $loc_label = $asset['dept_name'] ?? 'Unassigned';
                                            if (!empty($asset['floor'])) $loc_label .= " <span class='text-muted small'>(" . $asset['floor'] . ")</span>";
                                            echo $loc_label;
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
            <div class="card shadow-sm border-0 border-top border-success border-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-currency-rupee me-2 text-success"></i> Procurement Details</h5>
                </div>
                <div class="card-body bg-light rounded-bottom">
                    <div class="row">
                        <div class="col-sm-6 mb-3">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1">Vendor</span>
                            <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($asset['vendor_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-sm-6 mb-3">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1">Total Cost</span>
                            <span class="fw-bold text-success fs-6"><?= !empty($asset['cost']) ? '₹ ' . number_format($asset['cost'], 2) : 'N/A' ?></span>
                        </div>
                        <div class="col-sm-6 mb-3 mb-sm-0">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1">Purchase Date</span>
                            <span class="fw-bold text-dark"><i class="bi bi-calendar-check me-2 text-muted"></i><?= !empty($asset['purchase_date']) ? date('d M Y', strtotime($asset['purchase_date'])) : 'N/A' ?></span>
                        </div>
                        <div class="col-sm-6">
                            <span class="d-block text-muted small fw-bold text-uppercase mb-1">Warranty Expiry</span>
                            <?php 
                                if (!empty($asset['warranty_expiry'])) {
                                    $expiry_dt = new DateTime($asset['warranty_expiry']);
                                    $now_dt = new DateTime();
                                    
                                    $expiry_dt->setTime(0, 0, 0);
                                    $now_dt->setTime(0, 0, 0);
                                    
                                    $interval = $now_dt->diff($expiry_dt);
                                    $days = $interval->days;
                                    $is_expired = $interval->invert == 1; 

                                    if ($days == 0) {
                                        $color = 'text-warning';
                                        $icon = '<i class="bi bi-shield-exclamation me-1"></i>';
                                        $status_text = " <span class='badge bg-warning text-dark ms-2'>Expires Today</span>";
                                    } elseif ($is_expired) {
                                        $color = 'text-danger';
                                        $icon = '<i class="bi bi-shield-x me-1"></i>';
                                        $status_text = " <span class='badge bg-danger ms-2'>Expired $days days ago</span>";
                                    } elseif ($days <= 30) {
                                        $color = 'text-warning';
                                        $icon = '<i class="bi bi-shield-exclamation me-1"></i>';
                                        $status_text = " <span class='badge bg-warning text-dark ms-2'>Expiring Soon ($days days)</span>";
                                    } else {
                                        $color = 'text-success';
                                        $icon = '<i class="bi bi-shield-check me-1"></i>';
                                        $status_text = " <span class='badge bg-success bg-opacity-10 text-success border border-success ms-2'>Active ($days days left)</span>";
                                    }

                                    echo "<span class='fw-bold $color'>" . $icon . $expiry_dt->format('d M Y') . "</span>" . $status_text;
                                } else {
                                    echo "<span class='fw-bold text-muted'>N/A</span>";
                                }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ASSIGNMENT HISTORY CARD -->
            <div class="card shadow-sm border-0 border-top border-dark border-4 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-clock-history me-2 text-dark"></i> Assignment History</h5>
                    <a href="../assignments/assign_asset.php?asset_id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-dark fw-bold shadow-sm">
                        <i class="bi bi-person-plus-fill me-1"></i> Assign Asset
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-secondary">
                                <tr>
                                    <th class="ps-4 border-bottom-0">User Details</th>
                                    <th class="border-bottom-0">Assigned Date</th>
                                    <th class="border-bottom-0">Returned Date</th>
                                    <th class="border-bottom-0">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($history_res) > 0): ?>
                                    <?php while ($h = mysqli_fetch_assoc($history_res)): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($h['user_name'] ?? 'Unknown') ?></div>
                                                <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($h['dept_name'] ?? 'No Dept') ?></div>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark"><?= date('d M Y', strtotime($h['assigned_date'])) ?></span>
                                            </td>
                                            <td>
                                                <?php if (empty($h['returned_date'])): ?>
                                                    <span class="badge bg-success px-2 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>Currently In Use</span>
                                                <?php else: ?>
                                                    <span class="text-muted fw-semibold"><?= date('d M Y', strtotime($h['returned_date'])) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="d-inline-block text-truncate text-muted" style="max-width: 180px;" title="<?= htmlspecialchars($h['remarks'] ?? 'None') ?>">
                                                    <?= htmlspecialchars($h['remarks'] ?? '-') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="bi bi-clock fs-2 d-block mb-2 opacity-50"></i>
                                            <p class="mb-0">This asset has never been assigned.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <!-- DOCUMENTS CARD -->
            <div class="card shadow-sm border-0 border-top border-info border-4 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-folder2-open me-2 text-info"></i> Documents</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        
                        <!-- 1. DYNAMIC SUPPLY ORDER FROM MODEL -->
                        <li class="list-group-item d-flex justify-content-between align-items-center py-4 px-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-info bg-opacity-10 text-info rounded p-2 me-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                    <i class="bi bi-file-earmark-text fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-dark">Supply Order</h6>
                                    <small class="text-muted">Attached from Model</small>
                                </div>
                            </div>
                            <?php if (!empty($asset['supply_order_doc'])): ?>
                                <a href="../../<?= htmlspecialchars($asset['supply_order_doc']) ?>" target="_blank" class="btn btn-sm btn-outline-info fw-bold shadow-sm">View <i class="bi bi-box-arrow-up-right ms-1"></i></a>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Not Available</span>
                            <?php endif; ?>
                        </li>

                    </ul>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Copy to Clipboard Script -->
<script>
function copyToClipboard(text) {
    // Modern approach (Requires HTTPS or localhost)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied Serial Number: ' + text);
        }).catch(function(err) {
            console.error('Could not copy text: ', err);
        });
    } else {
        // Fallback approach (Works on standard HTTP)
        let textArea = document.createElement("textarea");
        textArea.value = text;
        
        // Prevent scrolling to the bottom of the page
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            alert('Copied Serial Number: ' + text);
        } catch (err) {
            console.error('Fallback copy failed: ', err);
            alert('Failed to copy. Please copy manually.');
        }
        
        textArea.remove();
    }
}
</script>

<?php include("../../includes/footer.php"); ?>