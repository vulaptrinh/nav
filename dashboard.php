<?php
require_once __DIR__ . '/auth/auth_check.php';

$user_id = $_SESSION['user_id'];
$fullname = $_SESSION['fullname'] ?? 'Người dùng';
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Fetch Totals
$incomeStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM `transactions` WHERE `user_id` = ? AND `type` = 'income'");
$incomeStmt->execute([$user_id]);
$totalIncome = floatval($incomeStmt->fetchColumn());

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM `transactions` WHERE `user_id` = ? AND `type` = 'expense'");
$expenseStmt->execute([$user_id]);
$totalExpense = floatval($expenseStmt->fetchColumn());

$balance = $totalIncome - $totalExpense;

// Fetch Recent Transactions
$transStmt = $pdo->prepare("SELECT * FROM `transactions` WHERE `user_id` = ? ORDER BY `transaction_date` DESC, `id` DESC LIMIT 100");
$transStmt->execute([$user_id]);
$transactions = $transStmt->fetchAll();

// Fetch Main Categories (parent_id IS NULL)
$catStmt = $pdo->prepare("SELECT * FROM `categories` WHERE (user_id = ? OR user_id IS NULL) AND parent_id IS NULL ORDER BY user_id DESC, type ASC, name ASC");
$catStmt->execute([$user_id]);
$categoriesList = $catStmt->fetchAll();

// Fetch Subcategories (parent_id IS NOT NULL)
$subCatStmt = $pdo->prepare("SELECT * FROM `categories` WHERE (user_id = ? OR user_id IS NULL) AND parent_id IS NOT NULL ORDER BY name ASC");
$subCatStmt->execute([$user_id]);
$subcategoriesList = $subCatStmt->fetchAll();

// Map subcategories by parent_id and parent name
$subcategoriesByParent = [];
$subcategoriesByCategoryName = [];
$catById = [];
foreach ($categoriesList as $c) {
    $catById[$c['id']] = $c;
}
foreach ($subcategoriesList as $sc) {
    $subcategoriesByParent[$sc['parent_id']][] = $sc;
    if (isset($catById[$sc['parent_id']])) {
        $parentName = $catById[$sc['parent_id']]['name'];
        $subcategoriesByCategoryName[$parentName][] = $sc['name'];
    }
}

// Fetch Planned Expenses
$plannedStmt = $pdo->prepare("
    SELECT * FROM `planned_expenses` 
    WHERE `user_id` = ? 
    ORDER BY 
        CASE WHEN `status` = 'pending' THEN 0 ELSE 1 END,
        `expected_date` ASC, 
        `id` DESC
");
$plannedStmt->execute([$user_id]);
$plannedExpenses = $plannedStmt->fetchAll();

// Compute Planned Expenses stats
$totalPlannedExpected = 0;
$totalPlannedActual = 0;
$pendingPlannedCount = 0;
$completedPlannedCount = 0;
foreach ($plannedExpenses as $pe) {
    $totalPlannedExpected += floatval($pe['expected_amount']);
    if ($pe['status'] === 'completed') {
        $totalPlannedActual += floatval($pe['actual_amount'] ?? $pe['expected_amount']);
        $completedPlannedCount++;
    } else {
        $pendingPlannedCount++;
    }
}

// Category statistics
$incomeByCat = [];
$expenseByCat = [];
foreach ($transactions as $t) {
    if ($t['type'] === 'income') {
        $incomeByCat[$t['category']] = ($incomeByCat[$t['category']] ?? 0) + floatval($t['amount']);
    } else {
        $expenseByCat[$t['category']] = ($expenseByCat[$t['category']] ?? 0) + floatval($t['amount']);
    }
}
arsort($incomeByCat);
arsort($expenseByCat);

function formatRepeatBadge($type, $interval = 1, $dayOfMonth = null) {
    switch ($type) {
        case 'monthly':
            return 'Hằng tháng' . ($dayOfMonth ? " (ngày " . sprintf('%02d', $dayOfMonth) . ")" : "");
        case 'weekly':
            return 'Hằng tuần';
        case 'daily':
            return 'Hằng ngày';
        case 'yearly':
            return 'Hằng năm';
        case 'custom_days':
            return ($interval ?? 1) . ' ngày / lần';
        case 'once':
        default:
            return 'Một lần';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CashTrack - Quản lý tài chính thông minh</title>
  
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
              blue: '#0066ff',
              navy: '#0b101d',
              dark: '#060913',
              sidebar: '#090e1c',
              card: '#0d1322',
              input: '#121826',
              border: '#1c2538'
            }
          }
        }
      }
    }
  </script>
  
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #060913;
      color: #f1f5f9;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Ambient background glows */
    .bg-mesh {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      background: 
        radial-gradient(circle at 10% 15%, rgba(0, 210, 255, 0.12) 0%, transparent 45%),
        radial-gradient(circle at 90% 85%, rgba(0, 102, 255, 0.14) 0%, transparent 50%),
        radial-gradient(circle at 50% 50%, rgba(12, 17, 30, 0.96) 0%, #060913 100%);
    }

    .bg-grid-lines {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      opacity: 0.03;
      background-image: 
        linear-gradient(to right, #ffffff 1px, transparent 1px),
        linear-gradient(to bottom, #ffffff 1px, transparent 1px);
      background-size: 40px 40px;
    }

    .card-glass {
      background: rgba(13, 19, 34, 0.92);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.08);
      box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
      border-radius: 24px;
    }

    .btn-gradient {
      background: linear-gradient(135deg, #00d2ff 0%, #0066ff 100%);
      box-shadow: 0 6px 20px -2px rgba(0, 114, 255, 0.45);
      transition: all 0.25s ease;
    }
    .btn-gradient:hover {
      box-shadow: 0 8px 25px rgba(0, 114, 255, 0.65);
      transform: translateY(-1px);
      filter: brightness(1.05);
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    ::-webkit-scrollbar-track {
      background: rgba(13, 19, 34, 0.5);
    }
    ::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.15);
      border-radius: 4px;
    }

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

    /* ========================================================= */
    /* Sidebar Collapsed State (Desktop >= 1024px)               */
    /* ========================================================= */
    #sidebar {
      transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    #mainContent {
      transition: padding-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @media (min-width: 1024px) {
      body.sidebar-collapsed #sidebar {
        width: 5rem !important; /* 80px */
      }
      body.sidebar-collapsed #mainContent {
        padding-left: 5rem !important;
      }
      body.sidebar-collapsed .sidebar-text {
        display: none !important;
      }
      body.sidebar-collapsed .sidebar-section-title {
        display: none !important;
      }
      body.sidebar-collapsed .sidebar-header-box {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      body.sidebar-collapsed .sidebar-nav-item {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
      }
      body.sidebar-collapsed .sidebar-nav-icon {
        margin: 0 !important;
      }
      body.sidebar-collapsed .sidebar-quick-btn {
        width: 2.75rem !important;
        height: 2.75rem !important;
        padding: 0 !important;
        border-radius: 1rem !important;
        margin-left: auto !important;
        margin-right: auto !important;
        justify-content: center !important;
      }
      body.sidebar-collapsed .sidebar-status-box {
        padding: 0.5rem !important;
        width: 2.75rem !important;
        height: 2.75rem !important;
        margin-left: auto !important;
        margin-right: auto !important;
        border-radius: 1rem !important;
        justify-content: center !important;
      }
      body.sidebar-collapsed .sidebar-user-box {
        flex-direction: column !important;
        gap: 0.6rem !important;
        align-items: center !important;
        justify-content: center !important;
      }
      body.sidebar-collapsed #sidebarCollapseIcon {
        transform: rotate(180deg) !important;
      }
      body.sidebar-collapsed #topNavCollapseIcon {
        transform: rotate(180deg) !important;
      }
    }
  </style>
</head>
<body class="relative">

  <!-- Immediate script to apply saved collapsed state without flash -->
  <script>
    if (localStorage.getItem('cashtrack_sidebar_collapsed') === '1' && window.innerWidth >= 1024) {
      document.body.classList.add('sidebar-collapsed');
    }
  </script>

  <div class="bg-mesh"></div>
  <div class="bg-grid-lines"></div>

  <!-- Toast Notification -->
  <div id="toast" class="flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold">
    <div id="toast-icon"></div>
    <div id="toast-message"></div>
  </div>

  <!-- Mobile Sidebar Backdrop -->
  <div id="sidebarBackdrop" onclick="toggleSidebar(false)" class="fixed inset-0 z-30 bg-black/70 backdrop-blur-sm hidden lg:hidden transition-opacity"></div>

  <!-- ========================================================= -->
  <!-- VERTICAL SIDEBAR MENU (Thanh menu dọc bên trái)           -->
  <!-- ========================================================= -->
  <aside id="sidebar" class="fixed top-0 bottom-0 left-0 z-40 w-72 h-screen bg-[#080d1b]/95 backdrop-blur-2xl border-r border-white/[0.08] flex flex-col justify-between -translate-x-full lg:translate-x-0 shadow-2xl">
    
    <!-- Floating Desktop Collapse / Expand Button on right border -->
    <button 
      type="button" 
      onclick="toggleSidebarCollapse()" 
      id="sidebarCollapseBtn" 
      class="hidden lg:flex absolute -right-3.5 top-7 w-7 h-7 rounded-full bg-[#0d1322] hover:bg-cyan-500/20 border border-white/20 hover:border-cyan-400/50 text-slate-400 hover:text-cyan-300 items-center justify-center shadow-xl transition-all z-50 group cursor-pointer"
      title="Thu gọn / Mở rộng menu (Ctrl + B)"
    >
      <svg id="sidebarCollapseIcon" class="w-3.5 h-3.5 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
      </svg>
    </button>

    <!-- Top Section: Brand & Navigation -->
    <div class="p-4 lg:p-5 flex flex-col h-full overflow-y-auto">
      
      <!-- Brand Logo Header -->
      <div class="sidebar-header-box flex items-center justify-between pb-6 mb-4 border-b border-white/[0.07]">
        <div class="flex items-center gap-3 overflow-hidden">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-cyan-500/20 to-blue-500/30 border border-cyan-400/40 flex items-center justify-center shadow-[0_0_18px_rgba(0,210,255,0.3)] shrink-0">
            <svg class="w-5 h-5 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
              <circle cx="12" cy="13" r="3" stroke-width="2" />
            </svg>
          </div>
          <div class="sidebar-text truncate">
            <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-100 to-cyan-300 bg-clip-text text-transparent">
              CashTrack
            </span>
            <div class="text-[10px] text-cyan-400 font-mono tracking-wider">FINANCE & CASHFLOW</div>
          </div>
        </div>

        <!-- Mobile Close Button -->
        <button 
          type="button" 
          onclick="toggleSidebar(false)" 
          class="lg:hidden w-8 h-8 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] text-slate-400 hover:text-white flex items-center justify-center transition"
        >
          ✕
        </button>
      </div>

      <!-- Quick Action Button -->
      <div class="mb-5">
        <button 
          type="button" 
          onclick="openAddModal()" 
          class="sidebar-quick-btn btn-gradient w-full py-3 px-3.5 rounded-2xl text-xs font-bold text-white flex items-center justify-center gap-2 shadow-lg group transition-all"
          title="Thêm giao dịch mới"
        >
          <svg class="w-4 h-4 transition-transform group-hover:rotate-90 duration-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span class="sidebar-text whitespace-nowrap">Thêm giao dịch mới</span>
        </button>
      </div>

      <!-- Nav Section: Menu chính -->
      <div class="space-y-1">
        <div class="sidebar-section-title text-[10px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Menu chính</div>
        
        <!-- Link 1: Tổng quan (Active) -->
        <a 
          href="dashboard.php" 
          class="sidebar-nav-item flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-bold bg-cyan-500/15 text-cyan-300 border border-cyan-400/25 shadow-[0_0_15px_rgba(0,210,255,0.1)] transition-all"
          title="Tổng quan"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-cyan-400/20 text-cyan-300 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
          </div>
          <span class="sidebar-text">Tổng quan</span>
          <span class="sidebar-text ml-auto w-2 h-2 rounded-full bg-cyan-400 animate-pulse"></span>
        </a>

        <!-- Link 2: Lịch sử Giao dịch -->
        <a 
          href="#transactionSection" 
          onclick="scrollToTransactions()"
          class="sidebar-nav-item flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-medium text-slate-300 hover:text-white hover:bg-white/[0.04] transition-all"
          title="Lịch sử giao dịch"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-white/[0.04] text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
          </div>
          <span class="sidebar-text">Lịch sử giao dịch</span>
          <span class="sidebar-text ml-auto text-[11px] font-mono px-2 py-0.5 rounded-full bg-white/[0.05] text-slate-400">
            <?= count($transactions) ?>
          </span>
        </a>

        <!-- Link 3: Khoản chi dự kiến (New Feature) -->
        <a 
          href="#plannedExpenseSection" 
          onclick="scrollToPlannedExpenses()"
          class="sidebar-nav-item flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-medium text-slate-300 hover:text-white hover:bg-white/[0.04] transition-all"
          title="Khoản chi dự kiến & định kỳ"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center shrink-0 border border-amber-500/20">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <span class="sidebar-text">Chi dự kiến</span>
          <span class="sidebar-text ml-auto text-[11px] font-mono px-2 py-0.5 rounded-full <?= $pendingPlannedCount > 0 ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30 font-bold' : 'bg-white/[0.05] text-slate-400' ?>">
            <?= $pendingPlannedCount ?> chờ
          </span>
        </a>

        <!-- Link 4: Quản lý Danh mục (New Feature!) -->
        <button 
          type="button" 
          onclick="openCategoryManagerModal()" 
          class="sidebar-nav-item w-full flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-medium text-slate-300 hover:text-white hover:bg-white/[0.04] transition-all"
          title="Danh mục thu chi"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-white/[0.04] text-purple-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
          </div>
          <span class="sidebar-text text-left">Danh mục thu chi</span>
          <span class="sidebar-text ml-auto text-[11px] font-mono px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-300 border border-purple-500/20">
            <?= count($categoriesList) ?>
          </span>
        </button>

        <!-- Link 4: Báo cáo & Thống kê -->
        <button 
          type="button" 
          onclick="openAnalyticsModal()" 
          class="sidebar-nav-item w-full flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-medium text-slate-300 hover:text-white hover:bg-white/[0.04] transition-all"
          title="Báo cáo & Thống kê"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-white/[0.04] text-emerald-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          </div>
          <span class="sidebar-text">Báo cáo & Thống kê</span>
          <span class="sidebar-text ml-auto text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-400 font-semibold">Mới</span>
        </button>
      </div>

      <!-- Nav Section: Quản trị & Hệ thống -->
      <div class="mt-5 space-y-1">
        <div class="sidebar-section-title text-[10px] font-bold text-slate-500 uppercase tracking-wider px-3 mb-2">Hệ thống</div>

        <button 
          type="button" 
          onclick="openCategoryManagerModal()" 
          class="sidebar-nav-item w-full flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-medium text-slate-400 hover:text-white hover:bg-white/[0.04] transition-all"
          title="Cấu hình danh mục"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-white/[0.04] text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
            </svg>
          </div>
          <span class="sidebar-text">Cấu hình danh mục</span>
        </button>

        <a 
          href="index.php" 
          class="sidebar-nav-item flex items-center gap-3 px-3.5 py-2.5 rounded-2xl text-xs font-medium text-slate-400 hover:text-white hover:bg-white/[0.04] transition-all"
          title="Trang Đăng nhập"
        >
          <div class="sidebar-nav-icon w-7 h-7 rounded-xl bg-white/[0.04] text-slate-400 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <span class="sidebar-text">Trang Đăng nhập</span>
        </a>
      </div>

      <!-- System Live Status Box -->
      <div class="mt-auto pt-6">
        <div class="sidebar-status-box p-3 rounded-2xl bg-white/[0.03] border border-white/[0.06] text-xs flex items-center justify-center transition-all" title="MariaDB Online - Database: cashtrack">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping shrink-0"></span>
            <div class="sidebar-text overflow-hidden">
              <div class="text-[11px] font-bold text-emerald-400">MariaDB Online</div>
              <div class="text-[10px] text-slate-400 truncate">
                DB: <span class="text-cyan-400 font-mono">cashtrack</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Bottom Section: User Profile & Logout -->
    <div class="p-3 lg:p-4 border-t border-white/[0.07] bg-white/[0.01]">
      <div class="sidebar-user-box flex items-center justify-between">
        <div class="flex items-center gap-2.5 overflow-hidden" title="<?= htmlspecialchars($fullname) ?> (<?= htmlspecialchars($role) ?>)">
          <div class="w-9 h-9 rounded-2xl bg-gradient-to-tr from-cyan-400 to-blue-600 flex items-center justify-center text-xs font-bold text-white shadow shrink-0">
            <?= strtoupper(substr($fullname, 0, 1)) ?>
          </div>
          <div class="sidebar-text truncate">
            <div class="text-xs font-bold text-white leading-tight truncate"><?= htmlspecialchars($fullname) ?></div>
            <div class="text-[10px] text-cyan-400/90 font-medium capitalize truncate"><?= htmlspecialchars($role) ?> • <?= htmlspecialchars($username) ?></div>
          </div>
        </div>

        <a 
          href="logout.php" 
          class="sidebar-logout-btn p-2 rounded-xl bg-white/[0.04] hover:bg-rose-500/15 border border-white/10 hover:border-rose-500/30 text-slate-400 hover:text-rose-400 transition shrink-0" 
          title="Đăng xuất khỏi hệ thống"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </a>
      </div>
    </div>

  </aside>

  <!-- ========================================================= -->
  <!-- MAIN CONTENT AREA (Khu vực nội dung bên phải Sidebar)      -->
  <!-- ========================================================= -->
  <div id="mainContent" class="lg:pl-72 flex flex-col min-h-screen">

    <!-- Top Navigation Bar -->
    <header class="relative z-20 border-b border-white/[0.07] bg-slate-950/60 backdrop-blur-xl sticky top-0">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        
        <!-- Left: Mobile Menu Toggle, Desktop Collapse Toggle & Title -->
        <div class="flex items-center gap-3">
          <!-- Mobile Hamburger Toggle Button -->
          <button 
            type="button" 
            onclick="toggleSidebar(true)" 
            class="lg:hidden p-2.5 rounded-2xl bg-white/[0.05] hover:bg-white/[0.1] border border-white/10 text-slate-300 hover:text-white transition"
            title="Mở menu dọc"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>

          <!-- Desktop Sidebar Collapse Toggle Button in Header -->
          <button 
            type="button" 
            onclick="toggleSidebarCollapse()" 
            class="hidden lg:flex p-2.5 rounded-2xl bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-slate-400 hover:text-cyan-300 transition items-center justify-center cursor-pointer"
            title="Thu gọn / Mở rộng menu (Ctrl + B)"
          >
            <svg id="topNavCollapseIcon" class="w-4 h-4 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16" />
            </svg>
          </button>

          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-base sm:text-lg font-bold text-white tracking-tight">
                Tổng quan tài chính
              </h1>
              <span class="text-xs bg-cyan-500/10 text-cyan-400 px-2.5 py-0.5 rounded-full font-semibold border border-cyan-400/20 hidden sm:inline-block">
                CashTrack v2.0
              </span>
            </div>
            <p class="text-[11px] text-slate-400 hidden sm:block">
              Hệ thống theo dõi và phân bổ dòng tiền cá nhân & doanh nghiệp
            </p>
          </div>
        </div>

        <!-- Right User & Action Controls -->
        <div class="flex items-center gap-3">
          
          <!-- Category Manager Quick Button -->
          <button 
            type="button" 
            onclick="openCategoryManagerModal()" 
            class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/[0.04] hover:bg-white/[0.08] border border-white/10 text-xs text-slate-300 transition cursor-pointer"
            title="Quản lý danh mục thu chi"
          >
            <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <span>Danh mục</span>
            <span class="px-1.5 py-0.2 rounded-full bg-purple-500/20 text-purple-300 text-[10px] font-mono font-bold"><?= count($categoriesList) ?></span>
          </button>

          <!-- Add Planned Expense Button -->
          <button 
            type="button" 
            onclick="openAddPlannedModal()" 
            class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-500/15 hover:bg-amber-500/25 border border-amber-400/30 text-xs font-bold text-amber-300 hover:text-white transition cursor-pointer shadow"
            title="Thêm khoản chi dự kiến"
          >
            <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>Chi dự kiến</span>
            <?php if ($pendingPlannedCount > 0): ?>
              <span class="px-1.5 py-0.2 rounded-full bg-amber-500/30 text-amber-200 text-[10px] font-mono font-bold"><?= $pendingPlannedCount ?></span>
            <?php endif; ?>
          </button>

          <!-- Add Transaction Button -->
          <button 
            type="button" 
            onclick="openAddModal()" 
            class="btn-gradient px-4 py-2 rounded-full text-xs font-bold text-white flex items-center gap-1.5 shadow-lg cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            <span class="hidden sm:inline">Thêm thu chi</span>
          </button>

          <!-- User Info Badge (Desktop) -->
          <div class="hidden sm:flex items-center gap-2.5 px-3 py-1 rounded-full bg-white/[0.04] border border-white/10">
            <div class="w-7 h-7 rounded-full bg-gradient-to-tr from-cyan-400 to-blue-600 flex items-center justify-center text-xs font-bold text-white shadow">
              <?= strtoupper(substr($fullname, 0, 1)) ?>
            </div>
            <div class="text-left">
              <div class="text-xs font-bold text-white leading-tight truncate max-w-[120px]"><?= htmlspecialchars($fullname) ?></div>
            </div>
          </div>

          <!-- Logout Button -->
          <a 
            href="logout.php" 
            class="p-2 rounded-full bg-white/[0.04] hover:bg-rose-500/10 border border-white/10 hover:border-rose-500/30 text-slate-300 hover:text-rose-400 transition-colors" 
            title="Đăng xuất"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
          </a>

        </div>

      </div>
    </header>

    <!-- Main Content Body -->
    <main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 w-full flex-1">
      
      <!-- Welcome Banner -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center gap-2">
            Tổng quan tài chính <span class="text-xl">📊</span>
          </h2>
          <p class="text-xs sm:text-sm text-slate-400 mt-1">
            Dữ liệu kết nối trực tiếp với MariaDB Database <code class="text-cyan-400 font-mono">cashtrack</code>
          </p>
        </div>

        <div class="flex items-center gap-2 text-xs text-slate-400 bg-white/[0.03] px-3.5 py-2 rounded-2xl border border-white/10 w-fit">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>Hệ thống trực tuyến</span>
          <span class="text-slate-600">&bull;</span>
          <span class="font-mono text-slate-300"><?= date('d/m/Y') ?></span>
        </div>
      </div>

      <!-- Stat Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        
        <!-- Card 1: Balance -->
        <div class="card-glass p-6 relative overflow-hidden group hover:border-cyan-500/30 transition-all duration-300">
          <div class="absolute -right-4 -top-4 w-24 h-24 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition-all"></div>
          <div class="flex items-center justify-between text-xs text-slate-400 font-medium mb-3">
            <span>Số dư khả dụng</span>
            <div class="w-8 h-8 rounded-xl bg-cyan-500/10 border border-cyan-400/20 flex items-center justify-center text-cyan-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white font-mono">
            <?= ($balance < 0 ? '-' : '') . number_format(abs($balance), 0, '.', ',') ?> <span class="text-xs font-normal text-slate-400">₫</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-2 flex items-center gap-1">
            <span class="<?= $balance >= 0 ? 'text-emerald-400' : 'text-rose-400' ?> font-semibold">
              <?= $balance >= 0 ? '▲ Cân bằng tốt' : '▼ Cảnh báo bội chi' ?>
            </span>
          </div>
        </div>

        <!-- Card 2: Total Income -->
        <div class="card-glass p-6 relative overflow-hidden group hover:border-emerald-500/30 transition-all duration-300">
          <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all"></div>
          <div class="flex items-center justify-between text-xs text-slate-400 font-medium mb-3">
            <span>Tổng Thu (Income)</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-400/20 flex items-center justify-center text-emerald-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" />
              </svg>
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-emerald-400 font-mono">
            +<?= number_format($totalIncome, 0, '.', ',') ?> <span class="text-xs font-normal text-emerald-300/80">₫</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-2">Dòng tiền ghi nhận vào tài khoản</div>
        </div>

        <!-- Card 3: Total Expense -->
        <div class="card-glass p-6 relative overflow-hidden group hover:border-rose-500/30 transition-all duration-300">
          <div class="absolute -right-4 -top-4 w-24 h-24 bg-rose-500/10 rounded-full blur-2xl group-hover:bg-rose-500/20 transition-all"></div>
          <div class="flex items-center justify-between text-xs text-slate-400 font-medium mb-3">
            <span>Tổng Chi (Expense)</span>
            <div class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-400/20 flex items-center justify-center text-rose-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6" />
              </svg>
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-rose-400 font-mono">
            -<?= number_format($totalExpense, 0, '.', ',') ?> <span class="text-xs font-normal text-rose-300/80">₫</span>
          </div>
          <div class="text-[11px] text-slate-400 mt-2">Khoản chi tiêu & vận hành</div>
        </div>

        <!-- Card 4: Transaction Count -->
        <div class="card-glass p-6 relative overflow-hidden group hover:border-purple-500/30 transition-all duration-300">
          <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full blur-2xl group-hover:bg-purple-500/20 transition-all"></div>
          <div class="flex items-center justify-between text-xs text-slate-400 font-medium mb-3">
            <span>Tổng số giao dịch</span>
            <div class="w-8 h-8 rounded-xl bg-purple-500/10 border border-purple-400/20 flex items-center justify-center text-purple-400">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
          </div>
          <div class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white font-mono">
            <?= count($transactions) ?> <span class="text-xs font-normal text-slate-400">bản ghi</span>
          </div>
          <div class="text-[11px] text-purple-400/90 mt-2">Đồng bộ MariaDB thời gian thực</div>
        </div>

      </div>

      <!-- ========================================================= -->
      <!-- SECTION: KHOẢN CHI DỰ KIẾN & ĐỊNH KỲ                       -->
      <!-- ========================================================= -->
      <div id="plannedExpenseSection" class="card-glass p-6 sm:p-7 scroll-mt-24">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
                Khoản chi dự kiến & Định kỳ 🗓️
                <span class="text-xs bg-amber-500/10 text-amber-400 px-2.5 py-0.5 rounded-full font-mono font-medium border border-amber-400/20">
                  <?= count($plannedExpenses) ?> khoản
                </span>
              </h3>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">Lên kế hoạch chi tiêu theo chu kỳ (Hằng ngày, Hằng tuần, Hằng tháng hoặc X ngày/lần) & theo dõi thực tế</p>
          </div>

          <!-- Actions & Filter -->
          <div class="flex items-center gap-2.5 flex-wrap">
            <div class="flex items-center gap-1 p-1 rounded-2xl bg-white/[0.04] border border-white/10">
              <button onclick="filterPlannedExpenses('all')" class="planned-filter-btn active px-3 py-1 rounded-xl text-xs font-semibold text-white bg-amber-500/20 text-amber-300 cursor-pointer">Tất cả</button>
              <button onclick="filterPlannedExpenses('pending')" class="planned-filter-btn px-3 py-1 rounded-xl text-xs font-medium text-slate-400 hover:text-white cursor-pointer">Chờ chi (<?= $pendingPlannedCount ?>)</button>
              <button onclick="filterPlannedExpenses('completed')" class="planned-filter-btn px-3 py-1 rounded-xl text-xs font-medium text-slate-400 hover:text-white cursor-pointer">Đã chi (<?= $completedPlannedCount ?>)</button>
            </div>

            <button 
              type="button" 
              onclick="openAddPlannedModal()" 
              class="px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:brightness-110 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-lg shadow-orange-500/20 active:scale-95 cursor-pointer"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
              <span>+ Thêm dự kiến</span>
            </button>
          </div>
        </div>

        <!-- Summary Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6 p-4 rounded-2xl bg-white/[0.02] border border-white/[0.06]">
          <div>
            <div class="text-[11px] text-slate-400 mb-0.5">Tổng dự kiến</div>
            <div class="text-base sm:text-lg font-extrabold text-amber-400 font-mono">
              <?= number_format($totalPlannedExpected, 0, '.', ',') ?> <span class="text-[10px] font-normal text-slate-400">₫</span>
            </div>
          </div>
          <div>
            <div class="text-[11px] text-slate-400 mb-0.5">Đã thực chi</div>
            <div class="text-base sm:text-lg font-extrabold text-emerald-400 font-mono">
              <?= number_format($totalPlannedActual, 0, '.', ',') ?> <span class="text-[10px] font-normal text-slate-400">₫</span>
            </div>
          </div>
          <div>
            <div class="text-[11px] text-slate-400 mb-0.5">Còn chờ chi</div>
            <div class="text-base sm:text-lg font-extrabold text-cyan-300 font-mono">
              <?= number_format(max(0, $totalPlannedExpected - $totalPlannedActual), 0, '.', ',') ?> <span class="text-[10px] font-normal text-slate-400">₫</span>
            </div>
          </div>
          <div>
            <div class="text-[11px] text-slate-400 mb-0.5">Chênh lệch chi phí</div>
            <?php 
              $diff = $totalPlannedActual - $totalPlannedExpected;
            ?>
            <div class="text-base sm:text-lg font-extrabold font-mono <?= $diff > 0 ? 'text-rose-400' : ($diff < 0 ? 'text-emerald-400' : 'text-slate-300') ?>">
              <?= ($diff > 0 ? '+' : '') . number_format($diff, 0, '.', ',') ?> <span class="text-[10px] font-normal text-slate-400">₫</span>
            </div>
          </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-white/[0.08] text-slate-400 uppercase tracking-wider font-semibold">
                <th class="pb-3.5 pl-3">Trạng thái</th>
                <th class="pb-3.5">Danh mục</th>
                <th class="pb-3.5">Nội dung</th>
                <th class="pb-3.5">Chu kỳ</th>
                <th class="pb-3.5">Ngày dự kiến</th>
                <th class="pb-3.5 text-right">Số tiền dự kiến</th>
                <th class="pb-3.5 text-right">Thực tế chi</th>
                <th class="pb-3.5 pr-3 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody id="plannedTableBody" class="divide-y divide-white/[0.04]">
              <?php if (empty($plannedExpenses)): ?>
                <tr>
                  <td colspan="8" class="py-12 text-center text-slate-500">
                    Chưa có khoản chi dự kiến nào. Bấm nút "+ Thêm dự kiến" để tạo kế hoạch chi đầu tiên!
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($plannedExpenses as $pe): ?>
                  <?php 
                    $isCompleted = $pe['status'] === 'completed';
                    $expAmt = floatval($pe['expected_amount']);
                    $actAmt = floatval($pe['actual_amount'] ?? 0);
                    $var = $isCompleted ? ($actAmt - $expAmt) : 0;
                  ?>
                  <tr class="planned-row hover:bg-white/[0.03] transition-colors" data-status="<?= $pe['status'] ?>" id="planned-row-<?= $pe['id'] ?>">
                    
                    <!-- Status Badge -->
                    <td class="py-3.5 pl-3">
                      <?php if ($isCompleted): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                          Đã chi
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2.5"/><path stroke-linecap="round" d="M12 6v6l4 2"/></svg>
                          Chờ chi
                        </span>
                      <?php endif; ?>
                    </td>

                    <!-- Category & Subcategory -->
                    <td class="py-3.5 font-semibold text-white">
                      <div class="flex items-center gap-1.5 flex-wrap">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                        <span><?= htmlspecialchars($pe['category']) ?></span>
                        <?php if (!empty($pe['subcategory'])): ?>
                          <span class="text-slate-500 text-[10px]">›</span>
                          <span class="text-cyan-300 text-[11px] font-normal px-2 py-0.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20 font-medium">
                            <?= htmlspecialchars($pe['subcategory']) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </td>

                    <!-- Title / Note -->
                    <td class="py-3.5 text-slate-300 max-w-xs truncate">
                      <?= htmlspecialchars($pe['title'] ?? '-') ?>
                    </td>

                    <!-- Repeat Badge -->
                    <td class="py-3.5">
                      <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-medium bg-purple-500/10 text-purple-300 border border-purple-500/20">
                        <?= formatRepeatBadge($pe['repeat_type'], $pe['repeat_interval'], $pe['day_of_month']) ?>
                      </span>
                    </td>

                    <!-- Expected Date -->
                    <td class="py-3.5 font-mono text-[11px] text-slate-300">
                      <?= date('d/m/Y', strtotime($pe['expected_date'])) ?>
                    </td>

                    <!-- Expected Amount -->
                    <td class="py-3.5 text-right font-mono font-bold text-sm text-amber-300">
                      <?= number_format($expAmt, 0, '.', ',') ?> ₫
                    </td>

                    <!-- Actual Result -->
                    <td class="py-3.5 text-right font-mono text-xs">
                      <?php if ($isCompleted): ?>
                        <div class="font-bold text-emerald-400 text-sm">
                          <?= number_format($actAmt, 0, '.', ',') ?> ₫
                        </div>
                        <div class="text-[10px] text-slate-400">
                          Ngày: <?= date('d/m/Y', strtotime($pe['actual_date'])) ?>
                          <?php if ($var != 0): ?>
                            <span class="<?= $var > 0 ? 'text-rose-400' : 'text-emerald-400' ?> font-semibold">
                              (<?= ($var > 0 ? '+' : '') . number_format($var, 0, '.', ',') ?> ₫)
                            </span>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <button 
                          type="button" 
                          onclick="openActualExpenseModal(<?= htmlspecialchars(json_encode($pe), ENT_QUOTES, 'UTF-8') ?>)"
                          class="px-2.5 py-1 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 hover:text-white font-bold text-[11px] transition shadow-sm cursor-pointer"
                        >
                          Xác nhận chi
                        </button>
                      <?php endif; ?>
                    </td>

                    <!-- Actions -->
                    <td class="py-3.5 pr-3 text-right">
                      <div class="flex items-center justify-end gap-1">
                        <?php if (!$isCompleted): ?>
                          <button 
                            type="button" 
                            onclick="openActualExpenseModal(<?= htmlspecialchars(json_encode($pe), ENT_QUOTES, 'UTF-8') ?>)"
                            class="p-1.5 rounded-lg text-slate-500 hover:text-emerald-400 hover:bg-emerald-500/10 transition-colors cursor-pointer"
                            title="Xác nhận chi thực tế"
                          >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                          </button>
                        <?php endif; ?>
                        <button 
                          type="button" 
                          onclick="deletePlannedExpense(<?= $pe['id'] ?>)" 
                          class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
                          title="Xóa khoản dự kiến này"
                        >
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </td>

                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Recent Transactions Table Section -->
      <div id="transactionSection" class="card-glass p-6 sm:p-7 scroll-mt-24">
        
        <!-- Table Header & Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h3 class="text-lg font-bold text-white tracking-tight flex items-center gap-2">
              Danh sách giao dịch gần nhất
              <span class="text-xs bg-cyan-500/10 text-cyan-400 px-2.5 py-0.5 rounded-full font-mono font-medium border border-cyan-400/20">
                <?= count($transactions) ?>
              </span>
            </h3>
            <p class="text-xs text-slate-400 mt-0.5">Nhấp vào bất kỳ dòng nào để xem chi tiết giao dịch</p>
          </div>

          <!-- Filter tabs -->
          <div class="flex items-center gap-1.5 p-1 rounded-2xl bg-white/[0.04] border border-white/10 w-fit">
            <button onclick="filterTable('all')" class="filter-btn active px-3 py-1 rounded-xl text-xs font-semibold text-white bg-cyan-500/20 text-cyan-300">Tất cả</button>
            <button onclick="filterTable('income')" class="filter-btn px-3 py-1 rounded-xl text-xs font-medium text-slate-400 hover:text-white">Khoản Thu</button>
            <button onclick="filterTable('expense')" class="filter-btn px-3 py-1 rounded-xl text-xs font-medium text-slate-400 hover:text-white">Khoản Chi</button>
          </div>
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-white/[0.08] text-slate-400 uppercase tracking-wider font-semibold">
                <th class="pb-3.5 pl-3">Loại</th>
                <th class="pb-3.5">Danh mục</th>
                <th class="pb-3.5">Ghi chú</th>
                <th class="pb-3.5">Ngày</th>
                <th class="pb-3.5 text-right">Số tiền (VND)</th>
                <th class="pb-3.5 pr-3 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody id="transactionTableBody" class="divide-y divide-white/[0.04]">
              <?php if (empty($transactions)): ?>
                <tr>
                  <td colspan="6" class="py-12 text-center text-slate-500">
                    Chưa có giao dịch nào. Bấm nút "Thêm thu chi" để tạo giao dịch đầu tiên!
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                  <tr 
                    class="transaction-row hover:bg-white/[0.04] transition-colors cursor-pointer group" 
                    data-type="<?= $t['type'] ?>" 
                    id="row-<?= $t['id'] ?>"
                    onclick="viewTransactionDetails(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)"
                    title="Nhấp để xem chi tiết giao dịch"
                  >
                    <!-- Type Badge -->
                    <td class="py-3.5 pl-3">
                      <?php if ($t['type'] === 'income'): ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                          Thu
                        </span>
                      <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
                          Chi
                        </span>
                      <?php endif; ?>
                    </td>
                    
                    <!-- Category -->
                    <td class="py-3.5 font-semibold text-white">
                      <div class="inline-flex items-center gap-1.5 flex-wrap group-hover:text-cyan-300 transition-colors">
                        <span class="w-1.5 h-1.5 rounded-full <?= $t['type'] === 'income' ? 'bg-emerald-400' : 'bg-rose-400' ?>"></span>
                        <span><?= htmlspecialchars($t['category']) ?></span>
                        <?php if (!empty($t['subcategory'])): ?>
                          <span class="text-slate-500 text-[10px]">›</span>
                          <span class="text-cyan-300 text-[11px] font-normal px-2 py-0.5 rounded-lg bg-cyan-500/10 border border-cyan-500/20">
                            <?= htmlspecialchars($t['subcategory']) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </td>

                    <!-- Note -->
                    <td class="py-3.5 text-slate-400 max-w-xs truncate">
                      <?= htmlspecialchars($t['note'] ?? '-') ?>
                    </td>

                    <!-- Date -->
                    <td class="py-3.5 text-slate-400 font-mono text-[11px]">
                      <?= date('d/m/Y', strtotime($t['transaction_date'])) ?>
                    </td>

                    <!-- Amount -->
                    <td class="py-3.5 text-right font-mono font-bold text-sm <?= $t['type'] === 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                      <?= ($t['type'] === 'income' ? '+' : '-') . number_format($t['amount'], 0, '.', ',') ?> ₫
                    </td>

                    <!-- Actions -->
                    <td class="py-3.5 pr-3 text-right" onclick="event.stopPropagation()">
                      <div class="flex items-center justify-end gap-1">
                        <!-- View button -->
                        <button 
                          type="button" 
                          onclick="viewTransactionDetails(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" 
                          class="p-1.5 rounded-lg text-slate-500 hover:text-cyan-400 hover:bg-cyan-500/10 transition-colors cursor-pointer"
                          title="Xem chi tiết giao dịch"
                        >
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                          </svg>
                        </button>
                        <!-- Delete button -->
                        <button 
                          type="button" 
                          onclick="deleteTransaction(<?= $t['id'] ?>)" 
                          class="p-1.5 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
                          title="Xóa giao dịch"
                        >
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

    </main>

    <!-- Footer -->
    <footer class="border-t border-white/[0.05] py-5 px-6 text-center text-xs text-slate-500">
      CashTrack &bull; Quản trị dòng tiền thông minh &bull; MariaDB <?= date('Y') ?>
    </footer>

  </div>

  <!-- ========================================================= -->
  <!-- MODAL 1: ADD TRANSACTION (Thêm giao dịch mới)              -->
  <!-- ========================================================= -->
  <div id="addModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-md p-6 sm:p-7 relative border border-white/10 shadow-2xl animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeAddModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <h3 class="text-lg font-bold text-white mb-1">Thêm giao dịch mới</h3>
      <p class="text-xs text-slate-400 mb-5">Lưu thông tin thu nhập hoặc chi phí vào hệ thống</p>

      <form id="addTransactionForm" onsubmit="submitTransaction(event)" class="space-y-4">
        
        <!-- Transaction Type Toggle -->
        <div class="grid grid-cols-2 gap-2 p-1 rounded-2xl bg-white/[0.04] border border-white/10">
          <label class="cursor-pointer">
            <input type="radio" name="type" value="income" class="hidden peer" onchange="syncCategoryType(this.value)">
            <div class="text-center py-2.5 rounded-xl text-xs font-bold text-slate-400 peer-checked:bg-emerald-500/20 peer-checked:text-emerald-400 peer-checked:border peer-checked:border-emerald-500/30 transition">
              + Thu nhập
            </div>
          </label>
          <label class="cursor-pointer">
            <input type="radio" name="type" value="expense" checked class="hidden peer" onchange="syncCategoryType(this.value)">
            <div class="text-center py-2.5 rounded-xl text-xs font-bold text-slate-400 peer-checked:bg-rose-500/20 peer-checked:text-rose-400 peer-checked:border peer-checked:border-rose-500/30 transition">
              - Khoản chi
            </div>
          </label>
        </div>

        <!-- Category Field with "Thêm" button next to input -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-semibold text-slate-300">Danh mục</label>
            <span class="text-[10px] text-slate-400">Chọn gợi ý hoặc bấm nút Thêm</span>
          </div>

          <!-- Input + Add Button Container -->
          <div class="flex items-center gap-2">
            <div class="relative flex-1">
              <input 
                type="text" 
                id="categoryInput"
                name="category" 
                required 
                list="categoryList" 
                placeholder="VD: Lương, Bán hàng, Tiền phòng, Ăn uống..." 
                oninput="syncTxSubcategories(this.value)"
                class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none transition"
              />
            </div>
            
            <!-- Nút Thêm danh mục phía sau input -->
            <button 
              type="button" 
              onclick="openAddCategoryModal()" 
              class="px-3.5 py-2.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 border border-cyan-400/40 text-cyan-300 hover:text-white font-bold text-xs flex items-center gap-1.5 transition shrink-0 shadow-sm active:scale-95 cursor-pointer"
              title="Thêm danh mục mới vào danh sách"
            >
              <svg class="w-3.5 h-3.5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              <span>Thêm</span>
            </button>
          </div>

          <!-- Datalist gợi ý từ Database -->
          <datalist id="categoryList">
            <?php foreach ($categoriesList as $cat): ?>
              <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['type'] === 'income' ? ' [Thu nhập]' : ($cat['type'] === 'expense' ? ' [Khoản chi]' : ' [Dùng chung]')) ?></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <!-- Subcategory Field (Danh mục chi tiết / con) -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-semibold text-slate-300">Danh mục chi tiết / con (Tùy chọn)</label>
            <span class="text-[10px] text-cyan-400">Gợi ý theo danh mục cha hoặc tự gõ</span>
          </div>
          <div class="relative">
            <input 
              type="text" 
              id="subcategoryInput"
              name="subcategory" 
              list="subcategoryList" 
              placeholder="VD: Tiền học, Tiền phòng, Ăn vặt..." 
              class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none transition"
            />
          </div>
          <datalist id="subcategoryList">
            <?php foreach ($subcategoriesList as $sub): ?>
              <option value="<?= htmlspecialchars($sub['name']) ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>

        <!-- Amount -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Số tiền (VND)</label>
          <div class="relative">
            <input 
              type="text" 
              inputmode="numeric"
              id="amountInput"
              name="amount" 
              required 
              autocomplete="off"
              placeholder="VD: 1,200,000" 
              class="w-full px-4 py-2.5 pr-10 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white font-mono font-bold focus:border-cyan-400 focus:outline-none transition"
            />
            <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-500 pointer-events-none select-none">₫</span>
          </div>
        </div>

        <!-- Date -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Ngày giao dịch</label>
          <input 
            type="date" 
            name="transaction_date" 
            value="<?= date('Y-m-d') ?>" 
            required 
            class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white focus:border-cyan-400 focus:outline-none transition"
          />
        </div>

        <!-- Note -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Ghi chú (Tùy chọn)</label>
          <input 
            type="text" 
            name="note" 
            placeholder="Nội dung chi tiết..." 
            class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white focus:border-cyan-400 focus:outline-none transition"
          />
        </div>

        <!-- Submit Button -->
        <div class="pt-3">
          <button 
            type="submit" 
            id="btnAddSubmit"
            class="btn-gradient w-full py-3 rounded-full text-white font-bold text-xs tracking-wide flex items-center justify-center gap-2 cursor-pointer"
          >
            <span>Lưu giao dịch</span>
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 2: THÊM DANH MỤC MỚI (Quick Add Category Modal)      -->
  <!-- ========================================================= -->
  <div id="addCategoryModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-sm p-6 relative border border-cyan-500/30 shadow-[0_0_50px_rgba(0,210,255,0.15)] animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeAddCategoryModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <div class="flex items-center gap-2.5 mb-2">
        <div class="w-8 h-8 rounded-xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-400">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
        </div>
        <div>
          <h3 class="text-base font-bold text-white">Thêm danh mục mới</h3>
          <p class="text-[11px] text-slate-400">Tạo danh mục mới để phân loại thu chi</p>
        </div>
      </div>

      <form id="addCategoryForm" onsubmit="submitNewCategory(event)" class="space-y-4 mt-4">
        
        <!-- Category Name -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Tên danh mục <span class="text-rose-400">*</span></label>
          <input 
            type="text" 
            id="modalCatName" 
            name="cat_name" 
            required 
            placeholder="VD: Học tập, Thể thao, Du lịch..." 
            class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none transition"
          />
        </div>

        <!-- Category Type -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1.5">Loại danh mục</label>
          <div class="grid grid-cols-3 gap-2">
            <label class="cursor-pointer">
              <input type="radio" name="cat_type" value="income" class="hidden peer" id="catTypeIncome">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-emerald-500/20 peer-checked:text-emerald-400 peer-checked:border-emerald-500/30 transition">
                + Thu nhập
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="cat_type" value="expense" checked class="hidden peer" id="catTypeExpense">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-rose-500/20 peer-checked:text-rose-400 peer-checked:border-rose-500/30 transition">
                - Khoản chi
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="cat_type" value="both" class="hidden peer" id="catTypeBoth">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-cyan-500/20 peer-checked:text-cyan-300 peer-checked:border-cyan-500/30 transition">
                ⇄ Dùng chung
              </div>
            </label>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2 pt-2">
          <button 
            type="button" 
            onclick="closeAddCategoryModal()" 
            class="w-1/3 py-2.5 rounded-xl bg-white/[0.05] hover:bg-white/[0.1] text-xs font-semibold text-slate-300 transition cursor-pointer"
          >
            Hủy
          </button>
          <button 
            type="submit" 
            id="btnSaveCategory" 
            class="btn-gradient w-2/3 py-2.5 rounded-xl text-xs font-bold text-white flex items-center justify-center gap-1.5 cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span>Lưu danh mục</span>
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 3: QUẢN LÝ DANH MỤC (Category Manager Modal)        -->
  <!-- ========================================================= -->
  <div id="categoryManagerModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-2xl max-h-[85vh] p-6 sm:p-7 relative border border-white/10 shadow-2xl flex flex-col animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeCategoryManagerModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-2xl bg-purple-500/20 border border-purple-400/30 flex items-center justify-center text-purple-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-bold text-white">Quản lý danh mục thu chi</h3>
          <p class="text-xs text-slate-400">Xem và phân loại các danh mục của bạn trong cơ sở dữ liệu</p>
        </div>
      </div>

      <!-- Quick Add Category Form directly inside Manager -->
      <form onsubmit="submitCategoryFromManager(event)" class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 mb-5 flex flex-col gap-2">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
          <input 
            type="text" 
            id="mgrCatName" 
            required 
            placeholder="Nhập tên danh mục mới..." 
            class="flex-1 px-3.5 py-2 rounded-xl text-xs bg-slate-900 border border-white/10 text-white placeholder-slate-500 focus:border-cyan-400 focus:outline-none"
          />
          <select 
            id="mgrCatType" 
            class="px-3 py-2 rounded-xl text-xs bg-slate-900 border border-white/10 text-white focus:border-cyan-400 focus:outline-none"
          >
            <option value="expense">Khoản chi (-)</option>
            <option value="income">Thu nhập (+)</option>
            <option value="both">Dùng chung (⇄)</option>
          </select>
          <select 
            id="mgrParentId" 
            class="px-3 py-2 rounded-xl text-xs bg-slate-900 border border-white/10 text-white focus:border-cyan-400 focus:outline-none"
          >
            <option value="">-- Là danh mục chính --</option>
            <?php foreach ($categoriesList as $pc): ?>
              <option value="<?= $pc['id'] ?>">Thuộc: <?= htmlspecialchars($pc['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button 
            type="submit" 
            class="btn-gradient px-4 py-2 rounded-xl text-xs font-bold text-white flex items-center justify-center gap-1.5 shrink-0 cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span>Thêm</span>
          </button>
        </div>
      </form>

      <!-- Category Filter Tabs -->
      <div class="flex items-center gap-2 mb-3">
        <button type="button" onclick="filterCategoryCards('all')" class="cat-filter-btn px-3 py-1 rounded-xl text-xs font-semibold bg-purple-500/20 text-purple-300 cursor-pointer">Tất cả (<?= count($categoriesList) ?>)</button>
        <button type="button" onclick="filterCategoryCards('income')" class="cat-filter-btn px-3 py-1 rounded-xl text-xs font-medium text-slate-400 hover:text-white cursor-pointer">Thu nhập</button>
        <button type="button" onclick="filterCategoryCards('expense')" class="cat-filter-btn px-3 py-1 rounded-xl text-xs font-medium text-slate-400 hover:text-white cursor-pointer">Khoản chi</button>
      </div>

      <!-- Category List Scrollable Container -->
      <div id="categoryCardsContainer" class="flex-1 overflow-y-auto pr-1 space-y-2.5 max-h-[380px]">
        <?php foreach ($categoriesList as $cat): ?>
          <?php $subs = $subcategoriesByParent[$cat['id']] ?? []; ?>
          <div class="cat-card p-3.5 rounded-2xl bg-white/[0.02] hover:bg-white/[0.04] border border-white/[0.06] transition" data-type="<?= $cat['type'] ?>" id="cat-card-<?= $cat['id'] ?>">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full <?= $cat['type'] === 'income' ? 'bg-emerald-400' : ($cat['type'] === 'expense' ? 'bg-rose-400' : 'bg-cyan-400') ?>"></span>
                <div>
                  <div class="text-xs font-bold text-white"><?= htmlspecialchars($cat['name']) ?></div>
                  <div class="text-[10px] text-slate-400">
                    <?= $cat['type'] === 'income' ? 'Thu nhập (+)' : ($cat['type'] === 'expense' ? 'Khoản chi (-)' : 'Dùng chung') ?>
                    <?php if (!empty($subs)): ?>
                      &bull; <span class="text-cyan-400 font-semibold"><?= count($subs) ?> danh mục con</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <!-- Nút thêm danh mục con nhanh -->
                <button 
                  type="button" 
                  onclick="quickAddSubcategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')" 
                  class="text-[11px] px-2.5 py-1 rounded-xl bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 font-semibold flex items-center gap-1 transition cursor-pointer"
                  title="Thêm danh mục con cho <?= htmlspecialchars($cat['name']) ?>"
                >
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                  <span>+ Con</span>
                </button>

                <?php if (empty($cat['user_id'])): ?>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-medium border border-white/5">Hệ thống</span>
                <?php else: ?>
                  <span class="text-[10px] px-2 py-0.5 rounded-full bg-cyan-500/10 text-cyan-400 font-medium border border-cyan-500/20">Tự tạo</span>
                  <button 
                    type="button" 
                    onclick="deleteCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>')" 
                    class="p-1 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-rose-500/10 transition cursor-pointer"
                    title="Xóa danh mục này"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  </button>
                <?php endif; ?>
              </div>
            </div>

            <!-- Subcategories Chips List -->
            <?php if (!empty($subs)): ?>
              <div class="mt-2.5 pt-2 border-t border-white/[0.04] flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] text-slate-400 font-semibold uppercase">Danh mục con:</span>
                <?php foreach ($subs as $s): ?>
                  <span class="inline-flex items-center gap-1.5 text-[11px] px-2.5 py-0.5 rounded-lg bg-white/[0.04] border border-white/10 text-slate-200">
                    <span><?= htmlspecialchars($s['name']) ?></span>
                    <button 
                      type="button" 
                      onclick="deleteCategory(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>')" 
                      class="text-slate-400 hover:text-rose-400 transition cursor-pointer font-bold"
                      title="Xóa danh mục con '<?= htmlspecialchars($s['name']) ?>'"
                    >&times;</button>
                  </span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 4: BÁO CÁO & THỐNG KÊ (Analytics Modal)             -->
  <!-- ========================================================= -->
  <div id="analyticsModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-lg p-6 sm:p-7 relative border border-white/10 shadow-2xl flex flex-col animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeAnalyticsModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-bold text-white">Thống kê tài chính & Danh mục</h3>
          <p class="text-xs text-slate-400">Phân tích tỷ trọng chi tiêu và nguồn thu</p>
        </div>
      </div>

      <!-- Ratio Visual Bar -->
      <?php 
        $sumAll = $totalIncome + $totalExpense;
        $incomePercent = $sumAll > 0 ? round(($totalIncome / $sumAll) * 100, 1) : 50;
        $expensePercent = $sumAll > 0 ? round(($totalExpense / $sumAll) * 100, 1) : 50;
      ?>
      <div class="mb-5 p-4 rounded-2xl bg-white/[0.03] border border-white/10">
        <div class="flex justify-between text-xs font-semibold mb-2">
          <span class="text-emerald-400">Thu: <?= $incomePercent ?>%</span>
          <span class="text-rose-400">Chi: <?= $expensePercent ?>%</span>
        </div>
        <div class="w-full h-3 rounded-full bg-slate-800 overflow-hidden flex">
          <div class="h-full bg-emerald-500" style="width: <?= $incomePercent ?>%"></div>
          <div class="h-full bg-rose-500" style="width: <?= $expensePercent ?>%"></div>
        </div>
      </div>

      <!-- Top Expense Categories -->
      <div class="space-y-3">
        <div class="text-xs font-bold text-slate-300">Top danh mục chi tiêu:</div>
        <?php if (empty($expenseByCat)): ?>
          <div class="text-xs text-slate-500 italic">Chưa có dữ liệu chi tiêu</div>
        <?php else: ?>
          <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
            <?php foreach (array_slice($expenseByCat, 0, 5) as $catName => $amount): ?>
              <?php $pct = $totalExpense > 0 ? round(($amount / $totalExpense) * 100, 1) : 0; ?>
              <div class="p-2.5 rounded-xl bg-white/[0.02] border border-white/[0.05]">
                <div class="flex justify-between text-xs mb-1">
                  <span class="font-medium text-slate-200"><?= htmlspecialchars($catName) ?></span>
                  <span class="font-mono text-rose-400 font-bold"><?= number_format($amount, 0, '.', ',') ?> ₫ (<?= $pct ?>%)</span>
                </div>
                <div class="w-full h-1.5 rounded-full bg-slate-800 overflow-hidden">
                  <div class="h-full bg-rose-500/80 rounded-full" style="width: <?= $pct ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 5: XEM CHI TIẾT GIAO DỊCH (View Transaction Modal)   -->
  <!-- ========================================================= -->
  <div id="viewTransactionModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-md p-6 sm:p-7 relative border border-white/10 shadow-2xl animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeViewTransactionModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <!-- Header with Icon & ID -->
      <div class="flex items-center gap-3 mb-5">
        <div id="viewTxIconBox" class="w-10 h-10 rounded-2xl bg-cyan-500/20 border border-cyan-400/30 flex items-center justify-center text-cyan-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <div>
          <h3 class="text-base font-bold text-white">Chi tiết giao dịch</h3>
          <p class="text-xs text-slate-400 flex items-center gap-1.5">
            <span>Mã giao dịch:</span>
            <span id="viewTxId" class="font-mono text-cyan-400 font-semibold">#1</span>
          </p>
        </div>
      </div>

      <!-- Amount Showcase Card -->
      <div class="p-5 rounded-2xl bg-white/[0.03] border border-white/[0.08] mb-5 text-center relative overflow-hidden">
        <div class="flex justify-center mb-2">
          <div id="viewTxTypeBadge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold">
            Thu nhập
          </div>
        </div>
        <div id="viewTxAmount" class="text-2xl sm:text-3xl font-extrabold font-mono text-emerald-400">
          +35.000.000 ₫
        </div>
      </div>

      <!-- Details List -->
      <div class="space-y-2.5 text-xs mb-6">
        <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
          <span class="text-slate-400">Danh mục</span>
          <span id="viewTxCategory" class="font-bold text-white">Lương & Thưởng</span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
          <span class="text-slate-400">Ngày giao dịch</span>
          <span id="viewTxDate" class="font-mono font-semibold text-slate-200">02/11/2026</span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
          <span class="text-slate-400">Thời gian ghi nhận</span>
          <span id="viewTxCreatedAt" class="font-mono text-slate-400">2026-11-02 09:42:00</span>
        </div>

        <div class="p-3 rounded-xl bg-white/[0.02] border border-white/[0.05]">
          <div class="text-slate-400 mb-1">Ghi chú</div>
          <div id="viewTxNote" class="text-slate-200 font-medium whitespace-pre-wrap leading-relaxed">
            -
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-3">
        <button 
          type="button" 
          onclick="closeViewTransactionModal()" 
          class="flex-1 py-2.5 rounded-xl bg-white/[0.06] hover:bg-white/[0.1] text-xs font-bold text-slate-300 hover:text-white transition cursor-pointer"
        >
          Đóng (Esc)
        </button>
        <button 
          type="button" 
          id="btnDeleteFromView" 
          class="px-4 py-2.5 rounded-xl bg-rose-500/15 hover:bg-rose-500/25 border border-rose-500/30 text-rose-300 hover:text-rose-200 text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          <span>Xóa</span>
        </button>
      </div>

    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 6: THÊM KHOẢN CHI DỰ KIẾN (Add Planned Expense Modal)-->
  <!-- ========================================================= -->
  <div id="addPlannedModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-lg p-6 sm:p-7 relative border border-amber-500/30 shadow-[0_0_50px_rgba(245,158,11,0.15)] animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeAddPlannedModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-2xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center text-amber-400 shadow-[0_0_15px_rgba(245,158,11,0.2)]">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-bold text-white">Thêm khoản chi dự kiến</h3>
          <p class="text-xs text-slate-400">Lên kế hoạch chi tiêu định kỳ và theo dõi chênh lệch thực tế</p>
        </div>
      </div>

      <form id="addPlannedForm" onsubmit="submitPlannedExpense(event)" class="space-y-4">
        
        <!-- Grid: Category & Subcategory -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Category -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Danh mục chính <span class="text-rose-400">*</span>
            </label>
            <input 
              type="text" 
              id="plannedCategoryInput"
              name="category" 
              required 
              list="plannedCategoryList"
              placeholder="VD: Dâu, Ăn uống, Tiền nhà..." 
              oninput="syncPlannedSubcategories(this.value)"
              class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none transition"
            />
            <datalist id="plannedCategoryList">
              <?php foreach ($categoriesList as $cat): ?>
                <option value="<?= htmlspecialchars($cat['name']) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>

          <!-- Subcategory -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Danh mục chi tiết / con
            </label>
            <input 
              type="text" 
              id="plannedSubcategoryInput"
              name="subcategory" 
              list="plannedSubcategoryList"
              placeholder="VD: Tiền học, Tiền sữa..." 
              class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none transition"
            />
            <datalist id="plannedSubcategoryList">
              <?php foreach ($subcategoriesList as $sub): ?>
                <option value="<?= htmlspecialchars($sub['name']) ?>"></option>
              <?php endforeach; ?>
            </datalist>
          </div>
        </div>

        <!-- Title / Description -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Tên khoản chi / Nội dung</label>
          <input 
            type="text" 
            name="title" 
            placeholder="VD: Tiền học cho Dâu, Tiền điện nước..." 
            class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none transition"
          />
        </div>

        <!-- Grid: Expected Amount & Expected Date -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Expected Amount -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Số tiền dự kiến (VND) <span class="text-rose-400">*</span>
            </label>
            <div class="relative">
              <input 
                type="text" 
                inputmode="numeric"
                id="plannedAmountInput"
                name="expected_amount" 
                required 
                autocomplete="off"
                placeholder="VD: 1,000,000" 
                class="w-full px-3.5 py-2.5 pr-8 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white font-mono font-bold focus:border-amber-400 focus:outline-none transition"
              />
              <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-500 pointer-events-none select-none">₫</span>
            </div>
          </div>

          <!-- Expected Date -->
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">
              Ngày dự kiến bắt đầu <span class="text-rose-400">*</span>
            </label>
            <input 
              type="date" 
              id="plannedExpectedDate"
              name="expected_date" 
              value="<?= date('Y-m-d') ?>" 
              required 
              onchange="onPlannedDateChange(this.value)"
              class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white focus:border-amber-400 focus:outline-none transition"
            />
          </div>
        </div>

        <!-- Repeat Frequency Control -->
        <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/10 space-y-3">
          <label class="block text-xs font-semibold text-amber-300">
            Chu kỳ lặp lại (Tùy chỉnh xuất hiện)
          </label>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <label class="cursor-pointer">
              <input type="radio" name="repeat_type" value="monthly" checked onchange="toggleRepeatOptions(this.value)" class="hidden peer">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-amber-500/20 peer-checked:text-amber-300 peer-checked:border-amber-500/30 transition">
                🗓️ Hằng tháng
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="repeat_type" value="weekly" onchange="toggleRepeatOptions(this.value)" class="hidden peer">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-amber-500/20 peer-checked:text-amber-300 peer-checked:border-amber-500/30 transition">
                📅 Hằng tuần
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="repeat_type" value="daily" onchange="toggleRepeatOptions(this.value)" class="hidden peer">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-amber-500/20 peer-checked:text-amber-300 peer-checked:border-amber-500/30 transition">
                ☀️ Hằng ngày
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="repeat_type" value="custom_days" onchange="toggleRepeatOptions(this.value)" class="hidden peer">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-amber-500/20 peer-checked:text-amber-300 peer-checked:border-amber-500/30 transition">
                ⏱️ X ngày / lần
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="repeat_type" value="yearly" onchange="toggleRepeatOptions(this.value)" class="hidden peer">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-amber-500/20 peer-checked:text-amber-300 peer-checked:border-amber-500/30 transition">
                🎉 Hằng năm
              </div>
            </label>
            <label class="cursor-pointer">
              <input type="radio" name="repeat_type" value="once" onchange="toggleRepeatOptions(this.value)" class="hidden peer">
              <div class="text-center py-2 px-1 rounded-xl text-[11px] font-bold text-slate-400 border border-white/10 peer-checked:bg-amber-500/20 peer-checked:text-amber-300 peer-checked:border-amber-500/30 transition">
                1️⃣ Một lần duy nhất
              </div>
            </label>
          </div>

          <!-- Dynamic Option: Monthly Day of Month -->
          <div id="monthlyDayWrapper" class="flex items-center gap-3 pt-1">
            <span class="text-xs text-slate-400">Xuất hiện vào ngày:</span>
            <div class="flex items-center gap-2">
              <span class="text-xs text-amber-300 font-bold">Ngày</span>
              <input 
                type="number" 
                id="plannedDayOfMonth"
                name="day_of_month" 
                min="1" 
                max="31" 
                value="<?= date('j') ?>" 
                class="w-16 px-2.5 py-1.5 rounded-lg text-xs font-mono font-bold bg-slate-900 border border-white/10 text-white text-center focus:border-amber-400 focus:outline-none"
              />
              <span class="text-xs text-slate-400">hằng tháng để xem</span>
            </div>
          </div>

          <!-- Dynamic Option: Custom Days Interval -->
          <div id="customDaysWrapper" class="hidden items-center gap-3 pt-1">
            <span class="text-xs text-slate-400">Xuất hiện chu kỳ:</span>
            <div class="flex items-center gap-2">
              <input 
                type="number" 
                id="plannedCustomInterval"
                name="repeat_interval" 
                min="1" 
                max="365" 
                value="3" 
                class="w-16 px-2.5 py-1.5 rounded-lg text-xs font-mono font-bold bg-slate-900 border border-white/10 text-white text-center focus:border-amber-400 focus:outline-none"
              />
              <span class="text-xs text-amber-300 font-bold">ngày xuất hiện 1 lần</span>
            </div>
          </div>
        </div>

        <!-- Note -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Ghi chú thêm</label>
          <input 
            type="text" 
            name="note" 
            placeholder="Ghi chú chi tiết cho khoản chi này..." 
            class="w-full px-3.5 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white placeholder-slate-500 focus:border-amber-400 focus:outline-none transition"
          />
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button 
            type="submit" 
            id="btnAddPlannedSubmit"
            class="w-full py-3 rounded-full text-white font-bold text-xs tracking-wide bg-gradient-to-r from-amber-500 to-orange-500 hover:brightness-110 shadow-lg shadow-orange-500/20 flex items-center justify-center gap-2 transition cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            <span>Lưu khoản chi dự kiến</span>
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 7: CẬP NHẬT THỰC TẾ CHI (Actual Expense Modal)       -->
  <!-- ========================================================= -->
  <div id="actualExpenseModal" class="modal-backdrop fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/80 backdrop-blur-md">
    <div class="card-glass w-full max-w-md p-6 sm:p-7 relative border border-emerald-500/30 shadow-[0_0_50px_rgba(16,185,129,0.15)] animate-in fade-in zoom-in-95 duration-200" onclick="event.stopPropagation()">
      
      <!-- Close button -->
      <button 
        type="button" 
        onclick="closeActualExpenseModal()" 
        class="absolute right-5 top-5 w-8 h-8 rounded-full bg-white/[0.04] hover:bg-white/[0.08] flex items-center justify-center text-slate-400 hover:text-white transition cursor-pointer"
        title="Đóng (Esc)"
      >
        ✕
      </button>

      <div class="flex items-center gap-3 mb-4">
        <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <div>
          <h3 class="text-lg font-bold text-white">Xác nhận thực chi</h3>
          <p class="text-xs text-slate-400">Cập nhật số tiền & ngày chi thực tế của khoản chi</p>
        </div>
      </div>

      <!-- Planned Info Summary Card -->
      <div class="p-3.5 rounded-2xl bg-white/[0.03] border border-white/[0.07] mb-4 space-y-2 text-xs">
        <div class="flex justify-between items-center">
          <span class="text-slate-400">Danh mục:</span>
          <span id="actualModalCatBadge" class="font-bold text-white"></span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-slate-400">Ngày dự kiến:</span>
          <span id="actualModalExpectedDate" class="font-mono text-slate-300"></span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-slate-400">Số tiền dự kiến:</span>
          <span id="actualModalExpectedAmount" class="font-mono font-bold text-amber-400"></span>
        </div>
      </div>

      <form id="actualExpenseForm" onsubmit="submitActualExpense(event)" class="space-y-4">
        <input type="hidden" id="actualModalPlannedId" name="id" value="" />
        <input type="hidden" id="actualModalBaseExpected" value="0" />

        <!-- Actual Date -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">
            Ngày thực tế <span class="text-rose-400">*</span>
          </label>
          <input 
            type="date" 
            id="actualDateInput"
            name="actual_date" 
            value="<?= date('Y-m-d') ?>" 
            required 
            class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white focus:border-emerald-400 focus:outline-none transition"
          />
        </div>

        <!-- Actual Amount -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-semibold text-slate-300">
              Số tiền thực tế (VND) <span class="text-rose-400">*</span>
            </label>
            <span id="actualVarianceBadge" class="text-[11px] font-mono font-bold"></span>
          </div>
          <div class="relative">
            <input 
              type="text" 
              inputmode="numeric"
              id="actualAmountInput"
              name="actual_amount" 
              required 
              autocomplete="off"
              placeholder="VD: 1,200,000" 
              class="w-full px-4 py-2.5 pr-10 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white font-mono font-bold focus:border-emerald-400 focus:outline-none transition"
            />
            <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-500 pointer-events-none select-none">₫</span>
          </div>
        </div>

        <!-- Sync to Transactions Checkbox -->
        <div class="p-3 rounded-xl bg-white/[0.02] border border-white/[0.06]">
          <label class="flex items-start gap-2.5 cursor-pointer">
            <input 
              type="checkbox" 
              name="record_to_transactions" 
              value="1" 
              checked 
              class="mt-0.5 rounded text-cyan-500 focus:ring-0 bg-slate-900 border-white/20"
            />
            <div>
              <div class="text-xs font-semibold text-white">Tự động ghi vào Lịch sử giao dịch chính</div>
              <div class="text-[11px] text-slate-400">Trừ trực tiếp số tiền thực chi này vào số dư CashTrack của bạn</div>
            </div>
          </label>
        </div>

        <!-- Note -->
        <div>
          <label class="block text-xs font-semibold text-slate-300 mb-1">Ghi chú thực chi (Tùy chọn)</label>
          <input 
            type="text" 
            name="note" 
            placeholder="Nội dung thực tế..." 
            class="w-full px-4 py-2.5 rounded-xl text-xs bg-slate-900/80 border border-white/10 text-white focus:border-emerald-400 focus:outline-none transition"
          />
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
          <button 
            type="submit" 
            id="btnSaveActualSubmit"
            class="w-full py-3 rounded-full text-white font-bold text-xs tracking-wide bg-gradient-to-r from-emerald-500 to-teal-500 hover:brightness-110 shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2 transition cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span>Xác nhận đã chi & Lưu</span>
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- JAVASCRIPT CONTROLLERS                                    -->
  <!-- ========================================================= -->
  <script>
    let currentViewingTxId = null;

    // Toast Notification helper
    let toastTimeout;
    function showToast(type, message) {
      const toast = document.getElementById("toast");
      const toastIcon = document.getElementById("toast-icon");
      const toastMsg = document.getElementById("toast-message");

      clearTimeout(toastTimeout);

      if (type === "success") {
        toast.className = "flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold bg-emerald-950/90 border-emerald-500/30 text-emerald-300";
        toastIcon.innerHTML = `<svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
      } else if (type === "error") {
        toast.className = "flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold bg-rose-950/90 border-rose-500/30 text-rose-300";
        toastIcon.innerHTML = `<svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>`;
      } else {
        toast.className = "flex items-center gap-3 px-4 py-3 rounded-2xl border shadow-2xl backdrop-blur-xl text-xs font-semibold bg-slate-900/90 border-cyan-500/30 text-cyan-300";
        toastIcon.innerHTML = `<svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
      }

      toastMsg.textContent = message;
      toast.classList.add("show");

      toastTimeout = setTimeout(() => {
        toast.classList.remove("show");
      }, 3500);
    }

    // Universal Modal Helper: Close on click outside (backdrop)
    document.querySelectorAll('.modal-backdrop').forEach(modalEl => {
      modalEl.addEventListener('click', function(e) {
        if (e.target === this) {
          closeModal(this.id);
        }
      });
    });

    function closeModal(modalId) {
      const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
      if (!modal) return;
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // Close open modal with Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModals = Array.from(document.querySelectorAll('.modal-backdrop:not(.hidden)'));
        if (openModals.length > 0) {
          const topModal = openModals[openModals.length - 1];
          closeModal(topModal);
        }
      }
    });

    // Sidebar Mobile Toggle (Drawer on mobile)
    function toggleSidebar(open) {
      const sidebar = document.getElementById('sidebar');
      const backdrop = document.getElementById('sidebarBackdrop');
      
      if (open) {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
      } else {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('hidden');
      }
    }

    // Sidebar Desktop Collapse / Expand Toggle
    function toggleSidebarCollapse() {
      const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('cashtrack_sidebar_collapsed', isCollapsed ? '1' : '0');

      const btn = document.getElementById('sidebarCollapseBtn');
      if (btn) {
        btn.title = isCollapsed ? 'Mở rộng menu (Ctrl + B)' : 'Thu gọn menu (Ctrl + B)';
      }
    }

    // Keyboard shortcut: Ctrl + B to toggle collapse
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') {
        e.preventDefault();
        toggleSidebarCollapse();
      }
    });

    // Smooth scroll to transactions
    function scrollToTransactions() {
      const el = document.getElementById('transactionSection');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
        toggleSidebar(false);
      }
    }

    // Modal Add Transaction Controls
    function openAddModal() {
      const modal = document.getElementById('addModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      toggleSidebar(false);
      setTimeout(() => {
        const amt = document.getElementById('amountInput');
        if (amt) amt.focus();
      }, 100);
    }

    function closeAddModal() {
      closeModal('addModal');
    }

    // Modal Add Category Controls
    function openAddCategoryModal() {
      const catModal = document.getElementById('addCategoryModal');
      const catInput = document.getElementById('categoryInput');
      const modalCatName = document.getElementById('modalCatName');

      // Pre-fill name if user already typed in category input
      if (catInput && catInput.value.trim()) {
        modalCatName.value = catInput.value.trim();
      } else {
        modalCatName.value = '';
      }

      // Sync category type with current radio selection
      const currentTxType = document.querySelector('input[name="type"]:checked')?.value || 'expense';
      syncCategoryType(currentTxType);

      catModal.classList.remove('hidden');
      catModal.classList.add('flex');
      setTimeout(() => modalCatName.focus(), 100);
    }

    function closeAddCategoryModal() {
      closeModal('addCategoryModal');
    }

    function syncCategoryType(type) {
      if (type === 'income') {
        const rad = document.getElementById('catTypeIncome');
        if (rad) rad.checked = true;
      } else if (type === 'expense') {
        const rad = document.getElementById('catTypeExpense');
        if (rad) rad.checked = true;
      }
    }

    // Submit New Category from quick modal
    function submitNewCategory(e) {
      e.preventDefault();
      const name = document.getElementById('modalCatName').value.trim();
      const type = document.querySelector('input[name="cat_type"]:checked')?.value || 'both';
      const btn = document.getElementById('btnSaveCategory');

      if (!name) {
        showToast('error', 'Vui lòng nhập tên danh mục!');
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<span>Đang lưu...</span>';

      const formData = new FormData();
      formData.append('name', name);
      formData.append('type', type);

      fetch('add_category.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg><span>Lưu danh mục</span>`;

        if (data.success) {
          showToast('success', data.message);
          
          // Set value into category input field
          const catInput = document.getElementById('categoryInput');
          if (catInput) {
            catInput.value = name;
          }

          // Add to datalist if not already in datalist
          const datalist = document.getElementById('categoryList');
          if (datalist) {
            let exists = false;
            for (let opt of datalist.options) {
              if (opt.value.toLowerCase() === name.toLowerCase()) {
                exists = true;
                break;
              }
            }
            if (!exists) {
              const opt = document.createElement('option');
              opt.value = name;
              opt.text = type === 'income' ? ' [Thu nhập]' : (type === 'expense' ? ' [Khoản chi]' : ' [Dùng chung]');
              datalist.appendChild(opt);
            }
          }

          closeAddCategoryModal();
        } else {
          showToast('error', data.message);
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<span>Lưu danh mục</span>';
        showToast('error', 'Lỗi kết nối khi lưu danh mục!');
      });
    }

    // Category Manager Modal Controls
    function openCategoryManagerModal() {
      const modal = document.getElementById('categoryManagerModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      toggleSidebar(false);
    }

    function closeCategoryManagerModal() {
      closeModal('categoryManagerModal');
    }

    function filterCategoryCards(type) {
      const cards = document.querySelectorAll('.cat-card');
      const buttons = document.querySelectorAll('.cat-filter-btn');

      buttons.forEach(b => {
        b.classList.remove('bg-purple-500/20', 'text-purple-300');
        b.classList.add('text-slate-400');
      });
      event.target.classList.add('bg-purple-500/20', 'text-purple-300');
      event.target.classList.remove('text-slate-400');

      cards.forEach(card => {
        if (type === 'all' || card.dataset.type === type || card.dataset.type === 'both') {
          card.style.display = '';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // Quick Add Subcategory shortcut
    function quickAddSubcategory(parentId, parentName) {
      const parentSelect = document.getElementById('mgrParentId');
      const nameInput = document.getElementById('mgrCatName');
      if (parentSelect) parentSelect.value = parentId;
      if (nameInput) {
        nameInput.placeholder = `Nhập tên danh mục con cho "${parentName}"...`;
        nameInput.focus();
      }
    }

    // Submit Category directly from Manager modal
    function submitCategoryFromManager(e) {
      e.preventDefault();
      const name = document.getElementById('mgrCatName').value.trim();
      const type = document.getElementById('mgrCatType').value;
      const parentId = document.getElementById('mgrParentId')?.value || '';

      if (!name) return;

      const formData = new FormData();
      formData.append('name', name);
      formData.append('type', type);
      if (parentId) formData.append('parent_id', parentId);

      fetch('add_category.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('success', data.message);
          document.getElementById('mgrCatName').value = '';
          setTimeout(() => location.reload(), 600);
        } else {
          showToast('error', data.message);
        }
      })
      .catch(() => showToast('error', 'Lỗi lưu danh mục!'));
    }

    // Delete Category
    function deleteCategory(id, name) {
      if (!confirm(`Bạn có chắc muốn xóa danh mục "${name}"?`)) return;

      const formData = new FormData();
      formData.append('id', id);

      fetch('delete_category.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('success', data.message);
          const el = document.getElementById('cat-card-' + id);
          if (el) el.remove();
        } else {
          showToast('error', data.message);
        }
      })
      .catch(() => showToast('error', 'Có lỗi khi xóa danh mục!'));
    }

    // Analytics Modal Controls
    function openAnalyticsModal() {
      const modal = document.getElementById('analyticsModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      toggleSidebar(false);
    }

    function closeAnalyticsModal() {
      closeModal('analyticsModal');
    }

    // =========================================================
    // View Transaction Details Modal Controls
    // =========================================================
    function viewTransactionDetails(tx) {
      currentViewingTxId = tx.id;
      document.getElementById('viewTxId').textContent = '#' + tx.id;
      
      const isIncome = tx.type === 'income';
      const typeBadge = document.getElementById('viewTxTypeBadge');
      typeBadge.className = isIncome 
        ? 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
        : 'inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20';
      typeBadge.innerHTML = isIncome 
        ? '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg> Khoản Thu'
        : '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg> Khoản Chi';

      const amountEl = document.getElementById('viewTxAmount');
      const formattedAmount = Number(tx.amount).toLocaleString('en-US');
      amountEl.textContent = (isIncome ? '+' : '-') + formattedAmount + ' ₫';
      amountEl.className = 'text-2xl sm:text-3xl font-extrabold font-mono ' + (isIncome ? 'text-emerald-400' : 'text-rose-400');

      document.getElementById('viewTxCategory').textContent = tx.subcategory ? `${tx.category} › ${tx.subcategory}` : tx.category;
      
      // Format Date
      if (tx.transaction_date) {
        const parts = tx.transaction_date.split('-');
        if (parts.length === 3) {
          document.getElementById('viewTxDate').textContent = `${parts[2]}/${parts[1]}/${parts[0]}`;
        } else {
          document.getElementById('viewTxDate').textContent = tx.transaction_date;
        }
      } else {
        document.getElementById('viewTxDate').textContent = '-';
      }
      
      document.getElementById('viewTxNote').textContent = tx.note && tx.note.trim() !== '' ? tx.note : 'Không có ghi chú thêm';
      document.getElementById('viewTxCreatedAt').textContent = tx.created_at ? tx.created_at : '-';

      const modal = document.getElementById('viewTransactionModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeViewTransactionModal() {
      closeModal('viewTransactionModal');
    }

    // Connect delete button in view modal
    document.getElementById('btnDeleteFromView').onclick = function() {
      if (currentViewingTxId) {
        closeViewTransactionModal();
        deleteTransaction(currentViewingTxId);
      }
    };

    // Submit new transaction
    function submitTransaction(e) {
      e.preventDefault();
      const form = document.getElementById('addTransactionForm');
      const amountInput = document.getElementById('amountInput');
      const rawAmount = amountInput ? amountInput.value.replace(/,/g, '').trim() : '';

      if (!rawAmount || isNaN(rawAmount) || parseFloat(rawAmount) <= 0) {
        showToast('error', 'Vui lòng nhập số tiền hợp lệ lớn hơn 0!');
        if (amountInput) amountInput.focus();
        return;
      }

      const formData = new FormData(form);
      formData.set('amount', rawAmount);
      const submitBtn = document.getElementById('btnAddSubmit');

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Đang lưu...</span>';

      fetch('add_transaction.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Lưu giao dịch</span>';

        if (data.success) {
          showToast('success', data.message);
          closeAddModal();
          form.reset();
          if (amountInput) amountInput.value = '';
          setTimeout(() => location.reload(), 600);
        } else {
          showToast('error', data.message);
        }
      })
      .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Lưu giao dịch</span>';
        showToast('error', 'Có lỗi kết nối đến máy chủ!');
      });
    }

    // Delete transaction
    function deleteTransaction(id) {
      if (!confirm('Bạn có chắc chắn muốn xóa giao dịch này?')) return;

      const formData = new FormData();
      formData.append('id', id);

      fetch('delete_transaction.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('success', data.message);
          const row = document.getElementById('row-' + id);
          if (row) {
            row.style.opacity = '0';
            row.style.transform = 'scale(0.95)';
            setTimeout(() => location.reload(), 400);
          }
        } else {
          showToast('error', data.message);
        }
      })
      .catch(err => {
        showToast('error', 'Có lỗi khi xóa giao dịch!');
      });
    }

    // Filter table by type
    function filterTable(type) {
      const rows = document.querySelectorAll('.transaction-row');
      const buttons = document.querySelectorAll('.filter-btn');

      buttons.forEach(b => {
        b.classList.remove('bg-cyan-500/20', 'text-cyan-300', 'text-white');
        b.classList.add('text-slate-400');
      });

      event.target.classList.add('bg-cyan-500/20', 'text-cyan-300', 'text-white');
      event.target.classList.remove('text-slate-400');

      rows.forEach(row => {
        if (type === 'all' || row.dataset.type === type) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    // =========================================================
    // Subcategories Dynamic Mapping
    // =========================================================
    const SUBCATEGORIES_MAP = <?= json_encode($subcategoriesByCategoryName, JSON_UNESCAPED_UNICODE) ?>;

    function syncTxSubcategories(catName) {
      const list = document.getElementById('subcategoryList');
      if (!list) return;
      list.innerHTML = '';
      const subs = SUBCATEGORIES_MAP[catName] || [];
      subs.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        list.appendChild(opt);
      });
    }

    function syncPlannedSubcategories(catName) {
      const list = document.getElementById('plannedSubcategoryList');
      if (!list) return;
      list.innerHTML = '';
      const subs = SUBCATEGORIES_MAP[catName] || [];
      subs.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        list.appendChild(opt);
      });
    }

    // =========================================================
    // Planned Expenses Controllers
    // =========================================================
    function scrollToPlannedExpenses() {
      const el = document.getElementById('plannedExpenseSection');
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      toggleSidebar(false);
    }

    function openAddPlannedModal() {
      const modal = document.getElementById('addPlannedModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      toggleSidebar(false);
      setTimeout(() => document.getElementById('plannedCategoryInput')?.focus(), 100);
    }

    function closeAddPlannedModal() {
      closeModal('addPlannedModal');
    }

    function toggleRepeatOptions(repeatType) {
      const monthlyWrapper = document.getElementById('monthlyDayWrapper');
      const customWrapper = document.getElementById('customDaysWrapper');

      if (monthlyWrapper) {
        monthlyWrapper.style.display = (repeatType === 'monthly') ? 'flex' : 'none';
      }
      if (customWrapper) {
        customWrapper.style.display = (repeatType === 'custom_days') ? 'flex' : 'none';
      }
    }

    function onPlannedDateChange(dateStr) {
      if (!dateStr) return;
      const parts = dateStr.split('-');
      if (parts.length === 3) {
        const day = parseInt(parts[2], 10);
        const dayInput = document.getElementById('plannedDayOfMonth');
        if (dayInput) {
          dayInput.value = day;
        }
      }
    }

    function submitPlannedExpense(e) {
      e.preventDefault();
      const form = document.getElementById('addPlannedForm');
      const amountInput = document.getElementById('plannedAmountInput');
      const rawAmount = amountInput ? amountInput.value.replace(/,/g, '').trim() : '';

      if (!rawAmount || isNaN(rawAmount) || parseFloat(rawAmount) <= 0) {
        showToast('error', 'Vui lòng nhập số tiền dự kiến hợp lệ!');
        if (amountInput) amountInput.focus();
        return;
      }

      const formData = new FormData(form);
      formData.set('expected_amount', rawAmount);

      const btn = document.getElementById('btnAddPlannedSubmit');
      btn.disabled = true;
      btn.innerHTML = '<span>Đang lưu...</span>';

      fetch('add_planned_expense.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg><span>Lưu khoản chi dự kiến</span>`;
        if (data.success) {
          showToast('success', data.message);
          closeAddPlannedModal();
          form.reset();
          setTimeout(() => location.reload(), 600);
        } else {
          showToast('error', data.message);
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<span>Lưu khoản chi dự kiến</span>';
        showToast('error', 'Lỗi khi lưu khoản chi dự kiến!');
      });
    }

    // Actual Expense Modal Controls
    function openActualExpenseModal(pe) {
      document.getElementById('actualModalPlannedId').value = pe.id;
      document.getElementById('actualModalBaseExpected').value = pe.expected_amount;

      const catText = pe.subcategory ? `${pe.category} › ${pe.subcategory}` : pe.category;
      document.getElementById('actualModalCatBadge').textContent = catText;
      document.getElementById('actualModalExpectedDate').textContent = pe.expected_date;
      document.getElementById('actualModalExpectedAmount').textContent = Number(pe.expected_amount).toLocaleString('en-US') + ' ₫';

      const actualInput = document.getElementById('actualAmountInput');
      const prefill = pe.actual_amount && parseFloat(pe.actual_amount) > 0 ? pe.actual_amount : pe.expected_amount;
      actualInput.value = formatCurrencyString(String(Math.round(prefill)));

      const dateInput = document.getElementById('actualDateInput');
      dateInput.value = pe.actual_date ? pe.actual_date : new Date().toISOString().split('T')[0];

      updateActualVariance();

      const modal = document.getElementById('actualExpenseModal');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }

    function closeActualExpenseModal() {
      closeModal('actualExpenseModal');
    }

    function updateActualVariance() {
      const baseExpected = parseFloat(document.getElementById('actualModalBaseExpected').value) || 0;
      const actualInput = document.getElementById('actualAmountInput');
      const rawActual = parseFloat((actualInput.value || '').replace(/,/g, '')) || 0;
      const badge = document.getElementById('actualVarianceBadge');
      if (!badge) return;

      if (rawActual <= 0) {
        badge.textContent = '';
        return;
      }

      const diff = rawActual - baseExpected;
      if (diff > 0) {
        badge.className = 'text-[11px] font-mono font-bold text-rose-400';
        badge.textContent = `+${Number(diff).toLocaleString('en-US')} ₫ (Vượt dự kiến)`;
      } else if (diff < 0) {
        badge.className = 'text-[11px] font-mono font-bold text-emerald-400';
        badge.textContent = `${Number(diff).toLocaleString('en-US')} ₫ (Tiết kiệm)`;
      } else {
        badge.className = 'text-[11px] font-mono font-bold text-cyan-400';
        badge.textContent = `0 ₫ (Đúng dự kiến)`;
      }
    }

    function submitActualExpense(e) {
      e.preventDefault();
      const form = document.getElementById('actualExpenseForm');
      const actualInput = document.getElementById('actualAmountInput');
      const rawActual = actualInput ? actualInput.value.replace(/,/g, '').trim() : '';

      if (!rawActual || isNaN(rawActual) || parseFloat(rawActual) <= 0) {
        showToast('error', 'Vui lòng nhập số tiền thực chi hợp lệ!');
        if (actualInput) actualInput.focus();
        return;
      }

      const formData = new FormData(form);
      formData.set('actual_amount', rawActual);

      const btn = document.getElementById('btnSaveActualSubmit');
      btn.disabled = true;
      btn.innerHTML = '<span>Đang xử lý...</span>';

      fetch('update_planned_actual.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        btn.disabled = false;
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg><span>Xác nhận đã chi & Lưu</span>`;
        if (data.success) {
          showToast('success', data.message);
          closeActualExpenseModal();
          setTimeout(() => location.reload(), 600);
        } else {
          showToast('error', data.message);
        }
      })
      .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<span>Xác nhận đã chi & Lưu</span>';
        showToast('error', 'Lỗi khi cập nhật chi thực tế!');
      });
    }

    function deletePlannedExpense(id) {
      if (!confirm('Bạn có chắc chắn muốn xóa khoản chi dự kiến này?')) return;
      const formData = new FormData();
      formData.append('id', id);

      fetch('delete_planned_expense.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          showToast('success', data.message);
          const row = document.getElementById('planned-row-' + id);
          if (row) {
            row.style.opacity = '0';
            row.style.transform = 'scale(0.95)';
            setTimeout(() => row.remove(), 300);
          }
        } else {
          showToast('error', data.message);
        }
      })
      .catch(() => showToast('error', 'Lỗi khi xóa khoản chi dự kiến!'));
    }

    function filterPlannedExpenses(status) {
      const rows = document.querySelectorAll('.planned-row');
      const buttons = document.querySelectorAll('.planned-filter-btn');

      buttons.forEach(b => {
        b.classList.remove('bg-amber-500/20', 'text-amber-300', 'text-white');
        b.classList.add('text-slate-400');
      });
      if (window.event && window.event.target) {
        window.event.target.classList.add('bg-amber-500/20', 'text-amber-300', 'text-white');
        window.event.target.classList.remove('text-slate-400');
      }

      rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    }

    // =========================================================
    // Format numeric string with thousand commas (e.g. 1200000 -> 1,200,000)
    // =========================================================
    function formatCurrencyString(digits) {
      if (!digits) return '';
      const normalized = digits.replace(/^0+(?=\d)/, '');
      return normalized.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Live number formatting helper for inputs
    function bindAmountFormatting(input, onChangeCallback) {
      if (!input) return;

      function formatWithDigitCount(elem, targetDigitCount) {
        const rawDigits = elem.value.replace(/\D/g, '');
        const formatted = formatCurrencyString(rawDigits);
        elem.value = formatted;

        if (!formatted) {
          elem.setSelectionRange(0, 0);
          return;
        }

        let newPos = formatted.length;
        let count = 0;
        for (let i = 0; i < formatted.length; i++) {
          if (/\d/.test(formatted[i])) {
            count++;
          }
          if (count === targetDigitCount) {
            newPos = i + 1;
            break;
          }
        }
        if (targetDigitCount === 0) newPos = 0;
        elem.setSelectionRange(newPos, newPos);
      }

      input.addEventListener('input', function() {
        const rawVal = this.value;
        const curPos = this.selectionStart || 0;
        const digitsBefore = (rawVal.slice(0, curPos).match(/\d/g) || []).length;
        formatWithDigitCount(this, digitsBefore);
        if (typeof onChangeCallback === 'function') {
          onChangeCallback(this);
        }
      });

      input.addEventListener('keydown', function(e) {
        if (this.selectionStart !== this.selectionEnd) return;
        const pos = this.selectionStart;
        const val = this.value;

        // When pressing Backspace right after a comma (e.g. 1,|200,000)
        if (e.key === 'Backspace' && pos > 0 && val[pos - 1] === ',') {
          e.preventDefault();
          this.value = val.slice(0, pos - 2) + val.slice(pos);
          const digitsBefore = (val.slice(0, pos - 2).match(/\d/g) || []).length;
          formatWithDigitCount(this, digitsBefore);
          if (typeof onChangeCallback === 'function') onChangeCallback(this);
        }
        // When pressing Delete right before a comma (e.g. 1|,200,000)
        else if (e.key === 'Delete' && pos < val.length && val[pos] === ',') {
          e.preventDefault();
          this.value = val.slice(0, pos) + val.slice(pos + 2);
          const digitsBefore = (val.slice(0, pos).match(/\d/g) || []).length;
          formatWithDigitCount(this, digitsBefore);
          if (typeof onChangeCallback === 'function') onChangeCallback(this);
        }
      });
    }

    function initAmountFormatting() {
      bindAmountFormatting(document.getElementById('amountInput'));
      bindAmountFormatting(document.getElementById('plannedAmountInput'));
      bindAmountFormatting(document.getElementById('actualAmountInput'), () => updateActualVariance());
    }

    initAmountFormatting();
  </script>
</body>
</html>
