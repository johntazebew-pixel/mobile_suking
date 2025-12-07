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
    header('Location: login-register.php?redirect=' . urlencode('cart.php'));
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'] ?? '';
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
function optimizeCloudinaryImage($imageUrl, $width = 150, $height = 150) {
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
        
        // Optimize images for cart display
        foreach($items as &$item) {
            $item['optimized_image'] = optimizeCloudinaryImage($item['image_url'], 150, 150);
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

// Handle cart actions via AJAX
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $productId = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch($action) {
            case 'update':
                if($quantity <= 0) {
                    // Remove item if quantity is 0 or less
                    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id AND product_id = :product_id");
                } else {
                    // Update quantity
                    $stmt = $conn->prepare("UPDATE cart SET quantity = :quantity WHERE user_id = :user_id AND product_id = :product_id");
                    $stmt->bindParam(':quantity', $quantity);
                }
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':product_id', $productId);
                $stmt->execute();
                $response['success'] = true;
                $response['message'] = 'Cart updated successfully';
                break;
                
            case 'remove':
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id AND product_id = :product_id");
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':product_id', $productId);
                $stmt->execute();
                $response['success'] = true;
                $response['message'] = 'Item removed from cart';
                break;
                
            case 'clear':
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id");
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                $response['success'] = true;
                $response['message'] = 'Cart cleared successfully';
                break;
                
            default:
                $response['message'] = 'Invalid action';
        }
        
        // Get updated cart info
        $cartItems = getCartItems($conn, $userId);
        $cartTotal = 0;
        $itemCount = 0;
        
        foreach($cartItems as $item) {
            $itemTotal = $item['price'] * $item['quantity'];
            $cartTotal += $itemTotal;
            $itemCount += $item['quantity'];
        }
        
        $response['cart_count'] = $itemCount;
        $response['cart_total'] = $cartTotal;
        $response['cart_items'] = count($cartItems);
        
    } catch(PDOException $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get cart items for display
$cartItems = getCartItems($conn, $userId);
$cartTotal = 0;
$itemCount = 0;

foreach($cartItems as $item) {
    $itemTotal = $item['price'] * $item['quantity'];
    $cartTotal += $itemTotal;
    $itemCount += $item['quantity'];
}

// Calculate summary
$shipping = $cartTotal > 50000 ? 0 : 500;
$tax = $cartTotal * 0.15;
$grandTotal = $cartTotal + $shipping + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Mobile Suking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Modern CSS Reset & Variables */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
    }

    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary: #7c3aed;
        --accent: #f59e0b;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --dark: #1f2937;
        --light: #f8fafc;
        --gray: #6b7280;
        --gray-light: #e5e7eb;
        --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --gradient-dark: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        --gradient-accent: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
        --gradient-danger: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --radius: 12px;
        --radius-sm: 8px;
        --radius-lg: 16px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        color: var(--dark);
        line-height: 1.6;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* Header Styles */
    .header {
        background: var(--gradient-dark);
        color: white;
        padding: 1rem 0;
        box-shadow: var(--shadow-xl);
        position: sticky;
        top: 0;
        z-index: 1000;
        backdrop-filter: blur(10px);
        border-bottom: 3px solid var(--accent);
    }

    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .logo {
        font-size: 2.2rem;
        font-weight: 800;
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: var(--radius);
        backdrop-filter: blur(5px);
        transition: var(--transition);
    }

    .logo:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }

    .nav-links {
        display: flex;
        gap: 0.5rem;
        list-style: none;
    }

    .nav-links a {
        color: white;
        text-decoration: none;
        font-weight: 600;
        padding: 12px 20px;
        border-radius: var(--radius-sm);
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
        position: relative;
    }

    .nav-links a::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 3px;
        background: var(--accent);
        border-radius: 2px;
        transition: width 0.3s ease;
    }

    .nav-links a:hover::after {
        width: 80%;
    }

    .nav-links a:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .nav-links a[style*="background-color"] {
        background: rgba(255, 255, 255, 0.2) !important;
    }

    .nav-links a[style*="background-color"]::after {
        width: 80%;
    }

    .user-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .user-welcome {
        font-weight: 700;
        color: var(--accent);
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 16px;
        border-radius: var(--radius-sm);
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        font-size: 0.95rem;
        position: relative;
        overflow: hidden;
    }

    .btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn:hover::before {
        width: 300px;
        height: 300px;
    }

    .btn-primary {
        background: var(--gradient-accent);
        color: white;
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    }

    .btn-secondary {
        background: transparent;
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
    }

    .btn-secondary:hover {
        background: white;
        color: var(--dark);
        border-color: white;
        transform: translateY(-2px);
    }

    /* Cart Container */
    .cart-container {
        max-width: 1400px;
        margin: 3rem auto;
        padding: 0 2rem;
        flex: 1;
        animation: fadeIn 0.6s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Cart Header */
    .cart-header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .cart-header h1 {
        font-size: 3.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1rem;
        position: relative;
        display: inline-block;
    }

    .cart-header h1::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 150px;
        height: 5px;
        background: var(--gradient-accent);
        border-radius: 3px;
    }

    .cart-header p {
        font-size: 1.2rem;
        color: var(--gray);
        max-width: 600px;
        margin: 1rem auto 0;
    }

    /* Cart Layout */
    .cart-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 3rem;
    }

    @media (max-width: 992px) {
        .cart-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Cart Items Section */
    .cart-items {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        overflow: hidden;
        border: 1px solid var(--gray-light);
        animation: slideIn 0.4s ease;
    }

    @keyframes slideIn {
        from { transform: translateX(-20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    /* Cart Item */
    .cart-item {
        display: grid;
        grid-template-columns: 140px 1fr auto;
        gap: 1.5rem;
        padding: 2rem;
        border-bottom: 2px solid var(--gray-light);
        align-items: center;
        transition: var(--transition);
        position: relative;
        overflow: hidden;
    }

    .cart-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 5px;
        height: 100%;
        background: var(--gradient-primary);
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .cart-item:hover::before {
        transform: translateX(0);
    }

    .cart-item:hover {
        background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    /* Item Image */
    .item-image {
        width: 140px;
        height: 140px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        border-radius: var(--radius);
        overflow: hidden;
        padding: 1rem;
        border: 2px solid var(--gray-light);
        transition: var(--transition);
    }

    .cart-item:hover .item-image {
        border-color: var(--primary);
        transform: scale(1.02);
    }

    .item-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .cart-item:hover .item-image img {
        transform: scale(1.1);
    }

    /* Item Details */
    .item-details {
        padding-right: 1rem;
    }

    .item-name {
        font-size: 1.25rem;
        font-weight: 800;
        margin-bottom: 0.75rem;
        color: var(--dark);
    }

    .item-name a {
        color: var(--dark);
        text-decoration: none;
        transition: var(--transition);
    }

    .item-name a:hover {
        color: var(--primary);
    }

    .item-brand {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 0.75rem;
        padding: 0.5rem 1rem;
        background: #f0f7ff;
        border-radius: var(--radius-sm);
        border-left: 4px solid var(--primary);
        width: fit-content;
    }

    .item-specs {
        display: flex;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .spec-badge {
        background: #f8fafc;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-sm);
        font-size: 0.9rem;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border: 1px solid var(--gray-light);
    }

    .spec-badge i {
        color: var(--primary);
    }

    /* Price Container */
    .item-price-container {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .item-price {
        font-size: 1.5rem;
        font-weight: 900;
        color: var(--success);
    }

    .original-price {
        font-size: 1rem;
        color: var(--gray);
        text-decoration: line-through;
    }

    .discount-badge {
        background: var(--gradient-accent);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-weight: 800;
        font-size: 0.85rem;
        box-shadow: var(--shadow);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    /* Stock Status */
    .item-stock {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-sm);
        width: fit-content;
    }

    .item-stock.in-stock {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border-left: 4px solid var(--success);
    }

    .item-stock.low-stock {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
        border-left: 4px solid var(--warning);
    }

    .item-stock.out-stock {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border-left: 4px solid var(--danger);
    }

    /* Item Controls */
    .item-controls {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5rem;
        min-width: 180px;
    }

    .quantity-control {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .qty-btn {
        width: 40px;
        height: 40px;
        border: 2px solid var(--gray-light);
        background: white;
        border-radius: var(--radius-sm);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 1.2rem;
        transition: var(--transition);
    }

    .qty-btn:hover:not(:disabled) {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .qty-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .qty-input {
        width: 70px;
        height: 40px;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        text-align: center;
        font-weight: 700;
        font-size: 1.1rem;
        transition: var(--transition);
    }

    .qty-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .qty-input:disabled {
        background: #f8fafc;
        cursor: not-allowed;
    }

    /* Item Total */
    .item-total {
        font-size: 1.4rem;
        font-weight: 900;
        color: var(--dark);
        text-align: center;
        padding: 1rem;
        background: #f8fafc;
        border-radius: var(--radius);
        min-width: 150px;
        border: 2px solid var(--gray-light);
        transition: var(--transition);
    }

    .cart-item:hover .item-total {
        border-color: var(--primary);
        background: white;
    }

    /* Remove Button */
    .remove-btn {
        background: var(--gradient-danger);
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: var(--radius);
        cursor: pointer;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: var(--transition);
        width: 100%;
        justify-content: center;
    }

    .remove-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }

    /* Cart Summary */
    .cart-summary {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: 2.5rem;
        position: sticky;
        top: 120px;
        border: 1px solid var(--gray-light);
        animation: slideInRight 0.4s ease;
    }

    @keyframes slideInRight {
        from { transform: translateX(20px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    .summary-title {
        font-size: 1.8rem;
        font-weight: 900;
        color: var(--dark);
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 3px solid var(--accent);
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px dashed var(--gray-light);
    }

    .summary-label {
        font-weight: 600;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .summary-value {
        font-weight: 700;
        color: var(--dark);
    }

    .summary-total {
        font-size: 2rem;
        font-weight: 900;
        color: var(--success);
        margin: 2rem 0;
        padding-top: 1.5rem;
        border-top: 3px solid var(--gray-light);
    }

    /* Summary Actions */
    .summary-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn-checkout {
        background: var(--gradient-success);
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        padding: 18px;
        border: none;
        border-radius: var(--radius);
        justify-content: center;
    }

    .btn-checkout:hover:not(:disabled) {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(16, 185, 129, 0.3);
    }

    .btn-checkout:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .btn-continue {
        background: var(--gradient-primary);
        color: white;
        border: none;
        padding: 15px;
        border-radius: var(--radius);
        justify-content: center;
    }

    .btn-continue:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-clear {
        background: var(--gradient-danger);
        color: white;
        border: none;
        padding: 15px;
        border-radius: var(--radius);
        justify-content: center;
    }

    .btn-clear:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    }

    .btn-clear:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Benefits Section */
    .summary-benefits {
        margin-top: 2rem;
        padding-top: 2rem;
        border-top: 2px solid var(--gray-light);
    }

    .benefit-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1rem;
        color: var(--gray);
        font-size: 0.95rem;
    }

    .benefit-item i {
        color: var(--primary);
        font-size: 1.2rem;
    }

    /* Empty Cart */
    .empty-cart {
        text-align: center;
        padding: 6rem 2rem;
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        margin: 2rem 0;
        grid-column: 1 / -1;
        border: 2px solid var(--gray-light);
    }

    .empty-cart i {
        font-size: 5rem;
        color: var(--gray-light);
        margin-bottom: 2rem;
        animation: bounce 2s infinite;
    }

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    .empty-cart h2 {
        font-size: 2.5rem;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .empty-cart p {
        color: var(--gray);
        margin-bottom: 2rem;
        font-size: 1.1rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Notification */
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 18px 24px;
        border-radius: var(--radius);
        color: white;
        font-weight: 600;
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--shadow-xl);
        min-width: 300px;
        animation: slideIn 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        display: none;
    }

    .notification.success {
        background: var(--gradient-success);
        border-left: 4px solid var(--success);
    }

    .notification.error {
        background: var(--gradient-danger);
        border-left: 4px solid var(--danger);
    }

    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }

    /* Footer */
    footer {
        background: var(--gradient-dark);
        color: white;
        padding: 4rem 2rem 2rem;
        margin-top: auto;
        border-top: 3px solid var(--accent);
    }

    footer .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    footer p {
        margin: 0.75rem 0;
        font-size: 1.1rem;
    }

    footer .contact-info {
        display: flex;
        justify-content: center;
        gap: 2.5rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    footer .contact-info span {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: var(--gray-light);
        font-size: 0.95rem;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .cart-layout {
            gap: 2rem;
        }
    }

    @media (max-width: 992px) {
        .navbar {
            flex-direction: column;
            gap: 1.5rem;
            padding: 1rem;
        }

        .nav-links {
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }

        .user-actions {
            width: 100%;
            justify-content: center;
            flex-wrap: wrap;
        }

        .cart-header h1 {
            font-size: 2.8rem;
        }

        .cart-summary {
            position: static;
            margin-top: 2rem;
        }
    }

    @media (max-width: 768px) {
        .cart-container {
            padding: 0 1rem;
        }

        .cart-header h1 {
            font-size: 2.4rem;
        }

        .cart-item {
            grid-template-columns: 1fr;
            text-align: center;
            padding: 1.5rem;
        }

        .item-image {
            margin: 0 auto;
            width: 120px;
            height: 120px;
        }

        .item-details {
            padding-right: 0;
        }

        .item-specs {
            justify-content: center;
        }

        .item-price-container {
            justify-content: center;
        }

        .item-controls {
            min-width: 100%;
        }

        .quantity-control {
            justify-content: center;
        }

        .notification {
            min-width: 250px;
            right: 10px;
            left: 10px;
            width: calc(100% - 20px);
        }
    }

    @media (max-width: 576px) {
        .cart-header h1 {
            font-size: 2rem;
        }

        .item-controls {
            flex-direction: column;
        }

        .quantity-control {
            width: 100%;
        }

        .empty-cart h2 {
            font-size: 2rem;
        }

        .empty-cart p {
            font-size: 1rem;
        }
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
        width: 10px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 5px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(135deg, var(--primary-dark) 0%, #6d28d9 100%);
    }

    /* Loading Animation */
    .loading-skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
        border-radius: var(--radius);
    }

    @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Item Removal Animation */
    @keyframes itemRemoval {
        0% { transform: translateX(0); opacity: 1; }
        100% { transform: translateX(-100%); opacity: 0; }
    }

    .item-removing {
        animation: itemRemoval 0.3s ease forwards;
    }

    /* Price Update Animation */
    @keyframes priceUpdate {
        0% { transform: scale(1); color: var(--dark); }
        50% { transform: scale(1.1); color: var(--success); }
        100% { transform: scale(1); color: var(--dark); }
    }

    .price-updated {
        animation: priceUpdate 0.5s ease;
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
                <li><a href="cart.php" style="background-color: rgba(255,255,255,0.3);"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
            </ul>
            
            <div class="user-actions">
                <span style="color: #ffcc00; font-weight: 600;">
                    <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($userName); ?>
                </span>
                <a href="user-dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-user-circle"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <a href="cart.php" class="btn" style="background-color: #2ecc71; color: white;">
                    <i class="fas fa-shopping-cart"></i> 
                    <span id="cartCount"><?php echo $itemCount; ?></span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Cart Container -->
    <div class="cart-container">
        <div class="cart-header">
            <h1>Shopping Cart</h1>
            <p>Review your items and proceed to checkout</p>
        </div>

        <?php if(count($cartItems) > 0): ?>
            <div class="cart-layout">
                <!-- Cart Items -->
                <div class="cart-items">
                    <?php foreach($cartItems as $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
                        $maxQuantity = min($item['stock'], 10); // Limit to stock or 10
                    ?>
                    <div class="cart-item" id="item-<?php echo $item['product_id']; ?>" data-price="<?php echo $item['price']; ?>">
                        <div class="item-image">
                            <!-- OPTIMIZED CLOUDINARY IMAGE -->
                            <img 
                                src="<?php echo htmlspecialchars($item['optimized_image']); ?>" 
                                alt="<?php echo htmlspecialchars($item['name']); ?>"
                                loading="lazy"
                                width="150"
                                height="150"
                                onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=150&h=150&fit=crop'"
                            >
                        </div>
                        
                        <div class="item-details">
                            <h3 class="item-name">
                                <a href="product-detail.php?id=<?php echo $item['product_id']; ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </a>
                            </h3>
                            <span class="item-brand">
                                <i class="fas fa-tag"></i> <?php echo ucfirst($item['brand']); ?>
                            </span>
                            
                            <?php if($item['storage'] || $item['color']): ?>
                            <div class="item-specs">
                                <?php if($item['storage']): ?>
                                    <span><i class="fas fa-hdd"></i> <?php echo htmlspecialchars($item['storage']); ?></span>
                                <?php endif; ?>
                                <?php if($item['color']): ?>
                                    <span><i class="fas fa-palette"></i> <?php echo htmlspecialchars($item['color']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="item-price-container">
                                <div class="item-price">ETB <?php echo number_format($item['price'], 2); ?></div>
                                <?php if($item['has_discount']): ?>
                                    <div class="original-price">ETB <?php echo number_format($item['original_price'], 2); ?></div>
                                    <div class="discount-badge">-<?php echo $item['discount_percent']; ?>%</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="item-stock <?php echo $item['stock'] > 10 ? 'in-stock' : ($item['stock'] > 0 ? 'low-stock' : 'out-stock'); ?>">
                                <?php if($item['stock'] > 10): ?>
                                    <i class="fas fa-check-circle"></i> In Stock
                                <?php elseif($item['stock'] > 0): ?>
                                    <i class="fas fa-exclamation-triangle"></i> Only <?php echo $item['stock']; ?> left
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="item-controls">
                            <div class="quantity-control">
                                <button class="qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, -1)" 
                                    <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" class="qty-input" 
                                       value="<?php echo $item['quantity']; ?>" 
                                       min="1" max="<?php echo $maxQuantity; ?>"
                                       onchange="updateQuantityInput(<?php echo $item['product_id']; ?>, this.value)"
                                       id="qty-<?php echo $item['product_id']; ?>"
                                       <?php echo $item['stock'] <= 0 ? 'disabled' : ''; ?>>
                                <button class="qty-btn" onclick="updateQuantity(<?php echo $item['product_id']; ?>, 1)"
                                    <?php echo $item['quantity'] >= $maxQuantity ? 'disabled' : ''; ?>>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <div class="item-total">
                                ETB <span id="item-total-<?php echo $item['product_id']; ?>">
                                    <?php echo number_format($itemTotal, 2); ?>
                                </span>
                            </div>
                            <button class="remove-btn" onclick="removeItem(<?php echo $item['product_id']; ?>)">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cart Summary -->
                <div class="cart-summary">
                    <h2 class="summary-title">Order Summary</h2>
                    
                    <div class="summary-item">
                        <span>Items (<?php echo $itemCount; ?>):</span>
                        <span>ETB <span id="subtotal"><?php echo number_format($cartTotal, 2); ?></span></span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Shipping:</span>
                        <span>ETB <span id="shipping"><?php echo number_format($shipping, 2); ?></span></span>
                    </div>
                    
                    <div class="summary-item">
                        <span>Tax (15%):</span>
                        <span>ETB <span id="tax"><?php echo number_format($tax, 2); ?></span></span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total:</span>
                        <span>ETB <span id="grand-total"><?php echo number_format($grandTotal, 2); ?></span></span>
                    </div>
                    
                    <div class="summary-actions">
                        <button class="btn btn-checkout" onclick="proceedToCheckout()" 
                            <?php echo $itemCount === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </button>
                        <a href="products.php" class="btn btn-continue">
                            <i class="fas fa-shopping-bag"></i> Continue Shopping
                        </a>
                        <button class="btn btn-clear" onclick="clearCart()" 
                            <?php echo $itemCount === 0 ? 'disabled' : ''; ?>>
                            <i class="fas fa-trash-alt"></i> Clear Cart
                        </button>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee; color: #666; font-size: 0.9rem;">
                        <p><i class="fas fa-shield-alt"></i> Secure checkout</p>
                        <p><i class="fas fa-truck"></i> Free shipping on orders over ETB 50,000</p>
                        <p><i class="fas fa-undo"></i> Easy returns within 14 days</p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2 style="color: #666; margin-bottom: 1rem;">Your cart is empty</h2>
                <p style="color: #999; margin-bottom: 2rem;">Add some products to your cart to see them here</p>
                <a href="products.php" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: inline-flex; padding: 12px 24px;">
                    <i class="fas fa-shopping-bag"></i> Browse Products
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> Mobile Suking. All rights reserved.</p>
            <p style="margin-top: 1rem; color: #bdc3c7;">
                <i class="fas fa-phone"></i> +251 911 223 344 | 
                <i class="fas fa-envelope"></i> info@mobilesuking.com
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
        
        // Update quantity with buttons
        function updateQuantity(productId, change) {
            const input = document.getElementById('qty-' + productId);
            const currentValue = parseInt(input.value) || 1;
            const max = parseInt(input.max) || 10;
            const newValue = currentValue + change;
            
            if(newValue >= 1 && newValue <= max) {
                input.value = newValue;
                sendUpdateRequest(productId, newValue);
            }
        }

        // Update quantity from input
        function updateQuantityInput(productId, value) {
            const input = document.getElementById('qty-' + productId);
            const max = parseInt(input.max) || 10;
            const newValue = Math.max(1, Math.min(max, parseInt(value) || 1));
            input.value = newValue;
            sendUpdateRequest(productId, newValue);
        }

        // Send update request to server
        function sendUpdateRequest(productId, quantity) {
            const formData = new FormData();
            formData.append('ajax', 'true');
            formData.append('action', 'update');
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Update item total
                    updateItemTotal(productId, quantity);
                    // Update cart summary
                    updateCartSummary(data);
                    // Update cart count
                    updateCartCount(data.cart_count);
                    // Update buttons state
                    updateButtonsState(productId, quantity);
                    showNotification('Cart updated successfully', 'success');
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to update cart', 'error');
            });
        }

        // Update item total display
        function updateItemTotal(productId, quantity) {
            const itemElement = document.getElementById('item-' + productId);
            const price = parseFloat(itemElement.getAttribute('data-price'));
            const total = price * quantity;
            
            document.getElementById('item-total-' + productId).textContent = total.toFixed(2);
        }

        // Update buttons state
        function updateButtonsState(productId, quantity) {
            const input = document.getElementById('qty-' + productId);
            const max = parseInt(input.max) || 10;
            const minusBtn = input.previousElementSibling;
            const plusBtn = input.nextElementSibling;
            
            minusBtn.disabled = quantity <= 1;
            plusBtn.disabled = quantity >= max;
            
            // Update button styles
            minusBtn.style.opacity = quantity <= 1 ? '0.5' : '1';
            plusBtn.style.opacity = quantity >= max ? '0.5' : '1';
        }

        // Remove item from cart
        function removeItem(productId) {
            if(confirm('Are you sure you want to remove this item from your cart?')) {
                const formData = new FormData();
                formData.append('ajax', 'true');
                formData.append('action', 'remove');
                formData.append('product_id', productId);
                
                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        // Remove item from display with animation
                        const itemElement = document.getElementById('item-' + productId);
                        itemElement.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            itemElement.remove();
                            
                            // If cart is now empty, reload page
                            if(document.querySelectorAll('.cart-item').length === 0) {
                                location.reload();
                            } else {
                                // Update cart summary
                                updateCartSummary(data);
                                // Update cart count
                                updateCartCount(data.cart_count);
                            }
                        }, 300);
                        
                        showNotification('Item removed from cart', 'success');
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to remove item', 'error');
                });
            }
        }

        // Clear entire cart
        function clearCart() {
            if(confirm('Are you sure you want to clear your entire cart?')) {
                const formData = new FormData();
                formData.append('ajax', 'true');
                formData.append('action', 'clear');
                
                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        showNotification(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to clear cart', 'error');
                });
            }
        }

        // Update cart summary
        function updateCartSummary(data) {
            if(!data.cart_total) return;
            
            const subtotal = data.cart_total;
            const shipping = subtotal > 50000 ? 0 : 500;
            const tax = subtotal * 0.15;
            const grandTotal = subtotal + shipping + tax;
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('shipping').textContent = shipping.toFixed(2);
            document.getElementById('tax').textContent = tax.toFixed(2);
            document.getElementById('grand-total').textContent = grandTotal.toFixed(2);
        }

        // Update cart count
        function updateCartCount(count) {
            const cartCountElement = document.getElementById('cartCount');
            if(cartCountElement) {
                cartCountElement.textContent = count || 0;
                cartCountElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    cartCountElement.style.transform = 'scale(1)';
                }, 300);
            }
            
            // Update checkout button state
            const checkoutBtn = document.querySelector('.btn-checkout');
            if(checkoutBtn) {
                checkoutBtn.disabled = count === 0;
                checkoutBtn.style.opacity = count === 0 ? '0.5' : '1';
            }
            
            // Update clear button state
            const clearBtn = document.querySelector('.btn-clear');
            if(clearBtn) {
                clearBtn.disabled = count === 0;
                clearBtn.style.opacity = count === 0 ? '0.5' : '1';
            }
        }

        // Proceed to checkout
        function proceedToCheckout() {
            window.location.href = 'checkout.php';
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add CSS for animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>