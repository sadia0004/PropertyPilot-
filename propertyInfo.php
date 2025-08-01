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
// Ensure these session variables are set during landlord login
$fullName = $_SESSION['fullName'] ?? 'Landlord'; // Fallback if not set
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png"; // Fallback if no image
$userRole = $_SESSION['userRole'] ?? 'landlord'; // Assume landlord role for this page

// Initialize messages
$successMsg = "";
$errorMsg = "";
$formData = [
    'apartment_no' => '',
    'apartment_rent' => '',
    'apartment_status' => '',
    'floor_no' => '',
    'apartment_type' => '',
    'apartment_size' => ''
];

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    foreach ($formData as $key => &$value) {
        $value = trim($_POST[$key] ?? '');
    }

    // ✅ Server-side validation
    if (empty($formData['apartment_no']) || empty($formData['apartment_rent']) || empty(
        $formData['apartment_status']
    )) {
        $errorMsg = "❌ Please fill in all required fields.";
    } elseif (!is_numeric($formData['apartment_rent']) || $formData['apartment_rent'] <= 0) {
        $errorMsg = "❌ Apartment rent must be a positive number.";
    } elseif (!empty($formData['apartment_size']) && (!is_numeric($formData['apartment_size']) || $formData['apartment_size'] <= 0)) {
        $errorMsg = "❌ Apartment size must be a positive number.";
    } else {
        // ✅ Proceed with DB insert
        $stmt = $conn->prepare("INSERT INTO properties (landlord_id, apartment_no, apartment_rent, apartment_status, floor_no, apartment_type, apartment_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "isdsssi",
            $landlord_id,
            $formData['apartment_no'],
            $formData['apartment_rent'],
            $formData['apartment_status'],
            $formData['floor_no'],
            $formData['apartment_type'],
            $formData['apartment_size']
        );

        if ($stmt->execute()) {
            $successMsg = "✅ Property saved successfully.";
            $formData = array_map(fn($v) => '', $formData); // clear form
        } else {
            if ($conn->errno == 1062) {
                $errorMsg = "❌ This apartment number already exists under your account.";
            } else {
                $errorMsg = "❌ Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Property Information</title>
    <style>
        /* Global box-sizing for consistent layouts */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
          margin: 0;
          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
          background-color: #f0f4ff; /* secondary-background */
          color: #222;
          display: flex;
          flex-direction: column; /* Stack top navbar and dashboard-content-wrapper */
          height: 100vh; /* Make body fill viewport height */
          overflow: hidden; /* Crucial: Prevent body from scrolling; children will handle scroll */
        }

        /* Main Top Navigation Bar (now part of the flex flow, not fixed) */
        .main-top-navbar {
          background-color: #021934; /* primary-dark */
          color: #f0f4ff; /* text-color */
          padding: 15px 30px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
          z-index: 1001;
          flex-shrink: 0; /* Ensures it doesn't shrink */
          /* No position: fixed; here, it's part of the flex column flow */
          height: 80px; /* Explicit height */
          width: 100%; /* Spans full width */
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
          background: #ffffff; /* card-background */
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
          border: 2px solid #f0f4ff; /* text-color */
        }

        .top-right-user-info .logout-btn {
          background-color: #dc3545; /* action-maintenance */
          color: #f0f4ff; /* text-color */
          padding: 8px 15px;
          border-radius: 5px;
          text-decoration: none;
          font-weight: 600;
          transition: background-color 0.3s ease;
          white-space: nowrap;
        }

        .top-right-user-info .logout-btn:hover {
          background-color: #c0392b; /* Darker red on hover */
        }

        /* Wrapper for Vertical Sidebar and Main Content */
        .dashboard-content-wrapper {
          display: flex; /* Arranges sidebar and main content horizontally */
          flex-grow: 1; /* Makes this div fill remaining vertical space */
          height: 100%; /* Important: Makes it fill 100% of the body's remaining height */
          overflow: hidden; /* Hide overflow here, let children handle scrolling */
        }

        /* Vertical Sidebar Styles (no longer sticky, fixed within wrapper) */
        .vertical-sidebar {
          display: flex;
          flex-direction: column;
          align-items: flex-start;
          background-color: #021934; /* primary-dark */
          padding: 20px 15px;
          color: #f0f4ff; /* text-color */
          box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
          z-index: 1000;
          flex-shrink: 0; /* Prevents it from shrinking */
          width: 250px; /* Fixed width */
          height: 100%; /* Important: Fills 100% height of dashboard-content-wrapper */
          overflow-y: auto; /* Crucial: Sidebar gets its own scrollbar if its content overflows */
          overflow-x: hidden;
          /* Removed position: sticky; and top: 80px; */
          /* Removed min-height, as height: 100% is more precise here */
        }

        .vertical-sidebar::-webkit-scrollbar {
          width: 8px; /* Width of the scrollbar */
        }

        .vertical-sidebar::-webkit-scrollbar-track {
          background: #021934; /* primary-dark */
        }

        .vertical-sidebar::-webkit-scrollbar-thumb {
          background-color: #2c5dbd; /* primary-accent */
          border-radius: 10px; /* Rounded scrollbar */
          border: 2px solid #021934; /* primary-dark */
        }

        /* Sidebar Navigation Links */
        .vertical-sidebar .nav-links a {
          color: #f0f4ff; /* text-color */
          text-decoration: none;
          width: 100%;
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
          background-color: #2c5dbd; /* primary-accent */
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
          background-color: #4CAF50; /* primary-highlight */
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
            color: #f0f4ff; /* text-color */
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
          color: #f0f4ff; /* text-color */
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
        .vertical-sidebar .link-tenant { background-color: #28a745; } /* action-add */
        .vertical-sidebar .link-tenant:hover { background-color: #218838; }

        .vertical-sidebar .link-billing { background-color: #ffc107; color: #021934; } /* action-billing, primary-dark for text */
        .vertical-sidebar .link-billing:hover { background-color: #e0a800; }

        .vertical-sidebar .link-docs { background-color: #6c757d; } /* action-list */
        .vertical-sidebar .link-docs:hover { background-color: #5a6268; }

        .vertical-sidebar .link-maintenance { background-color: #dc3545; } /* action-maintenance */
        .vertical-sidebar .link-maintenance:hover { background-color: #c82333; }


        /* Main content area for the form */
        .page-main-content {
            flex-grow: 1; /* Allow this content area to expand horizontally */
            padding: 30px; /* Add padding consistent with dashboard */
            display: flex; /* Use flex to center the form */
            justify-content: center; /* Center form horizontally */
            align-items: flex-start; /* Align form to the top */
            height: 100%; /* Crucial: Fills 100% height of dashboard-content-wrapper */
            overflow-y: auto; /* Crucial: Main content panel gets its own scrollbar */
            overflow-x: hidden;
        }
        .form-container {
            max-width: 600px;
            background: #ffffff; /* card-background */
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            width: 100%; /* Ensure it takes full width within its max-width */
        }
        
        /* --- FORM HORIZONTAL STYLES --- */
        .form-container form {
            display: flex;
            flex-wrap: wrap; /* Allow items to wrap to the next line */
            gap: 20px; /* Space between flex items (fields) */
            justify-content: space-between; /* Distribute space between items */
        }

        .form-field-group {
            flex-basis: calc(50% - 10px); /* For two columns: 50% minus half of the gap */
            display: flex;
            flex-direction: column; /* Stack label and input within the group */
        }

        /* Make specific fields full width if desired (e.g., if content is long) */
        .form-field-group.full-width {
            flex-basis: 100%;
        }

        .form-field-group label {
            margin-bottom: 5px; /* Small space between label and input */
            font-weight: 600; /* Make labels slightly bolder */
            color: #333;
        }

        .form-field-group input,
        .form-field-group select {
            width: 100%; /* Take full width of its group */
            padding: 10px;
            margin-top: 0; /* Remove default margin-top from input/select */
            margin-bottom: 0; /* Remove default margin-bottom */
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box; /* Include padding/border in width calculation */
        }

        /* Adjust button placement */
        .form-container button[type="submit"] {
            width: auto; /* Allow button to size itself */
            margin-top: 20px; /* Space from fields above */
            display: block; /* Make it a block element to take margin auto */
            margin-left: auto;
            margin-right: auto;
            flex-basis: 100%; /* Make button take full width of form row */
        }
        /* --- END FORM HORIZONTAL STYLES --- */


        input, select { /* Keep fallback for specific elements not wrapped in form-field-group */
            /* These styles are mostly overridden by .form-field-group input/select */
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        button { /* Keep fallback for buttons */
            background: #007BFF; /* Primary blue for form buttons */
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s ease; /* Smooth transition */
        }
        button:hover {
            background: #0056b3; /* Darker blue on hover */
        }
        .message {
            margin-bottom: 15px; /* Added margin */
            padding: 10px;
            border-radius: 5px;
            background-color: #d4edda; /* Light green background */
            color: #155724; /* Dark green text */
            border: 1px solid #c3e6cb;
            font-weight: bold;
            text-align: center;
        }
        .error {
            margin-bottom: 15px; /* Added margin */
            padding: 10px;
            border-radius: 5px;
            background-color: #f8d7da; /* Light red background */
            color: #721c24; /* Dark red text */
            border: 1px solid #f5c6cb;
            font-weight: bold;
            text-align: center;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px; /* Added margin */
        }

        /* Responsive design for smaller screens */
        @media (max-width: 768px) {
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
            }

            .vertical-sidebar {
                position: relative; /* No sticky on mobile */
                top: auto; /* Remove top offset */
                height: auto; /* Auto height on mobile */
                width: 100%; /* Take full width */
                flex-direction: row; /* Layout sidebar items horizontally */
                justify-content: space-around; /* Distribute items */
                padding: 10px 0;
                box-shadow: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
                min-height: auto; /* Reset min-height for mobile */
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
            .vertical-sidebar a::after {
                width: 100%;
                height: 3px;
                transform: translateX(-100%);
            }
            .vertical-sidebar a:hover::after,
            .vertical-sidebar a.active::after {
                transform: translateX(0);
            }

            /* Hide action buttons section on mobile as it becomes a top nav */
            .action-buttons {
                display: none;
            }

            .page-main-content {
                padding: 15px;
                height: auto; /* Auto height on mobile */
                overflow-y: visible; /* Let content flow out and body scroll */
            }
            .form-container {
                padding: 15px;
            }
            /* Form fields stack vertically on smaller screens */
            .form-field-group {
                flex-basis: 100%;
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
        <nav class="vertical-sidebar">
            <div class="nav-links">
                
                <a href="landlord_dashboard.php" class="active">Dashboard</a>
                <a href="profile.html">Profile</a>
                <a href="notifications.html">Notifications</a>
            </div>

            <section class="action-buttons">
                <h3>Quick Actions</h3>
                <a href="add_tenant.html" class="action-link link-tenant">+ Add Tenant</a>
                <a href="billing.html" class="action-link link-billing">Rent and Bills</a>
                <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
                <a href="maintenance.html" class="action-link link-maintenance">Maintenance Requests</a>
            </section>
        </nav>

        <div class="page-main-content">
            <div class="form-container">
                <h2>Add Property Information</h2>

                <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
                <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

                <form method="POST" action="">
                    <div class="form-field-group">
                        <label for="apartment_no">Apartment Number *</label>
                        <input type="text" id="apartment_no" name="apartment_no" required value="<?php echo htmlspecialchars($formData['apartment_no']); ?>">
                    </div>

                    <div class="form-field-group">
                        <label for="apartment_rent">Apartment Rent (BDT) *</label>
                        <input type="number" step="0.01" min="1" id="apartment_rent" name="apartment_rent" required value="<?php echo htmlspecialchars($formData['apartment_rent']); ?>">
                    </div>

                    <div class="form-field-group">
                        <label for="apartment_status">Status *</label>
                        <select id="apartment_status" name="apartment_status" required>
                            <option value="">-- Select Status --</option>
                            <option value="Vacant" <?php if ($formData['apartment_status'] === 'Vacant') echo 'selected'; ?>>Vacant</option>
                            <option value="Occupied" <?php if ($formData['apartment_status'] === 'Occupied') echo 'selected'; ?>>Occupied</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label for="floor_no">Floor Number</label>
                        <input type="number" id="floor_no" name="floor_no" value="<?php echo htmlspecialchars($formData['floor_no']); ?>">
                    </div>

                    <div class="form-field-group full-width"> <label for="apartment_type">Apartment Type</label>
                        <input type="text" id="apartment_type" name="apartment_type" placeholder="e.g., 2BHK, Studio" value="<?php echo htmlspecialchars($formData['apartment_type']); ?>">
                    </div>

                    <div class="form-field-group">
                        <label for="apartment_size">Apartment Size (sq ft)</label>
                        <input type="number" id="apartment_size" name="apartment_size" min="1" value="<?php echo htmlspecialchars($formData['apartment_size']); ?>">
                    </div>
                    
                    <button type="submit">Save Property</button>
                </form>
            </div>
        </div>
    </div> </body>
</html>