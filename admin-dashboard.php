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

// Check admin status - Redirect if not admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['username'] ?? '';
$isAdmin = true;

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
function optimizeCloudinaryImage($imageUrl, $width = 400, $height = 300) {
    if (strpos($imageUrl, 'cloudinary.com') === false) {
        return $imageUrl;
    }
    
    $transformation = "w_{$width},h_{$height},c_fill,q_auto,f_auto";
    $optimizedUrl = str_replace('/upload/', "/upload/$transformation/", $imageUrl);
    
    return $optimizedUrl;
}

// Function to get brand-specific fallback image
function getFallbackImage($brand) {
    $brand = strtolower($brand);
    $placeholders = [
        'samsung' => 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=400&h=300&fit=crop&q=80',
        'iphone' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&h=300&fit=crop&q=80',
        'huawei' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=400&h=300&fit=crop&q=80'
    ];
    
    return $placeholders[$brand] ?? 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=400&h=300&fit=crop&q=80';
}

// Initialize variables
$success = '';
$error = '';
$currentSection = $_GET['section'] ?? 'dashboard';

// Handle Product Actions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'add_product':
            $name = trim($_POST['name'] ?? '');
            $brand = $_POST['brand'] ?? '';
            $model = trim($_POST['model'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $original_price = floatval($_POST['original_price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $specifications = trim($_POST['specifications'] ?? '');
            $storage = trim($_POST['storage'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $screen_size = trim($_POST['screen_size'] ?? '');
            $camera = trim($_POST['camera'] ?? '');
            $battery = trim($_POST['battery'] ?? '');
            $stock = intval($_POST['stock'] ?? 0);
            $image_url = trim($_POST['image_url'] ?? '');
            $featured = isset($_POST['featured']) ? 1 : 0;
            
            if(empty($name) || empty($brand) || $price <= 0) {
                $error = "Name, brand, and price are required!";
            } else {
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO products 
                        (name, brand, model, price, original_price, description, specifications, 
                         storage, color, screen_size, camera, battery, stock, image_url, featured) 
                        VALUES 
                        (:name, :brand, :model, :price, :original_price, :description, :specifications,
                         :storage, :color, :screen_size, :camera, :battery, :stock, :image_url, :featured)
                    ");
                    
                    $stmt->execute([
                        ':name' => $name,
                        ':brand' => $brand,
                        ':model' => $model,
                        ':price' => $price,
                        ':original_price' => $original_price,
                        ':description' => $description,
                        ':specifications' => $specifications,
                        ':storage' => $storage,
                        ':color' => $color,
                        ':screen_size' => $screen_size,
                        ':camera' => $camera,
                        ':battery' => $battery,
                        ':stock' => $stock,
                        ':image_url' => $image_url,
                        ':featured' => $featured
                    ]);
                    
                    $success = "Product added successfully!";
                    $currentSection = 'products';
                    
                } catch(PDOException $e) {
                    $error = "Failed to add product: " . $e->getMessage();
                }
            }
            break;
            
        case 'update_product':
            $productId = $_POST['product_id'] ?? 0;
            $name = trim($_POST['name'] ?? '');
            $brand = $_POST['brand'] ?? '';
            $model = trim($_POST['model'] ?? '');
            $price = floatval($_POST['price'] ?? 0);
            $original_price = floatval($_POST['original_price'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $specifications = trim($_POST['specifications'] ?? '');
            $storage = trim($_POST['storage'] ?? '');
            $color = trim($_POST['color'] ?? '');
            $screen_size = trim($_POST['screen_size'] ?? '');
            $camera = trim($_POST['camera'] ?? '');
            $battery = trim($_POST['battery'] ?? '');
            $stock = intval($_POST['stock'] ?? 0);
            $image_url = trim($_POST['image_url'] ?? '');
            $featured = isset($_POST['featured']) ? 1 : 0;
            
            if($productId && !empty($name) && !empty($brand) && $price > 0) {
                try {
                    $stmt = $conn->prepare("
                        UPDATE products SET 
                        name = :name, brand = :brand, model = :model, price = :price, 
                        original_price = :original_price, description = :description, 
                        specifications = :specifications, storage = :storage, color = :color, 
                        screen_size = :screen_size, camera = :camera, battery = :battery, 
                        stock = :stock, image_url = :image_url, featured = :featured 
                        WHERE id = :id
                    ");
                    
                    $stmt->execute([
                        ':id' => $productId,
                        ':name' => $name,
                        ':brand' => $brand,
                        ':model' => $model,
                        ':price' => $price,
                        ':original_price' => $original_price,
                        ':description' => $description,
                        ':specifications' => $specifications,
                        ':storage' => $storage,
                        ':color' => $color,
                        ':screen_size' => $screen_size,
                        ':camera' => $camera,
                        ':battery' => $battery,
                        ':stock' => $stock,
                        ':image_url' => $image_url,
                        ':featured' => $featured
                    ]);
                    
                    $success = "Product updated successfully!";
                    $currentSection = 'products';
                    
                } catch(PDOException $e) {
                    $error = "Failed to update product: " . $e->getMessage();
                }
            } else {
                $error = "Invalid product data!";
            }
            break;
            
        case 'delete_product':
            $productId = $_POST['product_id'] ?? 0;
            if($productId) {
                try {
                    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = :id");
                    $stmt->execute([':id' => $productId]);
                    $orderCount = $stmt->fetchColumn();
                    
                    if($orderCount > 0) {
                        $error = "Cannot delete product. It exists in $orderCount order(s).";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
                        $stmt->execute([':id' => $productId]);
                        
                        $success = "Product deleted successfully!";
                    }
                } catch(PDOException $e) {
                    $error = "Failed to delete product: " . $e->getMessage();
                }
            }
            break;
            
        case 'ban_user':
            $userIdToBan = $_POST['user_id'] ?? 0;
            if($userIdToBan) {
                try {
                    $stmt = $conn->prepare("UPDATE users SET status = 'banned' WHERE id = :id");
                    $stmt->execute([':id' => $userIdToBan]);
                    
                    $success = "User banned successfully!";
                    $currentSection = 'users';
                } catch(PDOException $e) {
                    $error = "Failed to ban user: " . $e->getMessage();
                }
            }
            break;
            
        case 'activate_user':
            $userIdToActivate = $_POST['user_id'] ?? 0;
            if($userIdToActivate) {
                try {
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = :id");
                    $stmt->execute([':id' => $userIdToActivate]);
                    
                    $success = "User activated successfully!";
                    $currentSection = 'users';
                } catch(PDOException $e) {
                    $error = "Failed to activate user: " . $e->getMessage();
                }
            }
            break;
            
        case 'update_order_status':
            $orderId = $_POST['order_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            if($orderId && $status) {
                try {
                    $stmt = $conn->prepare("UPDATE orders SET order_status = :status WHERE id = :id");
                    $stmt->execute([':status' => $status, ':id' => $orderId]);
                    
                    $success = "Order status updated to " . ucfirst($status) . "!";
                    $currentSection = 'orders';
                } catch(PDOException $e) {
                    $error = "Failed to update order status: " . $e->getMessage();
                }
            }
            break;
            
        case 'update_payment_status':
            $orderId = $_POST['order_id'] ?? 0;
            $status = $_POST['payment_status'] ?? '';
            if($orderId && $status) {
                try {
                    $stmt = $conn->prepare("UPDATE orders SET payment_status = :status WHERE id = :id");
                    $stmt->execute([':status' => $status, ':id' => $orderId]);
                    
                    $success = "Payment status updated to " . ucfirst($status) . "!";
                    $currentSection = 'orders';
                } catch(PDOException $e) {
                    $error = "Failed to update payment status: " . $e->getMessage();
                }
            }
            break;
    }
}

// Get statistics for dashboard
$stats = [];
try {
    $queries = [
        'total_users' => "SELECT COUNT(*) as total FROM users WHERE role = 'user'",
        'active_users' => "SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'active'",
        'banned_users' => "SELECT COUNT(*) as total FROM users WHERE role = 'user' AND status = 'banned'",
        'total_products' => "SELECT COUNT(*) as total FROM products",
        'featured_products' => "SELECT COUNT(*) as total FROM products WHERE featured = 1",
        'low_stock' => "SELECT COUNT(*) as total FROM products WHERE stock < 10",
        'out_of_stock' => "SELECT COUNT(*) as total FROM products WHERE stock = 0",
        'total_orders' => "SELECT COUNT(*) as total FROM orders",
        'pending_orders' => "SELECT COUNT(*) as total FROM orders WHERE order_status = 'pending'",
        'total_revenue' => "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'completed'",
        'today_revenue' => "SELECT SUM(total_amount) as total FROM orders WHERE DATE(order_date) = CURDATE() AND payment_status = 'completed'",
        'monthly_revenue' => "SELECT SUM(total_amount) as total FROM orders WHERE MONTH(order_date) = MONTH(CURDATE()) AND YEAR(order_date) = YEAR(CURDATE()) AND payment_status = 'completed'"
    ];
    
    foreach($queries as $key => $query) {
        $stmt = $conn->query($query);
        $stats[$key] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    }
    
    // Recent orders
    $stmt = $conn->query("
        SELECT o.*, u.username 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY order_date DESC 
        LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent users
    $stmt = $conn->query("
        SELECT * FROM users 
        WHERE role = 'user' 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Low stock products
    $stmt = $conn->query("
        SELECT * FROM products 
        WHERE stock < 10 
        ORDER BY stock ASC 
        LIMIT 5
    ");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $error = "Failed to load dashboard statistics: " . $e->getMessage();
}

// Get data for each section
$products = [];
$users = [];
$orders = [];
$activities = [];

if($currentSection === 'products') {
    $search = $_GET['search'] ?? '';
    $brand = $_GET['brand'] ?? 'all';
    $sort = $_GET['sort'] ?? 'newest';
    
    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];
    
    if(!empty($search)) {
        $sql .= " AND (name LIKE :search OR model LIKE :search OR description LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if($brand !== 'all') {
        $sql .= " AND brand = :brand";
        $params[':brand'] = $brand;
    }
    
    switch($sort) {
        case 'price_low': $sql .= " ORDER BY price ASC"; break;
        case 'price_high': $sql .= " ORDER BY price DESC"; break;
        case 'name': $sql .= " ORDER BY name ASC"; break;
        case 'stock_low': $sql .= " ORDER BY stock ASC"; break;
        case 'newest':
        default: $sql .= " ORDER BY created_at DESC"; break;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif($currentSection === 'users') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    
    $sql = "SELECT * FROM users WHERE role = 'user'";
    $params = [];
    
    if(!empty($search)) {
        $sql .= " AND (username LIKE :search OR email LIKE :search OR full_name LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if($status !== 'all') {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif($currentSection === 'orders') {
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $payment_status = $_GET['payment_status'] ?? 'all';
    
    $sql = "
        SELECT o.*, u.username, u.email, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE 1=1
    ";
    $params = [];
    
    if(!empty($search)) {
        $sql .= " AND (o.order_number LIKE :search OR u.username LIKE :search OR u.email LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    if($status !== 'all') {
        $sql .= " AND o.order_status = :status";
        $params[':status'] = $status;
    }
    
    if($payment_status !== 'all') {
        $sql .= " AND o.payment_status = :payment_status";
        $params[':payment_status'] = $payment_status;
    }
    
    $sql .= " ORDER BY o.order_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif($currentSection === 'activity') {
    $stmt = $conn->query("
        SELECT a.*, u.username 
        FROM user_activity a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 50
    ");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get product details for edit
$editProduct = null;
if(isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $productId = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $productId]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get order details
$viewOrder = null;
if(isset($_GET['view_order']) && is_numeric($_GET['view_order'])) {
    $orderId = intval($_GET['view_order']);
    $stmt = $conn->prepare("
        SELECT o.*, u.username, u.email, u.phone, u.address 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = :id
    ");
    $stmt->execute([':id' => $orderId]);
    $viewOrder = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($viewOrder) {
        $stmt = $conn->prepare("
            SELECT oi.*, p.name, p.brand, p.image_url 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = :order_id
        ");
        $stmt->execute([':order_id' => $orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mobile Suking</title>
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
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 0.5rem;
        }

        .admin-info {
            font-size: 0.9rem;
            opacity: 0.8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #3498db;
        }

        .nav-item.active {
            background-color: rgba(255,255,255,0.15);
            border-left-color: #ffcc00;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 1rem 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .page-title {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background-color: #3498db;
            color: white;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-success {
            background-color: #2ecc71;
            color: white;
        }

        .btn-warning {
            background-color: #ffcc00;
            color: #333;
        }

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .icon-users { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); }
        .icon-products { background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); }
        .icon-orders { background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); }
        .icon-revenue { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Content Box */
        .content-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .box-title {
            font-size: 1.3rem;
            color: #2c3e50;
            font-weight: 600;
        }

        /* Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #eee;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background-color: #f9f9f9;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-active { background-color: #d4edda; color: #155724; }
        .badge-banned { background-color: #f8d7da; color: #721c24; }
        .badge-pending { background-color: #fff3cd; color: #856404; }
        .badge-processing { background-color: #cce5ff; color: #004085; }
        .badge-shipped { background-color: #d1ecf1; color: #0c5460; }
        .badge-delivered { background-color: #d4edda; color: #155724; }
        .badge-cancelled { background-color: #f8d7da; color: #721c24; }
        .badge-completed { background-color: #d4edda; color: #155724; }
        .badge-failed { background-color: #f8d7da; color: #721c24; }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 0.85rem;
            border-radius: 4px;
            min-width: 80px;
        }

        /* Forms */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Filters */
        .filters {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 150px;
            flex: 1;
        }

        .filter-label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }

        /* Dashboard Sections */
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1200px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }

        /* Product Image in Table */
        .product-image-small {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #eee;
        }

        /* Order Details */
        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .order-details {
                grid-template-columns: 1fr;
            }
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .order-items-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .order-items-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .sidebar-header span,
            .nav-item span {
                display: none;
            }
            
            .logo span {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 1rem;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 1.2rem;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .filters {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: 100%;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.3s ease;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <i class="fas fa-mobile-alt"></i>
                <span>Mobile Suking</span>
            </a>
            <div class="admin-info">
                <i class="fas fa-user-shield"></i> Admin Panel
            </div>
        </div>
        
        <div class="sidebar-nav">
            <a href="?section=dashboard" class="nav-item <?php echo $currentSection === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="?section=products" class="nav-item <?php echo $currentSection === 'products' ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            
            <a href="?section=orders" class="nav-item <?php echo $currentSection === 'orders' ? 'active' : ''; ?>">
                <i class="fas fa-shopping-bag"></i>
                <span>Orders</span>
            </a>
            
            <a href="?section=users" class="nav-item <?php echo $currentSection === 'users' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            
            <a href="?section=activity" class="nav-item <?php echo $currentSection === 'activity' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
            
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>View Store</span>
            </a>
            
            <a href="user-dashboard.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>User Dashboard</span>
            </a>
            
            <a href="logout.php" class="nav-item" style="color: #ff6b6b;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <h1 class="page-title">
                <?php 
                $titles = [
                    'dashboard' => 'Dashboard',
                    'products' => 'Product Management',
                    'orders' => 'Order Management',
                    'users' => 'User Management',
                    'activity' => 'Activity Log'
                ];
                echo $titles[$currentSection] ?? 'Admin Dashboard';
                ?>
            </h1>
            
            <div class="user-menu">
                <span style="color: #2c3e50; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($userName); ?>
                </span>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-store"></i> View Store
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if($success): ?>
            <div class="message success fade-in">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="message error fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Dashboard Section -->
        <?php if($currentSection === 'dashboard'): ?>
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-icon icon-users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Total Users</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <i class="fas fa-check-circle" style="color: #2ecc71;"></i> Active: <?php echo $stats['active_users']; ?> | 
                            <i class="fas fa-ban" style="color: #e74c3c;"></i> Banned: <?php echo $stats['banned_users']; ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-icon icon-products">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                        <div class="stat-label">Total Products</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <i class="fas fa-star" style="color: #ffcc00;"></i> Featured: <?php echo $stats['featured_products']; ?> | 
                            <i class="fas fa-exclamation-triangle" style="color: #e67e22;"></i> Low Stock: <?php echo $stats['low_stock']; ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-icon icon-orders">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                        <div class="stat-label">Total Orders</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <i class="fas fa-clock" style="color: #f39c12;"></i> Pending: <?php echo $stats['pending_orders']; ?>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card fade-in">
                    <div class="stat-icon icon-revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value">ETB <?php echo number_format($stats['total_revenue'], 0); ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div style="font-size: 0.8rem; color: #888; margin-top: 5px;">
                            <i class="fas fa-calendar-day"></i> Today: ETB <?php echo number_format($stats['today_revenue'], 0); ?> |
                            <i class="fas fa-calendar-alt"></i> Month: ETB <?php echo number_format($stats['monthly_revenue'], 0); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Sections -->
            <div class="dashboard-sections">
                <!-- Recent Orders -->
                <div class="content-box fade-in">
                    <div class="box-header">
                        <h3 class="box-title">Recent Orders</h3>
                        <a href="?section=orders" class="btn btn-secondary btn-small">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                    
                    <?php if(count($recentOrders) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentOrders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="?section=orders&view_order=<?php echo $order['id']; ?>" 
                                           style="color: #3498db; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td style="color: #2ecc71; font-weight: 600;">
                                        ETB <?php echo number_format($order['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-shopping-bag" style="font-size: 2rem; color: #ddd; margin-bottom: 1rem; display: block;"></i>
                            No recent orders
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Recent Users -->
                <div class="content-box fade-in">
                    <div class="box-header">
                        <h3 class="box-title">Recent Users</h3>
                        <a href="?section=users" class="btn btn-secondary btn-small">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                    
                    <?php if(count($recentUsers) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; padding: 2rem; color: #666;">
                            <i class="fas fa-users" style="font-size: 2rem; color: #ddd; margin-bottom: 1rem; display: block;"></i>
                            No recent users
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Low Stock Products -->
            <div class="content-box fade-in">
                <div class="box-header">
                    <h3 class="box-title">Low Stock Products</h3>
                    <a href="?section=products&sort=stock_low" class="btn btn-danger btn-small">
                        <i class="fas fa-exclamation-triangle"></i> View All
                    </a>
                </div>
                
                <?php if(count($lowStockProducts) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Brand</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lowStockProducts as $product): 
                                $optimizedImage = optimizeCloudinaryImage($product['image_url'], 50, 50);
                                $fallbackImage = getFallbackImage($product['brand']);
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="<?php echo htmlspecialchars($optimizedImage); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="product-image-small"
                                             onerror="this.src='<?php echo $fallbackImage; ?>'">
                                        <span><?php echo htmlspecialchars($product['name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo ucfirst($product['brand']); ?></td>
                                <td style="color: #2ecc71; font-weight: 600;">
                                    ETB <?php echo number_format($product['price'], 2); ?>
                                </td>
                                <td>
                                    <span style="color: <?php echo $product['stock'] < 3 ? '#e74c3c' : '#e67e22'; ?>; font-weight: 600;">
                                        <i class="fas fa-box"></i> <?php echo $product['stock']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?section=products&edit=<?php echo $product['id']; ?>" 
                                       class="btn btn-warning btn-small">
                                        <i class="fas fa-edit"></i> Restock
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: #666;">
                        <i class="fas fa-check-circle" style="font-size: 2rem; color: #2ecc71; margin-bottom: 1rem; display: block;"></i>
                        All products have sufficient stock
                    </p>
                <?php endif; ?>
            </div>

        <!-- Products Section -->
        <?php elseif($currentSection === 'products'): ?>
            <!-- Add/Edit Product Form -->
            <?php if(isset($_GET['edit']) || isset($_GET['add'])): ?>
                <div class="content-box fade-in">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-<?php echo $editProduct ? 'edit' : 'plus'; ?>"></i>
                            <?php echo $editProduct ? 'Edit Product' : 'Add New Product'; ?>
                        </h3>
                        <a href="?section=products" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Products
                        </a>
                    </div>
                    
                    <div class="form-container">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $editProduct ? 'update_product' : 'add_product'; ?>">
                            <?php if($editProduct): ?>
                                <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Product Name *</label>
                                    <input type="text" name="name" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['name'] ?? ''); ?>" 
                                           required placeholder="e.g., Samsung Galaxy S24 Ultra">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Brand *</label>
                                    <select name="brand" class="form-select" required>
                                        <option value="">Select Brand</option>
                                        <option value="samsung" <?php echo ($editProduct['brand'] ?? '') === 'samsung' ? 'selected' : ''; ?>>Samsung</option>
                                        <option value="iphone" <?php echo ($editProduct['brand'] ?? '') === 'iphone' ? 'selected' : ''; ?>>iPhone</option>
                                        <option value="huawei" <?php echo ($editProduct['brand'] ?? '') === 'huawei' ? 'selected' : ''; ?>>Huawei</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Model</label>
                                    <input type="text" name="model" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['model'] ?? ''); ?>"
                                           placeholder="e.g., S24 Ultra, 15 Pro Max">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Price (ETB) *</label>
                                    <input type="number" name="price" class="form-input" step="0.01" min="0" 
                                           value="<?php echo $editProduct['price'] ?? ''; ?>" required placeholder="159999.00">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Original Price (ETB)</label>
                                    <input type="number" name="original_price" class="form-input" step="0.01" min="0" 
                                           value="<?php echo $editProduct['original_price'] ?? ''; ?>"
                                           placeholder="169999.00">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Stock *</label>
                                    <input type="number" name="stock" class="form-input" min="0" 
                                           value="<?php echo $editProduct['stock'] ?? 10; ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Image URL</label>
                                <input type="text" name="image_url" class="form-input" 
                                       value="<?php echo htmlspecialchars($editProduct['image_url'] ?? ''); ?>"
                                       placeholder="https://res.cloudinary.com/YOUR_CLOUD_NAME/image/upload/v1/products/product-image">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-textarea" rows="3" 
                                          placeholder="Enter product description..."><?php echo htmlspecialchars($editProduct['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Storage</label>
                                    <input type="text" name="storage" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['storage'] ?? ''); ?>"
                                           placeholder="256GB, 512GB, 1TB">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Color</label>
                                    <input type="text" name="color" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['color'] ?? ''); ?>"
                                           placeholder="Black, White, Blue">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Screen Size</label>
                                    <input type="text" name="screen_size" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['screen_size'] ?? ''); ?>"
                                           placeholder="6.7 inches, 6.8 inches">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Camera</label>
                                    <input type="text" name="camera" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['camera'] ?? ''); ?>"
                                           placeholder="48MP + 12MP + 12MP">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Battery</label>
                                    <input type="text" name="battery" class="form-input" 
                                           value="<?php echo htmlspecialchars($editProduct['battery'] ?? ''); ?>"
                                           placeholder="5000mAh, 4500mAh">
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" name="featured" id="featured" value="1" 
                                               <?php echo ($editProduct['featured'] ?? 0) ? 'checked' : ''; ?>>
                                        <label for="featured" class="form-label" style="margin-bottom: 0;">
                                            <i class="fas fa-star" style="color: #ffcc00;"></i> Featured Product
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Specifications (One per line)</label>
                                <textarea name="specifications" class="form-textarea" rows="4" 
                                          placeholder="Snapdragon 8 Gen 3, 12GB RAM
Android 14
5G Support
Wireless Charging"><?php echo htmlspecialchars($editProduct['specifications'] ?? ''); ?></textarea>
                            </div>
                            
                            <div style="display: flex; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> 
                                    <?php echo $editProduct ? 'Update Product' : 'Add Product'; ?>
                                </button>
                                <a href="?section=products" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            
            <!-- Product List -->
            <?php else: ?>
                <div class="content-box fade-in">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-box"></i> Product Management
                        </h3>
                        <a href="?section=products&add=true" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add New Product
                        </a>
                    </div>
                    
                    <!-- Filters -->
                    <form method="GET" class="filters">
                        <input type="hidden" name="section" value="products">
                        
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" class="form-input" 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   placeholder="Search products...">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Brand</label>
                            <select name="brand" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($_GET['brand'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Brands</option>
                                <option value="samsung" <?php echo ($_GET['brand'] ?? '') === 'samsung' ? 'selected' : ''; ?>>Samsung</option>
                                <option value="iphone" <?php echo ($_GET['brand'] ?? '') === 'iphone' ? 'selected' : ''; ?>>iPhone</option>
                                <option value="huawei" <?php echo ($_GET['brand'] ?? '') === 'huawei' ? 'selected' : ''; ?>>Huawei</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Sort By</label>
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="newest" <?php echo ($_GET['sort'] ?? 'newest') === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="name" <?php echo ($_GET['sort'] ?? '') === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                <option value="price_low" <?php echo ($_GET['sort'] ?? '') === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo ($_GET['sort'] ?? '') === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="stock_low" <?php echo ($_GET['sort'] ?? '') === 'stock_low' ? 'selected' : ''; ?>>Stock: Low to High</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="?section=products" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                    
                    <!-- Products Table -->
                    <?php if(count($products) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Product</th>
                                    <th>Brand</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Featured</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $product): 
                                    $optimizedImage = optimizeCloudinaryImage($product['image_url'], 50, 50);
                                    $fallbackImage = getFallbackImage($product['brand']);
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($optimizedImage); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="product-image-small"
                                             onerror="this.src='<?php echo $fallbackImage; ?>'">
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></div>
                                        <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($product['model']); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $product['status'] ?? 'active'; ?>">
                                            <?php echo ucfirst($product['brand']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #2ecc71;">
                                            ETB <?php echo number_format($product['price'], 2); ?>
                                        </div>
                                        <?php if($product['original_price'] > $product['price']): ?>
                                            <div style="font-size: 0.8rem; color: #999; text-decoration: line-through;">
                                                ETB <?php echo number_format($product['original_price'], 2); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="color: <?php 
                                            echo $product['stock'] == 0 ? '#e74c3c' : 
                                                ($product['stock'] < 10 ? '#e67e22' : '#2ecc71'); 
                                        ?>; font-weight: 600;">
                                            <i class="fas fa-box"></i> <?php echo $product['stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($product['featured']): ?>
                                            <span class="status-badge badge-active" style="background-color: #ffcc00; color: #333;">
                                                <i class="fas fa-star"></i> Featured
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #666; font-size: 0.9rem;">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?section=products&edit=<?php echo $product['id']; ?>" 
                                               class="btn btn-secondary btn-small" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_product">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small" 
                                                        onclick="return confirm('Are you sure you want to delete this product?')" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <a href="product-detail.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-primary btn-small" target="_blank" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <h3>No products found</h3>
                            <p>Try adjusting your search or filter criteria</p>
                            <a href="?section=products" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-undo"></i> Clear All Filters
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <!-- Orders Section -->
        <?php elseif($currentSection === 'orders'): ?>
            <!-- Order Details -->
            <?php if(isset($viewOrder)): ?>
                <div class="content-box fade-in">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-file-invoice"></i> Order Details: <?php echo htmlspecialchars($viewOrder['order_number']); ?>
                        </h3>
                        <div style="display: flex; gap: 1rem;">
                            <a href="?section=orders" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Orders
                            </a>
                            <button onclick="window.print()" class="btn btn-primary">
                                <i class="fas fa-print"></i> Print Invoice
                            </button>
                        </div>
                    </div>
                    
                    <div class="order-details">
                        <!-- Order Information -->
                        <div>
                            <h4 style="margin-bottom: 1rem; color: #2c3e50;">
                                <i class="fas fa-info-circle"></i> Order Information
                            </h4>
                            <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                                <div style="display: grid; gap: 0.75rem;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Order Number:</span>
                                        <span style="font-weight: 600; color: #3498db;"><?php echo htmlspecialchars($viewOrder['order_number']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Order Date:</span>
                                        <span><?php echo date('F j, Y, g:i a', strtotime($viewOrder['order_date'])); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Customer:</span>
                                        <span><?php echo htmlspecialchars($viewOrder['username']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Email:</span>
                                        <span><?php echo htmlspecialchars($viewOrder['email']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Phone:</span>
                                        <span><?php echo htmlspecialchars($viewOrder['phone']); ?></span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Shipping Address:</span>
                                        <span><?php echo htmlspecialchars($viewOrder['shipping_address']); ?></span>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #ddd;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <span style="font-weight: 600; color: #555;">Order Status:</span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_order_status">
                                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" 
                                                    style="padding: 5px 10px; border-radius: 4px; border: 1px solid #ddd; background: white;">
                                                <option value="pending" <?php echo $viewOrder['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="processing" <?php echo $viewOrder['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                <option value="shipped" <?php echo $viewOrder['order_status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                <option value="delivered" <?php echo $viewOrder['order_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                <option value="cancelled" <?php echo $viewOrder['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                        <span style="font-weight: 600; color: #555;">Payment Status:</span>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="update_payment_status">
                                            <input type="hidden" name="order_id" value="<?php echo $viewOrder['id']; ?>">
                                            <select name="payment_status" onchange="this.form.submit()" 
                                                    style="padding: 5px 10px; border-radius: 4px; border: 1px solid #ddd; background: white;">
                                                <option value="pending" <?php echo $viewOrder['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="completed" <?php echo $viewOrder['payment_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="failed" <?php echo $viewOrder['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between;">
                                        <span style="font-weight: 600; color: #555;">Payment Method:</span>
                                        <span style="text-transform: capitalize; font-weight: 600;">
                                            <?php echo ucfirst($viewOrder['payment_method']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div>
                            <h4 style="margin-bottom: 1rem; color: #2c3e50;">
                                <i class="fas fa-shopping-cart"></i> Order Items
                            </h4>
                            <?php if(isset($orderItems) && count($orderItems) > 0): ?>
                                <table class="order-items-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $subtotal = 0;
                                        foreach($orderItems as $item): 
                                            $itemTotal = $item['price'] * $item['quantity'];
                                            $subtotal += $itemTotal;
                                            $optimizedImage = optimizeCloudinaryImage($item['image_url'], 40, 40);
                                            $fallbackImage = getFallbackImage($item['brand']);
                                        ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <img src="<?php echo htmlspecialchars($optimizedImage); ?>" 
                                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid #eee;"
                                                         onerror="this.src='<?php echo $fallbackImage; ?>'">
                                                    <div>
                                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($item['name']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #666;"><?php echo ucfirst($item['brand']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                                            <td style="color: #2ecc71;">ETB <?php echo number_format($item['price'], 2); ?></td>
                                            <td style="color: #3498db; font-weight: 600;">ETB <?php echo number_format($itemTotal, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Order Summary -->
                                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 5px; margin-top: 1.5rem; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);">
                                    <h5 style="margin-bottom: 1rem; color: #2c3e50;">Order Summary</h5>
                                    <div style="display: grid; gap: 0.5rem;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Subtotal:</span>
                                            <span>ETB <?php echo number_format($subtotal, 2); ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Shipping:</span>
                                            <span>ETB <?php echo number_format($subtotal > 50000 ? 0 : 500, 2); ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between;">
                                            <span>Tax (15%):</span>
                                            <span>ETB <?php echo number_format($subtotal * 0.15, 2); ?></span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 2px solid #ddd;">
                                            <span>Total:</span>
                                            <span style="color: #2ecc71;">
                                                ETB <?php echo number_format($viewOrder['total_amount'], 2); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 2rem; color: #666;">
                                    <i class="fas fa-shopping-cart" style="font-size: 2rem; color: #ddd; margin-bottom: 1rem;"></i>
                                    <p>No items found for this order</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            
            <!-- Orders List -->
            <?php else: ?>
                <div class="content-box fade-in">
                    <div class="box-header">
                        <h3 class="box-title">
                            <i class="fas fa-shopping-bag"></i> Order Management
                        </h3>
                    </div>
                    
                    <!-- Filters -->
                    <form method="GET" class="filters">
                        <input type="hidden" name="section" value="orders">
                        
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" class="form-input" 
                                   value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                   placeholder="Search orders...">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Order Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($_GET['status'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo ($_GET['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo ($_GET['status'] ?? '') === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo ($_GET['status'] ?? '') === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="cancelled" <?php echo ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Payment Status</label>
                            <select name="payment_status" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo ($_GET['payment_status'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Payment</option>
                                <option value="pending" <?php echo ($_GET['payment_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo ($_GET['payment_status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="failed" <?php echo ($_GET['payment_status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="?section=orders" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </form>
                    
                    <!-- Orders Table -->
                    <?php if(count($orders) > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="?section=orders&view_order=<?php echo $order['id']; ?>" 
                                           style="color: #3498db; text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($order['order_number']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['username']); ?></div>
                                        <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($order['email']); ?></div>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($order['order_date'])); ?></td>
                                    <td style="text-align: center;"><?php echo $order['item_count']; ?></td>
                                    <td style="color: #2ecc71; font-weight: 600;">
                                        ETB <?php echo number_format($order['total_amount'], 2); ?>
                                    </td>
                                    <td>
                                        <div style="text-transform: capitalize;"><?php echo ucfirst($order['payment_method']); ?></div>
                                        <span class="status-badge badge-<?php echo $order['payment_status']; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge badge-<?php echo $order['order_status']; ?>">
                                            <?php echo ucfirst($order['order_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="?section=orders&view_order=<?php echo $order['id']; ?>" 
                                               class="btn btn-primary btn-small" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_order_status">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <select name="status" onchange="this.form.submit()" 
                                                        style="padding: 5px 10px; border-radius: 4px; border: 1px solid #ddd; background: white; font-size: 0.85rem; min-width: 100px;">
                                                    <option value="">Update Status</option>
                                                    <option value="pending">Pending</option>
                                                    <option value="processing">Processing</option>
                                                    <option value="shipped">Shipped</option>
                                                    <option value="delivered">Delivered</option>
                                                </select>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 3rem; color: #666;">
                            <i class="fas fa-shopping-bag" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                            <h3>No orders found</h3>
                            <p>Try adjusting your search or filter criteria</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <!-- Users Section -->
        <?php elseif($currentSection === 'users'): ?>
            <div class="content-box fade-in">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-users"></i> User Management
                    </h3>
                </div>
                
                <!-- Filters -->
                <form method="GET" class="filters">
                    <input type="hidden" name="section" value="users">
                    
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="form-input" 
                               value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                               placeholder="Search users...">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($_GET['status'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="banned" <?php echo ($_GET['status'] ?? '') === 'banned' ? 'selected' : ''; ?>>Banned</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="?section=users" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
                
                <!-- Users Table -->
                <?php if(count($users) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Joined</th>
                                <th>Orders</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach($users as $user): 
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = :user_id");
                                $stmt->execute([':user_id' => $user['id']]);
                                $orderCount = $stmt->fetchColumn();
                            ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div style="font-size: 0.8rem; color: #666;"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td style="text-align: center;">
                                    <span style="color: #3498db; font-weight: 600;"><?php echo $orderCount; ?></span>
                                </td>
                                <td>
                                    <span class="status-badge badge-<?php echo $user['status']; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if($user['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="ban_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-small"
                                                        onclick="return confirm('Are you sure you want to ban user <?php echo htmlspecialchars(addslashes($user['username'])); ?>?')"
                                                        title="Ban User">
                                                    <i class="fas fa-ban"></i> Ban
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="activate_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-small" title="Activate User">
                                                    <i class="fas fa-check"></i> Activate
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="user-dashboard.php?user_id=<?php echo $user['id']; ?>" 
                                           class="btn btn-primary btn-small" target="_blank" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3>No users found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    </div>
                <?php endif; ?>
            </div>

        <!-- Activity Log Section -->
        <?php elseif($currentSection === 'activity'): ?>
            <div class="content-box fade-in">
                <div class="box-header">
                    <h3 class="box-title">
                        <i class="fas fa-history"></i> Activity Log
                    </h3>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_activity">
                        <button type="submit" class="btn btn-danger" 
                                onclick="return confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Clear Logs
                        </button>
                    </form>
                </div>
                
                <?php if(count($activities) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>User</th>
                                <th>Activity Type</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($activities as $activity): ?>
                            <tr>
                                <td style="color: #666; font-size: 0.9rem;">
                                    <?php echo date('M j, Y g:i a', strtotime($activity['created_at'])); ?>
                                </td>
                                <td>
                                    <?php if($activity['user_id']): ?>
                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($activity['username'] ?? 'User #' . $activity['user_id']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #666; font-style: italic;">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php 
                                        echo $activity['activity_type'] === 'login' ? 'badge-active' : 
                                            ($activity['activity_type'] === 'register' ? 'badge-delivered' : 'badge-processing');
                                    ?>">
                                        <?php echo ucfirst($activity['activity_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                <td>
                                    <span style="font-family: monospace; color: #666;">
                                        <?php echo htmlspecialchars($activity['ip_address']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: #666;">
                        <i class="fas fa-history" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                        <h3>No activity logs found</h3>
                        <p>User activity will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-refresh dashboard every 5 minutes
        if(window.location.search.includes('section=dashboard')) {
            setTimeout(() => {
                location.reload();
            }, 300000);
        }

        // Confirm delete
        function confirmDelete(item, id, type) {
            if(confirm(`Are you sure you want to delete this ${type}? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_' + type;
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = type + '_id';
                idInput.value = id;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Session timeout warning
        let lastActivity = <?php echo $_SESSION['last_activity'] ?? time(); ?>;
        
        function checkSession() {
            const now = Math.floor(Date.now() / 1000);
            const timeSince = now - lastActivity;
            const timeLeft = 86400 - timeSince;
            
            if(timeLeft < 300) {
                showSessionWarning(timeLeft);
            }
        }
        
        function showSessionWarning(secondsLeft) {
            const minutes = Math.ceil(secondsLeft / 60);
            const warning = document.createElement('div');
            warning.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #e74c3c;
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 5px;
                z-index: 3000;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                max-width: 300px;
                animation: slideIn 0.3s ease;
            `;
            warning.innerHTML = `
                <strong style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <i class="fas fa-exclamation-triangle"></i> Session Expiring Soon!
                </strong>
                <p style="margin-bottom: 10px;">Your session will expire in ${minutes} minutes.</p>
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="extendSession()" style="background: #2ecc71; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; flex: 1;">
                        <i class="fas fa-sync"></i> Extend Session
                    </button>
                    <button onclick="this.parentElement.parentElement.remove()" style="background: #95a5a6; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">
                        Dismiss
                    </button>
                </div>
            `;
            
            // Add animation style
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
            
            // Remove existing warning
            const existingWarning = document.querySelector('[style*="position: fixed; bottom: 20px; right: 20px; background: #e74c3c"]');
            if(existingWarning) existingWarning.remove();
            
            document.body.appendChild(warning);
        }
        
        function extendSession() {
            fetch('ajax_handler.php?action=keep_alive')
                .then(() => {
                    lastActivity = Math.floor(Date.now() / 1000);
                    const warning = document.querySelector('[style*="position: fixed; bottom: 20px; right: 20px; background: #e74c3c"]');
                    if(warning) warning.remove();
                    
                    // Show success message
                    const success = document.createElement('div');
                    success.style.cssText = `
                        position: fixed;
                        bottom: 20px;
                        right: 20px;
                        background: #2ecc71;
                        color: white;
                        padding: 1rem 1.5rem;
                        border-radius: 5px;
                        z-index: 3000;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                        animation: slideIn 0.3s ease;
                    `;
                    success.innerHTML = `
                        <strong style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle"></i> Session Extended!
                        </strong>
                        <p>Your session has been extended by 24 hours.</p>
                    `;
                    document.body.appendChild(success);
                    
                    setTimeout(() => {
                        success.style.animation = 'slideOut 0.3s ease';
                        setTimeout(() => success.remove(), 300);
                    }, 3000);
                })
                .catch(() => {
                    alert('Failed to extend session. Please refresh the page.');
                });
        }
        
        // Check session every minute
        setInterval(checkSession, 60000);
        
        // Update last activity on user interaction
        ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
            document.addEventListener(event, () => {
                lastActivity = Math.floor(Date.now() / 1000);
            });
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to all content boxes
            document.querySelectorAll('.content-box').forEach((box, index) => {
                box.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Handle image errors
            document.querySelectorAll('img').forEach(img => {
                img.addEventListener('error', function() {
                    if(this.src.includes('cloudinary.com')) {
                        const brand = this.closest('tr')?.querySelector('.status-badge')?.textContent?.toLowerCase() || 'default';
                        const fallbackImages = {
                            'samsung': 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=50&h=50&fit=crop&q=80',
                            'iphone': 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=50&h=50&fit=crop&q=80',
                            'huawei': 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=50&h=50&fit=crop&q=80',
                            'default': 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=50&h=50&fit=crop&q=80'
                        };
                        this.src = fallbackImages[brand] || fallbackImages.default;
                    }
                });
            });
        });

        // Add slideOut animation
        const slideOutStyle = document.createElement('style');
        slideOutStyle.textContent = `
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(slideOutStyle);
    </script>
</body>
</html>