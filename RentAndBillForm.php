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

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Fetch all tenant details for the logged-in landlord
$tenantDetails = [];
$detailsQuery = "SELECT tenant_id, name, apartment_no, monthly_rent FROM addtenants WHERE landlord_id = ?";
$stmt_details = $conn->prepare($detailsQuery);
$stmt_details->bind_param("i", $landlord_id);
$stmt_details->execute();
$result = $stmt_details->get_result();
while ($row = $result->fetch_assoc()) {
    $tenantDetails[$row['apartment_no']] = $row;
}
$stmt_details->close();

// ✅ Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenant_id = $_POST['tenant_id'] ?? 0;
    $apartment_no = $_POST['apartment_no'] ?? '';
    $rent_amount = $_POST['rent_amount'] ?? 0;
    $billing_date = $_POST['billing_date'] ?? '';
    $previous_due = $_POST['previous_due'] ?? 0;
    $water_bill = $_POST['water_bill'] ?? 0;
    $utility_bill = $_POST['utility_bill'] ?? 0;
    $guard_bill = $_POST['guard_bill'] ?? 0;

    if (empty($tenant_id) || empty($apartment_no) || empty($billing_date) || !is_numeric($rent_amount)) {
        $errorMsg = "❌ Please select an apartment and fill all required fields.";
    } else {
        $billing_month = date('m', strtotime($billing_date));
        $billing_year = date('Y', strtotime($billing_date));

        $checkStmt = $conn->prepare("SELECT rent_id FROM rentAndBill WHERE tenant_id = ? AND landlord_id = ? AND MONTH(billing_date) = ? AND YEAR(billing_date) = ?");
        $checkStmt->bind_param("iiii", $tenant_id, $landlord_id, $billing_month, $billing_year);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMsg = "❌ Bill for this tenant for " . date('F Y', strtotime($billing_date)) . " already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO rentAndBill (landlord_id, tenant_id, apartment_no, rent_amount, previous_due, water_bill, utility_bill, guard_bill, billing_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisddddds", $landlord_id, $tenant_id, $apartment_no, $rent_amount, $previous_due, $water_bill, $utility_bill, $guard_bill, $billing_date);

            if ($stmt->execute()) {
                $successMsg = "✅ Rent and bill information saved successfully.";
            } else {
                $errorMsg = "❌ Error saving information: " . $stmt->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent and Bills</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #006A4E;
            --secondary-color: #4A90E2;
            --background-color: #f0f4ff;
            --card-background: #ffffff;
            --text-color: #333;
            --border-color: #ddd;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
          margin: 0; font-family: 'Poppins', sans-serif; background-color: var(--background-color);
          color: var(--text-color); display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
        }
        .main-top-navbar {
          background-color: #021934; color: #f0f4ff; padding: 15px 30px; display: flex;
          justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
          z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #f0f4ff; }
        .top-right-user-info .logout-btn {
          background-color: #dc3545; color: #f0f4ff; padding: 8px 15px; border-radius: 5px;
          text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
        }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: #021934;
          padding: 20px 15px; color: #f0f4ff; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
          color: #f0f4ff; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: #2c5dbd; }
        .vertical-sidebar .action-buttons {
          width: 100%;
          display: flex;
          flex-direction: column;
          gap: 6px; /* Reduced gap between buttons */
          align-items: center;
          border-top: 1px solid rgba(255, 255, 255, 0.1);
          
        }
        .vertical-sidebar .action-buttons h3 {
          color: #f0f4ff;
          font-size: 1.1em;
          margin-bottom: 8px; /* Reduced margin */
          text-transform: uppercase;
        }
        .vertical-sidebar .action-link {
          width: calc(100% - 30px);
          padding: 8px 15px; /* Reduced vertical padding */
          border-radius: 8px;
          color: #f0f4ff;
          font-weight: 600;
          font-size: 14px;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: flex-start;
          gap: 10px;
          text-decoration: none;
          transition: all 0.2s ease;
        }
        .vertical-sidebar .action-link:hover { transform: translateX(5px); background-color: rgba(255, 255, 255, 0.1); }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; color: <?php echo $primaryDark; ?>; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }
        
        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; }
        .form-wrapper {
          width: 100%; max-width: 900px; background-color: var(--card-background);
          border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); overflow: hidden; margin: 0 auto;
        }
        .form-header {
          background-color: var(--primary-color); color: white; padding: 20px 30px; text-align: center;
        }
        .form-header h1 { margin: 0; font-size: 1.8rem; font-weight: 600; }
        .form-grid {
            padding: 30px; display: grid; grid-template-columns: 1fr 1fr; gap: 25px 40px;
        }
        .form-section-title {
            grid-column: 1 / -1; margin: 0 0 10px 0; color: var(--primary-color);
            font-size: 1.4rem; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;
        }
        .input-wrapper { position: relative; }
        .input-field {
            padding: 12px 15px 12px 40px; border-radius: 8px; border: 1px solid var(--border-color);
            width: 100%; font-family: 'Poppins', sans-serif;
        }
        .input-field[readonly] { background-color: #e9ecef; cursor: not-allowed; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .action-container {
            grid-column: 1 / -1; display: flex; justify-content: center;
            gap: 20px; flex-wrap: wrap; margin-top: 10px;
        }
        .action-button {
            color: white; border: none; border-radius: 8px; padding: 15px 30px;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease;
            display: flex; align-items: center; gap: 10px; text-decoration: none;
        }
        .save-button {
            background: linear-gradient(45deg, var(--primary-color), #008a63);
            box-shadow: 0 4px 15px rgba(0, 106, 78, 0.4);
        }
        .save-button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 106, 78, 0.5); }
        .message, .error {
          grid-column: 1 / -1; margin-bottom: 0; padding: 12px; border-radius: 5px;
          font-weight: 500; text-align: center;
        }
        .message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
                <a href="tenant_List.php" class="action-link link-tenant-list">View Tenant List</a>
                <a href="apartmentList.php" class="action-link link-docs">Apartment List</a>
                <a href="RentAndBillForm.php" class="action-link link-billing active">Rent and Bills</a>
                <a href="Rent_list.php" class="action-link link-rent">View Rent List</a>
                <a href="Schedule_create.php" class="action-link link-schedule-create">Create Schedule</a>
                <a href="scheduleInfo.php" class="action-link link-schedule-details">🗓️ Schedule Details</a>
            </section>
        </nav>

        <main class="page-main-content">
            <div class="form-wrapper">
                <header class="form-header">
                    <h1>Rent & Bills Information</h1>
                </header>
                <form method="POST" action="" class="form-grid">
                    <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
                    <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

                    <div class="input-wrapper" style="grid-column: 1 / -1;">
                        <i class="fas fa-building"></i>
                        <select class="input-field" id="apartment_no" name="apartment_no" required style="padding-left: 40px; appearance: none;">
                            <option value="">-- Select an Apartment --</option>
                            <?php foreach ($tenantDetails as $apartment_no => $details): ?>
                                <option value="<?php echo htmlspecialchars($apartment_no); ?>">
                                    <?php echo htmlspecialchars($apartment_no); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h2 class="form-section-title">Rent Information</h2>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input class="input-field" id="tenant_name" type="text" placeholder="Tenant's Name" readonly>
                    </div>
                    <div class="input-wrapper">
                        <i class="fas fa-money-bill-wave"></i>
                        <input class="input-field bill-component" id="rent_amount" name="rent_amount" type="text" placeholder="Rent Amount (৳)" readonly>
                    </div>
                    <div class="input-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                        <input class="input-field" id="billing_date" name="billing_date" type="date" required>
                    </div>
                    <div class="input-wrapper">
                        <i class="fas fa-exclamation-circle"></i>
                        <input class="input-field bill-component" id="previous_due" name="previous_due" type="number" step="0.01" placeholder="Previous Due (৳)">
                    </div>

                    <h2 class="form-section-title">Other Bills</h2>
                    <div class="input-wrapper">
                        <i class="fas fa-tint"></i>
                        <input class="input-field bill-component" id="water_bill" name="water_bill" type="number" step="0.01" placeholder="Water Bill (৳)">
                    </div>
                    <div class="input-wrapper">
                        <i class="fas fa-bolt"></i>
                        <input class="input-field bill-component" id="utility_bill" name="utility_bill" type="number" step="0.01" placeholder="Utility Bill (৳)">
                    </div>
                    <div class="input-wrapper">
                        <i class="fas fa-shield-alt"></i>
                        <input class="input-field bill-component" id="guard_bill" name="guard_bill" type="number" step="0.01" placeholder="Guard Bill (৳)">
                    </div>

                    <input type="hidden" id="tenant_id" name="tenant_id">
                    
                    <div class="total-section">
                        <h2>Total Payable Amount</h2>
                        <p id="total-amount">৳0.00</p>
                    </div>

                    <div class="action-container">
                        <button type="submit" class="action-button save-button"><i class="fas fa-save"></i> Save Information</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const tenantDetails = <?php echo json_encode($tenantDetails); ?>;
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const yyyy = today.getFullYear();
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const dd = String(today.getDate()).padStart(2, '0');
            document.getElementById('billing_date').value = `${yyyy}-${mm}-${dd}`;

            const apartmentSelect = document.getElementById('apartment_no');
            const tenantNameInput = document.getElementById('tenant_name');
            const rentAmountInput = document.getElementById('rent_amount');
            const tenantIdInput = document.getElementById('tenant_id');
            const totalAmountEl = document.getElementById('total-amount');
            const billComponents = document.querySelectorAll('.bill-component');

            function calculateTotal() {
                const getNumberValue = (id) => {
                    const el = document.getElementById(id);
                    const value = parseFloat(el.value);
                    return isNaN(value) ? 0 : value;
                };

                const rent = getNumberValue('rent_amount');
                const previousDue = getNumberValue('previous_due');
                const water = getNumberValue('water_bill');
                const utility = getNumberValue('utility_bill');
                const guard = getNumberValue('guard_bill');
                const total = rent + previousDue + water + utility + guard;
                totalAmountEl.textContent = `৳${total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
            }

            apartmentSelect.addEventListener('change', function() {
                const successMessage = document.querySelector('.message');
                const errorMessage = document.querySelector('.error');
                if (successMessage) successMessage.style.display = 'none';
                if (errorMessage) errorMessage.style.display = 'none';

                const apartmentNo = this.value;

                if (!apartmentNo) {
                    tenantNameInput.value = '';
                    rentAmountInput.value = '';
                    tenantIdInput.value = '';
                } else {
                    const details = tenantDetails[apartmentNo];
                    if (details) {
                        tenantNameInput.value = details.name;
                        rentAmountInput.value = details.monthly_rent;
                        tenantIdInput.value = details.tenant_id;
                    } else {
                        tenantNameInput.value = 'Details not found';
                        rentAmountInput.value = '';
                        tenantIdInput.value = '';
                    }
                }
                calculateTotal();
            });

            billComponents.forEach(input => {
                input.addEventListener('input', calculateTotal);
            });
        });
    </script>
</body>
</html>