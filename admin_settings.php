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


$fullName = $_SESSION['fullName'] ?? 'Admin';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";


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


$settingsFile = 'settings.json';
$settings = [];


if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true);
}


$defaults = [
    'site_name' => 'PropertyPilot',
    'contact_email' => 'support@propertypilot.com',
    'currency_symbol' => '৳',
    'rent_due_day' => 12,
    'maintenance_mode' => 'off'
];
$settings = array_merge($defaults, $settings);

$message = '';
$message_type = '';

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $settings['site_name'] = trim($_POST['site_name']);
    $settings['contact_email'] = trim($_POST['contact_email']);
    $settings['currency_symbol'] = trim($_POST['currency_symbol']);
    $settings['rent_due_day'] = intval($_POST['rent_due_day']);
    $settings['maintenance_mode'] = $_POST['maintenance_mode'] ?? 'off';

   
    if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT))) {
        $message = "Settings updated successfully!";
        $message_type = 'success';
    } else {
        $message = "Failed to save settings. Please check file permissions.";
        $message_type = 'error';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Settings - Admin Panel</title>
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
        .top-right-user-info .logout-btn { background-color: <?php echo $actionMaintenance; ?>; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
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
        .page-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
        
        .settings-container { max-width: 900px; margin: 0 auto; }
        .settings-card { background: #fff; padding: 30px 40px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .settings-card h2 { color: <?php echo $primaryDark; ?>; margin-top: 0; margin-bottom: 25px; font-size: 1.5rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { margin-bottom: 8px; font-weight: 600; color: #333; display: block; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .form-actions { text-align: right; margin-top: 30px; }
        .btn {
            padding: 12px 25px; border: none; border-radius: 6px;
            cursor: pointer; font-size: 1rem; font-weight: bold;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-save { background-color: <?php echo $primaryAccent; ?>; color: white; }
        .btn-backup { background-color: #3498db; color: white;}
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; }
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
                <a href="admin_properties.php"><i class="fas fa-city"></i> Manage Properties</a>
                <a href="admin_settings.php" class="active"><i class="fas fa-cogs"></i> Settings</a>
            </div>
        </nav>

        <main>
            <div class="page-header">
                <h1>Platform Settings</h1>
            </div>

            <div class="settings-container">
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="settings-card">
                        <h2><i class="fas fa-cogs"></i> General Settings</h2>
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="contact_email">Contact Email</label>
                            <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>">
                        </div>
                    </div>

                    <div class="settings-card">
                        <h2><i class="fas fa-dollar-sign"></i> Financial Settings</h2>
                        <div class="form-group">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo htmlspecialchars($settings['currency_symbol']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="rent_due_day">Rent Considered Late After (Day of Month)</label>
                            <input type="number" id="rent_due_day" name="rent_due_day" min="1" max="28" value="<?php echo htmlspecialchars($settings['rent_due_day']); ?>">
                        </div>
                    </div>
                    
                    <div class="settings-card">
                        <h2><i class="fas fa-tools"></i> System Actions</h2>
                        <div class="form-group">
                            <label>Maintenance Mode</label>
                            <select name="maintenance_mode">
                                <option value="off" <?php if($settings['maintenance_mode'] == 'off') echo 'selected'; ?>>Off</option>
                                <option value="on" <?php if($settings['maintenance_mode'] == 'on') echo 'selected'; ?>>On (Site disabled for users)</option>
                            </select>
                        </div>
                         <div class="form-group">
                            <label>Database Backup</label>
                            <a href="db_backup.php" class="btn btn-backup"><i class="fas fa-database"></i> Download Database Backup</a>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-save"><i class="fas fa-save"></i> Save All Settings</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
