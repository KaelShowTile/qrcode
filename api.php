<?php
require_once __DIR__ . '/bootstrap.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;

if (!is_app_logged_in()) {
    http_response_code(403);
    die('Unauthorized');
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$action = $_GET['action'] ?? '';

/**
 * Find the matching finish entry in a tile's ACF tile_finish array by product_code.
 */
function find_tile_finish_by_code($finishes, string $code): ?array {
    if (!$finishes || !is_array($finishes) || trim($code) === '') {
        return null;
    }
    foreach ($finishes as $f) {
        if (($f['product_code'] ?? '') === $code) {
            return $f;
        }
    }
    return null;
}

/**
 * Build the QR code target URL using post_id and product_code.
 */
function build_tile_qr_url(int $post_id, string $code): string {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    return $base_url . "/view.php?post_id=" . $post_id . "&code=" . urlencode($code);
}

if ($action === 'save_meta') {
    $post_id = (int) $_POST['post_id'];
    $qrcode_description = $_POST['qrcode_description'] ?? '';

    global $pdo;
    // We only update qrcode_description now. (slip_rating is kept in schema but ignored here)
    $stmt = $pdo->prepare("INSERT INTO tiles_meta (post_id, qrcode_description) VALUES (?, ?) 
                           ON CONFLICT(post_id) DO UPDATE SET qrcode_description = excluded.qrcode_description");
    $stmt->execute([$post_id, $qrcode_description]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_finish_meta') {
    $post_id = (int) $_POST['post_id'];
    $product_code = $_POST['product_code'] ?? '';
    $finish_name = $_POST['finish_name'] ?? '';
    $slip_rating = $_POST['slip_rating'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO tile_finishes_meta (post_id, product_code, finish_name, slip_rating) VALUES (?, ?, ?, ?)
                           ON CONFLICT(post_id, product_code) DO UPDATE SET slip_rating = excluded.slip_rating, finish_name = excluded.finish_name");
    $stmt->execute([$post_id, $product_code, $finish_name, $slip_rating]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_price') {
    $post_id = (int) $_POST['post_id'];
    $product_code = $_POST['product_code'] ?? '';
    $finish_name = $_POST['finish_name'] ?? '';
    $size_name = $_POST['size_name'] ?? '';
    $price = $_POST['price'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO tile_prices (post_id, product_code, finish_name, tile_size_name, price) VALUES (?, ?, ?, ?, ?)
                           ON CONFLICT(post_id, product_code, tile_size_name) DO UPDATE SET price = excluded.price, finish_name = excluded.finish_name");
    $stmt->execute([$post_id, $product_code, $finish_name, $size_name, $price]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'qrcode') {
    $post_id = (int) ($_GET['post_id'] ?? 0);
    $code = $_GET['code'] ?? '';

    if (!$post_id || trim($code) === '') {
        http_response_code(400);
        die('Product Code is empty or missing.');
    }

    $tile = get_post($post_id);
    if (!$tile || ($tile->post_type !== 'tile' && $tile->post_type !== 'private-tile')) {
        die('Invalid tile');
    }

    $finishes = function_exists('get_field') ? get_field('tile_finish', $post_id) : [];
    $selected_finish = find_tile_finish_by_code($finishes, $code);
    if (!$selected_finish) {
        die('Finish not found');
    }

    $url = build_tile_qr_url($post_id, $code);

    $options = new QROptions([
        'version' => \chillerlan\QRCode\Common\Version::AUTO,
        'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::L,
        'scale' => 5,
    ]);

    $qrcode = new QRCode($options);
    $imgBase64 = $qrcode->render($url);

    // Extract base64 part
    $imgBase64 = explode(',', $imgBase64)[1];

    header('Content-Type: image/png');
    echo base64_decode($imgBase64);
    exit;
}

if ($action === 'pdf') {
    $post_id = (int) ($_GET['post_id'] ?? 0);
    $code = $_GET['code'] ?? '';
    if (!$post_id || trim($code) === '') {
        die('Product Code is empty or missing.');
    }

    $tile = get_post($post_id);
    if (!$tile || ($tile->post_type !== 'tile' && $tile->post_type !== 'private-tile')) {
        die('Invalid tile');
    }

    $material = function_exists('get_field') ? get_field('tile_material', $post_id) : '';
    $application = function_exists('get_field') ? get_field('tile_application', $post_id) : '';
    $finishes = function_exists('get_field') ? get_field('tile_finish', $post_id) : [];

    $selected_finish = find_tile_finish_by_code($finishes, $code);
    if (!$selected_finish) {
        die('Finish not found');
    }
    $finish_name = $selected_finish['finish_name'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tiles_meta WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qrcode_description' => ''];

    $slip_stmt = $pdo->prepare("SELECT slip_rating FROM tile_finishes_meta WHERE post_id = ? AND product_code = ?");
    $slip_stmt->execute([$post_id, $code]);
    $slip_row = $slip_stmt->fetch(PDO::FETCH_ASSOC);
    $meta['slip_rating'] = $slip_row ? $slip_row['slip_rating'] : '';

    $stmt = $pdo->prepare("SELECT tile_size_name, price FROM tile_prices WHERE post_id = ? AND product_code = ?");
    $stmt->execute([$post_id, $code]);
    $prices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $prices = [];
    foreach ($prices_raw as $row) {
        $prices[$row['tile_size_name']] = $row['price'];
    }

    // Generate QR Code base64
    $url = build_tile_qr_url($post_id, $code);
    $qr_options = new QROptions([
        'version' => \chillerlan\QRCode\Common\Version::AUTO,
        'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::L,
        'scale' => 3,
        'addQuietzone' => false
    ]);
    $qrcode = new QRCode($qr_options);
    $qr_image_data = $qrcode->render($url);

    // Render HTML using template
    ob_start();
    include __DIR__ . '/card_template.php';
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);

    // Set paper size: 64mm x 47mm (1 mm = 2.83465 pt)
    $width_pt = 64 * 2.83465;
    $height_pt = 47 * 2.83465;
    $dompdf->setPaper(array(0, 0, $width_pt, $height_pt), 'portrait');

    $dompdf->render();

    $filename = 'tile_card_' . $post_id . '_' . sanitize_title($finish_name . '-' . $code) . '.pdf';
    $dompdf->stream($filename, array("Attachment" => false)); // Inline view
    exit;
}

if ($action === 'print_sheet') {
    $raw_data = isset($_POST['print_data']) ? stripslashes($_POST['print_data']) : '';
    $data = json_decode($raw_data, true);

    if (!$data || !is_array($data)) {
        die("No print data received. Please try again.");
    }

    global $pdo;
    $print_items = [];
    $total_cards = 0;

    // Increase limits for intensive DOMPDF rendering
    ini_set('memory_limit', '512M');
    set_time_limit(120);

    // QR generator setup
    $qr_options = new QROptions([
        'version' => \chillerlan\QRCode\Common\Version::AUTO,
        'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
        'eccLevel' => \chillerlan\QRCode\Common\EccLevel::L,
        'scale' => 3,
        'addQuietzone' => false
    ]);

    foreach ($data as $req) {
        $post_id = (int) ($req['post_id'] ?? 0);
        $code = $req['code'] ?? '';
        if (trim($code) === '') {
            die("Product Code is empty or missing for a selected item.");
        }
        $amount = (int) ($req['amount'] ?? 1);
        if ($amount < 1)
            $amount = 1;

        $tile = get_post($post_id);
        if (!$tile || ($tile->post_type !== 'tile' && $tile->post_type !== 'private-tile'))
            continue;

        $material = function_exists('get_field') ? get_field('tile_material', $post_id) : '';
        $application = function_exists('get_field') ? get_field('tile_application', $post_id) : '';
        $finishes = function_exists('get_field') ? get_field('tile_finish', $post_id) : [];

        $selected_finish = find_tile_finish_by_code($finishes, $code);
        if (!$selected_finish) {
            continue;
        }
        $finish_name = $selected_finish['finish_name'] ?? '';

        // Find selected finish sizes
        $selected_sizes = [];
        if (isset($selected_finish['tile_size']) && is_array($selected_finish['tile_size'])) {
            foreach ($selected_finish['tile_size'] as $s) {
                $selected_sizes[] = $s['tile_size_name'] ?? '-';
            }
        }

        // Meta description
        $stmt = $pdo->prepare("SELECT * FROM tiles_meta WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['qrcode_description' => ''];
        $desc = $meta['qrcode_description'] ?? '';

        // Slip rating
        $slip_stmt = $pdo->prepare("SELECT slip_rating FROM tile_finishes_meta WHERE post_id = ? AND product_code = ?");
        $slip_stmt->execute([$post_id, $code]);
        $slip_row = $slip_stmt->fetch(PDO::FETCH_ASSOC);
        $slip = $slip_row ? $slip_row['slip_rating'] : '';

        // QR Code
        $is_no_price = isset($_GET['no_price']) && $_GET['no_price'] == '1';
        if ($is_no_price) {
            $url = get_permalink($post_id) . '?_source=showroom&utm_medium=qrcode&utm_campaign=supplier';
        } else {
            $url = build_tile_qr_url($post_id, $code);
        }
        $qrcode = new QRCode($qr_options);
        $qr_image_data = $qrcode->render($url);

        $item = [
            'title' => $tile->post_title,
            'material' => $material,
            'application' => $application,
            'finish_name' => $finish_name,
            'slip_rating' => $slip,
            'sizes' => $selected_sizes,
            'description' => $desc,
            'qr_base64' => $qr_image_data
        ];

        for ($i = 0; $i < $amount; $i++) {
            if ($total_cards >= 18)
                break 2;
            $print_items[] = $item;
            $total_cards++;
        }
    }

    if (empty($print_items)) {
        die("No items to print.");
    }

    // Render HTML using sheet template
    ob_start();
    include __DIR__ . '/sheet_template.php';
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $dompdf->stream("print_sheet.pdf", array("Attachment" => false));
    exit;
}

if ($action === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tiles_export.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['tile_id', 'title', 'slip_rate', 'finish', 'size', 'price', 'description', 'product_code']);

    $args = array('post_type' => array('tile', 'private-tile'), 'posts_per_page' => -1, 'post_status' => 'publish');
    $tiles = get_posts($args);

    global $pdo;
    $meta_stmt = $pdo->prepare("SELECT * FROM tiles_meta WHERE post_id = ?");
    $slip_by_code_stmt = $pdo->prepare("SELECT slip_rating FROM tile_finishes_meta WHERE post_id = ? AND product_code = ?");
    $slip_by_finish_stmt = $pdo->prepare("SELECT slip_rating FROM tile_finishes_meta WHERE post_id = ? AND product_code = '' AND finish_name = ?");
    $price_by_code_stmt = $pdo->prepare("SELECT price FROM tile_prices WHERE post_id = ? AND product_code = ? AND tile_size_name = ?");
    $price_by_finish_stmt = $pdo->prepare("SELECT price FROM tile_prices WHERE post_id = ? AND product_code = '' AND finish_name = ? AND tile_size_name = ?");

    foreach ($tiles as $tile) {
        $post_id = $tile->ID;
        $title = $tile->post_title;

        $meta_stmt->execute([$post_id]);
        $meta = $meta_stmt->fetch(PDO::FETCH_ASSOC) ?: ['qrcode_description' => ''];
        $description = $meta['qrcode_description'] ?? '';

        $finishes = function_exists('get_field') ? get_field('tile_finish', $post_id) : [];
        if ($finishes && is_array($finishes)) {
            foreach ($finishes as $f) {
                $finish_name = $f['finish_name'] ?? '';
                $product_code = $f['product_code'] ?? '';
                $has_code = trim((string) $product_code) !== '';
                $sizes = $f['tile_size'] ?? [];

                if ($has_code) {
                    $slip_by_code_stmt->execute([$post_id, $product_code]);
                    $slip_row = $slip_by_code_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $slip_by_finish_stmt->execute([$post_id, $finish_name]);
                    $slip_row = $slip_by_finish_stmt->fetch(PDO::FETCH_ASSOC);
                }
                $slip_rate = $slip_row ? $slip_row['slip_rating'] : '';

                if ($sizes && is_array($sizes)) {
                    foreach ($sizes as $s) {
                        $size_name = $s['tile_size_name'] ?? '';

                        if ($has_code) {
                            $price_by_code_stmt->execute([$post_id, $product_code, $size_name]);
                            $price_row = $price_by_code_stmt->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $price_by_finish_stmt->execute([$post_id, $finish_name, $size_name]);
                            $price_row = $price_by_finish_stmt->fetch(PDO::FETCH_ASSOC);
                        }
                        $price = $price_row ? $price_row['price'] : '';

                        fputcsv($output, [$post_id, $title, $slip_rate, $finish_name, $size_name, $price, $description, $product_code]);
                    }
                }
            }
        }
    }
    fclose($output);
    exit;
}

if ($action === 'import_csv') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        die("Error uploading file.");
    }

    $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $header = fgetcsv($file);
    if ($header !== ['tile_id', 'title', 'slip_rate', 'finish', 'size', 'price', 'description', 'product_code']) {
        die("Invalid CSV format.");
    }

    global $pdo;
    $wp_finishes_cache = [];
    $pdo->beginTransaction();
    try {
        $meta_stmt = $pdo->prepare("INSERT INTO tiles_meta (post_id, qrcode_description) VALUES (?, ?) 
                                    ON CONFLICT(post_id) DO UPDATE SET qrcode_description = excluded.qrcode_description");
        $finish_meta_stmt = $pdo->prepare("INSERT INTO tile_finishes_meta (post_id, product_code, finish_name, slip_rating) VALUES (?, ?, ?, ?)
                                           ON CONFLICT(post_id, product_code) DO UPDATE SET slip_rating = excluded.slip_rating, finish_name = excluded.finish_name");
        $price_stmt = $pdo->prepare("INSERT INTO tile_prices (post_id, product_code, finish_name, tile_size_name, price) VALUES (?, ?, ?, ?, ?)
                                     ON CONFLICT(post_id, product_code, tile_size_name) DO UPDATE SET price = excluded.price, finish_name = excluded.finish_name");

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 7)
                continue; // skip malformed rows

            $post_id = (int) $row[0];
            if (!$post_id)
                continue;

            $slip_rate = $row[2];
            $finish_name = $row[3];
            $size_name = $row[4];
            $price = $row[5];
            $description = $row[6];
            $product_code = $row[7] ?? '';

            // Update tiles_meta (qrcode_description only)
            $meta_stmt->execute([$post_id, $description]);

            $target_codes = [];
            if (trim((string) $product_code) !== '') {
                $target_codes[] = $product_code;
            } else {
                // Fallback if CSV row has empty product_code: match WordPress finishes by finish_name
                if (!isset($wp_finishes_cache[$post_id])) {
                    $wp_finishes_cache[$post_id] = function_exists('get_field') ? (get_field('tile_finish', $post_id) ?: []) : [];
                }
                foreach ($wp_finishes_cache[$post_id] as $f) {
                    if (($f['finish_name'] ?? '') === $finish_name && trim((string) ($f['product_code'] ?? '')) !== '') {
                        $target_codes[] = $f['product_code'];
                    }
                }
                if (empty($target_codes)) {
                    $target_codes[] = '';
                }
            }

            foreach ($target_codes as $code) {
                $finish_meta_stmt->execute([$post_id, $code, $finish_name, $slip_rate]);
                $price_stmt->execute([$post_id, $code, $finish_name, $size_name, $price]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error importing data: " . $e->getMessage());
    }

    fclose($file);
    header("Location: tiles.php"); // redirect back
    exit;
}

if ($action === 'import_by_code') {
    if (!isset($_FILES['csv_code_file']) || $_FILES['csv_code_file']['error'] !== UPLOAD_ERR_OK) {
        die("Error uploading file.");
    }

    $file = fopen($_FILES['csv_code_file']['tmp_name'], 'r');
    $header = fgetcsv($file);
    // Expected header: code, size, price, slip_rate, description
    // But we can be flexible as long as they are at index 0, 1, 2, 3, 4
    if (!$header || count($header) < 3) {
        die("Invalid CSV format. Needs at least code, size, price.");
    }

    // 1. Build memory index
    $args = array('post_type' => array('tile', 'private-tile'), 'posts_per_page' => -1, 'post_status' => 'publish');
    $tiles = get_posts($args);
    $code_map = []; // 'product_code' => ['post_id' => X, 'finish_name' => Y]

    foreach ($tiles as $tile) {
        $finishes = function_exists('get_field') ? get_field('tile_finish', $tile->ID) : [];
        if ($finishes && is_array($finishes)) {
            foreach ($finishes as $f) {
                $code = $f['product_code'] ?? '';
                $fname = $f['finish_name'] ?? '';
                if (trim((string) $code) !== '') {
                    $code_map[$code] = [
                        'post_id' => $tile->ID,
                        'finish_name' => $fname
                    ];
                }
            }
        }
    }

    global $pdo;
    $pdo->beginTransaction();
    try {
        $price_stmt = $pdo->prepare("INSERT INTO tile_prices (post_id, product_code, finish_name, tile_size_name, price) VALUES (?, ?, ?, ?, ?)
                                     ON CONFLICT(post_id, product_code, tile_size_name) DO UPDATE SET price = excluded.price, finish_name = excluded.finish_name");

        $meta_insert_stmt = $pdo->prepare("INSERT INTO tiles_meta (post_id, qrcode_description) VALUES (?, ?)
                                           ON CONFLICT(post_id) DO UPDATE SET qrcode_description = excluded.qrcode_description");

        $finish_meta_insert_stmt = $pdo->prepare("INSERT INTO tile_finishes_meta (post_id, product_code, finish_name, slip_rating) VALUES (?, ?, ?, ?)
                                                  ON CONFLICT(post_id, product_code) DO UPDATE SET slip_rating = excluded.slip_rating, finish_name = excluded.finish_name");

        $get_meta_stmt = $pdo->prepare("SELECT * FROM tiles_meta WHERE post_id = ?");

        while (($row = fgetcsv($file)) !== false) {
            if (count($row) < 3)
                continue; // skip malformed rows

            $code = $row[0];
            $size = $row[1];
            $price = $row[2];
            $slip_rate = $row[3] ?? '';
            $description = $row[4] ?? '';

            if (!isset($code_map[$code])) {
                continue; // code not found in WordPress, skip this row
            }

            $post_id = $code_map[$code]['post_id'];
            $finish_name = $code_map[$code]['finish_name'];

            // Update tile_prices
            $price_stmt->execute([$post_id, $code, $finish_name, $size, $price]);

            if (trim($slip_rate) !== '') {
                $finish_meta_insert_stmt->execute([$post_id, $code, $finish_name, $slip_rate]);
            }

            if (trim($description) !== '') {
                $get_meta_stmt->execute([$post_id]);
                $existing = $get_meta_stmt->fetch(PDO::FETCH_ASSOC);
                $new_desc = trim($description) !== '' ? $description : ($existing['qrcode_description'] ?? '');
                $meta_insert_stmt->execute([$post_id, $new_desc]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error importing data: " . $e->getMessage());
    }

    fclose($file);
    header("Location: tiles.php");
    exit;
}
