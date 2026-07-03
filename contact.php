<?php
session_start();
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(htmlspecialchars($_POST['name'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $subject = trim(htmlspecialchars($_POST['subject'] ?? ''));
    $message = trim(htmlspecialchars($_POST['message'] ?? ''));

    if ($name && $email && $message) {
        // Here you can integrate mail() or PHPMailer to actually send the email to your inbox.
        // For now, it safely simulates a successful submission to the user.
        $success_msg = "Message dispatched! Our team will get back to you shortly.";
    } else {
        $error_msg = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koyuki | Contact Support</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Faculty+Glyphic&family=Rye&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            /* Cold as Ice Palette */
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
            padding-top: 80px;
            min-height: 100vh;
            background-attachment: fixed;
            display: flex;
            flex-direction: column;
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
            padding: 16px 20px;
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

        textarea.form-input {
            resize: vertical;
            min-height: 120px;
        }

        .btn-premium { 
            background: linear-gradient(135deg, var(--secondary), var(--primary)); 
            border: none; 
            border-radius: 16px; 
            font-weight: 700; 
            color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 229, 255, 0.3);
            transition: all 0.3s ease;
            text-transform: uppercase;
        }
        .btn-premium:hover { 
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(0, 229, 255, 0.5);
            color: white;
        }

        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Strict Navigation Formatting - Prevents Hover Underlines */
        nav a, nav a:hover { text-decoration: none !important; }
    </style>
</head>
<body>

<!-- NAVIGATION BAR -->
<nav class="fixed top-0 left-0 w-full z-50 bg-[#020613]/80 backdrop-blur-md border-b border-[rgba(0,229,255,0.2)] shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <!-- Left: Logo & Title -->
            <div class="flex items-center gap-2 sm:gap-3">
                <img src="logo.png" alt="Koyuki Logo" class="h-8 w-8 sm:h-10 sm:w-10 object-cover rounded-lg border border-[#00e5ff]/40 shadow-[0_0_15px_rgba(0,229,255,0.4)]" onerror="this.onerror=null; this.src='https://via.placeholder.com/40/00e5ff/ffffff?text=K';">
                <span class="rye-font text-xl sm:text-2xl tracking-wide glow-text mt-1">Koyuki Support</span>
            </div>
            <!-- Right: Links (Desktop) -->
            <div class="hidden sm:flex items-center gap-6">
                <a href="index.php" class="text-sm font-bold text-slate-300 hover:text-[#00e5ff] transition-colors uppercase tracking-wider">Checker</a>
                <a href="generator.php" class="bg-[rgba(0,229,255,0.1)] border border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813] px-4 py-2 rounded-xl text-sm font-bold transition-all shadow-[0_0_10px_rgba(0,229,255,0.1)] hover:shadow-[0_0_15px_rgba(0,229,255,0.4)] uppercase tracking-wider flex items-center">
                    Want some Netflix?
                </a>
            </div>
            <!-- Mobile Hamburger Button -->
            <div class="sm:hidden flex items-center">
                <button id="mobileMenuBtn" class="text-[#00e5ff] hover:text-white focus:outline-none text-2xl transition-colors p-2">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    <!-- Mobile Dropdown Menu -->
    <div id="mobileMenu" class="hidden sm:hidden bg-[#020813] border-t border-[rgba(0,229,255,0.1)] py-4 px-6 shadow-[0_10px_30px_rgba(0,0,0,0.8)]">
        <div class="flex flex-col gap-4">
            <a href="index.php" class="text-sm font-bold text-slate-300 hover:text-[#00e5ff] transition-colors uppercase tracking-wider flex items-center justify-center pt-2 pb-2">
                Checker
            </a>
            <a href="generator.php" class="bg-[rgba(0,229,255,0.1)] border border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813] px-4 py-3 rounded-xl text-sm font-bold transition-all shadow-[0_0_10px_rgba(0,229,255,0.1)] uppercase tracking-wider flex items-center justify-center">
                Want some Netflix?
            </a>
        </div>
    </div>
</nav>

<!-- MAIN CONTENT -->
<div class="container mx-auto px-4 mt-16 sm:mt-24 mb-20 flex-grow flex items-center justify-center">
    <div class="premium-card p-6 sm:p-10 w-full max-w-2xl relative overflow-hidden fade-in">
        <!-- Decorative Glows -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-400/10 rounded-full blur-3xl -mr-20 -mt-20 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-blue-600/10 rounded-full blur-3xl -ml-20 -mb-20 pointer-events-none"></div>

        <div class="relative z-10">
            <div class="text-center mb-8">
                <i class="fas fa-envelope-open-text text-4xl sm:text-5xl text-[#00e5ff] mb-4 drop-shadow-[0_0_15px_rgba(0,229,255,0.5)]"></i>
                <h1 class="rye-font text-3xl sm:text-4xl text-slate-200 mb-2 glow-text">Get in Touch</h1>
                <p class="text-[#7fa4c9] text-sm sm:text-base">Encountered an issue or have a business inquiry? Drop a message below.</p>
            </div>

            <form method="POST" action="">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                    <div>
                        <label class="block text-[10px] sm:text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Your Name</label>
                        <input type="text" name="name" class="form-input" required placeholder="John Doe">
                    </div>
                    <div>
                        <label class="block text-[10px] sm:text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Email Address</label>
                        <input type="email" name="email" class="form-input" required placeholder="john@example.com">
                    </div>
                </div>
                
                <div class="mb-4 sm:mb-6">
                    <label class="block text-[10px] sm:text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Subject</label>
                    <input type="text" name="subject" class="form-input" placeholder="What is this regarding?">
                </div>

                <div class="mb-8">
                    <label class="block text-[10px] sm:text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Message</label>
                    <textarea name="message" class="form-input" required placeholder="Describe your issue or inquiry in detail..."></textarea>
                </div>

                <button type="submit" class="btn-premium w-full py-4 text-sm sm:text-lg tracking-wider sm:tracking-[2px] flex items-center justify-center">
                    <i class="fas fa-paper-plane mr-2"></i> Send Message
                </button>
            </form>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer class="w-full border-t border-[rgba(0,229,255,0.15)] bg-[#020613]/80 backdrop-blur-md mt-auto py-6 text-center z-10 relative">
    <div class="max-w-[1200px] mx-auto px-4">
        <p class="text-[#7fa4c9] text-xs sm:text-sm font-medium tracking-wider">
            &copy; 2026 Koyuki NF Checker. All rights reserved &nbsp;<span class="text-[rgba(0,229,255,0.5)] hidden sm:inline">|</span><br class="sm:hidden"> 
            <span class="mt-2 sm:mt-0 inline-block">Powered by <span class="text-white font-bold">ZXNDEV</span></span>
        </p>
    </div>
</footer>

<script>
    // SweetAlert Interceptor for Form Submission
    <?php if ($success_msg): ?>
        Swal.fire({
            title: 'Message Sent!',
            text: '<?php echo $success_msg; ?>',
            icon: 'success',
            background: '#020813',
            color: '#f0f8ff',
            confirmButtonColor: '#00e5ff',
            customClass: { popup: 'border border-[rgba(0,229,255,0.3)] rounded-2xl' }
        });
    <?php endif; ?>

    <?php if ($error_msg): ?>
        Swal.fire({
            title: 'Error',
            text: '<?php echo $error_msg; ?>',
            icon: 'error',
            background: '#020813',
            color: '#f0f8ff',
            confirmButtonColor: '#f87171',
            customClass: { popup: 'border border-[rgba(239,68,68,0.3)] rounded-2xl' }
        });
    <?php endif; ?>

    // Mobile Menu Toggle Logic
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('mobileMenuBtn');
        const menu = document.getElementById('mobileMenu');
        if(btn && menu) {
            btn.addEventListener('click', function() {
                menu.classList.toggle('hidden');
                const icon = btn.querySelector('i');
                if(menu.classList.contains('hidden')) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                } else {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                }
            });
        }
    });
</script>

</body>
</html>