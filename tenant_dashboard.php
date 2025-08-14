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

// --- Define Color Palette for Tenant Dashboard ---
$primaryDark = '#0A2342'; // A deep, professional blue
$primaryAccent = '#2CA58D'; // A contrasting teal for highlights
$textColor = '#E0E0E0'; // Soft white for text
$secondaryBackground = '#F0F2F5'; // Light grey for the main content area
$cardBackground = '#FFFFFF';

// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Fetch All Necessary Data in One Go ---
$fullName = "Tenant";
$profilePhoto = "default-avatar.png";
$profession = "Not Provided";
$apartmentNo = "Not Assigned";
$rentDate = "N/A";
$rentDue = 0;
$pendingBills = 0;

// Fetch combined user and tenant info
$query = "SELECT u.fullName, u.profilePhoto, t.profession, t.apartment_no, t.rent_date 
          FROM users u
          LEFT JOIN tenants t ON u.id = t.tenant_id
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $fullName = $row['fullName'];
    $profilePhoto = $row['profilePhoto'] ?: "default-avatar.png";
    $profession = $row['profession'] ?: "Not Provided";
    $apartmentNo = $row['apartment_no'] ?: "Not Assigned";
    $rentDate = $row['rent_date'] ? date("F j, Y", strtotime($row['rent_date'])) : "N/A";
}
$stmt->close();

// Fetch rent due and pending bills count
if ($apartmentNo !== "Not Assigned") {
    $rentQuery = "SELECT monthly_rent FROM addtenants WHERE apartment_no = ?";
    $rentStmt = $conn->prepare($rentQuery);
    $rentStmt->bind_param("s", $apartmentNo);
    $rentStmt->execute();
    $rentResult = $rentStmt->get_result();
    if($rentRow = $rentResult->fetch_assoc()){
        $rentDue = $rentRow['monthly_rent'];
    }
    $rentStmt->close();
    
    // This is a placeholder for bill counting logic
    // In a real application, you would query the rentAndBill table
    $pendingBills = 2; 
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tenant Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: <?php echo $secondaryBackground; ?>;
      color: #222;
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow: hidden;
    }
    .main-top-navbar {
      background-color: <?php echo $primaryDark; ?>;
      color: <?php echo $textColor; ?>;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      z-index: 1001;
      flex-shrink: 0;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 80px;
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
    .welcome-header { margin-bottom: 40px; }
    .welcome-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0 0 5px 0; }
    .welcome-header p { font-size: 1.1rem; color: #7f8c8d; margin: 0; }
    .cards-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; }
    .card {
      background: <?php echo $cardBackground; ?>; padding: 25px; border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08); text-align: center;
      border-top: 4px solid transparent; transition: all 0.3s ease;
    }
    .card:hover { transform: translateY(-5px); box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12); }
    .card.rent { border-color: #2980b9; }
    .card.bills { border-color: #f39c12; }
    .card.notifications { border-color: #8e44ad; }
    .card.maintenance { border-color: #c0392b; }
    .card .icon { font-size: 2.5rem; margin-bottom: 15px; }
    .card.rent .icon { color: #2980b9; }
    .card.bills .icon { color: #f39c12; }
    .card.notifications .icon { color: #8e44ad; }
    .card.maintenance .icon { color: #c0392b; }
    .card .number { font-size: 2.8rem; font-weight: 700; color: #2c3e50; margin-bottom: 5px; }
    .card .label { font-size: 1rem; font-weight: 500; color: #7f8c8d; text-transform: uppercase; letter-spacing: 1px; }
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
        <a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="/profile.php"><i class="fas fa-user-circle"></i> Profile</a>
        <a href="#"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
        <a href="#"><i class="fas fa-bell"></i> Notifications</a>
        <a href="#"><i class="fas fa-tools"></i> Maintenance</a>
      </div>
    </nav>

    <main>
      <header class="welcome-header">
          <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>!</h1>
          <p>Here's what's happening with your tenancy.</p>
      </header>
      <section class="cards-container">
        <div class="card rent">
          <div class="icon"><i class="fas fa-wallet"></i></div>
          <div class="number">৳<?php echo number_format($rentDue, 2); ?></div>
          <div class="label">Rent Due</div>
        </div>
        <div class="card bills">
          <div class="icon"><i class="fas fa-receipt"></i></div>
          <div class="number"><?php echo $pendingBills; ?></div>
          <div class="label">Pending Bills</div>
        </div>
        <div class="card notifications">
          <div class="icon"><i class="fas fa-bell"></i></div>
          <div class="number">5</div>
          <div class="label">Notifications</div>
        </div>
        <div class="card maintenance">
          <div class="icon"><i class="fas fa-tools"></i></div>
          <div class="number">1</div>
          <div class="label">Maintenance</div>
        </div>
      </section>
    </main>
  </div> 
</body>
</html>
