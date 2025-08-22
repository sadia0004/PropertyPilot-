<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


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

$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$totalUsers = 0; $totalLandlords = 0; $totalTenants = 0; $totalProperties = 0; $totalPlatformIncome = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM users"); $totalUsers = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE userRole = 'landlord'"); $totalLandlords = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE userRole = 'tenant'"); $totalTenants = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT COUNT(*) as count FROM properties"); $totalProperties = $result->fetch_assoc()['count'];
$result = $conn->query("SELECT SUM(monthly_rent) as total FROM addtenants"); $totalPlatformIncome = $result->fetch_assoc()['total'] ?? 0;


$totalTransactionsCount = 0; 
$totalAmountTransacted = 0;
$averageRent = 0;


$result = $conn->query("SELECT COUNT(*) as count FROM transactions");
$totalTransactionsCount = $result->fetch_assoc()['count']; 


$result = $conn->query("SELECT SUM(amount) as total FROM transactions");
$totalAmountTransacted = $result->fetch_assoc()['total'] ?? 0;


$result = $conn->query("SELECT AVG(apartment_rent) as avg_rent FROM properties");
$averageRent = $result->fetch_assoc()['avg_rent'] ?? 0;



$userRolesData = [];
$result = $conn->query("SELECT userRole, COUNT(*) as count FROM users WHERE userRole != 'admin' GROUP BY userRole");
while ($row = $result->fetch_assoc()) {
    $userRolesData[$row['userRole']] = $row['count'];
}


$monthlyRegData = [];
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $months[$month] = ['month' => date('M Y', strtotime("-$i months")), 'count' => 0];
}
$regQuery = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
             FROM users 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
             GROUP BY month ORDER BY month ASC";
$regResult = $conn->query($regQuery);
while ($row = $regResult->fetch_assoc()) {
    if (isset($months[$row['month']])) {
        $months[$row['month']]['count'] = $row['count'];
    }
}

$regLabels = [];
$regCounts = [];
foreach ($months as $data) {
    $regLabels[] = $data['month'];
    $regCounts[] = $data['count'];
}


$recentUsers = [];
$result = $conn->query("SELECT fullName, email, userRole, created_at FROM users ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    $recentUsers[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard - PropertyPilot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: <?php echo $secondaryBackground; ?>; color: #333;
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
        .welcome-header { margin-bottom: 30px; }
        .welcome-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0 0 5px 0; }
        .welcome-header p { font-size: 1.1rem; color: #7f8c8d; margin: 0; }
        
        .dashboard-section { margin-bottom: 40px; } 

        .cards-container { display: grid; grid-template-columns: repeat(5, 1fr); gap: 25px; }
        .card {
            background: <?php echo $cardBackground; ?>; padding: 20px; border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.07); text-align: center;
            border-left: 5px solid; transition: all 0.3s ease;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0, 0, 0, 0.1); }
        .card .icon { font-size: 2.2rem; margin-bottom: 15px; }
        .card .number { font-size: 2.2rem; font-weight: 700; color: #2c3e50; margin-bottom: 5px; }
        .card .label { font-size: 0.9rem; font-weight: 500; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; }
        .card.users { border-color: #3498db; } .card.users .icon { color: #3498db; }
        .card.landlords { border-color: #9b59b6; } .card.landlords .icon { color: #9b59b6; }
        .card.tenants { border-color: #f1c40f; } .card.tenants .icon { color: #f1c40f; }
        .card.properties { border-color: #e67e22; } .card.properties .icon { color: #e67e22; }
        .card.income { border-color: #2ecc71; } .card.income .icon { color: #2ecc71; }

        .section-header { font-size: 1.8rem; font-weight: 600; color: #2c3e50; margin-bottom: 20px; }
        .insights-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: flex-start; }
        .chart-card { background: <?php echo $cardBackground; ?>; padding: 25px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.07); }
        .chart-card h3 { margin-top: 0; text-align: center; color: #34495e; }
        
        .table-container { background: <?php echo $cardBackground; ?>; border-radius: 15px; overflow: hidden; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background-color: #f8f9fa; font-weight: 600; }
        tr:last-child td { border-bottom: none; }
        .role-badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: white; text-transform: capitalize; }
        .role-landlord { background-color: #9b59b6; }
        .role-tenant { background-color: #f1c40f; color: #333; }
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
                <a href="admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="admin_add_user.php"><i class="fas fa-user-plus"></i> Add User</a>
                <a href="admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                <a href="admin_properties.php"><i class="fas fa-city"></i> Manage Properties</a>
                <a href="admin_settings.php"><i class="fas fa-cogs"></i> Settings</a>
            </div>
        </nav>

        <main>
            <header class="welcome-header">
                <h1>Admin Dashboard</h1>
                <p>An overview of the entire PropertyPilot platform.</p>
            </header>
            
            <section class="dashboard-section">
                <h2 class="section-header">Key Metrics</h2>
                <div class="cards-container">
                    <div class="card users"><div class="icon"><i class="fas fa-users"></i></div><div class="number"><?php echo $totalUsers; ?></div><div class="label">Total Users</div></div>
                    <div class="card landlords"><div class="icon"><i class="fas fa-user-tie"></i></div><div class="number"><?php echo $totalLandlords; ?></div><div class="label">Total Landlords</div></div>
                    <div class="card tenants"><div class="icon"><i class="fas fa-user-friends"></i></div><div class="number"><?php echo $totalTenants; ?></div><div class="label">Total Tenants</div></div>
                    <div class="card properties"><div class="icon"><i class="fas fa-building"></i></div><div class="number"><?php echo $totalProperties; ?></div><div class="label">Total Properties</div></div>
                    <div class="card income"><div class="icon"><i class="fas fa-wallet"></i></div><div class="number">৳<?php echo number_format($totalPlatformIncome); ?></div><div class="label">Platform Income</div></div>
                </div>
            </section>

            <section class="dashboard-section">
                <h2 class="section-header">Visual Insights</h2>
                <div class="insights-grid">
                    <div class="chart-card">
                        <h3>User Distribution</h3>
                        <canvas id="userRolesChart"></canvas>
                    </div>
                    <div class="chart-card">
                        <h3>Monthly Registrations</h3>
                        <canvas id="monthlyRegistrationsChart"></canvas>
                    </div>
                </div>
            </section>
            
            <section class="dashboard-section">
                <h2 class="section-header">Platform Activity</h2>
                <div class="insights-grid">
                    <div class="financial-section">
                        <h3>Financial Summary</h3>
                        <div class="table-container">
                            <table>
                                <tr><th>Metric</th><th>Value</th></tr>
                                <tr><td>Total Transactions Made</td><td><?php echo $totalTransactionsCount; ?></td></tr>
                                <tr><td>Total Amount Transacted</td><td><strong>৳<?php echo number_format($totalAmountTransacted, 2); ?></strong></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="recent-users-section">
                        <h3>Recent Registrations</h3>
                        <div class="table-container">
                            <table>
                                <thead><tr><th>Name</th><th>Role</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['fullName']); ?></td>
                                        <td><span class="role-badge role-<?php echo strtolower($user['userRole']); ?>"><?php echo htmlspecialchars($user['userRole']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
        
            const userRolesCtx = document.getElementById('userRolesChart').getContext('2d');
            new Chart(userRolesCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Landlords', 'Tenants'],
                    datasets: [{
                        label: 'User Distribution',
                        data: [
                            <?php echo $userRolesData['landlord'] ?? 0; ?>,
                            <?php echo $userRolesData['tenant'] ?? 0; ?>
                        ],
                        backgroundColor: ['#9b59b6', '#f1c40f'],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }]
                },
                options: { responsive: true, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
            });

            // --- Monthly Registrations Chart (Bar) ---
            const monthlyRegCtx = document.getElementById('monthlyRegistrationsChart').getContext('2d');
            new Chart(monthlyRegCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($regLabels); ?>,
                    datasets: [{
                        label: 'New Users',
                        data: <?php echo json_encode($regCounts); ?>,
                        backgroundColor: 'rgba(73, 29, 139, 0.6)',
                        borderColor: '<?php echo $primaryAccent; ?>',
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                    plugins: { legend: { display: false } }
                }
            });
        });
    </script>
</body>
</html>
