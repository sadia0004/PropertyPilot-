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


$message = '';
$message_type = '';


$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$rent_id = $_GET['rent_id'] ?? 0;
$total_amount = $_GET['amount'] ?? 0;
$landlord_id = $_GET['landlord_id'] ?? 0;
$payment_method = $_GET['payment_method'] ?? 'Card';

$transaction_id = uniqid('TXN-'); 


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    
    $paid_amount = filter_var($_POST['paid_amount'], FILTER_VALIDATE_FLOAT);
    $transaction_id_post = $_POST['transaction_id']; 

    if ($paid_amount > 0 && $paid_amount <= $total_amount) {
        $due_amount = $total_amount - $paid_amount;
        $transaction_status = ($due_amount <= 0) ? 'Paid' : 'Partially Paid';

        $conn->begin_transaction();
        try {
         
            $stmt_trans = $conn->prepare("INSERT INTO transactions (transaction_id, rent_id, tenant_id, landlord_id, amount, due_amount, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_trans->bind_param("siiiddss", $transaction_id_post, $rent_id, $tenant_id, $landlord_id, $paid_amount, $due_amount, $payment_method, $transaction_status);
            $stmt_trans->execute();
            $stmt_trans->close();

          
            $new_bill_status = ($due_amount <= 0) ? 'Paid' : 'Partially Paid';
            $stmt_update = $conn->prepare("UPDATE rentandbill SET previous_due = ?, rent_amount = 0, water_bill = 0, utility_bill = 0, guard_bill = 0, satus = ? WHERE rent_id = ?");
            $stmt_update->bind_param("dsi", $due_amount, $new_bill_status, $rent_id);
            $stmt_update->execute();
            $stmt_update->close();

            $conn->commit();
            $message = "Payment of ৳" . number_format($paid_amount, 2) . " was successful!";
            $message_type = 'success';

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "Payment failed. Please try again.";
            $message_type = 'error';
        }
    } else {
        $message = "Invalid payment amount.";
        $message_type = 'error';
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Complete Payment - PropertyPilot</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: <?php echo $secondaryBackground; ?>;
      color: #222; display: flex; flex-direction: column; height: 100vh; overflow: hidden; 
    }
    .main-top-navbar {
      background-color: <?php echo $primaryDark; ?>; color: <?php echo $textColor; ?>; padding: 15px 30px; display: flex;
      justify-content: space-between; align-items: center; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
      z-index: 1001; flex-shrink: 0; position: fixed; top: 0; left: 0; width: 100%; height: 80px;
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
    
    .payment-container {
        max-width: 500px;
        margin: 0 auto;
        background: #fff;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .payment-container h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #333;
        text-align: center;
        margin-bottom: 30px;
    }
    .payment-details { text-align: center; margin-bottom: 30px; }
    .payment-details .amount { font-size: 3rem; font-weight: 700; color: #2980b9; }
    .payment-details .method { font-size: 1.2rem; color: #555; }

    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #555; }
    .form-group input {
        width: 100%; padding: 12px; border: 1px solid #ccc;
        border-radius: 8px; font-size: 1rem;
    }
     .form-group input[readonly] { background-color: #e9ecef; cursor: not-allowed; }
    .btn-submit {
        display: block; width: 100%; padding: 15px; background-color: <?php echo $primaryAccent; ?>;
        color: white; border: none; border-radius: 8px; font-size: 1.1rem;
        font-weight: 600; cursor: pointer; transition: background-color 0.3s;
    }
    .btn-submit:hover { background-color: #248a75; }
    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; text-align: center; }
    .success { background-color: #d4edda; color: #155724; }
    .error { background-color: #f8d7da; color: #721c24; }
  </style>
</head>
<body>
  <header class="main-top-navbar">
    <div class="brand"><img src="image/logo.png" alt="PropertyPilot Logo" /> PropertyPilot</div>
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
                <a href="tprofile.php" class="active"><i class="fas fa-user-circle"></i> Profile</a>
                <a href="rentTransaction.php"><i class="fas fa-file-invoice-dollar"></i> Rent & Bills</a>
                <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                <a href="maintenanceRequest.php"><i class="fas fa-tools"></i> Maintenance</a>
      </div>
    </nav>

    <main>
        <div class="payment-container">
            <h1>Complete Your Payment</h1>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
                <a href="rentTransaction.php" style="display: block; text-align: center; margin-top: 20px; background-color: #2980b9; color: white; padding: 10px; border-radius: 5px; text-decoration: none;">Back to Bills</a>
            <?php else: ?>
                <div class="payment-details">
                    <div class="amount">৳<?php echo number_format($total_amount, 2); ?></div>
                    <div class="method">Payment Method: <?php echo htmlspecialchars($payment_method); ?></div>
                </div>
                <form method="POST">
                    <input type="hidden" name="rent_id" value="<?php echo $rent_id; ?>">
                    <input type="hidden" name="landlord_id" value="<?php echo $landlord_id; ?>">
                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                    <input type="hidden" name="payment_method" value="<?php echo $payment_method; ?>">
                    <input type="hidden" name="transaction_id" value="<?php echo $transaction_id; ?>">
                    
                    <div class="form-group">
                        <label for="tenant_name">Tenant Name</label>
                        <input type="text" id="tenant_name" value="<?php echo htmlspecialchars($fullName_session); ?>" readonly>
                    </div>
                     <div class="form-group">
                        <label for="transaction_date">Date</label>
                        <input type="date" id="transaction_date" readonly>
                    </div>
                     <div class="form-group">
                        <label for="transaction_id_display">Transaction ID</label>
                        <input type="text" id="transaction_id_display" value="<?php echo $transaction_id; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="paid_amount">Amount to Pay</label>
                        <input type="number" step="0.01" id="paid_amount" name="paid_amount" max="<?php echo $total_amount; ?>" placeholder="Enter amount to pay" required>
                    </div>

                    <?php if ($payment_method == 'Card'): ?>
                        <div class="form-group">
                            <label for="card_number">Card Number</label>
                            <input type="text" id="card_number" placeholder="xxxx xxxx xxxx xxxx" required>
                        </div>
                        <div class="form-group">
                            <label for="card_expiry">Transaction Date</label>
                            <input type="text" id="card_expiry" placeholder="MM/YY" required>
                        </div>
                        <div class="form-group">
                            <label for="card_cvc">CVC</label>
                            <input type="text" id="card_cvc" placeholder="123" required>
                        </div>
                    <?php else: ?>
                         <div class="form-group">
                            <label for="mobile_number">Mobile Number</label>
                            <input type="text" id="mobile_number" placeholder="Enter your mobile number" required>
                        </div>
                        <div class="form-group">
                            <label for="transaction_pin">PIN</label>
                            <input type="password" id="transaction_pin" placeholder="Enter your PIN" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="confirm_payment" class="btn-submit">Confirm Payment</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
  </div> 
  <script>
      document.addEventListener('DOMContentLoaded', function() {
          const today = new Date();
          const yyyy = today.getFullYear();
          const mm = String(today.getMonth() + 1).padStart(2, '0');
          const dd = String(today.getDate()).padStart(2, '0');
          document.getElementById('transaction_date').value = `${yyyy}-${mm}-${dd}`;
      });
  </script>
</body>
</html>
