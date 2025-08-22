<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Protect the page: allow only logged-in users
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
$actionPostVacancy = '#3d5977ff'; // Color for the new button

// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Handle Dismissal of Late Payment Notification ---
$dismiss_key = 'late_rent_dismissed_' . date('Y_m');
if (isset($_GET['action']) && $_GET['action'] === 'dismiss_alerts') {
    $_SESSION[$dismiss_key] = true;
    header("Location: landlord_dashboard.php");
    exit();
}

// =================================================================
// ✅ FETCHING ALL DASHBOARD DATA FROM DATABASE
// =================================================================

// --- 1. Main Card Data ---
$totalFlats = 0;
$totalTenants = 0;
$monthlyIncome = 0;
$pendingMaintenance = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM properties WHERE landlord_id = $landlord_id");
$totalFlats = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM addtenants WHERE landlord_id = $landlord_id");
$totalTenants = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT SUM(monthly_rent) as total FROM addtenants WHERE landlord_id = $landlord_id");
$monthlyIncome = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE landlord_id = $landlord_id AND status = 'Pending'");
$pendingMaintenance = $result->fetch_assoc()['count'];

// --- 2. Analytics Data ---
$occupiedFlats = 0;
$occupancyRate = 0;
$totalDue = 0;

$result = $conn->query("SELECT COUNT(*) as count FROM properties WHERE landlord_id = $landlord_id AND apartment_status = 'Occupied'");
$occupiedFlats = $result->fetch_assoc()['count'];
if ($totalFlats > 0) {
    $occupancyRate = round(($occupiedFlats / $totalFlats) * 100);
}
$vacantFlats = $totalFlats - $occupiedFlats;


$currentMonth = date('m');
$currentYear = date('Y');
$query = "SELECT SUM(rb.rent_amount + rb.water_bill + rb.utility_bill + rb.guard_bill + rb.previous_due - IFNULL(t.total_paid, 0)) as total_due
          FROM rentandbill rb
          LEFT JOIN (SELECT rent_id, SUM(amount) as total_paid FROM transactions GROUP BY rent_id) t ON rb.rent_id = t.rent_id
          WHERE rb.landlord_id = ? AND MONTH(rb.billing_date) = ? AND YEAR(rb.billing_date) = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $landlord_id, $currentMonth, $currentYear);
$stmt->execute();
$result = $stmt->get_result();
$totalDue = $result->fetch_assoc()['total_due'] ?? 0;
$stmt->close();

// --- 3. Late Payment Notifications ---
$lateTenants = [];
$currentDay = date('d');

if ($currentDay > 12) {
    $query = "
        SELECT a.name, (rb.rent_amount + rb.water_bill + rb.utility_bill + rb.guard_bill + rb.previous_due - IFNULL(t.total_paid, 0)) as due_amount
        FROM rentandbill rb
        JOIN addtenants a ON rb.tenant_id = a.tenant_id
        LEFT JOIN (SELECT rent_id, SUM(amount) as total_paid FROM transactions GROUP BY rent_id) t ON rb.rent_id = t.rent_id
        WHERE rb.landlord_id = ? AND MONTH(rb.billing_date) = ? AND YEAR(rb.billing_date) = ?
        HAVING due_amount > 0
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $landlord_id, $currentMonth, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $lateTenants[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PropertyPilot Dashboard</title>
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
        .top-right-user-info .logout-btn:hover { background-color: #c0392b; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
            display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
            padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
            color: <?php echo $textColor; ?>; text-decoration: none; width:100%; text-align: left; padding: 9px 12px;
            margin: 6px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
            transition: background-color 0.3s ease; display: flex; align-items: center; gap: 5px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        .vertical-sidebar .action-buttons { width: 100%; display: flex; flex-direction: column; gap: 7px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 5px; }
        .vertical-sidebar .action-buttons h3 { color: <?php echo $textColor; ?>; font-size: 1.1em; margin-bottom: 4px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
            width: calc(100% - 20px); padding: 9px 12px; border-radius: 8px; color: <?php echo $textColor; ?>;
            font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center;
            justify-content: flex-start; gap: 7px; text-decoration: none; transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); background-color: rgba(255, 255, 255, 0.1); }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }

        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }
        .welcome-header { margin-bottom: 40px; }
        .welcome-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
        .welcome-header p { font-size: 1.1rem; color: #7f8c8d; margin-top: 5px; }
        
        .vacancy-card {
            background: linear-gradient(45deg, <?php echo $primaryAccent; ?>, <?php echo $actionPostVacancy; ?>);
            color: white;
            padding: 30px;
            border-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            box-shadow: 0 10px 25px rgba(0, 123, 255, 0.2);
        }
        .vacancy-card-content h2 { margin: 0 0 10px 0; font-size: 1.8rem; }
        .vacancy-card-content p { margin: 0; opacity: 0.9; }
        .btn-main-action {
            background-color: white; color: <?php echo $primaryAccent; ?>; padding: 12px 25px;
            border-radius: 8px; text-decoration: none; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.3s ease;
        }
        .btn-main-action:hover { transform: scale(1.05); }

        .cards-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; }
        .card {
            background: <?php echo $cardBackground; ?>; padding: 25px; border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); text-align: center;
            border-top: 4px solid transparent; transition: all 0.3s ease;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12); }
        .card.flats { border-color: #3498db; }
        .card.tenants { border-color: #9b59b6; }
        .card.income { border-color: #2ecc71; }
        .card.maintenance { border-color: #e74c3c; }
        .card .icon { font-size: 2.5rem; margin-bottom: 15px; }
        .card.flats .icon { color: #3498db; }
        .card.tenants .icon { color: #9b59b6; }
        .card.income .icon { color: #2ecc71; }
        .card.maintenance .icon { color: #e74c3c; }
        .card .number { font-size: 2.8rem; font-weight: 700; color: #2c3e50; margin-bottom: 5px; }
        .card .label { font-size: 1rem; font-weight: 500; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; }

        .analytics-section { margin-top: 50px; }
        .analytics-header { font-size: 1.8rem; font-weight: 600; color: #2c3e50; margin-bottom: 20px; }
        .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .chart-container { position: relative; width: 150px; height: 150px; margin: 0 auto 20px; }
        .chart-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 2rem; font-weight: 700; }
        .card.analytics-card { border-color: #1abc9c; }
        .card.analytics-card .icon { color: #1abc9c; }

        .alert-section {
            background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
            border-left: 5px solid #c0392b; border-radius: 8px;
            padding: 20px; margin-bottom: 30px;
        }
        .alert-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .alert-header h3 { margin: 0; font-size: 1.4rem; }
        .dismiss-btn {
            background: none; border: none; font-size: 1.8rem; font-weight: bold;
            color: #721c24; cursor: pointer; text-decoration: none;
            opacity: 0.7; transition: opacity 0.2s ease;
        }
        .dismiss-btn:hover { opacity: 1; }
        .alert-section p { margin: 0 0 15px 0; }
        .alert-section .late-tenant-list { list-style: none; padding: 0; margin: 0; }
        .alert-section .late-tenant-list li { display: flex; justify-content: space-between; padding: 10px; border-radius: 5px; }
        .alert-section .late-tenant-list li:nth-child(odd) { background-color: rgba(0,0,0,0.03); }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand">
            <img src="image/logo.png" alt="PropertyPilot Logo" />
            PropertyPilot
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
                <a href="landlord_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="propertyInfo.php"><i class="fas fa-building"></i> Add Property</a>
                <a href="maintanance.php"><i class="fas fa-tools"></i> Maintanance</a>
            </div>
            <section class="action-buttons">
                <h3>Quick Actions</h3>
                <a href="add_tenant.php" class="action-link link-tenant"><i class="fas fa-user-plus"></i> Add Tenant</a>
                <a href="tenant_List.php" class="action-link link-tenant-list"><i class="fas fa-users"></i> View Tenant List</a>
                <a href="apartmentList.php" class="action-link link-docs"><i class="fas fa-list"></i> Apartment List</a>
                <a href="RentAndBillForm.php" class="action-link link-billing"><i class="fas fa-file-invoice-dollar"></i> Rent and Bills</a>
                <a href="Rent_list.php" class="action-link link-rent"><i class="fas fa-list-ul"></i> View Rent List</a>
                <a href="Schedule_create.php" class="action-link link-schedule-create"><i class="fas fa-calendar-plus"></i> Create Schedule</a>
                <a href="scheduleInfo.php" class="action-link link-schedule-details"><i class="fas fa-calendar-alt"></i> Schedule Details</a>
            </section>
        </nav>

        <main>
            <header class="welcome-header">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>!</h1>
                    <p>Here's a summary of your property portfolio.</p>
                </div>
            </header>

            <section class="vacancy-card">
                <div class="vacancy-card-content">
                    <h2>Manage Your Vacancies</h2>
                    <p>You have <strong><?php echo $vacantFlats; ?></strong> vacant properties. Post a listing to find your next tenant.</p>
                </div>
                <div>
                    <a href="post_vacancy.php" class="btn-main-action"><i class="fas fa-bullhorn"></i> Post a New Vacancy</a>
                </div>
            </section>

            <?php if (!empty($lateTenants) && !isset($_SESSION[$dismiss_key])): ?>
            <section class="alert-section">
                <div class="alert-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Late Rent Alert</h3>
                    <a href="landlord_dashboard.php?action=dismiss_alerts" class="dismiss-btn">&times;</a>
                </div>
                <p>The following tenants have outstanding payments for the current month:</p>
                <ul class="late-tenant-list">
                    <?php foreach ($lateTenants as $tenant): ?>
                        <li>
                            <span><?php echo htmlspecialchars($tenant['name']); ?></span>
                            <strong>৳<?php echo number_format($tenant['due_amount']); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>
            
            <section class="cards-container">
                <div class="card flats"><div class="icon"><i class="fas fa-building"></i></div><div class="number"><?php echo $totalFlats; ?></div><div class="label">Total Flats</div></div>
                <div class="card tenants"><div class="icon"><i class="fas fa-users"></i></div><div class="number"><?php echo $totalTenants; ?></div><div class="label">Total Tenants</div></div>
                <div class="card income"><div class="icon"><i class="fas fa-dollar-sign"></i></div><div class="number">৳<?php echo number_format($monthlyIncome); ?></div><div class="label">Monthly Income</div></div>
                <div class="card maintenance"><div class="icon"><i class="fas fa-tools"></i></div><div class="number"><?php echo $pendingMaintenance; ?></div><div class="label">Pending Maintenance</div></div>
            </section>

            <section class="analytics-section">
                <h2 class="analytics-header">Analytics Overview</h2>
                <div class="analytics-grid">
                    <div class="card analytics-card">
                        <div class="chart-container">
                            <svg width="150" height="150" viewBox="0 0 36 36">
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e6e6e6" stroke-width="3"></path>
                                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#1abc9c" stroke-width="3" stroke-dasharray="<?php echo $occupancyRate; ?>, 100" stroke-linecap="round"></path>
                            </svg>
                            <div class="chart-text"><?php echo $occupancyRate; ?>%</div>
                        </div>
                        <div class="label">Occupancy Rate</div>
                    </div>
                   <div class="card analytics-card">
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="number">৳<?php echo number_format($totalDue); ?></div>
                        <div class="label">Total Amount Due</div>
                    </div>
                </div>
            </section>
        </main>
    </div>

</body>
</html>
