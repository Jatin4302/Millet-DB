<?php
// ---------------------------------------------------------
// Central database connection.
// Every other PHP file includes this one so credentials
// live in exactly one place.
// ---------------------------------------------------------

$fail_hard = $fail_hard ?? true;
$db_host = "localhost";       // usually "localhost" for XAMPP and most lab servers
$db_user = "root";            // change to your MySQL username
$db_pass = "SQL@9211";                // change to your MySQL password
$db_name = "ssr_transcriptomics_db";

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    if (!$fail_hard) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'unhealthy', 'php' => 'ok', 'database' => 'unavailable']);
        exit;
    }
    http_response_code(503);
    die("Database connection failed. Check the database credentials in db_connect.php.");
}

$conn->set_charset("utf8mb4");

// Keep the appearance controls self-installing for local XAMPP use.
$conn->query("CREATE TABLE IF NOT EXISTS site_images (
    slot VARCHAR(60) PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$conn->query("ALTER TABLE site_settings MODIFY setting_value TEXT NOT NULL");
$conn->query("INSERT IGNORE INTO site_settings (setting_key, setting_value) VALUES
    ('palette', 'custom'), ('palette_ink', '#173b32'), ('palette_deep', '#12352e'),
    ('palette_muted', '#688078'), ('palette_line', '#d7e2dc'), ('palette_cream', '#f5f5ec'),
    ('palette_lime', '#d8e95a'), ('palette_orange', '#f18b4e'),
    ('font_family', 'manrope'), ('font_scale', 'normal')");
?>
