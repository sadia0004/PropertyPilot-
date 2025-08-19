<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check to match your dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check the role of the user
$userRole = $_SESSION['userRole'] ?? 'tenant';
if ($userRole !== 'landlord') {
    die("Access Denied: This page is for landlords only.");
}
$landlord_id = $_SESSION['user_id'];

// Retrieve user data from session
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// Define the consistent brand color palette
$primaryDark = '#021934';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';

// Action button colors
$actionAdd = '#28a745';
$actionBilling = '#ffc107';
$actionViewRentList = '#17a2b8';
$actionViewTenantList = '#6f42c1';
$actionApartmentList = '#6c757d';
$actionScheduleCreate = '#832d31ff';
$actionScheduleDetails = '#fd7e14';

// Initialize messages
$successMsg = "";
$errorMsg = "";

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $tenant_id_to_delete = $_GET['id'];

    // First, find the apartment number associated with the tenant to update its status
    $apartment_no_to_vacate = '';
    $find_apt_stmt = $conn->prepare("SELECT apartment_no FROM addtenants WHERE tenant_id = ? AND landlord_id = ?");
    $find_apt_stmt->bind_param("ii", $tenant_id_to_delete, $landlord_id);
    $find_apt_stmt->execute();
    $find_apt_result = $find_apt_stmt->get_result();
    if ($row = $find_apt_result->fetch_assoc()) {
        $apartment_no_to_vacate = $row['apartment_no'];
    }
    $find_apt_stmt->close();

    if (!empty($apartment_no_to_vacate)) {
        $conn->begin_transaction();
        try {
            // 1. Delete the tenant from the addtenants list
            $deleteStmt = $conn->prepare("DELETE FROM addtenants WHERE tenant_id = ? AND landlord_id = ?");
            $deleteStmt->bind_param("ii", $tenant_id_to_delete, $landlord_id);
            $deleteStmt->execute();
            $deleteStmt->close();

            // 2. Update the property's status back to 'Vacant'
            $updateStmt = $conn->prepare("UPDATE properties SET apartment_status = 'Vacant' WHERE apartment_no = ? AND landlord_id = ?");
            $updateStmt->bind_param("si", $apartment_no_to_vacate, $landlord_id);
            $updateStmt->execute();
            $updateStmt->close();

            $conn->commit();
            // Redirect to the same page to remove the GET parameters from the URL
            header("Location: view_tenants.php?delete_success=1");
            exit();
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $errorMsg = "❌ Error deleting tenant.";
        }
    }
}

// Display success message after redirect
if (isset($_GET['delete_success'])) {
    $successMsg = "✅ Tenant removed successfully and the apartment is now marked as vacant.";
}


// ✅ Handle Search
$search_apartment = $_GET['search_apartment'] ?? '';
$tenants_list = [];

$query = "SELECT tenant_id, name, apartment_no, monthly_rent, family_members, additional_info FROM addtenants WHERE landlord_id = ?";
$params = [$landlord_id];
$types = "i";

if (!empty($search_apartment)) {
    $query .= " AND apartment_no LIKE ?";
    $params[] = "%" . $search_apartment . "%";
    $types .= "s";
}

$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tenants_list[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Tenant List - PropertyPilot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #021934;
            --text-color: #f0f4ff;
            --secondary-background: #f0f4ff;
            --card-background: #ffffff;
            --primary-color: #006A4E;
            --secondary-color: #4A90E2;
            --border-color: #ddd;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
          margin: 0; font-family: 'Segoe UI', sans-serif; background-color: var(--secondary-background);
          color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
        }
        .main-top-navbar {
          background-color: var(--primary-dark); color: var(--text-color); padding: 15px 30px; display: flex;
          justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
          z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--text-color); }
        .top-right-user-info .logout-btn {
          background-color: #dc3545; color: var(--text-color); padding: 8px 15px; border-radius: 5px;
          text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
        }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: var(--primary-dark);
          padding: 20px 15px; color: var(--text-color); box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
          color: var(--text-color); text-decoration: none; width: 100%; text-align: left; padding: 9px 12px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center;;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .vertical-sidebar .action-buttons {
          margin-top: 5px; width: 100%; display: flex; flex-direction: column;
          gap: 7px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 7px;
        }
        .vertical-sidebar .action-buttons h3 { color: var(--text-color); font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
          width: calc(100% - 30px); padding: 9px 12px; border-radius: 8px; color: var(--text-color);
          font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center;
          justify-content: flex-start; gap: 10px; text-decoration: none; transition: all 0.2s ease;
        }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); background-color: rgba(255, 255, 255, 0.1); }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; color: #021934; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }
        
        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; }
        .list-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background-color: var(--card-background);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .list-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 30px;
        }
        .list-header h1 { margin: 0; font-size: 1.8rem; font-weight: 600; }
        .search-container {
            padding: 20px 30px;
            background-color: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .search-container input, .search-container button {
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }
        .search-container button {
            background-color: var(--secondary-color);
            color: white;
            cursor: pointer;
            border: none;
        }
        .table-wrapper {
            padding: 30px;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
            color: var(--primary-color);
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .no-records {
            text-align: center;
            padding: 50px;
            font-size: 1.2rem;
            color: #777;
        }
        .action-btn {
            padding: 6px 12px;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 0.9rem;
            margin-right: 5px;
            cursor: pointer;
            border: none;
        }
        .edit-btn { background-color: var(--secondary-color); }
        .delete-btn { background-color: #dc3545; }
        .message, .error {
            margin: 20px 30px 0;
            padding: 15px;
            border-radius: 8px;
            font-weight: 500;
        }
        .message { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>

<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo" />PropertyPilot</div>
        <div class="top-right-user-info">
            <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="propertyInfo.php"><i class="fas fa-building"></i> Add Property</a>
                 <a href="maintanance.php"><i class="fas fa-tools"></i> Maintanance</a>
            </div>
            <section class="action-buttons">
                <h3>Quick Actions</h3>
                <a href="add_tenant.php" class="action-link link-tenant">+ Add Tenant</a>
                <a href="tenant_List.php" class="action-link link-tenant-list active">View Tenant List</a>
                <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
                <a href="RentAndBillForm.php" class="action-link link-billing">Rent and Bills</a>
                <a href="Rent_list.php" class="action-link link-rent">View Rent List</a>
                <a href="Schedule_create.php" class="action-link link-schedule-create">Create Schedule</a>
                <a href="scheduleInfo.php" class="action-link link-schedule-details">🗓️ Schedule Details</a>
            </section>
        </nav>

        <main class="page-main-content">
            <div class="list-container">
                <header class="list-header">
                    <h1>Tenant List</h1>
                </header>
                
                <?php if ($successMsg): ?>
                    <div class="message"><?php echo $successMsg; ?></div>
                <?php endif; ?>
                <?php if ($errorMsg): ?>
                    <div class="error"><?php echo $errorMsg; ?></div>
                <?php endif; ?>

                <div class="search-container">
                    <form method="GET" action="" style="display: flex; gap: 20px; align-items: center;">
                        <input type="text" name="search_apartment" value="<?php echo htmlspecialchars($search_apartment); ?>" placeholder="Search by Apartment No...">
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tenant Name</th>
                                <th>Apartment No</th>
                                <th>Monthly Rent (৳)</th>
                                <th>Family Members</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tenants_list)): ?>
                                <tr>
                                    <td colspan="5" class="no-records">No tenants found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tenants_list as $tenant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($tenant['name']); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['apartment_no']); ?></td>
                                        <td><?php echo number_format($tenant['monthly_rent'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['family_members']); ?></td>
                                        <td>
                                            <a href="edit_tenant.php?id=<?php echo $tenant['tenant_id']; ?>" class="action-btn edit-btn"><i class="fas fa-edit"></i> Edit</a>
                                            <a href="tenant_List.php?action=delete&id=<?php echo $tenant['tenant_id']; ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this tenant? This will also mark the apartment as vacant.');"><i class="fas fa-trash"></i> Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div> 
</body>
</html>
