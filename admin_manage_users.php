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

// --- Handle User Deletion ---
if (isset($_GET['delete_id'])) {
    $user_to_delete = intval($_GET['delete_id']);
    
    if ($user_to_delete === $admin_id) {
        $message = "You cannot delete your own account.";
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_to_delete);
        if ($stmt->execute()) {
            $message = "User deleted successfully.";
            $message_type = 'success';
        } else {
            $message = "Failed to delete user.";
            $message_type = 'error';
        }
        $stmt->close();
    }
}


// --- Fetch Landlords and Tenants, excluding Admins ---
$users = [];
$query = "
    SELECT
        u.id,
        u.fullName,
        u.email,
        u.phoneNumber,
        u.userRole,
        u.created_at,
        landlord.fullName AS landlord_name,
        at.landlord_id
    FROM
        users u
    LEFT JOIN
        addtenants at ON u.id = at.tenant_id
    LEFT JOIN
        users landlord ON at.landlord_id = landlord.id
    WHERE
        u.userRole != 'admin'
    ORDER BY
        -- Group landlords and their tenants together
        COALESCE(at.landlord_id, u.id),
        -- Make landlords appear before their tenants
        CASE
            WHEN u.userRole = 'landlord' THEN 1
            ELSE 2
        END,
        u.fullName
";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Users - Admin Panel</title>
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
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
        
        .table-container { background: <?php echo $cardBackground; ?>; border-radius: 15px; overflow: hidden; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        
        .landlord-row { background-color: #f8f9fa; font-weight: bold; }
        .landlord-row td { border-top: 2px solid #dee2e6; }
        .tenant-row td:first-child { padding-left: 40px; }
        .tenant-row .fa-level-up-alt { transform: rotate(90deg); margin-right: 10px; color: #adb5bd; }

        .role-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: white; text-transform: capitalize; }
        .role-landlord { background-color: #9b59b6; }
        .role-tenant { background-color: #f1c40f; color: #333; }
        .role-admin { background-color: #e74c3c; }

        .action-btn {
            padding: 6px 12px; border: none; border-radius: 6px;
            color: white; font-weight: bold; text-decoration: none;
            cursor: pointer; transition: opacity 0.3s ease;
        }
        .btn-delete { background-color: #c0392b; }
        .btn-delete:hover { opacity: 0.8; }
        .btn-delete:disabled { background-color: #ccc; cursor: not-allowed; opacity: 0.6; }

        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand">
            <img src="image/logo.png" alt="PropertyPilot Logo" />
            PropertyPilot - Admin Panel
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
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="admin_add_user.php"><i class="fas fa-user-plus"></i> Add User</a>
                <a href="admin_manage_users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a>
                <a href="admin_properties.php"><i class="fas fa-city"></i> Manage Properties</a>
                <a href="admin_settings.php"><i class="fas fa-cogs"></i> Settings</a>
            </div>
        </nav>

        <main>
            <div class="page-header">
                <h1>Manage Users</h1>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>User Details</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Managed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" style="text-align: center; padding: 20px;">No landlords or tenants found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr class="<?php if($user['userRole'] === 'landlord') echo 'landlord-row'; else echo 'tenant-row'; ?>">
                                <td>
                                    <?php if($user['userRole'] === 'tenant'): ?>
                                        <i class="fas fa-level-up-alt"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($user['fullName']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phoneNumber']); ?></td>
                                <td><span class="role-badge role-<?php echo strtolower($user['userRole']); ?>"><?php echo htmlspecialchars($user['userRole']); ?></span></td>
                                <td><?php echo htmlspecialchars($user['landlord_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <a href="admin_manage_users.php?delete_id=<?php echo $user['id']; ?>" 
                                       class="action-btn btn-delete"
                                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');"
                                       <?php if ($user['id'] === $admin_id) echo 'disabled title="Cannot delete self"'; ?>>
                                       <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
