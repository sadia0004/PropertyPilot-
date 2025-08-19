<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check if landlord is logged in
if (!isset($_SESSION['landlord_id'])) {
    // In a real app, you'd probably redirect:
    // header("Location: login.php");
    // exit();
    die("Unauthorized access. Please log in as a landlord.");
}
$landlord_id = $_SESSION['landlord_id'];

// Retrieve user data for UI (Using session data from your dashboard example)
$_SESSION['userRole'] = 'landlord'; // Assuming this for the sidebar logic
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// =================================================================
// ✅ COPIED BRAND COLOR PALETTE FROM DASHBOARD FOR CONSISTENCY
// =================================================================
$primaryDark = '#021934';
$primaryAccent = '#2c5dbd';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';
$cardBackground = '#ffffff';
$actionAdd = '#28a745';
$actionBilling = '#ffc107';
$actionViewRentList = '#17a2b8';
$actionViewTenantList = '#6f42c1';
$actionApartmentList = '#6c757d';
$actionScheduleCreate = '#e83e8c';
$actionScheduleDetails = '#fd7e14';
$actionMaintenance = '#dc3545';
// =================================================================

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
    foreach ($formData as $key => &$value) {
        if ($key !== 'apartment_status') {
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
            $formData['apartment_status'],
            $formData['floor_no'],
            $formData['apartment_type'],
            $formData['apartment_size']
        );

        if ($stmt->execute()) {
            $successMsg = "✅ Property saved successfully.";
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

        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: <?php echo $secondaryBackground; ?>; color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }

        /* Main Top Navigation Bar */
        .main-top-navbar { background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px; }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; object-fit: contain; background: <?php echo $cardBackground; ?>; padding: 3px; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn { background-color: <?php echo $actionMaintenance; ?>; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .top-right-user-info .logout-btn:hover { background-color: #c0392b; }
        
        /* Main Content Wrapper */
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        
        /* Vertical Sidebar */
        .vertical-sidebar { display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>; padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2); z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden; /* <-- SCROLLBAR REMOVED */ }
        .vertical-sidebar .nav-links a { color: <?php echo $textColor; ?>; text-decoration: none; width:100% ; text-align: left; padding: 8px 11px; margin: 6px 0; font-weight: 600; font-size: 16px; border-radius: 8px; transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        
        /* Sidebar Action Buttons */
        .vertical-sidebar .action-buttons { margin-top: 20px; width: 100%; display: flex; flex-direction: column; gap: 7px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1);  }
        .vertical-sidebar .action-buttons h3 { color: <?php echo $textColor; ?>; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link { width: calc(100% - 30px); padding: 8px 12px; border-radius: 8px; color: <?php echo $textColor; ?>; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center; justify-content: flex-start; gap: 10px; text-decoration: none; transition: background-color 0.3s ease, transform 0.2s ease; }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); background-color: rgba(255, 255, 255, 0.1); }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }

        /* Styles specific to this form page */
        .page-main-content { flex-grow: 1; padding: 40px; display: flex; justify-content: center; align-items: flex-start; height: 100%; overflow-y: hidden; /* <-- SCROLLBAR REMOVED */ }
        .form-container { max-width: 650px; background: #ffffff; padding: 30px 40px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); width: 100%; }
        .form-container h2 { text-align: center; color: #021934; margin-bottom: 30px; font-weight: 700; }
        .form-container form { display: flex; flex-wrap: wrap; gap: 25px; justify-content: space-between; }
        .form-field-group { flex-basis: calc(50% - 12.5px); display: flex; flex-direction: column; }
        .form-field-group.full-width { flex-basis: 100%; }
        .form-field-group label { margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-field-group input, .form-field-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
        .form-field-group input:focus, .form-field-group select:focus { border-color: <?php echo $primaryAccent; ?>; box-shadow: 0 0 0 3px rgba(44, 93, 189, 0.2); outline: none; }
        .form-container button[type="submit"] { flex-basis: 100%; margin-top: 20px; background: <?php echo $primaryAccent; ?>; color: white; padding: 14px 20px; border: none; border-radius: 6px; cursor: pointer; transition: background 0.3s ease; font-size: 16px; font-weight: bold; }
        .form-container button[type="submit"]:hover { background: <?php echo $primaryDark; ?>; }
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
            <span class="welcome-greeting"><?php echo htmlspecialchars($fullName); ?></span>
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
                <a href="add_tenant.php" class="action-link link-tenant"><i class="fas fa-user-plus"></i> Add Tenant</a>
                <a href="tenant_List.php" class="action-link link-tenant-list"><i class="fas fa-users"></i> View Tenant List</a>
                <a href="apartmentList.php" class="action-link link-docs"><i class="fas fa-building"></i> Apartment List</a>
                <a href="RentAndBillForm.php" class="action-link link-billing"><i class="fas fa-file-invoice-dollar"></i> Rent and Bills</a>
                <a href="Rent_list.php" class="action-link link-rent"><i class="fas fa-list-ul"></i> View Rent List</a>
                <a href="Schedule_create.php" class="action-link link-schedule-create"><i class="fas fa-calendar-plus"></i> Create Schedule</a>
                <a href="scheduleInfo.php" class="action-link link-schedule-details"><i class="fas fa-calendar-alt"></i> Schedule Details</a>
            </section>
        </nav>

        <main class="page-main-content">
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
        </main>
    </div> 
</body>
</html>