<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- FILTER HANDLING ---------- */
$search = trim($_GET['search'] ?? '');
$type = $_GET['type'] ?? '';

$where = "WHERE 1=1";

if($search != ""){
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where .= " AND (d.file_name LIKE '%$search_escaped%' OR a.asset_name LIKE '%$search_escaped%' OR a.serial_number LIKE '%$search_escaped%')";
}

if($type != ""){
    $type_escaped = mysqli_real_escape_string($conn, $type);
    $where .= " AND d.document_type = '$type_escaped'";
}

/* ---------- PAGINATION LOGIC ---------- */
$limit = 10;
$page = $_GET['page'] ?? 1;
$page = max(1, (int)$page);
$offset = ($page - 1) * $limit;

/* ---------- MAIN QUERY ---------- */
$query = "
SELECT d.*, a.asset_name, a.serial_number
FROM documents d
JOIN assets a ON d.asset_id = a.asset_id
$where
ORDER BY d.document_id DESC
LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Documents Repository</h2>
        <a href="documents_upload.php" class="btn btn-primary">
            <i class="bi bi-cloud-upload"></i> Upload New Document
        </a>
    </div>

    <!-- SEARCH & FILTER FORM -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by file name, asset name or serial..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Document Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="SALE_ORDER" <?= ($type == 'SALE_ORDER') ? 'selected' : '' ?>>Sale Order</option>
                        <option value="INVOICE" <?= ($type == 'INVOICE') ? 'selected' : '' ?>>Invoice</option>
                        <option value="WARRANTY" <?= ($type == 'WARRANTY') ? 'selected' : '' ?>>Warranty Card</option>
                        
                        <option value="INSURANCE" <?= ($type == 'INSURANCE') ? 'selected' : '' ?>>Insurance Policy</option>
                        <option value="OTHER" <?= ($type == 'OTHER') ? 'selected' : '' ?>>General Document</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">Filter</button>
                    <a href="documents_list.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Asset Name</th>
                            <th>File Name</th>
                            <th>Type</th>
                            <th>Uploaded At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['document_id'] ?></td>
                                    <td>
                                        <a href="../assets/asset_details.php?id=<?= $row['asset_id'] ?>" class="text-decoration-none fw-bold">
                                            <?= htmlspecialchars($row['asset_name']) ?>
                                        </a>
                                        <br><small class="text-muted">SN: <?= htmlspecialchars($row['serial_number']) ?></small>
                                    </td>
                                    <td>
                                        <a href="document_details.php?id=<?= $row['document_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['file_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= str_replace('_', ' ', $row['document_type']) ?></span>
                                    </td>
                                    <td><?= date('d M Y, H:i', strtotime($row['uploaded_at'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="document_details.php?id=<?= $row['document_id'] ?>" class="btn btn-info">View</a>
                                            <a href="../../<?= $row['file_path'] ?>" target="_blank" class="btn btn-primary">Download</a>
                                            <a href="document_delete.php?id=<?= $row['document_id'] ?>" 
                                               onclick="return confirm('Are you sure you want to delete this document?')"
                                               class="btn btn-danger">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No documents found matching your criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- PAGINATION -->
    <?php
    $total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM documents d JOIN assets a ON d.asset_id = a.asset_id $where");
    $total_rows = mysqli_fetch_assoc($total_query)['total'];
    $total_pages = ceil($total_rows / $limit);
    ?>
    <?php if($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for($i=1; $i<=$total_pages; $i++): ?>
                <li class="page-item <?= ($i==$page)?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&type=<?= urlencode($type) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<?php include("../../includes/footer.php"); ?>
