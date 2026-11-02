<?php
session_start();
require_once __DIR__ . '/config/database.php';

// If already logged in, redirect straight to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

// Check auto-login via remember cookie
if (isset($_COOKIE['cashtrack_remember'])) {
    $token = $_COOKIE['cashtrack_remember'];
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `remember_token` = ? AND `status` = 1 LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        header("Location: dashboard.php");
        exit;
    }
}

$message = '';
$message_type = '';

if (isset($_GET['logged_out'])) {
    $message = 'Bạn đã đăng xuất an toàn khỏi hệ thống!';
    $message_type = 'info';
}

// Generate server-side captcha if not set
if (empty($_SESSION['cashtrack_security_code'])) {
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < 4; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    $_SESSION['cashtrack_security_code'] = $code;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
               (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $securityCode = strtoupper(trim($_POST['securityCode'] ?? ''));
    $remember = isset($_POST['rememberMe']);
    $sessionCode = $_SESSION['cashtrack_security_code'] ?? '';

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    $response = ['success' => false, 'message' => ''];

    // 1. Validation
    if (empty($username) || empty($password)) {
        $response['message'] = 'Vui lòng nhập đầy đủ tài khoản và mật khẩu!';
    } elseif (empty($securityCode)) {
        $response['message'] = 'Vui lòng nhập mã bảo mật (SECURITY)!';
    } elseif ($securityCode !== $sessionCode) {
        $response['message'] = 'Mã bảo mật (SECURITY) không chính xác! Vui lòng thử lại.';
        // Log attempt
        $logStmt = $pdo->prepare("INSERT INTO `login_logs` (`username`, `ip_address`, `user_agent`, `status`) VALUES (?, ?, ?, 'failed')");
        $logStmt->execute([$username, $ip, $userAgent]);
    } else {
        // 2. Query user from database
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE (`username` = ? OR `email` = ?) AND `status` = 1 LIMIT 1");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Success! Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];

            // Update last login
            $updateStmt = $pdo->prepare("UPDATE `users` SET `last_login` = NOW() WHERE `id` = ?");
            $updateStmt->execute([$user['id']]);

            // Handle Remember Me
            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                $tokenStmt = $pdo->prepare("UPDATE `users` SET `remember_token` = ? WHERE `id` = ?");
                $tokenStmt->execute([$rememberToken, $user['id']]);
                // Set cookie for 30 days
                setcookie('cashtrack_remember', $rememberToken, time() + (86400 * 30), '/', '', false, true);
            }

            // Log success
            $logStmt = $pdo->prepare("INSERT INTO `login_logs` (`username`, `ip_address`, `user_agent`, `status`) VALUES (?, ?, ?, 'success')");
            $logStmt->execute([$user['username'], $ip, $userAgent]);

            $response['success'] = true;
            $response['message'] = "Đăng nhập thành công! Đang chuyển hướng...";
            $response['redirect'] = "dashboard.php";
        } else {
            $response['message'] = 'Tài khoản hoặc mật khẩu không chính xác!';
            // Log failure
            $logStmt = $pdo->prepare("INSERT INTO `login_logs` (`username`, `ip_address`, `user_agent`, `status`) VALUES (?, ?, ?, 'failed')");
            $logStmt->execute([$username, $ip, $userAgent]);
        }
    }

    // Refresh captcha
    $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $newCode = '';
    for ($i = 0; $i < 4; $i++) {
        $newCode .= $chars[rand(0, strlen($chars) - 1)];
    }
    $_SESSION['cashtrack_security_code'] = $newCode;
    $response['newCaptcha'] = $newCode;

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response);
        exit;
    } else {
        if ($response['success']) {
            header("Location: dashboard.php");
            exit;
        } else {
            $message = $response['message'];
            $message_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CashTrack - Login</title>
  
  <!-- Google Fonts: Plus Jakarta Sans -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
          },
          colors: {
            brand: {
              cyan: '#00d2ff',
              blue: '#0070f3',
              navy: '#0b101d',
              dark: '#060913',
              card: '#0c111e',
              input: '#121826',
              border: '#1c2538'
            }
          }
        }
      }
    }
  </script>
  
  <style>
    * {
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #060913;
      color: #f1f5f9;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow-x: hidden;
      margin: 0;
      padding: 24px 16px;
    }

    /* Ambient background mesh glows matching Remini Dribbble shot */
    .bg-mesh {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: 
        radial-gradient(circle at 15% 20%, rgba(0, 210, 255, 0.18) 0%, transparent 45%),
        radial-gradient(circle at 85% 78%, rgba(0, 114, 255, 0.22) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(12, 17, 30, 0.94) 0%, #060913 100%);
    }

    .bg-grid-lines {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      opacity: 0.035;
      background-image: 
        linear-gradient(to right, #ffffff 1px, transparent 1px),
        linear-gradient(to bottom, #ffffff 1px, transparent 1px);
      background-size: 38px 38px;
    }

    /* Sleek Mobile / App Card container matching the Dribbble shot */
    .app-card {
      position: relative;
      z-index: 10;
      background: rgba(13, 18, 30, 0.92);
      backdrop-filter: blur(28px);
      -webkit-backdrop-filter: blur(28px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 
        0 30px 60px -15px rgba(0, 0, 0, 0.8),
        0 0 0 1px rgba(0, 210, 255, 0.06),
        0 0 70px -10px rgba(0, 114, 255, 0.2);
      border-radius: 40px;
      width: 100%;
      max-width: 420px;
      padding: 36px 30px 32px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    /* Input styling - Remini style pill container */
    .app-input {
      background: rgba(19, 26, 42, 0.75);
      border: 1px solid rgba(255, 255, 255, 0.08);
      transition: all 0.22s ease-in-out;
      color: #ffffff;
    }

    .app-input:focus {
      background: rgba(22, 31, 51, 0.95);
      border-color: #00d2ff;
      box-shadow: 0 0 0 3px rgba(0, 210, 255, 0.16), 0 0 20px rgba(0, 210, 255, 0.12);
      outline: none;
    }

    /* Remini signature glowing Cyan-to-Blue pill button */
    .btn-gradient {
      background: linear-gradient(135deg, #00d2ff 0%, #0066ff 100%);
      box-shadow: 0 8px 26px -3px rgba(0, 114, 255, 0.5);
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .btn-gradient:hover {
      box-shadow: 0 12px 32px -2px rgba(0, 114, 255, 0.7);
      transform: translateY(-1.5px);
      filter: brightness(1.05);
    }

    .btn-gradient:active {
      transform: translateY(1px);
      box-shadow: 0 4px 14px rgba(0, 114, 255, 0.35);
    }

    /* Captcha Canvas Badge */
    .captcha-badge {
      background: linear-gradient(135deg, rgba(17, 24, 40, 0.95), rgba(10, 15, 28, 0.95));
      border: 1px solid rgba(0, 210, 255, 0.22);
      box-shadow: inset 0 0 16px rgba(0, 210, 255, 0.05);
      user-select: none;
    }

    /* Custom Checkbox */
    .custom-checkbox {
      appearance: none;
      -webkit-appearance: none;
      width: 19px;
      height: 19px;
      border: 1.5px solid rgba(255, 255, 255, 0.22);
      border-radius: 6px;
      background: rgba(18, 25, 40, 0.6);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
      position: relative;
    }

    .custom-checkbox:checked {
      background: #00d2ff;
      border-color: #00d2ff;
      box-shadow: 0 0 12px rgba(0, 210, 255, 0.45);
    }

    .custom-checkbox:checked::after {
      content: '';
      width: 5px;
      height: 9px;
      border: solid #060913;
      border-width: 0 2.2px 2.2px 0;
      transform: rotate(45deg);
      margin-bottom: 2px;
    }

    /* Toast Notification */
    #toast {
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 100;
      transform: translateY(-80px);
      opacity: 0;
      transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
      pointer-events: none;
    }

    #toast.show {
      transform: translateY(0);
      opacity: 1;
      pointer-events: auto;
    }
  </style>
</head>
<body>

  <!-- Ambient Glow Effects -->
  <div class="bg-mesh"></div>
  <div class="bg-grid-lines"></div>

  <!-- Toast Notification System -->
  <div id="toast" class="flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold">
    <div id="toast-icon"></div>
    <div id="toast-message"></div>
  </div>

  <!-- Main Login Card Container -->
  <div class="app-card">
    
    <!-- Top Mobile-App Header (Matching Remini shot: Back button on left, Logo on right) -->
    <div class="flex items-center justify-between mb-8">
      <!-- Back Arrow Button -->
      <button 
        type="button" 
        class="w-10 h-10 rounded-full bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 flex items-center justify-center text-slate-300 hover:text-white transition duration-200" 
        title="Quay lại"
        onclick="showToast('info', 'Trang đăng nhập hệ thống bảo mật CashTrack')"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
      </button>

      <!-- App Logo on the Right (Authentic Remini Style Badge) -->
      <div class="flex items-center gap-2">
        <div class="relative w-8 h-8 rounded-xl bg-gradient-to-tr from-cyan-500/20 to-blue-500/30 border border-cyan-400/40 flex items-center justify-center shadow-[0_0_15px_rgba(0,210,255,0.25)]">
          <svg class="w-4 h-4 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
            <circle cx="12" cy="13" r="3" stroke-width="2" />
          </svg>
        </div>
        <span class="text-sm font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-100 to-cyan-300 bg-clip-text text-transparent">
          CashTrack
        </span>
      </div>
    </div>

    <!-- Heading Title Section -->
    <div class="mb-7">
      <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
        Get Started Now <span class="inline-block hover:scale-110 transition-transform duration-200">👋</span>
      </h1>
      <p class="text-xs text-slate-400 mt-1.5 leading-relaxed font-normal">
        Đăng nhập hệ thống quản lý tài chính & dòng tiền CashTrack
      </p>
    </div>

    <!-- Form Section connected to MySQL Backend -->
    <form id="loginForm" method="POST" action="index.php" class="space-y-4" autocomplete="off" onsubmit="handleAjaxLogin(event)">
      
      <!-- Field 1: USERNAME / EMAIL -->
      <div>
        <label for="username" class="block text-xs font-semibold text-slate-300 mb-1.5">
          Tài khoản (User / Email)
        </label>
        <div class="relative flex items-center">
          <span class="absolute left-4 text-slate-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
          </span>
          <input 
            type="text" 
            id="username" 
            name="username" 
            required 
            placeholder="Username hoặc email..."
            class="app-input w-full pl-11 pr-4 py-3 rounded-2xl text-sm placeholder:text-slate-500 font-medium"
          />
        </div>
      </div>

      <!-- Field 2: PASSWORD -->
      <div>
        <label for="password" class="block text-xs font-semibold text-slate-300 mb-1.5">
          Mật khẩu (Password)
        </label>
        <div class="relative flex items-center">
          <span class="absolute left-4 text-slate-400 pointer-events-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
          </span>
          <input 
            type="password" 
            id="password" 
            name="password" 
            required 
            placeholder="Mật khẩu của bạn..."
            class="app-input w-full pl-11 pr-11 py-3 rounded-2xl text-sm placeholder:text-slate-500 font-medium"
          />
          <!-- Eye Toggle Button -->
          <button 
            type="button" 
            id="togglePassword" 
            onclick="togglePasswordVisibility()" 
            class="absolute right-3.5 text-slate-400 hover:text-cyan-400 transition-colors p-1"
            title="Hiện / Ẩn mật khẩu"
          >
            <svg id="eyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Field 3: SECURITY (MÃ BẢO MẬT) -->
      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label for="securityCode" class="block text-xs font-semibold text-slate-300">
            Mã bảo mật <span class="text-cyan-400 font-bold">(SECURITY)</span>
          </label>
          <span class="text-[10px] text-slate-500">Mã xác thực</span>
        </div>
        
        <div class="grid grid-cols-12 gap-2.5">
          <!-- Input Security Code -->
          <div class="col-span-7 relative flex items-center">
            <span class="absolute left-4 text-slate-400 pointer-events-none">
              <svg class="w-4 h-4 text-cyan-400/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
              </svg>
            </span>
            <input 
              type="text" 
              id="securityCode" 
              name="securityCode" 
              required 
              maxlength="5"
              placeholder="MÃ XÁC THỰC"
              class="app-input w-full pl-11 pr-2 py-3 rounded-2xl text-xs placeholder:text-slate-500 font-mono font-bold tracking-widest uppercase"
            />
          </div>

          <!-- Captcha Box with Canvas & Refresh Button -->
          <div class="col-span-5 flex items-center justify-between captcha-badge px-2 py-1 rounded-2xl">
            <!-- Canvas generating dynamic visual captcha -->
            <canvas id="captchaCanvas" width="180" height="68" style="width: 86px; height: 34px;" class="rounded cursor-pointer" onclick="refreshCaptcha()" title="Bấm để đổi mã mới"></canvas>
            
            <!-- Refresh Button -->
            <button 
              type="button" 
              onclick="refreshCaptcha()" 
              class="p-1 text-slate-400 hover:text-cyan-400 hover:rotate-180 transition-all duration-300 rounded-lg hover:bg-white/5"
              title="Đổi mã bảo mật khác"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Field 4: REMEMBER ME & FORGOT PASSWORD -->
      <div class="flex items-center justify-between pt-1">
        <label class="flex items-center gap-2 cursor-pointer select-none">
          <input type="checkbox" id="rememberMe" name="rememberMe" class="custom-checkbox">
          <span class="text-xs text-slate-300 font-medium">Ghi nhớ đăng nhập</span>
        </label>
        
        <a href="javascript:void(0)" onclick="showToast('info', 'Tài khoản mặc định: admin / admin123 hoặc demo / demo123')" class="text-xs text-cyan-400 hover:text-cyan-300 transition-colors font-medium">
          Quên mật khẩu?
        </a>
      </div>

      <!-- Field 5: BUTTON LOGIN -->
      <div class="pt-3">
        <button 
          type="submit" 
          id="btnLogin"
          class="btn-gradient w-full py-3.5 rounded-full text-white font-bold text-sm tracking-wide flex items-center justify-center gap-2 transition duration-200"
        >
          <span id="btnText">Log In</span>
          <svg id="btnSpinner" class="hidden w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <svg id="btnArrow" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </button>
      </div>

      <!-- Fast Demo Fill Button for easy testing -->
      <div class="pt-2 text-center">
        <button 
          type="button" 
          onclick="fillDemoAccount('admin', 'admin123')"
          class="text-[11px] text-cyan-400/80 hover:text-cyan-300 underline underline-offset-4 decoration-cyan-400/30 transition-colors"
        >
          ⚡ Điền nhanh tài khoản Admin mẫu
        </button>
      </div>

      <!-- Strict requirement: NO SIGN UP / REGISTER SECTION -->
      <!-- Security Trust Footer -->
      <div class="pt-4 text-center border-t border-white/[0.06]">
        <div class="inline-flex items-center gap-1.5 text-[11px] text-slate-500 font-medium">
          <svg class="w-3.5 h-3.5 text-cyan-400/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span>Hệ thống bảo mật SSL 256-bit &bull; CashTrack MariaDB</span>
        </div>
      </div>

    </form>
  </div>

  <!-- JavaScript Functionality -->
  <script>
    let currentCaptcha = "<?= htmlspecialchars($_SESSION['cashtrack_security_code'] ?? '') ?>";

    // Draw captcha code onto Canvas
    function drawCaptcha(code) {
      currentCaptcha = code;
      const canvas = document.getElementById("captchaCanvas");
      if (!canvas) return;
      const ctx = canvas.getContext("2d");
      
      // Clear background
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      ctx.fillStyle = "rgba(10, 16, 28, 0.95)";
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      // Noise lines
      for (let i = 0; i < 4; i++) {
        ctx.strokeStyle = `rgba(0, 210, 255, ${0.18 + Math.random() * 0.28})`;
        ctx.lineWidth = 1.5;
        ctx.beginPath();
        ctx.moveTo(Math.random() * canvas.width, Math.random() * canvas.height);
        ctx.bezierCurveTo(
          Math.random() * canvas.width, Math.random() * canvas.height,
          Math.random() * canvas.width, Math.random() * canvas.height,
          Math.random() * canvas.width, Math.random() * canvas.height
        );
        ctx.stroke();
      }

      // Noise dots
      for (let i = 0; i < 25; i++) {
        ctx.fillStyle = `rgba(255, 255, 255, ${0.12 + Math.random() * 0.25})`;
        ctx.beginPath();
        ctx.arc(Math.random() * canvas.width, Math.random() * canvas.height, 1.5, 0, Math.PI * 2);
        ctx.fill();
      }

      // Draw stylized text characters
      const fonts = ["bold 32px 'Plus Jakarta Sans'", "bold 32px monospace", "bold 32px sans-serif"];
      const colors = ["#00d2ff", "#38bdf8", "#60a5fa", "#e2e8f0"];

      for (let i = 0; i < code.length; i++) {
        ctx.save();
        const char = code[i];
        ctx.font = fonts[Math.floor(Math.random() * fonts.length)];
        ctx.fillStyle = colors[i % colors.length];

        const x = 24 + i * 38;
        const y = 46 + (Math.random() * 6 - 3);
        const angle = (Math.random() * 22 - 11) * (Math.PI / 180);

        ctx.translate(x, y);
        ctx.rotate(angle);
        ctx.fillText(char, 0, 0);
        ctx.restore();
      }
    }

    // Refresh captcha via fetch from server
    function refreshCaptcha() {
      fetch('get_captcha.php')
        .then(res => res.json())
        .then(data => {
          if (data && data.code) {
            drawCaptcha(data.code);
          }
        })
        .catch(() => {
          // Client-side fallback if get_captcha is unreachable
          const chars = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";
          let code = "";
          for (let i = 0; i < 4; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
          }
          drawCaptcha(code);
        });
    }

    // Fast demo account fill
    function fillDemoAccount(u, p) {
      document.getElementById('username').value = u;
      document.getElementById('password').value = p;
      document.getElementById('securityCode').value = currentCaptcha;
      showToast('info', 'Đã điền tài khoản mẫu: ' + u);
    }

    // Toggle Password Visibility
    function togglePasswordVisibility() {
      const passInput = document.getElementById("password");
      const eyeIcon = document.getElementById("eyeIcon");

      if (passInput.type === "password") {
        passInput.type = "text";
        eyeIcon.innerHTML = `
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268-2.943-9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
        `;
      } else {
        passInput.type = "password";
        eyeIcon.innerHTML = `
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
        `;
      }
    }

    // Toast Notification helper
    let toastTimeout;
    function showToast(type, message) {
      const toast = document.getElementById("toast");
      const toastIcon = document.getElementById("toast-icon");
      const toastMsg = document.getElementById("toast-message");

      clearTimeout(toastTimeout);

      if (type === "success") {
        toast.className = "flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold bg-emerald-950/85 border-emerald-500/30 text-emerald-300";
        toastIcon.innerHTML = `<svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
      } else if (type === "error") {
        toast.className = "flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold bg-rose-950/85 border-rose-500/30 text-rose-300";
        toastIcon.innerHTML = `<svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>`;
      } else {
        toast.className = "flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold bg-slate-900/90 border-cyan-500/30 text-cyan-300";
        toastIcon.innerHTML = `<svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
      }

      toastMsg.textContent = message;
      toast.classList.add("show");

      toastTimeout = setTimeout(() => {
        toast.classList.remove("show");
      }, 4000);
    }

    // Ajax Login Submission
    function handleAjaxLogin(e) {
      e.preventDefault();

      const user = document.getElementById("username").value.trim();
      const pass = document.getElementById("password").value;
      const secInput = document.getElementById("securityCode").value.trim().toUpperCase();
      const remember = document.getElementById("rememberMe").checked;

      if (!user || !pass || !secInput) {
        showToast("error", "Vui lòng nhập đầy đủ thông tin!");
        return;
      }

      const btn = document.getElementById("btnLogin");
      const btnText = document.getElementById("btnText");
      const btnSpinner = document.getElementById("btnSpinner");
      const btnArrow = document.getElementById("btnArrow");

      btn.disabled = true;
      btn.classList.add("opacity-80", "cursor-not-allowed");
      btnText.textContent = "Đang xác thực MySQL...";
      btnSpinner.classList.remove("hidden");
      btnArrow.classList.add("hidden");

      const formData = new FormData();
      formData.append('username', user);
      formData.append('password', pass);
      formData.append('securityCode', secInput);
      if (remember) formData.append('rememberMe', '1');

      fetch('index.php', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.classList.remove("opacity-80", "cursor-not-allowed");
        btnText.textContent = "Log In";
        btnSpinner.classList.add("hidden");
        btnArrow.classList.remove("hidden");

        if (data.newCaptcha) {
          drawCaptcha(data.newCaptcha);
        }

        if (data.success) {
          showToast('success', data.message);
          setTimeout(() => {
            window.location.href = data.redirect || 'dashboard.php';
          }, 800);
        } else {
          showToast('error', data.message);
          document.getElementById('securityCode').value = '';
          document.getElementById('securityCode').focus();
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.classList.remove("opacity-80", "cursor-not-allowed");
        btnText.textContent = "Log In";
        btnSpinner.classList.add("hidden");
        btnArrow.classList.remove("hidden");
        // Fallback to normal form submit
        document.getElementById('loginForm').submit();
      });
    }

    // Initialize on page load
    window.addEventListener("DOMContentLoaded", () => {
      drawCaptcha(currentCaptcha);
      
      <?php if (!empty($message)): ?>
        showToast("<?= $message_type ?>", "<?= addslashes($message) ?>");
      <?php endif; ?>
    });
  </script>
</body>
</html>
