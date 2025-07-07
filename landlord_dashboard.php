<?php
session_start();

// Protect the page: only allow landlords
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'landlord') {
    header("Location: login.php");
    exit();
}

$fullName = $_SESSION['fullName'];
$profilePhoto = $_SESSION['profilePhoto'] ?: "default-avatar.png"; // Fallback if no image
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PropertyPilot Dashboard</title>
<link rel="stylesheet" href="landlordDashboard.css">
<style>
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
  .navbar {
    display: flex;
    align-items: center;
    background: #333;
    color: white;
    padding: 10px 20px;
  }
  .navbar a {
    color: white;
    margin-left: 20px;
    text-decoration: none;
  }
  .navbar .brand {
    font-weight: bold;
    font-size: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }
</style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar" role="navigation" aria-label="Main Navigation">
  <div class="brand" tabindex="0">
    <img src="image/logo.png" alt="PropertyPilot Logo" width="30" height="30" />
    PropertyPilot
  </div>
  <a href="profile.html" class="active">Profile</a>
  <a href="propertyInfo.php">Add Property Info</a>
  <a href="notifications.html">Notifications &amp; Reminders</a>
  <a href="logout.php" class="actionlink linkLogOut">Logout</a>

  <!-- User Profile Info -->
  <div class="profile-box">
    <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
    <span><?php echo htmlspecialchars($fullName); ?></span>
  </div>
</nav>

<!-- Action Buttons -->
<section class="action-buttons" aria-label="Body Navigation Links">
  <a href="tenant-management.html" class="action-link link-tenant">+ Add Tenant </a>
  <a href="billing.html" class="action-link link-billing">Rent and Bills</a> 
  <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
  <a href="maintenance.html" class="action-link link-maintenance">Maintenance Requests</a>
</section>

<!-- Dashboard Summary -->
<section class="cards-container" aria-label="Dashboard Summary Cards">
  <div class="card" tabindex="0">
    <div class="icon" aria-label="Total Flats">🏢</div>
    <div class="number">12</div>
    <div class="label">Total Flats</div>
  </div>

  <div class="card" tabindex="0">
    <div class="icon" aria-label="Total Tenants">👥</div>
    <div class="number">38</div>
    <div class="label">Total Tenants</div>
  </div>

  <div class="card" tabindex="0">
    <div class="icon" aria-label="Monthly Income">💵</div>
    <div class="number">$45,600</div>
    <div class="label">Monthly Income</div>
  </div>

  <div class="card" tabindex="0">
    <div class="icon" aria-label="Pending Maintenance">🚧</div>
    <div class="number">3</div>
    <div class="label">Pending Maintenance</div>
  </div>
</section>

</body>
</html>
