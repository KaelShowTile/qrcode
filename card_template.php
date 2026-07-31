<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Card</title>
    <style>
        @page {
            margin: 6mm 7mm 0;
            size: 63.5mm 46.6mm;
            font-family: sans-serif;
        }

        body {
            font-family: sans-serif;
            font-size: 8px;
            line-height: 1.2;
            margin: 0;
            color: #000;
            overflow: hidden;
        }

        li {
            font-family: sans-serif;
        }

        .title-box {
            margin-bottom: 5%;
            height: 20%;
        }

        .title {
            font-size: 13px;
        }

        .card-body {
            width: 100%;
            height: 72%;
        }

        .body-left {
            width: 50%;
        }

        .body-right {
            width: 50%;
            text-align: center;
        }

        .qr-img {
            width: 75%;
            margin-bottom: 3px;
        }

        .qr-des {
            font-size: 7px;
        }

        .finish-title {
            text-transform: capitalize;
            font-size: 10px;
            border-bottom: 1px solid #ccc;
            margin-bottom: 5px;
        }

        .sizes-table {
            margin-bottom: 10px;
        }

        .sizes-table li {
            font-weight: 600;
            font-size: 10px;
            font-family: sans-serif;
        }

        .extra-des {
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="title-box">
        <div class="title"><?= htmlspecialchars($tile->post_title) ?></div>
        <div class="subtitle"><?= htmlspecialchars($material ?: '-') ?>
            · <?= htmlspecialchars($application ?: '-') ?></div>
    </div>

    <table class="card-body">
        <td class="body-left">
            <div class="finish-title">
                <?= htmlspecialchars($selected_finish['finish_name'] ?? '-') ?>
                <?= htmlspecialchars($meta['slip_rating']) ?>
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

            <div class="extra-des">
                <?= htmlspecialchars($meta['qrcode_description']) ?>
            </div>
        </td>

        <td class="body-right qr-box">
            <img class="qr-img" src="<?= $qr_image_data ?>">
            <div class="qr-des">scan for price and more info</div>
        </td>
    </table>
</body>

</html>