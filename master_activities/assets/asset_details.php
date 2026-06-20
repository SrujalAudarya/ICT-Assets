<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

/* ---------- ASSET BASIC INFO ---------- */
$asset_query = "
SELECT a.*, c.category_name, s.status_name, l.dept_name, v.vendor_name, m.model_name
FROM assets a
LEFT JOIN asset_categories c ON a.category_id=c.category_id
LEFT JOIN asset_status s ON a.status_id=s.status_id
LEFT JOIN locations l ON a.location_id=l.location_id
LEFT JOIN vendors v ON a.vendor_id=v.vendor_id
LEFT JOIN asset_models m ON a.model_id=m.model_id
WHERE a.asset_id='$id'
";
$asset = mysqli_fetch_assoc(mysqli_query($conn, $asset_query));

if (!$asset) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Asset not found.</div></div>";
    exit();
}

/* ---------- LIFECYCLE CALCULATIONS ---------- */
$purchase_date = new DateTime($asset['purchase_date']);
$today = new DateTime();
$age = $purchase_date->diff($today);
$age_str = $age->y . " Years, " . $age->m . " Months";

$warranty_expiry = new DateTime($asset['warranty_expiry']);
$is_warranty_active = ($warranty_expiry > $today);
$warranty_diff = $today->diff($warranty_expiry);
$warranty_status = $is_warranty_active ? $warranty_diff->days . " Days Remaining" : "Expired";

/* ---------- ASSIGNMENT HISTORY ---------- */
$assignments_res = mysqli_query($conn,"
SELECT aa.*, u.name
FROM asset_assignments aa
JOIN users u ON aa.user_id=u.user_id
WHERE aa.asset_id='$id'
ORDER BY aa.assignment_id DESC
");
$assignments = [];
while($row = mysqli_fetch_assoc($assignments_res)) $assignments[] = $row;



/* ---------- DOCUMENTS BY TYPE ---------- */
$docs_res = mysqli_query($conn,"SELECT * FROM documents WHERE asset_id='$id' ORDER BY document_id DESC");
$procurement_docs = [];
$other_docs = [];

while($d = mysqli_fetch_assoc($docs_res)) {
    if(in_array($d['document_type'], ['SALE_ORDER', 'INVOICE', 'WARRANTY'])) {
        $procurement_docs[] = $d;
    } else {
        $other_docs[] = $d;
    }
}

/* ---------- EXPORT LOGIC ---------- */
if(isset($_GET['export'])) {
    $format = $_GET['export'];
    $clean_asset_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $asset['asset_name']);
    $filename = "Asset_Profile_" . $clean_asset_name . "_" . date('Y-m-d');

    if($format == 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><style>
                .header{font-size:16pt; font-weight:bold; background-color:#007bff; color:#fff;}
                .section-head{background-color:#eee; font-weight:bold; border:0.5pt solid #000;}
                td{border:0.5pt solid #000;}
                th{background-color:#D3D3D3; font-weight:bold; border:0.5pt solid #000;}
              </style></head><body>';
        
        echo '<table>';
        echo '<tr><th colspan="5" class="header">Asset Comprehensive Profile</th></tr>';
        echo '<tr><th colspan="5">Generated on: ' . date('d M Y H:i') . '</th></tr>';
        echo '<tr></tr>';
        
        // Basic Info
        echo '<tr><th colspan="5" class="section-head">Technical Specifications</th></tr>';
        echo '<tr><td>Asset Name:</td><td colspan="4">' . htmlspecialchars($asset['asset_name']) . '</td></tr>';
        echo '<tr><td>Serial Number:</td><td colspan="4">' . htmlspecialchars($asset['serial_number']) . '</td></tr>';
        echo '<tr><td>Category:</td><td colspan="4">' . htmlspecialchars($asset['category_name']) . '</td></tr>';
        echo '<tr><td>Model:</td><td colspan="4">' . htmlspecialchars($asset['model_name'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>Current Status:</td><td colspan="4">' . htmlspecialchars($asset['status_name']) . '</td></tr>';
        echo '<tr><td>Location:</td><td colspan="4">' . htmlspecialchars($asset['dept_name']) . '</td></tr>';
        
        echo '<tr></tr>';
        echo '<tr><th colspan="5" class="section-head">Lifecycle & Cost</th></tr>';
        echo '<tr><td>Purchase Date:</td><td colspan="4">' . $asset['purchase_date'] . '</td></tr>';
        echo '<tr><td>Warranty Expiry:</td><td colspan="4">' . $asset['warranty_expiry'] . '</td></tr>';
        echo '<tr><td>Initial Cost:</td><td colspan="4">' . number_format($asset['cost'], 2) . '</td></tr>';
        echo '<tr><td>Asset Age:</td><td colspan="4">' . $age_str . '</td></tr>';

        // Assignment History
        echo '<tr></tr>';
        echo '<tr><th colspan="5" class="section-head">Assignment History</th></tr>';
        echo '<tr><th>User</th><th>Assigned Date</th><th>Returned Date</th><th colspan="2">Remarks</th></tr>';
        foreach($assignments as $row) {
            echo '<tr><td>' . htmlspecialchars($row['name']) . '</td><td>' . $row['assigned_date'] . '</td><td>' . ($row['returned_date'] ?: 'Active') . '</td><td colspan="2">' . htmlspecialchars($row['remarks']) . '</td></tr>';
        }

        

        echo '</table></body></html>';
        exit();
    }

    if($format == 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ASSET PROFILE:', $asset['asset_name']]);
        fputcsv($output, ['Serial Number:', $asset['serial_number']]);
        fputcsv($output, ['Category:', $asset['category_name']]);
        fputcsv($output, ['Status:', $asset['status_name']]);
        fputcsv($output, []);
        fputcsv($output, ['ASSIGNMENT HISTORY']);
        fputcsv($output, ['User', 'Assigned Date', 'Returned Date', 'Remarks']);
        foreach($assignments as $row) {
            fputcsv($output, [$row['name'], $row['assigned_date'], $row['returned_date'] ?: 'Active', $row['remarks']]);
        }
        fputcsv($output, []);
        
        fputcsv($output, ['Date', 'Issue', 'Vendor', 'Cost']);
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
        <div class="btn-group">
            <div class="dropdown me-2">
                <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Export Profile
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportToPDF()"><i class="bi bi-file-earmark-pdf text-danger"></i> Export as PDF</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&export=excel"><i class="bi bi-file-earmark-excel text-success"></i> Export as Excel (.xls)</a></li>
                    <li><a class="dropdown-item" href="?id=<?= $id ?>&export=csv"><i class="bi bi-file-earmark-text text-primary"></i> Export as CSV</a></li>
                </ul>
            </div>
            <a href="assets_edit.php?id=<?= $id ?>" class="btn btn-warning">Edit Asset</a>
            <a href="assets_list.php" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- LEFT COLUMN: BASIC INFO & LIFECYCLE -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Technical Specifications</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th width="40%">Serial No</th><td><code><?= htmlspecialchars($asset['serial_number']) ?></code></td></tr>
                        <tr><th>Category</th><td><?= $asset['category_name'] ?></td></tr>
                        <tr><th>Model</th><td><?= htmlspecialchars($asset['model_name'] ?? 'N/A') ?></td></tr>
                        <tr><th>Status</th><td>
                            <span class="badge bg-info"><?= $asset['status_name'] ?></span>
                        </td></tr>
                        <tr><th>Location</th><td><?= $asset['dept_name'] ?></td></tr>
                        <tr><th>Vendor</th><td><?= $asset['vendor_name'] ?: 'N/A' ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4 border-info">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Lifecycle Intelligence</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th width="40%">Purchase Date</th><td><?= date('d M Y', strtotime($asset['purchase_date'])) ?></td></tr>
                        <tr><th>Asset Age</th><td><?= $age_str ?></td></tr>
                        <tr><th>Warranty Expiry</th><td><?= date('d M Y', strtotime($asset['warranty_expiry'])) ?></td></tr>
                        <tr><th>Warranty Status</th><td>
                            <span class="badge <?= $is_warranty_active ? 'bg-success' : 'bg-danger' ?>">
                                <?= $warranty_status ?>
                            </span>
                        </td></tr>
                        <tr><th>Initial Cost</th><td class="fw-bold text-primary">₹ <?= number_format($asset['cost'], 2) ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Procurement Documents</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if(empty($procurement_docs)): ?>
                            <li class="list-group-item text-muted">No procurement docs found.</li>
                        <?php else: foreach($procurement_docs as $doc): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted d-block"><?= str_replace('_', ' ', $doc['document_type']) ?></small>
                                    <?= htmlspecialchars($doc['file_name']) ?>
                                </div>
                                <a href="../../<?= $doc['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                            </li>
                        <?php endforeach; endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: HISTORY & OPERATIONS -->
        <div class="col-md-8">
            <!-- TABS FOR HISTORY -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <ul class="nav nav-tabs card-header-tabs" id="assetTab" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="assign-tab" data-bs-toggle="tab" data-bs-target="#assign" type="button">Assignment History</button>
                        </li>
                        
                        <li class="nav-item">
                            <button class="nav-link" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button">Other Documents</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="assetTabContent">
                        <!-- ASSIGNMENT TAB -->
                        <div class="tab-pane fade show active" id="assign" role="tabpanel">
                            <table class="table table-hover" id="assignTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>User / Employee</th>
                                        <th>Assigned Date</th>
                                        <th>Returned Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($assignments) > 0): ?>
                                        <?php foreach($assignments as $row): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['name']) ?></td>
                                                <td><?= date('d M Y', strtotime($row['assigned_date'])) ?></td>
                                                <td><?= $row['returned_date'] ? date('d M Y', strtotime($row['returned_date'])) : '-' ?></td>
                                                <td>
                                                    <?= $row['returned_date'] ? 'Returned' : 'Active' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-muted">No assignment history found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        

                        <!-- OTHER DOCUMENTS TAB -->
                        <div class="tab-pane fade" id="docs" role="tabpanel">
                            <div class="list-group">
                                <?php if(empty($other_docs)): ?>
                                    <div class="text-center text-muted py-3">No additional documents uploaded.</div>
                                <?php else: foreach($other_docs as $doc): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($doc['file_name']) ?></h6>
                                            <small class="text-muted">Uploaded on: <?= date('d M Y', strtotime($doc['uploaded_at'])) ?></small>
                                        </div>
                                        <a href="../../<?= $doc['file_path'] ?>" target="_blank" class="btn btn-primary btn-sm">Download</a>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    const assetName = "<?= addslashes($asset['asset_name']) ?>";
    const serialNo = "<?= addslashes($asset['serial_number']) ?>";
    
    // Header
    doc.setFontSize(18);
    doc.setTextColor(0, 123, 255);
    doc.text("Asset Comprehensive Profile", 14, 20);
    
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text("Generated on: " + new Date().toLocaleString(), 14, 28);
    
    // Technical Specs Section
    doc.setFontSize(14);
    doc.setTextColor(0);
    doc.text("Technical Specifications", 14, 40);
    
    doc.autoTable({
        startY: 45,
        body: [
            ["Asset Name", assetName],
            ["Serial Number", serialNo],
            ["Category", "<?= addslashes($asset['category_name']) ?>"],
            ["Model", "<?= addslashes($asset['model_name'] ?? 'N/A') ?>"],
            ["Current Status", "<?= addslashes($asset['status_name']) ?>"],
            ["Location", "<?= addslashes($asset['dept_name']) ?>"]
        ],
        theme: 'grid',
        styles: { fontSize: 10 }
    });
    
    // Lifecycle Section
    doc.text("Lifecycle & Cost", 14, doc.lastAutoTable.finalY + 15);
    doc.autoTable({
        startY: doc.lastAutoTable.finalY + 20,
        body: [
            ["Purchase Date", "<?= $asset['purchase_date'] ?>"],
            ["Warranty Expiry", "<?= $asset['warranty_expiry'] ?>"],
            ["Initial Cost", "INR <?= number_format($asset['cost'], 2) ?>"],
            ["Asset Age", "<?= $age_str ?>"]
        ],
        theme: 'grid',
        styles: { fontSize: 10 }
    });
    
    // Assignment History
    doc.text("Assignment History", 14, doc.lastAutoTable.finalY + 15);
    doc.autoTable({
        html: '#assignTable',
        startY: doc.lastAutoTable.finalY + 20,
        theme: 'striped',
        headStyles: { fillColor: [100, 100, 100] }
    });
    
    
    
    doc.save("Asset_Profile_" + assetName.replace(/\s+/g, '_') + ".pdf");
}
</script>

<?php include("../../includes/footer.php"); ?>
