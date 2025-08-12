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

// Initialize messages and variables
$successMsg = "";
$errorMsg = "";
$tenant_id_to_edit = $_GET['id'] ?? 0;

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Handle form submission for UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenant_id_to_edit = $_POST['tenant_id'];
    $tenantName = trim($_POST['tenantName']);
    $monthlyRent = filter_var($_POST['monthlyRent'], FILTER_VALIDATE_FLOAT);
    $familyMembers = filter_var($_POST['familyMembers'], FILTER_VALIDATE_INT);
    $emergencyContact = trim($_POST['emergencyContact']);
    $additionalInfo = trim($_POST['additionalInfo']);

    if (empty($tenantName) || $monthlyRent === false || $familyMembers === false || empty($emergencyContact)) {
        $errorMsg = "Please fill in all required fields with valid data.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Update 'addtenants' table
            $stmt1 = $conn->prepare("UPDATE addtenants SET name = ?, monthly_rent = ?, family_members = ?, additional_info = ? WHERE tenant_id = ? AND landlord_id = ?");
            $stmt1->bind_param("sdisii", $tenantName, $monthlyRent, $familyMembers, $additionalInfo, $tenant_id_to_edit, $landlord_id);
            $stmt1->execute();
            $stmt1->close();

            // 2. Update 'tenants' table
            $stmt2 = $conn->prepare("UPDATE tenants SET name = ?, family_members = ?, emergency_contact = ? WHERE tenant_id = ?");
            $stmt2->bind_param("sisi", $tenantName, $familyMembers, $emergencyContact, $tenant_id_to_edit);
            $stmt2->execute();
            $stmt2->close();
            
            // 3. Update 'users' table
            $stmt3 = $conn->prepare("UPDATE users SET fullName = ? WHERE id = ?");
            $stmt3->bind_param("si", $tenantName, $tenant_id_to_edit);
            $stmt3->execute();
            $stmt3->close();

            $conn->commit();
            $successMsg = "✅ Tenant details updated successfully!";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $errorMsg = "❌ Error updating details: " . $exception->getMessage();
        }
    }
}

// ✅ Fetch current tenant details for the form
$tenantData = null;
if ($tenant_id_to_edit > 0) {
    $query = "SELECT 
                at.name, 
                at.apartment_no, 
                at.monthly_rent, 
                at.family_members, 
                at.additional_info,
                t.emergency_contact
              FROM addtenants at
              LEFT JOIN tenants t ON at.tenant_id = t.tenant_id
              WHERE at.tenant_id = ? AND at.landlord_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $tenant_id_to_edit, $landlord_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $tenantData = $result->fetch_assoc();
    } else {
        $errorMsg = "Tenant not found or you do not have permission to edit.";
    }
    $stmt->close();
} else {
    $errorMsg = "No tenant selected for editing.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Tenant - PropertyPilot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --primary-dark: #021934;
            --text-color: #f0f4ff;
            --secondary-background: #f0f4ff;
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
                <a href="add_tenant.php" class="action-link link-tenant">+ Add Tenant</a>
                <a href="view_tenants.php" class="action-link link-tenant-list active">View Tenant List</a>
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
                    <h1 class="text-3xl font-extrabold text-gray-900 mb-8 text-center">Edit Tenant Information</h1>

                    <?php if (!empty($successMsg)): ?>
                        <div class="text-green-700 bg-green-100 p-2 rounded mb-4"><?php echo htmlspecialchars($successMsg); ?></div>
                    <?php elseif (!empty($errorMsg)): ?>
                        <div class="text-red-600 bg-red-100 p-2 rounded mb-4"><?php echo htmlspecialchars($errorMsg); ?></div>
                    <?php endif; ?>

                    <?php if ($tenantData): ?>
                    <form method="POST">
                        <input type="hidden" name="tenant_id" value="<?php echo $tenant_id_to_edit; ?>">
                        
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Apartment No.</label>
                            <input type="text" value="<?php echo htmlspecialchars($tenantData['apartment_no']); ?>" readonly class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm bg-gray-200">
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="tenantName">Tenant Name</label>
                                <input type="text" name="tenantName" id="tenantName" value="<?php echo htmlspecialchars($tenantData['name']); ?>" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                            <div class="w-full md:w-1/2 px-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="monthlyRent">Monthly Rent</label>
                                <input type="number" step="0.01" name="monthlyRent" id="monthlyRent" value="<?php echo htmlspecialchars($tenantData['monthly_rent']); ?>" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3 mb-6">
                            <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="familyMembers">Family Members</label>
                                <input type="number" name="familyMembers" id="familyMembers" value="<?php echo htmlspecialchars($tenantData['family_members'] ?? ''); ?>" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                             <div class="w-full md:w-1/2 px-3">
                                <label class="block text-sm font-semibold text-gray-700 mb-1" for="emergencyContact">Emergency Contact</label>
                                <input type="text" name="emergencyContact" id="emergencyContact" value="<?php echo htmlspecialchars($tenantData['emergency_contact'] ?? ''); ?>" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                            </div>
                        </div>

                        <div class="px-3 mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-1" for="additionalInfo">Additional Info</label>
                            <textarea name="additionalInfo" id="additionalInfo" rows="3" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300"><?php echo htmlspecialchars($tenantData['additional_info'] ?? ''); ?></textarea>
                        </div>

                        <div class="px-3 mb-4">
                            <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-purple-700 text-white font-bold rounded-xl shadow-md hover:scale-105 transition">Update Tenant</button>
                        </div>
                    </form>
                    <?php endif; ?>
                    <div class="text-center mt-2">
                        <a href="view_tenants.php" class="text-indigo-600 hover:underline">Back to Tenant List</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
