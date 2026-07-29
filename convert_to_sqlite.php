<?php
/**
 * MySQL to SQLite Converter
 * Converts MySQL dump to SQLite compatible format
 */

$inputFile = 'scha_main.sql';
$outputFile = 'scha_main_sqlite.sql';

echo "Reading MySQL dump: $inputFile\n";
$content = file_get_contents($inputFile);

echo "Converting to SQLite format...\n";

// Remove MySQL specific syntax
$content = preg_replace('/ENGINE=InnoDB/i', '', $content);
$content = preg_replace('/CHARSET=utf8mb4/i', '', $content);
$content = preg_replace('/COLLATE=utf8mb4_unicode_ci/i', '', $content);
$content = preg_replace('/AUTO_INCREMENT/i', 'AUTOINCREMENT', $content);
$content = preg_replace('/UNSIGNED\s*/i', '', $content);
$content = preg_replace('/`([^`]*)`/', '"$1"', $content);
$content = preg_replace('/COMMENT \'[^\']*\'/i', '', $content);
$content = preg_replace('/\\\'\'/', "''", $content);
$content = preg_replace('/SET .*?;/', '', $content);
$content = preg_replace('/-- .*?$/m', '', $content);

// Remove KEY definitions (SQLite doesn't need them in CREATE)
$content = preg_replace('/,\s*KEY\s+"[^"]*"\s*\([^)]*\)/', '', $content);
$content = preg_replace('/,\s*UNIQUE\s+KEY\s+"[^"]*"\s*\([^)]*\)/', '', $content);
$content = preg_replace('/,\s*FULLTEXT\s+KEY\s+"[^"]*"\s*\([^)]*\)/', '', $content);

// Remove constraints that SQLite doesn't support
$content = preg_replace('/,\s*CONSTRAINT\s+"[^"]*"\s+FOREIGN\s+KEY\s*\([^)]*\)\s+REFERENCES\s+"[^"]*"\s*\([^)]*\)/', '', $content);

// Remove MODIFY statements
$content = preg_replace('/MODIFY\s+"[^"]*"\s+[^;]*;/', '', $content);

// Remove ALTER TABLE statements (handle separately)
$content = preg_replace('/ALTER\s+TABLE\s+"[^"]*"\s+[^;]*;/', '', $content);

// Remove DROP TABLE statements (we want to keep the data)
$content = preg_replace('/DROP\s+TABLE\s+IF\s+EXISTS\s+"[^"]*";/', '', $content);

// Remove CREATE TABLE IF NOT EXISTS statements (we'll use CREATE TABLE)
$content = preg_replace('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS/', 'CREATE TABLE', $content);

// Remove DEFAULT NULL for primary keys
$content = preg_replace('/"id"\s+int\(11\)\s+NOT\s+NULL\s+AUTOINCREMENT/', '"id" INTEGER PRIMARY KEY AUTOINCREMENT', $content);
$content = preg_replace('/"id"\s+bigint\(20\)\s+NOT\s+NULL\s+AUTOINCREMENT/', '"id" INTEGER PRIMARY KEY AUTOINCREMENT', $content);

// Convert tinyint to integer
$content = preg_replace('/tinyint\(1\)/', 'INTEGER', $content);
$content = preg_replace('/tinyint\(4\)/', 'INTEGER', $content);

// Convert datetime to TEXT
$content = preg_replace('/datetime\s*(\([^)]*\))?/', 'TEXT', $content);
$content = preg_replace('/timestamp\s*(\([^)]*\))?/', 'TEXT', $content);

file_put_contents($outputFile, $content);

echo "Conversion completed! Output file: $outputFile\n";
echo "Now import with: sqlite3 database/database.sqlite < $outputFile\n";