<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if tenant ID not in session
if (!isset($_SESSION['pending_tenant_id'])) {
    header("Location: login.php");
    exit();
}

$tenantId = $_SESSION['pending_tenant_id'];

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $profession       = trim($_POST['profession']);
    $apartmentNo      = trim($_POST['apartmentNo']);
    $rentDate         = $_POST['rentDate'];
    $familyMembers    = (int)$_POST['familyMembers'];
    $emergencyContact = trim($_POST['emergencyContact']);

    // Insert tenant extra info linked with tenant_id = $tenantId
    $stmt = $conn->prepare("INSERT INTO tenants (tenant_id, profession, apartment_no, rent_date, family_members, emergency_contact) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssis", $tenantId, $profession, $apartmentNo, $rentDate, $familyMembers, $emergencyContact);
        if ($stmt->execute()) {
            // Tenant extra info saved
            unset($_SESSION['pending_tenant_id']);
            unset($_SESSION['pending_tenant_name']);
            $successMsg = "Tenant information saved successfully. You can now log in.";
        } else {
            $errorMsg = "Error saving tenant info: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $errorMsg = "Prepare failed: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Tenant Extra Information</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <form method="POST" class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md space-y-6">
        <h2 class="text-3xl font-bold text-indigo-700 text-center mb-6">Tenant Additional Details</h2>

        <?php if ($errorMsg): ?>
            <div class="text-red-600 bg-red-100 p-2 rounded"><?php echo $errorMsg; ?></div>
        <?php elseif ($successMsg): ?>
            <div class="text-green-700 bg-green-100 p-2 rounded"><?php echo $successMsg; ?></div>
            <div class="text-center mt-4">
                <a href="login.php" class="text-indigo-600 hover:underline font-semibold">Go to Login</a>
            </div>
        <?php endif; ?>

        <?php if (!$successMsg): ?>
        <div>
            <label for="profession" class="block mb-1 text-sm font-medium text-gray-700">Profession</label>
            <input type="text" id="profession" name="profession" placeholder="e.g., Software Engineer" required
                   class="w-full border px-4 py-2 rounded shadow-sm focus:ring-indigo-500">
        </div>

        <div>
            <label for="apartmentNo" class="block mb-1 text-sm font-medium text-gray-700">Rented Apartment No</label>
            <input type="text" id="apartmentNo" name="apartmentNo" placeholder="e.g., A-101" required
                   class="w-full border px-4 py-2 rounded shadow-sm focus:ring-indigo-500">
        </div>

        <div>
            <label for="rentDate" class="block mb-1 text-sm font-medium text-gray-700">Rent Start Date</label>
            <input type="date" id="rentDate" name="rentDate" required
                   class="w-full border px-4 py-2 rounded shadow-sm focus:ring-indigo-500">
        </div>

        <div>
            <label for="familyMembers" class="block mb-1 text-sm font-medium text-gray-700">Number of Family Members</label>
            <input type="number" id="familyMembers" name="familyMembers" placeholder="e.g., 3" min="0" required
                   class="w-full border px-4 py-2 rounded shadow-sm focus:ring-indigo-500">
        </div>

        <div>
            <label for="emergencyContact" class="block mb-1 text-sm font-medium text-gray-700">Emergency Contact</label>
            <input type="text" id="emergencyContact" name="emergencyContact" placeholder="e.g., John Doe - 555-123-4567" required
                   class="w-full border px-4 py-2 rounded shadow-sm focus:ring-indigo-500">
        </div>

        <div>
            <button type="submit"
                    class="w-full py-2 px-4 bg-indigo-600 text-white font-semibold rounded shadow hover:bg-indigo-700 transition">
                Save Details
            </button>
        </div>
        <?php endif; ?>
    </form>
</body>
</html>
