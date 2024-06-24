
<?php
session_start();
if (isset($_SESSION['user_id'])) {
      $uid= $_SESSION['user_id'];
}

include 'cart_cal.php';
require('functions.inc.php');
$jsonLimit=array();

$type= get_safe_value($con,$_POST['type']);
if ($type == 'plus') {
  $id= get_safe_value($con,$_POST['id']);
  $limit = mysqli_fetch_array(mysqli_query($con,"select * from order_items where item_id='{$id}' and user_id='{$uid}' and status='added_in_cart'"));
$quantity = $limit['quantity'];
 // for first like
$cart_q = mysqli_query($con,"select * from items where id=$id ");  
$incp = mysqli_fetch_array($cart_q);
$price = $incp['price'];
  if ($quantity<5) {
$id= get_safe_value($con,$_POST['id']);
$up = mysqli_query($con,"update order_items set price_num=price_num+$price where item_id='{$id}' and user_id='{$uid}' and status='added_in_cart'");
$plus = mysqli_query($con,"update order_items set quantity=quantity+1 where item_id='{$id}' and user_id='{$uid}' and status='added_in_cart'");
}else{
  $jsonLimit=array('is_error'=>'yes','dd'=>'You can buy only 5 items at a time');
}
}else if($type == 'minus'){
  $id= get_safe_value($con,$_POST['id']);
  $limit = mysqli_fetch_array(mysqli_query($con,"select * from order_items where item_id='{$id}' and user_id='{$uid}' and status='added_in_cart'"));
$quantity = $limit['quantity'];
 // for first like
$cart_q = mysqli_query($con,"select * from items where id=$id ");  
$incp = mysqli_fetch_array($cart_q);
$price = $incp['price'];
  if ($quantity>1) {
    $id= get_safe_value($con,$_POST['id']);
 $ups = mysqli_query($con,"update order_items set price_num=price_num-$price where item_id='{$id}' and user_id='{$uid}' and status='added_in_cart' ");
 $minus = mysqli_query($con,"update order_items set quantity=quantity-1 where item_id='{$id}' and user_id='{$uid}' and status='added_in_cart'");
}
else{
  $jsonLimit=array('is_error'=>'yes','dd'=>'At least buy 1 item');
}
}else if($type == 'remove_item'){
  $id= get_safe_value($con,$_POST['id']);
     $uid= $_SESSION['user_id'];
    $delete_query="delete from order_items where user_id='{$uid}' and item_id=$id and status='added_in_cart' ";
    $delete_query_result=mysqli_query($con,$delete_query) or die(mysqli_error($con));
    
}
else if($type == 'notify'){
  $pid= $_POST['id'];
    $size=mysqli_query($con,"update notify set status='1' where  id='{$pid}'") or die(mysqli_error($con));
    
}else if($type == 'testimonial' && !empty($_POST['comments'])){
  $comment = $_POST['comments'];
  $insert_cmt = mysqli_query($con,"INSERT INTO testimonial (user_name,image,testimonial)values('$users','$image','$comment')");
  if ($insert_cmt) {
    $jsonLimit = array('error'=>'no','msg'=>'<p class="text-success">Review Listed .</p>');
  }else{
    $jsonLimit = array('error'=>'yes','msg'=>'Something went wrong');
  }
}



if ($type == "wishlist" || $type == 'sizes' || $type == 'color') {
  $user_id = $_SESSION['user_id'];

  if ($type == 'wishlist') {
      $pid = intval($_POST['pid']);
      
      // Fetch item details securely
      $stmt = mysqli_prepare($con, "SELECT * FROM items WHERE id = ?");
      mysqli_stmt_bind_param($stmt, "i", $pid);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);
      $item_id = mysqli_fetch_assoc($result);
      mysqli_stmt_close($stmt);

      if ($item_id) {
          $slug_id = $item_id['id'];

          // Check if the item is already in the wishlist or cart
          $stmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE (status = 'wishlist' OR status = 'added_in_cart') AND item_id = ? AND user_id = ?");
          mysqli_stmt_bind_param($stmt, "ss", $slug_id, $user_id);
          mysqli_stmt_execute($stmt);
          $result = mysqli_stmt_get_result($stmt);
          $count = mysqli_num_rows($result);
          mysqli_stmt_close($stmt);

          if ($count > 0) {
              $jsonLimit = array('error' => 'yes', 'msg' => 'Already in Wishlist or Cart');
          } else {
              // Add item to wishlist
              $item_ref_id = "TMC" . (date('m')) . bin2hex(random_bytes(3));
              $slug = $item_id['slug'];
              $price_num = $item_id['price'];
              $item_name = mysqli_real_escape_string($con, $item_id['name']);
              $size = $item_id['size'];
              $colors = $item_id['colors'];
              $type = $item_id['type'];
              $brief_info = $item_id['brief_info'];
              $image = $item_id['image'];
              $processed = date("Y/m/d");
              $status = 'wishlist';
              $delivered = 0;

              $stmt = mysqli_prepare($con, "INSERT INTO order_items (item_ref_id, slug, user_id, item_id, price_num, item_name, size, colors, type, brief_info, image, status, processed, delivered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");
              mysqli_stmt_bind_param($stmt, "sssidsssssssss", $item_ref_id, $slug, $user_id, $pid, $price_num, $item_name, $sid, $cid, $type, $brief_info, $image, $status, $processed, $delivered);
              mysqli_stmt_execute($stmt);

              if (mysqli_stmt_affected_rows($stmt) > 0) {
                  $jsonLimit = array('error' => 'no', 'msg' => 'Item wishlisted');
              } else {
                  $jsonLimit = array('error' => 'yes', 'msg' => 'Error occurred while wishlisting item');
              }

              mysqli_stmt_close($stmt);
          }
      } else {
          $jsonLimit = array('error' => 'yes', 'msg' => 'Invalid item ID');
      }
  }

  if ($type == 'sizes' || $type == 'color') {
      $sid = mysqli_real_escape_string($con, $_POST['sid']);
      $cid = mysqli_real_escape_string($con, $_POST['cid']);
      $pid = intval($_POST['pid']);

      // Fetch item details securely
      $stmt = mysqli_prepare($con, "SELECT * FROM items WHERE id = ?");
      mysqli_stmt_bind_param($stmt, "i", $pid);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);
      $item_id = mysqli_fetch_assoc($result);
      mysqli_stmt_close($stmt);

      if ($item_id) {
          $slug_id = $item_id['id'];

          // Check if the item is already in the wishlist or cart
          $stmt = mysqli_prepare($con, "SELECT * FROM order_items WHERE (status = 'wishlist' OR status = 'added_in_cart') AND item_id = ? AND user_id = ?");
          mysqli_stmt_bind_param($stmt, "is", $slug_id, $user_id);
          mysqli_stmt_execute($stmt);
          $result = mysqli_stmt_get_result($stmt);
          $count = mysqli_num_rows($result);
          mysqli_stmt_close($stmt);

          if ($count > 0) {
              if ($type == 'sizes') {
                  // Update size
                  $stmt = mysqli_prepare($con, "UPDATE order_items SET size = ? WHERE item_id = ? AND user_id = ?");
                  mysqli_stmt_bind_param($stmt, "sss", $sid, $slug_id, $user_id);
                  mysqli_stmt_execute($stmt);
                  mysqli_stmt_close($stmt);
                  $jsonLimit = array('error' => 'no', 'msg' => 'Size updated in Wishlist');
              } else if ($type == 'color') {
                  // Update color
                  $stmt = mysqli_prepare($con, "UPDATE order_items SET colors = ? WHERE item_id = ? AND user_id = ?");
                  mysqli_stmt_bind_param($stmt, "sss", $cid, $slug_id, $user_id);
                  mysqli_stmt_execute($stmt);
                  mysqli_stmt_close($stmt);
                  $jsonLimit = array('error' => 'no', 'msg' => 'Color updated in Wishlist');
              }
          } else if ($count < 1) {
              // Add item to wishlist with size/color
              $item_ref_id = "TMC" . (date('m')) . bin2hex(random_bytes(3));
              $slug = $item_id['slug'];
              $price_num = $item_id['price'];
              $item_name = $item_id['name'];
              $type = $item_id['type'];
              $brief_info = $item_id['brief_info'];
              $image = $item_id['image'];
              $processed = date("Y/m/d");
              $status = 'wishlist';
              $delivered = '';

              $stmt = mysqli_prepare($con, "INSERT INTO order_items (item_ref_id, slug, user_id, item_id, price_num, item_name, size, colors, type, brief_info, image, status, processed, delivered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,?)");
              mysqli_stmt_bind_param($stmt, "sssidsssssssss", $item_ref_id, $slug, $user_id, $pid, $price_num, $item_name, $sid, $cid, $type, $brief_info, $image, $status, $processed, $delivered);
              mysqli_stmt_execute($stmt);

              if (mysqli_stmt_affected_rows($stmt) > 0) {
                  $jsonLimit = array('error' => 'no', 'msg' => 'Item wishlisted');
              } else {
                  $jsonLimit = array('error' => 'yes', 'msg' => 'Error occurred while wishlisting item');
              }

              mysqli_stmt_close($stmt);
          }
      } else {
          $jsonLimit = array('error' => 'yes', 'msg' => 'Invalid item ID');
      }
  }
}




if ($type == 'pin' && isset($_POST['zip'])) {
  $pin = $_POST['zip'];
  $search_pin = mysqli_num_rows(mysqli_query($con,"select * from pin where pin=$pin"));
  if ($search_pin==1)
  {
  $jsonLimit = array('error'=>'no','msg'=>'<span class="text-success">Yes! We delivered here</span>');

  }else if ($search_pin==0)
  {
    $insert_pin = mysqli_query($con,"insert into pin(pin)values($pin)");
    $jsonLimit = array('error'=>'no','msg'=>'Sorry! Delivery not available');
  }
}

if ($type == 'Verifyupi') {
  $upid  = $_POST['upid'];
  $upi = mysqli_query($con,"select * from redcart where upi_id='$upid'");
  $count = mysqli_num_rows($upi);
  if ($count>0) {
    $jsonLimit = array('error'=>'no','msg'=>'<span class="text-success">Verified <i class="fa fa-check" aria-hidden="true"></i></span>');
  }else{
    $jsonLimit = array('error'=>'yes','msg'=>'<span class="text-danger">Upi Not found</span>');
  }
}

if ($type == 'geoloc') {
  $search  = $_POST['search'];
  $upi = mysqli_query($con,"select * from pin where pin='$search'");
  $count = mysqli_num_rows($upi);
  if ($count>0) {
    $jsonLimit = array('error'=>'no','msg'=>'<p class="text-success">Delivery available within 2 days <i class="fa fa-check" aria-hidden="true"></i></p>');
  }else{
    $jsonLimit = array('error'=>'yes','msg'=>'<p class="text-danger">Delivery not available on this location</p>');
  }
}

if ($type == 'knowmore') {
  $id  = $_POST['id'];
  $knowm = mysqli_query($con,"select * from trending_item where id='$id'");
  $item_data = mysqli_fetch_array($knowm);
  $count = mysqli_num_rows($knowm);
  if ($count>0) {
    $_SESSION['proID'] = $item_data['id'];
    $jsonLimit = array('is_error'=>'no','pid'=>$item_data['id'],'img'=>$item_data['image'],'price'=>$item_data['price'],'name'=>$item_data['name']);
  }
}

if ($type == 'reviews') {
  $reviews  = $_POST['reviews'];
  $img  = $_POST['img'];
  $usid  = $_POST['uid'];
  $pid = $_POST['pid'];
  $data_review = mysqli_query($con,"insert into product_review(image,uid,pid,review)values('$image','$usid','$pid','$reviews')");
  if ($data_review) {
    $jsonLimit = array('is_error'=>'no');
  }
}


echo json_encode($jsonLimit); 
 ?>
