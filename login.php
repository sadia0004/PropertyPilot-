<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// DB Connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";

$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errorMsg = "";
$successMsg = "";

// Check if redirected from registration
if (isset($_GET['registered']) && $_GET['registered'] === 'success') {
    $successMsg = "Registration successful! Please login below.";
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $inputPassword = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, fullName, password, profilePhoto, userRole FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $fullName, $hashedPassword, $profilePhoto, $userRole);
        $stmt->fetch();

        if (password_verify($inputPassword, $hashedPassword)) {
            // Store common session data
            $_SESSION['user_id'] = $id;
            $_SESSION['fullName'] = $fullName;
            $_SESSION['profilePhoto'] = $profilePhoto;
            $_SESSION['userRole'] = $userRole;

            // Store role-specific ID
            if ($userRole === "landlord") {
                $_SESSION['landlord_id'] = $id;
                header("Location: landlord_dashboard.php");
            } elseif ($userRole === "tenant") {
                $_SESSION['tenant_id'] = $id;
                header("Location: tenant_dashboard.php");
            } else {
                $errorMsg = "Invalid user role.";
            }
            exit();
        } else {
            $errorMsg = "Incorrect password.";
        }
    } else {
        $errorMsg = "User not found.";
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - PropertyPilot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
        <h2 class="text-3xl font-bold text-center mb-6 text-gray-800">Login to PropertyPilot</h2>

        <?php if ($successMsg): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded"><?php echo $successMsg; ?></div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded"><?php echo $errorMsg; ?></div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium mb-1 text-gray-700">Email</label>
                <input type="email" name="email" required placeholder="your@email.com"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium mb-1 text-gray-700">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm">
            </div>

            <button type="submit"
                    class="w-full py-2 px-4 bg-indigo-600 text-white rounded-md font-semibold hover:bg-indigo-700 transition">
                Login
            </button>
        </form>

        <p class="text-sm text-gray-500 mt-6 text-center">
            Don’t have an account? <a href="register_user.php" class="text-indigo-600 hover:underline">Register here</a>
        </p>
    </div>
</body>
</html>
