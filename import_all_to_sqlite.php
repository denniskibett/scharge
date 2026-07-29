<?php
/**
 * Import all exported MySQL tables into SQLite
 */

$dbFile = 'database/database.sqlite';
$tables = [
    'users',
    'tenants',
    'companies',
    'estates',
    'sms_campaigns'
];

echo "Opening SQLite database: $dbFile\n";
$db = new PDO("sqlite:$dbFile");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

foreach ($tables as $table) {
    $sqlFile = $table . '_export.sql';
    
    if (!file_exists($sqlFile)) {
        echo "Warning: $sqlFile not found. Skipping...\n";
        continue;
    }
    
    echo "\nImporting $table from $sqlFile...\n";
    $content = file_get_contents($sqlFile);
    
    // Extract INSERT statements
    preg_match_all('/INSERT INTO `' . $table . '` VALUES \(([^;]*)\);/', $content, $matches);
    
    if (empty($matches[0])) {
        echo "No INSERT statements found for $table.\n";
        continue;
    }
    
    $count = 0;
    foreach ($matches[0] as $insert) {
        try {
            // Clean the SQL
            $sqliteInsert = str_replace('`', '"', $insert);
            $sqliteInsert = preg_replace('/\\\\\'\'/', "''", $sqliteInsert);
            
            // Handle the insert
            $db->exec($sqliteInsert);
            $count++;
        } catch (Exception $e) {
            // Try individual row insert
            preg_match('/VALUES \((.*)\)/i', $insert, $valuesMatch);
            if (!empty($valuesMatch[1])) {
                $rows = explode('),(', $valuesMatch[1]);
                foreach ($rows as $row) {
                    try {
                        $row = trim($row, '()');
                        $db->exec("INSERT INTO \"$table\" VALUES ($row)");
                        $count++;
                    } catch (Exception $e2) {
                        // Skip errors
                    }
                }
            }
        }
    }
    
    echo "Imported $count records into $table\n";
}

echo "\nImport completed!\n";