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

// Check if already logged in
if(isset($_SESSION['user_id'])) {
    $redirect = $_GET['redirect'] ?? 'index.php';
    header('Location: ' . $redirect);
    exit();
}

$isRegister = isset($_GET['register']) && $_GET['register'] === 'true';
$errors = [];
$success = '';

// Handle Login
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if(empty($username)) {
        $errors[] = 'Username is required';
    }
    if(empty($password)) {
        $errors[] = 'Password is required';
    }
    
    if(count($errors) === 0) {
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user) {
                // In production, use password_verify()
                if($password === $user['password']) {
                    // Check if user is banned
                    if($user['status'] === 'banned') {
                        $errors[] = 'Your account has been suspended. Contact support.';
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['last_activity'] = time();
                        
                        // Update last login
                        $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                        $stmt->bindParam(':id', $user['id']);
                        $stmt->execute();
                        
                        // Log activity
                        $stmt = $conn->prepare("
                            INSERT INTO user_activity (user_id, activity_type, description, ip_address, user_agent) 
                            VALUES (:user_id, 'login', 'User logged in', :ip, :ua)
                        ");
                        $stmt->bindParam(':user_id', $user['id']);
                        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                        $stmt->bindParam(':ua', $_SERVER['HTTP_USER_AGENT']);
                        $stmt->execute();
                        
                        // Redirect
                        $redirect = $_GET['redirect'] ?? 'index.php';
                        header('Location: ' . $redirect);
                        exit();
                    }
                } else {
                    $errors[] = 'Invalid username or password';
                }
            } else {
                $errors[] = 'Invalid username or password';
            }
        } catch(PDOException $e) {
            $errors[] = 'Login failed. Please try again.';
        }
    }
}

// Handle Registration
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validation
    if(empty($username)) {
        $errors[] = 'Username is required';
    } elseif(strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if(empty($email)) {
        $errors[] = 'Email is required';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if(empty($password)) {
        $errors[] = 'Password is required';
    } elseif(strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    // Check if username/email already exists
    if(empty($errors)) {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $errors[] = 'Username or email already exists';
            }
        } catch(PDOException $e) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
    
    if(count($errors) === 0) {
        try {
            // In production, use password_hash()
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, phone, role, created_at) 
                VALUES (:username, :email, :password, :full_name, :phone, 'user', NOW())
            ");
            
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->execute();
            
            $userId = $conn->lastInsertId();
            
            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO user_activity (user_id, activity_type, description, ip_address, user_agent) 
                VALUES (:user_id, 'register', 'New user registered', :ip, :ua)
            ");
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
            $stmt->bindParam(':ua', $_SERVER['HTTP_USER_AGENT']);
            $stmt->execute();
            
            $success = 'Registration successful! You can now login.';
            $isRegister = false; // Switch to login form
            
        } catch(PDOException $e) {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isRegister ? 'Register' : 'Login'; ?> - Mobile Suking</title>
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
            background-color: #ffcc00;
            color: #333;
        }

        /* Auth Container */
        .auth-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-box {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }

        /* Auth Header */
        .auth-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .auth-title {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .auth-subtitle {
            opacity: 0.9;
        }

        .auth-tabs {
            display: flex;
            margin-top: 2rem;
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255,255,255,0.1);
        }

        .auth-tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .auth-tab.active {
            background: white;
            color: #667eea;
        }

        /* Auth Body */
        .auth-body {
            padding: 2rem;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.error {
            background-color: #ffebee;
            border: 1px solid #ffcdd2;
            color: #c62828;
        }

        .message.success {
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
        }

        /* Forms */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }

        .form-label .required {
            color: #ff4757;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-input {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-auth {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            justify-content: center;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-register {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
        }

        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            color: #666;
        }

        .auth-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .auth-link:hover {
            text-decoration: underline;
        }

        /* Password Strength */
        .password-strength {
            margin-top: 0.5rem;
            height: 5px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-weak { background-color: #ff4757; width: 33%; }
        .strength-medium { background-color: #ff9f43; width: 66%; }
        .strength-strong { background-color: #2ecc71; width: 100%; }

        .strength-text {
            font-size: 0.85rem;
            margin-top: 0.25rem;
            color: #666;
        }

        /* Terms */
        .terms {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .terms input[type="checkbox"] {
            margin-top: 0.25rem;
        }

        .terms label {
            font-size: 0.9rem;
            color: #666;
        }

        .terms a {
            color: #667eea;
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            background-color: #2c3e50;
            color: white;
            padding: 2rem;
            text-align: center;
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
            
            .auth-box {
                margin: 1rem;
            }
        }

        @media (max-width: 480px) {
            .auth-header {
                padding: 1.5rem;
            }
            
            .auth-body {
                padding: 1.5rem;
            }
            
            .auth-title {
                font-size: 1.5rem;
            }
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
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                <li><a href="contact.php"><i class="fas fa-headset"></i> Contact</a></li>
            </ul>
            
            <div style="display: flex; gap: 1rem;">
                <?php if($isRegister): ?>
                    <a href="?register=false" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                <?php else: ?>
                    <a href="?register=true" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Auth Container -->
    <div class="auth-container">
        <div class="auth-box">
            <!-- Auth Header -->
            <div class="auth-header">
                <h1 class="auth-title">
                    <?php echo $isRegister ? 'Create Account' : 'Welcome Back'; ?>
                </h1>
                <p class="auth-subtitle">
                    <?php echo $isRegister ? 'Join Mobile Suking today' : 'Sign in to your account'; ?>
                </p>
                
                <div class="auth-tabs">
                    <div class="auth-tab <?php echo !$isRegister ? 'active' : ''; ?>" onclick="showLogin()">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </div>
                    <div class="auth-tab <?php echo $isRegister ? 'active' : ''; ?>" onclick="showRegister()">
                        <i class="fas fa-user-plus"></i> Register
                    </div>
                </div>
            </div>

            <!-- Auth Body -->
            <div class="auth-body">
                <?php if(count($errors) > 0): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php foreach($errors as $error): ?>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if($success): ?>
                    <div class="message success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo htmlspecialchars($success); ?></div>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" id="loginForm" <?php echo $isRegister ? 'style="display: none;"' : ''; ?>>
                    <input type="hidden" name="login" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">
                            Username <span class="required">*</span>
                        </label>
                        <input type="text" name="username" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Password <span class="required">*</span>
                        </label>
                        <div class="password-input">
                            <input type="password" name="password" class="form-input" id="loginPassword" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('loginPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-bottom: 1.5rem;">
                        <a href="forgot-password.php" class="auth-link" style="font-size: 0.9rem;">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-auth btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login to Account
                        </button>
                        
                        <div class="auth-links">
                            Don't have an account? 
                            <a href="?register=true" class="auth-link">Register here</a>
                        </div>
                    </div>
                </form>

                <!-- Register Form -->
                <form method="POST" id="registerForm" <?php echo !$isRegister ? 'style="display: none;"' : ''; ?>>
                    <input type="hidden" name="register" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">
                            Full Name <span class="required">*</span>
                        </label>
                        <input type="text" name="full_name" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">
                                Username <span class="required">*</span>
                            </label>
                            <input type="text" name="username" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Phone Number
                            </label>
                            <input type="tel" name="phone" class="form-input" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            Email Address <span class="required">*</span>
                        </label>
                        <input type="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label class="form-label">
                                Password <span class="required">*</span>
                            </label>
                            <div class="password-input">
                                <input type="password" name="password" class="form-input" 
                                       id="registerPassword" 
                                       oninput="checkPasswordStrength(this.value)" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('registerPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="strengthBar"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Password strength</div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                Confirm Password <span class="required">*</span>
                            </label>
                            <div class="password-input">
                                <input type="password" name="confirm_password" class="form-input" 
                                       id="confirmPassword" required>
                                <button type="button" class="toggle-password" onclick="togglePassword('confirmPassword', this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="passwordMatch" style="font-size: 0.85rem; margin-top: 0.25rem;"></div>
                        </div>
                    </div>
                    
                    <div class="terms">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">
                            I agree to the <a href="terms.php">Terms of Service</a> and 
                            <a href="privacy.php">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-auth btn-register">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                        
                        <div class="auth-links">
                            Already have an account? 
                            <a href="?register=false" class="auth-link">Login here</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <p>&copy; <?php echo date('Y'); ?> Mobile Suking. All rights reserved.</p>
            <p style="margin-top: 1rem; color: #bdc3c7;">
                <i class="fas fa-phone"></i> +251 911 223 344 | 
                <i class="fas fa-envelope"></i> info@mobilesuking.com
            </p>
        </div>
    </footer>

    <script>
        // Toggle between login and register
        function showLogin() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('registerForm').style.display = 'none';
            
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.auth-tab')[0].classList.add('active');
            
            // Update URL without reload
            history.pushState(null, '', '?register=false');
        }

        function showRegister() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('registerForm').style.display = 'block';
            
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.auth-tab')[1].classList.add('active');
            
            history.pushState(null, '', '?register=true');
        }

        // Toggle password visibility
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if(input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Check password strength
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if(password.length >= 8) strength++;
            if(/[A-Z]/.test(password)) strength++;
            if(/[0-9]/.test(password)) strength++;
            if(/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Weak';
                    color = 'strength-weak';
                    break;
                case 2:
                case 3:
                    text = 'Medium';
                    color = 'strength-medium';
                    break;
                case 4:
                    text = 'Strong';
                    color = 'strength-strong';
                    break;
            }
            
            strengthBar.className = 'strength-bar ' + color;
            strengthText.textContent = text;
            strengthText.style.color = color === 'strength-weak' ? '#ff4757' : 
                                     color === 'strength-medium' ? '#ff9f43' : '#2ecc71';
            
            // Check password match
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if(confirmPassword) {
                if(password === confirmPassword) {
                    matchDiv.innerHTML = '<span style="color: #2ecc71;">✓ Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span style="color: #ff4757;">✗ Passwords do not match</span>';
                }
            }
        }

        // Check password match on confirm password input
        document.getElementById('confirmPassword').addEventListener('input', function() {
            const password = document.getElementById('registerPassword').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if(this.value) {
                if(password === this.value) {
                    matchDiv.innerHTML = '<span style="color: #2ecc71;">✓ Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span style="color: #ff4757;">✗ Passwords do not match</span>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = ['loginForm', 'registerForm'];
            forms.forEach(formId => {
                const form = document.getElementById(formId);
                if(form) {
                    form.addEventListener('submit', function(e) {
                        const requiredFields = this.querySelectorAll('[required]');
                        let isValid = true;
                        
                        requiredFields.forEach(field => {
                            if(!field.value.trim()) {
                                isValid = false;
                                field.style.borderColor = '#ff4757';
                            } else {
                                field.style.borderColor = '#e0e0e0';
                            }
                        });
                        
                        if(!isValid) {
                            e.preventDefault();
                            alert('Please fill all required fields');
                        }
                    });
                }
            });
            
            // Initialize password strength for existing value
            const registerPassword = document.getElementById('registerPassword');
            if(registerPassword && registerPassword.value) {
                checkPasswordStrength(registerPassword.value);
            }
        });
    </script>
</body>
</html>