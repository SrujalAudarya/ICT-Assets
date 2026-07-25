<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$error = "";

/* =========================================================
   AJAX HANDLER: FETCH SUB-CATEGORIES
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] == 'get_subcategories') {
    header('Content-Type: application/json');
    $parent_id = (int)$_GET['parent_id'];
    
    // Assuming you have a parent_id or similar structure in your asset_categories table.
    // If your table is just a flat list of categories, you'll need to update this query
    // to match how you define the relationship between "PC" and "Desktop/Laptop".
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
if (isset($_POST['save'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['model_name']));
    // Save the final, most specific category (the subcategory) to the model
    $category_id = mysqli_real_escape_string($conn, $_POST['sub_category_id']); 
    
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']);
    $make_name = mysqli_real_escape_string($conn, trim($_POST['make_name']));
    $contract_no = mysqli_real_escape_string($conn, trim($_POST['contract_no']));
    $quantity = !empty($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    
    $purchase_date = !empty($_POST['purchase_date']) ? mysqli_real_escape_string($conn, $_POST['purchase_date']) : NULL;
    $expiry_date   = !empty($_POST['expiry_date']) ? mysqli_real_escape_string($conn, $_POST['expiry_date']) : NULL;
    
    $financial_year = mysqli_real_escape_string($conn, trim($_POST['financial_year']));
    $specifications = mysqli_real_escape_string($conn, trim($_POST['specifications']));

    $purchase_date_sql = $purchase_date ? "'$purchase_date'" : "NULL";
    $expiry_date_sql   = $expiry_date ? "'$expiry_date'" : "NULL";
    
    // Handle Model Image Upload
    $image_path_db = "NULL";
    if (isset($_FILES['model_image']) && $_FILES['model_image']['error'] == UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['model_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($file_ext, $allowed)) {
            $upload_dir = "../../uploads/models/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $new_filename = "model_" . time() . "_" . rand(1000, 9999) . "." . $file_ext;
            if (move_uploaded_file($_FILES['model_image']['tmp_name'], $upload_dir . $new_filename)) {
                $image_path_db = "'uploads/models/" . $new_filename . "'";
            }
        }
    }

    // Handle Supply Order Document Upload
    $supply_order_db = "NULL";
    if (isset($_FILES['supply_order_doc']) && $_FILES['supply_order_doc']['error'] == UPLOAD_ERR_OK) {
        $doc_ext = strtolower(pathinfo($_FILES['supply_order_doc']['name'], PATHINFO_EXTENSION));
        $allowed_docs = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        if (in_array($doc_ext, $allowed_docs)) {
            $doc_dir = "../../uploads/supply_orders/";
            if (!is_dir($doc_dir)) mkdir($doc_dir, 0777, true);
            $doc_filename = "SO_" . time() . "_" . rand(1000, 9999) . "." . $doc_ext;
            if (move_uploaded_file($_FILES['supply_order_doc']['tmp_name'], $doc_dir . $doc_filename)) {
                $supply_order_db = "'uploads/supply_orders/" . $doc_filename . "'";
            }
        } else {
            $error = "Invalid Supply Order format. Use PDF, DOC, or Images.";
        }
    }

    if (empty($error)) {
        if (empty($category_id)) {
            $error = "Please select both a Main Category and a Sub Category.";
        } else {
            $query = "INSERT INTO asset_models 
                        (model_name, category_id, vendor_id, make_name, contract_no, quantity, purchase_date, expiry_date, financial_year, specifications, model_image, supply_order_doc) 
                      VALUES 
                        ('$name', '$category_id', '$vendor_id', '$make_name', '$contract_no', '$quantity', $purchase_date_sql, $expiry_date_sql, '$financial_year', '$specifications', $image_path_db, $supply_order_db)";
            
            if(mysqli_query($conn, $query)) {
                header("Location: " . ROUTE_MODELS);
                exit();
            } else {
                $error = mysqli_error($conn);
            }
        }
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add Asset Model</h4>
        </div>
        <div class="card-body">
            <?php if($error != ""): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                
                <h5 class="text-primary border-bottom pb-2 mb-3">Model Details</h5>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="form-label">Model Name <span class="text-danger">*</span></label>
                        <input type="text" name="model_name" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <!-- MAIN CATEGORY -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Main Asset Type <span class="text-danger">*</span></label>
                        <select name="main_category_id" id="main_category_id" class="form-select" required>
                            <option value="">-- Select Main Category --</option>
                            <?php
                            // Fetch parent categories (assuming parent_id IS NULL or 0 denotes a main category)
                            $res = mysqli_query($conn, "SELECT * FROM asset_categories WHERE parent_id IS NULL OR parent_id = 0 ORDER BY category_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['category_id']}'>{$row['category_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- SUB CATEGORY -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                        <select name="sub_category_id" id="sub_category_id" class="form-select" required disabled>
                            <option value="">-- Select Sub Category --</option>
                        </select>
                        <small class="text-muted" id="sub_cat_help">Please select a Main Asset Type first.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Make</label>
                        <input type="text" name="make_name" class="form-control" placeholder="e.g. HP, D-Link, Voltas">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Vendor <span class="text-danger">*</span></label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="">-- Select Vendor --</option>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM vendors ORDER BY vendor_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['vendor_id']}'>{$row['vendor_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <h5 class="text-primary border-bottom pb-2 mb-3 mt-3">Procurement & Attachments</h5>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Contract No</label>
                        <input type="text" name="contract_no" class="form-control">
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" min="0">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty / Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Financial Year</label>
                        <input type="text" name="financial_year" class="form-control" placeholder="e.g. 2025-26">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Model Image / Logo</label>
                        <input type="file" name="model_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Supply Order Document <i class="bi bi-file-earmark-pdf text-danger"></i></label>
                        <input type="file" name="supply_order_doc" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">Attached to all assets of this model.</small>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Specifications</label>
                    <textarea name="specifications" class="form-control" rows="4"></textarea>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" name="save" class="btn btn-primary px-5 btn-lg">Save Model</button>
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