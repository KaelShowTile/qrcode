<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Card</title>
    <style>
        @page {
            margin: 2mm;
            size: 64mm 47mm;
        }

        body {
            font-family: sans-serif;
            font-size: 8px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            color: #000;
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="title-box">
        <div class="title"><?= htmlspecialchars($tile->post_title) ?></div>
        <div class="subtitle"><?= htmlspecialchars($material ?: '-') ?>
            · <?= htmlspecialchars($application ?: '-') ?></div>
    </div>

    <div class="finish-title">
        <?= htmlspecialchars($selected_finish['finish_name'] ?? '-') ?>
        <?= htmlspecialchars($meta['slip_rating'] ?: '-') ?>
    </div>

    <div class="sizes-table">
        <?php
        $sizes = $selected_finish['tile_size'] ?? [];
        if ($sizes && is_array($sizes)):
            foreach ($sizes as $size):
                $s_name = $size['tile_size_name'] ?? '-';
                ?>
                <li><?= htmlspecialchars($s_name) ?></li>
                <?php
            endforeach;
        endif;
        ?>
    </div>

    <div class="qr-des">
        <?= htmlspecialchars($meta['qrcode_description']) ?>
    </div>

    <div class="qr-box">
        <img class="qr-img" src="<?= $qr_image_data ?>">
        <div class="qr-des">scan for price and more info</div>
    </div>
</body>

</html>