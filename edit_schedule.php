<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Standard session & role check
if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'landlord') {
    header("Location: login.php");
    exit();
}
$landlord_id = $_SESSION['user_id'];

// Get 'scheduleID' from the URL parameter
$schedule_id = filter_input(INPUT_GET, 'scheduleID', FILTER_VALIDATE_INT);
if (!$schedule_id) {
    header("Location: scheduleInfo.php");
    exit();
}

$errorMsg = "";
$scheduleData = null;

// DB Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle POST request to update the schedule
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $meetingType = $_POST['meetingType'] ?? 'In-Person';
    $description = $_POST['EventDescription'] ?? '';
    $posted_schedule_id = filter_input(INPUT_POST, 'scheduleID', FILTER_VALIDATE_INT);

    if (empty($date) || empty($time) || !$posted_schedule_id) {
        $errorMsg = "❌ Date and Time are required.";
    } else {
        $updateQuery = "UPDATE meeting_schedule SET date = ?, time = ?, meetingType = ?, EventDescription = ? WHERE scheduleID = ? AND landlord_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssii", $date, $time, $meetingType, $description, $posted_schedule_id, $landlord_id);
        
        if ($stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => '✅ Schedule updated successfully!'];
            header("Location: scheduleInfo.php");
            exit();
        } else {
            $errorMsg = "❌ Error updating schedule: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch the existing schedule data to pre-fill the form
$fetchQuery = "SELECT * FROM meeting_schedule WHERE scheduleID = ? AND landlord_id = ?";
$stmt = $conn->prepare($fetchQuery);
$stmt->bind_param("ii", $schedule_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$scheduleData = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$scheduleData) {
    header("Location: scheduleInfo.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Schedule - PropertyPilot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; background-color: #f0f4ff; }
        main { padding: 2rem; }
        .form-container { max-width: 700px; margin: auto; background: #fff; padding: 2rem 2.5rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #021934; }
        h3 { text-align: center; color: #555; font-weight: normal; margin-top: -10px; margin-bottom: 2rem; }
        form { display: flex; flex-wrap: wrap; gap: 20px; }
        .form-field-group { flex-basis: 100%; }
        .half-width { flex-basis: calc(50% - 10px); }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="date"], input[type="time"], textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; box-sizing: border-box; }
        .error { padding: 1rem; background-color: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 1.5rem; text-align: center; }

        /* --- STYLES FOR ACTION BUTTONS --- */
        .form-actions {
            flex-basis: 100%;
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .btn {
            flex-grow: 1;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.2s ease-in-out;
        }
        .btn i {
            margin-right: 8px;
        }

        /* Update button (Green) */
        .btn-update {
            background-color: #28a745;
            flex-grow: 2; /* Takes more space */
        }
        .btn-update:hover {
            background-color: #218838;
        }
        
        /* Back button (Grey) */
        .btn-back {
            background-color: #6c757d;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <main>
        <div class="form-container">
            <h2>Edit Meeting Schedule</h2>
            <h3>For Tenant: <?php echo htmlspecialchars($scheduleData['name']); ?></h3>

            <?php if ($errorMsg): ?>
                <div class="error"><?php echo $errorMsg; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="scheduleID" value="<?php echo $scheduleData['scheduleID']; ?>">

                <div class="form-field-group half-width">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" required value="<?php echo htmlspecialchars($scheduleData['date']); ?>">
                </div>

                <div class="form-field-group half-width">
                    <label for="time">Time</label>
                    <input type="time" id="time" name="time" required value="<?php echo htmlspecialchars($scheduleData['time']); ?>">
                </div>
                
                <div class="form-field-group">
                    <label>Meeting Type</label>
                    <input type="radio" id="typeInPerson" name="meetingType" value="In-Person" <?php if ($scheduleData['meetingType'] === 'In-Person') echo 'checked'; ?>>
                    <label for="typeInPerson" style="display:inline; font-weight:normal;">In-Person</label>
                    <input type="radio" id="typeOnline" name="meetingType" value="Online" <?php if ($scheduleData['meetingType'] === 'Online') echo 'checked'; ?>>
                    <label for="typeOnline" style="display:inline; font-weight:normal;">Online</label>
                </div>

                <div class="form-field-group">
                    <label for="EventDescription">Event Description</label>
                    <textarea id="EventDescription" name="EventDescription" rows="4"><?php echo htmlspecialchars($scheduleData['EventDescription']); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="scheduleInfo.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Go Back</a>
                    <button type="submit" class="btn btn-update"><i class="fas fa-save"></i> Update Schedule</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>