<?php
/**
 * Import SQLite file into database
 */

$dbFile = 'database/database.sqlite';
$sqlFile = 'scha_main_sqlite.sql';

if (!file_exists($sqlFile)) {
    die("SQL file not found: $sqlFile\n");
}

echo "Opening database: $dbFile\n";
$db = new PDO("sqlite:$dbFile");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Reading SQL file: $sqlFile\n";
$content = file_get_contents($sqlFile);

// Split into statements
$statements = array_filter(array_map('trim', explode(';', $content)));

echo "Found " . count($statements) . " statements.\n";

$count = 0;
$errors = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    try {
        $db->exec($statement);
        $count++;
        if ($count % 50 == 0) {
            echo "Executed $count statements...\n";
        }
    } catch (Exception $e) {
        $errors++;
        if ($errors < 10) {
            echo "Error: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        }
    }
}

echo "\nImport completed!\n";
echo "Statements executed: $count\n";
echo "Errors: $errors\n";