<?php
require_once __DIR__ . '/bootstrap.php';

if (!is_app_logged_in()) {
    header("Location: login.php");
    exit;
}

// Fetch all tiles
$args = array(
    'post_type' => 'tile',
    'posts_per_page' => -1,
    'post_status' => 'publish'
);
$tiles = get_posts($args);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Tiles - QR Code Generator</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f6f8;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .logout-btn {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }

        .tile-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
            border: 1px solid #e1e4e8;
        }

        .tile-row-1 {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 2fr 1fr 100px;
            gap: 20px;
            padding: 15px;
            background: #fafbfc;
            border-bottom: 1px solid #e1e4e8;
            align-items: center;
            font-size: 14px;
        }

        .tile-row-2 {
            padding: 15px;
        }

        .col-title {
            font-weight: bold;
            font-size: 12px;
            color: #6a737d;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .col-value {
            font-size: 14px;
        }

        input[type="text"] {
            width: 100%;
            padding: 6px;
            border: 1px solid #d1d5da;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .finish-block {
            margin-bottom: 5px;
            padding: 10px;
            border-radius: 6px;
            background: #fff;
        }

        .finish-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eaecef;
            padding-bottom: 10px;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .size-row {
            display: grid;
            grid-template-columns: 200px 150px;
            gap: 15px;
            align-items: center;
            margin-bottom: 8px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-qr {
            background: #28a745;
        }

        .btn-pdf {
            background: #007bff;
        }

        .btn-save {
            background: #6c757d;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            display: none;
            z-index: 1000;
        }

        .size-row.price-row {
            display: inline-block;
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>QR Code & Print Card Generator</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="api.php?action=export_csv" class="action-btn"
                style="background:#007bff; color:white; padding:6px 12px; text-decoration:none; border-radius:4px; font-size:14px;">Export
                CSV</a>
            <button onclick="document.getElementById('csv_file').click()" class="action-btn"
                style="background:#28a745; color:white; border:none; padding:6px 12px; border-radius:4px; font-size:14px; cursor:pointer;">Import
                (Standard)</button>
            <form id="import_form" action="api.php?action=import_csv" method="POST" enctype="multipart/form-data"
                style="display:none;">
                <input type="file" name="csv_file" id="csv_file" accept=".csv"
                    onchange="document.getElementById('import_form').submit();">
            </form>
            <button onclick="document.getElementById('csv_code_file').click()" class="action-btn"
                style="background:#17a2b8; color:white; border:none; padding:6px 12px; border-radius:4px; font-size:14px; cursor:pointer;">Import
                by Code</button>
            <form id="import_code_form" action="api.php?action=import_by_code" method="POST"
                enctype="multipart/form-data" style="display:none;">
                <input type="file" name="csv_code_file" id="csv_code_file" accept=".csv"
                    onchange="document.getElementById('import_code_form').submit();">
            </form>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div id="toast" class="toast">Saved successfully!</div>

    <?php foreach ($tiles as $tile):
        // WP Data
        $material = function_exists('get_field') ? get_field('tile_material', $tile->ID) : '';
        $application = function_exists('get_field') ? get_field('tile_application', $tile->ID) : '';
        $finishes = function_exists('get_field') ? get_field('tile_finish', $tile->ID) : [];
        $url = get_permalink($tile->ID);

        // SQLite Data
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM tiles_meta WHERE post_id = ?");
        $stmt->execute([$tile->ID]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['slip_rating' => '', 'qrcode_description' => ''];

        $stmt = $pdo->prepare("SELECT finish_name, tile_size_name, price FROM tile_prices WHERE post_id = ?");
        $stmt->execute([$tile->ID]);
        $prices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $prices = [];
        foreach ($prices_raw as $row) {
            $prices[$row['finish_name']][$row['tile_size_name']] = $row['price'];
        }

        $stmt = $pdo->prepare("SELECT finish_name, slip_rating FROM tile_finishes_meta WHERE post_id = ?");
        $stmt->execute([$tile->ID]);
        $finishes_meta_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $finishes_meta = [];
        foreach ($finishes_meta_raw as $row) {
            $finishes_meta[$row['finish_name']] = $row['slip_rating'];
        }
        ?>
        <div class="tile-card" data-post-id="<?= $tile->ID ?>">
            <!-- Row 1 -->
            <div class="tile-row-1">
                <div>
                    <div class="col-title">Title</div>
                    <div class="col-value"><strong><?= htmlspecialchars($tile->post_title) ?></strong></div>
                </div>
                <div>
                    <div class="col-title">Material</div>
                    <div class="col-value"><?= htmlspecialchars($material ?: '-') ?></div>
                </div>
                <div>
                    <div class="col-title">Application</div>
                    <div class="col-value"><?= htmlspecialchars($application ?: '-') ?></div>
                </div>

                <div>
                    <div class="col-title">QR Code Desc</div>
                    <div><input type="text" class="input-meta" name="qrcode_description"
                            value="<?= htmlspecialchars($meta['qrcode_description']) ?>" placeholder="Description..."></div>
                </div>
                <div>
                    <div class="col-title">URL</div>
                    <div class="col-value"><a href="<?= $url ?>" target="_blank"
                            style="color:#0366d6; text-decoration:none; font-size:12px;">View Post</a></div>
                </div>
                <div>
                    <div class="col-title">Action</div>
                    <button class="btn btn-save" onclick="saveMeta(<?= $tile->ID ?>, this)">Save Meta</button>
                </div>
            </div>

            <!-- Row 2 -->
            <div class="tile-row-2">
                <?php if ($finishes && is_array($finishes)): ?>
                    <?php foreach ($finishes as $finish):
                        $f_name = $finish['finish_name'] ?? '';
                        $f_code = $finish['product_code'] ?? '';
                        $sizes = $finish['tile_size'] ?? [];
                        $f_slip = $finishes_meta[$f_name] ?? '';
                        ?>
                        <div class="finish-block">
                            <div class="finish-header">
                                <div style="display: flex; align-items: center; gap: 25px;">
                                    <div>
                                        <span style="color:#24292e;">Finish:
                                            <strong><?= htmlspecialchars($f_name) ?></strong></span>
                                        <span style="color:#6a737d; font-size:12px; margin-left:10px;">(Code:
                                            <?= htmlspecialchars($f_code) ?>)</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <span style="font-size: 13px; color: #586069; font-weight: 400; margin-right: 1px;">Slip
                                            Rating:</span>
                                        <input type="text" value="<?= htmlspecialchars($f_slip) ?>"
                                            style="padding: 4px; border: 1px solid #d1d5da; border-radius: 3px; font-size: 13px; width: 120px;"
                                            onblur="saveFinishMeta(<?= $tile->ID ?>, '<?= htmlspecialchars(addslashes($f_name)) ?>', this.value)"
                                            placeholder="e.g. P5">
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="api.php?action=qrcode&post_id=<?= $tile->ID ?>&finish=<?= urlencode($f_name) ?>"
                                        target="_blank" class="btn btn-qr">Generate QR Code</a>
                                    <a href="api.php?action=pdf&post_id=<?= $tile->ID ?>&finish=<?= urlencode($f_name) ?>"
                                        target="_blank" class="btn btn-pdf">Generate Print Card</a>
                                </div>
                            </div>
                            <div class="finish-sizes">
                                <?php if ($sizes && is_array($sizes)): ?>
                                    <?php foreach ($sizes as $size):
                                        $s_name = $size['tile_size_name'] ?? '';
                                        $s_price = $prices[$f_name][$s_name] ?? '';
                                        ?>
                                        <div class="size-row price-row">
                                            <div style="font-size:14px;"><?= htmlspecialchars($s_name) ?></div>
                                            <div>
                                                <input type="text" value="<?= htmlspecialchars($s_price) ?>"
                                                    onblur="savePrice(<?= $tile->ID ?>, '<?= htmlspecialchars(addslashes($f_name)) ?>', '<?= htmlspecialchars(addslashes($s_name)) ?>', this.value)">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="font-size:13px; color:#666;">No sizes found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="font-size:13px; color:#666;">No finishes configured for this tile.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        function showToast(msg) {
            const t = document.getElementById('toast');
            t.textContent = msg;
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 3000);
        }

        function saveMeta(postId, btn) {
            const card = document.querySelector(`.tile-card[data-post-id="${postId}"]`);
            const qrDesc = card.querySelector('input[name="qrcode_description"]').value;

            btn.textContent = 'Saving...';

            fetch('api.php?action=save_meta', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `post_id=${postId}&qrcode_description=${encodeURIComponent(qrDesc)}`
            })
                .then(r => r.json())
                .then(data => {
                    btn.textContent = 'Save Meta';
                    if (data.success) showToast('Meta data saved!');
                    else alert('Failed to save meta');
                });
        }

        function savePrice(postId, finishName, sizeName, price) {
            fetch('api.php?action=save_price', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `post_id=${postId}&finish_name=${encodeURIComponent(finishName)}&size_name=${encodeURIComponent(sizeName)}&price=${encodeURIComponent(price)}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) showToast('Price saved!');
                    else alert('Failed to save price');
                });
        }

        function saveFinishMeta(postId, finishName, slipRating) {
            fetch('api.php?action=save_finish_meta', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `post_id=${postId}&finish_name=${encodeURIComponent(finishName)}&slip_rating=${encodeURIComponent(slipRating)}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) showToast('Slip rating saved!');
                    else alert('Failed to save slip rating');
                });
        }
    </script>
</body>

</html>