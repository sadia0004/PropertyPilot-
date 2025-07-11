<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$successMsg = "";
$errorMsg = "";

// Form Handling
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName     = trim($_POST['fullName']);
    $email        = trim($_POST['email']);
    $phoneNumber  = trim($_POST['phoneNumber']);
    $nationalId   = trim($_POST['nationalId']);
    $userRole     = trim($_POST['userRole']);
    $rawPassword  = $_POST['password'];
    $profilePhoto = "";

  
    // Check for duplicate email or NID
$checkStmt = $conn->prepare("SELECT email, nationalId FROM users WHERE email = ? OR nationalId = ?");
if ($checkStmt) {
    $checkStmt->bind_param("ss", $email, $nationalId);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        // Now fetch the actual row to check what was duplicated
        $checkStmt->bind_result($existingEmail, $existingNid);
        while ($checkStmt->fetch()) {
            if ($email === $existingEmail) {
                $errorMsg = "This email is already registered.";
                break;
            }
            if ($nationalId === $existingNid) {
                $errorMsg = "This National ID is already registered.";
                break;
            }
        }
    }
    $checkStmt->close();
} else {
    $errorMsg = "Database error while checking existing user.";
}


    // If no error, proceed with registration
    if (empty($errorMsg)) {
        // Password hash
        $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

        // Handle profile photo
        if (isset($_FILES["profilePhoto"]) && $_FILES["profilePhoto"]["error"] === UPLOAD_ERR_OK) {
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = time() . "_" . basename($_FILES["profilePhoto"]["name"]);
            $uploadPath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES["profilePhoto"]["tmp_name"], $uploadPath)) {
                $profilePhoto = $uploadPath;
            }
        }

        // Insert new user
        $insertStmt = $conn->prepare("INSERT INTO users (fullName, email, phoneNumber, password, profilePhoto, nationalId, userRole) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($insertStmt) {
            $insertStmt->bind_param("sssssss", $fullName, $email, $phoneNumber, $hashedPassword, $profilePhoto, $nationalId, $userRole);
            if ($insertStmt->execute()) {
         $newUserId = $insertStmt->insert_id;

    // Store user ID temporarily in session for tenant extra info
    if ($userRole === 'tenant') {
        $_SESSION['pending_tenant_id'] = $newUserId;
        $_SESSION['pending_tenant_name'] = $fullName;
        header("Location: tenantExtra_info.php");
    } else {
        header("Location: login.php?registered=success");
    }
    exit();
          }

            $insertStmt->close();
        } else {
            $errorMsg = "Database error: could not prepare insert.";
        }
    }
}

$conn->close();
?>

<!--Registration Form -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Registration Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
<div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-lg">
    <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Register Your Account</h2>

    <?php if ($successMsg): ?>
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded"><?php echo $successMsg; ?></div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded"><?php echo $errorMsg; ?></div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">
    <div>
        <label for="fullName" class="block font-medium text-gray-700 mb-1">Full Name</label>
        <input type="text" id="fullName" name="fullName" placeholder="Full Name" required class="w-full px-4 py-2 border rounded">
    </div>

    <div>
        <label for="email" class="block font-medium text-gray-700 mb-1">Email Address</label>
        <input type="email" id="email" name="email" placeholder="Email Address" required class="w-full px-4 py-2 border rounded">
    </div>

    <div>
        <label for="phoneNumber" class="block font-medium text-gray-700 mb-1">Phone Number</label>
        <input type="tel" id="phoneNumber" name="phoneNumber" placeholder="Phone Number" class="w-full px-4 py-2 border rounded">
    </div>

    <div>
        <label for="password" class="block font-medium text-gray-700 mb-1">Password</label>
        <input type="password" id="password" name="password" placeholder="Password" required class="w-full px-4 py-2 border rounded">
    </div>

    <div>
        <label for="profilePhoto" class="block font-medium text-gray-700 mb-1">Profile Photo</label>
        <input type="file" id="profilePhoto" name="profilePhoto" accept="image/*" class="w-full text-sm text-gray-500">
    </div>

    <div>
        <label for="nationalId" class="block font-medium text-gray-700 mb-1">National ID</label>
        <input type="text" id="nationalId" name="nationalId" placeholder="National ID" required class="w-full px-4 py-2 border rounded">
    </div>

    <div>
        <label for="userRole" class="block font-medium text-gray-700 mb-1">User Role</label>
        <select id="userRole" name="userRole" required class="w-full px-4 py-2 border rounded">
            <option value="">Select Role</option>
            <option value="landlord">Landlord</option>
            <option value="tenant">Tenant</option>
        </select>
    </div>

    <button type="submit" class="w-full py-2 bg-indigo-600 text-white font-semibold rounded hover:bg-indigo-700">Register</button>
</form>

</div>
</body>
</html>
