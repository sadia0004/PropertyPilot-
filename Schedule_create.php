<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check the role of the user
$userRole = $_SESSION['userRole'] ?? 'tenant';
if ($userRole !== 'landlord') {
    die("Access Denied: This page is for landlords only.");
}
$landlord_id = $_SESSION['user_id'];


// Retrieve user data from session
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// Define the consistent brand color palette
$primaryDark = '#021934';
$primaryAccent = '#2c5dbd';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';
$cardBackground = '#ffffff';

// Action button colors - Professional and distinct palette
$actionAdd = '#28a745';      // Green for 'Add Tenant'
$actionBilling = '#ffc107';   // Yellow for 'Rent & Bills'
$actionViewRentList = '#17a2b8';  // Teal for 'View Rent List'
$actionViewTenantList = '#6f42c1'; // Purple for 'View Tenant List'
$actionApartmentList = '#6c757d';// Grey for 'Apartment List'
$actionScheduleCreate = '#832d31ff';   // Magenta for 'Create Schedule'
$actionScheduleDetails = '#fd7e14'; // Orange for 'Schedule Details'

// Initialize messages and form data
$successMsg = "";
$errorMsg = "";
$formData = [
    'tenant_ids' => [],
    'meetingType' => 'In-Person',
    'meeting_link' => '',
    'EventDescription' => '',
    'date' => '',
    'time' => ''
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

// ✅ Fetch tenants associated with the logged-in landlord
$tenants = [];
$tenantQuery = "SELECT u.id, u.fullName FROM users u JOIN addtenants a ON u.id = a.tenant_id WHERE a.landlord_id = ?";
$stmt_tenants = $conn->prepare($tenantQuery);
$stmt_tenants->bind_param("i", $landlord_id);
$stmt_tenants->execute();
$result = $stmt_tenants->get_result();
while ($row = $result->fetch_assoc()) {
    $tenants[] = $row;
}
$stmt_tenants->close();


// ✅ Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Repopulate form data from POST
    $formData['tenant_ids'] = $_POST['tenant_ids'] ?? [];
    $formData['meetingType'] = trim($_POST['meetingType'] ?? 'In-Person');
    $formData['meeting_link'] = trim($_POST['meeting_link'] ?? '');
    $formData['EventDescription'] = trim($_POST['EventDescription'] ?? '');
    $formData['date'] = trim($_POST['date'] ?? '');
    $formData['time'] = trim($_POST['time'] ?? '');

    // ✅ Server-side validation
    if (empty($formData['tenant_ids']) || empty($formData['date']) || empty($formData['time'])) {
        $errorMsg = "❌ Please select at least one tenant and fill in the date and time.";
    } elseif ($formData['meetingType'] === 'Online' && empty($formData['meeting_link'])) {
        $errorMsg = "❌ Please provide a meeting link for online meetings.";
    } elseif ($formData['meetingType'] === 'Online' && !filter_var($formData['meeting_link'], FILTER_VALIDATE_URL)) {
        $errorMsg = "❌ The provided meeting link is not a valid URL.";
    } else {
        $stmt = $conn->prepare("INSERT INTO meeting_schedule (landlord_id, tenant_id, name, meetingType, EventDescription, date, time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $tenant_name_map = array_column($tenants, 'fullName', 'id');
        
        $description = $formData['EventDescription'];
        if ($formData['meetingType'] === 'Online') {
            $description = "Meeting Link: " . $formData['meeting_link'] . "\n\n" . $description;
        }

        $isSuccessful = true;
        foreach ($formData['tenant_ids'] as $tenant_id) {
            $name = $tenant_name_map[$tenant_id] ?? 'Meeting';

            $stmt->bind_param(
                "iisssss",
                $landlord_id,
                $tenant_id,
                $name,
                $formData['meetingType'],
                $description,
                $formData['date'],
                $formData['time']
            );

            if (!$stmt->execute()) {
                $errorMsg = "❌ Error scheduling meeting: " . $stmt->error;
                $isSuccessful = false;
                break; 
            }
        }

        if ($isSuccessful) {
            $successMsg = "✅ Meeting scheduled successfully for all selected tenants.";
            $formData = array_map(fn($v) => is_array($v) ? [] : '', $formData);
            $formData['meetingType'] = 'In-Person';
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Schedule Meeting - PropertyPilot</title>

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
      background-color: #dc3545;
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
      overflow-y: auto; 
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
      gap: 7px;
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
      padding: 9px 15px;
      border-radius: 8px;
      color: <?php echo $textColor; ?>;
      font-weight: 600;
      font-size: 14px;
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
    
    .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
    .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; color: <?php echo $primaryDark; ?>; }
    .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
    .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
    .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
    .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
    .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }

    main {
      flex-grow: 1;
      padding: 30px;
      height: 100%; 
      overflow-y: auto; 
    }

    .form-container {
        max-width: 700px;
        margin: 0 auto;
        background: <?php echo $cardBackground; ?>;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        width: 100%;
    }
    
    .form-container form { display: flex; flex-wrap: wrap; gap: 20px; }
    .form-field-group { flex-basis: calc(50% - 10px); display: flex; flex-direction: column; }
    .form-field-group.full-width { flex-basis: 100%; }
    .form-field-group label { margin-bottom: 8px; font-weight: 600; color: #333; }
    .form-field-group input, .form-field-group select, .form-field-group textarea {
      width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;
    }
    .radio-group { display: flex; gap: 20px; align-items: center; padding-top: 10px; }
    .radio-group input[type="radio"] { width: auto; }
    .radio-group label { font-weight: normal; margin-bottom: 0; }
    #meetingLinkContainer { display: none; }
    
    .tenant-selection-area {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 15px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .tenant-item { display: inline-block; }
    .tenant-label {
        display: inline-block; padding: 8px 16px; border: 1px solid #ddd;
        border-radius: 25px; cursor: pointer; transition: all 0.2s ease-in-out;
        font-weight: 500; background-color: #f1f3f5;
    }
    .tenant-label:hover { border-color: <?php echo $actionScheduleCreate; ?>; }
    .tenant-checkbox { display: none; }
    .tenant-checkbox:checked + .tenant-label {
        background-color: <?php echo $actionScheduleCreate; ?>; color: white; border-color: <?php echo $actionScheduleCreate; ?>;
    }
    #selectAllTenants + label { background-color: #6c757d; color: white; border-color: #6c757d; }
    #selectAllTenants:checked + label { background-color: #28a745; border-color: #28a745; }

    .form-container button[type="submit"] {
      flex-basis: 100%; margin-top: 20px; padding: 15px; font-size: 18px;
      background: <?php echo $actionScheduleCreate; ?>; color: white; border: none; border-radius: 6px;
      cursor: pointer; transition: background 0.3s ease;
    }
    .form-container button[type="submit"]:hover { background: #5f0431ff; }
    .message, .error {
      flex-basis: 100%; margin-bottom: 15px; padding: 12px; border-radius: 5px;
      font-weight: bold; text-align: center;
    }
    .message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    h2 { text-align: center; color: #333; margin-bottom: 25px; }

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
        <a href="add_tenant.php" class="action-link link-tenant">+ Add Tenant</a>
        <a href="tenant_List.php" class="action-link link-tenant-list">View Tenant List</a>
        <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
        <a href="RentAndBillForm.php" class="action-link link-billing">Rent and Bills</a>
        <a href="Rent_list.php" class="action-link link-rent">View Rent List</a>
        <a href="Schedule_create.php" class="action-link link-schedule-create active">Create Schedule</a>
        <a href="scheduleInfo.php" class="action-link link-schedule-details">🗓️ Schedule Details</a>
      </section>
    </nav>
    <main>
        <div class="form-container">
            <h2>Schedule a New Meeting</h2>

            <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
            <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

            <form method="POST" action="">
                <div class="form-field-group full-width">
                    <label>Select Tenant(s) *</label>
                    <div class="tenant-selection-area">
                        <?php if (empty($tenants)): ?>
                            <p>You have no tenants to schedule a meeting with.</p>
                        <?php else: ?>
                            <div class="tenant-item">
                                <input type="checkbox" id="selectAllTenants" class="tenant-checkbox">
                                <label for="selectAllTenants" class="tenant-label"><strong>Select All</strong></label>
                            </div>
                            <?php foreach ($tenants as $tenant): ?>
                                <div class="tenant-item">
                                    <input type="checkbox" name="tenant_ids[]" id="tenant_<?php echo $tenant['id']; ?>" value="<?php echo $tenant['id']; ?>" class="tenant-checkbox" <?php echo in_array($tenant['id'], $formData['tenant_ids']) ? 'checked' : ''; ?>>
                                    <label for="tenant_<?php echo $tenant['id']; ?>" class="tenant-label"><?php echo htmlspecialchars($tenant['fullName']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-field-group">
                    <label for="date">Date *</label>
                    <input type="date" id="date" name="date" required value="<?php echo htmlspecialchars($formData['date']); ?>">
                </div>

                <div class="form-field-group">
                    <label for="time">Time *</label>
                    <input type="time" id="time" name="time" required value="<?php echo htmlspecialchars($formData['time']); ?>">
                </div>

                <div class="form-field-group full-width">
                    <label>Meeting Type *</label>
                    <div class="radio-group">
                        <input type="radio" id="typeInPerson" name="meetingType" value="In-Person" <?php if ($formData['meetingType'] === 'In-Person') echo 'checked'; ?>>
                        <label for="typeInPerson">In-Person</label>
                        <input type="radio" id="typeOnline" name="meetingType" value="Online" <?php if ($formData['meetingType'] === 'Online') echo 'checked'; ?>>
                        <label for="typeOnline">Online</label>
                    </div>
                </div>

                <div class="form-field-group full-width" id="meetingLinkContainer">
                    <label for="meeting_link">Meeting Link *</label>
                    <input type="url" id="meeting_link" name="meeting_link" placeholder="A new link will be generated here" value="<?php echo htmlspecialchars($formData['meeting_link']); ?>">
                </div>

                <div class="form-field-group full-width">
                    <label for="EventDescription">Event Description</label>
                    <textarea id="EventDescription" name="EventDescription" rows="4"><?php echo htmlspecialchars($formData['EventDescription']); ?></textarea>
                </div>
                
                <button type="submit" <?php if (empty($tenants)) echo 'disabled'; ?>>Create Schedule</button>
            </form>
        </div>
    </main>
  </div> 

  <script>
      document.addEventListener('DOMContentLoaded', function() {
          const meetingTypeRadios = document.querySelectorAll('input[name="meetingType"]');
          const meetingLinkContainer = document.getElementById('meetingLinkContainer');
          const meetingLinkInput = document.getElementById('meeting_link');

          function toggleMeetingLink() {
              if (document.getElementById('typeOnline').checked) {
                  meetingLinkContainer.style.display = 'block';
                  meetingLinkInput.required = true;
                  if(meetingLinkInput.value === '') {
                      const randomString = Math.random().toString(36).substring(2, 12);
                      meetingLinkInput.value = `https://propertymeet.io/${randomString}`;
                  }
              } else {
                  meetingLinkContainer.style.display = 'none';
                  meetingLinkInput.required = false;
                  meetingLinkInput.value = '';
              }
          }
          toggleMeetingLink(); 
          meetingTypeRadios.forEach(radio => radio.addEventListener('change', toggleMeetingLink));

          const selectAllCheckbox = document.getElementById('selectAllTenants');
          const tenantCheckboxes = document.querySelectorAll('input[name="tenant_ids[]"]');

          if(selectAllCheckbox) {
              selectAllCheckbox.addEventListener('change', function() {
                  tenantCheckboxes.forEach(checkbox => {
                      checkbox.checked = this.checked;
                  });
              });
          }

          tenantCheckboxes.forEach(checkbox => {
              checkbox.addEventListener('change', function() {
                  if (!this.checked) {
                      selectAllCheckbox.checked = false;
                  } else {
                      const allChecked = Array.from(tenantCheckboxes).every(c => c.checked);
                      if (allChecked) {
                          selectAllCheckbox.checked = true;
                      }
                  }
              });
          });
      });
  </script>
</body>
</html>
