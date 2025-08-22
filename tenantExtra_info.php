<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure a pending tenant's data exists in the session
if (!isset($_SESSION['pending_tenant_data'])) {
    header("Location: register_user.php");
    exit();
}

// ✅ Correctly get initial data from the session, not the database
$tenant_initial_data = $_SESSION['pending_tenant_data'];
$tenantName = $tenant_initial_data['fullName'];

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
$selectedLandlordId = "";
$availableApartments = [];

// Form field values to retain on POST back with errors
$profession_val = '';
$apartmentNo_val = '';
$rentDate_val = '';
$familyMembers_val = '';
$emergencyContact_val = '';

// Fetch landlords who have vacant apartments
$landlords = [];
$landlordQuery = "
    SELECT DISTINCT u.id, u.fullName
    FROM users u
    JOIN properties p ON u.id = p.landlord_id
    WHERE u.userRole = 'landlord' AND p.apartment_status = 'Vacant'
    ORDER BY u.fullName ASC
";
$landlordResult = $conn->query($landlordQuery);
if ($landlordResult) {
    while ($row = $landlordResult->fetch_assoc()) {
        $landlords[] = $row;
    }
}

// If a landlord is selected (via GET request for AJAX-like refresh)
if (isset($_GET['landlord_id']) && !empty($_GET['landlord_id'])) {
    $selectedLandlordId = (int)$_GET['landlord_id'];
    $apartmentQuery = "SELECT apartment_no FROM properties WHERE landlord_id = ? AND apartment_status = 'Vacant'";
    $stmt = $conn->prepare($apartmentQuery);
    if ($stmt) {
        $stmt->bind_param("i", $selectedLandlordId);
        $stmt->execute();
        $apartmentResult = $stmt->get_result();
        while ($row = $apartmentResult->fetch_assoc()) {
            $availableApartments[] = $row['apartment_no'];
        }
        $stmt->close();
    }
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $profession = trim($_POST['profession']);
    $apartmentNo = trim($_POST['apartmentNo']);
    $rentDate = $_POST['rentDate'];
    $familyMembers = filter_var($_POST['familyMembers'], FILTER_VALIDATE_INT);
    $emergencyContact = trim($_POST['emergencyContact']);
    $selectedLandlordId = (int)$_POST['landlordId'];

    // Retain form values on error
    $profession_val = $profession;
    $apartmentNo_val = $apartmentNo;
    $rentDate_val = $rentDate;
    $familyMembers_val = $_POST['familyMembers'];
    $emergencyContact_val = $emergencyContact;

    // Validation
    if (empty($profession) || empty($apartmentNo) || empty($rentDate) || empty($selectedLandlordId)) {
        $errorMsg = "Please fill in all required fields.";
    } else {
        // Use a transaction to ensure all data is saved together, or none at all
        $conn->begin_transaction();
        try {
            // 1. Insert the user data from the session into the `users` table
            $stmtUser = $conn->prepare("INSERT INTO users (fullName, email, phoneNumber, password, profilePhoto, nationalId, userRole) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtUser->bind_param("sssssss", 
                $tenant_initial_data['fullName'], 
                $tenant_initial_data['email'], 
                $tenant_initial_data['phoneNumber'], 
                $tenant_initial_data['hashedPassword'], 
                $tenant_initial_data['profilePhoto'], 
                $tenant_initial_data['nationalId'], 
                $tenant_initial_data['userRole']
            );
            $stmtUser->execute();
            $new_tenant_id = $stmtUser->insert_id;
            $stmtUser->close();

            // 2. Insert the extra info into the `tenants` table
            $stmtTenant = $conn->prepare("INSERT INTO tenants (tenant_id, name, profession, apartment_no, rent_date, family_members, emergency_contact) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtTenant->bind_param("issssis", $new_tenant_id, $tenantName, $profession, $apartmentNo, $rentDate, $familyMembers, $emergencyContact);
            $stmtTenant->execute();
            $stmtTenant->close();

            // 3. Update the property's status to 'Occupied'
            $updatePropStmt = $conn->prepare("UPDATE properties SET apartment_status = 'Occupied' WHERE apartment_no = ?");
            $updatePropStmt->bind_param("s", $apartmentNo);
            $updatePropStmt->execute();
            $updatePropStmt->close();

            // If everything is successful, commit the changes
            $conn->commit();

            // Clear the temporary session data and redirect to login
            unset($_SESSION['pending_tenant_data']);
            header("Location: login.php?registered=success");
            exit();

        } catch (mysqli_sql_exception $exception) {
            // If any step fails, roll back all changes
            $conn->rollback();
            $errorMsg = "Registration failed due to a database error. Please try again.";
        }
    }
    
    // Re-fetch apartments if there was an error to repopulate the dropdown
    if (!empty($errorMsg) && !empty($selectedLandlordId)) {
        $availableApartments = [];
        $apartmentQuery = "SELECT apartment_no FROM properties WHERE landlord_id = ? AND apartment_status = 'Vacant'";
        $stmt = $conn->prepare($apartmentQuery);
        if ($stmt) {
            $stmt->bind_param("i", $selectedLandlordId);
            $stmt->execute();
            $apartmentResult = $stmt->get_result();
            while ($row = $apartmentResult->fetch_assoc()) {
                $availableApartments[] = $row['apartment_no'];
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
    <title>Tenant Extra Information</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <form method="POST" class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-2xl mx-auto space-y-6">
        <h2 class="text-3xl font-bold text-indigo-700 text-center mb-2">Welcome, <?php echo htmlspecialchars($tenantName); ?>!</h2>
        <p class="text-center text-gray-600 mb-6">Just a few more details to complete your registration.</p>

        <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="landlordId" class="block text-sm font-medium text-gray-700 mb-1">Select Landlord</label>
                <select id="landlordId" name="landlordId" onchange="getApartments()" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select a landlord --</option>
                    <?php foreach ($landlords as $landlord): ?>
                        <option value="<?php echo htmlspecialchars($landlord['id']); ?>"
                            <?php echo ($selectedLandlordId == $landlord['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($landlord['fullName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="apartmentNo" class="block text-sm font-medium text-gray-700 mb-1">Select Apartment</label>
                <select id="apartmentNo" name="apartmentNo" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select a landlord first --</option>
                    <?php foreach ($availableApartments as $apartment): ?>
                        <option value="<?php echo htmlspecialchars($apartment); ?>"
                            <?php echo ($apartmentNo_val == $apartment) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($apartment); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="profession" class="block text-sm font-medium text-gray-700 mb-1">Profession</label>
                <input type="text" id="profession" name="profession" placeholder="e.g., Software Engineer" required
                       value="<?php echo htmlspecialchars($profession_val); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="rentDate" class="block text-sm font-medium text-gray-700 mb-1">Rent Start Date</label>
                <input type="date" id="rentDate" name="rentDate" required
                       value="<?php echo htmlspecialchars($rentDate_val); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="familyMembers" class="block text-sm font-medium text-gray-700 mb-1">Number of Family Members</label>
                <input type="number" id="familyMembers" name="familyMembers" placeholder="e.g., 3" min="0" required
                       value="<?php echo htmlspecialchars($familyMembers_val); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="emergencyContact" class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact</label>
                <input type="text" id="emergencyContact" name="emergencyContact" placeholder="e.g., John Doe - 555-123-4567" required
                       value="<?php echo htmlspecialchars($emergencyContact_val); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="w-full py-3 px-4 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-150 ease-in-out">
                Complete Registration
            </button>
        </div>
    </form>

    <script>
        function getApartments() {
            const landlordId = document.getElementById('landlordId').value;
            // This reloads the page with the selected landlord's ID to populate apartments
            window.location.href = `tenantExtra_info.php?landlord_id=${landlordId}`;
        }
    </script>
</body>
</html>
