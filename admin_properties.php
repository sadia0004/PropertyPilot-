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

// --- Handle Property Deletion ---
if (isset($_GET['delete_id'])) {
    $property_to_delete = intval($_GET['delete_id']);
    
    $stmt = $conn->prepare("DELETE FROM properties WHERE property_id = ?");
    $stmt->bind_param("i", $property_to_delete);
    if ($stmt->execute()) {
        $message = "Property deleted successfully.";
        $message_type = 'success';
    } else {
        $message = "Failed to delete property. It might be linked to other records.";
        $message_type = 'error';
    }
    $stmt->close();
}


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}



$landlordProperties = [];
$search_term = $_GET['search'] ?? '';

$query = "
    SELECT 
        p.property_id,
        p.apartment_no,
        p.apartment_rent,
        p.apartment_status,
        p.floor_no,
        u.fullName AS landlord_name
    FROM properties p
    JOIN users u ON p.landlord_id = u.id
    WHERE u.userRole = 'landlord'
";

if (!empty($search_term)) {
    $query .= " AND u.fullName LIKE ?";
}

$query .= " ORDER BY u.fullName, p.apartment_no ASC";

$stmt = $conn->prepare($query);

if (!empty($search_term)) {
    $like_search_term = "%" . $search_term . "%";
    $stmt->bind_param("s", $like_search_term);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $landlordProperties[$row['landlord_name']][] = $row;
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Properties - Admin Panel</title>
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
        .page-header { margin-bottom: 20px; }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
        
        .search-container {
            background: <?php echo $cardBackground; ?>;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .search-container form { display: flex; gap: 15px; align-items: center; }
        .search-container input {
            flex-grow: 1; padding: 10px 15px; border-radius: 8px;
            border: 1px solid #ddd; font-size: 1rem;
        }
        .search-container button, .search-container a {
            padding: 10px 20px; border-radius: 8px; border: none;
            color: white; font-weight: bold; cursor: pointer;
            text-decoration: none;
        }
        .search-container button { background-color: <?php echo $primaryAccent; ?>; }
        .search-container a { background-color: #6c757d; }

        .landlord-property-group { margin-bottom: 40px; }
        .landlord-header {
            font-size: 1.5rem; font-weight: 600; color: #34495e;
            padding-bottom: 10px; border-bottom: 2px solid <?php echo $primaryAccent; ?>;
            margin-bottom: 20px;
        }
        .table-container { background: <?php echo $cardBackground; ?>; border-radius: 15px; overflow: hidden; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        
        .status-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: white; }
        .status-Occupied { background-color: #e74c3c; }
        .status-Vacant { background-color: #2ecc71; }

        .actions-cell { display: flex; gap: 10px; }
        .action-btn { 
            padding: 8px 15px; border: none; border-radius: 8px; 
            color: white; font-weight: bold; text-decoration: none; 
            cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .btn-edit { background-color: #3498db; }
        .btn-delete { background-color: #c0392b; }

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
                <a href="admin_properties.php" class="active"><i class="fas fa-city"></i> Manage Properties</a>
                <a href="admin_settings.php"><i class="fas fa-cogs"></i> Settings</a>
            </div>
        </nav>

        <main>
            <div class="page-header">
                <h1>Manage Properties</h1>
            </div>

            <div class="search-container">
                <form action="admin_properties.php" method="GET">
                    <input type="text" name="search" placeholder="Search by landlord name..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                    <a href="admin_properties.php">Clear Filter</a>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (empty($landlordProperties)): ?>
                <div class="table-container" style="text-align: center; padding: 40px;">
                    <?php if (!empty($search_term)): ?>
                        No properties found for landlords matching "<?php echo htmlspecialchars($search_term); ?>".
                    <?php else: ?>
                        No properties found on the platform.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($landlordProperties as $landlordName => $properties): ?>
                    <div class="landlord-property-group">
                        <h2 class="landlord-header"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($landlordName); ?>'s Properties</h2>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Apartment No</th>
                                        <th>Rent (৳)</th>
                                        <th>Floor</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($properties as $prop): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($prop['apartment_no']); ?></strong></td>
                                        <td><?php echo number_format($prop['apartment_rent']); ?></td>
                                        <td><?php echo htmlspecialchars($prop['floor_no']); ?></td>
                                        <td><span class="status-badge status-<?php echo $prop['apartment_status']; ?>"><?php echo $prop['apartment_status']; ?></span></td>
                                        <td class="actions-cell">
                                            <a href="admin_edit_property.php?id=<?php echo $prop['property_id']; ?>" class="action-btn btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="admin_properties.php?delete_id=<?php echo $prop['property_id']; ?>" 
                                               class="action-btn btn-delete"
                                               onclick="return confirm('Are you sure you want to delete this property? This may affect linked tenants.');">
                                               <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
