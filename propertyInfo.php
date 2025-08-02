<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check if landlord is logged in
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access. Please log in as a landlord.");
}
$landlord_id = $_SESSION['landlord_id'];

// Retrieve user data for UI
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// Initialize messages and form data
$successMsg = "";
$errorMsg = "";
$formData = [
    'apartment_no' => '',
    'apartment_rent' => '',
    'apartment_status' => 'Vacant', // Default to Vacant
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
    // Populate form data from POST, keeping 'Vacant' as the status
    foreach ($formData as $key => &$value) {
        if ($key !== 'apartment_status') { // Don't overwrite status
            $value = trim($_POST[$key] ?? '');
        }
    }

    // ✅ Server-side validation
    if (empty($formData['apartment_no']) || empty($formData['apartment_rent'])) {
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
            $formData['apartment_status'], // This will be 'Vacant'
            $formData['floor_no'],
            $formData['apartment_type'],
            $formData['apartment_size']
        );

        if ($stmt->execute()) {
            $successMsg = "✅ Property saved successfully.";
            // Reset form data, keeping status as 'Vacant'
            $formData = array_fill_keys(array_keys($formData), '');
            $formData['apartment_status'] = 'Vacant';
        } else {
            if ($conn->errno == 1062) {
                $errorMsg = "❌ This apartment number already exists under your account.";
            } else {
                $errorMsg = "❌ Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Property Information</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f4ff; color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .main-top-navbar { background-color: #021934; color: #f0f4ff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); z-index: 1001; flex-shrink: 0; height: 80px; width: 100%; }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; object-fit: contain; background: #ffffff; padding: 3px; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #f0f4ff; }
        .top-right-user-info .logout-btn { background-color: #dc3545; color: #f0f4ff; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .top-right-user-info .logout-btn:hover { background-color: #c0392b; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar { display: flex; flex-direction: column; align-items: flex-start; background-color: #021934; padding: 20px 15px; color: #f0f4ff; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2); z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: auto; }
        .vertical-sidebar .nav-links a { color: #f0f4ff; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px; margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px; transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .vertical-sidebar .action-buttons { margin-top: 30px; width: 100%; display: flex; flex-direction: column; gap: 12px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 20px; }
        .vertical-sidebar .action-buttons h3 { color: #f0f4ff; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
        .vertical-sidebar .action-link { width: calc(100% - 30px); padding: 12px 15px; border-radius: 8px; color: #f0f4ff; font-weight: 600; font-size: 15px; cursor: pointer; display: flex; align-items: center; justify-content: flex-start; gap: 10px; text-decoration: none; transition: background-color 0.3s ease, transform 0.2s ease; }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); filter: brightness(1.1); }
        .page-main-content { flex-grow: 1; padding: 30px; display: flex; justify-content: center; align-items: flex-start; height: 100%; overflow-y: auto; }
        .form-container { max-width: 600px; background: #ffffff; padding: 25px 40px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); width: 100%; }
        .form-container h2 { text-align: center; color: #333; margin-bottom: 25px; }
        .form-container form { display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; }
        .form-field-group { flex-basis: calc(50% - 10px); display: flex; flex-direction: column; }
        .form-field-group.full-width { flex-basis: 100%; }
        .form-field-group label { margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-field-group input, .form-field-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; }
        .form-container button[type="submit"] { flex-basis: 100%; margin-top: 20px; background: #007BFF; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; transition: background 0.3s ease; font-size: 16px; font-weight: bold; }
        .form-container button[type="submit"]:hover { background: #0056b3; }
        .message, .error { padding: 12px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; text-align: center; }
        .message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand">
            <img src="image/logo.png" alt="PropertyPilot Logo" />
            PropertyPilot
        </div>
        <div class="top-right-user-info">
            <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php">Dashboard</a>
                <a href="profile.php">Profile</a>
                <a href="propertyInfo.php" class="active">Add Property Info</a>
                <a href="notifications.html">Notifications</a>
            </div>
            <section class="action-buttons">
                <h3>Quick Actions</h3>
                 <a href="add_tenant.php" class="action-link" style="background-color: #28a745;"><i class="fas fa-user-plus"></i> Add Tenant</a>
                 <a href="RentAndBillForm.php" class="action-link" style="background-color: #ffc107; color: #212529;"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
                 <a href="apartmentList.php" class="action-link" style="background-color: #6c757d;"><i class="fas fa-list-ol"></i> Apartment List</a>
                 <a href="maintenance.html" class="action-link" style="background-color: #dc3545;"><i class="fas fa-tools"></i> Maintenance</a>
            </section>
        </nav>

        <div class="page-main-content">
            <div class="form-container">
                <h2>Add New Property Information</h2>

                <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
                <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

                <form method="POST" action="">
                    <div class="form-field-group">
                        <label for="apartment_no">Apartment Number *</label>
                        <input type="text" id="apartment_no" name="apartment_no" required value="<?php echo htmlspecialchars($formData['apartment_no']); ?>">
                    </div>

                    <div class="form-field-group">
                        <label for="apartment_rent">Apartment Rent (BDT) *</label>
                        <input type="number" step="0.01" min="1" id="apartment_rent" name="apartment_rent" required value="<?php echo htmlspecialchars($formData['apartment_rent']); ?>">
                    </div>

                    <div class="form-field-group">
                        <label for="apartment_status">Status</label>
                        <input type="text" id="apartment_status" name="apartment_status" value="Vacant" readonly 
                               style="background-color: #e9ecef; cursor: not-allowed; color: #495057; font-weight: bold;">
                    </div>

                    <div class="form-field-group">
                        <label for="floor_no">Floor Number</label>
                        <input type="number" id="floor_no" name="floor_no" value="<?php echo htmlspecialchars($formData['floor_no']); ?>">
                    </div>

                    <div class="form-field-group full-width">
                        <label for="apartment_type">Apartment Type</label>
                        <input type="text" id="apartment_type" name="apartment_type" placeholder="e.g., 2BHK, Studio" value="<?php echo htmlspecialchars($formData['apartment_type']); ?>">
                    </div>

                    <div class="form-field-group">
                        <label for="apartment_size">Apartment Size (sq ft)</label>
                        <input type="number" id="apartment_size" name="apartment_size" min="1" value="<?php echo htmlspecialchars($formData['apartment_size']); ?>">
                    </div>
                    
                    <button type="submit">Save Property</button>
                </form>
            </div>
        </div>
    </div> 
</body>
</html>