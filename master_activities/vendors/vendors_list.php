<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

/* ---------- MAIN QUERY ---------- */
$query = "SELECT v.*, 
          (SELECT COUNT(*) FROM assets WHERE vendor_id = v.vendor_id) as asset_count
          FROM vendors v 
          ORDER BY v.vendor_id ASC";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Vendors Management</h2>
        <a href="<?= ROUTE_VENDORS_ADD ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Vendor
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Vendor Name</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th class="text-center">Assets</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['vendor_id'] ?></td>
                                    <td class="fw-bold">
                                        <a href="vendors_details.php?id=<?= $row['vendor_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($row['vendor_name']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($row['contact_person']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info rounded-pill"><?= $row['asset_count'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="vendors_details.php?id=<?= $row['vendor_id'] ?>" class="btn btn-info">View</a>
                                            <a href="<?= ROUTE_VENDORS_EDIT ?>?id=<?= $row['vendor_id'] ?>" class="btn btn-warning">Edit</a>
                                            <a href="<?= ROUTE_VENDORS_DELETE ?>?id=<?= $row['vendor_id'] ?>"
                                               onclick="return confirm('Are you sure? This will affect all assets associated with this vendor.')" 
                                               class="btn btn-danger">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No vendors found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>
