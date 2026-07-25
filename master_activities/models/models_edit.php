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
    
    // Fetch current image path first in case we need to replace or keep it
    $img_res = mysqli_query($conn, "SELECT model_image FROM asset_models WHERE model_id=$id");
    $img_row = mysqli_fetch_assoc($img_res);
    $image_update_sql = "";

    // Handle Image Upload if a new file is provided
    if (isset($_FILES['model_image']) && $_FILES['model_image']['error'] == UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['model_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (in_array($file_ext, $allowed)) {
            $upload_dir = "../../uploads/models/";
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            
            $new_filename = "model_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['model_image']['tmp_name'], $target_file)) {
                $image_path_db = "uploads/models/" . $new_filename;
                $image_update_sql = ", model_image='$image_path_db'";
                
                // Delete old image file if it exists
                if (!empty($img_row['model_image']) && file_exists("../../" . $img_row['model_image'])) {
                    unlink("../../" . $img_row['model_image']);
                }
            } else {
                $error = "Failed to move uploaded image.";
            }
        } else {
            $error = "Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.";
        }
    }

    if (empty($error)) {
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
                  $image_update_sql 
                  WHERE model_id=$id";

        if(mysqli_query($conn, $query)) {
            header("Location: " . ROUTE_MODELS);
            exit();
        } else {
            $error = mysqli_error($conn);
        }
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

            <form method="post" enctype="multipart/form-data">
                <div class="row align-items-center mb-3">
                    <div class="col-md-2 text-center">
                        <?php if (!empty($row['model_image'])): ?>
                            <img src="../../<?= htmlspecialchars($row['model_image']) ?>" class="img-thumbnail" style="max-height: 70px; object-fit: contain;" alt="Model Logo">
                        <?php else: ?>
                            <div class="bg-light border text-muted d-flex align-items-center justify-content-center mx-auto" style="height: 70px; width: 70px; border-radius: 5px;">
                                <i class="bi bi-image fs-4"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-10">
                        <label class="form-label">Change Model Image / Logo</label>
                        <input type="file" name="model_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
                        <small class="text-muted">Leave blank to keep the current image.</small>
                    </div>
                </div>

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
                        <label class="form-label">Vendor</label>
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