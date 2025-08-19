<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check for TENANT
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'tenant') {
    header("Location: login.php");
    exit();
}
$tenant_id = $_SESSION['user_id'];

// --- Define Color Palette from Tenant Dashboard ---
$primaryDark = '#1B3C53'; 
$primaryAccent = '#2CA58D';
$textColor = '#E0E0E0'; 
$secondaryBackground = '#F0F2F5';
$cardBackground = '#FFFFFF';

// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Fetch User Info ---
$fullName = "Tenant";
$profilePhoto = "default-avatar.png";
$queryUser = "SELECT fullName, profilePhoto FROM users WHERE id = ?";
$stmtUser = $conn->prepare($queryUser);
$stmtUser->bind_param("i", $tenant_id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
if ($rowUser = $resultUser->fetch_assoc()) {
    $fullName = $rowUser['fullName'];
    $profilePhoto = $rowUser['profilePhoto'] ?: "default-avatar.png";
}
$stmtUser->close();


// ✅ Handle Clear History Request using Sessions
if (isset($_GET['action']) && $_GET['action'] === 'clear_history') {
    $_SESSION['history_cleared'] = true;
    header("Location: tenant_notifications.php");
    exit();
}

// ✅ Fetch upcoming meeting schedules
$upcoming_notifications = [];
$query_upcoming = "
    SELECT ms.*, u.fullName AS landlord_name
    FROM meeting_schedule ms
    JOIN users u ON ms.landlord_id = u.id
    WHERE ms.tenant_id = ? AND CONCAT(ms.date, ' ', ms.time) >= NOW()
    ORDER BY ms.date ASC, ms.time ASC
";
$stmt_upcoming = $conn->prepare($query_upcoming);
$stmt_upcoming->bind_param("i", $tenant_id);
$stmt_upcoming->execute();
$result_upcoming = $stmt_upcoming->get_result();
while ($row = $result_upcoming->fetch_assoc()) {
    $upcoming_notifications[] = $row;
}
$stmt_upcoming->close();

// ✅ Fetch historical meeting schedules if not cleared
$history_notifications = [];
if (!isset($_SESSION['history_cleared']) || $_SESSION['history_cleared'] !== true) {
    $query_history = "
        SELECT ms.*, u.fullName AS landlord_name
        FROM meeting_schedule ms
        JOIN users u ON ms.landlord_id = u.id
        WHERE ms.tenant_id = ? AND CONCAT(ms.date, ' ', ms.time) < NOW()
        ORDER BY ms.date DESC, ms.time DESC
    ";
    $stmt_history = $conn->prepare($query_history);
    $stmt_history->bind_param("i", $tenant_id);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    while ($row = $result_history->fetch_assoc()) {
        $history_notifications[] = $row;
    }
    $stmt_history->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>My Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- Base styles from Tenant Dashboard --- */
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
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .welcome-greeting { font-size: 1.1em; font-weight: 500; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn {
          background-color: #dc3545; color: <?php echo $textColor; ?>; padding: 8px 15px;
          border-radius: 5px; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease;
        }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar {
          display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>;
          padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2);
          z-index: 1000; flex-shrink: 0; width: 250px; height: 100%;
        }
        .vertical-sidebar .nav-links a {
          color: <?php echo $textColor; ?>; text-decoration: none; width: 100%; text-align: left; padding: 12px 15px;
          margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px;
          transition: background-color 0.3s ease; display: flex; align-items: center; gap: 10px;
        }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }
        
        /* --- Styles specific to this Notifications page --- */
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 2.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
        .notifications-container { max-width: 900px; margin: 0 auto; }
        .notification-card {
            background-color: var(--card-background); border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 25px; overflow: hidden; border-left: 5px solid <?php echo $primaryAccent; ?>;
        }
        .card-header { padding: 20px; border-bottom: 1px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { margin: 0; font-size: 1.3rem; color: <?php echo $primaryDark; ?>; }
        .card-header .date-time { font-weight: 600; color: #555; }
        .card-body { padding: 25px; }
        .card-footer { padding: 15px 25px; background-color: #f8f9fa; text-align: right; }
        .info-item { display: flex; align-items: flex-start; gap: 15px; margin-bottom: 18px; }
        .info-item i { color: <?php echo $primaryAccent; ?>; width: 20px; text-align: center; font-size: 1.1rem; margin-top: 3px; }
        .info-item-content { display: flex; flex-direction: column; }
        .info-item-content span { font-weight: 600; color: #333; margin-bottom: 2px; }
        .info-item-content p { margin: 0; color: #666; line-height: 1.6; }
        .join-button {
            background-color: #28a745; color: white; text-decoration: none; padding: 10px 20px;
            border-radius: 8px; font-weight: bold; transition: background-color 0.3s ease; display: inline-block;
        }
        .join-button:hover { background-color: #218838; }
        .history-header { display: flex; justify-content: space-between; align-items: center; margin-top: 50px; }
        .clear-history-btn {
            background-color: #dc3545; color: white; border: none; padding: 8px 15px;
            text-decoration: none; font-size: 14px;
            border-radius: 6px; font-weight: bold; cursor: pointer; transition: background-color 0.3s ease;
        }
        .clear-history-btn:hover { background-color: #c82333; }
        .no-records { text-align: center; padding: 50px; font-size: 1.2rem; color: #777; background-color: var(--card-background); border-radius: 15px; }
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
                <a href="tenant_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="tprofile.php"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="rentTransaction.php"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
                <a href="tenant_notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a>
                <a href="maintenanceRequest.php"><i class="fas fa-tools"></i> Maintenance</a>
            </div>
        </nav>

        <main>
            <div class="notifications-container">
                <div class="page-header">
                    <h1>Upcoming Meetings</h1>
                </div>

                <?php if (empty($upcoming_notifications)): ?>
                    <p class="no-records">You have no upcoming meetings.</p>
                <?php else: ?>
                    <?php foreach ($upcoming_notifications as $note): ?>
                        <div class="notification-card">
                            <div class="card-header">
                                <h3>Meeting Scheduled</h3>
                                <div class="date-time">
                                    <i class="fas fa-calendar-alt"></i> <?php echo date("d M, Y", strtotime($note['date'])); ?> at <?php echo date("g:i A", strtotime($note['time'])); ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <i class="fas fa-user-tie"></i>
                                    <div class="info-item-content">
                                        <span>From Landlord</span>
                                        <p><?php echo htmlspecialchars($note['landlord_name']); ?></p>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-handshake"></i>
                                    <div class="info-item-content">
                                        <span>Meeting Type</span>
                                        <p><?php echo htmlspecialchars($note['meetingType']); ?></p>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-align-left"></i>
                                    <div class="info-item-content">
                                        <span>Description</span>
                                        <p><?php echo nl2br(htmlspecialchars($note['EventDescription'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php 
                            if ($note['meetingType'] === 'Online') {
                                $meeting_link = '';
                                preg_match('/(https?:\/\/[^\s]+)/', $note['EventDescription'], $matches);
                                if (!empty($matches[0])) {
                                    $meeting_link = $matches[0];
                                }

                                if ($meeting_link) {
                                    echo '<div class="card-footer">';
                                    echo '<a href="' . htmlspecialchars($meeting_link) . '" class="join-button" target="_blank"><i class="fas fa-video"></i> Join Meeting</a>';
                                    echo '</div>';
                                }
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="page-header history-header">
                    <h1>Meeting History</h1>
                    <?php if (!empty($history_notifications)): ?>
                        <a href="tenant_notifications.php?action=clear_history" class="clear-history-btn" onclick="return confirm('Are you sure you want to clear all past notifications? This will last for your current session.');">
                            <i class="fas fa-trash"></i> Clear All History
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($history_notifications)): ?>
                    <p class="no-records">Your meeting history is empty.</p>
                <?php else: ?>
                    <?php foreach ($history_notifications as $note): ?>
                        <div class="notification-card" style="border-left-color: #6c757d;">
                            <div class="card-header">
                                <h3>Meeting Expired</h3>
                                <div class="date-time">
                                    <i class="fas fa-calendar-check"></i> <?php echo date("d M, Y", strtotime($note['date'])); ?> at <?php echo date("g:i A", strtotime($note['time'])); ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <i class="fas fa-user-tie"></i>
                                    <div class="info-item-content">
                                        <span>From Landlord</span>
                                        <p><?php echo htmlspecialchars($note['landlord_name']); ?></p>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-handshake"></i>
                                    <div class="info-item-content">
                                        <span>Meeting Type</span>
                                        <p><?php echo htmlspecialchars($note['meetingType']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div> 
</body>
</html>
