<?php
// db.php
$db_file = __DIR__ . '/app_data.sqlite';
$pdo = new PDO('sqlite:' . $db_file);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if they don't exist
$pdo->exec("
CREATE TABLE IF NOT EXISTS tiles_meta (
    post_id INTEGER PRIMARY KEY,
    slip_rating TEXT,
    qrcode_description TEXT
);
CREATE TABLE IF NOT EXISTS tile_prices (
    post_id INTEGER,
    finish_name TEXT,
    tile_size_name TEXT,
    price TEXT,
    PRIMARY KEY (post_id, finish_name, tile_size_name)
);
CREATE TABLE IF NOT EXISTS tile_finishes_meta (
    post_id INTEGER,
    finish_name TEXT,
    slip_rating TEXT,
    PRIMARY KEY (post_id, finish_name)
);
");
