<?php
// 1. Start output buffering immediately
ob_start();

global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '0';

/* =========================================================
   FETCH CATEGORY DETAILS & PARENT RELATIONSHIP
   ========================================================= */
$cat_query = "SELECT c.*, p.category_name AS parent_name 
              FROM asset_categories c 
              LEFT JOIN asset_categories p ON c.parent_id = p.category_id 
              WHERE c.category_id = '$id'";
$cat_res = mysqli_query($conn, $cat_query);
$category = mysqli_fetch_assoc($cat_res);

if (!$category) {
    while (ob_get_level()) ob_end_clean(); // Clear buffer safely
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger'>Category not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

$is_main_category = empty($category['parent_id']) || $category['parent_id'] == 0;

/* =========================================================
   FETCH SUB-CATEGORIES (IF MAIN CATEGORY)
   ========================================================= */
$sub_cats = [];
if ($is_main_category) {
    $sub_res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id = '$id' ORDER BY category_name ASC");
    while($s = mysqli_fetch_assoc($sub_res)) {
        $sub_cats[] = $s;
    }
}

/* =========================================================
   FILTER HANDLING
   ========================================================= */
$model    = $_GET['model'] ?? '';
$status   = $_GET['status'] ?? '';
$location = $_GET['location'] ?? '';

// Determine base category condition
if ($is_main_category) {
    $sub_ids = [$id];
    foreach($sub_cats as $sc) { 
        $sub_ids[] = $sc['category_id']; 
    }
    $ids_str = implode(',', $sub_ids);
    $category_condition = "a.category_id IN ($ids_str)";
} else {
    $category_condition = "a.category_id = '$id'";
}

$where_clauses = [$category_condition];

if (!empty($model)) {
    $where_clauses[] = "a.model_id = '" . mysqli_real_escape_string($conn, $model) . "'";
}
if (!empty($status)) {
    $where_clauses[] = "a.status_id = '" . mysqli_real_escape_string($conn, $status) . "'";
}
if (!empty($location)) {
    $where_clauses[] = "a.location_id = '" . mysqli_real_escape_string($conn, $location) . "'";
}

$where_sql = implode(' AND ', $where_clauses);

/* =========================================================
   MASTER SQL QUERY (Used for both Export and HTML View)
   ========================================================= */
$assets_query = "SELECT a.*, 
                        s.status_name, 
                        l.dept_name, 
                        m.model_name,
                        u.name AS user_name
                 FROM assets a 
                 LEFT JOIN asset_status s ON a.status_id = s.status_id 
                 LEFT JOIN locations l ON a.location_id = l.location_id 
                 LEFT JOIN asset_models m ON a.model_id = m.model_id 
                 LEFT JOIN asset_assignments asn ON a.asset_id = asn.asset_id AND asn.returned_date IS NULL
                 LEFT JOIN users u ON asn.user_id = u.user_id
                 WHERE $where_sql ORDER BY a.asset_id DESC";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV) - EXECUTED BEFORE HTML
   ========================================================= */
if (isset($_GET['export'])) {
    
    // 1. Ruthlessly wipe ANY hidden spaces, HTML, or warnings
    while (ob_get_level()) {
        ob_end_clean();
    }

    $export_res = mysqli_query($conn, $assets_query);
    if (!$export_res) {
        die("Export Error - SQL Failed: " . mysqli_error($conn));
    }
    
    $clean_cat_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $category['category_name']);
    $filename = "Category_Assets_" . $clean_cat_name . "_" . date('Y-m-d');

    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><style>td{border:0.5pt solid #000;} th{background-color:#D3D3D3; font-weight:bold; border:0.5pt solid #000;}</style></head><body>';
        echo '<table><tr><th colspan="6" style="font-size:14pt; text-align:left;">Assets in Category: ' . htmlspecialchars($category['category_name']) . '</th></tr>';
        echo '<tr><th>Asset Name</th><th>User Name</th><th>Serial No</th><th>Model</th><th>Status</th><th>Location</th></tr>';
        
        while ($r = mysqli_fetch_assoc($export_res)) {
            echo "<tr>
                    <td>{$r['asset_name']}</td>
                    <td>" . ($r['user_name'] ?? '-') . "</td>
                    <td>{$r['serial_number']}</td>
                    <td>" . ($r['model_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['status_name'] ?? 'N/A') . "</td>
                    <td>" . ($r['dept_name'] ?? 'N/A') . "</td>
                  </tr>";
        }
        echo '</table></body></html>';
        exit();
    }
    
    if ($_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Asset Name', 'User Name', 'Serial No', 'Model', 'Status', 'Location']);
        
        while ($r = mysqli_fetch_assoc($export_res)) {
            fputcsv($output, [
                $r['asset_name'],
                $r['user_name'] ?? '-',
                $r['serial_number'],
                $r['model_name'] ?? 'N/A',
                $r['status_name'] ?? 'N/A',
                $r['dept_name'] ?? 'N/A'
            ]);
        }
        fclose($output);
        exit();
    }
}

// Flush normal HTML if not exporting
if (ob_get_length()) {
    ob_end_flush();
}

$assets_result = mysqli_query($conn, $assets_query);
$filtered_count = mysqli_num_rows($assets_result);

$total_assets_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets a WHERE $category_condition");
$total_assets = mysqli_fetch_assoc($total_assets_query)['total'];

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <!-- PAGE HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Category Profile</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="categories_list.php" class="text-decoration-none">Categories</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($category['category_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <!-- Export Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</a></li>
                    <!-- UPDATED LINKS: Added explicit categories_details.php -->
                    <li><a class="dropdown-item" href="categories_details.php?id=<?= $id ?>&model=<?= urlencode($model) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&export=excel"><i class="bi bi-file-earmark-excel text-success"></i> Excel</a></li>
                    <li><a class="dropdown-item" href="categories_details.php?id=<?= $id ?>&model=<?= urlencode($model) ?>&status=<?= urlencode($status) ?>&location=<?= urlencode($location) ?>&export=csv"><i class="bi bi-file-earmark-text text-primary"></i> CSV</a></li>
                </ul>
            </div>
            <a href="categories_edit.php?id=<?= $category['category_id'] ?>" class="btn btn-warning"><i class="bi bi-pencil-square"></i> Edit Category</a>
            <a href="categories_list.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: CATEGORY INFO -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-folder-fill me-2"></i> Category Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tbody>
                            <tr>
                                <th width="40%" class="text-muted">Name:</th>
                                <td class="fw-bold fs-5"><?= htmlspecialchars($category['category_name']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Type:</th>
                                <td>
                                    <?php if ($is_main_category): ?>
                                        <span class="badge bg-primary">Main Category</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sub Category</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!$is_main_category): ?>
                            <tr>
                                <th class="text-muted">Parent Category:</th>
                                <td><a href="categories_details.php?id=<?= $category['parent_id'] ?>" class="text-decoration-none fw-bold"><?= htmlspecialchars($category['parent_name']) ?></a></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th class="text-muted">Description:</th>
                                <td><?= htmlspecialchars($category['description'] ?? 'No description provided.') ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Created At:</th>
                                <td><?= !empty($category['created_at']) ? date('d M Y, h:i A', strtotime($category['created_at'])) : 'N/A' ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-info">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Assets in this Category</h6>
                    <h2 class="display-4 fw-bold text-info"><?= $total_assets ?></h2>
                </div>
            </div>

            <?php if ($is_main_category): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i> Sub-Categories</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if (count($sub_cats) > 0): ?>
                            <?php foreach($sub_cats as $sc): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <a href="categories_details.php?id=<?= $sc['category_id'] ?>" class="text-decoration-none fw-bold">
                                        <i class="bi bi-arrow-return-right text-muted me-2"></i> <?= htmlspecialchars($sc['category_name']) ?>
                                    </a>
                                    <a href="categories_details.php?id=<?= $sc['category_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center text-muted py-3">No sub-categories created yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN: SEARCH, FILTER & ASSETS LIST -->
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="col-md-3">
                            <label class="form-label small">Model</label>
                            <select name="model" class="form-select form-select-sm">
                                <option value="">All Models</option>
                                <?php
                                $mods = mysqli_query($conn, "SELECT DISTINCT m.model_id, m.model_name 
                                                             FROM asset_models m 
                                                             JOIN assets a ON m.model_id = a.model_id 
                                                             WHERE $category_condition ORDER BY m.model_name ASC");
                                while ($m = mysqli_fetch_assoc($mods)) {
                                    $sel = ($model == $m['model_id']) ? "selected" : "";
                                    echo "<option value='{$m['model_id']}' $sel>{$m['model_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Status</option>
                                <?php
                                $sts_query = "SELECT DISTINCT s.status_id, s.status_name 
                                              FROM asset_status s
                                              JOIN assets a ON s.status_id = a.status_id
                                              WHERE $category_condition
                                              ORDER BY s.status_name ASC";
                                $sts_res = mysqli_query($conn, $sts_query);
                                while($s = mysqli_fetch_assoc($sts_res)){
                                    $selected = ($status == $s['status_id']) ? "selected" : "";
                                    echo "<option value='{$s['status_id']}' $selected>{$s['status_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small">Location</label>
                            <select name="location" class="form-select form-select-sm">
                                <option value="">All Locations</option>
                                <?php
                                $loc_query = "SELECT DISTINCT l.location_id, l.dept_name 
                                              FROM locations l
                                              JOIN assets a ON l.location_id = a.location_id
                                              WHERE $category_condition
                                              ORDER BY l.dept_name ASC";
                                $loc_res = mysqli_query($conn, $loc_query);
                                while($l = mysqli_fetch_assoc($loc_res)){
                                    $selected = ($location == $l['location_id']) ? "selected" : "";
                                    echo "<option value='{$l['location_id']}' $selected>{$l['dept_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm me-1 w-100">Filter</button>
                            <a href="categories_details.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm w-100" title="Reset Filters">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Assets under "<?= htmlspecialchars($category['category_name']) ?>"</h5>
                    <span class="badge bg-dark"><?= $filtered_count ?> Results</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" id="assetsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Asset Name</th>
                                    <th>User Name</th>
                                    <th>Serial No</th>
                                    <th>Model</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th class="text-center no-export">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($filtered_count > 0): ?>
                                    <?php while($asset = mysqli_fetch_assoc($assets_result)): ?>
                                        <tr>
                                            <td class="fw-bold"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                            <td><?= htmlspecialchars($asset['user_name'] ?? '-') ?></td>
                                            <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                                            <td><?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?></td>
                                            <td><span class="badge bg-info text-dark"><?= htmlspecialchars($asset['status_name'] ?? 'N/A') ?></span></td>
                                            <td><?= htmlspecialchars($asset['dept_name'] ?? 'N/A') ?></td>
                                            <td class="text-center no-export">
                                                <a href="../assets/asset_details.php?id=<?= $asset['asset_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No assets found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================
     PDF EXPORT SCRIPT
     ========================================================= -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') {
            alert("Error: The PDF library failed to load. Please check your internet connection.");
            return;
        }

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        doc.setFontSize(16);
        doc.text("Assets in Category: <?= addslashes($category['category_name']) ?>", 14, 15);

        // Temporarily hide the Action column
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = 'none';
        });

        doc.autoTable({
            html: '#assetsTable',
            startY: 25,
            styles: {
                fontSize: 9,
                cellPadding: 2
            },
            headStyles: {
                fillColor: [52, 58, 64]
            }
        });

        // Restore the Action column in the HTML view
        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        const safeFilename = "<?= addslashes($category['category_name']) ?>".replace(/[^a-zA-Z0-9_-]/g, "_");
        doc.save("Category_Assets_" + safeFilename + "_<?= date('Y-m-d') ?>.pdf");
    }
</script>

<?php include("../../includes/footer.php"); ?>