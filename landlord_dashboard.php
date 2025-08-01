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
$primaryHighlight = '#4CAF50'; // Green for active line/success (can be adjusted)
$textColor = '#f0f4ff'; // Light text color for dark backgrounds
$secondaryBackground = '#f0f4ff'; // Main body background
$cardBackground = '#ffffff'; // Card background

// Action button colors - slightly desaturated for professionalism
$actionAdd = '#28a745'; // Green for 'Add Tenant'
$actionBilling = '#ffc107'; // Yellow/Orange for 'Rent & Bills' (adjust to make less vibrant)
$actionList = '#6c757d'; // Grey for 'Apartment List'
$actionMaintenance = '#dc3545'; // Red for 'Maintenance Requests'

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>PropertyPilot Dashboard</title>

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
      flex-direction: column; /* Stack top navbar and main content wrapper vertically */
      height: 100vh; /* Crucial: Make body fill viewport height */
      overflow: hidden; /* Crucial: Prevent body from showing scrollbar; children will manage */
    }

    /* Main Top Navigation Bar (now truly fixed) */
    .main-top-navbar {
      background-color: <?php echo $primaryDark; ?>; /* Consistent brand color */
      color: <?php echo $textColor; ?>;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between; /* Space out left and right content */
      align-items: center;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      z-index: 1001; /* Ensure it's always on top */
      flex-shrink: 0; /* Prevents it from shrinking */
      
      /* Fixed positioning to ensure it's always visible */
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 80px; /* Explicit height, for calculation below */
      box-sizing: border-box; /* Include padding in height */
    }

    /* Top Nav: Left Side (Logo + Name) */
    .main-top-navbar .brand {
      display: flex;
      align-items: center;
      font-weight: 700;
      font-size: 22px;
      white-space: nowrap;
      user-select: none;
      letter-spacing: 0.5px;
    }

    .main-top-navbar .brand img {
      height: 50px;
      width: 50px;
      margin-right: 10px;
      border-radius: 50%;
      object-fit: contain;
      background: <?php echo $cardBackground; ?>;
      padding: 3px;
      box-shadow: 0 0 8px rgba(255, 255, 255, 0.2);
    }

    /* Top Nav: Right Side (Welcome, User Info, Logout) */
    .top-right-user-info {
      display: flex;
      align-items: center;
      gap: 20px; /* Space between elements */
    }

    .top-right-user-info .welcome-greeting {
      font-size: 1.1em;
      font-weight: 500;
      white-space: nowrap;
    }

    .top-right-user-info .user-photo {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid <?php echo $textColor; ?>;
    }

    .top-right-user-info .logout-btn {
      background-color: <?php echo $actionMaintenance; ?>; /* Red for logout */
      color: <?php echo $textColor; ?>;
      padding: 8px 15px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: 600;
      transition: background-color 0.3s ease;
      white-space: nowrap;
    }

    .top-right-user-info .logout-btn:hover {
      background-color: #c0392b; 
    }

    /* Wrapper for Vertical Sidebar and Main Content */
    .dashboard-content-wrapper {
      display: flex; /* Arranges sidebar and main content horizontally */
      flex-grow: 1; /* Makes this div fill remaining vertical space below fixed header */
      
      /* Crucial: Position this wrapper right below the fixed header */
      margin-top: 80px; /* Offset by the height of the main-top-navbar */
      
      /* Crucial: Make it fill the exact remaining viewport height */
      height: calc(100vh - 80px); 
      overflow: hidden; /* Crucial: Hide its own scrollbar, children will handle */
    }

    /* Vertical Sidebar Styles (now fills remaining height and scrolls internally) */
    .vertical-sidebar {
      display: flex;
      flex-direction: column; /* Stack items vertically */
      align-items: flex-start; /* Align items to the left */
      background-color: <?php echo $primaryDark; ?>; /* Consistent brand color */
      padding: 20px 15px;
      color: <?php echo $textColor; ?>;
      box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      flex-shrink: 0; /* Prevents it from shrinking */
      width: 250px; /* Fixed width */
      
      /* Fills 100% height of dashboard-content-wrapper */
      height: 100%; 
      /* Crucial: Sidebar gets its own scrollbar if its content overflows */
      overflow-y: auto; 
      overflow-x: hidden;
      
      /* Removed position: sticky and top/min-height */
    }

    .vertical-sidebar::-webkit-scrollbar {
      width: 8px; /* Width of the scrollbar */
    }

    .vertical-sidebar::-webkit-scrollbar-track {
      background: <?php echo $primaryDark; ?>; /* Track color */
    }

    .vertical-sidebar::-webkit-scrollbar-thumb {
      background-color: <?php echo $primaryAccent; ?>; /* Scrollbar color */
      border-radius: 10px; /* Rounded scrollbar */
      border: 2px solid <?php echo $primaryDark; ?>; /* Padding around thumb */
    }

    /* Sidebar Navigation Links */
    .vertical-sidebar .nav-links a {
      color: <?php echo $textColor; ?>;
      text-decoration: none;
      width:100% ; /* Full width minus padding */
      text-align: left; /* Align text to the left */
      padding: 12px 15px; /* Adjust padding for vertical links */
      margin: 8px 0; /* Vertical margin between links */
      font-weight: 600;
      font-size: 16px;
      border-radius: 8px;
      transition: background-color 0.3s ease, transform 0.2s ease;
      position: relative;
      overflow: hidden;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .vertical-sidebar .nav-links a:hover,
    .vertical-sidebar .nav-links a.active {
      background-color: <?php echo $primaryAccent; ?>;
      transform: none;
    }

    /* Vertical underline effect on hover/active for nav links */
    .vertical-sidebar .nav-links a::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 4px; /* Thickness of the vertical line */
      height: 100%; /* Line spans full height of link */
      background-color: <?php echo $primaryHighlight; ?>; /* Accent color */
      transform: translateX(-100%);
      transition: transform 0.3s ease-out;
    }

    .vertical-sidebar .nav-links a:hover::after,
    .vertical-sidebar .nav-links a.active::after {
      transform: translateX(0);
    }

    /* Sidebar Action Buttons (styled as prominent links) */
    .vertical-sidebar .action-buttons {
      margin-top: 30px; /* Space from navigation links */
      margin-bottom: 20px;
      width: 100%;
      display: flex;
      flex-direction: column;
      gap: 12px; /* Slightly reduced gap between action buttons */
      align-items: center;
      border-top: 1px solid rgba(255, 255, 255, 0.1); /* Separator line */
      padding-top: 20px;
    }

    .vertical-sidebar .action-buttons h3 {
        color: <?php echo $textColor; ?>;
        font-size: 1.1em;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
    }

    .vertical-sidebar .action-link {
      width: calc(100% - 30px);
      padding: 12px 15px; /* Slightly less padding to feel more integrated */
      border-radius: 8px; /* Consistent rounding with nav links */
      color: <?php echo $textColor; ?>;
      font-weight: 600;
      font-size: 15px; /* Slightly smaller font for secondary importance */
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: flex-start; /* Align text to start for a list-like feel */
      gap: 10px;
      text-decoration: none;
      transition: background-color 0.3s ease, transform 0.2s ease;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Lighter shadow for these buttons */
    }

    .vertical-sidebar .action-link:hover {
        transform: translateX(5px); /* Slide effect on hover */
        background-color: rgba(255, 255, 255, 0.1); /* Subtle hover for these */
    }

    /* Specific action link colors (backgrounds) */
    .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
    .vertical-sidebar .link-tenant:hover { background-color: #218838; }

    .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; color: <?php echo $primaryDark; ?>; } /* Dark text for light background */
    .vertical-sidebar .link-billing:hover { background-color: #e0a800; }

    .vertical-sidebar .link-docs { background-color: <?php echo $actionList; ?>; }
    .vertical-sidebar .link-docs:hover { background-color: #5a6268; }

    .vertical-sidebar .link-schedule { background-color: <?php echo $actionMaintenance; ?>; }
    .vertical-sidebar .link-schedule:hover { background-color: #c82333; }


    /* Main content area */
    main {
      flex-grow: 1;
      padding: 30px;
      /* Crucial: Fills 100% height of dashboard-content-wrapper */
      height: 100%; 
      /* Crucial: Main content panel gets its own scrollbar */
      overflow-y: auto; 
      overflow-x: hidden;
    }

    /* Cards container */
    .cards-container {
      margin: 50px auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 25px;
      max-width: 960px;
      padding: 0 20px;
    }

    /* Individual card styles */
    .card {
      background: <?php echo $cardBackground; ?>;
      padding: 30px 25px;
      border-radius: 15px;
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      transition: box-shadow 0.3s ease;
    }

    .card:hover {
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
    }

    .card .icon {
      font-size: 42px;
      color: <?php echo $primaryAccent; ?>;
      margin-bottom: 15px;
    }

    .card .number {
      font-size: 38px;
      font-weight: 700;
      color: #34495e;
      margin-bottom: 10px;
    }

    .card .label {
      font-size: 16px;
      font-weight: 600;
      color: #7f8c8d;
      letter-spacing: 0.8px;
      text-transform: uppercase;
    }

    /* Welcome Container for main content area */
    .welcome-container {
        text-align: center;
        margin-bottom: 40px;
    }

    .welcome-container .welcome-message {
        font-size: 2.2em;
        font-weight: 700;
        color: #34495e;
        margin-bottom: 15px;
    }

    .property-name {
        font-size: 1.1em;
        color: #555;
        margin-bottom: 8px;
    }

    .important-alerts {
        font-size: 1.2em;
        font-weight: 600;
        color: <?php echo $actionMaintenance; ?>; /* Use red for alerts */
        background-color: #ffe0e0;
        padding: 10px 20px;
        border-radius: 8px;
        display: inline-block;
        margin-top: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }


    /* Responsive design for smaller screens */
    @media (max-width: 768px) {
      body {
        overflow-y: auto; /* Re-enable body scroll for mobile */
      }
      .main-top-navbar {
        height: auto; /* Allow height to adjust on mobile */
        position: relative; /* No fixed position on mobile */
        padding: 10px 15px;
        flex-wrap: wrap;
      }
      .main-top-navbar .brand {
        font-size: 18px;
      }
      .main-top-navbar .brand img {
        height: 35px;
        width: 35px;
      }
      .top-right-user-info {
        width: 100%;
        justify-content: center;
        margin-top: 10px;
        gap: 10px;
      }
      .top-right-user-info .welcome-greeting {
        display: none;
      }
      .top-right-user-info .user-photo {
        width: 30px;
        height: 30px;
      }
      .top-right-user-info .logout-btn {
        padding: 6px 12px;
        font-size: 14px;
      }

      .dashboard-content-wrapper {
        flex-direction: column; /* Stack sidebar and main content vertically */
        height: auto; /* Auto height on mobile */
        overflow: visible; /* Let children expand and body scroll */
        margin-top: 0; /* Remove margin-top on mobile */
      }

      .vertical-sidebar {
        position: relative; /* No fixed/sticky on mobile */
        top: auto; /* Remove top offset */
        height: auto; /* Auto height on mobile */
        width: 100%; /* Take full width */
        flex-direction: row; /* Layout sidebar items horizontally */
        justify-content: space-around; /* Distribute items */
        padding: 10px 0;
        box-shadow: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        /* Ensure min-height is reset for mobile */
        min-height: auto;
        overflow-y: hidden; /* Prevent internal scroll on mobile sidebar */
      }
      .vertical-sidebar .nav-links {
          display: flex; /* Make nav links a flex container for horizontal layout */
          width: 100%;
          justify-content: space-around;
          flex-wrap: wrap;
      }
      .vertical-sidebar .nav-links a {
        padding: 8px 10px;
        margin: 0 5px;
        width: auto;
        font-size: 14px;
        text-align: center;
      }
      .vertical-sidebar .nav-links a::after {
        width: 100%;
        height: 3px;
        transform: translateX(-100%);
      }
      .vertical-sidebar .nav-links a:hover::after,
      .vertical-sidebar .nav-links a.active::after {
        transform: translateX(0);
      }

      /* Hide action buttons section on mobile to avoid clutter */
      .action-buttons {
        display: none;
      }

      main {
        padding: 15px;
        height: auto; /* Auto height on mobile */
        overflow-y: visible; /* Let content flow out and body scroll */
      }
      .cards-container {
        grid-template-columns: repeat(1, 1fr);
        padding: 0 10px;
      }
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
      <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></span>
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
          <section class="welcome-container">
            <div class="welcome-message" id="welcomeMessage">Your Dashboard Overview</div>
            <div class="property-name" id="apartmentInfo">🏠 Apartment: -</div>
            <div class="property-name" id="professionInfo">🧑‍💼 Profession: -</div>
            <div class="property-name" id="rentDateInfo">📅 Rent Date: -</div>
            <div class="important-alerts" id="rentAlert">🔔 Your rent is due in - days</div>
          </section>

          <section class="cards-container">
            <div class="card">
              <div class="icon">💰</div>
              <div class="number" id="rentDue">৳0</div>
              <div class="label">Rent Due</div>
            </div>

            <div class="card">
              <div class="icon">📄</div>
              <div class="number" id="pendingBills">0</div>
              <div class="label">Pending Bills</div>
            </div>

            <div class="card">
              <div class="icon">🔔</div>
              <div class="number" id="unreadNotifications">0</div>
              <div class="label">Unread Notifications</div>
            </div>

            <div class="card">
              <div class="icon">🛠️</div>
              <div class="number" id="openMaintenance"></div>
              <div class="label">Open Maintenance</div>
            </div>
          </section>
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
            <a href="billing.html" class="action-link link-billing">Rent and Bills</a>
            <a href="apartmentList.php" class="action-link link-docs ">Apartment List</a>
            <a href="maintenance.html" class="action-link link-schedule">Meeting Schedule</a>
          </section>
        </nav>

        <main>
          <section class="cards-container">
            <div class="card">
              <div class="icon">🏢</div>
              <div class="number">12</div>
              <div class="label">Total Flats</div>
            </div>

            <div class="card">
              <div class="icon">👥</div>
              <div class="number">38</div>
              <div class="label">Total Tenants</div>
            </div>

            <div class="card">
              <div class="icon">💵</div>
              <div class="number">$45,600</div>
              <div class="label">Monthly Income</div>
            </div>

            <div class="card">
              <div class="icon">🚧</div>
              <div class="number">3</div>
              <div class="label">Pending Maintenance</div>
            </div>
          </section>
        </main>

    <?php endif; ?>
  </div> </body>
</html>