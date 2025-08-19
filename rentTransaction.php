<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check for tenants
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'tenant') {
    header("Location: login.php");
    exit();
}
$tenant_id = $_SESSION['user_id'];

// Retrieve user data from session for the navbar
$fullName_session = $_SESSION['fullName'] ?? 'Tenant';
$profilePhoto_session = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// --- Define Color Palette for Tenant Dashboard ---
$primaryDark = '#1B3C53';
$primaryAccent = '#2CA58D';
$textColor = '#E0E0E0';
$secondaryBackground = '#F0F2F5';

// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Fetch Unpaid Bills for the Tenant ---
$unpaid_bills = [];
$query = "SELECT * FROM rentandbill WHERE tenant_id = ? AND (satus IS NULL OR satus != 'Paid') ORDER BY billing_date DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $unpaid_bills[] = $row;
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Rent & Bills - PropertyPilot</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: <?php echo $secondaryBackground; ?>;
      color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
    }
    .main-top-navbar {
      background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px; display: flex;
      justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
    }
    .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
    .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
    .top-right-user-info { display: flex; align-items: center; gap: 20px; }
    .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
    .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
    .top-right-user-info .logout-btn {
      background-color: #dc3545; color: <?php echo $textColor; ?>; padding: 8px 15px;
      border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
    }
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
    
    .bill-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 30px;
    }
    .bill-card {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    .bill-header {
        background-color: #2980b9;
        color: white;
        padding: 15px 20px;
        font-size: 1.2rem;
        font-weight: 600;
    }
    .bill-body {
        padding: 20px;
    }
    .bill-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    .bill-item:last-child {
        border-bottom: none;
    }
    .bill-item .label {
        font-weight: 500;
        color: #555;
    }
    .bill-item .value {
        font-weight: 600;
        color: #333;
    }
    .bill-total {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid #2980b9;
        text-align: right;
    }
    .bill-total .label {
        font-size: 1.2rem;
        font-weight: 600;
    }
    .bill-total .value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2980b9;
    }
    .payment-method-select {
        width: 100%;
        padding: 10px;
        margin-top: 20px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 1rem;
    }
    .pay-button {
        display: block;
        width: 100%;
        padding: 15px;
        margin-top: 10px;
        background-color: <?php echo $primaryAccent; ?>;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .pay-button:hover {
        background-color: #248a75;
    }
    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; text-align: center; }
    .success { background-color: #d4edda; color: #155724; }
    .error { background-color: #f8d7da; color: #721c24; }
    .no-bills { text-align: center; padding: 50px; font-size: 1.2rem; color: #777; }
  </style>
</head>
<body>
  <header class="main-top-navbar">
    <div class="brand">
      <img src="image/logo.png" alt="PropertyPilot Logo" />
      PropertyPilot
    </div>
    <div class="top-right-user-info">
      <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName_session); ?></span>
      <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto_session); ?>" alt="Profile Photo">
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <div class="dashboard-content-wrapper">
    <nav class="vertical-sidebar">
      <div class="nav-links">
        <a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="tprofile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="rentTransaction.php"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
        <a href="#"><i class="fas fa-bell"></i> Notifications</a>
        <a href="maintenanceRequest.php"><i class="fas fa-tools"></i> Maintenance</a>
      </div>
    </nav>

    <main>
        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (empty($unpaid_bills)): ?>
            <div class="no-bills">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: #2ecc71;"></i>
                <h2 style="margin-top: 20px;">All Caught Up!</h2>
                <p>You have no pending bills at the moment.</p>
            </div>
        <?php else: ?>
            <div class="bill-container">
                <?php foreach ($unpaid_bills as $bill): 
                    $total_bill = $bill['rent_amount'] + $bill['previous_due'] + $bill['water_bill'] + $bill['utility_bill'] + $bill['guard_bill'];
                ?>
                <div class="bill-card">
                    <div class="bill-header">
                        Bill for <?php echo date("F Y", strtotime($bill['billing_date'])); ?>
                    </div>
                    <div class="bill-body">
                        <div class="bill-item">
                            <span class="label">Apartment No:</span>
                            <span class="value"><?php echo htmlspecialchars($bill['apartment_no']); ?></span>
                        </div>
                        <div class="bill-item">
                            <span class="label">Base Rent:</span>
                            <span class="value">৳<?php echo number_format($bill['rent_amount'], 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span class="label">Previous Due:</span>
                            <span class="value">৳<?php echo number_format($bill['previous_due'], 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span class="label">Water Bill:</span>
                            <span class="value">৳<?php echo number_format($bill['water_bill'], 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span class="label">Utility Bill:</span>
                            <span class="value">৳<?php echo number_format($bill['utility_bill'], 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span class="label">Guard Bill:</span>
                            <span class="value">৳<?php echo number_format($bill['guard_bill'], 2); ?></span>
                        </div>
                        <div class="bill-total">
                            <span class="label">Total Amount:</span>
                            <span class="value">৳<?php echo number_format($total_bill, 2); ?></span>
                        </div>
                        <form action="payment.php" method="GET">
                            <input type="hidden" name="rent_id" value="<?php echo $bill['rent_id']; ?>">
                            <input type="hidden" name="landlord_id" value="<?php echo $bill['landlord_id']; ?>">
                            <input type="hidden" name="amount" value="<?php echo $total_bill; ?>">
                            <select name="payment_method" class="payment-method-select">
                                <option value="Card">Card</option>
                                <option value="Mobile Banking">Mobile Banking</option>
                            </select>
                            <button type="submit" class="pay-button">Pay Now</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
  </div> 
</body>
</html>
