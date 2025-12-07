<?php
// ajax_handler.php (Complete Version)
session_set_cookie_params(86400);
session_start();

$host = 'localhost';
$dbname = 'mobile_suking';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch($action) {
    case 'add_to_cart':
        addToCart($conn);
        break;
        
    case 'get_cart_count':
        getCartCount($conn);
        break;
        
    case 'update_cart':
        updateCart($conn);
        break;
        
    case 'remove_from_cart':
        removeFromCart($conn);
        break;
        
    case 'clear_cart':
        clearCart($conn);
        break;
        
    case 'add_to_wishlist':
        addToWishlist($conn);
        break;
        
    case 'remove_from_wishlist':
        removeFromWishlist($conn);
        break;
        
    case 'check_session':
        checkSession($conn);
        break;
        
    case 'keep_alive':
        keepSessionAlive();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function addToCart($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first', 'login_required' => true]);
        return;
    }
    
    $productId = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    $userId = $_SESSION['user_id'];
    
    if(!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product specified']);
        return;
    }
    
    try {
        // Check if product exists and is in stock
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = :id AND stock > 0");
        $stmt->bindParam(':id', $productId);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not available']);
            return;
        }
        
        // Check if product already in cart
        $stmt = $conn->prepare("SELECT * FROM cart WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($existing) {
            // Check if adding more than stock
            $newQuantity = $existing['quantity'] + $quantity;
            if($newQuantity > $product['stock']) {
                echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
                return;
            }
            
            // Update quantity
            $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + :quantity WHERE id = :id");
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':id', $existing['id']);
            $stmt->execute();
        } else {
            // Add new item
            $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->execute();
        }
        
        // Get updated cart count
        $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Product added to cart',
            'cart_count' => $count
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function getCartCount($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'count' => 0]);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        echo json_encode(['success' => true, 'count' => $count]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'count' => 0, 'message' => $e->getMessage()]);
    }
}

function updateCart($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        return;
    }
    
    $productId = $_POST['product_id'] ?? null;
    $quantity = $_POST['quantity'] ?? 1;
    $userId = $_SESSION['user_id'];
    
    if(!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product specified']);
        return;
    }
    
    if($quantity <= 0) {
        // Remove item if quantity is 0 or less
        removeFromCart($conn);
        return;
    }
    
    try {
        // Check product stock
        $stmt = $conn->prepare("SELECT stock FROM products WHERE id = :id");
        $stmt->bindParam(':id', $productId);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$product || $quantity > $product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            return;
        }
        
        // Update quantity
        $stmt = $conn->prepare("UPDATE cart SET quantity = :quantity WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        // Get updated totals
        $stmt = $conn->prepare("
            SELECT SUM(c.quantity * p.price) as total 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = :user_id
        ");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cart updated',
            'total' => $total
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function removeFromCart($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        return;
    }
    
    $productId = $_POST['product_id'] ?? null;
    $userId = $_SESSION['user_id'];
    
    if(!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product specified']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        // Get updated cart count
        $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Item removed from cart',
            'cart_count' => $count
        ]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function clearCart($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        return;
    }
    
    $userId = $_SESSION['user_id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Cart cleared']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function addToWishlist($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        return;
    }
    
    $productId = $_POST['product_id'] ?? null;
    $userId = $_SESSION['user_id'];
    
    if(!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product specified']);
        return;
    }
    
    try {
        // Check if already in wishlist
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
            return;
        }
        
        // Add to wishlist
        $stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (:user_id, :product_id)");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function removeFromWishlist($conn) {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login first']);
        return;
    }
    
    $productId = $_POST['product_id'] ?? null;
    $userId = $_SESSION['user_id'];
    
    if(!$productId) {
        echo json_encode(['success' => false, 'message' => 'No product specified']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = :user_id AND product_id = :product_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Removed from wishlist']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function checkSession($conn) {
    $isLoggedIn = isset($_SESSION['user_id']);
    $response = [
        'logged_in' => $isLoggedIn,
        'user_id' => $isLoggedIn ? $_SESSION['user_id'] : null,
        'username' => $isLoggedIn ? $_SESSION['username'] : null,
        'role' => $isLoggedIn ? $_SESSION['role'] : null
    ];
    
    if($isLoggedIn && isset($_SESSION['last_activity'])) {
        $timeout = 86400;
        $timeSinceLastActivity = time() - $_SESSION['last_activity'];
        
        if($timeSinceLastActivity > $timeout) {
            session_unset();
            session_destroy();
            $response['logged_in'] = false;
            $response['message'] = 'Session expired';
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
    
    echo json_encode($response);
}

function keepSessionAlive() {
    if(isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
        echo json_encode(['success' => true, 'last_activity' => $_SESSION['last_activity']]);
    } else {
        echo json_encode(['success' => false]);
    }
}
?>