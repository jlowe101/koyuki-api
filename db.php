<?php
// Retrieve the Heroku Postgres Database URL automatically from the environment
$database_url = getenv('DATABASE_URL');

if ($database_url) {
    // Parse the URL into components
    $db_url = parse_url($database_url);
    
    // Check if parsing was successful
    if ($db_url && isset($db_url["host"], $db_url["port"], $db_url["user"], $db_url["pass"], $db_url["path"])) {
        $host = $db_url["host"];
        $port = $db_url["port"];
        $user = $db_url["user"];
        $pass = $db_url["pass"];
        $dbname = ltrim($db_url["path"], "/");
        
        // Construct the PostgreSQL DSN (Data Source Name)
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        
        try {
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 1. Table to store Live Cookies
            $pdo->exec("CREATE TABLE IF NOT EXISTS active_accounts (
                id SERIAL PRIMARY KEY,
                cookie_data TEXT UNIQUE NOT NULL,
                date_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // 2. Table for admin login
            $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
                id SERIAL PRIMARY KEY,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL
            )");
            
            // 3. Table for generator users (Status for Admin Approval)
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                forum VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            // Auto-patch existing tables that were created before the status column was added
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN status VARCHAR(50) DEFAULT 'pending'");
            } catch (PDOException $e) {
                // Safely ignore if the column already exists
            }

            // 4. Table for System Settings (e.g., locking registrations)
            $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
                setting_key VARCHAR(255) PRIMARY KEY,
                setting_value VARCHAR(255) NOT NULL
            )");
            
            // Initialize default settings (Registrations open by default)
            $pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES ('registration_locked', 'false') ON CONFLICT (setting_key) DO NOTHING");
            
        } catch (PDOException $e) {
            error_log("Database Connection failed: " . $e->getMessage());
        }
    } else {
        error_log("Database URL format is invalid.");
    }
} else {
    // Fallback error if no URL is provided
    error_log("DATABASE_URL not found. Please ensure the Heroku Postgres add-on is attached.");
}
?>
