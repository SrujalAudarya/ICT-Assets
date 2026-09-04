<?php
global $conn;

include("../../includes/auth.php");
include("../../config/db.php");

// Filter & Search values
$search = trim($_GET['search'] ?? '');
$tab = $_GET['tab'] ?? 'final'; // 'final' or 'provisional'

// Base query based on the selected tab type
if ($tab === 'provisional') {
    // Shows models that are Provisional Survey Off where ALL assets are marked as 'Not In Use' via asset_state
    $query = "SELECT m.*, 
              c.category_name, 
              pc.category_name AS parent_category_name,
              v.vendor_name,
              s.status_name,
              SUM(CASE WHEN a.asset_state = 'Not In Use' THEN 1 ELSE 0 END) AS total_assets
              FROM asset_models m
              LEFT JOIN asset_categories c ON m.category_id = c.category_id
              LEFT JOIN asset_categories pc ON c.parent_id = pc.category_id
              LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
              LEFT JOIN asset_status s ON m.status_id = s.status_id
              LEFT JOIN assets a ON m.model_id = a.model_id
              WHERE s.status_name = 'Provisional Survey Off'
              GROUP BY m.model_id
              HAVING SUM(CASE WHEN a.asset_state != 'Not In Use' THEN 1 ELSE 0 END) = 0";
} else {
    // Shows Final Survey Off models
    $query = "SELECT m.*, 
              c.category_name, 
              pc.category_name AS parent_category_name,
              v.vendor_name,
              s.status_name,
              COUNT(a.asset_id) AS total_assets
              FROM asset_models m
              LEFT JOIN asset_categories c ON m.category_id = c.category_id
              LEFT JOIN asset_categories pc ON c.parent_id = pc.category_id
              LEFT JOIN vendors v ON m.vendor_id = v.vendor_id
              LEFT JOIN asset_status s ON m.status_id = s.status_id
              LEFT JOIN assets a ON m.model_id = a.model_id
              WHERE s.status_name = 'Final Survey Off'
              GROUP BY m.model_id";
}

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $query .= " HAVING (m.model_name LIKE '%$search_esc%' OR c.category_name LIKE '%$search_esc%' OR v.vendor_name LIKE '%$search_esc%' OR m.make_name LIKE '%$search_esc%')";
}

$query .= " ORDER BY m.model_id ASC";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV)
   ========================================================= */
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'excel'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $export_res = mysqli_query($conn, $query);
    $filename = "Trash_Models_" . ucfirst($tab) . "_" . date('Y-m-d');
    $isExcel = ($_GET['export'] === 'excel');

    if ($isExcel) {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        $delimiter = "\t";
    } else {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $delimiter = ",";
    }

    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Model Name', 'Category', 'Make', 'Purchase Date', 'Expiry Date', 'Quantity', 'Unit Price', 'Total Assets'], $delimiter);

    $sr = 1;
    while ($r = mysqli_fetch_assoc($export_res)) {
        $catName = !empty($r['parent_category_name']) ? $r['parent_category_name'] . ' > ' . $r['category_name'] : ($r['category_name'] ?? 'N/A');
        fputcsv($output, [
            $sr++,
            $r['model_name'],
            $catName,
            $r['make_name'] ?? 'N/A',
            $r['purchase_date'] ?? 'N/A',
            $r['expiry_date'] ?? 'N/A',
            $r['quantity'] ?? 0,
            $r['cost'] ?? 0,
            $r['total_assets'] ?? 0
        ], $delimiter);
    }
    fclose($output);
    exit();
}

$result = mysqli_query($conn, $query);

$exportParams = $_GET;
$exportParams['export'] = 'excel';
$exportExcelUrl = '?' . http_build_query($exportParams);
$exportParams['export'] = 'csv';
$exportCsvUrl = '?' . http_build_query($exportParams);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2><i class="bi bi-trash3 text-danger me-2"></i> Trash Bin Management</h2>
            <p class="text-muted small mb-0">Decommissioned models and inactive survey assets.</p>
        </div>
        
        <div class="d-flex gap-2 flex-wrap">
            <!-- EXPORT DROPDOWN -->
            <div class="dropdown position-relative d-inline-block">
                <button class="btn btn-light bg-white border border-secondary text-dark dropdown-toggle fw-bold shadow-sm" type="button" id="btnExportDropdown">
                    <i class="bi bi-download me-1"></i> Export List
                </button>
                <ul class="dropdown-menu shadow" id="exportDropdownMenu" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1000;">
                    <li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger me-2"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportExcelUrl ?>"><i class="bi bi-file-earmark-excel text-success me-2"></i> Export as Excel (.xls)</a></li>
                    <li><a class="dropdown-item py-2 fw-bold" href="<?= $exportCsvUrl ?>"><i class="bi bi-file-earmark-text text-primary me-2"></i> Export as CSV</a></li>
                </ul>
            </div>

            <!-- TABS FOR SWITCHING VIEWS -->
            <div class="btn-group shadow-sm">
                <a href="trash_bin.php?tab=final" class="btn <?= $tab === 'final' ? 'btn-dark' : 'btn-outline-dark' ?>">
                    <i class="bi bi-clipboard-x-fill me-1"></i> Final Survey Off
                </a>
                <a href="trash_bin.php?tab=provisional" class="btn <?= $tab === 'provisional' ? 'btn-info text-dark fw-bold' : 'btn-outline-info text-dark' ?>">
                    <i class="bi bi-clipboard-minus-fill me-1"></i> Provisional (Not In Use)
                </a>
            </div>
        </div>
    </div>

    <!-- NOTIFICATION ALERTS -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'restored'): ?>
            <div class="alert alert-success shadow-sm border-0 d-flex align-items-center mb-4">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i> Model successfully restored to active inventory.
            </div>
        <?php elseif ($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                <i class="bi bi-trash-fill me-2 fs-5"></i> Model and associated files permanently deleted.
            </div>
        <?php elseif ($_GET['msg'] == 'error'): ?>
            <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i> An error occurred while processing your request.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- SEARCH BAR -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <div class="col-md-6">
                    <label class="form-label small">Search Trash</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by model, make, vendor..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-dark w-100">Search</button>
                    <a href="trash_bin.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- TRASH TABLE -->
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" id="trashTable" style="white-space: nowrap;">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th class="no-export">Logo</th>
                            <th>Model Name</th>
                            <th>Category</th>
                            <th>Make</th>
                            <th>Pur. Date</th>
                            <th>Exp. Date</th>
                            <th class="text-center">Qyt</th>
                            <th>Price</th>
                            <th class="text-center">Total Assets</th>
                            <th class="text-center no-export">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if($result && mysqli_num_rows($result) > 0): ?>
                        <?php $sr = 1; while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?= $sr++ ?></td>
                                <td class="text-center no-export" style="width: 50px;">
                                    <?php if (!empty($row['model_image'])): ?>
                                        <img src="../../<?= htmlspecialchars($row['model_image']) ?>" alt="Logo" style="height: 35px; width: 35px; object-fit: contain;" class="rounded border p-1 bg-white">
                                    <?php else: ?>
                                        <div class="bg-light border text-muted d-flex align-items-center justify-content-center rounded mx-auto" style="height: 35px; width: 35px;">
                                            <i class="bi bi-image fs-6"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">
                                    <a href="../models/models_details.php?id=<?= $row['model_id'] ?>" class="text-danger text-decoration-none">
                                        <?= htmlspecialchars($row['model_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                        if (!empty($row['parent_category_name'])) {
                                            echo htmlspecialchars($row['parent_category_name']) . ' &raquo; ' . htmlspecialchars($row['category_name']);
                                        } else {
                                            echo htmlspecialchars($row['category_name'] ?? '-');
                                        }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($row['make_name'] ?? '-') ?></td>
                                <td><?= !empty($row['purchase_date']) ? date('d M Y', strtotime($row['purchase_date'])) : '-' ?></td>
                                <td><?= !empty($row['expiry_date']) ? date('d M Y', strtotime($row['expiry_date'])) : '-' ?></td>
                                <td class="text-center fw-bold"><?= (int)($row['quantity'] ?? 0) ?></td>
                                <td class="text-success fw-bold">₹ <?= number_format((float)($row['cost'] ?? 0), 2) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?= $row['total_assets'] ?></span>
                                </td>
                                <td class="text-center no-export">
                                    <div class="btn-group btn-group-sm">
                                        <!-- RESTORE BUTTON -->
                                        <a href="trash_restore.php?id=<?= $row['model_id'] ?>" class="btn btn-success" onclick="return confirm('Restore this model back to active inventory?')" title="Restore Model">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </a>
                                        
                                        <!-- PERMANENT DELETE BUTTON (Hidden for Provisional Tab) -->
                                        <?php if ($tab !== 'provisional'): ?>
                                            <a href="trash_delete.php?id=<?= $row['model_id'] ?>" class="btn btn-outline-danger" onclick="return confirm('WARNING: This will permanently delete the model and its files. This action cannot be undone!')" title="Delete Permanently">
                                                <i class="bi bi-trash-fill"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-trash fs-2 d-block mb-2"></i> Trash bin is empty for this view.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const exportBtn = document.getElementById("btnExportDropdown");
        const exportMenu = document.getElementById("exportDropdownMenu");

        if (exportBtn && exportMenu) {
            exportBtn.addEventListener("click", function(e) {
                e.stopPropagation();
                exportMenu.style.display = (exportMenu.style.display === "block") ? "none" : "block";
            });

            document.addEventListener("click", function(e) {
                if (!exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
                    exportMenu.style.display = "none";
                }
            });
        }
    });

    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') {
            alert("PDF library is still loading. Please wait a moment.");
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        doc.setFontSize(16);
        doc.text("Trash Bin Models (<?= ucfirst($tab) ?>)", 14, 15);

        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = 'none';
        });

        doc.autoTable({
            html: '#trashTable',
            startY: 25,
            styles: { fontSize: 9, cellPadding: 3 },
            headStyles: { fillColor: [52, 58, 64] }
        });

        document.querySelectorAll('.no-export').forEach(function(el) {
            el.style.display = '';
        });

        doc.save("Trash_Models_<?= ucfirst($tab) ?>_<?= date('Y-m-d') ?>.pdf");
    }
</script>

<?php include("../../includes/footer.php"); ?>