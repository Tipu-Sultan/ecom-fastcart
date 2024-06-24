<?php
session_start();

sleep(2);

require_once 'cart_cal.php';
require_once 'sendmail.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: index.php');
  exit();
}

date_default_timezone_set("Asia/Calcutta");

$uid = $_SESSION['user_id'];
$jsonLimit = array();
$type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);

if (isset($_POST['cod']) && $type === "payRequestforCod") {
  $order_id = date('Ymdhisa') . bin2hex(random_bytes(2));
  $delivered = date("Y/m/d", strtotime(' +3 day'));
  $cod = filter_input(INPUT_POST, 'cod', FILTER_SANITIZE_STRING);
  $number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_STRING);

  // Prepare and execute the update query
  $stmt = $con->prepare("UPDATE order_items SET order_id=?, delivered=?, processed='10', status='confirmed' WHERE user_id=? AND status='added_in_cart'");
  $stmt->bind_param('sss', $order_id, $delivered, $uid);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    // Prepare and execute the select query
    $stmt = $con->prepare("SELECT * FROM order_items WHERE order_id=? AND status='confirmed'");
    $stmt->bind_param('s', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_array(MYSQLI_ASSOC);

    if ($data) {
      $email = $data['email'];
      $username = $data['username'];
      $address = $data['address'];
      $zip = $data['zip'];
      $image = $data['image'];

      $total_amount = calculateTotalGst($order_id, $con); 

      $subject = "Order Confirmation email";
      $html = createOrderConfirmationHtml($username, $order_id, $total_amount); // Function to create HTML content

      if (sendmailTO($email, $html, $subject)) {
        if (isset($_SESSION['COUPON_ID'])) {
          $coupon_id = $_SESSION['COUPON_ID'];
          $coupon_str = $_SESSION['COUPON_CODE'];
          $coupon_value = $_SESSION['COUPON_VALUE'];
          $cart_value = $_SESSION['cart_value'];
          echo $cart_value;

          // Insert into confirm table
          $stmt = $con->prepare("INSERT INTO confirm (order_id, txn_id, user_id, username, email, number, address, price, total_item, image, coupon_id, coupon_value, coupon_code, cod, zip, status, date) VALUES (?, 'COD', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
          $stmt->bind_param('ssssssiisiissis', $order_id,$uid,$username, $email, $number, $address, $total_amount, $total_cart, $image, $coupon_id, $coupon_value, $coupon_str, $cod, $zip, $delivered);
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
        $result = $stmt->get_result();
        while ($item = $result->fetch_array(MYSQLI_ASSOC)) {
          $itemid = $item['item_id'];
          $qty = $item['quantity'];

          $update_stmt = $con->prepare("UPDATE items SET qty = qty - ? WHERE id = ?");
          $update_stmt->bind_param('ii', $qty, $itemid);
          $update_stmt->execute();
        }


        // Notify user
        $notify_stmt = $con->prepare("INSERT INTO notify (user_id, message) VALUES (?, ?)");
        $notify_msg = "Hi $username, your order has been placed successfully.";
        $notify_stmt->bind_param('ss', $uid, $notify_msg);
        $notify_stmt->execute();

        if ($notify_stmt->affected_rows > 0) {
          $jsonLimit = array('redirect' => 'yes', 'ord_msg' => 'order-id-' . $order_id);
        } else {
          $jsonLimit = array('redirect' => 'yes');
        }

        // Clear coupon session variables
        unset($_SESSION['COUPON_ID'], $_SESSION['COUPON_CODE'], $_SESSION['COUPON_VALUE'], $_SESSION['cart_value']);
      }
    }
  }
}

echo json_encode($jsonLimit);

// Function to create HTML content for order confirmation email
function createOrderConfirmationHtml($username, $order_id, $total_vat)
{
  return '<div class="container">
        <div class="row">
            <div class="card">
                <div class="card-body">
                    <div class="modal-body">
                        <img src="https://tipusultan.epizy.com/images/mancode.jpg" style="width:632px; height:170px;border-radius:20px;">
                        <table class="table table-striped table-bordered mt-5">
                            <tr>
                                <td>Name :</td>
                                <td><h5>Hi, ' . $username . '</h5></td>
                            </tr>
                            <tr>
                                <td>Message :</td>
                                <td>Your order placed successfully ORDER NO. :' . $order_id . '</td>
                            </tr>
                            <tr>
                                <td>Amount :</td>
                                <td><h5>Your total amount : ' . $total_vat . '</h5></td>
                            </tr>
                            <tr>
                                <td>Link :</td>
                                <td><h5><strong><a href="http://localhost/fastcart/invoice?invoice=' . $order_id . '">Click here to download invoice</a></strong></h5></td>
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
function calculateTotalCart($order_id, $con)
{
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

