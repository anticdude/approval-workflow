<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'approval_workflow');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Initialize database table if it doesn't exist
function initializeDatabase() {
    $conn = getDBConnection();
    
    // First, check if table exists and has the new columns
    $result = $conn->query("SHOW TABLES LIKE 'requests'");
    if ($result->num_rows > 0) {
        // Table exists, check if we need to alter it
        $columns_result = $conn->query("SHOW COLUMNS FROM requests");
        $existing_columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
        
        // Add missing columns
        $new_columns = [
            "app_details_mentioned" => "ALTER TABLE requests ADD COLUMN app_details_mentioned TINYINT(1) DEFAULT 0",
            "app_version_proper" => "ALTER TABLE requests ADD COLUMN app_version_proper TINYINT(1) DEFAULT 0",
            "app_code_tested" => "ALTER TABLE requests ADD COLUMN app_code_tested TINYINT(1) DEFAULT 0",
            "app_owner_notes" => "ALTER TABLE requests ADD COLUMN app_owner_notes TEXT",
            "security_guidance_followed" => "ALTER TABLE requests ADD COLUMN security_guidance_followed TINYINT(1) DEFAULT 0",
            "security_others" => "ALTER TABLE requests ADD COLUMN security_others TINYINT(1) DEFAULT 0",
            "security_notes" => "ALTER TABLE requests ADD COLUMN security_notes TEXT",
            "sre_reliable" => "ALTER TABLE requests ADD COLUMN sre_reliable TINYINT(1) DEFAULT 0",
            "sre_risk_category" => "ALTER TABLE requests ADD COLUMN sre_risk_category ENUM('1', '2', '3', '4') NULL",
            "sre_monitoring_available" => "ALTER TABLE requests ADD COLUMN sre_monitoring_available TINYINT(1) DEFAULT 0",
            "sre_alerting_available" => "ALTER TABLE requests ADD COLUMN sre_alerting_available TINYINT(1) DEFAULT 0",
            "sre_rollback_available" => "ALTER TABLE requests ADD COLUMN sre_rollback_available TINYINT(1) DEFAULT 0",
            "sre_notes" => "ALTER TABLE requests ADD COLUMN sre_notes TEXT"
        ];
        
        foreach ($new_columns as $column_name => $alter_sql) {
            if (!in_array($column_name, $existing_columns)) {
                if (!$conn->query($alter_sql)) {
                    // If alteration fails, try to recreate the table
                    recreateTable($conn);
                    break;
                }
            }
        }
    } else {
        // Table doesn't exist, create it with all columns
        createNewTable($conn);
    }
    
    $conn->close();
}

function createNewTable($conn) {
    $sql = "CREATE TABLE requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        jira_number VARCHAR(50) NOT NULL,
        status ENUM('NEW', 'APP_OWNER_APPROVED', 'SECURITY_APPROVED', 'SRE_APPROVED', 'READY_TO_DEPLOY') DEFAULT 'NEW',
        
        -- App Owner Checklist
        app_details_mentioned TINYINT(1) DEFAULT 0,
        app_version_proper TINYINT(1) DEFAULT 0,
        app_code_tested TINYINT(1) DEFAULT 0,
        app_owner_notes TEXT,
        app_owner_approved_at DATETIME NULL,
        
        -- Security Checklist
        security_guidance_followed TINYINT(1) DEFAULT 0,
        security_others TINYINT(1) DEFAULT 0,
        security_notes TEXT,
        security_approved_at DATETIME NULL,
        
        -- SRE Checklist
        sre_reliable TINYINT(1) DEFAULT 0,
        sre_risk_category ENUM('1', '2', '3', '4') NULL,
        sre_monitoring_available TINYINT(1) DEFAULT 0,
        sre_alerting_available TINYINT(1) DEFAULT 0,
        sre_rollback_available TINYINT(1) DEFAULT 0,
        sre_notes TEXT,
        sre_approved_at DATETIME NULL,
        
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

function recreateTable($conn) {
    // Drop existing table and create new one
    $conn->query("DROP TABLE IF EXISTS requests");
    createNewTable($conn);
}

// Initialize the database when config is included
initializeDatabase();
?>