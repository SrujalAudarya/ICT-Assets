<?php
global $conn;
include("../../includes/auth.php");
include("../../config/db.php");

$is_select_all = isset($_POST['select_all_pages']) && $_POST['select_all_pages'] == '1';

// 1. Safety check
if (!$is_select_all && (!isset($_POST['asset_ids']) || empty($_POST['asset_ids']))) {
    die("<div style='font-family: Arial; padding: 50px; text-align: center; color: red;'>
            <h2>No assets selected!</h2>
            <p>Please close this tab, select at least one asset from the list, and try again.</p>
         </div>");
}

// Read the preferences from the Modal Popup
$code_type = $_POST['code_type'] ?? 'both'; // 'qr', 'barcode', or 'both'
$selected_fields = $_POST['label_fields'] ?? []; // Array of fields to show

$show_name     = in_array('asset_name', $selected_fields);
$show_sn       = in_array('serial_number', $selected_fields);
$show_category = in_array('category', $selected_fields);
$show_model    = in_array('model', $selected_fields);
$show_location = in_array('location', $selected_fields);

$where = "WHERE 1=1";

if ($is_select_all) {
    $search   = trim($_POST['filter_search'] ?? '');
    $category = $_POST['filter_category'] ?? '';
    $status   = $_POST['filter_status'] ?? '';
    $location = $_POST['filter_location'] ?? '';
    $model    = $_POST['filter_model'] ?? '';

    if ($search != "") {
        $search_escaped = mysqli_real_escape_string($conn, $search);
        $where .= " AND (
            a.asset_name LIKE '%$search_escaped%' OR
            a.serial_number LIKE '%$search_escaped%' OR
            u.name LIKE '%$search_escaped%'
        )";
    }
    if ($category != "") {
        $category = (int)$category;
        $cat_ids = [$category];
        $sub_cats_query = mysqli_query($conn, "SELECT category_id FROM asset_categories WHERE parent_id = $category");
        if ($sub_cats_query) {
            while ($sub = mysqli_fetch_assoc($sub_cats_query)) {
                $cat_ids[] = $sub['category_id'];
            }
        }
        $cat_ids_str = implode(',', $cat_ids);
        $where .= " AND a.category_id IN ($cat_ids_str)";
    }
    if ($status != "") $where .= " AND a.status_id = " . (int)$status;
    if ($location != "") $where .= " AND a.location_id = " . (int)$location;
    if ($model != "") $where .= " AND a.model_id = " . (int)$model;

    $query = "
        SELECT a.asset_id, a.asset_name, a.serial_number, a.warranty_expiry, 
               c.category_name, m.model_name, l.dept_name, s.status_name
        FROM assets a
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        LEFT JOIN asset_status s ON a.status_id = s.status_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN asset_models m ON a.model_id = m.model_id
        $where
    ";
} else {
    // Normal ID-based selection
    $ids = array_map('intval', $_POST['asset_ids']);
    $ids_string = implode(',', $ids);
    
    $query = "
        SELECT a.asset_id, a.asset_name, a.serial_number, a.warranty_expiry, 
               c.category_name, m.model_name, l.dept_name, s.status_name
        FROM assets a
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        LEFT JOIN asset_status s ON a.status_id = s.status_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN asset_models m ON a.model_id = m.model_id
        WHERE a.asset_id IN ($ids_string)
    ";
}

$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Asset Labels</title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #e9ecef; 
            text-align: center; 
            margin: 0; 
            padding: 20px; 
        }
        
        .print-controls {
            margin-bottom: 30px;
            padding: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: inline-block;
        }

        .btn-print { 
            background: #198754; 
            color: #fff; 
            border: none; 
            padding: 12px 30px; 
            font-size: 16px; 
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer; 
            transition: 0.2s;
        }
        
        .btn-print:hover { background: #157347; }

        .label-grid { 
            display: flex; 
            flex-wrap: wrap; 
            justify-content: center; 
            gap: 15px; 
            max-width: 1200px;
            margin: 0 auto;
        }

        .label-card { 
            background: #fff; 
            border: 2px solid #000; 
            width: 420px; 
            height: 200px; 
            padding: 12px; 
            box-sizing: border-box; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            border-radius: 8px; 
            page-break-inside: avoid;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .label-info { 
            flex: 1; 
            text-align: left; 
            overflow: hidden; 
            padding-right: 15px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .label-info h5 { 
            margin: 0 0 5px 0; 
            font-size: 14px; 
            color: #333;
            text-transform: uppercase; 
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
        }

        .label-info p { 
            margin: 2px 0; 
            font-size: 12px; 
            color: #000;
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }

        .barcode-svg { 
            width: 100%; 
            height: 40px; 
            margin-top: 8px; 
        }

        /* Adjusted for the larger, text-heavy QR code */
        .qr-code { 
            width: 145px; 
            height: 145px; 
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .layout-barcode-only .label-info { padding-right: 0; text-align: center; }
        .layout-barcode-only h5 { text-align: center; }

        @media print {
            body { background: #fff; padding: 0; }
            .print-controls { display: none; }
            .label-grid { gap: 5px; max-width: 100%; }
            .label-card { border: 1px dashed #999; border-radius: 0; margin-bottom: 10px; box-shadow: none; }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <h3 style="margin-top: 0;">Label Generation Complete</h3>
        <button class="btn-print" onclick="window.print()">🖨️ Print Labels Now</button>
        <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">Set your printer margins to "None" and uncheck "Headers and Footers" for best results.</p>
    </div>

    <div class="label-grid">
        <?php 
        while($row = mysqli_fetch_assoc($result)): 
            
            // -------------------------------------------------------------
            // 1. CALCULATE WARRANTY LOGIC
            // -------------------------------------------------------------
            $warranty_text = "N/A";
            if (!empty($row['warranty_expiry'])) {
                $exp_time = strtotime($row['warranty_expiry']);
                $days_left = floor(($exp_time - time()) / (60 * 60 * 24));
                
                if ($days_left < 0) {
                    $warranty_text = date('d-M-Y', $exp_time) . " (Expired)";
                } else {
                    $warranty_text = date('d-M-Y', $exp_time) . " ({$days_left} days left)";
                }
            }

            // -------------------------------------------------------------
            // 2. FETCH ASSIGNMENT HISTORY & CURRENT USER
            // -------------------------------------------------------------
            $asset_id = $row['asset_id'];
            $hist_q = mysqli_query($conn, "
                SELECT u.name, asn.assigned_date, asn.returned_date 
                FROM asset_assignments asn 
                JOIN users u ON asn.user_id = u.user_id 
                WHERE asn.asset_id = $asset_id 
                ORDER BY asn.assigned_date DESC LIMIT 3
            ");
            
            $history_str = "";
            $current_user = "Not Assigned";
            $is_first = true;

            while($h = mysqli_fetch_assoc($hist_q)) {
                $ret = $h['returned_date'] ? date('d-M-y', strtotime($h['returned_date'])) : "Present";
                
                // Using a hyphen instead of a dot to ensure 100% scanner compatibility
                $history_str .= "- " . $h['name'] . " (" . date('d-M-y', strtotime($h['assigned_date'])) . " to $ret)\n";
                
                // If the most recent record has no return date, this person is the Current User
                if ($is_first && empty($h['returned_date'])) {
                    $current_user = $h['name'];
                }
                $is_first = false;
            }
            if(empty($history_str)) {
                $history_str = "No history available.\n";
            }

            // -------------------------------------------------------------
            // 3. COMPILE FULL, BEAUTIFUL TEXT BLOCK FOR THE NOTES APP
            // -------------------------------------------------------------
            $qr_text = "=== ASSET DETAILS ===\n";
            $qr_text .= "Name: " . $row['asset_name'] . "\n";
            $qr_text .= "Serial No: " . $row['serial_number'] . "\n";
            $qr_text .= "Category: " . ($row['category_name'] ?? 'N/A') . "\n";
            $qr_text .= "Model: " . ($row['model_name'] ?? 'N/A') . "\n";
            $qr_text .= "Status: " . ($row['status_name'] ?? 'N/A') . "\n";
            $qr_text .= "Location: " . ($row['dept_name'] ?? 'N/A') . "\n";
            $qr_text .= "User: " . $current_user . "\n";
            $qr_text .= "Warranty: " . $warranty_text . "\n";
            $qr_text .= "\n=== HISTORY ===\n";
            $qr_text .= trim($history_str);
            
            // Encode safely for JavaScript
            $b64_qr_text = base64_encode($qr_text);
            $safe_sn_text = htmlspecialchars($row['serial_number'], ENT_QUOTES, 'UTF-8');
        ?>
            
            <div class="label-card <?= ($code_type == 'barcode') ? 'layout-barcode-only' : '' ?>">
                
                <div class="label-info">
                    <h5>Company Asset</h5>
                    
                    <?php if ($show_name): ?>
                        <p title="<?= htmlspecialchars($row['asset_name']) ?>"><strong>Asset:</strong> <?= htmlspecialchars($row['asset_name']) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($show_sn): ?>
                        <p><strong>SN:</strong> <?= htmlspecialchars($row['serial_number']) ?></p>
                    <?php endif; ?>

                    <?php if ($show_category): ?>
                        <p><strong>Cat:</strong> <?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></p>
                    <?php endif; ?>

                    <?php if ($show_model): ?>
                        <p><strong>Model:</strong> <?= htmlspecialchars($row['model_name'] ?? 'N/A') ?></p>
                    <?php endif; ?>

                    <?php if ($show_location): ?>
                        <p><strong>Loc:</strong> <?= htmlspecialchars($row['dept_name'] ?? 'N/A') ?></p>
                    <?php endif; ?>
                    
                    <!-- BARCODE DATA TARGET -->
                    <?php if ($code_type == 'both' || $code_type == 'barcode'): ?>
                        <svg class="barcode-svg js-barcode-target" data-serial="<?= $safe_sn_text ?>"></svg>
                    <?php endif; ?>
                </div>
                
                <!-- QR CODE DATA TARGET -->
                <?php if ($code_type == 'both' || $code_type == 'qr'): ?>
                    <div class="qr-code js-qrcode-target" data-base64="<?= $b64_qr_text ?>"></div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>

    <!-- RENDER SCRIPT -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            
            // 1. Render all QR Codes Safely
            const qrElements = document.querySelectorAll('.js-qrcode-target');
            qrElements.forEach(function(el) {
                const b64Data = el.getAttribute('data-base64');
                if (b64Data) {
                    try {
                        const decodedData = decodeURIComponent(escape(window.atob(b64Data)));
                        new QRCode(el, {
                            text: decodedData,
                            width: 135,  // Big enough to hold the full text block
                            height: 135, 
                            colorDark : "#000000",
                            colorLight : "#ffffff",
                            correctLevel : QRCode.CorrectLevel.L // Low error correction keeps dots thick
                        });
                    } catch(e) {
                        console.error("Failed to generate QR for data: ", b64Data, e);
                    }
                }
            });

            // 2. Render all Barcodes
            const barcodeElements = document.querySelectorAll('.js-barcode-target');
            barcodeElements.forEach(function(el) {
                const snData = el.getAttribute('data-serial');
                if (snData) {
                    try {
                        JsBarcode(el, snData, {
                            format: "CODE128",
                            width: 1.5,
                            height: 35,
                            displayValue: false,
                            margin: 0
                        });
                    } catch(e) {
                        console.error("Failed to generate Barcode for: ", snData, e);
                    }
                }
            });

        });
    </script>
</body>
</html>