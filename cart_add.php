<?php
date_default_timezone_set("Asia/Calcutta");
session_start();

// Redirect to index if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index');
    exit();
}

// Check if item_id is set in the GET request
if (isset($_GET['item_id'])) {
    require 'themancode.php';

    $user_id = $_SESSION['user_id'];
    $item_id = mysqli_real_escape_string($con, $_GET['item_id']); // Sanitize input

    // Fetch item details using a prepared statement
    $items_query = $con->prepare("SELECT * FROM items WHERE id=? OR slug=?");
    $items_query->bind_param("ss", $item_id, $item_id);
    $items_query->execute();
    $items_result = $items_query->get_result();

    if ($items_result->num_rows == 0) {
        echo json_encode(['error' => 'yes', 'msg' => 'Item not found']);
        exit();
    }

    $items = $items_result->fetch_assoc();
    $iid = $items['id'];
    $slugid = $items['slug'];

    // Fetch cart list and added in cart details using prepared statements
    $cartlist_query = $con->prepare("SELECT * FROM order_items WHERE user_id=? AND status='wishlist' AND (slug=? OR item_id=?)");
    $cartlist_query->bind_param("ssi", $user_id, $slugid, $iid);
    $cartlist_query->execute();
    $wish_count = $cartlist_query->get_result()->num_rows;

    $added_in_cart_query = $con->prepare("SELECT * FROM order_items WHERE user_id=? AND status='added_in_cart' AND (slug=? OR item_id=?)");
    $added_in_cart_query->bind_param("ssi", $user_id, $slugid, $iid);
    $added_in_cart_query->execute();
    $add_count = $added_in_cart_query->get_result()->num_rows;

    // Prepare item details
    $item_ref_id = "TMC" . date('m') . bin2hex(random_bytes(3));
    $price_num = $items['price'];
    $slug = $items['slug'];
    $item_name = $items['name'];
    $size = $items['size'];
    $colors = $items['colors'];
    $type = $items['type'];
    $brief_info = $items['brief_info'];
    $image = $items['image'];
    $processed = date("Y/m/d");
    $status = 'wishlist';
    $delivered = 0;

    // Check if item is already in wishlist or added in cart
    if ($wish_count > 0 || $add_count > 0) {
        // Update item status to added in cart if already in wishlist
        $add_to_cart_query = $con->prepare("UPDATE order_items SET status='added_in_cart', quantity='1', processed='0', delivered=? WHERE user_id=? AND status='wishlist' AND item_id=?");
        $add_to_cart_query->bind_param("ssi", $processed, $user_id, $iid);
        $add_to_cart_result = $add_to_cart_query->execute();

        if ($add_to_cart_result) {
            header('Location: cart');
            exit();
        } else {
            $jsonLimit = ['error' => 'yes', 'msg' => 'Already in Wishlist'];
        }
    } else {
        // Insert new item into order_items table
        $add_to_copy = "INSERT INTO order_items (item_ref_id, slug, user_id, item_id, price_num, item_name, size, colors, type, brief_info, image, status, processed, delivered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $con->prepare($add_to_copy);

        if ($stmt) {
            $stmt->bind_param("sssidssssssss", $item_ref_id, $slug, $user_id, $iid, $price_num, $item_name, $size, $colors, $type, $brief_info, $image, $status, $processed, $delivered);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $jsonLimit = ['error' => 'no', 'msg' => 'Item wishlisted'];
            } else {
                $jsonLimit = ['error' => 'yes', 'msg' => 'Error occurred while wishlisting item'];
            }

            $stmt->close();
        } else {
            $jsonLimit = ['error' => 'yes', 'msg' => 'Error in prepared statement'];
        }
    }

    echo json_encode($jsonLimit);
} else {
    echo "<h2 style='text-align: center;margin-top: 100px;'>Please login <a href='#' data-mdb-toggle='modal' data-mdb-target='#login'>click here</a></h2>";
}

