<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");
include("../../includes/header.php");
include("../../includes/sidebar.php");

if(isset($_POST['upload_ajax'])) {
    // Clear any previous output to ensure clean JSON
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    try {
        $asset_id = mysqli_real_escape_string($conn, $_POST['asset_id']);
        $doc_type = mysqli_real_escape_string($conn, $_POST['document_type']);
        $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

        if (!isset($_FILES['file'])) {
            throw new Exception("No file uploaded.");
        }

        $original_name = $_FILES['file']['name'];
        $file_ext = pathinfo($original_name, PATHINFO_EXTENSION);
        $file_name = "DOC_" . $asset_id . "_" . time() . "." . $file_ext;

        $upload_dir = "../../uploads/";
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $upload_dir));
        }

        $path = $upload_dir . $file_name;
        $db_path = "uploads/" . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
            $stmt = mysqli_prepare($conn, "INSERT INTO documents (asset_id, file_name, file_path, document_type) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isss", $asset_id, $original_name, $db_path, $doc_type);
            
            if(mysqli_stmt_execute($stmt)) {
                echo json_encode(['status' => 'success', 'redirect' => 'documents_list.php']);
            } else {
                throw new Exception("Database error: " . mysqli_error($conn));
            }
        } else {
            throw new Exception("Failed to move uploaded file.");
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}
?>

<style>
    #progressBar {
        transition: width 0.3s ease-in-out;
    }
    .upload-overlay {
        display:none; 
        position: fixed; 
        top: 0; 
        left: 0; 
        width: 100%; 
        height: 100%; 
        background: rgba(0,0,0,0.85); 
        z-index: 10000; 
        color: white; 
        text-align: center; 
        padding-top: 15vh;
        backdrop-filter: blur(5px);
    }
</style>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Upload Operational Document</h4>
        </div>
        <div class="card-body">
            <!-- Progress Overlay -->
            <div id="uploadOverlay" class="upload-overlay">
                <div class="spinner-border text-success mb-4" role="status" style="width: 5rem; height: 5rem;"></div>
                <h2 id="statusText" class="mb-4">Preparing Upload...</h2>
                <div class="container" style="max-width: 600px;">
                    <div class="progress" style="height: 35px; background-color: rgba(255,255,255,0.2);">
                        <div id="progressBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%; font-weight: bold; font-size: 1.2rem;">0%</div>
                    </div>
                    <p class="mt-4 fs-5 text-info">Please do not refresh or close this page.</p>
                </div>
            </div>

            <form id="uploadForm" method="post" enctype="multipart/form-data">
                <input type="hidden" name="upload_ajax" value="1">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Select Asset</label>
                        <select name="asset_id" class="form-select" required>
                            <option value="">-- Choose Asset --</option>
                            <?php
                            $assets = mysqli_query($conn,"SELECT asset_id, asset_name, serial_number FROM assets ORDER BY asset_name ASC");
                            while($row = mysqli_fetch_assoc($assets)) {
                                echo "<option value='{$row['asset_id']}'>{$row['asset_name']} ({$row['serial_number']})</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Document Type</label>
                        <select name="document_type" class="form-select" required>
                            <option value="OTHER">General Document</option>
                            <option value="SALE_ORDER">Sale Order</option>
                            <option value="INVOICE">Invoice</option>
                            <option value="WARRANTY">Warranty Card</option>
                            
                            <option value="INSURANCE">Insurance Policy</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Select File</label>
                    <input type="file" name="file" id="fileInput" class="form-control" required>
                    <small class="text-muted">Supported formats: PDF, DOCX, JPG, PNG (Max 20MB)</small>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Remarks / Description</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Briefly describe this document..."></textarea>
                </div>

                <div class="mt-4 border-top pt-3">
                    <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm">
                        <i class="bi bi-cloud-upload"></i> Start Upload
                    </button>
                    <a href="documents_list.php" class="btn btn-secondary btn-lg px-5">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('uploadForm').onsubmit = function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const overlay = document.getElementById('uploadOverlay');
    const bar = document.getElementById('progressBar');
    const status = document.getElementById('statusText');
    
    // 1. Show overlay immediately
    overlay.style.display = 'block';
    console.log("Upload started...");

    // 2. Use a small timeout to let the browser render the overlay before starting the heavy XHR
    setTimeout(() => {
        const xhr = new XMLHttpRequest();
        
        xhr.upload.onprogress = function(event) {
            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                console.log("Progress: " + percent + "%");
                bar.style.width = percent + '%';
                bar.innerHTML = percent + '%';
                
                if (percent < 100) {
                    status.innerHTML = "Uploading Document (" + percent + "%)...";
                } else {
                    status.innerHTML = "Processing & Saving on Server...";
                    bar.classList.add('bg-info');
                }
            }
        };
        
        xhr.onload = function() {
            console.log("Server Response: ", xhr.responseText);
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.status === 'success') {
                    status.innerHTML = "Success! Redirecting...";
                    setTimeout(() => { window.location.href = response.redirect; }, 500);
                } else {
                    alert('Error: ' + response.message);
                    overlay.style.display = 'none';
                }
            } catch (err) {
                console.error("Parsing Error:", err, xhr.responseText);
                alert('Server returned an invalid response. Check console for details.');
                overlay.style.display = 'none';
            }
        };
        
        xhr.onerror = function() {
            alert('Network error occurred during upload.');
            overlay.style.display = 'none';
        };
        
        xhr.open('POST', window.location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    }, 100);
};
</script>

<?php include("../../includes/footer.php"); ?>
