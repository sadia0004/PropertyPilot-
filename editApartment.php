<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access.");
}
$landlord_id = $_SESSION['landlord_id'];

$host = "localhost";
$username = "root";
$password = "";
$database = "property";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$successMsg = "";
$errorMsg = "";

// Get apartment data
if (!isset($_GET['id'])) {
    die("Apartment ID not provided.");
}

$property_id = intval($_GET['id']);

// Fetch existing data
$stmt = $conn->prepare("SELECT * FROM properties WHERE property_id = ? AND landlord_id = ?");
$stmt->bind_param("ii", $property_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("No apartment found or unauthorized access.");
}
$apartment = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apartment_no = $_POST['apartment_no'];
    $apartment_rent = $_POST['apartment_rent'];
    $apartment_status = $_POST['apartment_status'];
    $floor_no = $_POST['floor_no'];
    $apartment_type = $_POST['apartment_type'];
    $apartment_size = $_POST['apartment_size'];

    $stmt = $conn->prepare("UPDATE properties SET apartment_no = ?, apartment_rent = ?, apartment_status = ?, floor_no = ?, apartment_type = ?, apartment_size = ? WHERE property_id = ? AND landlord_id = ?");
    $stmt->bind_param("sdsssiii", $apartment_no, $apartment_rent, $apartment_status, $floor_no, $apartment_type, $apartment_size, $property_id, $landlord_id);

    if ($stmt->execute()) {
        $successMsg = "✅ Apartment updated successfully.";
        // Refresh apartment data
        $apartment = $_POST;
    } else {
        if ($conn->errno == 1062) {
            $errorMsg = "❌ This apartment number already exists under your account.";
        } else {
            $errorMsg = "❌ Error: " . $stmt->error;
        }
    }

    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Apartment</title>
    <style>
        /* Reuse form style from add page */
        body {
            font-family: Arial;
            background: #f4f6f7;
            padding: 30px;
        }
        .form-container {
            max-width: 600px;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            margin: auto;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #218838;
        }
        .message { font-weight: bold; color: green; }
        .error { font-weight: bold; color: red; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Edit Apartment Information</h2>

    <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
    <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

    <form method="POST">
        <label>Apartment Number</label>
        <input type="text" name="apartment_no" required value="<?php echo htmlspecialchars($apartment['apartment_no']); ?>">

        <label>Apartment Rent (BDT)</label>
        <input type="number" step="0.01" name="apartment_rent" required value="<?php echo htmlspecialchars($apartment['apartment_rent']); ?>">

        <label>Status</label>
        <select name="apartment_status" required>
            <option value="Vacant" <?php if ($apartment['apartment_status'] === 'Vacant') echo 'selected'; ?>>Vacant</option>
            <option value="Occupied" <?php if ($apartment['apartment_status'] === 'Occupied') echo 'selected'; ?>>Occupied</option>
        </select>

        <label>Floor Number</label>
        <input type="number" name="floor_no" value="<?php echo htmlspecialchars($apartment['floor_no']); ?>">

        <label>Apartment Type</label>
        <input type="text" name="apartment_type" value="<?php echo htmlspecialchars($apartment['apartment_type']); ?>">

        <label>Apartment Size (sq ft)</label>
        <input type="number" name="apartment_size" value="<?php echo htmlspecialchars($apartment['apartment_size']); ?>">

        <button type="submit">Update Apartment</button>
    </form>
</div>

</body>
</html>
