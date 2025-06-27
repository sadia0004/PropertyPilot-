<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check if landlord is logged in
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access. Please log in as a landlord.");
}
$landlord_id = $_SESSION['landlord_id'];

// Initialize messages
$successMsg = "";
$errorMsg = "";
$formData = [
    'apartment_no' => '',
    'apartment_rent' => '',
    'apartment_status' => '',
    'floor_no' => '',
    'apartment_type' => '',
    'apartment_size' => ''
];

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($formData as $key => &$value) {
        $value = trim($_POST[$key] ?? '');
    }

    // ✅ Server-side validation
    if (empty($formData['apartment_no']) || empty($formData['apartment_rent']) || empty($formData['apartment_status'])) {
        $errorMsg = "❌ Please fill in all required fields.";
    } elseif (!is_numeric($formData['apartment_rent']) || $formData['apartment_rent'] <= 0) {
        $errorMsg = "❌ Apartment rent must be a positive number.";
    } elseif (!empty($formData['apartment_size']) && (!is_numeric($formData['apartment_size']) || $formData['apartment_size'] <= 0)) {
        $errorMsg = "❌ Apartment size must be a positive number.";
    } else {
        // ✅ Proceed with DB insert
        $stmt = $conn->prepare("INSERT INTO properties (landlord_id, apartment_no, apartment_rent, apartment_status, floor_no, apartment_type, apartment_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isdsssi",
            $landlord_id,
            $formData['apartment_no'],
            $formData['apartment_rent'],
            $formData['apartment_status'],
            $formData['floor_no'],
            $formData['apartment_type'],
            $formData['apartment_size']
        );

        if ($stmt->execute()) {
            $successMsg = "✅ Property saved successfully.";
            $formData = array_map(fn($v) => '', $formData); // clear form
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
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Property Information</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef2f3;
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
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button {
            background: #007BFF;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .message {
            margin-top: 10px;
            font-weight: bold;
            color: green;
        }
        .error {
            margin-top: 10px;
            font-weight: bold;
            color: red;
        }
        h2 {
            text-align: center;
            color: #333;
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>Add Property Information</h2>

    <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
    <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

    <form method="POST" action="">
        <label>Apartment Number *</label>
        <input type="text" name="apartment_no" required value="<?php echo htmlspecialchars($formData['apartment_no']); ?>">

        <label>Apartment Rent (BDT) *</label>
        <input type="number" step="0.01" min="1" name="apartment_rent" required value="<?php echo htmlspecialchars($formData['apartment_rent']); ?>">

        <label>Status *</label>
        <select name="apartment_status" required>
            <option value="">-- Select Status --</option>
            <option value="Vacant" <?php if ($formData['apartment_status'] === 'Vacant') echo 'selected'; ?>>Vacant</option>
            <option value="Occupied" <?php if ($formData['apartment_status'] === 'Occupied') echo 'selected'; ?>>Occupied</option>
        </select>

        <label>Floor Number</label>
        <input type="number" name="floor_no" value="<?php echo htmlspecialchars($formData['floor_no']); ?>">

        <label>Apartment Type</label>
        <input type="text" name="apartment_type" placeholder="e.g., 2BHK, Studio" value="<?php echo htmlspecialchars($formData['apartment_type']); ?>">

        <label>Apartment Size (sq ft)</label>
        <input type="number" name="apartment_size" min="1" value="<?php echo htmlspecialchars($formData['apartment_size']); ?>">

        <button type="submit">Save Property</button>
    </form>
</div>

</body>
</html>
