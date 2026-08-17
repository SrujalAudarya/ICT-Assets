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
    // Build the WHERE clause exactly matching your current table filters!
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

    // Fetch ALL matching assets across ALL pages
    $query = "
        SELECT a.asset_id, a.asset_name, a.serial_number, c.category_name, m.model_name, l.dept_name
        FROM assets a
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
        LEFT JOIN asset_status s ON a.status_id = s.status_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN asset_models m ON a.model_id = m.model_id
        LEFT JOIN vendors v ON a.vendor_id = v.vendor_id
        LEFT JOIN asset_assignments aa ON a.asset_id = aa.asset_id AND aa.returned_date IS NULL
        LEFT JOIN users u ON aa.user_id = u.user_id
        $where
    ";
} else {
    // Normal ID-based selection
    $ids = array_map('intval', $_POST['asset_ids']);
    $ids_string = implode(',', $ids);
    $query = "
        SELECT a.asset_id, a.asset_name, a.serial_number, c.category_name, m.model_name, l.dept_name
        FROM assets a
        LEFT JOIN asset_categories c ON a.category_id = c.category_id
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

        /* 
           LABEL SIZING 
           Standard thermal barcode label size ~ 100mm x 50mm (Approx 380px x 190px) 
        */
        .label-card { 
            background: #fff; 
            border: 2px solid #000; 
            width: 380px; 
            height: 190px; 
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
            font-size: 13px; 
            color: #555;
            text-transform: uppercase; 
            border-bottom: 1px solid #ccc;
            padding-bottom: 3px;
        }

        .label-info p { 
            margin: 3px 0; 
            font-size: 13px; 
            color: #000;
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }

        .barcode-svg { 
            width: 100%; 
            height: 45px; 
            margin-top: 10px; 
        }

        .qr-code { 
            width: 90px; 
            height: 90px; 
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Center contents if only Barcode is selected */
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
        <?php while($row = mysqli_fetch_assoc($result)): ?>
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
                    
                    <!-- BARCODE -->
                    <?php if ($code_type == 'both' || $code_type == 'barcode'): ?>
                        <svg class="barcode-svg" id="barcode-<?= $row['asset_id'] ?>"></svg>
                    <?php endif; ?>
                </div>
                
                <!-- QR CODE -->
                <?php if ($code_type == 'both' || $code_type == 'qr'): ?>
                    <div class="qr-code" id="qrcode-<?= $row['asset_id'] ?>"></div>
                <?php endif; ?>
            </div>
            
            <script>
                <?php if ($code_type == 'both' || $code_type == 'qr'): ?>
                // Generate QR Code (Encodes the Serial Number)
                new QRCode(document.getElementById("qrcode-<?= $row['asset_id'] ?>"), {
                    text: "<?= htmlspecialchars($row['serial_number']) ?>",
                    width: 90,
                    height: 90,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.L
                });
                <?php endif; ?>
                
                <?php if ($code_type == 'both' || $code_type == 'barcode'): ?>
                // Generate Barcode (Encodes the Serial Number)
                JsBarcode("#barcode-<?= $row['asset_id'] ?>", "<?= htmlspecialchars($row['serial_number']) ?>", {
                    format: "CODE128",
                    width: 1.5,
                    height: 35,
                    displayValue: false,
                    margin: 0
                });
                <?php endif; ?>
            </script>
        <?php endwhile; ?>
    </div>

</body>
</html>