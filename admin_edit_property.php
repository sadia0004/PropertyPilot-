<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Protect the page: allow only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];

// Retrieve user data from session for the header
$fullName = $_SESSION['fullName'] ?? 'Admin';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// --- Define Color Palette ---
$primaryDark = '#0A0908';
$primaryAccent = '#491D8B';
$textColor = '#F2F4F3';
$secondaryBackground = '#F0F2F5';
$cardBackground = '#FFFFFF';
$actionMaintenance = '#dc3545';

// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = '';
$property = null;
$property_id = $_GET['id'] ?? 0;

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $property_id = $_POST['property_id'];
    $apartment_rent = $_POST['apartment_rent'];
    $floor_no = $_POST['floor_no'];
    $landlord_id = $_POST['landlord_id'];

    $stmt = $conn->prepare("UPDATE properties SET apartment_rent = ?, floor_no = ?, landlord_id = ? WHERE property_id = ?");
    $stmt->bind_param("diii", $apartment_rent, $floor_no, $landlord_id, $property_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = "Property updated successfully!";
        $_SESSION['message_type'] = 'success';
        header("Location: admin_properties.php");
        exit();
    } else {
        $message = "Failed to update property.";
        $message_type = 'error';
    }
    $stmt->close();
}


// --- Fetch Property Details for Editing ---
if ($property_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM properties WHERE property_id = ?");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $property = $result->fetch_assoc();
    } else {
        $message = "Property not found.";
        $message_type = 'error';
    }
    $stmt->close();
} else {
    $message = "Invalid property ID.";
    $message_type = 'error';
}

// Fetch all landlords for the dropdown
$landlords = [];
$result = $conn->query("SELECT id, fullName FROM users WHERE userRole = 'landlord'");
while($row = $result->fetch_assoc()) {
    $landlords[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Property - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .top-right-user-info .logout-btn { background-color: <?php echo $actionMaintenance; ?>; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
            display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
            padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
            color: <?php echo $textColor; ?>; text-decoration: none; width:100%; text-align: left; padding: 12px 15px;
            margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
            transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }
        .form-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .form-container h2 { color: <?php echo $primaryDark; ?>; margin-top: 0; margin-bottom: 30px; font-size: 2rem; }
        .form-group { margin-bottom: 20px; }
        .form-group label { margin-bottom: 8px; font-weight: 600; color: #333; display: block; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .btn-save { background-color: <?php echo $primaryAccent; ?>; color: white; padding: 12px 25px; border: none; border-radius: 6px; font-size: 1rem; font-weight: bold; cursor: pointer; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .message.error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo"/>PropertyPilot - Admin Panel</div>
        <div class="top-right-user-info">
            <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="admin_add_user.php"><i class="fas fa-user-plus"></i> Add User</a>
                <a href="admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                <a href="admin_properties.php" class="active"><i class="fas fa-city"></i> Manage Properties</a>
                <a href="admin_settings.php"><i class="fas fa-cogs"></i> Settings</a>
            </div>
        </nav>

        <main>
            <div class="form-container">
                <h2>Edit Property Details</h2>

                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php if ($property): ?>
                <form action="admin_edit_property.php" method="POST">
                    <input type="hidden" name="property_id" value="<?php echo $property['property_id']; ?>">
                    
                    <div class="form-group">
                        <label>Apartment Number</label>
                        <input type="text" value="<?php echo htmlspecialchars($property['apartment_no']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="apartment_rent">Apartment Rent (৳)</label>
                        <input type="number" id="apartment_rent" name="apartment_rent" value="<?php echo htmlspecialchars($property['apartment_rent']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="floor_no">Floor Number</label>
                        <input type="number" id="floor_no" name="floor_no" value="<?php echo htmlspecialchars($property['floor_no']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="landlord_id">Assign to Landlord</label>
                        <select id="landlord_id" name="landlord_id" required>
                            <?php foreach ($landlords as $landlord): ?>
                                <option value="<?php echo $landlord['id']; ?>" <?php if ($landlord['id'] == $property['landlord_id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($landlord['fullName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
