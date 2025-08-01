<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ Check if landlord is logged in
if (!isset($_SESSION['landlord_id'])) {
    die("Unauthorized access. Please log in as a landlord.");
}

$landlord_id = $_SESSION['landlord_id']; // Fetch landlord ID from session

// ✅ DB connection
$host = "localhost";
$username = "root";
$password = "";
$database = "property";
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$errorMsg = ""; // Initialize error message
$successMsg = ""; // Initialize success message

// Variables to hold form values for repopulation on error
$tenantName_val = '';
$apartmentNo_val = ''; // Will be set from $_GET or $_POST for repopulation
$monthlyRent_val = '';
$familyMembers_val = '';
$emergencyContact_val = '';
$additionalInfo_val = '';


// Fetch apartment numbers for the dropdown (from the properties table)
$query = "SELECT apartment_no FROM properties WHERE landlord_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
$apartments = [];
while ($row = $result->fetch_assoc()) {
    $apartments[] = $row['apartment_no'];
}
$stmt->close();

// Fetch tenant details when apartment is selected via GET (initial page load or dropdown change)
if (isset($_GET['apartment_no'])) {
    $apartmentNo_val = $_GET['apartment_no']; // Set for repopulation

    // Fetch tenant details by joining tenants and users table based on tenant_id
    $tenantQuery = "SELECT t.tenant_id, u.fullName, t.family_members, t.emergency_contact
                    FROM tenants t
                    JOIN users u ON t.tenant_id = u.id
                    WHERE t.apartment_no = ?";
    $tenantStmt = $conn->prepare($tenantQuery);
    $tenantStmt->bind_param("s", $apartmentNo_val);
    $tenantStmt->execute();
    $tenantResult = $tenantStmt->get_result();

    if ($tenantResult->num_rows > 0) {
        $tenantDetails = $tenantResult->fetch_assoc();
        // Populate form values from fetched tenant details
        $tenantName_val = $tenantDetails['fullName'];
        $familyMembers_val = $tenantDetails['family_members'];
        $emergencyContact_val = $tenantDetails['emergency_contact'];
    }
    $tenantStmt->close();

    // Fetch rent details from the properties table
    $propertyQuery = "SELECT apartment_rent FROM properties WHERE apartment_no = ?";
    $propertyStmt = $conn->prepare($propertyQuery);
    $propertyStmt->bind_param("s", $apartmentNo_val);
    $propertyStmt->execute();
    $propertyResult = $propertyStmt->get_result();

    if ($propertyResult->num_rows > 0) {
        $apartmentRent = $propertyResult->fetch_assoc()['apartment_rent'];
        $monthlyRent_val = $apartmentRent; // Populate rent for form
    }
    $propertyStmt->close();
}


// Handle form submission to add a new tenant
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Re-collect values from POST for validation and repopulation
    $tenantName = trim($_POST['tenantName']);
    $apartmentNo = trim($_POST['apartmentNo']);
    $monthlyRent = filter_var($_POST['rent'], FILTER_VALIDATE_FLOAT); // Use float for decimal
    $familyMembers = filter_var($_POST['familyMembers'], FILTER_VALIDATE_INT);
    $emergencyContact = trim($_POST['emergencyContact']);
    $additionalInfo = trim($_POST['moreInfo']);

    // Repopulate form values with POST data for sticky form
    $tenantName_val = $tenantName;
    $apartmentNo_val = $apartmentNo;
    $monthlyRent_val = $_POST['rent']; // Use raw string for text input
    $familyMembers_val = $_POST['familyMembers']; // Use raw string for number input
    $emergencyContact_val = $emergencyContact;
    $additionalInfo_val = $additionalInfo;

    // --- Validation Checks ---
    if (empty($tenantName)) {
        $errorMsg = "Tenant Name is required.";
    } elseif (empty($apartmentNo)) {
        $errorMsg = "Apartment Number is required.";
    } elseif ($monthlyRent === false || $monthlyRent < 0) {
        $errorMsg = "Monthly Rent must be a valid non-negative number.";
    } elseif ($familyMembers === false || $familyMembers < 0) {
        $errorMsg = "Number of Family Members must be a non-negative integer.";
    } elseif (empty($emergencyContact)) {
        $errorMsg = "Emergency Contact is required.";
    } else {
        // 1. Fetch tenant_id from the 'users' table based on the provided tenantName.
        $getTenantIdQuery = "SELECT id FROM users WHERE fullName = ? AND userRole = 'tenant'";
        $getTenantIdStmt = $conn->prepare($getTenantIdQuery);
        if (!$getTenantIdStmt) {
            $errorMsg = "Prepare failed (get tenant ID): " . $conn->error;
        } else {
            $getTenantIdStmt->bind_param("s", $tenantName);
            $getTenantIdStmt->execute();
            $getTenantIdResult = $getTenantIdStmt->get_result();

            if ($getTenantIdResult->num_rows > 0) {
                $tenantData = $getTenantIdResult->fetch_assoc();
                $tenant_id_to_insert = $tenantData['id'];
            } else {
                $errorMsg = "Error: Tenant with the provided name not found or not registered as a tenant user.";
            }
            $getTenantIdStmt->close();
        }

        // Only proceed if no error from fetching tenant ID
        if (empty($errorMsg)) {
            // --- NEW VALIDATION: Check if apartment is already added for THIS landlord in 'addtenants' ---
            $checkAddTenantDuplicateStmt = $conn->prepare("SELECT COUNT(*) FROM addtenants WHERE landlord_id = ? AND apartment_no = ?");
            if ($checkAddTenantDuplicateStmt) {
                $checkAddTenantDuplicateStmt->bind_param("is", $landlord_id, $apartmentNo);
                $checkAddTenantDuplicateStmt->execute();
                $checkAddTenantDuplicateStmt->bind_result($duplicateCount);
                $checkAddTenantDuplicateStmt->fetch();
                $checkAddTenantDuplicateStmt->close();

                if ($duplicateCount > 0) {
                    $errorMsg = "This apartment (".$apartmentNo.") is already added to your tenants list.";
                }
            } else {
                $errorMsg = "Prepare failed (check addtenants duplicate): " . $conn->error;
            }
        }
    }

    // Only proceed with insert if there are no validation errors
    if (empty($errorMsg)) {
        // Insert into 'addtenants' table.
        // The 'addtenants' table schema does NOT have an 'emergency_contact' column.
        $stmt = $conn->prepare("INSERT INTO addtenants (tenant_id, name, apartment_no, monthly_rent, family_members, additional_info, landlord_id)
                                 VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            // 'i' for tenant_id (int), 's' for name, 's' for apartment_no, 'd' for monthly_rent (decimal/double),
            // 'i' for family_members (int), 's' for additional_info, 'i' for landlord_id (int)
            $stmt->bind_param("issdisi", $tenant_id_to_insert, $tenantName, $apartmentNo, $monthlyRent, $familyMembers, $additionalInfo, $landlord_id);

            if ($stmt->execute()) {
                $successMsg = "Tenant added successfully!";
                // Clear the values after successful submission to empty the form
                $tenantName_val = '';
                $apartmentNo_val = '';
                $monthlyRent_val = '';
                $familyMembers_val = '';
                $emergencyContact_val = '';
                $additionalInfo_val = '';

            } else {
                $errorMsg = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMsg = "Prepare failed (insert into addtenants): " . $conn->error;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add New Tenant</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100 p-4">
    <div class="bg-gradient-to-br from-indigo-500 to-purple-600 p-1 rounded-2xl shadow-2xl w-full max-w-2xl">
        <div class="bg-white p-8 rounded-2xl">
            <h1 class="text-3xl font-extrabold text-gray-900 mb-8 text-center">Add New Tenant</h1>

            <?php if (!empty($successMsg)): ?>
                <div class="text-green-700 bg-green-100 p-2 rounded mb-4"><?php echo htmlspecialchars($successMsg); ?></div>
            <?php elseif (!empty($errorMsg)): ?>
                <div class="text-red-600 bg-red-100 p-2 rounded mb-4"><?php echo htmlspecialchars($errorMsg); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="flex flex-wrap -mx-3 mb-6">
                    <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                        <label class="block text-sm font-semibold text-gray-700 mb-1" for="tenantName">Tenant Name</label>
                        <input type="text" name="tenantName" id="tenantName"
                            value="<?php echo htmlspecialchars($tenantName_val); ?>"
                            required placeholder="e.g., Jane Doe"
                            class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                    </div>
                    <div class="w-full md:w-1/2 px-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-1" for="apartmentNo">Apartment No.</label>
                        <select name="apartmentNo" id="apartmentNo" required class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300" onchange="getTenantInfo()">
                            <option value="">-- Select Apartment --</option>
                            <?php foreach ($apartments as $apartment): ?>
                                <option value="<?php echo htmlspecialchars($apartment); ?>"
                                    <?php echo ($apartmentNo_val === $apartment) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($apartment); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap -mx-3 mb-6">
                    <div class="w-full md:w-1/2 px-3 mb-6 md:mb-0">
                        <label class="block text-sm font-semibold text-gray-700 mb-1" for="monthlyRent">Monthly Rent</label>
                        <input type="number" step="0.01" name="rent" id="monthlyRent" value="<?php echo htmlspecialchars($monthlyRent_val); ?>" required placeholder="e.g., 1500.00" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                    </div>
                    <div class="w-full md:w-1/2 px-3">
                        <label class="block text-sm font-semibold text-gray-700 mb-1" for="familyMembers">Family Members</label>
                        <input type="number" name="familyMembers" id="familyMembers" value="<?php echo htmlspecialchars($familyMembers_val); ?>" required placeholder="e.g., 4" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                    </div>
                </div>

                <div class="px-3 mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="emergencyContact">Emergency Contact</label>
                    <input type="text" name="emergencyContact" id="emergencyContact" value="<?php echo htmlspecialchars($emergencyContact_val); ?>" required placeholder="e.g., John Doe - 555-123-4567" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300">
                </div>

                <div class="px-3 mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-1" for="moreInfo">Additional Info</label>
                    <textarea name="moreInfo" id="moreInfo" rows="3" class="block w-full px-4 py-2 border border-gray-300 rounded-xl shadow-sm focus:ring focus:ring-indigo-300" placeholder="Any specific notes or requests..."><?php echo htmlspecialchars($additionalInfo_val); ?></textarea>
                </div>

                <div class="px-3 mb-4">
                    <button type="submit" class="w-full py-3 px-4 bg-gradient-to-r from-indigo-600 to-purple-700 text-white font-bold rounded-xl shadow-md hover:scale-105 transition">Add Tenant</button>
                </div>

                <div class="text-center">
                    <a href="tenant_list.php" class="text-indigo-600 hover:underline">View All Tenants</a>
                </div>

                <div class="text-center mt-2">
                    <a href="landlord_dashboard.php" class="text-indigo-600 hover:underline">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function getTenantInfo() {
            const apartmentNo = document.getElementById('apartmentNo').value;
            if (apartmentNo !== '') {
                // Redirect to the same page with apartment_no in GET parameters
                window.location.href = `add_tenant.php?apartment_no=${apartmentNo}`;
            }
        }

        // This ensures the selected apartment number is retained after a page load (e.g., after form submission with errors)
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const selectedApartmentFromGet = urlParams.get('apartment_no');
            const apartmentNoDropdown = document.getElementById('apartmentNo');

            // Prioritize POST value if available (means form was submitted with error)
            const selectedApartmentValue = "<?php echo htmlspecialchars($apartmentNo_val); ?>";

            if (selectedApartmentValue !== '') {
                apartmentNoDropdown.value = selectedApartmentValue;
            } else if (selectedApartmentFromGet !== null) {
                apartmentNoDropdown.value = selectedApartmentFromGet;
            }
        };
    </script>
</body>
</html>  