<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check for tenants
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'tenant') {
    header("Location: login.php");
    exit();
}
$tenant_id = $_SESSION['user_id'];

// Retrieve user data from session for the navbar
$fullName_session = $_SESSION['fullName'] ?? 'Tenant';
$profilePhoto_session = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// --- Define Color Palette for Tenant Dashboard ---
$primaryDark = '#1B3C53'; // UPDATED COLOR
$primaryAccent = '#2CA58D'; // A contrasting teal for highlights
$textColor = '#E0E0E0'; // Soft white for text
$secondaryBackground = '#F0F2F5'; // Light grey for the main content area
$cardBackground = '#FFFFFF';

// Initialize messages
$message = '';
$message_type = '';

// --- DB Connection ---
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Handle Form Submission (POST Request) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['email']);
    $phoneNumber = trim($_POST['phoneNumber']);
    $nationalId = trim($_POST['nationalId']);
    $profession = trim($_POST['profession']);
    $newPassword = $_POST['newPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Basic Validation
    if (empty($fullName) || empty($phoneNumber) || empty($email) || empty($nationalId)) {
        $message = "Please fill all required fields.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
            // --- Update Users Table ---
            $update_fields_users = [];
            $params_users = [];
            $types_users = "";

            $update_fields_users[] = "fullName = ?"; $params_users[] = $fullName; $types_users .= "s";
            $update_fields_users[] = "email = ?"; $params_users[] = $email; $types_users .= "s";
            $update_fields_users[] = "phoneNumber = ?"; $params_users[] = $phoneNumber; $types_users .= "s";
            $update_fields_users[] = "nationalId = ?"; $params_users[] = $nationalId; $types_users .= "s";

            if (!empty($newPassword)) {
                if ($newPassword === $confirmPassword) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update_fields_users[] = "password = ?";
                    $params_users[] = $hashedPassword;
                    $types_users .= "s";
                } else {
                    throw new Exception("Passwords do not match.");
                }
            }

            if (isset($_FILES['profilePhoto']) && $_FILES['profilePhoto']['error'] == 0) {
                $uploadDir = 'uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $fileName = time() . '_' . basename($_FILES['profilePhoto']['name']);
                $targetFilePath = $uploadDir . $fileName;
                $allowTypes = array('jpg','png','jpeg','gif','webp');
                $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

                if (in_array($fileType, $allowTypes)) {
                    if (move_uploaded_file($_FILES['profilePhoto']['tmp_name'], $targetFilePath)) {
                        $update_fields_users[] = "profilePhoto = ?";
                        $params_users[] = $targetFilePath;
                        $types_users .= "s";
                    } else {
                        throw new Exception("Sorry, there was an error uploading your file.");
                    }
                } else {
                    throw new Exception('Sorry, only JPG, JPEG, PNG, GIF, & WEBP files are allowed.');
                }
            }

            if (!empty($update_fields_users)) {
                $params_users[] = $tenant_id;
                $types_users .= "i";
                $sql_users = "UPDATE users SET " . implode(", ", $update_fields_users) . " WHERE id = ?";
                $stmt_users = $conn->prepare($sql_users);
                $stmt_users->bind_param($types_users, ...$params_users);
                $stmt_users->execute();
                $stmt_users->close();
            }

            // --- Update Tenants Table ---
            $stmt_tenants = $conn->prepare("UPDATE tenants SET profession = ? WHERE tenant_id = ?");
            $stmt_tenants->bind_param("si", $profession, $tenant_id);
            $stmt_tenants->execute();
            $stmt_tenants->close();

            $conn->commit();
            $message = "Profile updated successfully!";
            $message_type = 'success';
            
            // Update session variables
            $_SESSION['fullName'] = $fullName;
            if (isset($targetFilePath)) {
                $_SESSION['profilePhoto'] = $targetFilePath;
            }

        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'error';
        }
    }
}

// --- Fetch current user data for display ---
$stmt_fetch = $conn->prepare(
    "SELECT u.fullName, u.email, u.phoneNumber, u.profilePhoto, u.nationalId, t.profession 
     FROM users u
     LEFT JOIN tenants t ON u.id = t.tenant_id
     WHERE u.id = ?"
);
$stmt_fetch->bind_param("i", $tenant_id);
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
        *, *::before, *::after { box-sizing: border-box; }
        body {
          margin: 0; font-family: 'Segoe UI', sans-serif; background-color: <?php echo $secondaryBackground; ?>;
          color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
        }
        .main-top-navbar {
          background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px; display: flex;
          justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
          z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn {
          background-color: #dc3545; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px;
          text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
        }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
          padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%;
        }
        .vertical-sidebar .nav-links a {
          color: <?php echo $textColor; ?>; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; }
        .profile-container {
            max-width: 800px; margin: 20px auto; background: #fff;
            padding: 30px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .profile-header { text-align: center; margin-bottom: 30px; }
        .profile-header .avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 15px; border: 4px solid <?php echo $primaryAccent; ?>; }
        .profile-header h2 { margin: 0; font-size: 1.8rem; color: #333; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-group input {
            width: 100%; padding: 12px; border: 1px solid #ccc;
            border-radius: 8px; font-size: 1rem;
        }
        .form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .form-actions { text-align: center; margin-top: 30px; }
        .btn-save {
            background-color: <?php echo $primaryAccent; ?>; color: white; padding: 12px 30px;
            border: none; border-radius: 8px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: background-color 0.3s;
        }
        .btn-save:hover { background-color: #248a75; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo" /> PropertyPilot</div>
        <div class="top-right-user-info">
            <span><?php echo htmlspecialchars($fullName_session); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto_session); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="tprofile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="rentTransaction.php"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
                <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="maintenanceRequest.php"><i class="fas fa-tools"></i> Maintenance</a>
            </div>
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

                <form method="POST" action="tprofile.php" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullName">Full Name</label>
                            <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['fullName']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phoneNumber">Phone Number</label>
                            <input type="text" id="phoneNumber" name="phoneNumber" value="<?php echo htmlspecialchars($user['phoneNumber'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="nationalId">National ID</label>
                            <input type="text" id="nationalId" name="nationalId" value="<?php echo htmlspecialchars($user['nationalId']); ?>" required>
                        </div>
                    </div>
                     <div class="form-row">
                        <div class="form-group">
                            <label for="profession">Profession</label>
                            <input type="text" id="profession" name="profession" value="<?php echo htmlspecialchars($user['profession'] ?? ''); ?>">
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
