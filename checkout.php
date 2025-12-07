<?php
// Start session with extended timeout
session_set_cookie_params(86400);
session_start();

// Database Connection
$host = 'localhost';
$dbname = 'mobile_suking';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check login status - Redirect if not logged in
$isLoggedIn = isset($_SESSION['user_id']);
if(!$isLoggedIn) {
    header('Location: login-register.php?redirect=' . urlencode('checkout.php'));
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Handle session timeout
if(isset($_SESSION['last_activity'])) {
    $timeout = 86400;
    $timeSinceLastActivity = time() - $_SESSION['last_activity'];
    
    if($timeSinceLastActivity > $timeout) {
        session_unset();
        session_destroy();
        header('Location: login-register.php');
        exit();
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Function to optimize Cloudinary image URL
function optimizeCloudinaryImage($imageUrl, $width = 80, $height = 80) {
    // If not a Cloudinary URL, return as is
    if (strpos($imageUrl, 'cloudinary.com') === false) {
        return $imageUrl;
    }
    
    // Add Cloudinary transformation parameters for optimization
    $transformation = "w_{$width},h_{$height},c_fill,q_auto,f_auto";
    
    // Insert transformation into Cloudinary URL
    $optimizedUrl = str_replace('/upload/', "/upload/$transformation/", $imageUrl);
    
    return $optimizedUrl;
}

// Fetch user details
$userDetails = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("User details error: " . $e->getMessage());
}

// Function to get cart items with optimized images
function getCartItems($conn, $userId) {
    try {
        $stmt = $conn->prepare("
            SELECT c.*, p.name, p.brand, p.price, p.image_url, p.stock, 
                   p.original_price, p.color, p.storage
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = :user_id
            ORDER BY c.added_at DESC
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Optimize images for checkout display
        foreach($items as &$item) {
            $item['optimized_image'] = optimizeCloudinaryImage($item['image_url'], 80, 80);
            $item['has_discount'] = $item['original_price'] && $item['original_price'] > $item['price'];
            if($item['has_discount']) {
                $item['discount_amount'] = $item['original_price'] - $item['price'];
                $item['discount_percent'] = round(($item['discount_amount'] / $item['original_price']) * 100);
            }
        }
        
        return $items;
    } catch(PDOException $e) {
        error_log("Cart items error: " . $e->getMessage());
        return [];
    }
}

// Fetch cart items
$cartItems = getCartItems($conn, $userId);
$cartTotal = 0;
$itemCount = 0;

// Check if cart is empty
if(count($cartItems) === 0) {
    header('Location: cart.php');
    exit();
}

// Calculate totals
foreach($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $cartTotal += $itemTotal;
    $itemCount += $item['quantity'];
}

// Calculate order totals
$shipping = $cartTotal > 50000 ? 0 : 500;
$tax = $cartTotal * 0.15;
$grandTotal = $cartTotal + $shipping + $tax;

// Handle form submission
$orderPlaced = false;
$orderNumber = '';
$orderId = 0;
$errors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? 'Addis Ababa');
    $paymentMethod = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate required fields
    if(empty($fullName)) $errors[] = 'Full name is required';
    if(empty($phone)) $errors[] = 'Phone number is required';
    if(empty($address)) $errors[] = 'Address is required';
    if(empty($paymentMethod)) $errors[] = 'Payment method is required';
    
    // Validate phone number (Ethiopian format)
    if(!empty($phone) && !preg_match('/^(\+251|0)[0-9]{9}$/', $phone)) {
        $errors[] = 'Please enter a valid Ethiopian phone number (e.g., +251912345678 or 0912345678)';
    }
    
    if(count($errors) === 0) {
        try {
            // Generate order number
            $orderNumber = 'MS' . date('YmdHis') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Start transaction
            $conn->beginTransaction();
            
            // Create order
            $stmt = $conn->prepare("
                INSERT INTO orders (user_id, order_number, total_amount, payment_method, 
                                   payment_status, order_status, shipping_address, phone, notes)
                VALUES (:user_id, :order_number, :total_amount, :payment_method, 
                       'pending', 'pending', :shipping_address, :phone, :notes)
            ");
            
            $shippingAddress = $address . ', ' . $city;
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':order_number', $orderNumber);
            $stmt->bindParam(':total_amount', $grandTotal);
            $stmt->bindParam(':payment_method', $paymentMethod);
            $stmt->bindParam(':shipping_address', $shippingAddress);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            $orderId = $conn->lastInsertId();
            
            // Add order items and update stock
            foreach($cartItems as $item) {
                // Check stock availability
                if($item['stock'] < $item['quantity']) {
                    throw new Exception("Insufficient stock for {$item['name']}");
                }
                
                // Insert order item
                $stmt = $conn->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price)
                    VALUES (:order_id, :product_id, :quantity, :price)
                ");
                
                $stmt->bindParam(':order_id', $orderId);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':price', $item['price']);
                $stmt->execute();
                
                // Update product stock
                $stmt = $conn->prepare("
                    UPDATE products SET stock = stock - :quantity WHERE id = :product_id
                ");
                $stmt->bindParam(':quantity', $item['quantity']);
                $stmt->bindParam(':product_id', $item['product_id']);
                $stmt->execute();
            }
            
            // Clear cart
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            $orderPlaced = true;
            
            // Update user details if changed
            if($fullName !== ($userDetails['full_name'] ?? '') || $phone !== ($userDetails['phone'] ?? '') || $address !== ($userDetails['address'] ?? '')) {
                $stmt = $conn->prepare("
                    UPDATE users SET full_name = :full_name, phone = :phone, address = :address 
                    WHERE id = :id
                ");
                $stmt->bindParam(':full_name', $fullName);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
            }
            
        } catch(PDOException $e) {
            $conn->rollBack();
            $errors[] = 'Failed to place order. Please try again.';
            error_log("Order placement error: " . $e->getMessage());
        } catch(Exception $e) {
            $conn->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Mobile Suking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        /* Checkout Container */
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            flex: 1;
        }

        .checkout-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .checkout-header h1 {
            color: #667eea;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        /* Checkout Layout */
        .checkout-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Order Complete */
        .order-complete {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            grid-column: 1 / -1;
            animation: fadeIn 0.5s ease;
        }

        .order-complete i {
            font-size: 4rem;
            color: #2ecc71;
            margin-bottom: 1rem;
            animation: bounce 1s infinite alternate;
        }

        .order-details {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin: 2rem auto;
            max-width: 500px;
            text-align: left;
            border-left: 4px solid #2ecc71;
        }

        /* Checkout Form */
        .checkout-form {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .section-title {
            font-size: 1.3rem;
            color: #667eea;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-label .required {
            color: #ff4757;
            font-size: 0.9rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .payment-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-option:hover {
            border-color: #667eea;
            background-color: #f8f9ff;
            transform: translateY(-2px);
        }

        .payment-option.selected {
            border-color: #2ecc71;
            background-color: #f0f9f0;
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.1);
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .payment-option.selected .payment-icon {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
        }

        .payment-details {
            flex: 1;
        }

        .payment-name {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .payment-desc {
            font-size: 0.9rem;
            color: #666;
        }

        /* Payment Instructions */
        .payment-instructions {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            animation: slideIn 0.3s ease;
        }

        .payment-instructions h4 {
            color: #667eea;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-instructions ol {
            padding-left: 1.5rem;
            color: #555;
        }

        .payment-instructions li {
            margin-bottom: 0.5rem;
        }

        .payment-instructions strong {
            color: #2ecc71;
        }

        /* Order Summary */
        .order-summary {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .summary-title {
            font-size: 1.5rem;
            color: #667eea;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .order-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1.5rem;
            padding-right: 5px;
        }

        .order-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
            align-items: center;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            overflow: hidden;
            padding: 5px;
        }

        .item-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .item-details {
            flex: 1;
            min-width: 0;
        }

        .item-name {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            line-height: 1.3;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-brand {
            font-size: 0.8rem;
            color: #667eea;
            margin-bottom: 0.25rem;
        }

        .item-price {
            font-size: 0.9rem;
            color: #2ecc71;
            font-weight: 600;
        }

        .item-quantity {
            font-size: 0.8rem;
            color: #666;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
        }

        .item-total {
            font-weight: 700;
            color: #333;
            font-size: 1rem;
            white-space: nowrap;
        }

        .summary-totals {
            border-top: 2px solid #f0f0f0;
            padding-top: 1.5rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .total-row.grand-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2ecc71;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 2px solid #f0f0f0;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
        }

        .btn-place-order {
            flex: 2;
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            font-size: 1.1rem;
            padding: 15px;
            justify-content: center;
            border: none;
        }

        .btn-place-order:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }

        .btn-back {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            justify-content: center;
            border: none;
        }

        /* Error Messages */
        .error-messages {
            background-color: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease;
        }

        .error-messages ul {
            list-style: none;
            padding-left: 0;
        }

        .error-messages li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-messages li:before {
            content: '⚠️';
        }

        /* Footer */
        .footer {
            background-color: #2c3e50;
            color: white;
            padding: 2rem;
            margin-top: auto;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-10px); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.8rem;
            }
            
            .user-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .order-summary {
                position: static;
                max-height: none;
            }
        }

        @media (max-width: 480px) {
            .checkout-header h1 {
                font-size: 2rem;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-place-order, .btn-back {
                flex: 1;
                width: 100%;
            }
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 5px;
            color: white;
            font-weight: 600;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            display: none;
        }

        .notification.success {
            background-color: #2ecc71;
        }

        .notification.error {
            background-color: #e74c3c;
        }
    </style>
</head>
<body>
    <!-- Notification -->
    <div id="notification" class="notification"></div>

    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-mobile-alt"></i>
                Mobile Suking
            </a>
            
            <ul class="nav-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Products</a></li>
                <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                <li><a href="checkout.php" style="background-color: rgba(255,255,255,0.3);"><i class="fas fa-shopping-bag"></i> Checkout</a></li>
            </ul>
            
            <div class="user-actions">
                <span style="color: #ffcc00; font-weight: 600;">
                    <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($userName); ?>
                </span>
                <a href="user-dashboard.php" class="btn" style="background-color: transparent; color: white; border: 2px solid white;">
                    <i class="fas fa-user-circle"></i> Dashboard
                </a>
                <a href="logout.php" class="btn" style="background-color: transparent; color: white; border: 2px solid white;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <a href="cart.php" class="btn" style="background-color: #2ecc71; color: white;">
                    <i class="fas fa-shopping-cart"></i> 
                    <span id="cartCount"><?php echo $itemCount; ?></span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Checkout Container -->
    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Checkout</h1>
            <p>Complete your purchase with secure payment options</p>
        </div>

        <?php if($orderPlaced): ?>
            <!-- Order Complete -->
            <div class="order-complete">
                <i class="fas fa-check-circle"></i>
                <h2 style="color: #2ecc71; margin-bottom: 1rem;">Order Placed Successfully!</h2>
                <p style="color: #666; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Thank you for your purchase! Your order has been received and is being processed.
                    You will receive a confirmation email shortly.
                </p>
                
                <div class="order-details">
                    <h3 style="color: #667eea; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-receipt"></i> Order Details
                    </h3>
                    <div style="display: grid; gap: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600;">Order Number:</span>
                            <span style="color: #2ecc71; font-weight: 700; font-size: 1.1rem;"><?php echo $orderNumber; ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600;">Total Amount:</span>
                            <span style="color: #2ecc71; font-weight: 700; font-size: 1.2rem;">ETB <?php echo number_format($grandTotal, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 600;">Payment Method:</span>
                            <span>
                                <?php 
                                $paymentDisplay = [
                                    'telebirr' => 'Telebirr',
                                    'cash' => 'Cash on Delivery'
                                ];
                                echo $paymentDisplay[$paymentMethod] ?? ucfirst($paymentMethod);
                                ?>
                            </span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 600;">Order Status:</span>
                            <span style="color: #ff9f43; font-weight: 600;">Processing</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 600;">Estimated Delivery:</span>
                            <span style="color: #667eea;"><?php echo date('F j, Y', strtotime('+3 days')); ?></span>
                        </div>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 2rem; flex-wrap: wrap;">
                    <a href="user-dashboard.php?tab=orders" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <i class="fas fa-clipboard-list"></i> View Orders
                    </a>
                    <a href="products.php" class="btn" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white;">
                        <i class="fas fa-shopping-bag"></i> Continue Shopping
                    </a>
                    <a href="index.php" class="btn" style="background: linear-gradient(135deg, #ff9f43 0%, #ff7f00 100%); color: white;">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Checkout Form -->
            <div class="checkout-layout">
                <!-- Left Column - Checkout Form -->
                <div class="checkout-form">
                    <?php if(count($errors) > 0): ?>
                        <div class="error-messages">
                            <h4 style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-exclamation-triangle"></i> Please fix the following errors:
                            </h4>
                            <ul>
                                <?php foreach($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="checkoutForm" novalidate>
                        <!-- Shipping Information -->
                        <div class="form-section">
                            <h2 class="section-title">
                                <i class="fas fa-shipping-fast"></i> Shipping Information
                            </h2>
                            <div class="form-group">
                                <label class="form-label">
                                    Full Name <span class="required">* Required</span>
                                </label>
                                <input type="text" name="full_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($userDetails['full_name'] ?? ''); ?>" 
                                       required
                                       placeholder="Enter your full name">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        Email Address <span class="required">* Required</span>
                                    </label>
                                    <input type="email" name="email" class="form-input" 
                                           value="<?php echo htmlspecialchars($userEmail); ?>" 
                                           required readonly style="background-color: #f5f5f5;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">
                                        Phone Number <span class="required">* Required</span>
                                    </label>
                                    <input type="tel" name="phone" class="form-input" 
                                           value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>" 
                                           required
                                           placeholder="+251912345678 or 0912345678"
                                           pattern="^(\+251|0)[0-9]{9}$">
                                    <small style="display: block; margin-top: 5px; color: #666;">
                                        Format: +251912345678 or 0912345678
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    Street Address <span class="required">* Required</span>
                                </label>
                                <input type="text" name="address" class="form-input" 
                                       value="<?php echo htmlspecialchars($userDetails['address'] ?? ''); ?>" 
                                       required
                                       placeholder="Street name, building, apartment">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">
                                        City <span class="required">* Required</span>
                                    </label>
                                    <input type="text" name="city" class="form-input" 
                                           value="Addis Ababa" 
                                           required readonly 
                                           style="background-color: #f5f5f5;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Additional Notes</label>
                                    <textarea name="notes" class="form-textarea" 
                                              placeholder="Special instructions, delivery notes, etc."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="form-section">
                            <h2 class="section-title">
                                <i class="fas fa-credit-card"></i> Payment Method
                            </h2>
                            <div class="payment-methods">
                                <div class="payment-option <?php echo ($_POST['payment_method'] ?? '') === 'telebirr' ? 'selected' : ''; ?>" 
                                     onclick="selectPayment('telebirr')">
                                    <div class="payment-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">Telebirr</div>
                                        <div class="payment-desc">Mobile money payment - Instant confirmation</div>
                                    </div>
                                    <input type="radio" name="payment_method" value="telebirr" 
                                           <?php echo ($_POST['payment_method'] ?? '') === 'telebirr' ? 'checked' : ''; ?>
                                           style="display: none;" required>
                                </div>
                                
                                <div class="payment-option <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>" 
                                     onclick="selectPayment('cash')">
                                    <div class="payment-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">Cash on Delivery</div>
                                        <div class="payment-desc">Pay when you receive - No extra fees</div>
                                    </div>
                                    <input type="radio" name="payment_method" value="cash" 
                                           <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'checked' : ''; ?>
                                           style="display: none;" required>
                                </div>
                            </div>
                            
                            <!-- Payment Instructions -->
                            <div id="telebirrInstructions" class="payment-instructions" style="display: <?php echo ($_POST['payment_method'] ?? '') === 'telebirr' ? 'block' : 'none'; ?>;">
                                <h4><i class="fas fa-info-circle"></i> Telebirr Payment Instructions:</h4>
                                <ol>
                                    <li>Open your <strong>Telebirr app</strong> on your phone</li>
                                    <li>Go to <strong>"Send Money"</strong> section</li>
                                    <li>Enter Phone Number: <strong>+251 911 223 344</strong></li>
                                    <li>Enter Amount: <strong>ETB <?php echo number_format($grandTotal, 2); ?></strong></li>
                                    <li>Add note: <strong>"Order <?php echo $orderNumber ? $orderNumber : 'MS' . date('YmdHis'); ?>"</strong></li>
                                    <li>Complete the transaction and keep the receipt</li>
                                </ol>
                                <p style="margin-top: 10px; color: #2ecc71; font-weight: 600;">
                                    <i class="fas fa-check-circle"></i> Your order will be confirmed instantly after payment
                                </p>
                            </div>
                            
                            <div id="cashInstructions" class="payment-instructions" style="display: <?php echo ($_POST['payment_method'] ?? '') === 'cash' ? 'block' : 'none'; ?>;">
                                <h4><i class="fas fa-info-circle"></i> Cash on Delivery Information:</h4>
                                <p>Pay with cash when your order is delivered. Our delivery agent will provide you with an official receipt.</p>
                                <ul style="padding-left: 1.5rem; margin-top: 10px;">
                                    <li>Delivery within <strong>3-5 business days</strong> in Addis Ababa</li>
                                    <li>Please have exact amount ready for faster delivery</li>
                                    <li>You can inspect the products before payment</li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="form-section" style="border-bottom: none;">
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <input type="checkbox" id="terms" name="terms" required style="margin-top: 5px;">
                                <label for="terms" style="font-size: 0.9rem; color: #666;">
                                    I agree to the <a href="terms.php" style="color: #667eea;">Terms and Conditions</a> 
                                    and <a href="privacy.php" style="color: #667eea;">Privacy Policy</a>. 
                                    I understand that my order is subject to availability and confirmation.
                                </label>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <a href="cart.php" class="btn btn-back">
                                <i class="fas fa-arrow-left"></i> Back to Cart
                            </a>
                            <button type="submit" class="btn btn-place-order" id="placeOrderBtn">
                                <i class="fas fa-lock"></i> Place Order - ETB <?php echo number_format($grandTotal, 2); ?>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Right Column - Order Summary -->
                <div class="order-summary">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i> Order Summary
                    </h2>
                    
                    <div class="order-items">
                        <?php foreach($cartItems as $item): 
                            $itemTotal = $item['price'] * $item['quantity'];
                        ?>
                        <div class="order-item">
                            <div class="item-image">
                                <!-- OPTIMIZED CLOUDINARY IMAGE -->
                                <img 
                                    src="<?php echo htmlspecialchars($item['optimized_image']); ?>" 
                                    alt="<?php echo htmlspecialchars($item['name']); ?>"
                                    loading="lazy"
                                    width="80"
                                    height="80"
                                    onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=80&h=80&fit=crop'"
                                >
                            </div>
                            <div class="item-details">
                                <div class="item-name" title="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </div>
                                <div class="item-brand"><?php echo ucfirst($item['brand']); ?></div>
                                <div class="item-price">ETB <?php echo number_format($item['price'], 2); ?></div>
                                <div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
                            </div>
                            <div class="item-total">
                                ETB <?php echo number_format($itemTotal, 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-totals">
                        <div class="total-row">
                            <span>Subtotal (<?php echo $itemCount; ?> items):</span>
                            <span>ETB <?php echo number_format($cartTotal, 2); ?></span>
                        </div>
                        <div class="total-row">
                            <span>Shipping:</span>
                            <span style="color: <?php echo $shipping === 0 ? '#2ecc71' : '#333'; ?>;">
                                <?php echo $shipping === 0 ? 'FREE' : 'ETB ' . number_format($shipping, 2); ?>
                            </span>
                        </div>
                        <div class="total-row">
                            <span>Tax (15%):</span>
                            <span>ETB <?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total Amount:</span>
                            <span>ETB <?php echo number_format($grandTotal, 2); ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee; color: #666; font-size: 0.9rem;">
                        <p style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-shipping-fast" style="color: #2ecc71;"></i> 
                            <strong>Free shipping</strong> on orders over ETB 50,000
                        </p>
                        <p style="margin-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-shield-alt" style="color: #667eea;"></i> 
                            <strong>100% Secure</strong> Payment - Your data is protected
                        </p>
                        <p style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-undo" style="color: #ff9f43;"></i> 
                            <strong>14-Day Return Policy</strong> - Easy returns & refunds
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Mobile Suking. All rights reserved.</p>
            <p style="margin-top: 1rem; color: #bdc3c7;">
                <i class="fas fa-phone"></i> +251 911 223 344 | 
                <i class="fas fa-envelope"></i> info@mobilesuking.com |
                <i class="fas fa-map-marker-alt"></i> Addis Ababa, Ethiopia
            </p>
        </div>
    </footer>

    <script>
        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.style.display = 'block';
            notification.style.animation = 'slideIn 0.3s ease';
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 300);
            }, 3000);
        }
        
        // Select payment method
        function selectPayment(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            const selectedOption = event.currentTarget;
            selectedOption.classList.add('selected');
            
            // Check the radio button
            const radio = selectedOption.querySelector('input[type="radio"]');
            radio.checked = true;
            
            // Show payment instructions
            document.getElementById('telebirrInstructions').style.display = 'none';
            document.getElementById('cashInstructions').style.display = 'none';
            
            if(method === 'telebirr') {
                document.getElementById('telebirrInstructions').style.display = 'block';
                updateTelebirrAmount();
            } else if(method === 'cash') {
                document.getElementById('cashInstructions').style.display = 'block';
            }
        }

        // Initialize payment selection
        document.addEventListener('DOMContentLoaded', function() {
            // Check if a payment method is already selected
            const selectedPayment = document.querySelector('input[name="payment_method"]:checked');
            if(selectedPayment) {
                selectPayment(selectedPayment.value);
            }
            
            // Set default to cash if nothing selected
            if(!selectedPayment) {
                const cashOption = document.querySelector('input[value="cash"]');
                if(cashOption) {
                    cashOption.checked = true;
                    selectPayment('cash');
                }
            }
            
            // Add CSS for animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        });

        // Update Telebirr instructions with correct amount
        function updateTelebirrAmount() {
            const telebirrInstructions = document.getElementById('telebirrInstructions');
            if(telebirrInstructions) {
                const totalAmount = <?php echo $grandTotal; ?>;
                telebirrInstructions.innerHTML = telebirrInstructions.innerHTML.replace(
                    /ETB [0-9,.]+/g, 
                    'ETB ' + totalAmount.toFixed(2)
                );
            }
        }

        // Form validation and submission
        document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
            const placeOrderBtn = document.getElementById('placeOrderBtn');
            
            // Validate terms and conditions
            const termsCheckbox = document.getElementById('terms');
            if(!termsCheckbox.checked) {
                e.preventDefault();
                showNotification('Please agree to the Terms and Conditions', 'error');
                termsCheckbox.focus();
                return;
            }
            
            // Validate payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if(!paymentMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'error');
                return;
            }
            
            // Validate required fields
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            let firstInvalidField = null;
            
            requiredFields.forEach(field => {
                if(!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#ff4757';
                    if(!firstInvalidField) firstInvalidField = field;
                } else {
                    field.style.borderColor = '#ddd';
                }
                
                // Special validation for phone
                if(field.name === 'phone' && field.value.trim()) {
                    const pattern = /^(\+251|0)[0-9]{9}$/;
                    if(!pattern.test(field.value.trim())) {
                        isValid = false;
                        field.style.borderColor = '#ff4757';
                        showNotification('Please enter a valid Ethiopian phone number', 'error');
                        if(!firstInvalidField) firstInvalidField = field;
                    }
                }
            });
            
            if(!isValid) {
                e.preventDefault();
                if(firstInvalidField) {
                    firstInvalidField.focus();
                }
                showNotification('Please fill all required fields correctly', 'error');
                return;
            }
            
            // Change button state
            placeOrderBtn.disabled = true;
            placeOrderBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
            placeOrderBtn.style.opacity = '0.8';
            
            // Show processing notification
            showNotification('Processing your order...', 'success');
        });

        // Real-time phone validation
        const phoneInput = document.querySelector('input[name="phone"]');
        if(phoneInput) {
            phoneInput.addEventListener('input', function() {
                const pattern = /^(\+251|0)[0-9]{9}$/;
                if(this.value && !pattern.test(this.value)) {
                    this.style.borderColor = '#ff4757';
                } else {
                    this.style.borderColor = '#ddd';
                }
            });
        }

        // Auto-format phone number
        if(phoneInput) {
            phoneInput.addEventListener('blur', function() {
                let value = this.value.trim();
                if(value.startsWith('0') && value.length === 10) {
                    // Convert 09xxxxxxxx to +2519xxxxxxxx
                    this.value = '+251' + value.substring(1);
                } else if(value.length === 9 && !value.startsWith('0') && !value.startsWith('+')) {
                    // Convert 9xxxxxxxx to +2519xxxxxxxx
                    this.value = '+251' + value;
                }
            });
        }

        // Initialize on load
        updateTelebirrAmount();
    </script>
</body>
</html>