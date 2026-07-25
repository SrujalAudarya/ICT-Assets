<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$id = mysqli_real_escape_string($conn, $_GET['id']);
$error = "";

/* =========================================================
   AJAX HANDLER: FETCH SUB-CATEGORIES
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] == 'get_subcategories') {
    header('Content-Type: application/json');
    $parent_id = (int)$_GET['parent_id'];
    
    $query = "SELECT category_id, category_name FROM asset_categories WHERE parent_id = $parent_id ORDER BY category_name ASC";
    $res = mysqli_query($conn, $query);
    
    $subcats = [];
    if ($res) {
        while($row = mysqli_fetch_assoc($res)) {
            $subcats[] = $row;
        }
    }
    echo json_encode($subcats);
    exit();
}

/* =========================================================
   HANDLE FORM SUBMISSION
   ========================================================= */
if(isset($_POST['update'])) {
    $name = mysqli_real_escape_string($conn, $_POST['model_name']);
    
    // Get the most specific category selected
    $main_category_id = mysqli_real_escape_string($conn, $_POST['main_category_id']);
    $sub_category_id = isset($_POST['sub_category_id']) ? mysqli_real_escape_string($conn, $_POST['sub_category_id']) : '';
    $category_id = !empty($sub_category_id) ? $sub_category_id : $main_category_id;

    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 
    $make_name = mysqli_real_escape_string($conn, $_POST['make_name']); 
    $contract_no = mysqli_real_escape_string($conn, $_POST['contract_no']);
    $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    
    $purchase_date = !empty($_POST['purchase_date']) ? mysqli_real_escape_string($conn, $_POST['purchase_date']) : NULL;
    $expiry_date   = !empty($_POST['expiry_date']) ? mysqli_real_escape_string($conn, $_POST['expiry_date']) : NULL; // NEW
    
    $financial_year = mysqli_real_escape_string($conn, $_POST['financial_year']);
    $specifications = mysqli_real_escape_string($conn, $_POST['specifications']);

    $purchase_date_sql = $purchase_date ? "'$purchase_date'" : "NULL";
    $expiry_date_sql   = $expiry_date ? "'$expiry_date'" : "NULL"; // NEW
    
    $img_res = mysqli_query($conn, "SELECT model_image, supply_order_doc FROM asset_models WHERE model_id=$id");
    $db_data = mysqli_fetch_assoc($img_res);
    $image_update_sql = "";
    $doc_update_sql = "";

    // Handle Image Upload
    if (isset($_FILES['model_image']) && $_FILES['model_image']['error'] == UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['model_image']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $upload_dir = "../../uploads/models/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_filename = "model_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
            if (move_uploaded_file($_FILES['model_image']['tmp_name'], $upload_dir . $new_filename)) {
                $image_update_sql = ", model_image='uploads/models/$new_filename'";
                if (!empty($db_data['model_image']) && file_exists("../../" . $db_data['model_image'])) unlink("../../" . $db_data['model_image']);
            }
        }
    }

    // Handle Supply Order Upload
    if (isset($_FILES['supply_order_doc']) && $_FILES['supply_order_doc']['error'] == UPLOAD_ERR_OK) {
        $doc_ext = strtolower(pathinfo($_FILES['supply_order_doc']['name'], PATHINFO_EXTENSION));
        if (in_array($doc_ext, ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'])) {
            $doc_dir = "../../uploads/supply_orders/";
            if (!is_dir($doc_dir)) mkdir($doc_dir, 0777, true);
            $doc_filename = "SO_" . time() . "_" . rand(1000, 9999) . "." . $doc_ext;
            if (move_uploaded_file($_FILES['supply_order_doc']['tmp_name'], $doc_dir . $doc_filename)) {
                $doc_update_sql = ", supply_order_doc='uploads/supply_orders/$doc_filename'";
                if (!empty($db_data['supply_order_doc']) && file_exists("../../" . $db_data['supply_order_doc'])) unlink("../../" . $db_data['supply_order_doc']);
            }
        }
    }

    if (empty($error)) {
        if (empty($category_id)) {
            $error = "Please select a Category.";
        } else {
            $query = "UPDATE asset_models SET 
                      model_name='$name', category_id='$category_id', vendor_id='$vendor_id',
                      make_name='$make_name', contract_no='$contract_no', quantity='$quantity',
                      purchase_date=$purchase_date_sql, expiry_date=$expiry_date_sql, financial_year='$financial_year', specifications='$specifications'
                      $image_update_sql $doc_update_sql 
                      WHERE model_id=$id";

            if(mysqli_query($conn, $query)) {
                header("Location: " . ROUTE_MODELS);
                exit();
            } else {
                $error = mysqli_error($conn);
            }
        }
    }
}

$result = mysqli_query($conn, "SELECT * FROM asset_models WHERE model_id=$id");
$row = mysqli_fetch_assoc($result);

// Determine current Main Category and Sub Category for pre-selection
$current_cat_id = $row['category_id'];
$parent_cat_id = 0;
$sub_cat_id = "";

if ($current_cat_id) {
    $cat_check_res = mysqli_query($conn, "SELECT parent_id FROM asset_categories WHERE category_id = '$current_cat_id'");
    if ($cat_check_res && mysqli_num_rows($cat_check_res) > 0) {
        $cat_check = mysqli_fetch_assoc($cat_check_res);
        if ($cat_check['parent_id'] != 0) {
            // It's a sub category
            $parent_cat_id = $cat_check['parent_id'];
            $sub_cat_id = $current_cat_id;
        } else {
            // It's a main category
            $parent_cat_id = $current_cat_id;
        }
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h4 class="mb-0">Edit Asset Model</h4>
        </div>
        <div class="card-body">
            <?php if($error != ""): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                
                <!-- ATTACHMENTS SECTION -->
                <div class="row align-items-center mb-4 pb-3 border-bottom">
                    <div class="col-md-2 text-center">
                        <?php if (!empty($row['model_image'])): ?>
                            <img src="../../<?= htmlspecialchars($row['model_image']) ?>" class="img-thumbnail" style="max-height: 70px;" alt="Logo">
                        <?php else: ?>
                            <div class="bg-light border text-muted d-flex align-items-center justify-content-center mx-auto" style="height: 70px; width: 70px; border-radius: 5px;"><i class="bi bi-image fs-4"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Change Model Image</label>
                        <input type="file" name="model_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Change Supply Order</label>
                        <input type="file" name="supply_order_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <?php if (!empty($row['supply_order_doc'])): ?>
                            <small class="text-success"><i class="bi bi-check-circle"></i> Document currently uploaded. (<a href="../../<?= $row['supply_order_doc'] ?>" target="_blank">View</a>)</small>
                        <?php endif; ?>
                    </div>
                </div>

                <h5 class="text-primary mb-3">Model Details</h5>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Model Name <span class="text-danger">*</span></label>
                        <input type="text" name="model_name" value="<?= htmlspecialchars($row['model_name']) ?>" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <!-- MAIN CATEGORY -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Main Asset Type <span class="text-danger">*</span></label>
                        <select name="main_category_id" id="main_category_id" class="form-select" required>
                            <option value="">-- Select Main Category --</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY category_name ASC");
                            while($cat = mysqli_fetch_assoc($res)) {
                                $selected = ($cat['category_id'] == $parent_cat_id) ? "selected" : "";
                                echo "<option value='{$cat['category_id']}' $selected>{$cat['category_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- SUB CATEGORY -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sub Category</label>
                        <select name="sub_category_id" id="sub_category_id" class="form-select" <?= empty($sub_cat_id) && empty($parent_cat_id) ? 'disabled' : '' ?>>
                            <option value="">-- Select Sub Category --</option>
                            <?php
                            if ($parent_cat_id > 0) {
                                $sub_res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id = '$parent_cat_id' ORDER BY category_name ASC");
                                while($subcat = mysqli_fetch_assoc($sub_res)) {
                                    $selected = ($subcat['category_id'] == $sub_cat_id) ? "selected" : "";
                                    echo "<option value='{$subcat['category_id']}' $selected>{$subcat['category_name']}</option>";
                                }
                            }
                            ?>
                        </select>
                        <small class="text-muted" id="sub_cat_help">Changes based on Main Asset Type.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Make</label>
                        <input type="text" name="make_name" value="<?= htmlspecialchars($row['make_name']) ?>" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vendor <span class="text-danger">*</span></label>
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

                <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Procurement Info</h5>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Contract No</label>
                        <input type="text" name="contract_no" value="<?= htmlspecialchars($row['contract_no']) ?>" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" value="<?= htmlspecialchars($row['quantity']) ?>" class="form-control" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" value="<?= !empty($row['purchase_date']) ? htmlspecialchars($row['purchase_date']) : '' ?>" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty / Expiry Date</label>
                        <input type="date" name="expiry_date" value="<?= !empty($row['expiry_date']) ? htmlspecialchars($row['expiry_date']) : '' ?>" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Financial Year</label>
                        <input type="text" name="financial_year" value="<?= htmlspecialchars($row['financial_year']) ?>" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Specifications</label>
                    <textarea name="specifications" class="form-control" rows="4"><?= htmlspecialchars($row['specifications']) ?></textarea>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" name="update" class="btn btn-warning px-5 btn-lg">Update Model</button>
                    <a href="<?= ROUTE_MODELS ?>" class="btn btn-secondary px-5 btn-lg">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const mainCategorySelect = document.getElementById("main_category_id");
    const subCategorySelect = document.getElementById("sub_category_id");
    const subCatHelp = document.getElementById("sub_cat_help");

    mainCategorySelect.addEventListener("change", function () {
        const parentId = this.value;

        // Reset Sub Category Dropdown
        subCategorySelect.innerHTML = '<option value="">-- Select Sub Category --</option>';
        
        if (parentId === "") {
            subCategorySelect.setAttribute("disabled", "disabled");
            subCatHelp.textContent = "Please select a Main Asset Type first.";
            subCatHelp.className = "text-muted";
            return;
        }

        // Fetch Subcategories via AJAX
        fetch(window.location.pathname + `?action=get_subcategories&parent_id=${parentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    subCategorySelect.removeAttribute("disabled");
                    subCatHelp.textContent = "Sub categories loaded.";
                    subCatHelp.className = "text-success";

                    data.forEach(subcat => {
                        const option = document.createElement("option");
                        option.value = subcat.category_id;
                        option.textContent = subcat.category_name;
                        subCategorySelect.appendChild(option);
                    });
                } else {
                    subCategorySelect.setAttribute("disabled", "disabled");
                    subCatHelp.textContent = "No sub-categories found for this type.";
                    subCatHelp.className = "text-danger";
                }
            })
            .catch(error => {
                console.error('Error fetching subcategories:', error);
                subCatHelp.textContent = "Error loading sub-categories.";
                subCatHelp.className = "text-danger";
            });
    });
});
</script>

<?php include("../../includes/footer.php"); ?>