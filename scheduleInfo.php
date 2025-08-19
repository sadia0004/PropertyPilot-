<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Protect the page: allow only logged-in landlords
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'landlord') {
    header("Location: login.php");
    exit();
}
$landlord_id = $_SESSION['user_id'];

// Retrieve user data from session
$fullName = $_SESSION['fullName'];
$profilePhoto = $_SESSION['profilePhoto'] ?: "default-avatar.png";
$userRole = $_SESSION['userRole'];

// --- Color Palette from your Dashboard ---
$primaryDark = '#021934';
$primaryAccent = '#2c5dbd';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';
$cardBackground = '#ffffff';
$actionAdd = '#28a745';
$actionBilling = '#ffc107';
$actionViewRentList = '#17a2b8';
$actionViewTenantList = '#6f42c1';
$actionApartmentList = '#6c757d';
$actionScheduleCreate = '#832d31ff';
$actionScheduleDetails = '#fd7e14';
$actionMaintenance = '#dc3545'; // Used for Delete
$actionEdit = '#007bff'; // Blue for Edit

// --- Database Connection ---
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Handle POST Actions (Delete, Clear History) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // Action to delete a single meeting
    if ($action === 'delete_single' && isset($_POST['scheduleID'])) {
        $scheduleID = filter_input(INPUT_POST, 'scheduleID', FILTER_VALIDATE_INT);
        $stmt = $conn->prepare("DELETE FROM meeting_schedule WHERE scheduleID = ? AND landlord_id = ?");
        $stmt->bind_param("ii", $scheduleID, $landlord_id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Meeting successfully deleted.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error deleting meeting.'];
        }
        $stmt->close();
    }

    // Action to clear all past meeting history
    if ($action === 'clear_history') {
        $today_date = date('Y-m-d');
        $stmt = $conn->prepare("DELETE FROM meeting_schedule WHERE date < ? AND landlord_id = ?");
        $stmt->bind_param("si", $today_date, $landlord_id);
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Meeting history has been cleared.'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error clearing history.'];
        }
        $stmt->close();
    }
    
    // Redirect to prevent form resubmission
    header("Location: scheduleInfo.php");
    exit();
}


// --- Fetch Schedules ---
$today = date('Y-m-d');
$upcoming_schedules = [];
$past_schedules = [];

// Fetch Upcoming Meetings (today and future)
$stmt_upcoming = $conn->prepare("SELECT * FROM meeting_schedule WHERE landlord_id = ? AND date >= ? ORDER BY date ASC, time ASC");
$stmt_upcoming->bind_param("is", $landlord_id, $today);
$stmt_upcoming->execute();
$result_upcoming = $stmt_upcoming->get_result();
while ($row = $result_upcoming->fetch_assoc()) {
    $upcoming_schedules[] = $row;
}
$stmt_upcoming->close();

// Fetch Past Meetings (history)
$stmt_past = $conn->prepare("SELECT * FROM meeting_schedule WHERE landlord_id = ? AND date < ? ORDER BY date DESC, time DESC");
$stmt_past->bind_param("is", $landlord_id, $today);
$stmt_past->execute();
$result_past = $stmt_past->get_result();
while ($row = $result_past->fetch_assoc()) {
    $past_schedules[] = $row;
}
$stmt_past->close();
$conn->close();

// Get flash message from session
$flash_message = $_SESSION['flash_message'] ?? null;
if ($flash_message) {
    unset($_SESSION['flash_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Meeting Schedules - PropertyPilot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Copied all styles from your dashboard file for 100% consistency */
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: <?php echo $secondaryBackground; ?>; color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .main-top-navbar { background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0,0,0,0.2); z-index: 1001; position: fixed; top: 0; left: 0; width: 100%; height: 80px; }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; object-fit: contain; background: <?php echo $cardBackground; ?>; padding: 3px; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn { background-color: #dc3545; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar { display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>; padding: 20px 15px; color: <?php echo $textColor; ?>; z-index: 1000; width: 250px; height: 100%; overflow-y: hidden; }
        .vertical-sidebar .nav-links a { color: <?php echo $textColor; ?>; text-decoration: none; width:100%; text-align: left; padding: 8px 11px; margin: 6px 0; font-weight: 600; font-size: 16px; border-radius: 8px; display: flex; align-items: center; gap: 7px; transition: background-color 0.3s ease; }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        .vertical-sidebar .action-buttons { width: 100%; display: flex; flex-direction: column; gap: 7px; align-items: center; border-top: 1px solid rgba(255,255,255,0.1); }
        .vertical-sidebar .action-buttons h3 { color: <?php echo $textColor; ?>; font-size: 1.1em; margin-bottom: 10px; text-transform: uppercase; }
        .vertical-sidebar .action-link { width: calc(100% - 30px); padding: 9px 15px; border-radius: 8px; color: <?php echo $textColor; ?>; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: flex-start; gap: 10px; text-decoration: none; }
        .vertical-sidebar .link-tenant { background-color: <?php echo $actionAdd; ?>; }
        .vertical-sidebar .link-billing { background-color: <?php echo $actionBilling; ?>; }
        .vertical-sidebar .link-rent { background-color: <?php echo $actionViewRentList; ?>; }
        .vertical-sidebar .link-tenant-list { background-color: <?php echo $actionViewTenantList; ?>; }
        .vertical-sidebar .link-docs { background-color: <?php echo $actionApartmentList; ?>; }
        .vertical-sidebar .link-schedule-create { background-color: <?php echo $actionScheduleCreate; ?>; }
        .vertical-sidebar .link-schedule-details { background-color: <?php echo $actionScheduleDetails; ?>; }
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }

        /* New Styles for this page */
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        .section-header h3 { font-size: 1.8rem; color: #2c3e50; margin: 0; }
        .section-header i { margin-right: 12px; }
        .schedule-table { width: 100%; border-collapse: collapse; background: <?php echo $cardBackground; ?>; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.07); }
        .schedule-table th, .schedule-table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .schedule-table th { background-color: #f7f9fc; font-size: 14px; text-transform: uppercase; color: #555; }
        .schedule-table td { font-size: 15px; }
        .actions-cell { display: flex; gap: 10px; }
        .btn { padding: 6px 12px; font-size: 14px; font-weight: 600; border-radius: 5px; text-decoration: none; color: white; border: none; cursor: pointer; transition: opacity 0.2s ease; }
        .btn:hover { opacity: 0.85; }
        .btn i { margin-right: 5px; }
        .btn-edit { background-color: <?php echo $actionEdit; ?>; }
        .btn-delete { background-color: <?php echo $actionMaintenance; ?>; }
        .btn-clear-all { background-color: #34495e; }
        .flash-message { padding: 15px; margin-bottom: 20px; border-radius: 5px; color: white; text-align: center; }
        .flash-message.success { background-color: #28a745; }
        .flash-message.error { background-color: #dc3545; }
        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
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
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php">Dashboard</a>
                <a href="profile.html">Profile</a>
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
            <?php if ($flash_message): ?>
                <div class="flash-message <?php echo $flash_message['type']; ?>">
                    <?php echo htmlspecialchars($flash_message['text']); ?>
                </div>
            <?php endif; ?>

            <section id="upcoming-meetings">
                <div class="section-header">
                    <h3><i class="fas fa-calendar-day"></i> Upcoming Meetings</h3>
                </div>
                <table class="schedule-table">
                    <thead>
                        <tr><th>Tenant</th><th>Date & Time</th><th>Type</th><th style="width: 150px;">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($upcoming_schedules)): ?>
                            <tr><td colspan="4"><div class="empty-state">No upcoming meetings scheduled.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($upcoming_schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['name']); ?></td>
                                    <td><?php echo date("D, M j, Y", strtotime($schedule['date'])) . ' at ' . date("g:i A", strtotime($schedule['time'])); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['meetingType']); ?></td>
                                    <td class="actions-cell">
                                        <a href="edit_schedule.php?scheduleID=<?php echo $schedule['scheduleID']; ?>" class="btn btn-edit"><i class="fas fa-pencil-alt"></i> Edit</a>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this meeting?');">
                                            <input type="hidden" name="action" value="delete_single">
                                            <input type="hidden" name="scheduleID" value="<?php echo $schedule['scheduleID']; ?>">
                                            <button type="submit" class="btn btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <br><br>

            <section id="past-meetings">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Meeting History</h3>
                    <?php if (!empty($past_schedules)): ?>
                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to clear ALL meeting history? This cannot be undone.');">
                            <input type="hidden" name="action" value="clear_history">
                            <button type="submit" class="btn btn-clear-all"><i class="fas fa-broom"></i> Clear All History</button>
                        </form>
                    <?php endif; ?>
                </div>
                <table class="schedule-table">
                    <thead>
                        <tr><th>Tenant</th><th>Date & Time</th><th>Type</th><th style="width: 100px;">Action</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($past_schedules)): ?>
                            <tr><td colspan="4"><div class="empty-state">No meeting history found.</div></td></tr>
                        <?php else: ?>
                            <?php foreach ($past_schedules as $schedule): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['name']); ?></td>
                                    <td><?php echo date("D, M j, Y", strtotime($schedule['date'])) . ' at ' . date("g:i A", strtotime($schedule['time'])); ?></td>
                                    <td><?php echo htmlspecialchars($schedule['meetingType']); ?></td>
                                    <td class="actions-cell">
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this history record?');">
                                            <input type="hidden" name="action" value="delete_single">
                                            <input type="hidden" name="scheduleID" value="<?php echo $schedule['scheduleID']; ?>">
                                            <button type="submit" class="btn btn-delete"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
</body>
</html>