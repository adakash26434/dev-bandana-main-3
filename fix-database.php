<?php
/**
 * Emergency Database Fix Script
 * Run this to fix missing columns causing 500 errors
 */
require_once 'includes/config.php';

echo "<h1>Database Fix Script</h1>";
echo "<p>Fixing missing database columns...</p>";

try {
    $db = getDB();
    
    // List of tables and columns to add
    $fixes = [
        'admin_users' => [
            'full_name_np' => 'VARCHAR(255) DEFAULT NULL',
            'published' => 'TINYINT(1) DEFAULT 1',
            'risk_review_status' => "ENUM('pending','approved','rejected') DEFAULT 'pending'"
        ],
        'members' => [
            'full_name_np' => 'VARCHAR(255) DEFAULT NULL',
            'published' => 'TINYINT(1) DEFAULT 1',
            'risk_review_status' => "ENUM('pending','approved','rejected') DEFAULT 'approved'"
        ],
        'loan_applications' => [
            'full_name' => 'VARCHAR(255) DEFAULT NULL',
            'full_name_np' => 'VARCHAR(255) DEFAULT NULL',
            'published' => 'TINYINT(1) DEFAULT 1',
            'risk_review_status' => "ENUM('pending','approved','rejected') DEFAULT 'pending'"
        ],
        'kyc_applications' => [
            'full_name' => 'VARCHAR(255) DEFAULT NULL',
            'full_name_np' => 'VARCHAR(255) DEFAULT NULL',
            'published' => 'TINYINT(1) DEFAULT 1',
            'risk_review_status' => "ENUM('pending','approved','rejected') DEFAULT 'pending'"
        ],
        'news' => [
            'published' => 'TINYINT(1) DEFAULT 1'
        ],
        'notices' => [
            'published' => 'TINYINT(1) DEFAULT 1'
        ],
        'committees' => [
            'published' => 'TINYINT(1) DEFAULT 1'
        ],
        'careers' => [
            'published' => 'TINYINT(1) DEFAULT 1'
        ],
        'digital_service_requests' => [
            'full_name' => 'VARCHAR(255) DEFAULT NULL',
            'full_name_np' => 'VARCHAR(255) DEFAULT NULL',
            'published' => 'TINYINT(1) DEFAULT 1',
            'risk_review_status' => "ENUM('pending','approved','rejected') DEFAULT 'pending'"
        ]
    ];
    
    foreach ($fixes as $table => $columns) {
        echo "<h3>Fixing table: $table</h3>";
        
        // Check if table exists
        $tableCheck = $db->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() == 0) {
            echo "<p style='color: orange;'>Table $table does not exist, skipping...</p>";
            continue;
        }
        
        // Get existing columns
        $existingCols = [];
        $colsQuery = $db->query("SHOW COLUMNS FROM $table");
        while ($row = $colsQuery->fetch(PDO::FETCH_ASSOC)) {
            $existingCols[] = $row['Field'];
        }
        
        // Add missing columns
        foreach ($columns as $colName => $colDef) {
            if (!in_array($colName, $existingCols)) {
                try {
                    $sql = "ALTER TABLE $table ADD COLUMN $colName $colDef";
                    $db->exec($sql);
                    echo "<p style='color: green;'>✓ Added column $colName to $table</p>";
                } catch (Exception $e) {
                    echo "<p style='color: red;'>✗ Failed to add $colName to $table: " . $e->getMessage() . "</p>";
                }
            } else {
                echo "<p style='color: blue;'>→ Column $colName already exists in $table</p>";
            }
        }
    }
    
    // Update existing records
    echo "<h3>Updating existing records...</h3>";
    
    $updates = [
        'admin_users' => [
            'published' => 1,
            'risk_review_status' => 'approved'
        ],
        'members' => [
            'published' => 1,
            'risk_review_status' => 'approved'
        ],
        'loan_applications' => [
            'published' => 1,
            'risk_review_status' => 'pending'
        ],
        'kyc_applications' => [
            'published' => 1,
            'risk_review_status' => 'pending'
        ],
        'news' => ['published' => 1],
        'notices' => ['published' => 1],
        'committees' => ['published' => 1],
        'careers' => ['published' => 1],
        'digital_service_requests' => [
            'published' => 1,
            'risk_review_status' => 'pending'
        ]
    ];
    
    foreach ($updates as $table => $fields) {
        $setClauses = [];
        foreach ($fields as $field => $value) {
            if (is_string($value)) {
                $setClauses[] = "$field = '$value'";
            } else {
                $setClauses[] = "$field = $value";
            }
        }
        
        $setClause = implode(', ', $setClauses);
        $sql = "UPDATE $table SET $setClause WHERE $setClause[0] IS NULL";
        
        try {
            $db->exec($sql);
            echo "<p style='color: green;'>✓ Updated $table</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ Failed to update $table: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2 style='color: green;'>✅ Database fix completed!</h2>";
    echo "<p><a href='index.php'>Go to homepage</a></p>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Check your database connection and permissions.</p>";
}
?>
