<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if tenant is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'tenant') {
    header("Location: login.php");
    exit();
}

// DB Connection
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$userId = $_SESSION['user_id'];

// Fetch user info from `users` table
$userQuery = $conn->prepare("SELECT fullName, profilePhoto FROM users WHERE id = ?");
$userQuery->bind_param("i", $userId);
$userQuery->execute();
$userQuery->bind_result($fullName, $profilePhoto);
$userQuery->fetch();
$userQuery->close();

// Default values
$profession = "Not Provided";
$apartmentNo = "Not Assigned";
$rentDate = "N/A";

// Fetch tenant extra info from `tenants` table
$tenantQuery = $conn->prepare("SELECT profession, apartment_no, rent_date FROM tenants WHERE tenant_id = ?");
$tenantQuery->bind_param("i", $userId);
$tenantQuery->execute();
$tenantQuery->bind_result($profession, $apartmentNo, $rentDate);
$tenantQuery->fetch();
$tenantQuery->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Tenant Dashboard</title>
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f0f4ff;
      color: #222;
    }

    .navbar {
      display: flex;
      align-items: center;
      background-color: #2980b9;
      padding: 12px 25px;
      color: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    }

    .navbar .brand img {
      height: 60px;
      width: 60px;
      border-radius: 50%;
      margin-right: 15px;
      object-fit: cover;
    }

    .navbar h1 {
      font-size: 22px;
      margin-right: auto;
    }

    .navbar a {
      color: white;
      margin: 0 10px;
      text-decoration: none;
      font-weight: 600;
      padding: 6px 10px;
      border-radius: 5px;
    }

    .navbar a:hover,
    .navbar a.active {
      background-color: #1c598a;
    }

     .profile-box {
    display: flex;
    align-items: center;
    margin-left: auto;
    gap: 10px;
    padding: 10px;
  }
  .profile-box img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
  }

    main {
      max-width: 960px;
      margin: 40px auto;
      padding: 0 20px;
    }

    .welcome-container {
      background: white;
      padding: 30px;
      border-radius: 15px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
    }

    .welcome-message {
      font-size: 28px;
      font-weight: bold;
      color: #2980b9;
    }

    .property-name {
      font-size: 20px;
      color: #333;
      margin-top: 10px;
    }

    .important-alerts {
      margin-top: 20px;
      background: #ffeeba;
      border-left: 5px solid #f0ad4e;
      padding: 12px;
      border-radius: 5px;
    }

    .cards-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
    }

    .card {
      background: white;
      padding: 25px;
      border-radius: 15px;
      text-align: center;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    }

    .card .icon {
      font-size: 36px;
      margin-bottom: 10px;
      color: #2980b9;
    }

    .card .number {
      font-size: 22px;
      font-weight: bold;
      color: #2c3e50;
    }

    .card .label {
      font-size: 14px;
      font-weight: 600;
      color: #7f8c8d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
  </style>
</head>
<body>

  <nav class="navbar">
    <div class="brand" tabindex="0">
    <img src="image/logo.png" alt="PropertyPilot Logo" width="30" height="30" />
  
   </div>
    <h1>PropertyPilot</h1>
    <a href="profile.html" class="active">Profile</a>
    <a href="rent-bills.html">Rent & Bills</a>
    <a href="notifications.html">Notifications</a>
    <a href="maintenance.html">Maintenance</a>
    <a href="logout.php">LogOut</a>

     <!-- User Profile Info -->
  <div class="profile-box">
    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
    <span><?php echo htmlspecialchars($fullName); ?></span>
  </div>
  </nav>

  <main>
    <section class="welcome-container">
      <div class="welcome-message">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></div>
      <div class="property-name">🏠 Apartment: <?php echo htmlspecialchars($apartmentNo); ?></div>
      <div class="property-name">🧑‍💼 Profession: <?php echo htmlspecialchars($profession); ?></div>
      <div class="property-name">📅 Rent Date: <?php echo htmlspecialchars($rentDate); ?></div>
      <div class="important-alerts">🔔 Your rent is due in 3 days</div>
    </section>

    <section class="cards-container">
      <div class="card">
        <div class="icon">💰</div>
        <div class="number">৳12,000</div>
        <div class="label">Rent Due</div>
      </div>

      <div class="card">
        <div class="icon">📄</div>
        <div class="number">2</div>
        <div class="label">Pending Bills</div>
      </div>

      <div class="card">
        <div class="icon">🔔</div>
        <div class="number">5</div>
        <div class="label">Unread Notifications</div>
      </div>

      <div class="card">
        <div class="icon">🛠️</div>
        <div class="number">1</div>
        <div class="label">Open Maintenance</div>
      </div>
    </section>
  </main>

</body>
</html>
