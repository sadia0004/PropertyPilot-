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
$tenantName = "";
$selectedLandlordId = ""; // To hold the selected landlord_id from GET or POST
$availableApartments = []; // To hold apartments for the selected landlord

// Form field values to retain on POST back with errors
$profession_val = '';
$apartmentNo_val = '';
$rentDate_val = '';
$familyMembers_val = '';
$emergencyContact_val = '';


// Fetch tenant's name from the `users` table using `tenant_id`
$stmt = $conn->prepare("SELECT fullName FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $tenantId);
    $stmt->execute();
    $stmt->bind_result($tenantName);
    $stmt->fetch();
    $stmt->close();
} else {
    $errorMsg = "Prepare failed (fetch tenant name): " . $conn->error;
}

// Fetch only landlords who have at least one VACANT apartment
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
} else {
    $errorMsg = "Error fetching landlords with vacant apartments: " . $conn->error;
}

// If a landlord is selected (via GET request for AJAX or initial page load)
// This populates $availableApartments for the dropdown, ONLY WITH VACANT ONES
if (isset($_GET['landlord_id']) && !empty($_GET['landlord_id'])) {
    $selectedLandlordId = (int)$_GET['landlord_id'];

    // Fetch ONLY VACANT apartments for the selected landlord
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
    } else {
        $errorMsg = "Prepare failed (fetch vacant apartments for selected landlord): " . $conn->error;
    }
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Collect and sanitize input
    $profession         = trim($_POST['profession']);
    $apartmentNo        = trim($_POST['apartmentNo']);
    $rentDate           = $_POST['rentDate'];
    $familyMembers      = filter_var($_POST['familyMembers'], FILTER_VALIDATE_INT);
    $emergencyContact   = trim($_POST['emergencyContact']);
    $selectedLandlordId = (int)$_POST['landlordId']; // Get selected landlordId from POST

    // Populate values for form re-population on error
    $profession_val = $profession;
    $apartmentNo_val = $apartmentNo;
    $rentDate_val = $rentDate;
    $familyMembers_val = $_POST['familyMembers'];
    $emergencyContact_val = $emergencyContact;


    // --- Server-Side Validation ---
    if (empty($profession)) {
        $errorMsg = "Profession is required.";
    } elseif (empty($apartmentNo)) {
        $errorMsg = "Apartment Number is required.";
    } elseif (empty($rentDate)) {
        $errorMsg = "Rent Start Date is required.";
    } elseif (!strtotime($rentDate)) {
        $errorMsg = "Invalid Rent Start Date format.";
    } elseif ($familyMembers === false || $familyMembers < 0) {
        $errorMsg = "Number of Family Members must be a non-negative integer.";
    } elseif (empty($emergencyContact)) {
        $errorMsg = "Emergency Contact is required.";
    } elseif (empty($selectedLandlordId)) {
        $errorMsg = "Please select a landlord.";
    } else {
        // --- Advanced Apartment Status/Existence Checks ---
        $apartmentExistsAndVacant = false;

        // 1. Check if apartment_no exists, is linked to the landlord, AND is Vacant in properties table
        $propCheckStmt = $conn->prepare("SELECT COUNT(*) FROM properties WHERE apartment_no = ? AND landlord_id = ? AND apartment_status = 'Vacant'");
        if ($propCheckStmt) {
            $propCheckStmt->bind_param("si", $apartmentNo, $selectedLandlordId);
            $propCheckStmt->execute();
            $propCheckStmt->bind_result($countVacant);
            $propCheckStmt->fetch();
            $propCheckStmt->close();

            if ($countVacant > 0) {
                $apartmentExistsAndVacant = true;
            }
        } else {
            $errorMsg = "Prepare failed (check property vacant status): " . $conn->error;
        }

        if (!$apartmentExistsAndVacant) {
            // This error covers: apartment not found, not managed by landlord, or not vacant.
            $errorMsg = "The selected apartment is not available (either not found, not managed by this landlord, or already occupied). Please select a different apartment.";
        } else {
            // 2. If the apartment is truly vacant, now check if this specific tenant has already booked it in the `tenants` table.
            $checkTenantBookingStmt = $conn->prepare("SELECT COUNT(*) FROM tenants WHERE tenant_id = ? AND apartment_no = ?");
            if ($checkTenantBookingStmt) {
                $checkTenantBookingStmt->bind_param("is", $tenantId, $apartmentNo);
                $checkTenantBookingStmt->execute();
                $checkTenantBookingStmt->bind_result($count);
                $checkTenantBookingStmt->fetch();
                $checkTenantBookingStmt->close();

                if ($count > 0) {
                    $errorMsg = "You are already listed as renting this apartment. If you wish to update details, please go to your profile or contact your landlord.";
                }
            } else {
                $errorMsg = "Prepare failed (check tenant's existing booking): " . $conn->error;
            }
        }
    }

    // Only proceed with insert if there are no validation errors
    if (empty($errorMsg)) {
        // Insert tenant extra info into the `tenants` table
        $stmt = $conn->prepare("INSERT INTO tenants (tenant_id, name, profession, apartment_no, rent_date, family_members, emergency_contact) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("issssis", $tenantId, $tenantName, $profession, $apartmentNo, $rentDate, $familyMembers, $emergencyContact);
            if ($stmt->execute()) {
                // Tenant extra info saved
                unset($_SESSION['pending_tenant_id']);

                // IMPORTANT: Update the 'properties' table 'apartment_status' to 'Occupied'
                // as this tenant has now rented it.
                $updatePropertyStatusStmt = $conn->prepare("UPDATE properties SET apartment_status = 'Occupied' WHERE apartment_no = ?");
                if($updatePropertyStatusStmt) {
                    $updatePropertyStatusStmt->bind_param("s", $apartmentNo);
                    $updatePropertyStatusStmt->execute();
                    $updatePropertyStatusStmt->close();
                }

                $successMsg = "Tenant information saved successfully. You can now go to your dashboard.";
                // Optional: Redirect to tenant dashboard after success
                // header("Location: tenant_dashboard.php");
                // exit();
            } else {
                $errorMsg = "Error saving tenant info: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMsg = "Prepare failed (insert tenants): " . $conn->error;
        }
    }

    // If there was an error, re-fetch available apartments for the selected landlord
    // (only vacant ones) so the dropdown is correctly populated if the page reloads due to validation error.
    if (!empty($errorMsg) && !empty($selectedLandlordId)) {
        $availableApartments = []; // Clear previous list
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
        <h2 class="text-3xl font-bold text-indigo-700 text-center mb-6">Tenant Additional Details</h2>

        <?php if ($errorMsg): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($errorMsg); ?></span>
            </div>
        <?php elseif ($successMsg): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline"><?php echo htmlspecialchars($successMsg); ?></span>
            </div>
            <div class="text-center mt-4">
                <a href="tenant_dashboard.php" class="text-indigo-600 hover:underline font-semibold">Go to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (!$successMsg): // Only show form if not successfully submitted ?>
            <div class="mb-6">
                <label for="tenantName" class="block text-sm font-medium text-gray-700 mb-1">Tenant Name</label>
                <input type="text" id="tenantName" name="tenantName" value="<?php echo htmlspecialchars($tenantName); ?>" readonly
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm bg-gray-100 cursor-not-allowed">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="landlordId" class="block text-sm font-medium text-gray-700 mb-1">Select Landlord ID</label>
                    <select id="landlordId" name="landlordId" onchange="getApartments()" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Select Landlord ID --</option>
                        <?php foreach ($landlords as $landlord): ?>
                            <option value="<?php echo htmlspecialchars($landlord['id']); ?>"
                                <?php echo ($selectedLandlordId == $landlord['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($landlord['id'] . ' (' . $landlord['fullName'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="apartmentNo" class="block text-sm font-medium text-gray-700 mb-1">Rented Apartment No</label>
                    <select id="apartmentNo" name="apartmentNo" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">-- Select Apartment --</option>
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
                    Save Details
                </button>
            </div>
        <?php endif; ?>
    </form>

    <script>
        function getApartments() {
            const landlordId = document.getElementById('landlordId').value;
            if (landlordId) {
                // Ensure this URL matches your actual file name: 'tenantExtra_info.php'
                // This is the file that will handle fetching and displaying apartments
                window.location.href = `tenantExtra_info.php?landlord_id=${landlordId}`;
            } else {
                // Clear apartments if no landlord is selected
                document.getElementById('apartmentNo').innerHTML = '<option value="">-- Select Apartment --</option>';
            }
        }

        // This function runs when the page loads to retain form selections after a refresh or POST
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const selectedLandlord = urlParams.get('landlord_id');
            // For apartment number, we take the value from the PHP variable
            // which will be set if there was a POST and an error.
            const selectedApartment = "<?php echo htmlspecialchars($apartmentNo_val); ?>";

            const landlordIdDropdown = document.getElementById('landlordId');
            if (selectedLandlord && landlordIdDropdown) {
                landlordIdDropdown.value = selectedLandlord;
            }

            const apartmentNoDropdown = document.getElementById('apartmentNo');
            if (selectedApartment && apartmentNoDropdown) {
                // It's crucial to ensure options are loaded before trying to select one.
                // In this setup, PHP re-populates options on page load.
                // We just need to ensure the correct one is marked 'selected'.
                for (let i = 0; i < apartmentNoDropdown.options.length; i++) {
                    if (apartmentNoDropdown.options[i].value === selectedApartment) {
                        apartmentNoDropdown.options[i].selected = true;
                        break;
                    }
                }
            }
        };
    </script>
</body>
</html>