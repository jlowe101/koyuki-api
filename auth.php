<?php
session_start();
require_once 'db.php';

// Check if registration is locked by Admin
$reg_locked = false;
if (isset($pdo)) {
    $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_locked'");
    if ($stmt->fetchColumn() === 'true') {
        $reg_locked = true;
    }
}

// Check for kicked/revoked messages
$error_msg = '';
if (isset($_GET['error']) && $_GET['error'] === 'revoked') {
    $error_msg = "Your access has been revoked or is pending approval.";
}

// If already logged in, send directly to generator
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header("Location: generator.php");
    exit;
}

$success_msg = '';
$mode = 'login'; // default mode

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'register') {
        $mode = 'register'; 
        
        if ($reg_locked) {
            $error_msg = "Sign up is temporarily disabled by the administrator.";
        } else {
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $forum = trim($_POST['forum'] ?? '');

            if ($email && $username && $password && $forum) {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, username, password_hash, forum, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmt->execute([$email, $username, $hash, $forum]);
                    $success_msg = "Registration successful! Your account is pending admin approval.";
                    $mode = 'login'; 
                } catch (\PDOException $e) {
                    // Check if it's a unique constraint violation (duplicate username/email)
                    if ($e->getCode() == 23505) {
                        $error_msg = "Username or Email already exists in the databank.";
                    } else {
                        // Show the actual error if it's something else
                        $error_msg = "Database Error: " . $e->getMessage();
                    }
                }
            } else {
                $error_msg = "Please fill in all required fields.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'login') {
        $mode = 'login';
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Access Control Check
                if ($user['status'] === 'pending') {
                    $error_msg = "Access Denied: Your account is awaiting administrator approval.";
                } elseif ($user['status'] === 'rejected') {
                    $error_msg = "Access Denied: Your account has been revoked by the admin.";
                } else {
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_username'] = $user['username'];
                    header("Location: generator.php");
                    exit;
                }
            } else {
                $error_msg = "Invalid credentials. Access denied.";
            }
        } else {
            $error_msg = "Please provide both username and password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koyuki | Member Access</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Faculty+Glyphic&family=Rye&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00e5ff;       
            --secondary: #0055ff;     
            --bg-dark: #020813;       
            --card-bg: rgba(4, 12, 24, 0.65);
            --card-border: rgba(0, 229, 255, 0.25);
            --text-main: #f0f8ff;
            --text-muted: #7fa4c9;
        }

        body { 
            background: radial-gradient(ellipse at 50% 0%, #003366 0%, var(--bg-dark) 70%);
            color: var(--text-main); 
            font-family: 'Faculty Glyphic', sans-serif; 
            min-height: 100vh;
            background-attachment: fixed;
        }
        
        .rye-font { font-family: 'Rye', serif; font-weight: normal; }

        .premium-card { 
            background: var(--card-bg); 
            border: 1px solid var(--card-border); 
            border-radius: 24px; 
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.05) inset;
        }

        .glow-text {
            background: linear-gradient(to right, #00e5ff, #8ab4f8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 20px rgba(0, 229, 255, 0.4);
        }

        .form-input {
            background: rgba(2, 8, 20, 0.6); 
            border: 1px solid rgba(0, 229, 255, 0.2); 
            color: #e2e8f0; 
            border-radius: 12px; 
            padding: 14px 20px;
            width: 100%;
            font-size: 14px;
            font-family: 'Faculty Glyphic', sans-serif;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            background: rgba(2, 8, 20, 0.9); 
            border-color: var(--primary); 
            color: #fff; 
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.2); 
            outline: none; 
        }

        .btn-premium { 
            background: linear-gradient(135deg, var(--secondary), var(--primary)); 
            border: none; 
            border-radius: 12px; 
            padding: 14px;
            font-weight: 700; 
            letter-spacing: 1px;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 229, 255, 0.3);
            transition: all 0.3s ease;
            text-transform: uppercase;
            width: 100%;
            cursor: pointer;
        }
        .btn-premium:hover { 
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(0, 229, 255, 0.5);
        }

        .fade-in { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex flex-col items-center justify-center min-h-screen relative py-10 px-4">

<!-- Top Background Glows -->
<div class="fixed top-0 right-0 w-96 h-96 bg-cyan-400/10 rounded-full blur-[100px] pointer-events-none -z-10"></div>
<div class="fixed bottom-0 left-0 w-96 h-96 bg-blue-600/10 rounded-full blur-[100px] pointer-events-none -z-10"></div>

<div class="premium-card p-6 sm:p-8 w-full max-w-md relative overflow-hidden">
    <div class="text-center mb-6 relative z-10">
        <div class="inline-block p-2 rounded-full bg-[#040c18] border border-[rgba(0,229,255,0.3)] mb-4 shadow-[0_0_15px_rgba(0,229,255,0.2)]">
            <img src="koyun1.png" alt="Koyuki Logo" class="h-16 w-16 sm:h-20 sm:w-20 object-cover rounded-full border border-[#00e5ff]/20" onerror="this.onerror=null; this.src='https://via.placeholder.com/80/00e5ff/ffffff?text=K';">
        </div>
    </div>

    <?php if ($error_msg): ?>
        <div class="border border-red-500/50 text-red-400 bg-red-900/20 p-3 rounded-xl text-sm mb-6 flex items-center shadow-[0_0_10px_rgba(239,68,68,0.2)] relative z-10">
            <i class="fas fa-exclamation-circle mr-3 text-lg flex-shrink-0"></i> <span><?php echo htmlspecialchars($error_msg); ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success_msg): ?>
        <div class="border border-emerald-500/50 text-emerald-400 bg-emerald-900/20 p-3 rounded-xl text-sm mb-6 flex items-center shadow-[0_0_10px_rgba(52,211,153,0.2)] relative z-10">
            <i class="fas fa-check-circle mr-3 text-lg flex-shrink-0"></i> <span><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- LOGIN FORM -->
    <div id="login-form" class="relative z-10 <?php echo $mode === 'login' ? 'fade-in' : 'hidden'; ?>">
        <h2 class="text-2xl sm:text-3xl rye-font glow-text text-center mb-2">Member Access</h2>
        <p class="text-[#7fa4c9] text-xs sm:text-sm text-center mb-6">Authenticate to access the generator</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-5">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Username / Email</label>
                <input type="text" name="username" class="form-input" required placeholder="Enter credentials">
            </div>
            <div class="mb-8">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" class="form-input" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-premium mb-4">
                <i class="fas fa-sign-in-alt mr-2"></i> Authenticate
            </button>
            <p class="text-center text-sm text-[#7fa4c9]">
                Don't have an account? <a href="#" onclick="toggleMode('register')" class="text-[#00e5ff] hover:underline font-bold tracking-wider">Sign up</a>
            </p>
        </form>
    </div>

    <!-- REGISTER FORM -->
    <div id="register-form" class="relative z-10 <?php echo $mode === 'register' ? 'fade-in' : 'hidden'; ?>">
        <h2 class="text-2xl sm:text-3xl rye-font glow-text text-center mb-2">Request Access</h2>
        <p class="text-[#7fa4c9] text-xs sm:text-sm text-center mb-6">Register a new generator node</p>

        <form method="POST">
            <input type="hidden" name="action" value="register">
            <div class="mb-4">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Email Address</label>
                <input type="email" name="email" class="form-input" required placeholder="your@email.com">
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Username</label>
                <input type="text" name="username" class="form-input" required placeholder="Choose a username">
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" class="form-input" required placeholder="••••••••">
            </div>
            <div class="mb-6">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">What Forum?</label>
                <input type="text" name="forum" class="form-input" required placeholder="E.g., Nulled, LeakForums, etc.">
            </div>
            <button type="submit" class="btn-premium mb-4" id="btn-submit-reg">
                <i class="fas fa-user-plus mr-2"></i> Register Account
            </button>
            <p class="text-center text-sm text-[#7fa4c9]">
                Already have an account? <a href="#" onclick="toggleMode('login')" class="text-[#00e5ff] hover:underline font-bold tracking-wider">Sign in</a>
            </p>
        </form>
    </div>

    <div class="text-center mt-6 border-t border-[rgba(0,229,255,0.1)] pt-4 relative z-10">
        <a href="index.php" class="text-xs text-slate-400 hover:text-white transition-colors uppercase tracking-widest"><i class="fas fa-arrow-left mr-1"></i> Return to Checker</a>
    </div>
</div>

<script>
    function toggleMode(targetMode) {
        if (targetMode === 'register') {
            const isLocked = <?php echo $reg_locked ? 'true' : 'false'; ?>;
            if (isLocked) {
                Swal.fire({
                    title: 'Registration Disabled',
                    text: 'Sign up is temporarily disabled by the administrator.',
                    icon: 'warning',
                    background: '#020813',
                    color: '#f0f8ff',
                    confirmButtonColor: '#00e5ff',
                    customClass: { popup: 'border border-[rgba(0,229,255,0.3)] rounded-2xl' }
                });
                return; // Stop execution, don't switch to register form
            }
            
            document.getElementById('login-form').classList.add('hidden');
            document.getElementById('register-form').classList.remove('hidden');
            document.getElementById('register-form').classList.add('fade-in');
        } else {
            document.getElementById('register-form').classList.add('hidden');
            document.getElementById('login-form').classList.remove('hidden');
            document.getElementById('login-form').classList.add('fade-in');
        }
    }
</script>

</body>
</html>