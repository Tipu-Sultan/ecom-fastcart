<?php
include 'link.php';
include 'cart_cal.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Simulate delay
sleep(2);

// Validate transaction ID
if (isset($_SESSION['user_id']) && isset($_GET['txnId'])) {
    $uid = $_SESSION['user_id'];
    $txnId = filter_input(INPUT_GET, 'txnId', FILTER_SANITIZE_STRING);

    if (isset($_GET['order_id'])) {
        $orderId = filter_input(INPUT_GET, 'order_id', FILTER_SANITIZE_STRING);
        
        // Update the confirm table
        $stmt = $con->prepare("UPDATE confirm SET txn_id=?, status='confirmed' WHERE user_id=? AND order_id=?");
        $stmt->bind_param('sss', $txnId, $uid, $orderId);
        $stmt->execute();
        
    } else {
        // Check if transaction ID already exists
        $stmt = $con->prepare("SELECT * FROM confirm WHERE txn_id=?");
        $stmt->bind_param('s', $txnId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            date_default_timezone_set("Asia/Calcutta");
            $order_id = date('Ymdhisa') . bin2hex(random_bytes(2));
            $delivered = date("Y/m/d", strtotime(' +3 day'));

            // Update payment table with the new order ID
            $stmt = $con->prepare("UPDATE payment SET order_id=? WHERE payment_id=?");
            $stmt->bind_param('ss', $order_id, $txnId);
            $stmt->execute();

            // Update order_items table with new order ID and status
            $stmt = $con->prepare("UPDATE order_items SET order_id=?, cod='razorpay', delivered=?, processed='10', status='confirmed' WHERE user_id=? AND status='added_in_cart'");
            $stmt->bind_param('sss', $order_id, $delivered, $uid);
            $stmt->execute();

            // Retrieve updated cart data
            $stmt = $con->prepare("SELECT * FROM order_items WHERE order_id=? AND status='confirmed'");
            $stmt->bind_param('s', $order_id);
            $stmt->execute();
            $cart_data = $stmt->get_result();

            if ($cart_data->num_rows > 0) {
                $data = $cart_data->fetch_assoc();
                $email = $data['email'];
                $username = $data['username'];
                $address = $data['address'];
                $zip = $data['zip'];
                $cod = $data['cod'];
                $number = $_SESSION['mobile'];
                $image = $data['image'];

                $total_amount = calculateTotalGst($order_id, $con); 

                $subject = "Order Confirmation mail";
                $html = createOrderConfirmationHtml($username, $order_id, $total_amount); 

                include 'sendmail.php';
                if (sendmailTO($email, $html, $subject)) {
                    if (isset($_SESSION['COUPON_ID'])) {
                        $coupon_id = $_SESSION['COUPON_ID'];
                        $coupon_str = $_SESSION['COUPON_CODE'];
                        $coupon_value = $_SESSION['COUPON_VALUE'];
                        $cart_value = $total_amount-$coupon_value;

                    // Insert into confirm table
                      $stmt = $con->prepare("INSERT INTO confirm (order_id, txn_id, user_id, username, email, number, address, price, total_item, image, coupon_id, coupon_value, coupon_code, cod, zip, status, date) VALUES (?, 'COD', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                      $stmt->bind_param('ssssssiisiissis', $order_id,$uid,$username, $email, $number, $address, $cart_value, $total_cart, $image, $coupon_id, $coupon_value, $coupon_str, $cod, $zip, $delivered);
                      $stmt->execute();
                    } else {
                      $stmt = $con->prepare("INSERT INTO confirm (order_id, txn_id, user_id, username, email, number, address, price, total_item, image, cod, zip, status, date) VALUES (?, 'COD', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                      $stmt->bind_param('ssssssiissis', $order_id,$uid, $username, $email, $number, $address, $total_amount, $total_cart, $image, $cod, $zip, $delivered);
                      $stmt->execute();
                    }

                    // Update item quantities
                    $stmt = $con->prepare("SELECT item_id, quantity FROM order_items WHERE order_id=?");
                    $stmt->bind_param('s', $order_id);
                    $stmt->execute();
                    $items_result = $stmt->get_result();
                    while ($item = $items_result->fetch_assoc()) {
                        $item_id = $item['item_id'];
                        $quantity = $item['quantity'];
                        $stmt = $con->prepare("UPDATE items SET qty=qty-? WHERE id=?");
                        $stmt->bind_param('ii', $quantity, $item_id);
                        $stmt->execute();
                    }

                    // Send notification
                    $notify_message = "Hi $username, your order has been placed successfully.";
                    $stmt = $con->prepare("INSERT INTO notify (user_id, message) VALUES (?, ?)");
                    $stmt->bind_param('is', $uid, $notify_message);
                    $stmt->execute();

                    // Clear session variables related to coupon
                    unset($_SESSION['COUPON_ID'], $_SESSION['COUPON_CODE'], $_SESSION['COUPON_VALUE'], $_SESSION['cart_value']);

                    // Display success message
                    echo '<div class="container"><div class="alert alert-success alert-dismissible fade show" role="alert">
                            <strong></strong><span style="font-weight:bold;">An order confirmation mail has been sent to you</span>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                          </div></div>';
                } else {
                    // Display email sending failure message
                    echo '<div class="container"><div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong></strong><span style="font-weight:bold;">Email sending Failed..</span>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                          </div></div>';
                }
            } else {
                // Handle case when no cart data is found
                echo '<div class="container"><div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong></strong><span style="font-weight:bold;">No cart data found.</span>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                      </div></div>';
            }
        }
    }
}

// Clear coupon session variables if set
if (isset($_SESSION['COUPON_ID'])) {
    unset($_SESSION['COUPON_ID'], $_SESSION['COUPON_CODE'], $_SESSION['COUPON_VALUE'], $_SESSION['cart_value']);
}

// Check for transaction ID in GET parameters
if (isset($_GET['txnId'])) {
    $tid = filter_input(INPUT_GET, 'txnId', FILTER_SANITIZE_STRING);
    $stmt = $con->prepare("SELECT * FROM payment WHERE payment_id = ?");
    $stmt->bind_param('s', $tid);
    $stmt->execute();
    $result = $stmt->get_result();
    $pay = $result->fetch_assoc();

    if ($pay) {
        ?>
        <div class="container mt-2">
            <div class="card justify-content-center">
                <div class="card-header bg-dark">
                    <h3 class="text-success">Transaction successful</h3>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6 col-6">
                            <p>Name: <?php echo htmlspecialchars($pay['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-uppercase">ORDER ID: <?php echo htmlspecialchars($pay['order_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Amount: <?php echo htmlspecialchars($pay['amount'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Txn Id: <?php echo htmlspecialchars($pay['payment_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Date: <?php echo htmlspecialchars($pay['added_on'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Status: <span class="text-success"><?php echo htmlspecialchars($pay['payment_status'], ENT_QUOTES, 'UTF-8'); ?> <i class="fa fa-check" aria-hidden="true"></i></span></p>
                            <p><a href="http://localhost/fastcart/orders.php" class="btn btn-success btn-sm">Go to order page</a></p>
                        </div>
                        <div class="col-lg-6 col-6">
                            <img src="success.gif" class="tex-success">
                        </div>

                        <ul class="text-danger">
                            <li>If you have any issue with order or product then mail us at teepukhan729@gmail.com</li>
                            <li>** In case your payment has been deducted and the transaction was failed, kindly wait for 3 working days</li>
                        </ul>

                        <div class="sizes mt-3">
                            <h6 class="text-uppercase text-secondary">Share your experience with us</h6>

                            <label class="radio">
                                <input onclick="feed('EXCELLENT')" type="radio" name="feed" value="EXCELLENT">
                                <span>EXCELLENT</span>
                            </label>

                            <label class="radio">
                                <input onclick="feed('HAPPY')" type="radio" name="feed" value="HAPPY">
                                <span>HAPPY</span>
                            </label>

                            <label class="radio">
                                <input onclick="feed('OK')" type="radio" name="feed" value="OK">
                                <span>OK</span>
                            </label>

                            <label class="radio">
                                <input onclick="feed('UNHAPPY')" type="radio" name="feed" value="UNHAPPY">
                                <span>UNHAPPY</span>
                            </label>

                            <label class="radio">
                                <input onclick="feed('WORST')" type="radio" name="feed" value="WORST">
                                <span>WORST</span>
                            </label>

                            <input type="text" name="sid" id="pid" value="<?php echo htmlspecialchars($tid, ENT_QUOTES, 'UTF-8'); ?>" hidden>
                            <p id="feedmsg" class="mt-3 text-success"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="card-header">
                <h3 class="text-danger">Something went wrong</h3>
                <a href="http://localhost/html/cart.php">Back to cart page</a>
              </div>';
    }
}

function createOrderConfirmationHtml($username, $order_id, $total_amount) {
  if (isset($_SESSION['COUPON_ID'])) {
    $total_amount = $total_amount - $_SESSION['COUPON_VALUE'];
  }
  return '<div class="container">
      <div class="row">
          <div class="card">
              <div class="card-body">
                  <div class="modal-body">
                      <img src="https://tipusultan.epizy.com/images/mancode.jpg" style="width:632px; height:170px;border-radius:20px;">
                      <table class="table table-striped table-bordered mt-5">
                          <tr>
                              <td>Name :</td>
                              <td><h5>Hi, '.$username.'</h5></td>
                          </tr>
                          <tr>
                              <td>Message :</td>
                              <td>Your order placed successfully ORDER NO. :'.$order_id.'</td>
                          </tr>
                          <tr>
                              <td>Amount :</td>
                              <td><h5>Your total amount : '.$total_amount.'</h5></td>
                          </tr>
                          <tr>
                              <td>Link :</td>
                              <td><h5><strong><a href="http://localhost/fastcart/invoice?invoice='.$order_id.'">Click here to download invoice</a></strong></h5></td>
                          </tr>
                          <tr>
                              <td>Contact Us :</td>
                              <td>For further problems please contact this number 9919408817. Thank You</td>
                          </tr>
                      </table>                        
                  </div>
              </div>
          </div>
      </div>
  </div>';
}

// Function to calculate the total VAT for an order
function calculateTotalGst($order_id, $con)
{
    $gst_rate = 0.18; // GST rate of 18% (0.18)

    // Calculate total cart value
    $total_cart_value = calculateTotalCart($order_id, $con);

    // Calculate GST
    $total_gst = $total_cart_value * $gst_rate;

    // Total amount including GST
    $total_with_gst = $total_cart_value + $total_gst;

    return $total_with_gst;
}

// Function to calculate the total cart value for an order
function calculateTotalCart($order_id, $con) {
  $total_cart_value = 0.0;

  // Fetch the price and quantity of each item in the order
  $stmt = $con->prepare("SELECT price_num FROM order_items WHERE order_id = ?");
  $stmt->bind_param('s', $order_id);
  $stmt->execute();
  $result = $stmt->get_result();

  while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
      $total_cart_value += $row['price_num'];
  }

  return $total_cart_value;
}
?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script type="text/javascript">
function feed(feed) {
    $.ajax({
        url: 'feed.php',
        type: 'POST',
        data: {
            type: 'feed',
            fid: feed,
            pid: $("#pid").val(),
        },
        success: function (result) {
            var obj = JSON.parse(result);
            if (obj.error === "yes" || obj.error === "no") {
                $("#feedmsg").html(obj.msg);
            }
        }
    });
}
</script>

