<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if tenant is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'tenant') {
    header("Location: login.php");
    exit();
}
$userId = $_SESSION['user_id'];


$primaryDark = '#1B3C53';
$primaryAccent = '#2CA58D';
$textColor = '#E0E0E0';
$secondaryBackground = '#F0F2F5';
$cardBackground = '#FFFFFF';

// DB Connection
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$fullName = "Tenant";
$profilePhoto = "default-avatar.png";
$query = "SELECT fullName, profilePhoto FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $fullName = $row['fullName'];
    $profilePhoto = $row['profilePhoto'] ?: "default-avatar.png";
}
$stmt->close();


$rentDue = 0;
$pendingBills = 0;
$upcomingMeetings = 0;
$pendingMaintenance = 0;


$query = "
    SELECT 
        SUM(GREATEST(0, (rb.rent_amount + rb.water_bill + rb.utility_bill + rb.guard_bill + rb.previous_due - IFNULL(t.total_paid, 0)))) as total_due
    FROM rentandbill rb
    LEFT JOIN (
        SELECT rent_id, SUM(amount) as total_paid 
        FROM transactions 
        WHERE status = 'Paid'
        GROUP BY rent_id
    ) t ON rb.rent_id = t.rent_id
    WHERE rb.tenant_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $rentDue = $row['total_due'] > 0 ? $row['total_due'] : 0;
}
$stmt->close();


$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM rentandbill rb
    LEFT JOIN (
        SELECT rent_id, SUM(amount) as total_paid 
        FROM transactions 
        GROUP BY rent_id
    ) t ON rb.rent_id = t.rent_id
    WHERE rb.tenant_id = ? 
    AND (rb.rent_amount + rb.water_bill + rb.utility_bill + rb.guard_bill + rb.previous_due - IFNULL(t.total_paid, 0)) > 0.01
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$pendingBills = $result->fetch_assoc()['count'];
$stmt->close();


$result = $conn->query("SELECT COUNT(*) as count FROM meeting_schedule WHERE tenant_id = $userId AND date >= CURDATE()");
$upcomingMeetings = $result->fetch_assoc()['count'];


$result = $conn->query("SELECT COUNT(*) as count FROM maintenance_requests WHERE tenant_id = $userId AND status != 'Completed'");
$pendingMaintenance = $result->fetch_assoc()['count'];


$paymentHistory = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $paymentHistory[$month] = ['month' => date('M Y', strtotime("-$i months")), 'amount' => 0];
}

$query = "
    SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, SUM(amount) as total_paid 
    FROM transactions 
    WHERE tenant_id = ? AND transaction_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
    GROUP BY month ORDER BY month ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (isset($paymentHistory[$row['month']])) {
        $paymentHistory[$row['month']]['amount'] = $row['total_paid'];
    }
}

$paymentLabels = [];
$paymentData = [];
foreach ($paymentHistory as $data) {
    $paymentLabels[] = $data['month'];
    $paymentData[] = $data['amount'];
}
$stmt->close();


$maintenanceStatusData = [];
$query = "
    SELECT status, COUNT(*) as count 
    FROM maintenance_requests 
    WHERE tenant_id = ? 
    GROUP BY status
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $maintenanceStatusData[$row['status']] = $row['count'];
}
$stmt->close();
$maintenanceStatuses = ['Pending', 'In Progress', 'Completed'];
$maintenanceLabels = [];
$maintenanceData = [];
$maintenanceColors = ['#f39c12', '#3498db', '#27ae60'];

foreach ($maintenanceStatuses as $status) {
    $maintenanceLabels[] = $status;
    $maintenanceData[] = $maintenanceStatusData[$status] ?? 0;
}


$recentTransactions = [];
$query = "
    SELECT t.*, rb.billing_date, DATE_FORMAT(rb.billing_date, '%M %Y') as billing_month
    FROM transactions t
    JOIN rentandbill rb ON t.rent_id = rb.rent_id
    WHERE t.tenant_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 5
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $recentTransactions[] = $row;
}
$stmt->close();


$filter_year = $_GET['year'] ?? date('Y');
$filter_month = $_GET['month'] ?? '';

$yearly_summary = [];
$query = "
    SELECT 
        DATE_FORMAT(rb.billing_date, '%M %Y') as month,
        rb.billing_date,
        rb.rent_id,
        -- Fixed total billed amount (what landlord originally set)
        (rb.rent_amount + rb.water_bill + rb.utility_bill + rb.guard_bill + rb.previous_due) as total_billed_amount,
        -- Total paid amount
        IFNULL(t.total_paid, 0) as total_paid_amount,
        -- Due amount (never negative)
        GREATEST(0, (rb.rent_amount + rb.water_bill + rb.utility_bill + rb.guard_bill + rb.previous_due - IFNULL(t.total_paid, 0))) as due_amount,
        trans.latest_transaction_date,
        trans.latest_status
    FROM rentandbill rb
    LEFT JOIN (
        SELECT rent_id, SUM(amount) as total_paid 
        FROM transactions 
        WHERE status = 'Paid'
        GROUP BY rent_id
    ) t ON rb.rent_id = t.rent_id
    LEFT JOIN (
        SELECT rent_id, MAX(transaction_date) AS latest_transaction_date, 
               (SELECT status FROM transactions WHERE rent_id = t_inner.rent_id ORDER BY transaction_date DESC LIMIT 1) AS latest_status
        FROM transactions t_inner
        GROUP BY rent_id
    ) trans ON rb.rent_id = trans.rent_id
    WHERE rb.tenant_id = ? AND YEAR(rb.billing_date) = ?
";

$params = [$userId, $filter_year];
$types = "is";

if (!empty($filter_month)) {
    $query .= " AND MONTH(rb.billing_date) = ?";
    $params[] = $filter_month;
    $types .= "i";
}

$query .= " ORDER BY rb.billing_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()){
    $yearly_summary[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Tenant Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn { background-color: #dc3545; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
            display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
            padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            z-index: 1000; flex-shrink: 0; width: 250px; height: 100%;
        }
        .vertical-sidebar .nav-links a {
            color: <?php echo $textColor; ?>; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
            margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
            transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }
        .welcome-header { margin-bottom: 40px; }
        .welcome-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0 0 5px 0; }
        .welcome-header p { font-size: 1.1rem; color: #7f8c8d; margin: 0; }
        
        .dashboard-section { margin-bottom: 40px; }

        .cards-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; }
        .card {
            background: <?php echo $cardBackground; ?>; padding: 25px; border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); text-align: center;
            border-left: 5px solid; transition: all 0.3s ease;
        }
        .card:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12); }
        .card.rent { border-color: #c0392b; }
        .card.bills { border-color: #f39c12; }
        .card.notifications { border-color: #8e44ad; }
        .card.maintenance { border-color: #2980b9; }
        .card .icon { font-size: 2.5rem; margin-bottom: 15px; }
        .card.rent .icon { color: #c0392b; }
        .card.bills .icon { color: #f39c12; }
        .card.notifications .icon { color: #8e44ad; }
        .card.maintenance .icon { color: #2980b9; }
        .card .number { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin-bottom: 5px; }
        .card .label { font-size: 1rem; font-weight: 500; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; }
        
        .section-header { font-size: 1.8rem; font-weight: 600; color: #2c3e50; margin-bottom: 20px; }
        .insights-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; align-items: flex-start; }
        .chart-card { background: <?php echo $cardBackground; ?>; padding: 25px; border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.07); }
        .chart-card h3 { margin-top: 0; text-align: center; color: #34495e; }
        
        .table-container { background: <?php echo $cardBackground; ?>; border-radius: 15px; overflow: hidden; box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #f0f0f0; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .filter-form { display: flex; gap: 15px; margin-bottom: 20px; }
        .filter-form select, .filter-form button { padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; }
        .filter-form button { background-color: <?php echo $primaryAccent; ?>; color: white; border-color: <?php echo $primaryAccent; ?>; cursor: pointer; }

        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.85em; font-weight: 600; color: white; text-align: center; }
        .status-Paid { background-color: #28a745; }
        .status-Partially-Paid { background-color: #ffc107; color: #333; }
        .status-Unpaid { background-color: #dc3545; }

        .transaction-item {
            padding: 12px; border-bottom: 1px solid #f0f0f0; 
            display: flex; justify-content: space-between; align-items: center;
        }
        .transaction-amount { font-weight: 600; font-size: 1.1em; color: #2c3e50; }
        .transaction-month { color: #7f8c8d; font-size: 0.9em; }
        .transaction-right { text-align: right; }
        .transaction-date { color: #7f8c8d; font-size: 0.85em; }

        .amount-paid { color: #28a745; font-weight: 600; }
        .amount-due { color: #dc3545; font-weight: 600; }
        .amount-total { color: #2c3e50; font-weight: 600; }
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
                <a href="tenant_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="tprofile.php"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="rentTransaction.php"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
                <a href="tenant_notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="maintenanceRequest.php"><i class="fas fa-tools"></i> Maintenance</a>
            </div>
        </nav>

        <main>
            <header class="welcome-header">
                <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>!</h1>
                <p>Here's what's happening with your tenancy.</p>
            </header>
            
            <section class="cards-container dashboard-section">
                <div class="card rent">
                    <div class="icon"><i class="fas fa-wallet"></i></div>
                    <div class="number">৳<?php echo number_format($rentDue, 2); ?></div>
                    <div class="label">Total Due</div>
                </div>
                <div class="card bills">
                    <div class="icon"><i class="fas fa-receipt"></i></div>
                    <div class="number"><?php echo $pendingBills; ?></div>
                    <div class="label">Pending Bills</div>
                </div>
                <div class="card notifications">
                    <div class="icon"><i class="fas fa-bell"></i></div>
                    <div class="number"><?php echo $upcomingMeetings; ?></div>
                    <div class="label">Notifications</div>
                </div>
                <div class="card maintenance">
                    <div class="icon"><i class="fas fa-tools"></i></div>
                    <div class="number"><?php echo $pendingMaintenance; ?></div>
                    <div class="label">Maintenance</div>
                </div>
            </section>

            <section class="insights-grid dashboard-section">
                <div class="chart-card">
                    <h3 class="section-header">Payment History (Last 6 Months)</h3>
                    <canvas id="paymentHistoryChart"></canvas>
                </div>
                
                <div class="chart-card">
                    <h3 class="section-header">Recent Transactions</h3>
                    <?php if(!empty($recentTransactions)): ?>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach($recentTransactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div>
                                        <div class="transaction-amount">৳<?php echo number_format($transaction['amount'], 2); ?></div>
                                        <div class="transaction-month"><?php echo $transaction['billing_month']; ?></div>
                                    </div>
                                    <div class="transaction-right">
                                        <span class="status-badge status-<?php echo str_replace(' ', '-', $transaction['status']); ?>"><?php echo $transaction['status']; ?></span>
                                        <div class="transaction-date"><?php echo date('d M, Y', strtotime($transaction['transaction_date'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align:center; padding: 20px; color: #7f8c8d;">No transactions found.</p>
                    <?php endif; ?>
                </div>
            </section>

            <?php if(array_sum($maintenanceData) > 0): ?>
            <section class="dashboard-section">
                <div class="chart-card" style="max-width: 500px; margin: 0 auto;">
                    <h3 class="section-header">Maintenance Requests Status</h3>
                    <canvas id="maintenanceStatusChart"></canvas>
                    <div style="text-align: center; margin-top: 15px; color: #7f8c8d;">
                        <small>Total Requests: <?php echo array_sum($maintenanceData); ?></small>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <section class="dashboard-section">
                <h2 class="section-header">Yearly Financial Summary</h2>
                <div class="filter-form">
                    <form action="" method="GET">
                        <select name="year">
                            <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php if($y == $filter_year) echo 'selected'; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="month">
                            <option value="">All Months</option>
                            <?php for($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php if($m == $filter_month) echo 'selected'; ?>><?php echo date('F', mktime(0,0,0,$m,1)); ?></option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit">Filter</button>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Billing Date</th>
                                <th>Payable Amount (৳)</th>
                                <th>Amount Paid (৳)</th>
                                <th>Due Amount (৳)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($yearly_summary)): ?>
                                <tr><td colspan="5" style="text-align:center;">No records found for the selected period.</td></tr>
                            <?php else: ?>
                                <?php foreach($yearly_summary as $summary): 
                                   
                                    $status = 'Unpaid';
                                    if ($summary['due_amount'] == 0) {
                                        $status = 'Paid';
                                    } elseif ($summary['total_paid_amount'] > 0) {
                                        $status = 'Partially Paid';
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo date("d M, Y", strtotime($summary['billing_date'])); ?></td>
                                        <td class="amount-total">৳<?php echo number_format($summary['total_billed_amount'], 2); ?></td>
                                        <td class="amount-paid">৳<?php echo number_format($summary['total_paid_amount'], 2); ?></td>
                                        <td class="amount-due">৳<?php echo number_format($summary['due_amount'], 2); ?></td>
                                        <td><span class="status-badge status-<?php echo str_replace(' ', '-', $status); ?>"><?php echo $status; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div> 

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            //Payment History Chart (Bar)
            const paymentHistoryCtx = document.getElementById('paymentHistoryChart').getContext('2d');
            new Chart(paymentHistoryCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($paymentLabels); ?>,
                    datasets: [{
                        label: 'Amount Paid (৳)',
                        data: <?php echo json_encode($paymentData); ?>,
                        backgroundColor: 'rgba(44, 165, 141, 0.6)',
                        borderColor: '<?php echo $primaryAccent; ?>',
                        borderWidth: 1,
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });

            //Maintenance Status Chart (Doughnut)
            <?php if(array_sum($maintenanceData) > 0): ?>
            const maintenanceStatusCtx = document.getElementById('maintenanceStatusChart').getContext('2d');
            new Chart(maintenanceStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($maintenanceLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($maintenanceData); ?>,
                        backgroundColor: ['#f39c12', '#3498db', '#27ae60'],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    cutout: '60%',
                    plugins: { 
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.parsed + ' requests';
                                }
                            }
                        }
                    }
                }
            });
            <?php endif; ?>
        });
    </script>
</body>
</html>
