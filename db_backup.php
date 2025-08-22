<?php
session_start();


if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: login.php");
    exit("Access Denied.");
}


$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$backup_file_name = $database . '_backup_' . date("Y-m-d-H-i-s") . '.sql';


header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($backup_file_name) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');


$mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe'; 

$command = sprintf(
    '"%s" --user=%s --password=%s --host=%s %s',
    $mysqldump_path,
    escapeshellarg($username),
    escapeshellarg($password),
    escapeshellarg($host),
    escapeshellarg($database)
);


$output = null;
$return_var = null;
exec($command, $output, $return_var);

if ($return_var === 0) {
    echo implode("\n", $output);
} else {
    
    echo "-- \n";
    echo "-- Database backup failed. \n";
    echo "-- Please check the path to mysqldump.exe in db_backup.php and ensure it is correct for your server setup. \n";
    echo "-- Return Code: " . $return_var . "\n";
}

exit();
?>
