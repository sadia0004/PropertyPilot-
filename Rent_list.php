<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check
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
$primaryAccent = '#2c5dbd';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';
$cardBackground = '#ffffff';

// Action button colors
$actionAdd = '#28a745';
$actionBilling = '#ffc107';
$actionViewRentList = '#17a2b8';
$actionViewTenantList = '#6f42c1';
$actionApartmentList = '#6c757d';
$actionScheduleCreate = '#e83e8c';
$actionScheduleDetails = '#fd7e14';

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Handle Search: Set default month/year to current, or use search values
$search_month = $_GET['month'] ?? date('m');
$search_year = $_GET['year'] ?? date('Y');

// ✅ Fetch rent records based on search
$rent_list = [];
$query = "SELECT rb.*, a.name as tenant_name 
          FROM rentAndBill rb
          JOIN addtenants a ON rb.tenant_id = a.tenant_id
          WHERE rb.landlord_id = ? 
          AND MONTH(rb.billing_date) = ? 
          AND YEAR(rb.billing_date) = ?
          ORDER BY rb.billing_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $landlord_id, $search_month, $search_year);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $rent_list[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent and Bill List</title>
    
    <style>
        :root {
            --primary-color: #006A4E; 
            --secondary-color: #4A90E2; 
            --background-color: #f0f4ff;
            --card-background: #ffffff;
            --text-color: #333;
            --border-color: #ddd;
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
          color: #f0f4ff; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .vertical-sidebar .action-buttons {
          margin-top: 5px; width: 100%; display: flex; flex-direction: column;
          gap: 8px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 10px;
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
        .search-container select, .search-container input, .search-container button {
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
                <a href="landlord_dashboard.php">Dashboard</a>
                <a href="profile.php">Profile</a>
                <a href="notifications.php">Notifications</a>
            </div>
            <section class="action-buttons">
                <h3>Quick Actions</h3>
                <a href="add_tenant.php" class="action-link link-tenant">+ Add Tenant</a>
                <a href="view_tenants.php" class="action-link link-tenant-list">View Tenant List</a>
                <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
                <a href="RentAndBillForm.php" class="action-link link-billing">Rent and Bills</a>
                <a href="Rent_list.php" class="action-link link-rent active">View Rent List</a>
                <a href="Schedule_create.php" class="action-link link-schedule-create">Create Schedule</a>
                <a href="scheduleInfo.php" class="action-link link-schedule-details">🗓️ Schedule Details</a>
            </section>
        </nav>

        <main class="page-main-content">
            <div class="list-container">
                <header class="list-header">
                    <h1>Rent & Bills History</h1>
                </header>
                <div class="search-container">
                    <form method="GET" action="" style="display: flex; gap: 20px; align-items: center;">
                        <select name="month" id="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php if ($m == $search_month) echo 'selected'; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <input type="number" name="year" id="year" value="<?php echo $search_year; ?>" placeholder="Year" min="2000" max="2100">
                        <button type="submit"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Tenant Name</th>
                                <th>Apt No</th>
                                <th>Rent (৳)</th>
                                <th>Other Bills (৳)</th>
                                <th>Previous Due (৳)</th>
                                <th>Total (৳)</th>
                                <th>Billing Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rent_list)): ?>
                                <tr>
                                    <td colspan="7" class="no-records">No records found for the selected period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($rent_list as $record): 
                                    $other_bills = $record['water_bill'] + $record['utility_bill'] + $record['guard_bill'];
                                    $total_bill = $record['rent_amount'] + $other_bills + $record['previous_due'];
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($record['tenant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($record['apartment_no']); ?></td>
                                        <td><?php echo number_format($record['rent_amount'], 2); ?></td>
                                        <td><?php echo number_format($other_bills, 2); ?></td>
                                        <td><?php echo number_format($record['previous_due'], 2); ?></td>
                                        <td><strong><?php echo number_format($total_bill, 2); ?></strong></td>
                                        <td><?php echo date("d M, Y", strtotime($record['billing_date'])); ?></td>
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
