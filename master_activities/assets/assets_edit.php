<?php
ob_start();
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

/* =========================================================
   AJAX HANDLER: FETCH MODEL DETAILS FOR AUTO-FILL
   ========================================================= */
if (isset($_GET['action']) && $_GET['action'] == 'get_model_details') {
    header('Content-Type: application/json');
    $mod_id = (int)$_GET['model_id'];

    // Dynamically pulls the defaults DIRECTLY from the asset_models table
    $query = "SELECT vendor_id, purchase_date, expiry_date AS warranty_expiry, cost 
              FROM asset_models 
              WHERE model_id = $mod_id LIMIT 1";
              
    $res = mysqli_query($conn, $query);

    if ($res && $row = mysqli_fetch_assoc($res)) {
        echo json_encode($row);
    } else {
        echo json_encode([]);
    }
    exit();
}

// Capture the Asset ID securely
$id = isset($_POST['asset_id']) ? (int)$_POST['asset_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

/* =========================================================
   FETCH EXISTING ASSET DATA
   ========================================================= */
$query = "SELECT * FROM assets WHERE asset_id = '$id'";
$result = mysqli_query($conn, $query);
$asset = mysqli_fetch_assoc($result);

if (!$asset) {
    include("../../includes/header.php");
    include("../../includes/sidebar.php");
    echo "<div class='container mt-4'><div class='alert alert-danger shadow-sm border-0'><i class='bi bi-exclamation-triangle-fill me-2'></i> Asset not found.</div></div>";
    include("../../includes/footer.php");
    exit();
}

/* =========================================================
   DETERMINE MAIN AND SUB CATEGORY FOR PRE-SELECTION
   ========================================================= */
$current_category_id = $asset['category_id'];
$main_cat_id = "";
$sub_cat_id = "";

if ($current_category_id) {
    $cat_query = mysqli_query($conn, "SELECT parent_id FROM asset_categories WHERE category_id = '$current_category_id'");
    if ($cat_row = mysqli_fetch_assoc($cat_query)) {
        if (empty($cat_row['parent_id']) || $cat_row['parent_id'] == 0) {
            $main_cat_id = $current_category_id;
        } else {
            $main_cat_id = $cat_row['parent_id'];
            $sub_cat_id = $current_category_id;
        }
    }
}

$error = "";

/* =========================================================
   HANDLE FORM SUBMIT (UPDATE)
   ========================================================= */
if (isset($_POST['update_asset'])) {
    
    $asset_name      = trim($_POST['asset_name'] ?? '');
    $serial_number   = trim($_POST['serial_number'] ?? '');
    
    $post_main_cat_id = trim($_POST['main_category_id'] ?? '');
    $post_sub_cat_id  = trim($_POST['sub_category_id'] ?? '');
    $category_id      = !empty($post_sub_cat_id) ? $post_sub_cat_id : $post_main_cat_id;

    $model_id        = trim($_POST['model_id'] ?? '');
    $vendor_id       = trim($_POST['vendor_id'] ?? '');
    $location_id     = trim($_POST['location_id'] ?? '');
    $status_id       = trim($_POST['status_id'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? '');
    $warranty_expiry = trim($_POST['warranty_expiry'] ?? '');
    $cost            = trim($_POST['cost'] ?? '');

    if ($asset_name == "") $error = "Asset Name is required.";
    elseif ($serial_number == "") $error = "Serial Number is required.";
    elseif ($category_id == "") $error = "Please select at least a Main Category.";
    elseif ($location_id == "") $error = "Please select Location.";
    elseif ($status_id == "") $error = "Please select Status.";

    if ($error == "") {
        $serial_safe = mysqli_real_escape_string($conn, $serial_number);
        $dup = mysqli_query($conn, "SELECT asset_id FROM assets WHERE serial_number = '$serial_safe' AND asset_id != '$id' LIMIT 1");
        if ($dup && mysqli_num_rows($dup) > 0) $error = "Another asset with this Serial Number already exists.";
    }

    if ($error == "") {
        $asset_name_safe    = mysqli_real_escape_string($conn, $asset_name);
        $category_id_safe   = mysqli_real_escape_string($conn, $category_id);
        $location_id_safe   = mysqli_real_escape_string($conn, $location_id);
        $status_id_safe     = mysqli_real_escape_string($conn, $status_id);

        $model_id_sql        = ($model_id !== '') ? "'" . mysqli_real_escape_string($conn, $model_id) . "'" : "NULL";
        $vendor_id_sql       = ($vendor_id !== '') ? "'" . mysqli_real_escape_string($conn, $vendor_id) . "'" : "NULL";
        $purchase_date_sql   = ($purchase_date !== '') ? "'" . mysqli_real_escape_string($conn, $purchase_date) . "'" : "NULL";
        $warranty_expiry_sql = ($warranty_expiry !== '') ? "'" . mysqli_real_escape_string($conn, $warranty_expiry) . "'" : "NULL";
        $cost_sql            = ($cost !== '') ? "'" . mysqli_real_escape_string($conn, $cost) . "'" : "NULL";

        $update_query = "
            UPDATE assets SET 
                asset_name = '$asset_name_safe',
                serial_number = '$serial_safe',
                category_id = '$category_id_safe',
                model_id = $model_id_sql,
                vendor_id = $vendor_id_sql,
                location_id = '$location_id_safe',
                status_id = '$status_id_safe',
                purchase_date = $purchase_date_sql,
                warranty_expiry = $warranty_expiry_sql,
                cost = $cost_sql
            WHERE asset_id = '$id'
        ";

        if (mysqli_query($conn, $update_query)) {
            // Success! Send them back to the list
            header("Location: assets_list.php?msg=updated");
            exit();
        } else {
            $error = "Error updating asset: " . mysqli_error($conn);
        }
    }
}

$main_categories = mysqli_query($conn, "SELECT category_id, category_name FROM asset_categories WHERE parent_id = 0 OR parent_id IS NULL ORDER BY category_name ASC");
$sub_categories  = mysqli_query($conn, "SELECT category_id, category_name, parent_id FROM asset_categories WHERE parent_id > 0 ORDER BY category_name ASC");
$models    = mysqli_query($conn, "SELECT model_id, model_name, category_id FROM asset_models ORDER BY model_name ASC");
$vendors   = mysqli_query($conn, "SELECT vendor_id, vendor_name FROM vendors ORDER BY vendor_name ASC");
$locations = mysqli_query($conn, "SELECT location_id, dept_name, floor FROM locations ORDER BY dept_name ASC");
$statuses  = mysqli_query($conn, "SELECT status_id, status_name FROM asset_status ORDER BY status_name ASC");

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4 mb-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0 text-dark"><i class="bi bi-pencil-square text-warning me-2"></i> Edit Asset</h3>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 mt-1">
                    <li class="breadcrumb-item"><a href="assets_list.php" class="text-decoration-none">Assets Inventory</a></li>
                    <li class="breadcrumb-item"><a href="asset_details.php?id=<?= $id ?>" class="text-decoration-none"><?= htmlspecialchars($asset['asset_name']) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit</li>
                </ol>
            </nav>
        </div>
        <a href="assets_list.php" class="btn btn-secondary shadow-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <div class="card shadow-sm border-0 border-top border-warning border-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-pc-display me-2 text-warning"></i> Update Device Specifications</h5>
        </div>
        <div class="card-body p-4 bg-light">

            <?php if ($error != ""): ?>
                <div class="alert alert-danger d-flex align-items-center shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="assets_edit.php?id=<?= $id ?>">
                <!-- CRITICAL: This hidden input ensures the ID doesn't get lost on save! -->
                <input type="hidden" name="asset_id" value="<?= $id ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Asset Name <span class="text-danger">*</span></label>
                        <input type="text" name="asset_name" class="form-control shadow-sm" value="<?= htmlspecialchars($asset['asset_name']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Serial Number <span class="text-danger">*</span></label>
                        <input type="text" name="serial_number" class="form-control text-uppercase shadow-sm" value="<?= htmlspecialchars($asset['serial_number']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <!-- MAIN CATEGORY -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Main Category <span class="text-danger">*</span></label>
                        <select name="main_category_id" id="main_category_id" class="form-select shadow-sm" required>
                            <option value="">Select Main Category</option>
                            <?php
                            mysqli_data_seek($main_categories, 0);
                            while ($row = mysqli_fetch_assoc($main_categories)) {
                                $selected = ($main_cat_id == $row['category_id']) ? 'selected' : '';
                                echo "<option value='{$row['category_id']}' $selected>" . htmlspecialchars($row['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- SUB CATEGORY -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Sub Category</label>
                        <select name="sub_category_id" id="sub_category_id" class="form-select shadow-sm">
                            <option value="">Select Subcategory</option>
                            <?php
                            mysqli_data_seek($sub_categories, 0);
                            while ($row = mysqli_fetch_assoc($sub_categories)) {
                                echo "<option value='{$row['category_id']}' data-parent='{$row['parent_id']}'>" . htmlspecialchars($row['category_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- MODEL -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Device Model</label>
                        <select name="model_id" id="model_id" class="form-select shadow-sm">
                            <option value="">Select Model</option>
                            <?php
                            mysqli_data_seek($models, 0);
                            while ($row = mysqli_fetch_assoc($models)) {
                                echo "<option value='{$row['model_id']}' data-category='{$row['category_id']}'>" . htmlspecialchars($row['model_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Vendor</label>
                        <select name="vendor_id" id="vendor_id" class="form-select shadow-sm">
                            <option value="">Select Vendor</option>
                            <?php
                            mysqli_data_seek($vendors, 0);
                            while ($row = mysqli_fetch_assoc($vendors)) {
                                $selected = ($asset['vendor_id'] == $row['vendor_id']) ? 'selected' : '';
                                echo "<option value='{$row['vendor_id']}' $selected>" . htmlspecialchars($row['vendor_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Storage Location <span class="text-danger">*</span></label>
                        <select name="location_id" id="location_id" class="form-select shadow-sm" required>
                            <option value="">Select Location</option>
                            <?php
                            mysqli_data_seek($locations, 0);
                            while ($row = mysqli_fetch_assoc($locations)) {
                                $selected = ($asset['location_id'] == $row['location_id']) ? 'selected' : '';
                                $label = $row['dept_name'] . (!empty($row['floor']) ? " ({$row['floor']})" : "");
                                echo "<option value='{$row['location_id']}' $selected>" . htmlspecialchars($label) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Asset Status <span class="text-danger">*</span></label>
                        <select name="status_id" id="status_id" class="form-select shadow-sm" required>
                            <option value="">Select Status</option>
                            <?php
                            mysqli_data_seek($statuses, 0);
                            while ($row = mysqli_fetch_assoc($statuses)) {
                                if (in_array($row['status_name'], ['Assigned', 'Available'])) {
                                    $selected = ($asset['status_id'] == $row['status_id']) ? 'selected' : '';
                                    echo "<option value='{$row['status_id']}' $selected>" . htmlspecialchars($row['status_name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- Added ID tags to these inputs so Javascript can auto-fill them -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Purchase Date</label>
                        <input type="date" name="purchase_date" id="purchase_date" class="form-control shadow-sm" value="<?= htmlspecialchars($asset['purchase_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" id="warranty_expiry" class="form-control shadow-sm" value="<?= htmlspecialchars($asset['warranty_expiry'] ?? '') ?>">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Total Cost (₹)</label>
                        <input type="number" step="0.01" min="0" name="cost" id="cost" class="form-control shadow-sm" value="<?= htmlspecialchars($asset['cost'] ?? '') ?>">
                    </div>
                </div>

                <!-- ACTION BUTTONS -->
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="asset_details.php?id=<?= $id ?>" class="btn btn-light border px-4 shadow-sm">Cancel</a>
                    <button type="submit" name="update_asset" class="btn btn-warning px-5 fw-bold shadow-sm text-dark"><i class="bi bi-save me-1"></i> Update Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const mainCatSelect = document.getElementById("main_category_id");
        const subCatSelect = document.getElementById("sub_category_id");
        const modelSelect = document.getElementById("model_id");

        const allSubCats = Array.from(subCatSelect.options);
        const allModels = Array.from(modelSelect.options);

        const initialSubCat = "<?= htmlspecialchars($sub_cat_id) ?>";
        const initialModel = "<?= htmlspecialchars($asset['model_id'] ?? '') ?>";

        function filterSubCategories(isInitialLoad = false) {
            const parentId = mainCatSelect.value;
            
            subCatSelect.innerHTML = '<option value="">Select Subcategory</option>';

            allSubCats.forEach(option => {
                if (option.value === "") return;
                if (option.getAttribute('data-parent') === parentId) {
                    subCatSelect.appendChild(option.cloneNode(true));
                }
            });

            if (isInitialLoad && initialSubCat) {
                subCatSelect.value = initialSubCat;
            } else if (!isInitialLoad) {
                subCatSelect.value = "";
            }

            filterModels(isInitialLoad);
        }

        function filterModels(isInitialLoad = false) {
            const mainId = mainCatSelect.value;
            const subId = subCatSelect.value;
            const targetCategoryId = subId !== "" ? subId : mainId;

            modelSelect.innerHTML = '<option value="">Select Model</option>';

            allModels.forEach(option => {
                if (option.value === "") return;
                if (targetCategoryId === "" || option.getAttribute('data-category') === targetCategoryId) {
                    modelSelect.appendChild(option.cloneNode(true));
                }
            });

            if (isInitialLoad && initialModel) {
                modelSelect.value = initialModel;
            } else if (!isInitialLoad) {
                modelSelect.value = "";
            }
        }

        mainCatSelect.addEventListener('change', () => filterSubCategories(false));
        subCatSelect.addEventListener('change', () => filterModels(false));

        if (mainCatSelect.value !== "") {
            filterSubCategories(true);
        }

        // ----------------------------------------------------
        // NEW LOGIC: Model Auto-fill Data Fetching
        // ----------------------------------------------------
        const vendorSelect = document.getElementById("vendor_id");
        const purchaseDateInput = document.getElementById("purchase_date");
        const warrantyExpiryInput = document.getElementById("warranty_expiry");
        const costInput = document.getElementById("cost");

        modelSelect.addEventListener('change', function() {
            const selectedModelId = this.value;

            // In Edit Mode, we don't clear the fields if they unselect a model 
            // (so we don't accidentally wipe out their custom data unless they pick a new model)
            if (!selectedModelId) {
                return;
            }

            // Fetch data for the newly selected model via AJAX
            fetch(window.location.pathname + `?action=get_model_details&model_id=${selectedModelId}`)
                .then(response => response.json())
                .then(data => {
                    if (data && Object.keys(data).length > 0) {
                        if (data.vendor_id) vendorSelect.value = data.vendor_id;
                        if (data.purchase_date) purchaseDateInput.value = data.purchase_date;
                        if (data.warranty_expiry) warrantyExpiryInput.value = data.warranty_expiry;
                        if (data.cost) costInput.value = data.cost;
                    }
                })
                .catch(error => {
                    console.error('Error fetching model details:', error);
                });
        });
    });
</script>

<?php 
if (ob_get_length()) ob_end_flush();
include("../../includes/footer.php"); 
?>