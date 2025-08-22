<?php
session_start();

// Protect the page: allow only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: login.php");
    exit("Access Denied.");
}

// --- Database Configuration ---
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$backup_file_name = $database . '_backup_' . date("Y-m-d-H-i-s") . '.sql';

// --- Set Headers for File Download ---
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($backup_file_name) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');

// --- Create the Database Backup using mysqldump ---
// This command needs the full path to mysqldump.exe if it's not in your system's PATH.
// For a standard XAMPP installation on Windows, the path is usually 'C:\xampp\mysql\bin\mysqldump.exe'.
// Adjust the path below if your XAMPP is installed elsewhere.
$mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe'; // Common path for XAMPP on Windows

$command = sprintf(
    '"%s" --user=%s --password=%s --host=%s %s',
    $mysqldump_path,
    escapeshellarg($username),
    escapeshellarg($password),
    escapeshellarg($host),
    escapeshellarg($database)
);

// Execute the command and pass the output directly to the browser
$output = null;
$return_var = null;
exec($command, $output, $return_var);

if ($return_var === 0) {
    echo implode("\n", $output);
} else {
    // If mysqldump fails, provide an error message.
    // This could be due to incorrect path or permissions.
    echo "-- \n";
    echo "-- Database backup failed. \n";
    echo "-- Please check the path to mysqldump.exe in db_backup.php and ensure it is correct for your server setup. \n";
    echo "-- Return Code: " . $return_var . "\n";
}

exit();
?>
