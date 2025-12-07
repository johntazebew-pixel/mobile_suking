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
    header('Location: login-register.php?redirect=' . urlencode('user-dashboard.php'));
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

// Fetch user details
$userDetails = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $userId]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Failed to load user details.";
}

// Fetch user orders
$userOrders = [];
try {
    $stmt = $conn->prepare("
        SELECT o.*, 
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
        FROM orders o 
        WHERE user_id = :user_id 
        ORDER BY order_date DESC
        LIMIT 10
    ");
    $stmt->execute([':user_id' => $userId]);
    $userOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Failed to load orders.";
}

// Fetch wishlist items
$wishlistItems = [];
try {
    $stmt = $conn->prepare("
        SELECT w.*, p.name, p.brand, p.price, p.image_url, p.stock 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.id 
        WHERE w.user_id = :user_id 
        ORDER BY w.added_at DESC
        LIMIT 8
    ");
    $stmt->execute([':user_id' => $userId]);
    $wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Wishlist might not exist, ignore error
}

// Get cart count
$cartCount = 0;
try {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $cartCount = $result['total'] ?? 0;
} catch(PDOException $e) {
    // Cart might be empty
}

// Handle profile update
$updateSuccess = false;
$updateErrors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validate
    if(empty($fullName)) {
        $updateErrors[] = 'Full name is required';
    }
    
    if(count($updateErrors) === 0) {
        try {
            $stmt = $conn->prepare("
                UPDATE users 
                SET full_name = :full_name, phone = :phone, address = :address 
                WHERE id = :id
            ");
            $stmt->execute([
                ':full_name' => $fullName,
                ':phone' => $phone,
                ':address' => $address,
                ':id' => $userId
            ]);
            
            $updateSuccess = true;
            $_SESSION['full_name'] = $fullName;
            
        } catch(PDOException $e) {
            $updateErrors[] = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle password change
$passwordSuccess = false;
$passwordErrors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate
    if(empty($currentPassword)) {
        $passwordErrors[] = 'Current password is required';
    }
    if(empty($newPassword)) {
        $passwordErrors[] = 'New password is required';
    }
    if(strlen($newPassword) < 6) {
        $passwordErrors[] = 'New password must be at least 6 characters';
    }
    if($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'Passwords do not match';
    }
    
    // Verify current password
    if(empty($passwordErrors) && $currentPassword !== $userDetails['password']) {
        $passwordErrors[] = 'Current password is incorrect';
    }
    
    if(count($passwordErrors) === 0) {
        try {
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute([':password' => $newPassword, ':id' => $userId]);
            $passwordSuccess = true;
            
        } catch(PDOException $e) {
            $passwordErrors[] = 'Failed to change password. Please try again.';
        }
    }
}

// Get current section from URL
$currentSection = $_GET['section'] ?? 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Mobile Suking</title>
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

        .nav-links a:hover {
            background-color: rgba(255,255,255,0.1);
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

        .btn-primary {
            background: linear-gradient(135deg, #ffcc00 0%, #ffaa00 100%);
            color: #333;
        }

        .btn-secondary {
            background-color: transparent;
            color: white;
            border: 2px solid white;
        }

        /* Dashboard Container */
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .welcome-text h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .welcome-text p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .user-stats {
            display: flex;
            gap: 2rem;
            text-align: center;
        }

        .stat-item {
            padding: 1rem 1.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar */
        .sidebar {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            height: fit-content;
        }

        .user-profile {
            padding: 2rem;
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .avatar {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2.5rem;
            color: white;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .user-name {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .user-email {
            color: #666;
            font-size: 0.9rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 2rem;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .nav-item:hover {
            background-color: #f8f9fa;
            color: #667eea;
        }

        .nav-item.active {
            background-color: #f0f2ff;
            color: #667eea;
            border-left-color: #667eea;
        }

        /* Main Content */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        /* Content Section */
        .content-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 2rem;
            animation: fadeIn 0.3s ease;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 1.5rem;
            color: #667eea;
        }

        /* Orders Table */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th {
            background-color: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #eee;
        }

        .orders-table td {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .orders-table tr:hover {
            background-color: #f9f9f9;
        }

        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-processing { background-color: #cce5ff; color: #004085; }
        .status-shipped { background-color: #d4edda; color: #155724; }
        .status-delivered { background-color: #d1ecf1; color: #0c5460; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }

        /* Wishlist Items */
        .wishlist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .wishlist-item {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .wishlist-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .wishlist-image {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            padding: 1rem;
            overflow: hidden;
        }

        .wishlist-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
        }

        .wishlist-item:hover .wishlist-image img {
            transform: scale(1.05);
        }

        .wishlist-info {
            padding: 1.5rem;
        }

        .wishlist-name {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            height: 2.8rem;
            overflow: hidden;
            line-height: 1.4;
        }

        .wishlist-brand {
            color: #667eea;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .wishlist-price {
            color: #2ecc71;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }

        .wishlist-actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.5rem;
        }

        .btn-add-cart, .btn-remove-wishlist {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .btn-add-cart {
            background-color: #2ecc71;
            color: white;
        }

        .btn-add-cart:hover {
            background-color: #27ae60;
        }

        .btn-remove-wishlist {
            background-color: #ff4757;
            color: white;
            width: 40px;
        }

        .btn-remove-wishlist:hover {
            background-color: #ff2e43;
        }

        /* Forms */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-save {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .btn-cancel {
            background: #95a5a6;
            color: white;
        }

        .btn-change {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
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

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #555;
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
            
            .welcome-section {
                flex-direction: column;
                text-align: center;
                gap: 1.5rem;
            }
            
            .user-stats {
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .orders-table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 480px) {
            .wishlist-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .wishlist-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cart Count Animation */
        .cart-count {
            transition: transform 0.3s ease;
        }

        /* Stock Status */
        .stock-status {
            font-size: 0.8rem;
            padding: 3px 8px;
            border-radius: 3px;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .in-stock {
            background-color: #d4edda;
            color: #155724;
        }

        .low-stock {
            background-color: #fff3cd;
            color: #856404;
        }

        .out-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
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
                <li><a href="user-dashboard.php" style="background-color: rgba(255,255,255,0.3);"><i class="fas fa-user"></i> Dashboard</a></li>
            </ul>
            
            <div class="user-actions">
                <span style="color: #ffcc00; font-weight: 600; display: flex; align-items: center; gap: 5px;">
                    <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($userName); ?>
                </span>
                <?php if($isAdmin): ?>
                    <a href="admin-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-cog"></i> Admin Panel
                    </a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-secondary">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <a href="cart.php" class="btn" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); color: white;">
                    <i class="fas fa-shopping-cart"></i> 
                    <span id="cartCount" class="cart-count"><?php echo $cartCount; ?></span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Welcome back, <?php echo htmlspecialchars($userName); ?>!</h1>
                <p>Manage your account, view orders, and track your purchases</p>
            </div>
            <div class="user-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($userOrders); ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($wishlistItems); ?></div>
                    <div class="stat-label">Wishlist Items</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">
                        <?php 
                        $totalSpent = 0;
                        foreach($userOrders as $order) {
                            if($order['order_status'] === 'delivered' || $order['order_status'] === 'completed') {
                                $totalSpent += $order['total_amount'];
                            }
                        }
                        echo 'ETB ' . number_format($totalSpent, 0);
                        ?>
                    </div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Layout -->
        <div class="dashboard-layout">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="user-profile">
                    <div class="avatar">
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                    <h3 class="user-name"><?php echo htmlspecialchars($userDetails['full_name'] ?? $userName); ?></h3>
                    <p class="user-email"><?php echo htmlspecialchars($userEmail); ?></p>
                </div>
                <div class="sidebar-nav">
                    <a href="?section=profile" class="nav-item <?php echo $currentSection === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i> Profile Information
                    </a>
                    <a href="?section=orders" class="nav-item <?php echo $currentSection === 'orders' ? 'active' : ''; ?>">
                        <i class="fas fa-shopping-bag"></i> My Orders
                    </a>
                    <a href="?section=wishlist" class="nav-item <?php echo $currentSection === 'wishlist' ? 'active' : ''; ?>">
                        <i class="fas fa-heart"></i> Wishlist
                    </a>
                    <a href="?section=password" class="nav-item <?php echo $currentSection === 'password' ? 'active' : ''; ?>">
                        <i class="fas fa-lock"></i> Change Password
                    </a>
                    <a href="logout.php" class="nav-item" style="color: #ff4757;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Profile Information -->
                <?php if($currentSection === 'profile'): ?>
                <div class="content-section" id="profile-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i> Profile Information
                        </h2>
                        <button class="btn btn-primary" onclick="editProfile()" id="editProfileBtn">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                    
                    <?php if($updateSuccess): ?>
                        <div class="message success">
                            <i class="fas fa-check-circle"></i> Profile updated successfully!
                        </div>
                    <?php elseif(count($updateErrors) > 0): ?>
                        <div class="message error">
                            <?php foreach($updateErrors as $error): ?>
                                <div><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($userName); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-input" value="<?php echo htmlspecialchars($userEmail); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Full Name *</label>
                                <input type="text" name="full_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($userDetails['full_name'] ?? ''); ?>"
                                       id="fullNameInput" readonly required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>"
                                       id="phoneInput" readonly>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Address</label>
                                <textarea name="address" class="form-textarea" 
                                          rows="3" id="addressInput" readonly><?php echo htmlspecialchars($userDetails['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions" id="profileActions" style="display: none;">
                            <button type="submit" class="btn btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-cancel" onclick="cancelEdit()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- My Orders -->
                <?php if($currentSection === 'orders'): ?>
                <div class="content-section" id="orders-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-shopping-bag"></i> Recent Orders
                        </h2>
                        <a href="orders.php" class="btn btn-primary">
                            <i class="fas fa-eye"></i> View All Orders
                        </a>
                    </div>
                    
                    <?php if(count($userOrders) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($userOrders as $order): ?>
                                    <tr>
                                        <td>
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                               style="color: #3498db; text-decoration: none; font-weight: 600;">
                                                <?php echo htmlspecialchars($order['order_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo $order['item_count']; ?> item(s)</td>
                                        <td style="color: #2ecc71; font-weight: 600;">
                                            ETB <?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                        <td style="text-transform: capitalize;">
                                            <?php echo ucfirst($order['payment_method']); ?>
                                        </td>
                                        <td>
                                            <span class="order-status status-<?php echo $order['order_status']; ?>">
                                                <?php echo ucfirst($order['order_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn" 
                                               style="padding: 8px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 0.9rem;">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No orders yet</h3>
                            <p>You haven't placed any orders yet.</p>
                            <a href="products.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-shopping-bag"></i> Start Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Wishlist -->
                <?php if($currentSection === 'wishlist'): ?>
                <div class="content-section" id="wishlist-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-heart"></i> My Wishlist
                        </h2>
                        <?php if(count($wishlistItems) > 0): ?>
                            <button class="btn btn-primary" onclick="clearWishlist()">
                                <i class="fas fa-trash"></i> Clear All
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(count($wishlistItems) > 0): ?>
                        <div class="wishlist-grid">
                            <?php foreach($wishlistItems as $item): 
                                $optimizedImage = optimizeCloudinaryImage($item['image_url'], 250, 180);
                                $fallbackImage = getFallbackImage($item['brand']);
                            ?>
                            <div class="wishlist-item">
                                <div class="wishlist-image">
                                    <img src="<?php echo htmlspecialchars($optimizedImage); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         loading="lazy"
                                         onerror="this.src='<?php echo $fallbackImage; ?>'">
                                </div>
                                <div class="wishlist-info">
                                    <!-- Stock Status -->
                                    <?php if($item['stock'] > 10): ?>
                                        <span class="stock-status in-stock">In Stock</span>
                                    <?php elseif($item['stock'] > 0): ?>
                                        <span class="stock-status low-stock">Only <?php echo $item['stock']; ?> left</span>
                                    <?php else: ?>
                                        <span class="stock-status out-stock">Out of Stock</span>
                                    <?php endif; ?>
                                    
                                    <div class="wishlist-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <span class="wishlist-brand"><?php echo ucfirst($item['brand']); ?></span>
                                    <div class="wishlist-price">ETB <?php echo number_format($item['price'], 2); ?></div>
                                    <div class="wishlist-actions">
                                        <button class="btn-add-cart" onclick="addToCartFromWishlist(<?php echo $item['product_id']; ?>)" 
                                                <?php echo ($item['stock'] <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </button>
                                        <button class="btn-remove-wishlist" onclick="removeFromWishlist(<?php echo $item['product_id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-heart"></i>
                            <h3>Your wishlist is empty</h3>
                            <p>Add products you like to your wishlist.</p>
                            <a href="products.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-shopping-bag"></i> Browse Products
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Change Password -->
                <?php if($currentSection === 'password'): ?>
                <div class="content-section" id="password-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-lock"></i> Change Password
                        </h2>
                    </div>
                    
                    <?php if($passwordSuccess): ?>
                        <div class="message success">
                            <i class="fas fa-check-circle"></i> Password changed successfully!
                        </div>
                    <?php elseif(count($passwordErrors) > 0): ?>
                        <div class="message error">
                            <?php foreach($passwordErrors as $error): ?>
                                <div><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="passwordForm">
                        <input type="hidden" name="change_password" value="1">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Current Password *</label>
                                <input type="password" name="current_password" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password *</label>
                                <input type="password" name="new_password" class="form-input" required minlength="6">
                                <small style="color: #666; font-size: 0.85rem; margin-top: 5px; display: block;">
                                    Must be at least 6 characters long
                                </small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-change">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer style="background-color: #2c3e50; color: white; padding: 2rem; margin-top: 3rem;">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <p>&copy; <?php echo date('Y'); ?> Mobile Suking. All rights reserved.</p>
            <p style="margin-top: 1rem; color: #bdc3c7;">
                <i class="fas fa-phone"></i> +251 911 223 344 | 
                <i class="fas fa-envelope"></i> info@mobilesuking.com
            </p>
        </div>
    </footer>

    <script>
        // Edit profile
        function editProfile() {
            document.getElementById('fullNameInput').readOnly = false;
            document.getElementById('phoneInput').readOnly = false;
            document.getElementById('addressInput').readOnly = false;
            document.getElementById('profileActions').style.display = 'flex';
            document.getElementById('editProfileBtn').style.display = 'none';
        }

        function cancelEdit() {
            document.getElementById('fullNameInput').readOnly = true;
            document.getElementById('phoneInput').readOnly = true;
            document.getElementById('addressInput').readOnly = true;
            document.getElementById('profileActions').style.display = 'none';
            document.getElementById('editProfileBtn').style.display = 'flex';
        }

        // Wishlist functions
        function addToCartFromWishlist(productId) {
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=add_to_cart&product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showNotification('Product added to cart!', 'success');
                    updateCartCount(data.cart_count || 0);
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Failed to add to cart', 'error');
            });
        }

        function removeFromWishlist(productId) {
            if(confirm('Remove this item from your wishlist?')) {
                fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=remove_from_wishlist&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        showNotification('Removed from wishlist', 'success');
                        // Reload the page to update wishlist
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.message || 'Error removing from wishlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to remove from wishlist', 'error');
                });
            }
        }

        function clearWishlist() {
            if(confirm('Clear your entire wishlist? This action cannot be undone.')) {
                fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=clear_wishlist'
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        showNotification('Wishlist cleared', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.message || 'Error clearing wishlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Failed to clear wishlist', 'error');
                });
            }
        }

        // Update cart count
        function updateCartCount(count) {
            const cartCountElement = document.getElementById('cartCount');
            if(cartCountElement) {
                cartCountElement.textContent = count;
                cartCountElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    cartCountElement.style.transform = 'scale(1)';
                }, 300);
            }
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 5px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                animation: slideIn 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                ${type === 'success' ? 'background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);' : 'background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);'}
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}
            `;
            
            document.body.appendChild(notification);
            
            // Add CSS for animations
            if (!document.querySelector('#notificationStyles')) {
                const style = document.createElement('style');
                style.id = 'notificationStyles';
                style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Password form validation
            const passwordForm = document.getElementById('passwordForm');
            if(passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = this.querySelector('input[name="new_password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                    
                    if(newPassword.length < 6) {
                        e.preventDefault();
                        showNotification('Password must be at least 6 characters long', 'error');
                        return false;
                    }
                    
                    if(newPassword !== confirmPassword) {
                        e.preventDefault();
                        showNotification('Passwords do not match', 'error');
                        return false;
                    }
                });
            }
            
            // Profile form validation
            const profileForm = document.getElementById('profileForm');
            if(profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const fullName = this.querySelector('input[name="full_name"]').value;
                    
                    if(!fullName.trim()) {
                        e.preventDefault();
                        showNotification('Full name is required', 'error');
                        return false;
                    }
                });
            }
            
            // Handle image errors
            document.querySelectorAll('.wishlist-image img').forEach(img => {
                img.addEventListener('error', function() {
                    const fallbackUrl = 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=250&h=180&fit=crop&q=80';
                    if(this.src !== fallbackUrl) {
                        this.src = fallbackUrl;
                    }
                });
            });
        });

        // Session timeout warning
        let lastActivity = <?php echo $_SESSION['last_activity'] ?? time(); ?>;
        
        function checkSession() {
            const now = Math.floor(Date.now() / 1000);
            const timeSince = now - lastActivity;
            const timeLeft = 86400 - timeSince;
            
            if(timeLeft < 300) { // 5 minutes left
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
                background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
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
            
            document.body.appendChild(warning);
        }
        
        function extendSession() {
            fetch('ajax_handler.php?action=keep_alive')
                .then(() => {
                    lastActivity = Math.floor(Date.now() / 1000);
                    const warning = document.querySelector('[style*="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #e74c3c"]');
                    if(warning) warning.remove();
                    
                    showNotification('Session extended!', 'success');
                })
                .catch(() => {
                    showNotification('Failed to extend session', 'error');
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
    </script>
</body>
</html>