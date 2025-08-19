<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Session, UI Variables, and DB Setup ---
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access.");
}
$landlord_id = $_SESSION['landlord_id'];

// Retrieve user data for UI
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";

$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- Initial Data Fetch ---
$errorMsg = "";
$successMsg = "";
if (!isset($_GET['id'])) {
    die("Apartment ID not provided.");
}
$property_id = intval($_GET['id']);

// Fetch the specific apartment, ensuring it belongs to the logged-in landlord
$stmt = $conn->prepare("SELECT * FROM properties WHERE property_id = ? AND landlord_id = ?");
$stmt->bind_param("ii", $property_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("No apartment found or you do not have permission to access it.");
}
$apartment = $result->fetch_assoc();
$stmt->close();

// --- Handle Form Submission (Update Logic) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Current status from before the update
    $current_status = $apartment['apartment_status'];

    // Data from the submitted form
    $apartment_rent = $_POST['apartment_rent'];
    $apartment_status = $_POST['apartment_status'];
    $floor_no = $_POST['floor_no'];
    $apartment_type = $_POST['apartment_type'];
    $apartment_size = $_POST['apartment_size'];

    // ✅ Backend Safety Check: Enforce the core rule
    if ($current_status === 'Vacant' && $apartment_status === 'Occupied') {
        $errorMsg = "❌ You cannot manually change a 'Vacant' apartment to 'Occupied'.";
    } else {
        $update_stmt = $conn->prepare("UPDATE properties SET apartment_rent = ?, apartment_status = ?, floor_no = ?, apartment_type = ?, apartment_size = ? WHERE property_id = ? AND landlord_id = ?");
        $update_stmt->bind_param("dsisiii", $apartment_rent, $apartment_status, $floor_no, $apartment_type, $apartment_size, $property_id, $landlord_id);

        if ($update_stmt->execute()) {
            $successMsg = "✅ Apartment updated successfully.";
            // Refresh data to show the latest updates in the form
            $stmt = $conn->prepare("SELECT * FROM properties WHERE property_id = ? AND landlord_id = ?");
            $stmt->bind_param("ii", $property_id, $landlord_id);
            $stmt->execute();
            $apartment = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            $errorMsg = "❌ Error updating apartment: " . $update_stmt->error;
        }
        $update_stmt->close();
    }
}
$conn->close();

$brandColors = [
    'primaryDark' => '#021934', 'primaryAccent' => '#2c5dbd', 'textColor' => '#f0f4ff',
    'secondaryBackground' => '#f0f4ff', 'actionAdd' => '#28a745', 'actionBilling' => '#ffc107',
    'actionViewRentList' => '#17a2b8', 'actionViewTenantList' => '#6f42c1',
    'actionApartmentList' => '#6c757d', 'actionScheduleCreate' => '#832d31ff',
    'actionScheduleDetails' => '#fd7e14', 'actionMaintenance' => '#dc3545'
];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Apartment - PropertyPilot</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: <?php echo $brandColors['secondaryBackground']; ?>; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .main-top-navbar { background-color: <?php echo $brandColors['primaryDark']; ?>; color: <?php echo $brandColors['textColor']; ?>; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); z-index: 1001; position: fixed; top: 0; left: 0; width: 100%; height: 80px; }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; object-fit: contain; background: #fff; padding: 3px; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $brandColors['textColor']; ?>; }
        .top-right-user-info .logout-btn { background-color: <?php echo $brandColors['actionMaintenance']; ?>; color: #fff; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar { display: flex; flex-direction: column; background-color: <?php echo $brandColors['primaryDark']; ?>; padding: 20px 15px; color: <?php echo $brandColors['textColor']; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2); z-index: 1000; flex-shrink: 0; width: 250px; }
        
        /* Main Navigation Links */
        .vertical-sidebar .nav-links a { color: <?php echo $brandColors['textColor']; ?>; text-decoration: none; padding: 8px 10px; margin: 8px 0; font-weight: 600; border-radius: 8px; transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .vertical-sidebar .nav-links a:hover,
        .vertical-sidebar .nav-links a.active { background-color: <?php echo $brandColors['primaryAccent']; ?>; }

        /* Action Buttons Section */
        .vertical-sidebar .action-buttons { border-top: 1px solid rgba(255,255,255,0.1);   }
        .vertical-sidebar .action-buttons h3 { color: <?php echo $brandColors['textColor']; ?>; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
            width: 100%; padding: 9px 15px; border-radius: 8px; color: <?php echo $brandColors['textColor']; ?>;
            font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center;
            gap: 8px; text-decoration: none; margin: 4px 0;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .vertical-sidebar .action-link:hover {
            transform: translateX(5px);
            background-color: rgba(255, 255, 255, 0.1) !important; /* Use !important to override base color on hover */
        }
        .vertical-sidebar .action-link.active {
            background-color: <?php echo $brandColors['primaryAccent']; ?> !important;
            
        }
        
        /* Specific background colors for each action button */
        .link-add-tenant { background-color: <?php echo $brandColors['actionAdd']; ?>; }
        .link-view-tenants { background-color: <?php echo $brandColors['actionViewTenantList']; ?>; }
        .link-apartment-list { background-color: <?php echo $brandColors['actionApartmentList']; ?>; }
        .link-rent-bills { background-color: <?php echo $brandColors['actionBilling']; ?>; }
        .link-rent-list { background-color: <?php echo $brandColors['actionViewRentList']; ?>; }
        .link-schedule-create { background-color: <?php echo $brandColors['actionScheduleCreate']; ?>; }
        .link-schedule-details { background-color: <?php echo $brandColors['actionScheduleDetails']; ?>; }

        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; display: flex; justify-content: center; align-items: flex-start; }
        .form-container { max-width: 600px; width: 100%; background: #fff; padding: 25px 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .form-container h2 { text-align: center; color: #333; margin-bottom: 25px; }
        form { display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between; }
        .form-field { flex-basis: calc(50% - 10px); }
        .form-field.full-width { flex-basis: 100%; }
        .form-field label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        .form-field input, .form-field select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; }
        .form-field input[readonly] { background: #e9ecef; cursor: not-allowed; }
        .form-buttons { flex-basis: 100%; text-align: right; margin-top: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; text-decoration: none; color: white; font-weight: bold; cursor: pointer; }
        .btn-save { background-color: #007bff; }
        .btn-back { background-color: #6c757d; }
        .message, .error { padding: 12px; margin-bottom: 20px; border-radius: 5px; font-weight: 500; text-align: center; width: 100%; }
        .message { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<header class="main-top-navbar">
    <div class="brand"><img src="image/logo.png" alt="Logo"/> PropertyPilot</div>
    <div class="top-right-user-info">
        <span><?php echo htmlspecialchars($fullName); ?></span>
        <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Photo">
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
        <div class="action-buttons">
            <h3>Quick Actions</h3>
            <a href="add_tenant.php" class="action-link link-add-tenant">+ Add Tenant</a>
            <a href="tenant_List.php" class="action-link link-view-tenants">View Tenant List</a>
            <a href="apartmentList.php" class="action-link link-apartment-list active">Apartment List</a>
            <a href="RentAndBillForm.php" class="action-link link-rent-bills">Rent and Bills</a>
            <a href="Rent_list.php" class="action-link link-rent-list">View Rent List</a>
            <a href="Schedule_create.php" class="action-link link-schedule-create">Create Schedule</a>
            <a href="scheduleInfo.php" class="action-link link-schedule-details">Schedule Details</a>
        </div>
    </nav>
    <main>
        <div class="form-container">
            <h2>Edit Apartment: <?php echo htmlspecialchars($apartment['apartment_no']); ?></h2>
            <?php if ($successMsg) echo "<div class='message'>$successMsg</div>"; ?>
            <?php if ($errorMsg) echo "<div class='error'>$errorMsg</div>"; ?>

            <form method="POST" action="editApartment.php?id=<?php echo $property_id; ?>">
                
                <div class="form-field full-width">
                    <label for="apartment_no">Apartment Number (Read-only)</label>
                    <input type="text" id="apartment_no" name="apartment_no" value="<?php echo htmlspecialchars($apartment['apartment_no']); ?>" readonly>
                </div>

                <div class="form-field">
                    <label for="apartment_rent">Rent (BDT)</label>
                    <input type="number" step="0.01" id="apartment_rent" name="apartment_rent" value="<?php echo htmlspecialchars($apartment['apartment_rent']); ?>" required>
                </div>

                <div class="form-field">
                    <label for="apartment_status">Status</label>
                    <select id="apartment_status" name="apartment_status" required>
                        <option value="Vacant" <?php if ($apartment['apartment_status'] === 'Vacant') echo 'selected'; ?>>
                            Vacant
                        </option>
                        <option value="Occupied" 
                            <?php if ($apartment['apartment_status'] === 'Occupied') echo 'selected'; ?>
                            <?php if ($apartment['apartment_status'] === 'Vacant') echo 'disabled style="color: #999;"'; ?>>
                            Occupied (Cannot be set manually)
                        </option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="floor_no">Floor</label>
                    <input type="number" id="floor_no" name="floor_no" value="<?php echo htmlspecialchars($apartment['floor_no']); ?>">
                </div>

                <div class="form-field">
                    <label for="apartment_size">Size (sq ft)</label>
                    <input type="number" id="apartment_size" name="apartment_size" value="<?php echo htmlspecialchars($apartment['apartment_size']); ?>">
                </div>

                <div class="form-field full-width">
                    <label for="apartment_type">Apartment Type</label>
                    <input type="text" id="apartment_type" name="apartment_type" value="<?php echo htmlspecialchars($apartment['apartment_type']); ?>">
                </div>

                <div class="form-buttons full-width">
                    <a href="apartmentList.php" class="btn btn-back">Back to List</a>
                    <button type="submit" class="btn btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </main>
</div>
</body>
</html>