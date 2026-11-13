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
    product_code TEXT,
    finish_name TEXT,
    tile_size_name TEXT,
    price TEXT,
    PRIMARY KEY (post_id, product_code, tile_size_name)
);
CREATE TABLE IF NOT EXISTS tile_finishes_meta (
    post_id INTEGER,
    product_code TEXT,
    finish_name TEXT,
    slip_rating TEXT,
    PRIMARY KEY (post_id, product_code)
);
");

/**
 * Check whether an SQLite table contains a specific column.
 */
function sqlite_table_has_column(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->query("PRAGMA table_info(" . $table . ")");
    $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($cols as $col) {
        if (($col['name'] ?? '') === $column) {
            return true;
        }
    }
    return false;
}

/**
 * Migrate legacy (post_id, finish_name) tables to (post_id, product_code).
 * Copies existing prices and slip ratings to all matching finishes by finish_name.
 */
function migrate_sqlite_to_product_code(PDO $pdo, string $db_file): void {
    $prices_need_migration = !sqlite_table_has_column($pdo, 'tile_prices', 'product_code');
    $meta_need_migration = !sqlite_table_has_column($pdo, 'tile_finishes_meta', 'product_code');

    if (!$prices_need_migration && !$meta_need_migration) {
        return;
    }

    // Create a timestamped backup before altering schema
    $backup_file = $db_file . '.bak.' . date('Ymd_His');
    if (file_exists($db_file) && !file_exists($backup_file)) {
        @copy($db_file, $backup_file);
    }

    // Collect affected post_ids and resolve finish_name -> [product_code, ...] from WordPress ACF
    $post_ids = [];
    if ($prices_need_migration) {
        $rows = $pdo->query("SELECT DISTINCT post_id FROM tile_prices")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $pid) {
            $post_ids[(int) $pid] = true;
        }
    }
    if ($meta_need_migration) {
        $rows = $pdo->query("SELECT DISTINCT post_id FROM tile_finishes_meta")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $pid) {
            $post_ids[(int) $pid] = true;
        }
    }

    $finish_codes_map = [];
    foreach (array_keys($post_ids) as $pid) {
        $finishes = function_exists('get_field') ? get_field('tile_finish', $pid) : [];
        if ($finishes && is_array($finishes)) {
            foreach ($finishes as $f) {
                $fname = $f['finish_name'] ?? '';
                $code = $f['product_code'] ?? '';
                if (trim((string) $code) !== '') {
                    if (!isset($finish_codes_map[$pid][$fname])) {
                        $finish_codes_map[$pid][$fname] = [];
                    }
                    if (!in_array($code, $finish_codes_map[$pid][$fname], true)) {
                        $finish_codes_map[$pid][$fname][] = $code;
                    }
                }
            }
        }
    }

    $pdo->beginTransaction();
    try {
        if ($prices_need_migration) {
            $old_prices = $pdo->query("SELECT post_id, finish_name, tile_size_name, price FROM tile_prices")->fetchAll(PDO::FETCH_ASSOC);
            $pdo->exec("DROP TABLE IF EXISTS tile_prices_new");
            $pdo->exec("
                CREATE TABLE tile_prices_new (
                    post_id INTEGER,
                    product_code TEXT,
                    finish_name TEXT,
                    tile_size_name TEXT,
                    price TEXT,
                    PRIMARY KEY (post_id, product_code, tile_size_name)
                )
            ");
            $insert_price = $pdo->prepare("
                INSERT OR IGNORE INTO tile_prices_new (post_id, product_code, finish_name, tile_size_name, price)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($old_prices as $row) {
                $pid = (int) $row['post_id'];
                $fname = $row['finish_name'] ?? '';
                $sname = $row['tile_size_name'] ?? '';
                $price = $row['price'] ?? '';
                $codes = $finish_codes_map[$pid][$fname] ?? [];
                if (!empty($codes)) {
                    foreach ($codes as $code) {
                        $insert_price->execute([$pid, $code, $fname, $sname, $price]);
                    }
                } else {
                    $insert_price->execute([$pid, '', $fname, $sname, $price]);
                }
            }
            $pdo->exec("DROP TABLE tile_prices");
            $pdo->exec("ALTER TABLE tile_prices_new RENAME TO tile_prices");
        }

        if ($meta_need_migration) {
            $old_meta = $pdo->query("SELECT post_id, finish_name, slip_rating FROM tile_finishes_meta")->fetchAll(PDO::FETCH_ASSOC);
            $pdo->exec("DROP TABLE IF EXISTS tile_finishes_meta_new");
            $pdo->exec("
                CREATE TABLE tile_finishes_meta_new (
                    post_id INTEGER,
                    product_code TEXT,
                    finish_name TEXT,
                    slip_rating TEXT,
                    PRIMARY KEY (post_id, product_code)
                )
            ");
            $insert_meta = $pdo->prepare("
                INSERT OR IGNORE INTO tile_finishes_meta_new (post_id, product_code, finish_name, slip_rating)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($old_meta as $row) {
                $pid = (int) $row['post_id'];
                $fname = $row['finish_name'] ?? '';
                $slip = $row['slip_rating'] ?? '';
                $codes = $finish_codes_map[$pid][$fname] ?? [];
                if (!empty($codes)) {
                    foreach ($codes as $code) {
                        $insert_meta->execute([$pid, $code, $fname, $slip]);
                    }
                } else {
                    $insert_meta->execute([$pid, '', $fname, $slip]);
                }
            }
            $pdo->exec("DROP TABLE tile_finishes_meta");
            $pdo->exec("ALTER TABLE tile_finishes_meta_new RENAME TO tile_finishes_meta");
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
