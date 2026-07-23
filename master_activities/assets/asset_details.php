<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if ($id == '') {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Invalid asset ID.</div></div>";
    exit();
}

/* =========================================================
   ASSET BASIC INFO
   ========================================================= */
$asset_query = "
    SELECT 
        a.*,
        c.category_name,
        s.status_name,
        l.dept_name,
        l.floor,
        v.vendor_name,
        m.model_name
    FROM assets a
    LEFT JOIN asset_categories c ON a.category_id = c.category_id
    LEFT JOIN asset_status s ON a.status_id = s.status_id
    LEFT JOIN locations l ON a.location_id = l.location_id
    LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
    LEFT JOIN asset_models m ON a.model_id = m.model_id
    WHERE a.asset_id = '$id'
    LIMIT 1
";
$asset_result = mysqli_query($conn, $asset_query);
$asset = mysqli_fetch_assoc($asset_result);

if (!$asset) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Asset not found.</div></div>";
    exit();
}

/* =========================================================
   SAFE LIFECYCLE / WARRANTY CALCULATIONS
   ========================================================= */
$today = new DateTime();

$age_str = 'N/A';
if (!empty($asset['purchase_date']) && $asset['purchase_date'] != '0000-00-00') {
    $purchase_date = new DateTime($asset['purchase_date']);
    $age = $purchase_date->diff($today);
    $age_str = $age->y . " Years, " . $age->m . " Months";
}

$is_warranty_active = false;
$warranty_status = 'N/A';
if (!empty($asset['warranty_expiry']) && $asset['warranty_expiry'] != '0000-00-00') {
    $warranty_expiry = new DateTime($asset['warranty_expiry']);
    $is_warranty_active = ($warranty_expiry >= $today);
    $warranty_diff = $today->diff($warranty_expiry);
    $warranty_status = $is_warranty_active ? $warranty_diff->days . " Days Remaining" : "Expired";
}

/* =========================================================
   CURRENT ACTIVE ASSIGNMENT
   ========================================================= */
$current_assignment_q = mysqli_query($conn, "
    SELECT aa.*, u.name, u.email, u.role, u.user_id
    FROM asset_assignments aa
    JOIN users u ON aa.user_id = u.user_id
    WHERE aa.asset_id = '$id' AND aa.returned_date IS NULL
    ORDER BY aa.assignment_id DESC
    LIMIT 1
");
$current_assignment = mysqli_fetch_assoc($current_assignment_q);

/* =========================================================
   ASSIGNMENT HISTORY
   ========================================================= */
$assignments_res = mysqli_query($conn, "
    SELECT aa.*, u.name
    FROM asset_assignments aa
    JOIN users u ON aa.user_id = u.user_id
    WHERE aa.asset_id = '$id'
    ORDER BY aa.assignment_id DESC
");
$assignments = [];
while ($row = mysqli_fetch_assoc($assignments_res)) {
    $assignments[] = $row;
}

/* =========================================================
   EXPORT LOGIC
   ========================================================= */
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $clean_asset_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $asset['asset_name']);
    $filename = "Asset_Profile_" . $clean_asset_name . "_" . date('Y-m-d');

    /* ---------------- EXCEL EXPORT ---------------- */
    if ($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><style>
                .header{font-size:16pt; font-weight:bold; background-color:#007bff; color:#fff;}
                .section-head{background-color:#eee; font-weight:bold; border:0.5pt solid #000;}
                td{border:0.5pt solid #000; padding:4px;}
                th{background-color:#D3D3D3; font-weight:bold; border:0.5pt solid #000; padding:4px;}
              </style></head><body>';

        echo '<table>';
        echo '<tr><th colspan="5" class="header">Asset Comprehensive Profile</th></tr>';
        echo '<tr><th colspan="5">Generated on: ' . date('d M Y H:i') . '</th></tr>';
        echo '<tr></tr>';

        // Technical Specifications
        echo '<tr><th colspan="5" class="section-head">Technical Specifications</th></tr>';
        echo '<tr><td>Asset Name</td><td colspan="4">' . htmlspecialchars($asset['asset_name']) . '</td></tr>';
        echo '<tr><td>Serial Number</td><td colspan="4">' . htmlspecialchars($asset['serial_number']) . '</td></tr>';
        echo '<tr><td>Category</td><td colspan="4">' . htmlspecialchars($asset['category_name'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Model</td><td colspan="4">' . htmlspecialchars($asset['model_name'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Current Status</td><td colspan="4">' . htmlspecialchars($asset['status_name'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Location</td><td colspan="4">' . htmlspecialchars($asset['dept_name'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Vendor</td><td colspan="4">' . htmlspecialchars($asset['vendor_name'] ?? 'N/A') . '</td></tr>';

        echo '<tr></tr>';
        echo '<tr><th colspan="5" class="section-head">Lifecycle & Cost</th></tr>';
        echo '<tr><td>Purchase Date</td><td colspan="4">' . (!empty($asset['purchase_date']) && $asset['purchase_date'] != '0000-00-00' ? $asset['purchase_date'] : 'N/A') . '</td></tr>';
        echo '<tr><td>Warranty Expiry</td><td colspan="4">' . (!empty($asset['warranty_expiry']) && $asset['warranty_expiry'] != '0000-00-00' ? $asset['warranty_expiry'] : 'N/A') . '</td></tr>';
        echo '<tr><td>Initial Cost</td><td colspan="4">₹ ' . number_format((float)$asset['cost'], 2) . '</td></tr>';
        echo '<tr><td>Asset Age</td><td colspan="4">' . $age_str . '</td></tr>';
        echo '<tr><td>Warranty Status</td><td colspan="4">' . $warranty_status . '</td></tr>';

        echo '<tr></tr>';
        echo '<tr><th colspan="5" class="section-head">Current Assignment</th></tr>';
        if ($current_assignment) {
            echo '<tr><td>Assigned To</td><td colspan="4">' . htmlspecialchars($current_assignment['name']) . '</td></tr>';
            echo '<tr><td>Assigned Date</td><td colspan="4">' . htmlspecialchars($current_assignment['assigned_date']) . '</td></tr>';
            echo '<tr><td>Role</td><td colspan="4">' . htmlspecialchars($current_assignment['role']) . '</td></tr>';
            echo '<tr><td>Remarks</td><td colspan="4">' . htmlspecialchars($current_assignment['remarks'] ?: '-') . '</td></tr>';
        } else {
            echo '<tr><td colspan="5">This asset is currently not assigned.</td></tr>';
        }

        echo '<tr></tr>';
        echo '<tr><th colspan="5" class="section-head">Assignment History</th></tr>';
        echo '<tr>
                <th>User</th>
                <th>Assigned Date</th>
                <th>Returned Date</th>
                <th>Status</th>
                <th>Remarks</th>
              </tr>';

        if (!empty($assignments)) {
            foreach ($assignments as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['assigned_date']) . '</td>';
                echo '<td>' . (!empty($row['returned_date']) ? htmlspecialchars($row['returned_date']) : '-') . '</td>';
                echo '<td>' . (!empty($row['returned_date']) ? 'Returned' : 'Active') . '</td>';
                echo '<td>' . htmlspecialchars($row['remarks'] ?: '-') . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">No assignment history found.</td></tr>';
        }

        echo '</table></body></html>';
        exit();
    }

    /* ---------------- CSV EXPORT ---------------- */
    if ($format == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, ['ASSET PROFILE', $asset['asset_name']]);
        fputcsv($output, ['Serial Number', $asset['serial_number']]);
        fputcsv($output, ['Category', $asset['category_name'] ?? 'N/A']);
        fputcsv($output, ['Model', $asset['model_name'] ?? 'N/A']);
        fputcsv($output, ['Status', $asset['status_name'] ?? 'N/A']);
        fputcsv($output, ['Location', $asset['dept_name'] ?? 'N/A']);
        fputcsv($output, ['Vendor', $asset['vendor_name'] ?? 'N/A']);
        fputcsv($output, ['Purchase Date', $asset['purchase_date'] ?? '']);
        fputcsv($output, ['Warranty Expiry', $asset['warranty_expiry'] ?? '']);
        fputcsv($output, ['Cost', $asset['cost'] ?? '']);
        fputcsv($output, ['Asset Age', $age_str]);
        fputcsv($output, ['Warranty Status', $warranty_status]);

        fputcsv($output, []);
        fputcsv($output, ['CURRENT ASSIGNMENT']);

        if ($current_assignment) {
            fputcsv($output, ['Assigned To', $current_assignment['name']]);
            fputcsv($output, ['Assigned Date', $current_assignment['assigned_date']]);
            fputcsv($output, ['Role', $current_assignment['role']]);
            fputcsv($output, ['Remarks', $current_assignment['remarks']]);
        } else {
            fputcsv($output, ['This asset is currently not assigned']);
        }

        fputcsv($output, []);
        fputcsv($output, ['ASSIGNMENT HISTORY']);
        fputcsv($output, ['User', 'Assigned Date', 'Returned Date', 'Status', 'Remarks']);

        if (!empty($assignments)) {
            foreach ($assignments as $row) {
                fputcsv($output, [
                    $row['name'],
                    $row['assigned_date'],
                    $row['returned_date'] ?: '-',
                    $row['returned_date'] ? 'Returned' : 'Active',
                    $row['remarks'] ?: '-'
                ]);
            }
        }

        fclose($output);
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Asset Profile: <?= htmlspecialchars($asset['asset_name']) ?></h2>

        <div class="d-flex gap-2 flex-wrap">
            <?php if ($current_assignment): ?>
                <a href="../assignments/return_asset.php?id=<?= $current_assignment['assignment_id'] ?>"
                    class="btn btn-danger"
                    onclick="return confirm('Mark this asset as returned?')">
                    Return Asset
                </a>
            <?php else: ?>
                <a href="../assignments/assign_asset.php?asset_id=<?= $id ?>" class="btn btn-success">
                    Assign Asset
                </a>
            <?php endif; ?>

            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export Profile
                </button>
                <ul class="dropdown-menu shadow">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0)" onclick="exportToPDF()">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> Export as PDF
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="?id=<?= $id ?>&export=excel">
                            <i class="bi bi-file-earmark-excel text-success"></i> Export as Excel (.xls)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="?id=<?= $id ?>&export=csv">
                            <i class="bi bi-file-earmark-text text-primary"></i> Export as CSV
                        </a>
                    </li>
                </ul>
            </div>

            <a href="assets_edit.php?id=<?= $id ?>" class="btn btn-warning">Edit Asset</a>
            <a href="assets_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN -->
        <div class="col-md-4">
            <!-- TECHNICAL SPECIFICATIONS -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Technical Specifications</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Serial No</th>
                            <td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td>
                        </tr>
                        <tr>
                            <th>Category</th>
                            <td><?= htmlspecialchars($asset['category_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Model</th>
                            <td><?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="badge bg-info"><?= htmlspecialchars($asset['status_name'] ?? 'N/A') ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th>Department</th>
                            <td><?= htmlspecialchars($asset['dept_name'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Floor</th>
                            <td><?= htmlspecialchars($asset['floor'] ?? 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th>Vendor</th>
                            <td><?= htmlspecialchars($asset['vendor_name'] ?: 'N/A') ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- LIFECYCLE -->
            <div class="card shadow-sm mb-4 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Lifecycle Intelligence</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr>
                            <th width="40%">Purchase Date</th>
                            <td>
                                <?= (!empty($asset['purchase_date']) && $asset['purchase_date'] != '0000-00-00')
                                    ? date('d M Y', strtotime($asset['purchase_date']))
                                    : 'N/A' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Asset Age</th>
                            <td><?= $age_str ?></td>
                        </tr>
                        <tr>
                            <th>Warranty Expiry</th>
                            <td>
                                <?= (!empty($asset['warranty_expiry']) && $asset['warranty_expiry'] != '0000-00-00')
                                    ? date('d M Y', strtotime($asset['warranty_expiry']))
                                    : 'N/A' ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Warranty Status</th>
                            <td>
                                <?php if ($warranty_status == 'N/A'): ?>
                                    <span class="badge bg-secondary">N/A</span>
                                <?php else: ?>
                                    <span class="badge <?= $is_warranty_active ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $warranty_status ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Initial Cost</th>
                            <td class="fw-bold text-primary">₹ <?= number_format((float)$asset['cost'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- CURRENT ASSIGNMENT -->
            <div class="card shadow-sm mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Current Assignment</h5>
                </div>
                <div class="card-body">
                    <?php if ($current_assignment): ?>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Assigned To</label>
                            <a href="../users/users_view.php?id=<?= $current_assignment['user_id'] ?>" class="fw-bold text-decoration-none">
                                <?= htmlspecialchars($current_assignment['name']) ?>
                            </a>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Assigned Date</label>
                            <span><?= date('d M Y', strtotime($current_assignment['assigned_date'])) ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Email</label>
                            <span><?= htmlspecialchars($current_assignment['email'] ?: 'N/A') ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Role</label>
                            <span class="badge bg-info"><?= htmlspecialchars($current_assignment['role'] ?: 'N/A') ?></span>
                        </div>
                        <div class="mb-0">
                            <label class="text-muted small d-block">Remarks</label>
                            <div><?= nl2br(htmlspecialchars($current_assignment['remarks'] ?: 'No remarks')) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="text-muted">This asset is currently not assigned to any user.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs" id="assetTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="assign-tab" data-bs-toggle="tab" data-bs-target="#assign" type="button">
                                Assignment History
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content" id="assetTabContent">

                        <!-- ASSIGNMENT HISTORY -->
                        <div class="tab-pane fade show active" id="assign" role="tabpanel">
                            <div class="table-responsive">
                                <table class="table table-hover" id="assignTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User / Employee</th>
                                            <th>Assigned Date</th>
                                            <th>Returned Date</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($assignments) > 0): ?>
                                            <?php foreach ($assignments as $row): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                                    <td><?= date('d M Y', strtotime($row['assigned_date'])) ?></td>
                                                    <td>
                                                        <?= $row['returned_date']
                                                            ? date('d M Y', strtotime($row['returned_date']))
                                                            : '-' ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['returned_date']): ?>
                                                            <span class="badge bg-secondary">Returned</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($row['remarks'] ?: '-') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No assignment history found.</td>
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
    </div>
</div>

<!-- PDF EXPORT -->
<script>
    function exportToPDF() {
        const {
            jsPDF
        } = window.jspdf;
        const doc = new jsPDF();

        const assetName = "<?= addslashes($asset['asset_name']) ?>";
        const serialNo = "<?= addslashes($asset['serial_number']) ?>";

        doc.setFontSize(18);
        doc.setTextColor(0, 123, 255);
        doc.text("Asset Comprehensive Profile", 14, 20);

        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text("Generated on: " + new Date().toLocaleString(), 14, 28);

        // Technical Specs
        doc.setFontSize(14);
        doc.setTextColor(0);
        doc.text("Technical Specifications", 14, 40);

        doc.autoTable({
            startY: 45,
            body: [
                ["Asset Name", assetName],
                ["Serial Number", serialNo],
                ["Category", "<?= addslashes($asset['category_name'] ?? 'N/A') ?>"],
                ["Model", "<?= addslashes($asset['model_name'] ?? 'N/A') ?>"],
                ["Current Status", "<?= addslashes($asset['status_name'] ?? 'N/A') ?>"],
                ["Department", "<?= addslashes($asset['dept_name'] ?? 'N/A') ?>"],
                ["Vendor", "<?= addslashes($asset['vendor_name'] ?? 'N/A') ?>"]
            ],
            theme: 'grid',
            styles: {
                fontSize: 10
            }
        });

        // Lifecycle
        doc.text("Lifecycle & Cost", 14, doc.lastAutoTable.finalY + 15);
        doc.autoTable({
            startY: doc.lastAutoTable.finalY + 20,
            body: [
                ["Purchase Date", "<?= (!empty($asset['purchase_date']) && $asset['purchase_date'] != '0000-00-00') ? $asset['purchase_date'] : 'N/A' ?>"],
                ["Warranty Expiry", "<?= (!empty($asset['warranty_expiry']) && $asset['warranty_expiry'] != '0000-00-00') ? $asset['warranty_expiry'] : 'N/A' ?>"],
                ["Initial Cost", "INR <?= number_format((float)$asset['cost'], 2) ?>"],
                ["Asset Age", "<?= addslashes($age_str) ?>"],
                ["Warranty Status", "<?= addslashes($warranty_status) ?>"]
            ],
            theme: 'grid',
            styles: {
                fontSize: 10
            }
        });

        // Current Assignment
        doc.text("Current Assignment", 14, doc.lastAutoTable.finalY + 15);
        doc.autoTable({
            startY: doc.lastAutoTable.finalY + 20,
            body: [
                <?php if ($current_assignment): ?>["Assigned To", "<?= addslashes($current_assignment['name']) ?>"],
                    ["Assigned Date", "<?= addslashes($current_assignment['assigned_date']) ?>"],
                    ["Role", "<?= addslashes($current_assignment['role']) ?>"],
                    ["Remarks", "<?= addslashes($current_assignment['remarks'] ?: '-') ?>"]
                <?php else: ?>["Status", "Currently not assigned"]
                <?php endif; ?>
            ],
            theme: 'grid',
            styles: {
                fontSize: 10
            }
        });

        // Assignment History
        doc.text("Assignment History", 14, doc.lastAutoTable.finalY + 15);
        doc.autoTable({
            html: '#assignTable',
            startY: doc.lastAutoTable.finalY + 20,
            theme: 'striped',
            headStyles: {
                fillColor: [100, 100, 100]
            },
            styles: {
                fontSize: 9
            }
        });

        doc.save("Asset_Profile_" + assetName.replace(/\s+/g, '_') + ".pdf");
    }
</script>

<?php include("../../includes/footer.php"); ?>