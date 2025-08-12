<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Standardized session check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userRole = $_SESSION['userRole'] ?? 'tenant';
if ($userRole !== 'landlord') {
    die("Access Denied: This page is for landlords only.");
}
$landlord_id = $_SESSION['user_id'];

// Get schedule ID from URL. Redirect if not provided.
$schedule_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$schedule_id) {
    header("Location: scheduleInfo.php");
    exit();
}

// Color Palette
$primaryDark = '#021934';
$primaryAccent = '#2c5dbd';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';
$cardBackground = '#ffffff';
$actionUpdate = '#28a745'; // Green for Update button

$errorMsg = "";
$scheduleData = null;

// DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle POST request (form submission)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic validation
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $meetingType = $_POST['meetingType'] ?? 'In-Person';
    $description = $_POST['EventDescription'] ?? '';
    $posted_schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);

    if (empty($date) || empty($time) || !$posted_schedule_id) {
        $errorMsg = "❌ Date and Time are required.";
    } else {
        // Prepare UPDATE statement
        $updateQuery = "UPDATE meeting_schedule SET date = ?, time = ?, meetingType = ?, EventDescription = ? WHERE id = ? AND landlord_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssii", $date, $time, $meetingType, $description, $posted_schedule_id, $landlord_id);
        
        if ($stmt->execute()) {
            // Set success message in session and redirect
            $_SESSION['success_message'] = "✅ Schedule updated successfully!";
            header("Location: scheduleInfo.php");
            exit();
        } else {
            $errorMsg = "❌ Error updating schedule: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch the existing schedule data to populate the form
$fetchQuery = "SELECT * FROM meeting_schedule WHERE id = ? AND landlord_id = ?";
$stmt = $conn->prepare($fetchQuery);
$stmt->bind_param("ii", $schedule_id, $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$scheduleData = $result->fetch_assoc();
$stmt->close();
$conn->close();

// If schedule not found or doesn't belong to landlord, redirect.
if (!$scheduleData) {
    header("Location: scheduleInfo.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Schedule - PropertyPilot</title>
    <style>
        /* Re-use styles from your other pages for consistency */
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: <?php echo $secondaryBackground; ?>; }
        main { padding: 30px; }
        .form-container { max-width: 700px; margin: 0 auto; background: <?php echo $cardBackground; ?>; padding: 30px; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; margin-bottom: 10px; }
        h3 { text-align: center; margin-top: 0; margin-bottom: 25px; color: #555; font-weight: 500;}
        .form-container form { display: flex; flex-wrap: wrap; gap: 20px; }
        .form-field-group { flex-basis: 100%; display: flex; flex-direction: column; }
        .form-field-group.half-width { flex-basis: calc(50% - 10px); }
        .form-field-group label { margin-bottom: 8px; font-weight: 600; }
        .form-field-group input, .form-field-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; }
        .radio-group { display: flex; gap: 20px; align-items: center; padding-top: 10px; }
        .radio-group input[type="radio"] { width: auto; }
        .form-container button[type="submit"] { flex-basis: 100%; margin-top: 20px; padding: 15px; font-size: 18px; background: <?php echo $actionUpdate; ?>; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .error { width: 100%; padding: 12px; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin-bottom: 15px; text-align: center; }
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
                <input type="hidden" name="schedule_id" value="<?php echo $scheduleData['id']; ?>">

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
                    <div class="radio-group">
                        <input type="radio" id="typeInPerson" name="meetingType" value="In-Person" <?php if ($scheduleData['meetingType'] === 'In-Person') echo 'checked'; ?>>
                        <label for="typeInPerson">In-Person</label>
                        <input type="radio" id="typeOnline" name="meetingType" value="Online" <?php if ($scheduleData['meetingType'] === 'Online') echo 'checked'; ?>>
                        <label for="typeOnline">Online</label>
                    </div>
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