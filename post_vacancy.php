<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'landlord') {
    header("Location: login.php");
    exit();
}
$landlord_id = $_SESSION['user_id'];

$fullName = $_SESSION['fullName'] ?? 'Landlord';
$profilePhoto = $_SESSION['profilePhoto'] ?? "default-avatar.png";


$primaryDark = '#021934';
$primaryAccent = '#2c5dbd';
$textColor = '#f0f4ff';
$secondaryBackground = '#f0f4ff';
$cardBackground = '#ffffff';
$actionMaintenance = '#dc3545';

$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

$message = '';
$message_type = '';


$vacant_properties = [];
$stmt = $conn->prepare("
    SELECT p.property_id, p.apartment_no 
    FROM properties p
    LEFT JOIN vacancy_posts vp ON p.property_id = vp.property_id
    WHERE p.landlord_id = ? AND p.apartment_status = 'Vacant' AND vp.post_id IS NULL
");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $vacant_properties[] = $row;
}
$stmt->close();


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $property_id = $_POST['property_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);

    if (empty($property_id) || empty($title) || empty($location)) {
        $message = "Please select a property, provide a title, and set a location.";
        $message_type = 'error';
    } else {
        $conn->begin_transaction();
        try {
           
            $stmt = $conn->prepare("INSERT INTO vacancy_posts (landlord_id, property_id, title, description, location) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $landlord_id, $property_id, $title, $description, $location);
            $stmt->execute();
            $post_id = $stmt->insert_id;
            $stmt->close();

          
            if (isset($_FILES['photos']) && !empty(array_filter($_FILES['photos']['name']))) {
                $uploadDir = "uploads/properties/";
                if (!is_dir($uploadDir)) { mkdir($uploadDir, 0777, true); }

                foreach ($_FILES['photos']['name'] as $key => $name) {
                    if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['photos']['tmp_name'][$key];
                        $filename = time() . '_' . uniqid() . '_' . basename($name);
                        $uploadPath = $uploadDir . $filename;
                        if (move_uploaded_file($tmp_name, $uploadPath)) {
                            $imgStmt = $conn->prepare("INSERT INTO post_images (post_id, image_path) VALUES (?, ?)");
                            $imgStmt->bind_param("is", $post_id, $uploadPath);
                            $imgStmt->execute();
                            $imgStmt->close();
                        }
                    }
                }
            }
            
            $conn->commit();
            $message = "Vacancy posted successfully!";
            $message_type = 'success';

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "Failed to create post. Please try again.";
            $message_type = 'error';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Post Vacancy - PropertyPilot</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: <?php echo $secondaryBackground; ?>; color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .main-top-navbar { background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2); z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px; }
        .main-top-navbar .brand { display: flex; align-items: center; font-weight: 700; font-size: 22px; }
        .main-top-navbar .brand img { height: 50px; width: 50px; margin-right: 10px; border-radius: 50%; }
        .top-right-user-info { display: flex; align-items: center; gap: 20px; }
        .top-right-user-info .user-photo { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid <?php echo $textColor; ?>; }
        .top-right-user-info .logout-btn { background-color: <?php echo $actionMaintenance; ?>; color: <?php echo $textColor; ?>; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-weight: 600; }
        .dashboard-content-wrapper { display: flex; flex-grow: 1; margin-top: 80px; height: calc(100vh - 80px); overflow: hidden; }
        .vertical-sidebar { display: flex; flex-direction: column; align-items: flex-start; background-color: <?php echo $primaryDark; ?>; padding: 20px 15px; color: <?php echo $textColor; ?>; box-shadow: 2px 0 8px rgba(0, 0, 0, 0.2); z-index: 1000; flex-shrink: 0; width: 250px; height: 100%; }
        .vertical-sidebar .nav-links a { color: <?php echo $textColor; ?>; text-decoration: none; width:100%; text-align: left; padding: 12px 15px; margin: 8px 0; font-weight: 600; font-size: 16px; border-radius: 8px; display: flex; align-items: center; gap: 10px; }
        .vertical-sidebar .nav-links a:hover, .vertical-sidebar .nav-links a.active { background-color: <?php echo $primaryAccent; ?>; }
        main { flex-grow: 1; padding: 40px; height: 100%; overflow-y: auto; }
        .form-container { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 12px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { margin-bottom: 8px; font-weight: 600; display: block; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; }
        .form-group textarea { min-height: 120px; }
        .btn-submit { background-color: <?php echo $primaryAccent; ?>; color: white; padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: bold; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .message.success { background-color: #d4edda; color: #155724; }
        .message.error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <header class="main-top-navbar">
        <div class="brand"><img src="image/logo.png" alt="Logo"/> PropertyPilot</div>
        <div class="top-right-user-info">
            <span><?php echo htmlspecialchars($fullName); ?></span>
            <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Profile">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>
    <div class="dashboard-content-wrapper">
        <nav class="vertical-sidebar">
            <div class="nav-links">
                <a href="landlord_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="post_vacancy.php" class="active"><i class="fas fa-bullhorn"></i> Post Vacancy</a>
               
            </div>
        </nav>
        <main>
            <div class="form-container">
                <h1 class="text-3xl font-bold mb-6">Post a New Vacancy</h1>
                <?php if ($message): ?>
                    <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <?php endif; ?>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="property_id">Select a Vacant Property</label>
                        <select id="property_id" name="property_id" required>
                            <option value="">-- Choose an apartment --</option>
                            <?php foreach ($vacant_properties as $prop): ?>
                                <option value="<?php echo $prop['property_id']; ?>"><?php echo htmlspecialchars($prop['apartment_no']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="title">Post Title</label>
                        <input type="text" id="title" name="title" placeholder="e.g., 2 Bedroom Apartment in Gulshan" required>
                    </div>
                     <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" placeholder="e.g., Dhanmondi, Dhaka" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe the property, amenities, and nearby facilities."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="photos">Upload Photos (can select multiple)</label>
                        <input type="file" id="photos" name="photos[]" multiple accept="image/*">
                    </div>
                    <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Publish Post</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
