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

// CHANGED: Get 'scheduleID' from the URL parameter
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
    // CHANGED: Get 'scheduleID' from the hidden form field
    $posted_schedule_id = filter_input(INPUT_POST, 'scheduleID', FILTER_VALIDATE_INT);

    if (empty($date) || empty($time) || !$posted_schedule_id) {
        $errorMsg = "❌ Date and Time are required.";
    } else {
        // CHANGED: The WHERE clause now uses 'scheduleID'
        $updateQuery = "UPDATE meeting_schedule SET date = ?, time = ?, meetingType = ?, EventDescription = ? WHERE scheduleID = ? AND landlord_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssii", $date, $time, $meetingType, $description, $posted_schedule_id, $landlord_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "✅ Schedule updated successfully!";
            header("Location: scheduleInfo.php");
            exit();
        } else {
            $errorMsg = "❌ Error updating schedule: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch the existing schedule data to pre-fill the form
// CHANGED: The WHERE clause now uses 'scheduleID'
$fetchQuery = "SELECT * FROM meeting_schedule WHERE scheduleID = ? AND landlord_id = ?";
$stmt = $conn->prepare($fetchQuery);
$stmt->bind_param("ii", $schedule_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$scheduleData = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$scheduleData) {
    // Redirect if schedule doesn't exist or belong to the landlord
    header("Location: scheduleInfo.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Edit Schedule - PropertyPilot</title>
    <style>
        /* Your CSS styles remain the same */
        body { font-family: sans-serif; margin: 0; background-color: #f0f4ff; }
        main { padding: 2rem; }
        .form-container { max-width: 700px; margin: auto; background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #021934; }
        h3 { text-align: center; color: #555; font-weight: normal; margin-top: -10px; margin-bottom: 2rem; }
        form { display: flex; flex-wrap: wrap; gap: 20px; }
        .form-field-group { flex-basis: 100%; }
        .half-width { flex-basis: calc(50% - 10px); }
        label { display: block; margin-bottom: 8px; font-weight: bold; }
        input[type="date"], input[type="time"], textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 1rem; }
        button { width: 100%; padding: 15px; font-size: 1.1rem; color: #fff; background-color: #28a745; border: none; border-radius: 5px; cursor: pointer; }
        .error { padding: 1rem; background-color: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 1.5rem; text-align: center; }
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
                
                <button type="submit">Update Schedule</button>
            </form>
        </div>
    </main>
</body>
</html>