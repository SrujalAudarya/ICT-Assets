<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);

if(isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['model_name']);
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); // PARTY
    $make_name = mysqli_real_escape_string($conn, $_POST['make_name']); // MAKE
    $contract_no = mysqli_real_escape_string($conn, $_POST['contract_no']);
    $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $purchase_date = !empty($_POST['purchase_date']) ? mysqli_real_escape_string($conn, $_POST['purchase_date']) : NULL;
    $financial_year = mysqli_real_escape_string($conn, $_POST['financial_year']);
    $specifications = mysqli_real_escape_string($conn, $_POST['specifications']);

    $purchase_date_sql = $purchase_date ? "'$purchase_date'" : "NULL";

    $query = "UPDATE asset_models SET 
              model_name='$name', 
              category_id='$category_id', 
              vendor_id='$vendor_id',
              make_name='$make_name',
              contract_no='$contract_no',
              quantity='$quantity',
              purchase_date=$purchase_date_sql,
              financial_year='$financial_year',
              specifications='$specifications' 
              WHERE model_id=$id";

    if(mysqli_query($conn, $query)) {
        header("Location: " . ROUTE_MODELS);
        exit();
    } else {
        $error = mysqli_error($conn);
    }
}

$result = mysqli_query($conn, "SELECT * FROM asset_models WHERE model_id=$id");
$row = mysqli_fetch_assoc($result);

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">Edit Asset Model</h4>
        </div>
        <div class="card-body">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Model Name</label>
                        <input type="text" name="model_name" value="<?= htmlspecialchars($row['model_name']) ?>" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category (Type of PC)</label>
                        <select name="category_id" class="form-select" required>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name ASC");
                            while($cat = mysqli_fetch_assoc($res)) {
                                $selected = ($cat['category_id'] == $row['category_id']) ? "selected" : "";
                                echo "<option value='{$cat['category_id']}' $selected>{$cat['category_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Make</label>
                        <input type="text" name="make_name" value="<?= htmlspecialchars($row['make_name']) ?>" class="form-control" placeholder="e.g. HP, Dell, Lenovo">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Party / Vendor</label>
                        <select name="vendor_id" class="form-select" required>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM vendors ORDER BY vendor_name ASC");
                            while($ven = mysqli_fetch_assoc($res)) {
                                $selected = ($ven['vendor_id'] == $row['vendor_id']) ? "selected" : "";
                                echo "<option value='{$ven['vendor_id']}' $selected>{$ven['vendor_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Contract No</label>
                        <input type="text" name="contract_no" value="<?= htmlspecialchars($row['contract_no']) ?>" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" value="<?= htmlspecialchars($row['quantity']) ?>" class="form-control" min="0">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" name="purchase_date" value="<?= !empty($row['purchase_date']) ? htmlspecialchars($row['purchase_date']) : '' ?>" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Financial Year</label>
                        <input type="text" name="financial_year" value="<?= htmlspecialchars($row['financial_year']) ?>" class="form-control" placeholder="e.g. 2025-26">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Specifications</label>
                    <textarea name="specifications" class="form-control" rows="4"><?= htmlspecialchars($row['specifications']) ?></textarea>
                </div>

                <div class="mt-4">
                    <button type="submit" name="update" class="btn btn-primary px-4">Update Model</button>
                    <a href="<?= ROUTE_MODELS ?>" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include("../../includes/footer.php"); ?>