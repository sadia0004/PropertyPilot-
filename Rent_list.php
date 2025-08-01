<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check if landlord is logged in
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access. Please log in as a landlord.");
}
$landlord_id = $_SESSION['landlord_id'];

// Retrieve user data from session for the navbars
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
          z-index: 1001; flex-shrink: 0; height: 80px; width: 100%; 
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
        .top-right-user-info .logout-btn:hover { background-color: #c0392b; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: #021934;
          padding: 20px 15px; color: #f0f4ff; box-shadow: 2px 0 8px rgba(0,0,0,0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: auto;
        }
        .vertical-sidebar .nav-links a {
          color: #f0f4ff; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .action-buttons { margin-top: 30px; width: 100%; }
        .action-buttons h3 { color: #f0f4ff; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .action-link {
          display: block; width: 100%; padding: 12px 15px; margin-bottom: 10px; border-radius: 8px;
          color: #f0f4ff; font-weight: 600; text-decoration: none; transition: background-color 0.3s ease;
        }
        .link-tenant { background-color: #28a745; }
        .link-billing { background-color: #ffc107; color: #021934; }
        .link-docs { background-color: #6c757d; }
        .link-maintenance { background-color: #dc3545; }
        .link-schedule { background-color: #17a2b8; }
        .page-main-content {
          flex-grow: 1; padding: 30px; display: flex; flex-direction: column;
          align-items: center; height: 100%; overflow-y: auto;
        }
        .list-container {
            width: 100%;
            max-width: 1200px;
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
                <a href="propertyInfo.php" class="action-link link-docs">+ Add Property</a>
                <a href="add_tenant.php" class="action-link link-tenant">+ Add Tenant</a>
                <a href="Schedule_create.php" class="action-link link-schedule">🗓️ Schedule Meeting</a>
                <a href="RentAndBillForm.php" class="action-link link-billing active">Rent and Bills</a>
               
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
