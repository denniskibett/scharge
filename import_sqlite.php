<?php
// Import MySQL dump into SQLite
$sqlFile = 'scha_main.sql';
$dbFile = 'database/database.sqlite';

if (!file_exists($sqlFile)) {
    die("SQL file not found: $sqlFile\n");
}

if (!file_exists($dbFile)) {
    die("Database file not found: $dbFile\n");
}

echo "Opening database...\n";
$db = new PDO("sqlite:$dbFile");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Reading SQL file...\n";
$content = file_get_contents($sqlFile);

echo "Converting MySQL to SQLite...\n";

// Remove MySQL specific syntax
$content = preg_replace('/ENGINE=InnoDB\s*/', '', $content);
$content = preg_replace('/CHARSET=utf8mb4\s*/', '', $content);
$content = preg_replace('/COLLATE=utf8mb4_unicode_ci\s*/', '', $content);
$content = preg_replace('/AUTO_INCREMENT/', 'AUTOINCREMENT', $content);
$content = preg_replace('/UNSIGNED\s*/', '', $content);
$content = preg_replace('/`/', '"', $content);
$content = preg_replace('/COMMENT \'.*?\'/', '', $content);
$content = preg_replace('/\\\'\'/', "''", $content);
$content = preg_replace('/SET .*?;/', '', $content);
$content = preg_replace('/-- .*?$/m', '', $content);

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $content)));

echo "Found " . count($statements) . " statements to execute.\n";

$count = 0;
foreach ($statements as $statement) {
    try {
        $db->exec($statement);
        $count++;
        if ($count % 10 == 0) {
            echo "Executed $count statements...\n";
        }
    } catch (Exception $e) {
        echo "Error executing statement: " . substr($statement, 0, 100) . "\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Import completed! $count statements executed.\n";
echo "Done!\n";