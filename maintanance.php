<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'landlord') {
    header("Location: login.php");
    exit();
}
$landlord_id = $_SESSION['user_id'];

// Retrieve user data from session
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// --- Define Color Palette ---
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


// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$updateMessage = '';

// ✅ Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['status'];

    // Validate status
    $allowed_statuses = ['Pending', 'In Progress', 'Completed'];
    if (in_array($new_status, $allowed_statuses)) {
        $update_query = "UPDATE maintenance_requests SET status = ? WHERE request_id = ? AND landlord_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("sii", $new_status, $request_id, $landlord_id);
        if ($stmt->execute()) {
            $updateMessage = "<div class='message success'>✅ Status updated successfully!</div>";
        } else {
            $updateMessage = "<div class='message error'>❌ Failed to update status.</div>";
        }
        $stmt->close();
    }
}


// ✅ Fetch maintenance requests for the landlord
$requests = [];
$query = "
    SELECT 
        mr.*, 
        u.fullName AS tenant_name,
        p.apartment_no
    FROM maintenance_requests mr
    JOIN users u ON mr.tenant_id = u.id
    JOIN properties p ON mr.property_id = p.property_id
    WHERE mr.landlord_id = ?
    ORDER BY mr.created_at DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #021934; 
            --secondary-color: #2c5dbd; 
            --background-color: #f0f4ff;
            --card-background: #ffffff;
            --text-color: #333;
            --border-color: #ddd;
            --status-pending: #fd7e14;
            --status-in-progress: #17a2b8;
            --status-completed: #28a745;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
          margin: 0; font-family: 'Poppins', sans-serif; background-color: var(--background-color);
          color: var(--text-color); display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
        }
        .main-top-navbar {
          background-color: #021934; color: #f0f4ff; padding: 15px 30px; display: flex;
          justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0,0,0,0.2);
          z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #f0f4ff; }
        .top-right-user-info .logout-btn {
          background-color: #dc3545; color: #f0f4ff; padding: 8px 15px; border-radius: 5px;
          text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
        }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: #021934;
          padding: 20px 15px; color: #f0f4ff; box-shadow: 2px 0 8px rgba(0,0,0,0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
          color: #f0f4ff; text-decoration: none; width: 100%; text-align: left; padding: 9px 12px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 7px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .vertical-sidebar .action-buttons {
          margin-top: 12px; width: 100%; display: flex; flex-direction: column;
          gap: 7px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 5px;
        }
        .vertical-sidebar .action-buttons h3 { color: #f0f4ff; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
          width: calc(100% - 30px); padding: 9px 15px; border-radius: 8px; color: #f0f4ff;
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
        .vertical-sidebar .link-maintenance { background-color: <?php echo $actionMaintenance; ?>; }

        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2.5rem; color: var(--primary-color); margin: 0; }
        
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        .request-card {
            background-color: var(--card-background);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border-left: 5px solid;
        }
        .request-card.status-Pending { border-left-color: var(--status-pending); }
        .request-card.status-In-Progress { border-left-color: var(--status-in-progress); }
        .request-card.status-Completed { border-left-color: var(--status-completed); }

        .card-header { padding: 20px; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0 0 5px 0; font-size: 1.2rem; }
        .card-header p { margin: 0; color: #666; }

        .card-body { padding: 20px; flex-grow: 1; }
        .info-item { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; }
        .info-item i { color: var(--secondary-color); width: 20px; text-align: center; }
        .info-item span { font-weight: 600; }
        .description { color: #555; line-height: 1.6; }
        .photo-link { display: inline-block; margin-top: 15px; color: var(--secondary-color); font-weight: bold; text-decoration: none; }
        .photo-link:hover { text-decoration: underline; }

        .card-footer {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
        }
        .update-form { display: flex; gap: 10px; align-items: center; }
        .update-form select { flex-grow: 1; padding: 8px; border-radius: 6px; border: 1px solid var(--border-color); }
        .update-form button {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            background-color: var(--secondary-color);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .update-form button:hover { background-color: var(--primary-color); }

        .no-records { text-align: center; padding: 50px; font-size: 1.2rem; color: #777; background-color: var(--card-background); border-radius: 15px; }
        
        .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo"/>PropertyPilot</div>
        <div class="top-right-user-info">
            <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="propertyInfo.php"><i class="fas fa-building"></i> Add Property</a>
                <a href="maintanance.php" class="active"><i class="fas fa-tools"></i> Maintanance</a>
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
            <div class="page-header">
                <h1>Maintenance Requests</h1>
            </div>

            <?php echo $updateMessage; ?>

            <div class="requests-grid">
                <?php if (empty($requests)): ?>
                    <p class="no-records">🎉 No maintenance requests at the moment.</p>
                <?php else: ?>
                    <?php foreach ($requests as $req): 
                        $status_class = str_replace(' ', '-', $req['status']);
                    ?>
                        <div class="request-card status-<?php echo $status_class; ?>">
                            <div class="card-header">
                                <h3><?php echo htmlspecialchars($req['tenant_name']); ?></h3>
                                <p>Apartment: <?php echo htmlspecialchars($req['apartment_no']); ?></p>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <i class="fas fa-tag"></i>
                                    <span>Category:</span> <?php echo htmlspecialchars($req['issue_category']); ?>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Submitted:</span> <?php echo date("d M, Y", strtotime($req['created_at'])); ?>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-info-circle"></i>
                                    <span>Status:</span> <strong><?php echo htmlspecialchars($req['status']); ?></strong>
                                </div>
                                <p class="description"><?php echo nl2br(htmlspecialchars($req['issue_description'])); ?></p>
                                <?php if (!empty($req['photo'])): ?>
                                    <a href="<?php echo htmlspecialchars($req['photo']); ?>" target="_blank" class="photo-link"><i class="fas fa-camera"></i> View Attached Photo</a>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <form method="POST" action="" class="update-form">
                                    <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                    <select name="status">
                                        <option value="Pending" <?php if ($req['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                        <option value="In Progress" <?php if ($req['status'] == 'In Progress') echo 'selected'; ?>>In Progress</option>
                                        <option value="Completed" <?php if ($req['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                                    </select>
                                    <button type="submit" name="update_status">Update</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div> 
</body>
</html>
