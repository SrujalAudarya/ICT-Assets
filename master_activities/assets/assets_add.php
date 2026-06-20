<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

// Helper function for document uploads
function uploadDoc($conn, $asset_id, $file_input, $type) {
    if(!empty($_FILES[$file_input]['name'])) {
        $original_name = $_FILES[$file_input]['name'];
        $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $file_name = $type . "_" . $asset_id . "_" . time() . "." . $file_ext;
        
        $upload_dir = "../../uploads/";
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $upload_dir));
        }
        
        $path = $upload_dir . $file_name;
        $db_path = "uploads/" . $file_name;

        if(move_uploaded_file($_FILES[$file_input]['tmp_name'], $path)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO documents (asset_id, file_name, file_path, document_type) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $asset_id, $original_name, $db_path, $type);
            mysqli_stmt_execute($stmt);
        }
    }
}

if(isset($_POST['save_ajax'])) {
    // Clear any previous output to ensure clean JSON
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $name = mysqli_real_escape_string($conn, $_POST['asset_name']);
    $serial = mysqli_real_escape_string($conn, $_POST['serial_number']);
    $category = mysqli_real_escape_string($conn, $_POST['category_id']);
    $model_id = mysqli_real_escape_string($conn, $_POST['model_id']);
    $vendor = mysqli_real_escape_string($conn, $_POST['vendor_id']);
    $location = mysqli_real_escape_string($conn, $_POST['location_id']);
    $status = mysqli_real_escape_string($conn, $_POST['status_id']);
    $date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $warranty_expiry = mysqli_real_escape_string($conn, $_POST['warranty_expiry']);
    $cost = mysqli_real_escape_string($conn, $_POST['cost']);

    $query = "INSERT INTO assets
    (asset_name, serial_number, category_id, model_id, vendor_id, location_id, status_id, purchase_date, warranty_expiry, cost)
    VALUES ('$name','$serial','$category','$model_id','$vendor','$location','$status','$date','$warranty_expiry','$cost')";
    
    if(mysqli_query($conn, $query)) {
        $asset_id = mysqli_insert_id($conn);
        uploadDoc($conn, $asset_id, "sale_order", "SALE_ORDER");
        uploadDoc($conn, $asset_id, "invoice", "INVOICE");
        uploadDoc($conn, $asset_id, "warranty_doc", "WARRANTY");

        echo json_encode(['status' => 'success', 'redirect' => 'assets_list.php']);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        exit();
    }
}

include("../../includes/header.php");
include("../../includes/sidebar.php");
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Add New Asset & Procurement Documents</h4>
        </div>
        <div class="card-body">
            <!-- Progress Overlay -->
            <div id="uploadOverlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; color: white; text-align: center; padding-top: 15%;">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 4rem; height: 4rem;"></div>
                <h2 id="statusText">Uploading Asset Data...</h2>
                <div class="container mt-4" style="max-width: 600px;">
                    <div class="progress" style="height: 30px;">
                        <div id="progressBar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%; font-weight: bold;">0%</div>
                    </div>
                    <p class="mt-3 fs-5">Please wait, do not refresh or close the page.</p>
                </div>
            </div>

            <form id="assetForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="save_ajax" value="1">
                <h5 class="text-primary border-bottom pb-2 mb-3">Basic Information</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Asset Name</label>
                        <input type="text" name="asset_name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php
                            $res = mysqli_query($conn,"SELECT * FROM asset_categories ORDER BY category_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['category_id']}'>{$row['category_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Model</label>
                        <select name="model_id" class="form-select">
                            <option value="">Select Model</option>
                            <?php
                            $res = mysqli_query($conn,"SELECT * FROM asset_models ORDER BY model_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['model_id']}'>{$row['model_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Vendor</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Select Vendor</option>
                            <?php
                            $res = mysqli_query($conn,"SELECT * FROM vendors ORDER BY vendor_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['vendor_id']}'>{$row['vendor_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Location</label>
                        <select name="location_id" class="form-select">
                            <option value="">Select Location</option>
                            <?php
                            $res = mysqli_query($conn,"SELECT * FROM locations ORDER BY dept_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['location_id']}'>{$row['dept_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select name="status_id" class="form-select">
                            <option value="">Select Status</option>
                            <?php
                            $res = mysqli_query($conn,"SELECT * FROM asset_status ORDER BY status_name ASC");
                            while($row = mysqli_fetch_assoc($res)) {
                                echo "<option value='{$row['status_id']}'>{$row['status_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty Expiry</label>
                        <input type="date" name="warranty_expiry" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cost (₹)</label>
                        <input type="number" step="0.01" name="cost" class="form-control">
                    </div>
                </div>

                <h5 class="text-primary border-bottom pb-2 mt-4 mb-3">Procurement Documents</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Sale Order</label>
                        <input type="file" name="sale_order" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Invoice</label>
                        <input type="file" name="invoice" class="form-control">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Warranty Card</label>
                        <input type="file" name="warranty_doc" class="form-control">
                    </div>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" class="btn btn-primary btn-lg px-5">Save Asset & Documents</button>
                    <a href="assets_list.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('assetForm').onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const xhr = new XMLHttpRequest();
    
    document.getElementById('uploadOverlay').style.display = 'block';
    
    xhr.upload.onprogress = function(event) {
        if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            const bar = document.getElementById('progressBar');
            bar.style.width = percent + '%';
            bar.innerHTML = percent + '%';
            if(percent === 100) document.getElementById('statusText').innerHTML = "Finalizing...";
        }
    };
    
    xhr.onload = function() {
        try {
            const response = JSON.parse(xhr.responseText);
            if (response.status === 'success') window.location.href = response.redirect;
            else {
                alert('Error: ' + response.message);
                document.getElementById('uploadOverlay').style.display = 'none';
            }
        } catch(e) {
            console.error(xhr.responseText);
            alert('An unexpected error occurred.');
            document.getElementById('uploadOverlay').style.display = 'none';
        }
    };
    
    xhr.open('POST', window.location.href, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.send(formData);
};
</script>

<?php include("../../includes/footer.php"); ?>
