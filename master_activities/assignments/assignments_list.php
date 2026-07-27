<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$tab = $_GET['tab'] ?? 'active'; // 'active' or 'history'
$search = trim($_GET['search'] ?? '');

// =========================================================
// PAGINATION SETUP
// =========================================================
$limit = 15; // Number of entries per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base Condition
$where_clauses = [];
if ($tab == 'active') {
    $where_clauses[] = "asn.returned_date IS NULL";
} else {
    $where_clauses[] = "asn.returned_date IS NOT NULL";
}

if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($conn, $search);
    $where_clauses[] = "(a.asset_name LIKE '%$search_esc%' OR a.serial_number LIKE '%$search_esc%' OR u.name LIKE '%$search_esc%')";
}

$where_sql = implode(' AND ', $where_clauses);

// Base Query (Used for Exports to get ALL matching data)
$query = "SELECT asn.*, a.asset_name, a.serial_number, u.name AS user_name, c.category_name
          FROM asset_assignments asn
          JOIN assets a ON asn.asset_id = a.asset_id
          LEFT JOIN asset_categories c ON a.category_id = c.category_id
          JOIN users u ON asn.user_id = u.user_id
          WHERE $where_sql 
          ORDER BY asn.assigned_date DESC, asn.assignment_id DESC";

/* =========================================================
   EXPORT LOGIC (EXCEL & CSV) - Exports ALL filtered records
   ========================================================= */
if (isset($_GET['export'])) {
    while (ob_get_level()) ob_end_clean();
    $export_res = mysqli_query($conn, $query);
    $filename = "Assignments_" . ucfirst($tab) . "_" . date('Y-m-d');

    if ($_GET['export'] == 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo '<table border="1"><tr><th>Asset Name</th><th>Serial No</th><th>Category</th><th>Assigned To</th><th>Assigned Date</th><th>Returned Date</th><th>Remarks</th></tr>';
        while ($r = mysqli_fetch_assoc($export_res)) {
            $ret_date = $r['returned_date'] ? date('d M Y', strtotime($r['returned_date'])) : 'Not Returned';
            echo "<tr>
                    <td>{$r['asset_name']}</td>
                    <td>{$r['serial_number']}</td>
                    <td>{$r['category_name']}</td>
                    <td>{$r['user_name']}</td>
                    <td>" . date('d M Y', strtotime($r['assigned_date'])) . "</td>
                    <td>{$ret_date}</td>
                    <td>{$r['remarks']}</td>
                  </tr>";
        }
        echo '</table>';
        exit();
    }
    
    if ($_GET['export'] == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Asset Name', 'Serial No', 'Category', 'Assigned To', 'Assigned Date', 'Returned Date', 'Remarks']);
        while ($r = mysqli_fetch_assoc($export_res)) {
            $ret_date = $r['returned_date'] ? date('d M Y', strtotime($r['returned_date'])) : 'Not Returned';
            fputcsv($output, [$r['asset_name'], $r['serial_number'], $r['category_name'], $r['user_name'], date('d M Y', strtotime($r['assigned_date'])), $ret_date, $r['remarks']]);
        }
        fclose($output);
        exit();
    }
}

if (ob_get_length()) ob_end_flush();

/* =========================================================
   PAGINATION EXECUTION (For HTML View Only)
   ========================================================= */
// 1. Get total records for current filter to calculate total pages
$count_query = "SELECT COUNT(*) as total
                FROM asset_assignments asn
                JOIN assets a ON asn.asset_id = a.asset_id
                JOIN users u ON asn.user_id = u.user_id
                WHERE $where_sql";
$count_res = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_records / $limit);

// 2. Fetch paginated records
$paginated_query = $query . " LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $paginated_query);
$count = mysqli_num_rows($result);

// Get Overall Tab Counts (for the badges in the tabs)
$active_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM asset_assignments WHERE returned_date IS NULL"))['c'];
$history_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM asset_assignments WHERE returned_date IS NOT NULL"))['c'];

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0"><i class="bi bi-list-check text-primary me-2"></i> Asset Assignments</h3>
        <div class="d-flex gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF (Current Page)</a></li>
                    <li><a class="dropdown-item" href="?tab=<?= $tab ?>&search=<?= urlencode($search) ?>&export=excel"><i class="bi bi-file-earmark-excel text-success"></i> Excel (All Records)</a></li>
                    <li><a class="dropdown-item" href="?tab=<?= $tab ?>&search=<?= urlencode($search) ?>&export=csv"><i class="bi bi-file-earmark-text text-primary"></i> CSV (All Records)</a></li>
                </ul>
            </div>
            <a href="assign_asset.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> New Assignment</a>
        </div>
    </div>

    <!-- TABS -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link fw-bold <?= $tab == 'active' ? 'active text-primary' : 'text-muted' ?>" href="?tab=active&search=<?= urlencode($search) ?>">
                <i class="bi bi-person-workspace me-1"></i> Active Assignments 
                <span class="badge bg-<?= $tab == 'active' ? 'primary' : 'secondary' ?> ms-1"><?= $active_count ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold <?= $tab == 'history' ? 'active text-primary' : 'text-muted' ?>" href="?tab=history&search=<?= urlencode($search) ?>">
                <i class="bi bi-clock-history me-1"></i> Assignment History 
                <span class="badge bg-<?= $tab == 'history' ? 'primary' : 'secondary' ?> ms-1"><?= $history_count ?></span>
            </a>
        </li>
    </ul>

    <!-- FILTER -->
    <div class="card shadow-sm mb-4 border-0 bg-light">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-center">
                <input type="hidden" name="tab" value="<?= $tab ?>">
                <div class="col-md-8">
                    <input type="text" name="search" class="form-control" placeholder="Search by Asset, Serial No, or Employee Name..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                    <a href="?tab=<?= $tab ?>" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLE -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="assignmentsTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Asset Details</th>
                            <th>Assigned To</th>
                            <th>Assigned Date</th>
                            <?php if($tab == 'history'): ?><th>Returned Date</th><?php endif; ?>
                            <th>Remarks</th>
                            <th class="text-center no-export">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($count > 0): while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($row['asset_name']) ?></div>
                                    <code class="small"><?= htmlspecialchars($row['serial_number']) ?></code>
                                </td>
                                <td class="fw-bold text-primary"><i class="bi bi-person-fill text-muted me-1"></i> <?= htmlspecialchars($row['user_name']) ?></td>
                                <td><?= date('d M Y', strtotime($row['assigned_date'])) ?></td>
                                
                                <?php if($tab == 'history'): ?>
                                    <td class="text-success fw-bold"><?= date('d M Y', strtotime($row['returned_date'])) ?></td>
                                <?php endif; ?>
                                
                                <td class="text-muted small"><?= htmlspecialchars($row['remarks'] ?? '-') ?></td>
                                <td class="text-center no-export">
                                    <div class="btn-group btn-group-sm">
                                        <a href="assignment_details.php?id=<?= $row['asset_id'] ?>" class="btn btn-info text-white" title="View Asset"><i class="bi bi-eye"></i></a>
                                        <a href="assignment_edit.php?id=<?= $row['assignment_id'] ?>" class="btn btn-warning" title="Edit Record"><i class="bi bi-pencil"></i></a>
                                        
                                        <?php if($tab == 'active'): ?>
                                            <a href="return_asset.php?id=<?= $row['assignment_id'] ?>" class="btn btn-success" title="Return Asset"><i class="bi bi-arrow-return-left"></i> Return</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?= ($tab == 'history') ? '6' : '5' ?>" class="text-center text-muted py-4">No assignments found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- PAGINATION UI -->
        <?php if ($total_pages > 0): ?>
        <div class="card-footer bg-white border-0 py-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="text-muted small mb-2 mb-md-0">
                    Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $limit, $total_records) ?></strong> of <strong><?= $total_records ?></strong> entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php 
                        $base_url = "?tab=$tab&search=" . urlencode($search);
                        ?>
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>&page=<?= $page - 1 ?>">Previous</a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $base_url ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $base_url ?>&page=<?= $page + 1 ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<script>
    function exportToPDF() {
        if (typeof window.jspdf === 'undefined') return alert("PDF library failed to load.");
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        doc.setFontSize(14);
        doc.text("Asset Assignments (<?= ucfirst($tab) ?> - Page <?= $page ?>)", 14, 15);
        document.querySelectorAll('.no-export').forEach(el => el.style.display = 'none');
        doc.autoTable({ html: '#assignmentsTable', startY: 20, styles: { fontSize: 9 } });
        document.querySelectorAll('.no-export').forEach(el => el.style.display = '');
        doc.save("Assignments_<?= ucfirst($tab) ?>_Pg<?= $page ?>_<?= date('Y-m-d') ?>.pdf");
    }
</script>
<?php include("../../includes/footer.php"); ?>