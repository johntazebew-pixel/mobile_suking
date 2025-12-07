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
function optimizeCloudinaryImage($imageUrl, $width = 800, $height = 600) {
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
        'samsung' => 'https://images.unsplash.com/photo-1610945265064-0e34e5519bbf?w=800&h=600&fit=crop&q=80',
        'iphone' => 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&h=600&fit=crop&q=80',
        'huawei' => 'https://images.unsplash.com/photo-1598327105666-5b89351aff97?w=800&h=600&fit=crop&q=80'
    ];
    
    return $placeholders[$brand] ?? 'https://images.unsplash.com/photo-1592899677977-9c10ca588bbd?w=800&h=600&fit=crop&q=80';
}

// Get product ID from URL
$productId = $_GET['id'] ?? 0;

// Fetch product details
$product = null;
$relatedProducts = [];
$productImages = [];

try {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($product) {
        // Optimize main product image
        $product['optimized_image'] = optimizeCloudinaryImage($product['image_url'], 800, 600);
        $product['fallback_image'] = getFallbackImage($product['brand']);
        
        // Create additional image variations (for gallery)
        $productImages = [
            'main' => $product['optimized_image'],
            'large' => optimizeCloudinaryImage($product['image_url'], 1200, 900),
            'thumb' => optimizeCloudinaryImage($product['image_url'], 150, 150)
        ];
        
        // Fetch related products (same brand, exclude current product)
        $stmt = $conn->prepare("SELECT * FROM products WHERE brand = :brand AND id != :id AND stock > 0 ORDER BY RAND() LIMIT 4");
        $stmt->bindParam(':brand', $product['brand']);
        $stmt->bindParam(':id', $productId);
        $stmt->execute();
        $relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Optimize related product images
        foreach($relatedProducts as &$related) {
            $related['optimized_image'] = optimizeCloudinaryImage($related['image_url'], 300, 200);
            $related['fallback_image'] = getFallbackImage($related['brand']);
        }
        
        // Calculate discount percentage
        if($product['original_price'] && $product['original_price'] > $product['price']) {
            $product['discount_percent'] = round((($product['original_price'] - $product['price']) / $product['original_price']) * 100);
            $product['discount_amount'] = $product['original_price'] - $product['price'];
        }
    }
} catch(PDOException $e) {
    $error = "Failed to load product details: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Product Not Found'; ?> - Mobile Suking</title>
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

    .user-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
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

    /* Product Detail Container */
    .product-detail-container {
        max-width: 1400px;
        margin: 3rem auto;
        padding: 0 2rem;
    }

    /* Breadcrumb */
    .breadcrumb {
        margin-bottom: 2.5rem;
        padding: 1rem 1.5rem;
        background: white;
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        border-left: 4px solid var(--accent);
    }

    .breadcrumb a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: var(--transition);
    }

    .breadcrumb a:hover {
        color: var(--primary-dark);
        transform: translateX(3px);
    }

    .breadcrumb span {
        color: var(--gray);
    }

    .breadcrumb i {
        color: var(--accent);
    }

    /* Product Main Section */
    .product-main {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 4rem;
        margin-bottom: 4rem;
    }

    /* Product Images Section */
    .product-images {
        position: relative;
    }

    .main-image-container {
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        padding: 3rem;
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
        border: 1px solid var(--gray-light);
        height: 500px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .main-image {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
        transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .main-image-container:hover .main-image {
        transform: scale(1.05);
    }

    /* Product Badges */
    .product-badge {
        position: absolute;
        top: 20px;
        left: 20px;
        z-index: 2;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: 800;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        box-shadow: var(--shadow);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .badge-discount {
        background: var(--gradient-accent);
        color: white;
    }

    .badge-featured {
        background: var(--gradient-primary);
        color: white;
    }

    /* Image Actions */
    .image-actions {
        position: absolute;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 0.5rem;
    }

    .image-action-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.9);
        border: none;
        color: var(--dark);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
        box-shadow: var(--shadow);
    }

    .image-action-btn:hover {
        background: white;
        transform: scale(1.1);
        box-shadow: var(--shadow-lg);
    }

    /* Product Info Section */
    .product-info {
        background: white;
        padding: 2.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--gray-light);
    }

    .product-title {
        font-size: 2.5rem;
        font-weight: 900;
        color: var(--dark);
        margin-bottom: 1rem;
        line-height: 1.2;
    }

    .product-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 1.1rem;
        color: var(--primary);
        font-weight: 600;
        margin-bottom: 1.5rem;
        padding: 0.75rem 1rem;
        background: #f0f7ff;
        border-radius: var(--radius-sm);
        border-left: 4px solid var(--primary);
    }

    .product-meta {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 2px solid var(--gray-light);
        flex-wrap: wrap;
    }

    .product-rating {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .stars {
        display: flex;
        gap: 2px;
        color: var(--accent);
    }

    .rating-count {
        color: var(--gray);
        font-weight: 500;
    }

    .product-code {
        background: #f8fafc;
        padding: 8px 16px;
        border-radius: var(--radius-sm);
        color: var(--gray);
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Price Section */
    .product-price-section {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 2rem;
        border-radius: var(--radius);
        margin-bottom: 2rem;
        border: 2px solid var(--gray-light);
        position: relative;
        overflow: hidden;
    }

    .product-price-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .price-container {
        display: flex;
        align-items: baseline;
        gap: 1.5rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .current-price {
        font-size: 3rem;
        font-weight: 900;
        color: var(--success);
        line-height: 1;
    }

    .original-price {
        font-size: 1.5rem;
        color: var(--gray);
        text-decoration: line-through;
    }

    .discount-badge {
        background: var(--gradient-accent);
        color: white;
        padding: 8px 16px;
        border-radius: 30px;
        font-weight: 800;
        font-size: 0.9rem;
    }

    /* Stock Status */
    .stock-status {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        padding: 0.75rem 1rem;
        border-radius: var(--radius-sm);
        margin-top: 1rem;
    }

    .stock-status.in-stock {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success);
        border-left: 4px solid var(--success);
    }

    .stock-status.low-stock {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning);
        border-left: 4px solid var(--warning);
    }

    .stock-status.out-stock {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border-left: 4px solid var(--danger);
    }

    /* Description */
    .product-description {
        background: #f0f8ff;
        padding: 1.5rem;
        border-radius: var(--radius);
        margin: 2rem 0;
        border-left: 4px solid var(--primary);
    }

    .product-description p {
        color: var(--gray);
        line-height: 1.7;
    }

    /* Product Actions */
    .product-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn-add-cart {
        background: var(--gradient-primary);
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        padding: 18px;
        border-radius: var(--radius);
        grid-column: 1 / -1;
    }

    .btn-add-cart:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(102, 126, 234, 0.3);
    }

    .btn-buy-now {
        background: var(--gradient-success);
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        padding: 18px;
        border-radius: var(--radius);
    }

    .btn-buy-now:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(16, 185, 129, 0.3);
    }

    .btn-wishlist {
        background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
        color: white;
        font-size: 1.1rem;
        font-weight: 700;
        padding: 18px;
        border-radius: var(--radius);
    }

    .btn-wishlist:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 30px rgba(236, 72, 153, 0.3);
    }

    /* Product Specifications */
    .product-specs {
        background: white;
        padding: 2.5rem;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        margin-bottom: 4rem;
        border: 1px solid var(--gray-light);
    }

    .specs-title {
        font-size: 1.8rem;
        font-weight: 900;
        color: var(--dark);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-bottom: 1rem;
        border-bottom: 3px solid var(--accent);
    }

    .specs-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .spec-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 1.5rem;
        background: #f8fafc;
        border-radius: var(--radius-sm);
        transition: var(--transition);
        border-left: 4px solid var(--primary);
    }

    .spec-item:hover {
        background: #f1f5f9;
        transform: translateX(5px);
    }

    .spec-label {
        font-weight: 600;
        color: var(--gray);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .spec-value {
        font-weight: 700;
        color: var(--dark);
        text-align: right;
    }

    .detailed-specs {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        padding: 2rem;
        border-radius: var(--radius);
        border: 2px solid var(--gray-light);
        margin-top: 1rem;
    }

    .detailed-specs .spec-label {
        font-size: 1.2rem;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .detailed-specs .spec-value {
        text-align: left;
        color: var(--gray);
        line-height: 1.8;
        font-weight: 500;
    }

    /* Related Products */
    .related-products {
        margin-bottom: 4rem;
    }

    .section-title {
        font-size: 2.2rem;
        font-weight: 900;
        color: var(--dark);
        margin-bottom: 2.5rem;
        text-align: center;
        position: relative;
        padding-bottom: 1rem;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100px;
        height: 4px;
        background: var(--gradient-accent);
        border-radius: 2px;
    }

    .related-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 2rem;
    }

    .related-card {
        background: white;
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: var(--transition);
        border: 1px solid var(--gray-light);
        position: relative;
    }

    .related-card:hover {
        transform: translateY(-10px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary);
    }

    .related-image {
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        position: relative;
    }

    .related-image img {
        max-width: 80%;
        max-height: 80%;
        object-fit: contain;
        transition: transform 0.5s ease;
    }

    .related-card:hover .related-image img {
        transform: scale(1.1);
    }

    .related-info {
        padding: 1.5rem;
    }

    .related-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 0.75rem;
        min-height: 3rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .related-brand {
        color: var(--primary);
        font-weight: 600;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .related-price {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--success);
        margin-bottom: 1rem;
    }

    .btn-view-details {
        width: 100%;
        padding: 12px;
        background: var(--gradient-primary);
        color: white;
        border: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
    }

    .btn-view-details:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a40a3 100%);
        transform: translateY(-2px);
    }

    /* Product Not Found */
    .product-not-found {
        text-align: center;
        padding: 6rem 2rem;
        background: white;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-xl);
        margin: 2rem 0;
    }

    .product-not-found i {
        font-size: 5rem;
        color: var(--gray-light);
        margin-bottom: 2rem;
    }

    .product-not-found h2 {
        font-size: 2.5rem;
        color: var(--dark);
        margin-bottom: 1rem;
    }

    .product-not-found p {
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
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
        margin-top: 5rem;
        border-top: 3px solid var(--accent);
    }

    footer > div {
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
        .product-main {
            gap: 3rem;
        }
        
        .main-image-container {
            height: 450px;
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

        .product-main {
            grid-template-columns: 1fr;
            gap: 3rem;
        }

        .main-image-container {
            height: 400px;
        }

        .product-title {
            font-size: 2.2rem;
        }
    }

    @media (max-width: 768px) {
        .product-detail-container {
            padding: 0 1rem;
        }

        .product-info {
            padding: 2rem;
        }

        .current-price {
            font-size: 2.5rem;
        }

        .product-actions {
            grid-template-columns: 1fr;
        }

        .specs-grid {
            grid-template-columns: 1fr;
        }

        .related-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }

        .notification {
            min-width: 250px;
            right: 10px;
            left: 10px;
            width: calc(100% - 20px);
        }
    }

    @media (max-width: 576px) {
        .main-image-container {
            height: 350px;
            padding: 2rem;
        }

        .product-title {
            font-size: 1.8rem;
        }

        .current-price {
            font-size: 2rem;
        }

        .price-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .related-grid {
            grid-template-columns: 1fr;
        }

        .section-title {
            font-size: 1.8rem;
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

    /* Image Modal */
    .image-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s, visibility 0.3s;
    }

    .image-modal.active {
        opacity: 1;
        visibility: visible;
    }

    .modal-image {
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }

    .image-modal.active .modal-image {
        transform: scale(1);
    }

    .close-modal {
        position: absolute;
        top: 20px;
        right: 20px;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: none;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 1.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: var(--transition);
    }

    .close-modal:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
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
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
            </ul>
            
            <div class="user-actions">
                <?php if($isLoggedIn): ?>
                    <span style="color: #ffcc00; font-weight: 600;">
                        <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($userName); ?>
                    </span>
                    <a href="user-dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-user-circle"></i> Dashboard
                    </a>
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

    <!-- Product Detail Container -->
    <div class="product-detail-container">
        <?php if($product): ?>
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php">Home</a> › 
                <a href="products.php">Products</a> › 
                <a href="products.php?brand=<?php echo $product['brand']; ?>">
                    <?php echo ucfirst($product['brand']); ?>
                </a> › 
                <span><?php echo htmlspecialchars($product['name']); ?></span>
            </div>

            <!-- Product Main Section -->
            <div class="product-main">
                <!-- Product Images -->
                <div class="product-images">
                    <div class="main-image-container">
                        <?php if(isset($product['discount_percent'])): ?>
                            <div class="product-badge">-<?php echo $product['discount_percent']; ?>% OFF</div>
                        <?php endif; ?>
                        
                        <!-- OPTIMIZED CLOUDINARY IMAGE -->
                        <img 
                            src="<?php echo htmlspecialchars($productImages['main']); ?>" 
                            alt="<?php echo htmlspecialchars($product['name']); ?>"
                            class="main-image"
                            id="mainProductImage"
                            loading="eager"
                            width="800"
                            height="600"
                            onerror="this.onerror=null; this.src='<?php echo $product['fallback_image']; ?>'"
                            data-large="<?php echo htmlspecialchars($productImages['large']); ?>"
                        >
                        <div class="image-zoom" onclick="zoomImage()">
                            <i class="fas fa-search-plus"></i> Zoom
                        </div>
                    </div>
                </div>

                <!-- Product Info -->
                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['name']); ?></h1>
                    
                    <div class="product-meta">
                        <span class="product-brand">
                            <i class="fas fa-tag"></i> <?php echo ucfirst($product['brand']); ?>
                        </span>
                        
                        <div class="product-rating">
                            <div class="stars">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                            <span class="rating-count">4.5/5.0 • 128 Reviews</span>
                        </div>
                        
                        <span class="product-code">
                            <i class="fas fa-barcode"></i> SKU: MS<?php echo str_pad($product['id'], 4, '0', STR_PAD_LEFT); ?>
                        </span>
                    </div>

                    <div class="product-price-section">
                        <div class="price-container">
                            <div class="current-price">ETB <?php echo number_format($product['price'], 2); ?></div>
                            <?php if(isset($product['original_price'])): ?>
                                <div class="original-price">ETB <?php echo number_format($product['original_price'], 2); ?></div>
                                <div class="discount-badge">
                                    Save ETB <?php echo number_format($product['discount_amount'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stock-status">
                            <?php if($product['stock'] > 10): ?>
                                <span class="in-stock">
                                    <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock']; ?> available)
                                </span>
                            <?php elseif($product['stock'] > 0): ?>
                                <span class="low-stock">
                                    <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock']; ?> left in stock
                                </span>
                            <?php else: ?>
                                <span class="out-stock">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin: 1.5rem 0; padding: 1rem; background: #f0f8ff; border-radius: 5px; border-left: 4px solid #667eea;">
                        <p style="color: #555; margin-bottom: 0.5rem;">
                            <i class="fas fa-info-circle" style="color: #667eea;"></i> 
                            <strong>Product Description:</strong>
                        </p>
                        <p style="color: #666;"><?php echo htmlspecialchars($product['description']); ?></p>
                    </div>

                    <div class="product-actions">
                        <button class="btn btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)" 
                            <?php echo ($product['stock'] <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                        <button class="btn btn-buy-now" onclick="buyNow(<?php echo $product['id']; ?>)" 
                            <?php echo ($product['stock'] <= 0) ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>
                        <button class="btn btn-wishlist" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                            <i class="fas fa-heart"></i> Add to Wishlist
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Specifications -->
            <div class="product-specs">
                <h2 style="color: #667eea; margin-bottom: 1rem;">
                    <i class="fas fa-list-alt"></i> Specifications
                </h2>
                <div class="specs-grid">
                    <?php if($product['model']): ?>
                    <div class="spec-item">
                        <span class="spec-label">Model</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['model']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($product['storage']): ?>
                    <div class="spec-item">
                        <span class="spec-label">Storage</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['storage']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($product['color']): ?>
                    <div class="spec-item">
                        <span class="spec-label">Color</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['color']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($product['screen_size']): ?>
                    <div class="spec-item">
                        <span class="spec-label">Screen Size</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['screen_size']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($product['camera']): ?>
                    <div class="spec-item">
                        <span class="spec-label">Camera</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['camera']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($product['battery']): ?>
                    <div class="spec-item">
                        <span class="spec-label">Battery</span>
                        <span class="spec-value"><?php echo htmlspecialchars($product['battery']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if($product['specifications']): ?>
                    <div class="spec-item" style="grid-column: 1 / -1; display: block; padding: 1.5rem; background: #f8f9fa; border-radius: 5px;">
                        <div class="spec-label" style="margin-bottom: 0.5rem; font-size: 1.1rem;">
                            <i class="fas fa-cogs"></i> Detailed Specifications
                        </div>
                        <div class="spec-value" style="text-align: left; color: #555;">
                            <?php echo nl2br(htmlspecialchars($product['specifications'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Products -->
            <?php if(count($relatedProducts) > 0): ?>
            <div class="related-products">
                <h2 class="section-title">
                    <i class="fas fa-mobile-alt"></i> Related Products
                </h2>
                <div class="related-grid">
                    <?php foreach($relatedProducts as $related): ?>
                    <div class="related-card" data-product-id="<?php echo $related['id']; ?>">
                        <div class="related-image">
                            <img 
                                src="<?php echo htmlspecialchars($related['optimized_image']); ?>" 
                                alt="<?php echo htmlspecialchars($related['name']); ?>"
                                loading="lazy"
                                width="300"
                                height="200"
                                onerror="this.onerror=null; this.src='<?php echo $related['fallback_image']; ?>'"
                            >
                        </div>
                        <div style="padding: 1.5rem;">
                            <h3 style="font-size: 1rem; margin-bottom: 0.5rem; min-height: 2.5rem;">
                                <?php echo htmlspecialchars($related['name']); ?>
                            </h3>
                            <div style="color: #667eea; font-weight: 600; margin-bottom: 0.5rem;">
                                <i class="fas fa-tag"></i> <?php echo ucfirst($related['brand']); ?>
                            </div>
                            <div style="color: #2ecc71; font-weight: 700; font-size: 1.2rem; margin-bottom: 1rem;">
                                ETB <?php echo number_format($related['price'], 2); ?>
                            </div>
                            <button onclick="window.location.href='product-detail.php?id=<?php echo $related['id']; ?>'" 
                                    style="width: 100%; padding: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- Product Not Found -->
            <div style="text-align: center; padding: 4rem 2rem; background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <i class="fas fa-search" style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;"></i>
                <h2 style="color: #666; margin-bottom: 1rem;">Product Not Found</h2>
                <p style="color: #999; margin-bottom: 2rem;">The product you're looking for doesn't exist or has been removed.</p>
                <a href="products.php" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: inline-flex; padding: 12px 24px;">
                    <i class="fas fa-arrow-left"></i> Back to Products
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
        
        // Add to Cart Function
        function addToCart(productId) {
            <?php if(!$isLoggedIn): ?>
                showNotification('Please login to add items to cart!', 'error');
                setTimeout(() => {
                    window.location.href = 'login-register.php?redirect=' + encodeURIComponent(window.location.href);
                }, 1500);
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
                        updateCartCount(data.cart_count || 0);
                        showNotification('✓ Product added to cart!', 'success');
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

        function buyNow(productId) {
            <?php if(!$isLoggedIn): ?>
                showNotification('Please login to purchase items!', 'error');
                setTimeout(() => {
                    window.location.href = 'login-register.php?redirect=' + encodeURIComponent(window.location.href);
                }, 1500);
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
                        updateCartCount(data.cart_count || 0);
                        showNotification('Redirecting to checkout...', 'success');
                        setTimeout(() => {
                            window.location.href = 'checkout.php';
                        }, 1000);
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

        function addToWishlist(productId) {
            <?php if(!$isLoggedIn): ?>
                showNotification('Please login to add items to wishlist!', 'error');
                setTimeout(() => {
                    window.location.href = 'login-register.php?redirect=' + encodeURIComponent(window.location.href);
                }, 1500);
            <?php else: ?>
                fetch('ajax_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=add_to_wishlist&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        showNotification('✓ Added to wishlist!', 'success');
                    } else {
                        showNotification('✗ ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('✗ Failed to add to wishlist', 'error');
                });
            <?php endif; }
        
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
        
        // Zoom image function
        function zoomImage() {
            const img = document.getElementById('mainProductImage');
            const largeUrl = img.getAttribute('data-large');
            
            // Create modal for zoomed image
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
                cursor: zoom-out;
            `;
            
            const zoomedImg = document.createElement('img');
            zoomedImg.src = largeUrl;
            zoomedImg.style.cssText = `
                max-width: 90%;
                max-height: 90%;
                object-fit: contain;
                animation: zoomIn 0.3s ease;
            `;
            
            // Add close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '<i class="fas fa-times"></i>';
            closeBtn.style.cssText = `
                position: absolute;
                top: 20px;
                right: 20px;
                background: rgba(255,255,255,0.2);
                color: white;
                border: none;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                font-size: 1.2rem;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
            `;
            closeBtn.onclick = () => modal.remove();
            
            modal.appendChild(zoomedImg);
            modal.appendChild(closeBtn);
            modal.onclick = (e) => {
                if(e.target === modal) modal.remove();
            };
            
            document.body.appendChild(modal);
            
            // Add CSS for animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes zoomIn {
                    from { transform: scale(0.8); opacity: 0; }
                    to { transform: scale(1); opacity: 1; }
                }
            `;
            document.head.appendChild(style);
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
            const mainImage = document.getElementById('mainProductImage');
            if(mainImage) {
                mainImage.addEventListener('error', function() {
                    this.src = '<?php echo $product['fallback_image'] ?? ""; ?>';
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>