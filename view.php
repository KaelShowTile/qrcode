<?php
require_once __DIR__ . '/bootstrap.php';

$post_id = (int) ($_GET['post_id'] ?? 0);
$code = $_GET['code'] ?? '';
$finish_name = $_GET['finish'] ?? '';

if (!$post_id || (trim($code) === '' && trim($finish_name) === '')) {
    die("<div style='padding:20px; font-family:sans-serif; text-align:center;'>Invalid request.</div>");
}

$tile = get_post($post_id);
if (!$tile) {
    die("<div style='padding:20px; font-family:sans-serif; text-align:center;'>Tile not found.</div>");
}

$finishes = function_exists('get_field') ? get_field('tile_finish', $post_id) : [];
$matched_finishes = [];
if ($finishes && is_array($finishes)) {
    if (trim($code) !== '') {
        foreach ($finishes as $f) {
            if (($f['product_code'] ?? '') === $code) {
                $matched_finishes[] = $f;
                break;
            }
        }
    } else {
        // Legacy QR code fallback: match all finishes sharing the requested finish_name
        foreach ($finishes as $f) {
            if (($f['finish_name'] ?? '') === $finish_name) {
                $matched_finishes[] = $f;
            }
        }
    }
}

if (empty($matched_finishes)) {
    die("<div style='padding:20px; font-family:sans-serif; text-align:center;'>Finish not found.</div>");
}

global $pdo;
$price_by_code_stmt = $pdo->prepare("SELECT tile_size_name, price FROM tile_prices WHERE post_id = ? AND product_code = ?");
$price_by_finish_stmt = $pdo->prepare("SELECT tile_size_name, price FROM tile_prices WHERE post_id = ? AND product_code = '' AND finish_name = ?");

$display_variants = [];
foreach ($matched_finishes as $mf) {
    $mf_code = $mf['product_code'] ?? '';
    $mf_name = $mf['finish_name'] ?? $finish_name;
    if (trim((string) $mf_code) !== '') {
        $price_by_code_stmt->execute([$post_id, $mf_code]);
        $prices_raw = $price_by_code_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $price_by_finish_stmt->execute([$post_id, $mf_name]);
        $prices_raw = $price_by_finish_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $prices = [];
    foreach ($prices_raw as $row) {
        $prices[$row['tile_size_name']] = $row['price'];
    }
    $display_variants[] = [
        'finish_name' => $mf_name,
        'product_code' => $mf_code,
        'sizes' => $mf['tile_size'] ?? [],
        'virtual_theatre' => $mf['visual_theatre'] ?? [],
        'prices' => $prices,
    ];
}

$wp_url = get_permalink($post_id);

$stmt_meta = $pdo->prepare("SELECT qrcode_description FROM tiles_meta WHERE post_id = ?");
$stmt_meta->execute([$post_id]);
$meta_row = $stmt_meta->fetch(PDO::FETCH_ASSOC);
$qrcode_desc = $meta_row ? $meta_row['qrcode_description'] : '';

// Try to get post thumbnail for a beautiful background or header
$thumbnail_url = get_the_post_thumbnail_url($post_id, 'large');

// Fallback background if no featured image
if (!$thumbnail_url) {
    $thumbnail_url = 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?q=80&w=1000&auto=format&fit=crop';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tile->post_title) ?> - Pricing</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'roboto', Helvetica, Arial, sans-serif;
            min-height: 100vh;
            letter-spacing: 0.5px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            background: #000000;
        }

        .container {
            width: 100%;
            max-width: 480px;
            padding: 20px;
            box-sizing: border-box;
            background: #fff;
        }

        .container.logo-container {
            background: #000;
            text-align: center;
            padding: 20px 20px 10px;
        }

        .logo-container img {
            width: 80%;
        }

        .product-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px 0;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
        }

        .meta-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .meta-value {
            font-size: 12px;
            font-weight: 400;
            color: #666;
        }

        .meta-value.finish-value {
            text-transform: uppercase;
            font-size: 14px;
            margin-top: 15px;
            margin-bottom: 1px;
            color: #000;
        }

        .price-list {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 9px 0 8px;
            border-bottom: 1px solid #eee;
        }

        .size-name {
            font-size: 14px;
            font-weight: 400;
        }

        .price-value {
            font-size: 14px;
            font-weight: 700;
        }

        .empty-sizes {
            text-align: center;
            color: var(--text-muted);
            font-style: italic;
            padding: 20px 0;
        }

        .qr-desc {
            margin: 15px 0 20px 0;
            padding: 5px 0;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 8px;
            font-size: 13px;
            color: #555;
            line-height: 1.5;
            text-align: left;
        }

        .btn-visit {
            display: block;
            width: 100%;
            padding: 12px 0 10px;
            background: #ff0000;
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 400;
            text-transform: uppercase;
        }
    </style>
</head>

<body>

    <div class="container logo-container">
        <img src="https://showtile.com.au/wp-content/uploads/2026/02/st-logo-svg-w.svg">
    </div>

    <div class="container">
        <h1 class="product-title"><?= htmlspecialchars($tile->post_title) ?></h1>

        <?php $is_multi = count($display_variants) > 1; ?>
        <?php foreach ($display_variants as $variant): ?>
            <div class="product-meta">
                <div class="meta-item">
                    <span class="meta-value finish-value"><?= htmlspecialchars($variant['finish_name']) ?></span>
                    <span class="meta-value">Code: <?= htmlspecialchars($variant['product_code'] ?: '-') ?></span>
                </div>
            </div>

            <div class="price-list">
                <?php if ($variant['sizes'] && is_array($variant['sizes'])): ?>
                    <?php foreach ($variant['sizes'] as $size):
                        $s_name = $size['tile_size_name'] ?? '';
                        $s_price = $variant['prices'][$s_name] ?? '-';
                        ?>
                        <div class="price-row">
                            <span class="size-name"><?= htmlspecialchars($s_name) ?></span>
                            <span class="price-value"><?= htmlspecialchars($s_price) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-sizes"></div>
                <?php endif; ?>
            </div>

            <?php if ($is_multi && !empty($variant['virtual_theatre']) && is_string($variant['virtual_theatre'])): ?>
                <a href="<?= htmlspecialchars($variant['virtual_theatre']) ?>" class="btn-visit" style="margin-bottom: 15px;">Virtual Theatre</a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!empty($qrcode_desc)): ?>
            <div class="qr-desc">
                <?= $qrcode_desc ?>
            </div>
        <?php endif; ?>
        <?php if (!$is_multi && !empty($display_variants[0]['virtual_theatre']) && is_string($display_variants[0]['virtual_theatre'])): ?>
            <a href="<?= htmlspecialchars($display_variants[0]['virtual_theatre']) ?>" class="btn-visit" style="margin-bottom: 10px;">Virtual Theatre</a>
        <?php endif; ?>
        <a href="<?= $wp_url ?>?utm_source=showroom&utm_medium=qrcode&utm_campaign=showroom" class="btn-visit">View Collection</a>
    </div>

</body>

</html>