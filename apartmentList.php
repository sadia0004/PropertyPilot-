<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Check landlord login
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access. Please log in as a landlord.");
}
$landlord_id = $_SESSION['landlord_id'];

// ✅ Handle Delete
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $conn->query("DELETE FROM properties WHERE property_id = $delete_id AND landlord_id = $landlord_id");
    header("Location: apartmentList.php");
    exit();
}

// ✅ Fetch Apartment Data
$sql = "SELECT * FROM properties WHERE landlord_id = $landlord_id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Apartment Info List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f9fc;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            background: white;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: center;
        }
        th {
            background-color: #007BFF;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        a.btn {
            padding: 5px 12px;
            text-decoration: none;
            border-radius: 4px;
            margin: 0 4px;
        }
        .edit-btn {
            background-color: #28a745;
            color: white;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        h2 {
            text-align: center;
        }
    </style>
</head>
<body>

<h2>Your Apartment Info List</h2>

<table>
    <tr>
        <th>Apartment No</th>
        <th>Rent (BDT)</th>
        <th>Status</th>
        <th>Floor</th>
        <th>Type</th>
        <th>Size (sq ft)</th>
        <th>Actions</th>
    </tr>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['apartment_no']; ?></td>
                <td><?php echo $row['apartment_rent']; ?></td>
                <td><?php echo $row['apartment_status']; ?></td>
                <td><?php echo $row['floor_no']; ?></td>
                <td><?php echo $row['apartment_type']; ?></td>
                <td><?php echo $row['apartment_size']; ?></td>
                <td>
                    <a href="editApartment.php?id=<?php echo $row['property_id']; ?>" class="btn edit-btn">Edit</a>
                    <a href="apartmentList.php?delete_id=<?php echo $row['property_id']; ?>" class="btn delete-btn" onclick="return confirm('Are you sure you want to delete this apartment?');">Delete</a>
                </td>
            </tr>
        <?php } ?>
    <?php else: ?>
        <tr><td colspan="7">No apartments found.</td></tr>
    <?php endif; ?>
</table>

</body>
</html>
