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

// Handle session timeout
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

// Get filter parameters
$brandFilter = $_GET['brand'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // Products per page
$offset = ($page - 1) * $limit;

// Build query for products
$sql = "SELECT * FROM products WHERE stock > 0";
$countSql = "SELECT COUNT(*) as total FROM products WHERE stock > 0";
$params = [];
$countParams = [];

if($brandFilter !== 'all') {
    $sql .= " AND brand = :brand";
    $countSql .= " AND brand = :brand";
    $params[':brand'] = $brandFilter;
    $countParams[':brand'] = $brandFilter;
}

if(!empty($searchQuery)) {
    $sql .= " AND (name LIKE :search OR description LIKE :search OR model LIKE :search)";
    $countSql .= " AND (name LIKE :search OR description LIKE :search OR model LIKE :search)";
    $params[':search'] = "%$searchQuery%";
    $countParams[':search'] = "%$searchQuery%";
}

if(!empty($minPrice) && is_numeric($minPrice)) {
    $sql .= " AND price >= :min_price";
    $countSql .= " AND price >= :min_price";
    $params[':min_price'] = $minPrice;
    $countParams[':min_price'] = $minPrice;
}

if(!empty($maxPrice) && is_numeric($maxPrice)) {
    $sql .= " AND price <= :max_price";
    $countSql .= " AND price <= :max_price";
    $params[':max_price'] = $maxPrice;
    $countParams[':max_price'] = $maxPrice;
}

// Add sorting
switch($sortBy) {
    case 'price_low':
        $sql .= " ORDER BY price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY price DESC";
        break;
    case 'name':
        $sql .= " ORDER BY name ASC";
        break;
    case 'featured':
        $sql .= " ORDER BY featured DESC, created_at DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY created_at DESC";
        break;
}

// Add pagination
$sql .= " LIMIT :limit OFFSET :offset";
$params[':limit'] = $limit;
$params[':offset'] = $offset;

// Get total count for pagination
$countStmt = $conn->prepare($countSql);
foreach($countParams as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
$totalProducts = $totalResult['total'];
$totalPages = ceil($totalProducts / $limit);

// Execute main query
$stmt = $conn->prepare($sql);
foreach($params as $key => $value) {
    if($key === ':limit' || $key === ':offset') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Mobile Suking</title>
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
        --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --gradient-dark: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        --gradient-accent: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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

    /* Button Styles */
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

    /* Products Page Container */
    .products-container {
        max-width: 1400px;
        margin: 3rem auto;
        padding: 0 2rem;
    }

    /* Page Header */
    .page-header {
        text-align: center;
        margin-bottom: 3rem;
        position: relative;
    }

    .page-header h1 {
        font-size: 3.5rem;
        font-weight: 900;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 1rem;
        position: relative;
        display: inline-block;
    }

    .page-header h1::after {
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

    .page-header p {
        font-size: 1.2rem;
        color: var(--gray);
        max-width: 600px;
        margin: 1rem auto 0;
    }

    /* Product Count */
    .product-count {
        text-align: center;
        font-size: 1.1rem;
        color: var(--gray);
        background: white;
        padding: 1rem 2rem;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 2rem;
        display: inline-block;
        border-left: 4px solid var(--accent);
    }

    /* Filters Container */
    .filters-container {
        background: white;
        padding: 2rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        margin-bottom: 3rem;
        border: 1px solid var(--gray-light);
        animation: slideUp 0.5s ease;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-label {
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-select, .filter-input {
        padding: 14px 16px;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        font-size: 1rem;
        transition: var(--transition);
        background: white;
    }

    .filter-select:focus, .filter-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .price-range {
        display: flex;
        gap: 0.75rem;
    }

    .price-range .filter-input {
        flex: 1;
        min-width: 0;
    }

    .search-box {
        display: flex;
        gap: 0.75rem;
    }

    .search-box .filter-input {
        flex: 1;
    }

    /* Products Grid */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2.5rem;
        margin-bottom: 4rem;
        animation: fadeIn 0.6s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .product-card {
        background: white;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
        position: relative;
        border: 1px solid var(--gray-light);
    }

    .product-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary);
    }

    .product-image {
        height: 250px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        position: relative;
    }

    .product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .product-card:hover .product-image img {
        transform: scale(1.1);
    }

    /* Badges */
    .product-badge {
        position: absolute;
        top: 15px;
        left: 15px;
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 800;
        z-index: 2;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .badge-discount {
        background: var(--gradient-accent);
    }

    .badge-featured {
        background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
    }

    .badge-new {
        background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    }

    /* Product Info */
    .product-info {
        padding: 1.5rem;
    }

    .product-title {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        color: var(--dark);
        line-height: 1.4;
        min-height: 3.5rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .product-brand {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--primary);
        font-weight: 600;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }

    .product-specs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .spec-item {
        background: #f8fafc;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.85rem;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .spec-item i {
        color: var(--primary);
    }

    /* Price */
    .product-price {
        margin-bottom: 1rem;
    }

    .current-price {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--success);
        display: block;
    }

    .original-price {
        font-size: 0.95rem;
        color: var(--gray);
        text-decoration: line-through;
        margin-top: 0.25rem;
        display: block;
    }

    /* Stock Status */
    .stock-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        padding: 0.5rem 0;
    }

    .stock-status.in-stock {
        color: var(--success);
    }

    .stock-status.low-stock {
        color: var(--warning);
    }

    .stock-status.out-stock {
        color: var(--danger);
    }

    /* Actions */
    .product-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
    }

    .btn-view, .btn-add-cart {
        padding: 12px;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        font-size: 0.95rem;
    }

    .btn-view {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
    }

    .btn-view:hover {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        transform: translateY(-2px);
    }

    .btn-add-cart {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
    }

    .btn-add-cart:hover:not(:disabled) {
        background: linear-gradient(135deg, #059669 0%, #047857 100%);
        transform: translateY(-2px);
    }

    .btn-add-cart:disabled {
        background: var(--gray-light);
        color: var(--gray);
        cursor: not-allowed;
        opacity: 0.7;
    }

    /* Pagination */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 0.5rem;
        margin: 4rem 0;
        flex-wrap: wrap;
    }

    .pagination a, .pagination span {
        padding: 12px 18px;
        border: 2px solid var(--gray-light);
        border-radius: var(--radius-sm);
        text-decoration: none;
        color: var(--dark);
        font-weight: 600;
        transition: var(--transition);
        min-width: 45px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .pagination a:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .pagination .active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    .pagination .disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* No Results */
    .no-results {
        text-align: center;
        padding: 4rem 2rem;
        grid-column: 1/-1;
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow);
    }

    .no-results i {
        font-size: 4rem;
        color: var(--gray-light);
        margin-bottom: 1.5rem;
    }

    .no-results h3 {
        font-size: 1.8rem;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .no-results p {
        color: var(--gray);
        margin-bottom: 2rem;
        max-width: 400px;
        margin-left: auto;
        margin-right: auto;
    }

    /* Footer */
    footer {
        background: var(--gradient-dark);
        color: white;
        padding: 3rem 2rem;
        margin-top: 5rem;
    }

    footer > div {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }

    footer p {
        margin: 0.5rem 0;
    }

    footer .contact-info {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }

    footer .contact-info span {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: var(--gray-light);
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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

        .page-header h1 {
            font-size: 2.8rem;
        }
    }

    @media (max-width: 768px) {
        .products-container {
            padding: 0 1rem;
        }

        .page-header h1 {
            font-size: 2.4rem;
        }

        .filter-grid {
            grid-template-columns: 1fr;
        }

        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 2rem;
        }

        .product-image {
            height: 220px;
        }

        .product-actions {
            grid-template-columns: 1fr;
        }

        .btn-view, .btn-add-cart {
            width: 100%;
        }
    }

    @media (max-width: 576px) {
        .page-header h1 {
            font-size: 2rem;
        }

        .products-grid {
            grid-template-columns: 1fr;
        }

        .pagination a, .pagination span {
            padding: 10px 14px;
            min-width: 40px;
        }

        .product-specs {
            flex-direction: column;
            gap: 0.5rem;
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
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }

    .loading-shimmer {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite;
    }

    /* Custom Checkbox for Filters (if added later) */
    .filter-checkbox {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        padding: 0.5rem 0;
    }

    .filter-checkbox input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: var(--primary);
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
                <li><a href="products.php" style="background-color: rgba(255,255,255,0.3);"><i class="fas fa-shopping-bag"></i> Products</a></li>
                <li><a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                <li><a href="contact.php"><i class="fas fa-headset"></i> Contact</a></li>
            </ul>
            
            <div class="user-actions">
                <?php if($isLoggedIn): ?>
                    <span style="color: #ffcc00; font-weight: 600;">
                        <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($userName); ?>
                    </span>
                    <a href="user-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-user-circle"></i> Dashboard
                    </a>
                    <?php if($isAdmin): ?>
                        <a href="admin-dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-cog"></i> Admin
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
                <a href="cart.php" class="btn" style="background-color: #2ecc71; color: white;">
                    <i class="fas fa-shopping-cart"></i> 
                    <span id="cartCount">0</span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Products Container -->
    <div class="products-container">
        <div class="page-header">
            <h1>Our Products</h1>
            <p>Browse our collection of premium mobile phones</p>
        </div>

        <!-- Product Count -->
        <div class="product-count">
            Showing <?php echo count($products); ?> of <?php echo $totalProducts; ?> products
            <?php if($brandFilter !== 'all'): ?>
                in <?php echo ucfirst($brandFilter); ?>
            <?php endif; ?>
        </div>

        <!-- Filters -->
        <form method="GET" action="products.php" class="filters-container">
            <div class="filter-grid">
                <div class="filter-group">
                    <label class="filter-label">Brand</label>
                    <select name="brand" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $brandFilter === 'all' ? 'selected' : ''; ?>>All Brands</option>
                        <option value="samsung" <?php echo $brandFilter === 'samsung' ? 'selected' : ''; ?>>Samsung</option>
                        <option value="iphone" <?php echo $brandFilter === 'iphone' ? 'selected' : ''; ?>>iPhone</option>
                        <option value="huawei" <?php echo $brandFilter === 'huawei' ? 'selected' : ''; ?>>Huawei</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="featured" <?php echo $sortBy === 'featured' ? 'selected' : ''; ?>>Featured</option>
                        <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Price Range (ETB)</label>
                    <div class="price-range">
                        <input type="number" name="min_price" placeholder="Min" class="filter-input" 
                               value="<?php echo htmlspecialchars($minPrice); ?>" min="0" step="1000">
                        <input type="number" name="max_price" placeholder="Max" class="filter-input"
                               value="<?php echo htmlspecialchars($maxPrice); ?>" min="0" step="1000">
                    </div>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Search Products</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" name="search" placeholder="Search by name or model..." 
                               class="filter-input" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit" class="btn" style="background-color: #667eea; color: white; padding: 10px 15px;">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if($brandFilter !== 'all' || !empty($searchQuery) || !empty($minPrice) || !empty($maxPrice)): ?>
                            <a href="products.php" class="btn" style="background-color: #95a5a6; color: white; padding: 10px 15px;">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>

        <!-- Products Grid -->
        <?php if(count($products) > 0): ?>
            <div class="products-grid">
                <?php foreach($products as $product): 
                    // Get optimized Cloudinary image URL
                    $originalImageUrl = $product['image_url'];
                    $optimizedImageUrl = optimizeCloudinaryImage($originalImageUrl, 400, 300);
                    $fallbackImage = getFallbackImage($product['brand']);
                    
                    // Calculate discount if original price exists
                    $hasDiscount = $product['original_price'] && $product['original_price'] > $product['price'];
                    $discountPercent = $hasDiscount ? round((($product['original_price'] - $product['price']) / $product['original_price']) * 100) : 0;
                ?>
                <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                    <div class="product-image">
                        <?php if($hasDiscount): ?>
                            <span class="product-badge">-<?php echo $discountPercent; ?>% OFF</span>
                        <?php elseif($product['featured']): ?>
                            <span class="product-badge" style="background-color: #ff9f43;">FEATURED</span>
                        <?php endif; ?>
                        
                        <!-- OPTIMIZED CLOUDINARY IMAGE -->
                        <img 
                            src="<?php echo htmlspecialchars($optimizedImageUrl); ?>" 
                            alt="<?php echo htmlspecialchars($product['name']); ?> - <?php echo ucfirst($product['brand']); ?> mobile phone"
                            loading="lazy"
                            width="400"
                            height="300"
                            onerror="this.onerror=null; this.src='<?php echo $fallbackImage; ?>'"
                            data-fallback="<?php echo $fallbackImage; ?>"
                        >
                    </div>
                    
                    <div class="product-info">
                        <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <span class="product-brand">
                            <i class="fas fa-tag"></i> <?php echo ucfirst($product['brand']); ?>
                        </span>
                        
                        <div class="product-specs">
                            <?php if($product['storage']): ?>
                                <span title="Storage"><i class="fas fa-hdd"></i> <?php echo htmlspecialchars($product['storage']); ?></span>
                            <?php endif; ?>
                            
                            <?php if($product['color']): ?>
                                <span title="Color"><i class="fas fa-palette"></i> <?php echo htmlspecialchars($product['color']); ?></span>
                            <?php endif; ?>
                            
                            <?php if($product['screen_size']): ?>
                                <span title="Screen Size"><i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($product['screen_size']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="product-price">
                            ETB <?php echo number_format($product['price'], 2); ?>
                            <?php if($hasDiscount): ?>
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
                                } elseif($product['stock'] > 0) {
                                    echo "Only {$product['stock']} left";
                                } else {
                                    echo "Out of Stock";
                                }
                            ?>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn-view" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)" 
                                <?php echo ($product['stock'] <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                                <i class="fas fa-cart-plus"></i> 
                                <?php echo ($product['stock'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
                <div class="pagination">
                    <?php if($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for($i = $startPage; $i <= $endPage; $i++):
                        if($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif;
                    endfor; ?>
                    
                    <?php if($page < $totalPages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h3>No products found</h3>
                <p>Try adjusting your search or filter criteria</p>
                <a href="products.php" class="btn btn-primary" style="margin-top: 1rem; padding: 12px 24px;">
                    <i class="fas fa-undo"></i> Clear All Filters
                </a>
            </div>
        <?php endif; ?>
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
        // Add to Cart Function
        function addToCart(productId) {
            <?php if(!$isLoggedIn): ?>
                alert('Please login to add items to cart!');
                window.location.href = 'login-register.php?redirect=' + encodeURIComponent(window.location.href);
            <?php else: ?>
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
                        // Update cart count
                        updateCartCount(data.cart_count || 0);
                        
                        // Show success message
                        showNotification('✓ Product added to cart!', 'success');
                        
                        // Add animation to product card
                        const productCard = document.querySelector(`[data-product-id="${productId}"]`);
                        if(productCard) {
                            productCard.style.animation = 'none';
                            setTimeout(() => {
                                productCard.style.animation = 'pulse 0.5s';
                            }, 10);
                        }
                    } else {
                        showNotification('✗ ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('✗ Failed to add to cart', 'error');
                });
            <?php endif; ?>
        }
        
        function viewProduct(productId) {
            window.location.href = 'product-detail.php?id=' + productId;
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
        
        function showNotification(message, type) {
            // Create notification element
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
                ${type === 'success' ? 'background-color: #2ecc71;' : 'background-color: #e74c3c;'}
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.02); }
                    100% { transform: scale(1); }
                }
            `;
            document.head.appendChild(style);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Check cart count on load
        <?php if($isLoggedIn): ?>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('ajax_handler.php?action=get_cart_count')
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        updateCartCount(data.count || 0);
                    }
                });
            
            // Handle image errors
            document.querySelectorAll('.product-image img').forEach(img => {
                img.addEventListener('error', function() {
                    const fallbackUrl = this.getAttribute('data-fallback');
                    if(fallbackUrl) {
                        this.src = fallbackUrl;
                    }
                });
            });
        });
        <?php endif; ?>
        
        // Price range validation
        document.addEventListener('DOMContentLoaded', function() {
            const minPriceInput = document.querySelector('input[name="min_price"]');
            const maxPriceInput = document.querySelector('input[name="max_price"]');
            
            if(minPriceInput && maxPriceInput) {
                minPriceInput.addEventListener('change', function() {
                    if(this.value && maxPriceInput.value && parseInt(this.value) > parseInt(maxPriceInput.value)) {
                        alert('Minimum price cannot be greater than maximum price');
                        this.value = '';
                    }
                });
                
                maxPriceInput.addEventListener('change', function() {
                    if(this.value && minPriceInput.value && parseInt(this.value) < parseInt(minPriceInput.value)) {
                        alert('Maximum price cannot be less than minimum price');
                        this.value = '';
                    }
                });
            }
        });
    </script>
</body>
</html>