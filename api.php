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

$action = $_GET['action'] ?? '';

if ($action === 'save_meta') {
    $post_id = (int) $_POST['post_id'];
    $slip_rating = $_POST['slip_rating'] ?? '';
    $qrcode_description = $_POST['qrcode_description'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO tiles_meta (post_id, slip_rating, qrcode_description) VALUES (?, ?, ?) 
                           ON CONFLICT(post_id) DO UPDATE SET slip_rating = excluded.slip_rating, qrcode_description = excluded.qrcode_description");
    $stmt->execute([$post_id, $slip_rating, $qrcode_description]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_price') {
    $post_id = (int) $_POST['post_id'];
    $finish_name = $_POST['finish_name'] ?? '';
    $size_name = $_POST['size_name'] ?? '';
    $price = $_POST['price'] ?? '';

    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO tile_prices (post_id, finish_name, tile_size_name, price) VALUES (?, ?, ?, ?)
                           ON CONFLICT(post_id, finish_name, tile_size_name) DO UPDATE SET price = excluded.price");
    $stmt->execute([$post_id, $finish_name, $size_name, $price]);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'qrcode') {
    $post_id = (int) $_GET['post_id'];
    $url = get_permalink($post_id);

    // Add finish query param if needed to the URL, but requirement says "指向对应文章URL的二维码", so base URL is fine.

    $options = new QROptions([
        'version' => 5,
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
    $post_id = (int) $_GET['post_id'];
    $finish_name = $_GET['finish'] ?? '';

    $tile = get_post($post_id);
    if (!$tile || $tile->post_type !== 'tile') {
        die('Invalid tile');
    }

    $material = function_exists('get_field') ? get_field('tile_material', $post_id) : '';
    $application = function_exists('get_field') ? get_field('tile_application', $post_id) : '';
    $finishes = function_exists('get_field') ? get_field('tile_finish', $post_id) : [];

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tiles_meta WHERE post_id = ?");
    $stmt->execute([$post_id]);
    $meta = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['slip_rating' => '', 'qrcode_description' => ''];

    $stmt = $pdo->prepare("SELECT tile_size_name, price FROM tile_prices WHERE post_id = ? AND finish_name = ?");
    $stmt->execute([$post_id, $finish_name]);
    $prices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $prices = [];
    foreach ($prices_raw as $row) {
        $prices[$row['tile_size_name']] = $row['price'];
    }

    $selected_finish = null;
    if ($finishes && is_array($finishes)) {
        foreach ($finishes as $f) {
            if (($f['finish_name'] ?? '') === $finish_name) {
                $selected_finish = $f;
                break;
            }
        }
    }

    if (!$selected_finish) {
        die('Finish not found');
    }

    // Generate QR Code base64
    $url = get_permalink($post_id);
    $qr_options = new QROptions([
        'version' => 5,
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

    $filename = 'tile_card_' . $post_id . '_' . sanitize_title($finish_name) . '.pdf';
    $dompdf->stream($filename, array("Attachment" => false)); // Inline view
    exit;
}
