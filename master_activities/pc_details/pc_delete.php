<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include(__DIR__ . "/../../includes/auth.php");
include(__DIR__ . "/../../config/db.php");
include(__DIR__ . "/../../includes/header.php");
include(__DIR__ . "/../../includes/sidebar.php");

// Check DB connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>

<div class="container mt-4">
    <h2>PC List</h2>

    <a href="pc_add.php" class="btn btn-primary mb-3">Add New PC</a>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>PC Model</th>
                <th>Supply Order</th>
                <th>Expiry Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>

        <?php
        $result = mysqli_query($conn, "SELECT * FROM pc_details");

        if (!$result) {
            die("Query Failed: " . mysqli_error($conn));
        }

        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
        ?>
            <tr>
                <td><?= $row['id']; ?></td>
                <td><?= $row['pc_model']; ?></td>
                <td><?= $row['supply_order']; ?></td>
                <td><?= $row['expiry_date']; ?></td>
                <td>
                    <a href="pc_edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                    <a href="pc_delete.php?id=<?= $row['id']; ?>" 
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Delete?')">Delete</a>
                </td>
            </tr>
        <?php
            }
        } else {
            echo "<tr><td colspan='5' class='text-center'>No Records Found</td></tr>";
        }
        ?>

        </tbody>
    </table>
</div>

<?php include(__DIR__ . "/../../includes/footer.php"); ?>