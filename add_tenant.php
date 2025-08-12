<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check to match your dashboard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check the role of the user
$userRole = $_SESSION['userRole'] ?? 'tenant';
if ($userRole !== 'landlord') {
    die("Access Denied: This page is for landlords only.");
}
$landlord_id = $_SESSION['user_id']; // Use 'user_id' for consistency

// Retrieve user data from session
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// Define the consistent brand color palette
$primaryDark = '#021934';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';

// Action button colors
$actionAdd = '#28a745';
$actionBilling = '#ffc107';
$actionViewRentList = '#17a2b8';
$actionViewTenantList = '#6f42c1';
$actionApartmentList = '#6c757d';
$actionScheduleCreate = '#e83e8c';
$actionScheduleDetails = '#fd7e14';

// Initialize messages
$successMsg = "";
$errorMsg = "";

// Variables to hold form values for repopulation on error
$tenantName_val = '';
$apartmentNo_val = '';
$monthlyRent_val = '';
$familyMembers_val = '';
$emergencyContact_val = '';
$additionalInfo_val = '';

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ CORRECTED: Fetch ALL apartments for the dropdown
$apartments = [];
$query = "SELECT apartment_no FROM properties WHERE landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $apartments[] = $row['apartment_no'];
}
$stmt->close();

// Fetch tenant details when apartment is selected via GET
if (isset($_GET['apartment_no'])) {
    $apartmentNo_val = $_GET['apartment_no'];

    $tenantQuery = "SELECT t.tenant_id, u.fullName, t.family_members, t.emergency_contact
                    FROM tenants t
                    JOIN users u ON t.tenant_id = u.id
                    WHERE t.apartment_no = ?";
    $tenantStmt = $conn->prepare($tenantQuery);
    $tenantStmt->bind_param("s", $apartmentNo_val);
    $tenantStmt->execute();
    $tenantResult = $tenantStmt->get_result();

    if ($tenantResult->num_rows > 0) {
        $tenantDetails = $tenantResult->fetch_assoc();
        $tenantName_val = $tenantDetails['fullName'];
        $familyMembers_val = $tenantDetails['family_members'];
        $emergencyContact_val = $tenantDetails['emergency_contact'];
    }
    $tenantStmt->close();

    $propertyQuery = "SELECT apartment_rent FROM properties WHERE apartment_no = ?";
    $propertyStmt = $conn->prepare($propertyQuery);
    $propertyStmt->bind_param("s", $apartmentNo_val);
    $propertyStmt->execute();
    $propertyResult = $propertyStmt->get_result();

    if ($propertyResult->num_rows > 0) {
        $apartmentRent = $propertyResult->fetch_assoc()['apartment_rent'];
        $monthlyRent_val = $apartmentRent;
    }
    $propertyStmt->close();
}

// Handle form submission to add a new tenant
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenantName = trim($_POST['tenantName']);
    $apartmentNo = trim($_POST['apartmentNo']);
    $monthlyRent = filter_var($_POST['rent'], FILTER_VALIDATE_FLOAT);
    $familyMembers = filter_var($_POST['familyMembers'], FILTER_VALIDATE_INT);
    $emergencyContact = trim($_POST['emergencyContact']);
    $additionalInfo = trim($_POST['moreInfo']);

    $tenantName_val = $tenantName;
    $apartmentNo_val = $apartmentNo;
    $monthlyRent_val = $_POST['rent'];
    $familyMembers_val = $_POST['familyMembers'];
    $emergencyContact_val = $emergencyContact;
    $additionalInfo_val = $additionalInfo;

    if (empty($tenantName) || empty($apartmentNo) || $monthlyRent === false || $familyMembers === false || empty($emergencyContact)) {
        $errorMsg = "Please fill all required fields correctly.";
    } else {
        $getTenantIdQuery = "SELECT id FROM users WHERE fullName = ? AND userRole = 'tenant'";
        $getTenantIdStmt = $conn->prepare($getTenantIdQuery);
        $getTenantIdStmt->bind_param("s", $tenantName);
        $getTenantIdStmt->execute();
        $getTenantIdResult = $getTenantIdStmt->get_result();

        if ($getTenantIdResult->num_rows > 0) {
            $tenantData = $getTenantIdResult->fetch_assoc();
            $tenant_id_to_insert = $tenantData['id'];

            $checkAddTenantDuplicateStmt = $conn->prepare("SELECT COUNT(*) FROM addtenants WHERE landlord_id = ? AND apartment_no = ?");
            $checkAddTenantDuplicateStmt->bind_param("is", $landlord_id, $apartmentNo);
            $checkAddTenantDuplicateStmt->execute();
            $checkAddTenantDuplicateStmt->bind_result($duplicateCount);
            $checkAddTenantDuplicateStmt->fetch();
            $checkAddTenantDuplicateStmt->close();

            if ($duplicateCount > 0) {
                $errorMsg = "This apartment (" . $apartmentNo . ") is already added to your tenants list.";
            } else {
                $stmt = $conn->prepare("INSERT INTO addtenants (tenant_id, name, apartment_no, monthly_rent, family_members, additional_info, landlord_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issdisi", $tenant_id_to_insert, $tenantName, $apartmentNo, $monthlyRent, $familyMembers, $additionalInfo, $landlord_id);

                if ($stmt->execute()) {
                    $successMsg = "Tenant added successfully!";
                    $tenantName_val = $apartmentNo_val = $monthlyRent_val = $familyMembers_val = $emergencyContact_val = $additionalInfo_val = '';
                } else {
                    $errorMsg = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        } else {
            $errorMsg = "Error: Tenant with the provided name not found or not registered as a tenant user.";
        }
        $getTenantIdStmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add Tenant - PropertyPilot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-dark: #021934;
            --text-color: #f0f4ff;
            --secondary-background: #f0f4ff;
            --actionAdd: #28a745;
            --actionBilling: #ffc107;
            --actionViewRentList: #17a2b8;
            --actionViewTenantList: #6f42c1;
            --actionApartmentList: #6c757d;
            --actionScheduleCreate: #e83e8c;
            --actionScheduleDetails: #fd7e14;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
          margin: 0; font-family: 'Segoe UI', sans-serif; background-color: var(--secondary-background);
          color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
        }
        .main-top-navbar {
          background-color: var(--primary-dark); color: var(--text-color); padding: 15px 30px; display: flex;
          justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
          z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--text-color); }
        .top-right-user-info .logout-btn {
          background-color: #dc3545; color: var(--text-color); padding: 8px 15px; border-radius: 5px;
          text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
        }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: var(--primary-dark);
          padding: 20px 15px; color: var(--text-color); box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
          color: var(--text-color); text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .vertical-sidebar .action-buttons {
          margin-top: 5px; width: 100%; display: flex; flex-direction: column;
          gap: 8px; align-items: center; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 10px;
        }
        .vertical-sidebar .action-buttons h3 { color: var(--text-color); font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
          width: calc(100% - 30px); padding: 9px 15px; border-radius: 8px; color: var(--text-color);
          font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center;
          justify-content: flex-start; gap: 10px; text-decoration: none; transition: all 0.2s ease;
        }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); background-color: rgba(255, 255, 255, 0.1); }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; color: #021934; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }
        
        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; }
    </style>
</head>

<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo" />PropertyPilot</div>
        <div class="top-right-user-info">
            <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php">Dashboard</a>
                <a href="profile.php">Profile</a>
                <a href="notifications.php">Notifications</a>
            </div>
            <section class="action-buttons">
                <h3>Quick Actions</h3>
                <a href="add_tenant.php" class="action-link link-tenant active">+ Add Tenant</a>
                <a href="tenant_List.php" class="action-link link-tenant-list">View Tenant List</a>
                <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
                <a href="RentAndBillForm.php" class="action-link link-billing">Rent and Bills</a>
                <a href="Rent_list.php" class="action-link link-rent">View Rent List</a>
                <a href="Schedule_create.php" class="action-link link-schedule-create">Create Schedule</a>
                <a href="scheduleInfo.php" class="action-link link-schedule-details">🗓️ Schedule Details</a>
            </section>
        </nav>

        <main class="page-main-content">
             <div class="bg-gradient-to-br from-indigo-500 to-purple-600 p-1 rounded-2xl shadow-2xl w-full max-w-2xl mx-auto">
                <div class="bg-white p-8 rounded-2xl">
                    <h1 class="text-3xl font-extrabold text-gray-900 mb-8 text-center">Add New Tenant</h1>

                    <?php if (!empty($successMsg)): ?>
                        <div class="text-green-700 bg-green-100 p-2 rounded mb-4"><?php echo htmlspecialchars($successMsg); ?></div>
                    <?php elseif (!empty($errorMsg)): ?>
                        <div class="text-red-600 bg-red-100 p-2 rounded mb-4"><?php echo htmlspecialchars($errorMsg); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="add_tenant.php">
                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="tenantName">Tenant Name</label>
                                <input type="text" name="tenantName" id="tenantName"
                                    value="<?php echo htmlspecialchars($tenantName_val); ?>"
                                    required placeholder="e.g., Jane Doe"
                                    class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="apartmentNo">Apartment No.</label>
                                <select name="apartmentNo" id="apartmentNo" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300" onchange="getTenantInfo()">
                                    <option value="">-- Select Apartment --</option>
                                    <?php foreach ($apartments as $apartment): ?>
                                        <option value="<?php echo htmlspecialchars($apartment); ?>"
                                            <?php echo ($apartmentNo_val === $apartment) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($apartment); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="monthlyRent">Monthly Rent</label>
                                <input type="number" step="0.01" name="rent" id="monthlyRent" value="<?php echo htmlspecialchars($monthlyRent_val); ?>" required placeholder="e.g., 1500.00" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="familyMembers">Family Members</label>
                                <input type="number" name="familyMembers" id="familyMembers" value="<?php echo htmlspecialchars($familyMembers_val); ?>" required placeholder="e.g., 4" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                        </div>

                        <div class="px-3 mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-1" for="emergencyContact">Emergency Contact</label>
                            <input type="text" name="emergencyContact" id="emergencyContact" value="<?php echo htmlspecialchars($emergencyContact_val); ?>" required placeholder="e.g., John Doe - 555-123-4567" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                        </div>

                        <div class="px-3 mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-1" for="moreInfo">Additional Info</label>
                            <textarea name="moreInfo" id="moreInfo" rows="3" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300" placeholder="Any specific notes or requests..."><?php echo htmlspecialchars($additionalInfo_val); ?></textarea>
                        </div>

                        <div class="px-3 mb-4">
                            <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-purple-700 text-white font-bold rounded-xl shadow-md hover:scale-105 transition">Add Tenant</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        function getTenantInfo() {
            const apartmentNo = document.getElementById('apartmentNo').value;
            if (apartmentNo !== '') {
                window.location.href = `add_tenant.php?apartment_no=${apartmentNo}`;
            }
        }
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const selectedApartmentFromGet = urlParams.get('apartment_no');
            const apartmentNoDropdown = document.getElementById('apartmentNo');
            const selectedApartmentValue = "<?php echo htmlspecialchars($apartmentNo_val); ?>";
            if (selectedApartmentValue !== '') {
                apartmentNoDropdown.value = selectedApartmentValue;
            } else if (selectedApartmentFromGet !== null) {
                apartmentNoDropdown.value = selectedApartmentFromGet;
            }
        };
    </script>
</body>
</html>
