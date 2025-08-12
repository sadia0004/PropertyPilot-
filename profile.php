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
    <link rel="stylesheet" href="profile.css">
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand">
            <img src="image/logo.png" alt="Logo" /> PropertyPilot
        </div>
        <div class="top-right-user-info">
            <span><?php echo htmlspecialchars($_SESSION['fullName']); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($_SESSION['profilePhoto'] ?: 'image/default-avatar.png'); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <a href="landlord_dashboard.php">Dashboard</a>
            <a href="profile.php" class="active">My Profile</a>
            <a href="notice_board.html">Notifications</a>
            <a href="scheduleInfo.php">Schedules</a>
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