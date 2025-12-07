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

// Check login status
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$userName = $isLoggedIn ? $_SESSION['username'] : '';
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Auto-login functionality
if($isLoggedIn && isset($_SESSION['last_activity'])) {
    $timeout = 86400;
    $timeSinceLastActivity = time() - $_SESSION['last_activity'];
    
    if($timeSinceLastActivity > $timeout) {
        session_unset();
        session_destroy();
        $isLoggedIn = false;
        $userName = '';
        $userId = null;
        $isAdmin = false;
    } else {
        $_SESSION['last_activity'] = time();
    }
}

// Handle login persistence
if($isLoggedIn) {
    $_SESSION['last_activity'] = time();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id AND status = 'active'");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($userData) {
        $_SESSION['username'] = $userData['username'];
        $_SESSION['role'] = $userData['role'];
        $_SESSION['email'] = $userData['email'];
    } else {
        session_unset();
        session_destroy();
        $isLoggedIn = false;
        $userName = '';
        $userId = null;
        $isAdmin = false;
    }
}

// Function to optimize Cloudinary image URL
function optimizeCloudinaryImage($imageUrl, $width = 400, $height = 300) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Suking - Premium Mobile Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* Modern CSS Reset & Base Styles */
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
        --dark: #1f2937;
        --light: #f8fafc;
        --gray: #6b7280;
        --shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.15);
        --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --gradient-dark: linear-gradient(135deg, #1e3a8a 0%, #7c3aed 100%);
        --radius: 12px;
        --radius-sm: 8px;
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--dark);
        line-height: 1.6;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* Header Styles */
    .header {
        background: var(--gradient-dark);
        backdrop-filter: blur(10px);
        color: white;
        padding: 1rem 2rem;
        box-shadow: var(--shadow);
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 3px solid var(--accent);
        animation: slideDown 0.5s ease;
    }

    @keyframes slideDown {
        from { transform: translateY(-100%); }
        to { transform: translateY(0); }
    }

    .navbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 1rem;
    }

.logo {
    font-size: 2rem;
    font-weight: 800;
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    transition: var(--transition);
}

.logo:hover {
    transform: translateY(-2px);
    text-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

    .nav-links {
        display: flex;
        gap: 1rem;
        list-style: none;
    }

    .nav-links a {
        color: white;
        text-decoration: none;
        font-weight: 600;
        padding: 12px 24px;
        border-radius: var(--radius-sm);
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
        overflow: hidden;
    }

    .nav-links a::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 0;
        height: 3px;
        background: var(--accent);
        transition: width 0.3s ease;
    }

    .nav-links a:hover::before {
        width: 100%;
    }

    .nav-links a:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateY(-3px);
    }

    .nav-links a.active {
        background: rgba(255, 255, 255, 0.2);
        color: var(--accent);
    }

    .nav-links a.active::before {
        width: 100%;
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
        padding: 10px 20px;
        border-radius: var(--radius-sm);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    /* Button Styles */
    .btn {
        padding: 12px 28px;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 700;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        font-size: 0.95rem;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }

    .btn::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        transform: translate(-50%, -50%);
        transition: width 0.6s, height 0.6s;
    }

    .btn:hover::after {
        width: 300px;
        height: 300px;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--accent) 0%, #fbbf24 100%);
        color: var(--dark);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-3px) scale(1.05);
        box-shadow: 0 8px 25px rgba(245, 158, 11, 0.4);
    }

    .btn-secondary {
        background: transparent;
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(5px);
    }

    .btn-secondary:hover {
        background: white;
        color: var(--primary);
        border-color: white;
        transform: translateY(-3px);
    }

    .btn-cart {
        background: var(--success);
        color: white;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .btn-cart:hover {
        background: #0da271;
        transform: translateY(-3px) scale(1.05);
    }

    /* Hero Section */
    .hero {
        padding: 6rem 2rem;
        background: var(--gradient-primary);
        background-size: 400% 400%;
        animation: gradientShift 15s ease infinite;
        color: white;
        text-align: center;
        margin-bottom: 4rem;
        position: relative;
        overflow: hidden;
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50% }
        50% { background-position: 100% 50% }
        100% { background-position: 0% 50% }
    }

    .hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23ffffff' fill-opacity='0.05' fill-rule='evenodd'/%3E%3C/svg%3E");
        animation: float 20s linear infinite;
    }

    @keyframes float {
        0% { transform: translateY(0) rotate(0deg); }
        100% { transform: translateY(-100px) rotate(360deg); }
    }

    .hero-content {
        max-width: 800px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }

    .hero h1 {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        font-weight: 900;
        text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        animation: fadeInUp 1s ease;
        background: linear-gradient(135deg, #fff 0%, #f3f4f6 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .hero p {
        font-size: 1.25rem;
        margin-bottom: 2.5rem;
        opacity: 0.9;
        animation: fadeInUp 1s ease 0.2s both;
    }

    /* Brands Section */
    .brands {
        padding: 4rem 2rem;
        text-align: center;
        margin-bottom: 4rem;
        position: relative;
    }

    .brands h2 {
        color: var(--dark);
        margin-bottom: 3rem;
        font-size: 3rem;
        font-weight: 900;
        position: relative;
        display: inline-block;
    }

    .brands h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 5px;
        background: var(--accent);
        border-radius: 2px;
    }

    .brand-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 2.5rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .brand-card {
        background: white;
        padding: 2.5rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        transition: var(--transition);
        border: 1px solid rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .brand-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: var(--gradient-primary);
    }

    .brand-card:hover {
        transform: translateY(-15px) scale(1.02);
        box-shadow: var(--shadow-lg);
    }

    .brand-card i {
        font-size: 4rem;
        margin-bottom: 1.5rem;
        display: block;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    /* Featured Products */
    .featured-products {
        padding: 4rem 2rem;
        max-width: 1400px;
        margin: 0 auto 5rem;
    }

    .featured-products h2 {
        text-align: center;
        margin-bottom: 3rem;
        color: var(--dark);
        font-size: 3rem;
        font-weight: 900;
        position: relative;
    }

    .featured-products h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 5px;
        background: var(--accent);
        border-radius: 2px;
    }

    .product-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2.5rem;
    }

    .product-card {
        background: white;
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
        position: relative;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .product-card:hover {
        transform: translateY(-10px) scale(1.02);
        box-shadow: var(--shadow-lg);
    }

    .product-image {
        height: 250px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
        position: relative;
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .product-card:hover .product-image img {
        transform: scale(1.15);
    }

    /* Discount Badge */
    .discount-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        background: linear-gradient(135deg, var(--danger) 0%, #f87171 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 800;
        z-index: 1;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    /* Footer */
    .footer {
        background: var(--dark);
        color: white;
        padding: 5rem 2rem 2rem;
        margin-top: 5rem;
        position: relative;
        overflow: hidden;
    }

    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, var(--primary), var(--accent), var(--secondary));
    }

    .footer-content {
        max-width: 1400px;
        margin: 0 auto;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
    }

    .footer h3 {
        color: white;
        margin-bottom: 1.5rem;
        font-size: 1.5rem;
        font-weight: 700;
        position: relative;
        padding-bottom: 10px;
    }

    .footer h3::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background: var(--accent);
    }

    .footer a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 12px;
        transition: var(--transition);
    }

    .footer a:hover {
        color: var(--accent);
        transform: translateX(5px);
    }

    /* Session Status */
    .session-status {
        background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
        color: white;
        padding: 16px 24px;
        text-align: center;
        font-size: 1rem;
        font-weight: 600;
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(100%);
        z-index: 1000;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 15px;
        min-width: 300px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .session-status.show {
        display: flex;
        animation: slideInUp 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55) forwards;
    }

    @keyframes slideInUp {
        to { transform: translateX(-50%) translateY(0); }
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .navbar {
            flex-direction: column;
            gap: 1.5rem;
            padding: 1rem;
        }
        
        .hero h1 {
            font-size: 3rem;
        }
        
        .brands h2,
        .featured-products h2 {
            font-size: 2.5rem;
        }
    }

    @media (max-width: 768px) {
        .header {
            padding: 1rem;
        }
        
        .nav-links {
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .nav-links a {
            padding: 10px 16px;
            font-size: 0.9rem;
        }
        
        .user-actions {
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.8rem;
        }
        
        .hero {
            padding: 4rem 1rem;
        }
        
        .hero h1 {
            font-size: 2.5rem;
        }
        
        .brand-grid,
        .product-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
        }
    }

    @media (max-width: 480px) {
        .hero h1 {
            font-size: 2rem;
        }
        
        .brands h2,
        .featured-products h2 {
            font-size: 2rem;
        }
        
        .product-grid {
            grid-template-columns: 1fr;
        }
        
        .footer-content {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        
        .session-status {
            min-width: 90%;
            left: 5%;
            transform: translateX(0) translateY(100%);
        }
        
        .session-status.show {
            transform: translateX(0) translateY(0);
        }
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
        width: 12px;
    }

    ::-webkit-scrollbar-track {
        background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, var(--primary) 0%, var(--secondary) 100%);
        border-radius: 6px;
        border: 3px solid #f1f5f9;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, var(--primary-dark) 0%, #6d28d9 100%);
    }

    /* Loading Animation */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }

    .loading-shimmer {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite;
    }
</style>
</head>
<body>
    <!-- Session Status Display -->
    <div id="sessionStatus" class="session-status">
        <span id="statusMessage"></span>
        <button onclick="hideStatus()" style="background: none; border: none; color: white; margin-left: 10px; cursor: pointer;">×</button>
    </div>

    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-mobile-alt"></i>
                Mobile Suking
            </a>
            
            <ul class="nav-links">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Products</a></li>
                <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                <li><a href="contact.php"><i class="fas fa-headset"></i> Contact</a></li>
            </ul>
            
            <div class="user-actions">
                <?php if($isLoggedIn): ?>
                    <span class="user-welcome"><i class="fas fa-user-check"></i> <?php echo htmlspecialchars($userName); ?></span>
                    <a href="user-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-user-circle"></i> Dashboard
                    </a>
                    <?php if($isAdmin): ?>
                        <a href="admin-dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-secondary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="login-register.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="login-register.php?register=true" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
                <a href="cart.php" class="btn btn-cart">
                    <i class="fas fa-shopping-cart"></i> 
                    <span id="cartCount">0</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Premium Mobile Phones Store</h1>
            <p>Discover the latest smartphones from Samsung, iPhone, and Huawei at unbeatable prices</p>
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag"></i> Shop Now
            </a>
            <?php if(!$isLoggedIn): ?>
                <a href="login-register.php" class="btn btn-secondary" style="margin-left: 10px;">
                    <i class="fas fa-user"></i> Join Now
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Brands Section -->
    <section class="brands">
        <h2 style="color: #667eea; margin-bottom: 2rem; font-size: 2.5rem;">Our Premium Brands</h2>
        <div class="brand-grid">
            <div class="brand-card">
                <i class="fab fa-android" style="font-size: 3rem; color: #1428a0; margin-bottom: 1rem;"></i>
                <h3>Samsung</h3>
                <p>Latest Galaxy series with innovative features and cutting-edge technology</p>
                <a href="products.php?brand=samsung" class="btn btn-primary" style="margin-top: 15px;">
                    View Samsung Products
                </a>
            </div>
            
            <div class="brand-card">
                <i class="fab fa-apple" style="font-size: 3rem; color: #000000; margin-bottom: 1rem;"></i>
                <h3>iPhone</h3>
                <p>Apple's premium smartphones with iOS ecosystem and superior performance</p>
                <a href="products.php?brand=iphone" class="btn btn-primary" style="margin-top: 15px;">
                    View iPhone Products
                </a>
            </div>
            
            <div class="brand-card">
                <i class="fas fa-camera" style="font-size: 3rem; color: #ff0000; margin-bottom: 1rem;"></i>
                <h3>Huawei</h3>
                <p>Advanced photography and innovative designs with powerful hardware</p>
                <a href="products.php?brand=huawei" class="btn btn-primary" style="margin-top: 15px;">
                    View Huawei Products
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="featured-products">
        <h2 style="text-align: center; margin-bottom: 2rem; color: #667eea; font-size: 2.5rem;">Featured Products</h2>
        <div class="product-grid">
            <?php
            // Fetch featured products from database
            $stmt = $conn->prepare("
                SELECT * FROM products 
                WHERE featured = 1 AND stock > 0 
                ORDER BY RAND() 
                LIMIT 8
            ");
            $stmt->execute();
            $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if(count($featuredProducts) > 0):
                foreach($featuredProducts as $product):
                    // Get optimized Cloudinary image URL
                    $originalImageUrl = $product['image_url'];
                    $optimizedImageUrl = optimizeCloudinaryImage($originalImageUrl, 400, 300);
                    
                    // Calculate discount percentage if original price exists
                    $discountPercent = 0;
                    if($product['original_price'] && $product['original_price'] > $product['price']) {
                        $discountPercent = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);
                    }
            ?>
            <div class="product-card">
                <div class="product-image image-container">
                    <?php if($discountPercent > 0): ?>
                        <span class="discount-badge">-<?php echo $discountPercent; ?>%</span>
                    <?php endif; ?>
                    
                    <!-- OPTIMIZED CLOUDINARY IMAGE -->
                    <img 
                        src="<?php echo htmlspecialchars($optimizedImageUrl); ?>" 
                        alt="<?php echo htmlspecialchars($product['name']); ?> - <?php echo ucfirst($product['brand']); ?> mobile phone"
                        loading="lazy"
                        width="400"
                        height="300"
                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=400&h=300&fit=crop&q=80'"
                    >
                </div>
                
                <div style="padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem; min-height: 2.8rem;">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="color: #667eea; font-weight: 600; font-size: 0.9rem;">
                            <i class="fas fa-tag"></i> <?php echo ucfirst($product['brand']); ?>
                        </span>
                        <?php if($product['storage']): ?>
                            <span style="background: #f1f8ff; color: #0366d6; padding: 3px 8px; border-radius: 3px; font-size: 0.8rem;">
                                <?php echo htmlspecialchars($product['storage']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Price Display -->
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 1.4rem; color: #2ecc71; font-weight: 700;">
                            ETB <?php echo number_format($product['price'], 2); ?>
                        </div>
                        <?php if($discountPercent > 0): ?>
                            <div style="font-size: 0.9rem; color: #999; text-decoration: line-through;">
                                Was: ETB <?php echo number_format($product['original_price'], 2); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stock Status -->
                    <div style="font-size: 0.9rem; margin-bottom: 1rem; color: <?php echo ($product['stock'] > 5) ? '#27ae60' : '#e67e22'; ?>;">
                        <i class="fas fa-box"></i> 
                        <?php 
                            if($product['stock'] > 10) {
                                echo "In Stock";
                            } elseif($product['stock'] > 5) {
                                echo "Available";
                            } elseif($product['stock'] > 0) {
                                echo "Only {$product['stock']} left";
                            }
                        ?>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 10px;">
                        <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                                class="btn btn-primary" 
                                style="flex: 2;"
                                <?php echo ($product['stock'] <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                            <i class="fas fa-cart-plus"></i> 
                            <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                        </button>
                        
                        <a href="product-detail.php?id=<?php echo $product['id']; ?>" 
                           class="btn btn-secondary" 
                           style="flex: 1; text-align: center; text-decoration: none;">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php 
                endforeach; 
            else: 
                // If no featured products, show latest products
                $stmt = $conn->prepare("SELECT * FROM products WHERE stock > 0 ORDER BY created_at DESC LIMIT 8");
                $stmt->execute();
                $latestProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach($latestProducts as $product):
                    $originalImageUrl = $product['image_url'];
                    $optimizedImageUrl = optimizeCloudinaryImage($originalImageUrl, 400, 300);
            ?>
            <div class="product-card">
                <div class="product-image">
                    <img 
                        src="<?php echo htmlspecialchars($optimizedImageUrl); ?>" 
                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                        loading="lazy"
                        width="400"
                        height="300"
                        onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=400&h=300&fit=crop&q=80'"
                    >
                </div>
                <div style="padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>
                    <span style="color: #667eea; font-weight: 600; display: block; margin-bottom: 0.5rem;">
                        <?php echo ucfirst($product['brand']); ?>
                    </span>
                    <div style="font-size: 1.3rem; color: #2ecc71; font-weight: 700; margin-bottom: 1rem;">
                        ETB <?php echo number_format($product['price'], 2); ?>
                    </div>
                    <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                            class="btn btn-primary" 
                            style="width: 100%;">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
            <?php 
                endforeach; 
            endif; 
            ?>
        </div>
        
        <div style="text-align: center; margin-top: 3rem;">
            <a href="products.php" class="btn btn-primary" style="padding: 12px 30px; font-size: 1.1rem;">
                <i class="fas fa-eye"></i> View All Products
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div>
                <h3>Mobile Suking</h3>
                <p>Your trusted mobile phone store with genuine products and excellent service.</p>
            </div>
            <div>
                <h3>Quick Links</h3>
                <a href="index.php" style="color: white; display: block; margin-bottom: 5px; text-decoration: none;">
                    <i class="fas fa-home"></i> Home
                </a>
                <a href="products.php" style="color: white; display: block; margin-bottom: 5px; text-decoration: none;">
                    <i class="fas fa-shopping-bag"></i> Products
                </a>
                <a href="cart.php" style="color: white; display: block; margin-bottom: 5px; text-decoration: none;">
                    <i class="fas fa-shopping-cart"></i> Cart
                </a>
            </div>
            <div>
                <h3>Contact Info</h3>
                <p><i class="fas fa-phone"></i> +251 911 223 344</p>
                <p><i class="fas fa-envelope"></i> info@mobilesuking.com</p>
                <p><i class="fas fa-map-marker-alt"></i> Addis Ababa, Ethiopia</p>
            </div>
            <div>
                <h3>Payment Methods</h3>
                <p><i class="fas fa-money-bill-wave"></i> Cash</p>
                <p><i class="fas fa-mobile-alt"></i> Telebirr</p>
                <p><i class="fas fa-credit-card"></i> Credit/Debit Cards</p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
            <p>&copy; <?php echo date('Y'); ?> Mobile Suking. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Session Management
        function showStatus(message, type = 'success') {
            const statusDiv = document.getElementById('sessionStatus');
            const messageSpan = document.getElementById('statusMessage');
            
            statusDiv.style.backgroundColor = type === 'success' ? '#2ecc71' : 
                                            type === 'error' ? '#e74c3c' : '#3498db';
            messageSpan.textContent = message;
            statusDiv.classList.add('show');
            
            setTimeout(() => {
                statusDiv.classList.remove('show');
            }, 3000);
        }
        
        function hideStatus() {
            document.getElementById('sessionStatus').classList.remove('show');
        }
        
        // Add to Cart Function
        function addToCart(productId) {
            <?php if(!$isLoggedIn): ?>
                showStatus('Please login to add items to cart!', 'error');
                setTimeout(() => {
                    window.location.href = 'login-register.php?redirect=' + encodeURIComponent(window.location.href);
                }, 1500);
            <?php else: ?>
                fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=add_to_cart&product_id=' + productId + '&user_id=<?php echo $userId; ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        showStatus('✓ Product added to cart!', 'success');
                        updateCartCount(data.cart_count || 0);
                    } else {
                        showStatus('✗ ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showStatus('✗ Failed to add to cart', 'error');
                });
            <?php endif; ?>
        }
        
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
        
        // Initialize cart count
        <?php if($isLoggedIn): ?>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('ajax_handler.php?action=get_cart_count&user_id=<?php echo $userId; ?>')
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        updateCartCount(data.count);
                    }
                });
        });
        <?php endif; ?>
        
        // Image preloading for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.product-image img');
            images.forEach(img => {
                // Create a preload image
                const preload = new Image();
                preload.src = img.src;
                
                // Handle image errors
                img.addEventListener('error', function() {
                    const brand = this.alt.toLowerCase();
                    let fallbackUrl = 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=400&h=300&fit=crop';
                    
                    if(brand.includes('samsung')) {
                        fallbackUrl = 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=400&h=300&fit=crop';
                    } else if(brand.includes('iphone')) {
                        fallbackUrl = 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=400&h=300&fit=crop';
                    } else if(brand.includes('huawei')) {
                        fallbackUrl = 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=400&h=300&fit=crop';
                    }
                    
                    this.src = fallbackUrl;
                });
            });
        });
    </script>
</body>
</html>