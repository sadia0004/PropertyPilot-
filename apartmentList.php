<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- DASHBOARD UI INTEGRATION ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
// Specific check for this page's logic
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access. Please log in as a landlord.");
}
$landlord_id = $_SESSION['landlord_id'];

// Retrieve user data for UI
$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?: "default-avatar.png";

// DB connection
$host = "localhost"; $username = "root"; $password = ""; $database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Delete Action
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM properties WHERE property_id = ? AND landlord_id = ?");
    $stmt->bind_param("ii", $delete_id, $landlord_id);
    $stmt->execute();
    $stmt->close();
    header("Location: apartmentList.php?deleted=true");
    exit();
}

// Fetch Apartment Data
$stmt = $conn->prepare("SELECT * FROM properties WHERE landlord_id = ? ORDER BY apartment_no ASC");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();

$brandColors = [
    'primaryDark' => '#021934', 'primaryAccent' => '#2c5dbd', 'textColor' => '#f0f4ff',
    'secondaryBackground' => '#f0f4ff', 'actionAdd' => '#28a745', 'actionBilling' => '#ffc107',
    'actionViewRentList' => '#17a2b8', 'actionViewTenantList' => '#6f42c1',
    'actionApartmentList' => '#6c757d', 'actionScheduleCreate' => '#e83e8c',
    'actionScheduleDetails' => '#fd7e14', 'actionMaintenance' => '#dc3545'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Apartment List - PropertyPilot</title>
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
        .vertical-sidebar { display: flex; flex-direction: column; background-color: <?php echo $brandColors['primaryDark']; ?>; padding: 20px 15px; color: <?php echo $brandColors['textColor']; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2); z-index: 1000; flex-shrink: 0; width: 250px; overflow-y: auto; }
        
        /* Main Navigation Links */
        .vertical-sidebar .nav-links a { color: <?php echo $brandColors['textColor']; ?>; text-decoration: none; padding: 8px 10px; margin: 8px 0; font-weight: 600; border-radius: 8px; transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px; }
        .vertical-sidebar .nav-links a:hover,
        .vertical-sidebar .nav-links a.active { background-color: <?php echo $brandColors['primaryAccent']; ?>; }

        /* Action Buttons Section - Corrected CSS */
        .vertical-sidebar .action-buttons { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; margin-top: 15px; }
        .vertical-sidebar .action-buttons h3 { color: <?php echo $brandColors['textColor']; ?>; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link {
            width: 100%; padding: 9px 15px; border-radius: 8px; color: <?php echo $brandColors['textColor']; ?>;
            font-weight: 600; font-size: 14px; cursor: pointer; display: flex; align-items: center;
            gap: 10px; text-decoration: none; margin: 7px 0;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .vertical-sidebar .action-link:hover {
            transform: translateX(5px);
            background-color: rgba(255, 255, 255, 0.1) !important;
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
        
        main { flex-grow: 1; padding: 30px; height: 100%; overflow-y: auto; }
        .table-container { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        h2 { text-align: center; color: #333; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid #e9ecef; text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; color: #495057; text-transform: uppercase; font-size: 12px; }
        tr:hover { background-color: #f1f8ff; }
        a.btn { padding: 6px 12px; text-decoration: none; border-radius: 6px; color: white; font-weight: 500; font-size: 14px; transition: filter 0.2s; }
        a.btn:hover { filter: brightness(1.1); }
        .edit-btn { background-color: #28a745; }
        .delete-btn { background-color: #dc3545; }
        .status-vacant { background-color: #d4edda; color: #155724; padding: 5px 10px; border-radius: 20px; font-weight: bold; font-size: 12px; }
        .status-occupied { background-color: #f8d7da; color: #721c24; padding: 5px 10px; border-radius: 20px; font-weight: bold; font-size: 12px; }
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
            <a href="landlord_dashboard.php">Dashboard</a>
            <a href="profile.php">Profile</a>
            <a href="propertyInfo.php">Add Property Info</a>
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
        <div class="table-container">
            <h2>Your Apartment Info List</h2>
            <table>
                <tr><th>Apt No</th><th>Rent (BDT)</th><th>Status</th><th>Floor</th><th>Type</th><th>Size (sq ft)</th><th>Actions</th></tr>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['apartment_no']); ?></td>
                            <td><?php echo number_format($row['apartment_rent']); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower($row['apartment_status']); ?>">
                                    <?php echo htmlspecialchars($row['apartment_status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['floor_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['apartment_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['apartment_size']); ?></td>
                            <td>
                                <a href="editApartment.php?id=<?php echo $row['property_id']; ?>" class="btn edit-btn">Edit</a>
                                <a href="apartmentList.php?delete_id=<?php echo $row['property_id']; ?>" class="btn delete-btn" onclick="return confirm('Are you sure? This action cannot be undone.');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center; padding: 20px;">No apartments found. <a href="propertyInfo.php">Add one now!</a></td></tr>
                <?php endif; ?>
            </table>
        </div>
    </main>
</div>
</body>
</html>