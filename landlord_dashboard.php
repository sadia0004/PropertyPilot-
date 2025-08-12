<?php
session_start();

// Protect the page: allow only logged-in users
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Retrieve user data from session
$fullName = $_SESSION['fullName'];
$profilePhoto = $_SESSION['profilePhoto'] ?: "default-avatar.png"; // Fallback if no image

// Check the role of the user (tenant or landlord)
$userRole = $_SESSION['userRole']; // 'tenant' or 'landlord'

// Define the consistent brand color palette for a professional look
$primaryDark = '#021934'; // Main dark blue for navbars
$primaryAccent = '#2c5dbd'; // Accent blue for active/hover states
$primaryHighlight = '#4CAF50'; // Green for active line/success
$textColor = '#f0f4ff'; // Light text color for dark backgrounds
$secondaryBackground = '#f0f4ff'; // Main body background
$cardBackground = '#ffffff'; // Card background

// Action button colors - Professional and distinct palette
$actionAdd = '#28a745';      // Green for 'Add Tenant'
$actionBilling = '#ffc107';   // Yellow for 'Rent & Bills'
$actionViewRentList = '#17a2b8';  // Teal for 'View Rent List'
$actionViewTenantList = '#6f42c1'; // Purple for 'View Tenant List'
$actionApartmentList = '#6c757d';// Grey for 'Apartment List'
$actionScheduleCreate = '#e83e8c';   // Magenta for 'Create Schedule'
$actionScheduleDetails = '#fd7e14'; // Orange for 'Schedule Details'
$actionMaintenance = '#dc3545';// Red for 'Maintenance Requests'

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PropertyPilot Dashboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    /* Global box-sizing for consistent layouts */
    *, *::before, *::after {
        box-sizing: border-box;
    }

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

    /* Main Top Navigation Bar (now truly fixed) */
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
      box-sizing: border-box;
    }

    .main-top-navbar .brand {
      display: flex;
      align-items: center;
      font-weight: 700;
      font-size: 22px;
    }

    .main-top-navbar .brand img {
      height: 50px;
      width: 50px;
      margin-right: 10px;
      border-radius: 50%;
      object-fit: contain;
      background: <?php echo $cardBackground; ?>;
      padding: 3px;
    }

    .top-right-user-info {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .top-right-user-info .welcome-greeting {
      font-size: 1.1em;
      font-weight: 500;
    }

    .top-right-user-info .user-photo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid <?php echo $textColor; ?>;
    }

    .top-right-user-info .logout-btn {
      background-color: <?php echo $actionMaintenance; ?>;
      color: <?php echo $textColor; ?>;
      padding: 8px 15px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s ease;
    }

    .top-right-user-info .logout-btn:hover {
      background-color: #c0392b; 
    }

    .dashboard-content-wrapper {
      display: flex;
      flex-grow: 1;
      margin-top: 80px;
      height: calc(100vh - 80px); 
      overflow: hidden;
    }

    .vertical-sidebar {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      background-color: <?php echo $primaryDark; ?>;
      padding: 20px 15px;
      color: <?php echo $textColor; ?>;
      box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      flex-shrink: 0;
      width: 250px;
      height: 100%; 
      overflow-y: hidden; /* Removed scrollbar */
    }

    .vertical-sidebar .nav-links a {
      color: <?php echo $textColor; ?>;
      text-decoration: none;
      width:100% ;
      text-align: left;
      padding: 12px 15px;
      margin: 8px 0;
      font-weight: 600;
      font-size: 16px;
      border-radius: 8px;
      transition: background-color 0.3s ease;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .vertical-sidebar .nav-links a:hover,
    .vertical-sidebar .nav-links a.active {
      background-color: <?php echo $primaryAccent; ?>;
    }

    .vertical-sidebar .action-buttons {
  
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 7px; /* Further reduced gap */
      align-items: center;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      
    }

    .vertical-sidebar .action-buttons h3 {
        color: <?php echo $textColor; ?>;
        font-size: 1.1em;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .vertical-sidebar .action-link {
      width: calc(100% - 30px);
      padding: 9px 15px; /* Reduced padding */
      border-radius: 8px;
      color: <?php echo $textColor; ?>;
      font-weight: 600;
      font-size: 14px; /* Slightly smaller font */
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 10px;
      text-decoration: none;
      transition: background-color 0.3s ease, transform 0.2s ease;
    }

    .vertical-sidebar .action-link:hover {
        transform: translateX(5px);
        background-color: rgba(255, 255, 255, 0.1);
    }
    
    /* Corrected and distinct action link colors */
    .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
    .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>;  }
    .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
    .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
    .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
    .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
    .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }


    main {
      flex-grow: 1;
      padding: 40px;
      height: 100%; 
      overflow-y: auto; 
    }

    .welcome-header {
        margin-bottom: 40px;
    }
    .welcome-header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        color: #2c3e50;
        margin: 0 0 5px 0;
    }
    .welcome-header p {
        font-size: 1.1rem;
        color: #7f8c8d;
        margin: 0;
    }

    .cards-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 30px;
    }

    .card {
      background: <?php echo $cardBackground; ?>;
      padding: 25px;
      border-radius: 15px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
      text-align: center;
      border-top: 4px solid transparent;
      transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(0, 0, 0, 0.12);
    }
    
    .card.flats { border-color: #3498db; }
    .card.tenants { border-color: #9b59b6; }
    .card.income { border-color: #2ecc71; }
    .card.maintenance { border-color: #e74c3c; }

    .card .icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
    }
    .card.flats .icon { color: #3498db; }
    .card.tenants .icon { color: #9b59b6; }
    .card.income .icon { color: #2ecc71; }
    .card.maintenance .icon { color: #e74c3c; }


    .card .number {
      font-size: 2.8rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 5px;
    }

    .card .label {
      font-size: 1rem;
      font-weight: 500;
      color: #7f8c8d;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
  </style>
</head>

<body>
  <header class="main-top-navbar">
    <div class="brand">
      <img src="image/logo.png" alt="PropertyPilot Logo" />
      PropertyPilot
    </div>
    <div class="top-right-user-info">
      <span class="welcome-greeting"><?php echo htmlspecialchars($fullName); ?></span>
      <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile Photo">
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <div class="dashboard-content-wrapper">
    <?php if ($userRole === 'tenant'): ?>
        <nav class="vertical-sidebar">
          <div class="nav-links">
            <a href="profile.html" class="active">Profile</a>
            <a href="Rent_Pay.html">Rent & Bills</a>
            <a href="notice_board.html">Notifications</a>
            <a href="maintenance.html">Maintenance</a>
          </div>
        </nav>
        <main>
          <!-- Tenant content here -->
        </main>

    <?php elseif ($userRole === 'landlord'): ?>
        <nav class="vertical-sidebar">
          <div class="nav-links">
            <a href="profile.html" class="active">Profile</a>
            <a href="propertyInfo.php">Add Property Info</a>
            <a href="notifications.html">Notifications</a>
          </div>

          <section class="action-buttons">
            <h3>Quick Actions</h3>
            <a href="add_tenant.php" class="action-link link-tenant">+ Add Tenant</a>
            <a href="tenant_List.php" class="action-link link-tenant-list">View Tenant List</a>
            <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
            <a href="RentAndBillForm.php" class="action-link link-billing">Rent and Bills</a>
            <a href="Rent_list.php" class="action-link link-rent">View Rent List</a>
            <a href="Schedule_create.php" class="action-link link-schedule-create">Create Schedule</a>
            <a href="scheduleInfo.php" class="action-link link-schedule-details active">🗓️ Schedule Details</a>
          </section>
        </nav>

        <main>
          <header class="welcome-header">
              <h1>👋 Welcome back, <?php echo htmlspecialchars(explode(' ', $fullName)[0]); ?>!</h1>
              <p>Here's a summary of your property portfolio.</p>
          </header>
          <section class="cards-container">
            <div class="card flats">
              <div class="icon"><i class="fas fa-building"></i></div>
              <div class="number">12</div>
              <div class="label">Total Flats</div>
            </div>
            <div class="card tenants">
              <div class="icon"><i class="fas fa-users"></i></div>
              <div class="number">38</div>
              <div class="label">Total Tenants</div>
            </div>
            <div class="card income">
              <div class="icon"><i class="fas fa-dollar-sign"></i></div>
              <div class="number">$45,600</div>
              <div class="label">Monthly Income</div>
            </div>
            <div class="card maintenance">
              <div class="icon"><i class="fas fa-tools"></i></div>
              <div class="number">3</div>
              <div class="label">Pending Maintenance</div>
            </div>
          </section>
        </main>
    <?php endif; ?>
  </div> 
</body>
</html>
