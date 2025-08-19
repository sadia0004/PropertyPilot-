<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Protect the page: allow only logged-in landlords
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'landlord') {
    header("Location: login.php");
    exit();
}
$landlord_id = $_SESSION['user_id'];

// --- Define Color Palette (from Dashboard) ---
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
$actionScheduleCreate = '#832d31ff';
$actionScheduleDetails = '#fd7e14';
$actionMaintenance = '#dc3545';


// --- Database Connection ---
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = '';

// --- Handle Form Submission (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['fullName']);
    $phoneNumber = trim($_POST['phoneNumber']);
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Basic Validation
    if (empty($fullName) || empty($phoneNumber)) {
        $message = "Full Name and Phone Number cannot be empty.";
        $message_type = 'error';
    } else {
        $update_fields = [];
        $params = [];
        $types = "";

        // Prepare fields for update
        $update_fields[] = "fullName = ?";
        $params[] = $fullName;
        $types .= "s";

        $update_fields[] = "phoneNumber = ?";
        $params[] = $phoneNumber;
        $types .= "s";

        // Handle Password Change
        if (!empty($newPassword)) {
            if ($newPassword === $confirmPassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $update_fields[] = "password = ?";
                $params[] = $hashedPassword;
                $types .= "s";
            } else {
                $message = "Passwords do not match.";
                $message_type = 'error';
            }
        }

        // Handle Profile Photo Upload
        if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] == 0) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['profilePhoto']['name']);
            $targetFilePath = $uploadDir . $fileName;
            
            $allowTypes = array('jpg','png','jpeg','gif','webp');
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

            if (in_array($fileType, $allowTypes)) {
                if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $targetFilePath)) {
                    $update_fields[] = "profilePhoto = ?";
                    $params[] = $targetFilePath;
                    $types .= "s";
                } else {
                    $message = "Sorry, there was an error uploading your file.";
                    $message_type = 'error';
                }
            } else {
                $message = 'Sorry, only JPG, JPEG, PNG, GIF, & WEBP files are allowed.';
                $message_type = 'error';
            }
        }

        // If no errors, proceed with database update
        if (empty($message)) {
            $params[] = $landlord_id;
            $types .= "i";
            
            $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $message = "Profile updated successfully!";
                $message_type = 'success';
                // Update session variables to reflect changes immediately
                $_SESSION['fullName'] = $fullName;
                if (isset($targetFilePath)) {
                    $_SESSION['profilePhoto'] = $targetFilePath;
                }
            } else {
                $message = "Error updating profile: " . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    }
}

// Fetch the latest user data for display
$stmt_fetch = $conn->prepare("SELECT fullName, email, phoneNumber, profilePhoto, nationalId FROM users WHERE id = ?");
$stmt_fetch->bind_param("i", $landlord_id);
$stmt_fetch->execute();
$result = $stmt_fetch->get_result();
$user = $result->fetch_assoc();
$stmt_fetch->close();
$conn->close();

$profilePhoto = (!empty($user['profilePhoto']) && file_exists($user['profilePhoto'])) ? $user['profilePhoto'] : 'image/default-avatar.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Profile - PropertyPilot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Base styles from Dashboard --- */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: <?php echo $secondaryBackground; ?>; color: #222;
            display: flex; flex-direction: column; height: 100vh; overflow: hidden;
        }
        .main-top-navbar {
            background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); z-index: 1001; flex-shrink: 0;
            position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; object-fit: contain; background: <?php echo $cardBackground; ?>; padding: 3px; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn { background-color: <?php echo $actionMaintenance; ?>; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
            display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
            padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
            color: <?php echo $textColor; ?>; text-decoration: none; width:100%; text-align: left; padding: 9px 12px;
            margin: 6px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
            transition: background-color 0.3s ease; display: flex; align-items: center; ;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        .vertical-sidebar .action-buttons { width: 100%; display: flex; flex-direction: column; gap: 7px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1);   }
        .vertical-sidebar .action-buttons h3 { color: <?php echo $textColor; ?>; font-size: 1.1em; margin-bottom: 5px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
            width: calc(100% - 30px); padding: 9px 15px; border-radius: 8px; color: <?php echo $textColor; ?>;
            font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center;
            justify-content: flex-start; gap: 10px; text-decoration: none; transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); background-color: rgba(255, 255, 255, 0.1); }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }

        /* --- Styles specific to Profile page --- */
        .profile-container {
            max-width: 800px; margin: 0 auto; background: #fff; padding: 40px;
            border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .profile-header { text-align: center; margin-bottom: 30px; }
        .profile-header .avatar {
            width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
            border: 4px solid <?php echo $primaryAccent; ?>; margin-bottom: 15px;
        }
        .profile-header h2 { color: <?php echo $primaryDark; ?>; margin: 0; font-size: 2rem; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input {
            width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px;
            font-size: 1rem; transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input:focus { border-color: <?php echo $primaryAccent; ?>; box-shadow: 0 0 0 3px rgba(44, 93, 189, 0.2); outline: none; }
        .form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .form-actions { text-align: right; margin-top: 30px; }
        .btn-save {
            background-color: <?php echo $primaryAccent; ?>; color: white; padding: 12px 25px; border: none;
            border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .btn-save:hover { background-color: <?php echo $primaryDark; ?>; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand">
            <img src="image/logo.png" alt="PropertyPilot Logo" /> PropertyPilot
        </div>
        <div class="top-right-user-info">
            <span class="welcome-greeting"><?php echo htmlspecialchars($_SESSION['fullName']); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($_SESSION['profilePhoto'] ?: 'image/default-avatar.png'); ?>" alt="Profile Photo">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <a href="propertyInfo.php"><i class="fas fa-building"></i> Add Property</a>
                <a href="maintanance.php"><i class="fas fa-tools"></i> Maintanance</a>
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

        <main>
            <div class="profile-container">
                <div class="profile-header">
                    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Current Profile Photo" class="avatar">
                    <h2>My Profile</h2>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST" action="profile.php" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['fullName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phoneNumber">Phone Number</label>
                            <input type="text" id="phoneNumber" name="phoneNumber" value="<?php echo htmlspecialchars($user['phoneNumber']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nationalId">National ID</label>
                            <input type="text" id="nationalId" value="<?php echo htmlspecialchars($user['nationalId']); ?>" readonly>
                        </div>
                    </div>
                    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="profilePhoto">Change Profile Photo</label>
                            <input type="file" id="profilePhoto" name="profilePhoto">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="newPassword">New Password (leave blank to keep current)</label>
                            <input type="password" id="newPassword" name="newPassword">
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm New Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
