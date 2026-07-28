<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>A4 Print Sheet</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        body {
            font-family: sans-serif;
            font-size: 8px;
            line-height: 1.2;
            margin: 0;
            padding: 0;
            color: #000;
        }

        .card-container {
            position: absolute;
            width: 63.5mm;
            height: 46.6mm;
            overflow: hidden;
            box-sizing: border-box;
            /* Inner padding to mimic original card margins */
            padding: 6mm 7mm 0;
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
            border-collapse: collapse;
        }

        .body-left {
            width: 50%;
            vertical-align: top;
        }

        .body-right {
            width: 50%;
            text-align: center;
            vertical-align: top;
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
            font-size: 9px;
            border-bottom: 1px solid #ccc;
            margin-bottom: 5px;
        }

        .sizes-table {
            margin-bottom: 10px;
            padding: 0;
            list-style: none;
        }

        .sizes-table li {
            font-weight: 600;
            font-size: 9px;
            margin-left: 10px;
            display: list-item;
            list-style-type: disc;
        }

        .extra-des {
            font-size: 7px;
        }
    </style>
</head>

<body>
    <?php foreach ($print_items as $index => $item):
        // Calculate coordinates
        $col = $index % 3;
        $row = floor($index / 3);
        $left = 3 + (66) * $col; // 63.5 + 2.5 = 66
        $top = 8 + (46.6) * $row;
        ?>
        <div class="card-container" style="left: <?= $left ?>mm; top: <?= $top ?>mm;">
            <div class="title-box">
                <div class="title"><?= htmlspecialchars($item['title']) ?></div>
                <div class="subtitle"><?= htmlspecialchars($item['material'] ?: '-') ?>
                    · <?= htmlspecialchars($item['application'] ?: '-') ?></div>
            </div>

            <table class="card-body">
                <tr>
                    <td class="body-left">
                        <div class="finish-title">
                            <?= htmlspecialchars($item['finish_name'] ?? '-') ?>
                            <?= htmlspecialchars($item['slip_rating']) ?>
                        </div>

                        <ul class="sizes-table">
                            <?php if ($item['sizes'] && is_array($item['sizes'])):
                                foreach ($item['sizes'] as $s_name): ?>
                                    <li><?= htmlspecialchars($s_name) ?></li>
                                <?php endforeach;
                            endif; ?>
                        </ul>

                        <div class="extra-des">
                            <?= htmlspecialchars($item['description']) ?>
                        </div>
                    </td>

                    <td class="body-right qr-box">
                        <img class="qr-img" src="<?= $item['qr_base64'] ?>">
                        <div class="qr-des">scan for price and more info</div>
                    </td>
                </tr>
            </table>
        </div>
    <?php endforeach; ?>
</body>

</html>