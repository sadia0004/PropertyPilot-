<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Protect the page: allow only logged-in admins
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$admin_id = $_SESSION['user_id'];

// Retrieve user data from session for the header
$fullName_header = $_SESSION['fullName'] ?? 'Admin';
$profilePhoto_header = $_SESSION['profilePhoto'] ?? "default-avatar.png";

// --- Define Color Palette ---
$primaryDark = '#0A0908';
$primaryAccent = '#491D8B';
$textColor = '#F2F4F3';
$secondaryBackground = '#F0F2F5';
$cardBackground = '#FFFFFF';
$actionMaintenance = '#dc3545';

// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$successMsg = "";
$errorMsg = "";

// --- Form Handling ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName     = trim($_POST['fullName']);
    $email        = trim($_POST['email']);
    $phoneNumber  = trim($_POST['phoneNumber']);
    $nationalId   = trim($_POST['nationalId']);
    $userRole     = trim($_POST['userRole']);
    $rawPassword  = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $profilePhoto = "";

    // --- Validation ---
    if ($rawPassword !== $confirmPassword) {
        $errorMsg = "Passwords do not match.";
    } else {
        // Check for duplicate email or NID
        $checkStmt = $conn->prepare("SELECT email, nationalId FROM users WHERE email = ? OR nationalId = ?");
        $checkStmt->bind_param("ss", $email, $nationalId);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            $checkStmt->bind_result($existingEmail, $existingNid);
            while ($checkStmt->fetch()) {
                if ($email === $existingEmail) { $errorMsg = "This email is already registered."; break; }
                if ($nationalId === $existingNid) { $errorMsg = "This National ID is already registered."; break; }
            }
        }
        $checkStmt->close();
    }

    // If no error, proceed
    if (empty($errorMsg)) {
        $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

        if (isset($_FILES["profilePhoto"]) && $_FILES["profilePhoto"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }
            $filename = time() . "_" . basename($_FILES["profilePhoto"]["name"]);
            $uploadPath = $uploadDir . $filename;
            if (move_uploaded_file($_FILES["profilePhoto"]["tmp_name"], $uploadPath)) {
                $profilePhoto = $uploadPath;
            }
        }

        $conn->begin_transaction();
        try {
            // 1. Insert into users table
            $insertStmt = $conn->prepare("INSERT INTO users (fullName, email, phoneNumber, password, profilePhoto, nationalId, userRole) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssssss", $fullName, $email, $phoneNumber, $hashedPassword, $profilePhoto, $nationalId, $userRole);
            $insertStmt->execute();
            $newUserId = $insertStmt->insert_id;
            $insertStmt->close();

            // 2. If user is a tenant, also add to `addtenants` and update property
            if ($userRole === 'tenant') {
                $landlord_id = $_POST['landlord_id'];
                $apartment_no = $_POST['apartment_no'];
                $monthly_rent = $_POST['monthly_rent'];

                $addTenantStmt = $conn->prepare("INSERT INTO addtenants (tenant_id, landlord_id, name, apartment_no, monthly_rent) VALUES (?, ?, ?, ?, ?)");
                $addTenantStmt->bind_param("iissd", $newUserId, $landlord_id, $fullName, $apartment_no, $monthly_rent);
                $addTenantStmt->execute();
                $addTenantStmt->close();
                
                $updatePropStmt = $conn->prepare("UPDATE properties SET apartment_status = 'Occupied' WHERE apartment_no = ?");
                $updatePropStmt->bind_param("s", $apartment_no);
                $updatePropStmt->execute();
                $updatePropStmt->close();
            }
            
            $conn->commit();
            $successMsg = "User account created successfully!";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $errorMsg = "Database error: could not create user. " . $exception->getMessage();
        }
    }
}

// Fetch landlords and their vacant apartments for the dropdowns
$landlordsWithApartments = [];
$landlordQuery = "SELECT u.id, u.fullName FROM users u WHERE u.userRole = 'landlord'";
$landlordResult = $conn->query($landlordQuery);
while($landlord = $landlordResult->fetch_assoc()) {
    $apartments = [];
    $aptStmt = $conn->prepare("SELECT apartment_no, apartment_rent FROM properties WHERE landlord_id = ? AND apartment_status = 'Vacant'");
    $aptStmt->bind_param("i", $landlord['id']);
    $aptStmt->execute();
    $aptResult = $aptStmt->get_result();
    while($apt = $aptResult->fetch_assoc()){
        $apartments[] = $apt;
    }
    $aptStmt->close();
    if (!empty($apartments)) {
        $landlordsWithApartments[] = [
            'id' => $landlord['id'],
            'name' => $landlord['fullName'],
            'apartments' => $apartments
        ];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Add User - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: <?php echo $secondaryBackground; ?>; color: #222;
            display: flex; flex-direction: column; height: 100vh; overflow: hidden;
        }
        .main-top-navbar {
            background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); z-index: 1001; flex-shrink: 0;
            position: fixed; top: 0; left: 0; width: 100%; height: 80px;
        }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; object-fit: contain; background: <?php echo $cardBackground; ?>; padding: 3px; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn { background-color: <?php echo $actionMaintenance; ?>; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
            display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
            padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
            z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; overflow-y: hidden;
        }
        .vertical-sidebar .nav-links a {
            color: <?php echo $textColor; ?>; text-decoration: none; width:100%; text-align: left; padding: 12px 15px;
            margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
            transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }
        
        .form-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .form-container h2 { color: <?php echo $primaryDark; ?>; margin-top: 0; margin-bottom: 30px; font-size: 2rem; text-align: center; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-group { flex: 1; display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 1rem; }
        .form-actions { text-align: right; margin-top: 30px; }
        .btn-save { background-color: <?php echo $primaryAccent; ?>; color: white; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: bold; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
        #tenant-fields { display: none; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo"/>PropertyPilot - Admin Panel</div>
        <div class="top-right-user-info">
            <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName_header); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto_header); ?>" alt="Profile Photo">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="admin_add_user.php" class="active"><i class="fas fa-user-plus"></i> Add User</a>
                <a href="admin_manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a>
                <a href="admin_properties.php"><i class="fas fa-city"></i> Manage Properties</a>
                <a href="admin_settings.php"><i class="fas fa-cogs"></i> Settings</a>
            </div>
        </nav>

        <main>
            <div class="form-container">
                <h2>Create New User Account</h2>

                <?php if ($successMsg): ?><div class="message success"><?php echo $successMsg; ?></div><?php endif; ?>
                <?php if ($errorMsg): ?><div class="message error"><?php echo $errorMsg; ?></div><?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group"><label for="fullName">Full Name</label><input type="text" id="fullName" name="fullName" required></div>
                        <div class="form-group"><label for="email">Email Address</label><input type="email" id="email" name="email" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="phoneNumber">Phone Number</label><input type="tel" id="phoneNumber" name="phoneNumber"></div>
                        <div class="form-group"><label for="nationalId">National ID</label><input type="text" id="nationalId" name="nationalId" required></div>
                    </div>
                     <div class="form-row">
                        <div class="form-group"><label for="password">Password</label><input type="password" id="password" name="password" required></div>
                        <div class="form-group"><label for="confirmPassword">Confirm Password</label><input type="password" id="confirmPassword" name="confirmPassword" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="userRole">User Role</label>
                            <select id="userRole" name="userRole" required onchange="toggleTenantFields()">
                                <option value="">Select Role</option>
                                <option value="landlord">Landlord</option>
                                <option value="tenant">Tenant</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="profilePhoto">Profile Photo</label><input type="file" id="profilePhoto" name="profilePhoto" accept="image/*"></div>
                    </div>
                    
                    <!-- Tenant Specific Fields -->
                    <div id="tenant-fields" class="form-row">
                        <div class="form-group">
                            <label for="landlord_id">Assign to Landlord</label>
                            <select id="landlord_id" name="landlord_id" onchange="updateApartments()">
                                <option value="">Select Landlord</option>
                                <?php foreach($landlordsWithApartments as $landlord): ?>
                                    <option value="<?php echo $landlord['id']; ?>"><?php echo htmlspecialchars($landlord['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="apartment_no">Assign to Apartment</label>
                            <select id="apartment_no" name="apartment_no" onchange="updateRent()">
                                <option value="">Select Landlord First</option>
                            </select>
                        </div>
                         <div class="form-group">
                            <label for="monthly_rent">Monthly Rent (৳)</label>
                            <input type="number" id="monthly_rent" name="monthly_rent" readonly>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-save"><i class="fas fa-plus-circle"></i> Create User</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        const landlordsData = <?php echo json_encode($landlordsWithApartments); ?>;

        function toggleTenantFields() {
            const role = document.getElementById('userRole').value;
            const tenantFields = document.getElementById('tenant-fields');
            const landlordSelect = document.getElementById('landlord_id');
            const apartmentSelect = document.getElementById('apartment_no');
            const rentInput = document.getElementById('monthly_rent');

            if (role === 'tenant') {
                tenantFields.style.display = 'flex';
                landlordSelect.required = true;
                apartmentSelect.required = true;
                rentInput.required = true;
            } else {
                tenantFields.style.display = 'none';
                landlordSelect.required = false;
                apartmentSelect.required = false;
                rentInput.required = false;
            }
        }

        function updateApartments() {
            const landlordId = document.getElementById('landlord_id').value;
            const apartmentSelect = document.getElementById('apartment_no');
            apartmentSelect.innerHTML = '<option value="">Loading...</option>';

            const selectedLandlord = landlordsData.find(l => l.id == landlordId);
            
            if (selectedLandlord) {
                apartmentSelect.innerHTML = '<option value="">Select Apartment</option>';
                selectedLandlord.apartments.forEach(apt => {
                    const option = document.createElement('option');
                    option.value = apt.apartment_no;
                    option.textContent = apt.apartment_no;
                    option.dataset.rent = apt.apartment_rent; // Store rent in data attribute
                    apartmentSelect.appendChild(option);
                });
            } else {
                apartmentSelect.innerHTML = '<option value="">Select Landlord First</option>';
            }
            updateRent(); // Clear rent when landlord changes
        }

        function updateRent() {
            const apartmentSelect = document.getElementById('apartment_no');
            const selectedOption = apartmentSelect.options[apartmentSelect.selectedIndex];
            const rentInput = document.getElementById('monthly_rent');
            
            if (selectedOption && selectedOption.dataset.rent) {
                rentInput.value = selectedOption.dataset.rent;
            } else {
                rentInput.value = '';
            }
        }
    </script>
</body>
</html>
