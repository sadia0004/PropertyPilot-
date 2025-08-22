<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


if (!isset($_SESSION['user_id']) || $_SESSION['userRole'] !== 'tenant') {
    header("Location: login.php");
    exit();
}
$tenant_id = $_SESSION['user_id'];


$fullName_session = $_SESSION['fullName'] ?? 'Tenant';
$profilePhoto_session = $_SESSION['profilePhoto'] ?? "default-avatar.png";


$primaryDark = '#1B3C53';
$primaryAccent = '#2CA58D';
$textColor = '#E0E0E0';
$secondaryBackground = '#F0F2F5';
$cardBackground = '#FFFFFF';


$message = '';
$message_type = '';


$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$landlord_id = null;
$property_id = null;
$tenant_info = [];

$infoQuery = "SELECT 
                u_tenant.fullName AS tenant_name, 
                u_tenant.phoneNumber,
                at.apartment_no, 
                at.landlord_id, 
                p.property_id,
                u_landlord.fullName AS landlord_name
              FROM users u_tenant
              JOIN addtenants at ON u_tenant.id = at.tenant_id
              JOIN properties p ON at.apartment_no = p.apartment_no AND at.landlord_id = p.landlord_id
              JOIN users u_landlord ON at.landlord_id = u_landlord.id
              WHERE u_tenant.id = ?";
$stmt_info = $conn->prepare($infoQuery);
$stmt_info->bind_param("i", $tenant_id);
$stmt_info->execute();
$infoResult = $stmt_info->get_result();
if ($infoRow = $infoResult->fetch_assoc()) {
    $tenant_info = $infoRow;
    $landlord_id = $infoRow['landlord_id'];
    $property_id = $infoRow['property_id'];
}
$stmt_info->close();


//Handle Maintenance Request Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($landlord_id) || empty($property_id)) {
        $message = "Could not submit request. Your apartment details are not fully configured.";
        $message_type = 'error';
    } else {
        $category = $_POST['issue_category'];
        $description = trim($_POST['issue_description']);
        $permissionToEnter = isset($_POST['permission_to_enter']) ? 1 : 0;
        $photoPath = null;

      
        if (isset($_FILES['photo_upload']) && $_FILES['photo_upload']['error'] == 0) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $fileName = time() . '_' . basename($_FILES['photo_upload']['name']);
            $targetFile = $uploadDir . $fileName;
            $allowTypes = array('jpg','png','jpeg','gif','webp');
            $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            if (in_array($fileType, $allowTypes)) {
                if (move_uploaded_file($_FILES["photo_upload"]["tmp_name"], $targetFile)) {
                    $photoPath = $targetFile;
                } else {
                    $message = "Error uploading file.";
                    $message_type = 'error';
                }
            } else {
                $message = "Invalid file type. Only JPG, PNG, JPEG, GIF, WEBP are allowed.";
                $message_type = 'error';
            }
        }

     
        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO maintenance_requests (tenant_id, landlord_id, property_id, issue_category, issue_description, photo, permission_to_enter, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("iiisssi", $tenant_id, $landlord_id, $property_id, $category, $description, $photoPath, $permissionToEnter);
            
            if ($stmt->execute()) {
                $message = "Maintenance request submitted successfully!";
                $message_type = 'success';
            } else {
                $message = "Error submitting request: " . $stmt->error;
                $message_type = 'error';
            }
            $stmt->close();
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
  <title>Maintenance Request - PropertyPilot</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: <?php echo $secondaryBackground; ?>;
      color: #222;
      display: flex;
      flex-direction: column;
      height: 100vh;
      overflow: hidden;
    }
    .main-top-navbar {
      background-color: <?php echo $primaryDark; ?>;
      color: <?php echo $textColor; ?>;
      padding: 15px 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      z-index: 1001;
      flex-shrink: 0;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 80px;
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
    
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .form-container h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
        text-align: center;
        margin-bottom: 30px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group.full-width {
        grid-column: 1 / -1;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #555;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 1rem;
    }
    .form-group input[readonly] {
        background-color: #e9ecef;
        cursor: not-allowed;
    }
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .btn-submit {
        display: block;
        width: 100%;
        padding: 15px;
        background-color: <?php echo $primaryAccent; ?>;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    .btn-submit:hover {
        background-color: #248a75;
    }
    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; }
    .success { background-color: #d4edda; color: #155724; }
    .error { background-color: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <header class="main-top-navbar">
    <div class="brand">
      <img src="image/logo.png" alt="PropertyPilot Logo" />
      PropertyPilot
    </div>
    <div class="top-right-user-info">
      <span class="welcome-greeting">👋 Welcome, <?php echo htmlspecialchars($fullName_session); ?></span>
      <img class="user-photo" src="<?php echo htmlspecialchars($profilePhoto_session); ?>" alt="Profile Photo">
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
        <div class="form-container">
            <h1>Submit a Maintenance Request</h1>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="tenant-name">Full Name</label>
                        <input type="text" id="tenant-name" value="<?php echo htmlspecialchars($tenant_info['tenant_name'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="unit-number">Apartment/Unit #</label>
                        <input type="text" id="unit-number" value="<?php echo htmlspecialchars($tenant_info['apartment_no'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="phone-number">Phone Number</label>
                        <input type="tel" id="phone-number" value="<?php echo htmlspecialchars($tenant_info['phoneNumber'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="landlord-name">Landlord</label>
                        <input type="text" id="landlord-name" value="<?php echo htmlspecialchars($tenant_info['landlord_name'] ?? ''); ?>" readonly>
                    </div>
                    <div class="form-group full-width">
                        <label for="issue_category">Issue Category</label>
                        <select name="issue_category" id="issue_category" required>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Appliances">Appliances</option>
                            <option value="HVAC">HVAC</option>
                            <option value="Pest Control">Pest Control</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="issue_description">Description of the Issue</label>
                        <textarea name="issue_description" id="issue_description" rows="4" required></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="photo_upload">Upload Photo (Optional)</label>
                        <input type="file" name="photo_upload" id="photo_upload" accept="image/*">
                    </div>
                    <div class="form-group full-width checkbox-group">
                        <input type="checkbox" name="permission_to_enter" id="permission_to_enter" value="1">
                        <label for="permission_to_enter">Grant permission for staff to enter if I'm not available</label>
                    </div>
                    <div class="form-group full-width">
                        <button type="submit" class="btn-submit">Submit Request</button>
                    </div>
                </div>
            </form>
        </div>
    </main>
  </div> 
</body>
</html>
