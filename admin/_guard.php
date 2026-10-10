<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
$db = $conn ?? $koneksi ?? null;
if (!$db instanceof mysqli) {
    die('Koneksi database tidak ditemukan.');
}
$db->set_charset('utf8mb4');

// Pastikan yang mengakses memiliki hak akses admin
$isAdminUser = (
    !empty($_SESSION['admin_logged_in'])
    || isset($_SESSION['admin_id'])
    || (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin')
    || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
    || isset($_SESSION['admin_username'])
);

if (!$isAdminUser) {
    header('Location: login.php');
    exit;
}

function adminEsc($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function adminPageStart(string $title, string $activeTab = 'dashboard'): void {
    global $db, $conn;
    $base = '/Klinik-Pratama-Rumah-Hanania';
    $adminUser = $_SESSION['admin_username'] ?? 'admin';

    // Hitung ulasan pasien yang butuh verifikasi admin (status = 'menunggu')
    $pendingUlasanCount = 0;
    if (isset($db) && $db instanceof mysqli) {
        $qUlas = $db->query("SELECT nilai FROM pengaturan_konten WHERE kunci = 'daftar_ulasan_google' LIMIT 1");
        if ($qUlas && $rUlas = $qUlas->fetch_assoc()) {
            $ulasanData = json_decode($rUlas['nilai'] ?: '[]', true);
            if (is_array($ulasanData)) {
                foreach ($ulasanData as $u) {
                    if (($u['status'] ?? '') === 'menunggu') {
                        $pendingUlasanCount++;
                    }
                }
            }
        }
    }
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= adminEsc($title) ?> - Panel Admin Klinik Hanania</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{
            --hanania-primary:#4F3293;
            --hanania-primary-hover:#3F237D;
            --hanania-secondary:#7650A2;
            --hanania-purple-soft:#F3EEFA;
            --hanania-lavender:#E8DFF2;
            --hanania-bg:#F8F5FC;
            --hanania-card:#FFFFFF;
            --hanania-border:#E5DBF0;
            --hanania-text:#2D253B;
            --hanania-muted:#736787;
            --success-bg:#E9F9EE;
            --success-text:#1B7A3E;
            --warning-bg:#FFF6E5;
            --warning-text:#996000;
            --danger-bg:#FFEBEB;
            --danger-text:#B72B2B;
            --sidebar-width:260px;
        }
        body{
            background:var(--hanania-bg);
            color:var(--hanania-text);
            font-family:'Plus Jakarta Sans',Arial,Helvetica,sans-serif;
            min-height:100vh;
            display:flex;
        }
        a{text-decoration:none;color:inherit}

        /* SIDEBAR KIRI */
        .admin-sidebar{
            width:var(--sidebar-width);
            height:100vh;
            position:fixed;
            left:0;
            top:0;
            bottom:0;
            background:#FFFFFF;
            border-right:1px solid var(--hanania-border);
            display:flex;
            flex-direction:column;
            z-index:1100;
            box-shadow:4px 0 24px rgba(79,50,147,.04);
            transition:transform .25s ease;
        }

        .sidebar-brand{
            padding:20px 22px;
            display:flex;
            align-items:center;
            gap:12px;
            border-bottom:1px solid var(--hanania-border);
            background:#FAF8FD;
        }
        .sidebar-brand-logo{
            width:44px;
            height:44px;
            border-radius:12px;
            background:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:22px;
            flex-shrink:0;
            box-shadow:0 4px 12px rgba(79,50,147,.12);
            border:1px solid var(--hanania-border);
            overflow:hidden;
        }
        .sidebar-brand-logo img{
            width:100%;
            height:100%;
            object-fit:cover;
            display:block;
        }
        .sidebar-brand-text h2{
            font-size:13px;
            font-weight:900;
            color:var(--hanania-primary);
            line-height:1.25;
            letter-spacing:-.2px;
        }
        .sidebar-brand-text p{
            font-size:11px;
            color:var(--hanania-muted);
            font-weight:700;
            margin-top:2px;
            letter-spacing:.2px;
        }

        /* SIDEBAR USER PROFILE */
        .sidebar-user{
            padding:16px 20px;
            margin:14px 14px 8px;
            background:var(--hanania-purple-soft);
            border:1px solid var(--hanania-lavender);
            border-radius:14px;
            display:flex;
            align-items:center;
            gap:12px;
        }
        .sidebar-avatar{
            width:38px;
            height:38px;
            border-radius:50%;
            background:#fff;
            border:2px solid var(--hanania-lavender);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:18px;
            flex-shrink:0;
        }
        .sidebar-user-info{
            overflow:hidden;
        }
        .sidebar-user-name{
            font-size:13px;
            font-weight:800;
            color:var(--hanania-primary);
            white-space:nowrap;
            text-overflow:ellipsis;
            overflow:hidden;
        }
        .sidebar-user-role{
            font-size:10.5px;
            font-weight:800;
            color:#fff;
            background:var(--hanania-primary);
            padding:2px 7px;
            border-radius:6px;
            display:inline-block;
            margin-top:3px;
            letter-spacing:.3px;
            text-transform:uppercase;
        }

        /* SIDEBAR NAV LINKS */
        .sidebar-nav{
            flex:1;
            padding:10px 14px;
            overflow-y:auto;
            display:flex;
            flex-direction:column;
            gap:4px;
        }
        .sidebar-nav-title{
            font-size:10px;
            font-weight:800;
            color:#8F82A3;
            text-transform:uppercase;
            letter-spacing:.8px;
            padding:12px 10px 6px;
        }
        .sidebar-item{
            display:flex;
            align-items:center;
            gap:12px;
            padding:11px 14px;
            border-radius:12px;
            font-size:13px;
            font-weight:700;
            color:#524765;
            transition:all .18s;
        }
        .sidebar-item-icon{
            font-size:18px;
            width:24px;
            display:flex;
            align-items:center;
            justify-content:center;
        }
        .sidebar-item:hover{
            background:var(--hanania-purple-soft);
            color:var(--hanania-primary);
            transform:translateX(3px);
        }
        .sidebar-item.active{
            background:var(--hanania-primary);
            color:#fff;
            box-shadow:0 6px 16px rgba(79,50,147,.25);
        }
        .sidebar-item.active .sidebar-item-icon{
            filter:brightness(1.2);
        }

        /* SIDEBAR FOOTER */
        .sidebar-footer{
            padding:14px;
            border-top:1px solid var(--hanania-border);
            display:flex;
            flex-direction:column;
            gap:6px;
            background:#FAF8FD;
        }
        .sidebar-btn-link{
            display:flex;
            align-items:center;
            gap:10px;
            padding:9px 12px;
            border-radius:10px;
            font-size:12.5px;
            font-weight:700;
            color:#5A4F6D;
            transition:.15s;
        }
        .sidebar-btn-link:hover{
            background:var(--hanania-purple-soft);
            color:var(--hanania-primary);
        }
        .sidebar-btn-logout{
            color:#C0392B!important;
        }
        .sidebar-btn-logout:hover{
            background:#FDEAEA!important;
            color:#962D22!important;
        }

        /* MAIN CONTENT AREA */
        .admin-main{
            margin-left:var(--sidebar-width);
            flex:1;
            min-height:100vh;
            display:flex;
            flex-direction:column;
            width:calc(100% - var(--sidebar-width));
        }

        /* TOPBAR */
        .admin-topbar{
            height:68px;
            background:#FFFFFF;
            border-bottom:1px solid var(--hanania-border);
            position:sticky;
            top:0;
            z-index:990;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 30px;
            box-shadow:0 2px 10px rgba(79,50,147,.02);
        }
        .topbar-left{
            display:flex;
            align-items:center;
            gap:16px;
        }
        .sidebar-toggle-btn{
            display:none;
            background:#FAF8FD;
            border:1px solid var(--hanania-border);
            padding:7px 11px;
            border-radius:10px;
            font-size:18px;
            cursor:pointer;
            color:var(--hanania-primary);
        }
        .topbar-title h3{
            font-size:17px;
            font-weight:800;
            color:var(--hanania-primary);
            letter-spacing:-.2px;
        }
        .topbar-title p{
            font-size:11.5px;
            color:var(--hanania-muted);
            font-weight:600;
        }
        .topbar-right{
            display:flex;
            align-items:center;
            gap:16px;
        }
        .topbar-date{
            font-size:12.5px;
            font-weight:700;
            color:var(--hanania-primary);
            background:var(--hanania-purple-soft);
            border:1px solid var(--hanania-lavender);
            padding:6px 14px;
            border-radius:10px;
            display:flex;
            align-items:center;
            gap:6px;
        }
        .topbar-btn-website{
            font-size:12px;
            font-weight:700;
            color:#fff;
            background:var(--hanania-primary);
            padding:7px 14px;
            border-radius:10px;
            transition:all .18s;
        }
        .topbar-btn-website:hover{
            background:var(--hanania-primary-hover);
            transform:translateY(-1px);
        }

        /* PAGE CONTENT */
        .admin-page{
            padding:28px 30px 60px;
            flex:1;
            max-width:1400px;
            width:100%;
        }

        .admin-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            margin-bottom:24px;
            flex-wrap:wrap;
        }
        .admin-header h1{
            font-size:22px;
            font-weight:900;
            color:var(--hanania-primary);
            display:flex;
            align-items:center;
            gap:10px;
            letter-spacing:-.3px;
        }
        .admin-header p{
            font-size:13px;
            color:var(--hanania-muted);
            margin-top:4px;
        }
        .admin-header-actions{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }

        /* CARDS */
        .admin-card{
            background:#FFFFFF;
            border:1px solid var(--hanania-border);
            border-radius:16px;
            padding:24px;
            margin-bottom:24px;
            box-shadow:0 4px 18px rgba(79,50,147,.03);
        }
        .admin-card-head{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:18px;
            flex-wrap:wrap;
            border-bottom:1px solid #F4EEFB;
            padding-bottom:14px;
        }
        .admin-card-title{
            font-size:16px;
            font-weight:800;
            color:var(--hanania-primary);
            display:flex;
            align-items:center;
            gap:8px;
        }
        .admin-card-subtitle{
            font-size:12px;
            color:var(--hanania-muted);
            margin-top:2px;
        }

        /* TABLES */
        .admin-table-wrap,
        .admin-table-responsive{
            width:100%;
            overflow-x:auto!important;
            -webkit-overflow-scrolling:touch;
            display:block;
            scrollbar-width:thin;
            scrollbar-color:#A68BC9 #F3EEFA;
        }
        .admin-table-wrap::-webkit-scrollbar,
        .admin-table-responsive::-webkit-scrollbar{
            height:8px;
        }
        .admin-table-wrap::-webkit-scrollbar-track,
        .admin-table-responsive::-webkit-scrollbar-track{
            background:#F3EEFA;
            border-radius:4px;
        }
        .admin-table-wrap::-webkit-scrollbar-thumb,
        .admin-table-responsive::-webkit-scrollbar-thumb{
            background:#A68BC9;
            border-radius:4px;
        }
        .admin-table-wrap::-webkit-scrollbar-thumb:hover,
        .admin-table-responsive::-webkit-scrollbar-thumb:hover{
            background:#7650A2;
        }
        table{
            width:100%;
            border-collapse:collapse;
            min-width:720px;
            font-size:13px;
        }
        th,td{
            padding:13px 16px;
            text-align:left;
            border-bottom:1px solid #F2ECF7;
            vertical-align:middle;
        }
        th{
            background:#FAF8FD;
            color:var(--hanania-primary);
            font-weight:800;
            font-size:11.5px;
            text-transform:uppercase;
            letter-spacing:.4px;
        }
        tr:last-child td{
            border-bottom:0;
        }
        tr:hover td{
            background:#FCFAFF;
        }

        /* BADGES */
        .badge{
            display:inline-flex;
            align-items:center;
            gap:4px;
            padding:4px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:800;
            text-transform:capitalize;
        }
        .badge-success{background:var(--success-bg);color:var(--success-text)}
        .badge-warning{background:var(--warning-bg);color:var(--warning-text)}
        .badge-danger{background:var(--danger-bg);color:var(--danger-text)}
        .badge-purple{background:var(--hanania-purple-soft);color:var(--hanania-primary);border:1px solid #E4D8F1}
        .badge-pink{background:#FDF2F8;color:#BE185D;border:1px solid #FBCFE8}
        .badge-light{background:#F8F6FB;color:#594E68;border:1px solid #EADBFA}
        .badge-gray{background:#EFEBF4;color:#675F73}

        /* BUTTONS */
        .btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:6px;
            padding:10px 18px;
            border-radius:10px;
            background:var(--hanania-primary);
            color:#fff;
            font-size:12.5px;
            font-weight:800;
            text-decoration:none;
            border:0;
            cursor:pointer;
            transition:all .18s;
            font-family:inherit;
        }
        .btn:hover{
            background:var(--hanania-primary-hover);
            transform:translateY(-1px);
            box-shadow:0 4px 12px rgba(79,50,147,.2);
        }
        .btn-light{
            background:var(--hanania-purple-soft);
            color:var(--hanania-primary);
        }
        .btn-light:hover{
            background:var(--hanania-lavender);
        }
        .btn-danger{
            background:#D33A3A;
            color:#fff;
        }
        .btn-danger:hover{
            background:#B72727;
        }
        .btn-sm{
            padding:6px 11px;
            font-size:11px;
            border-radius:8px;
        }

        /* FORMS */
        .form-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(230px, 1fr));
            gap:16px;
        }
        .form-field{
            display:flex;
            flex-direction:column;
            gap:6px;
        }
        .form-field.full{
            grid-column:1/-1;
        }
        .form-field label{
            font-size:12px;
            font-weight:800;
            color:#4E4458;
        }
        .form-field input, .form-field select, .form-field textarea{
            width:100%;
            padding:10px 12px;
            border:1px solid var(--hanania-border);
            border-radius:10px;
            font:inherit;
            font-size:13px;
            background:#fff;
            color:var(--hanania-text);
            transition:border .15s;
        }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus{
            outline:none;
            border-color:var(--hanania-primary);
            box-shadow:0 0 0 3px rgba(79,50,147,.12);
        }

        /* FILTER BAR */
        .admin-filter-bar{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:18px;
            background:#FAF8FD;
            padding:12px 16px;
            border-radius:12px;
            border:1px solid var(--hanania-border);
        }
        .admin-filter-group{
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
        }
        .filter-input{
            padding:8px 12px;
            border:1px solid var(--hanania-border);
            border-radius:8px;
            font-size:12px;
            font-family:inherit;
            background:#fff;
        }

        /* ALERTS */
        .alert{
            padding:13px 18px;
            border-radius:12px;
            margin-bottom:20px;
            font-size:13px;
            font-weight:700;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .alert-success{
            background:var(--success-bg);
            color:var(--success-text);
            border:1px solid #BEE8CA;
        }
        .alert-danger{
            background:var(--danger-bg);
            color:var(--danger-text);
            border:1px solid #F5C6C6;
        }

        /* BACKDROP UNTUK MOBILE */
        .sidebar-backdrop{
            display:none;
            position:fixed;
            top:0;
            left:0;
            right:0;
            bottom:0;
            background:rgba(0,0,0,.45);
            z-index:1050;
        }
        .sidebar-backdrop.active{display:block}

        /* =========================================================================
           RESPONSIF MOBILE & SMARTPHONE KHUSUS PANEL ADMIN
           (Hanya aktif pada resolusi <= 960px dan <= 540px, Desktop > 960px 100% UTUH)
           ========================================================================= */
        html, body {
            max-width: 100vw;
            overflow-x: hidden;
        }

        @media(max-width:960px){
            /* SIDEBAR MOBILE */
            .admin-sidebar{
                width: min(290px, 86vw);
                transform: translateX(-100%);
                z-index: 1200;
                box-shadow: 4px 0 28px rgba(15, 8, 30, 0.22);
            }
            .admin-sidebar.open{
                transform: translateX(0);
            }
            .admin-main{
                margin-left: 0;
                width: 100%;
                min-width: 0;
                max-width: 100vw;
                overflow-x: hidden;
            }
            .sidebar-toggle-btn{
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                border-radius: 10px;
                font-size: 20px;
            }
            .admin-topbar{
                padding: 0 16px;
                height: 60px;
                max-width: 100vw;
            }
            .topbar-left{
                gap: 12px;
                min-width: 0;
                flex: 1;
            }
            .topbar-title h3{
                font-size: 15px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 200px;
            }
            .topbar-title p{
                display: none;
            }
            .topbar-date{
                display: none;
            }
            .topbar-btn-website{
                font-size: 11.5px;
                padding: 7px 12px;
                white-space: nowrap;
                border-radius: 8px;
            }

            /* ADMIN PAGE & HEADER */
            .admin-page{
                padding: 16px 14px 60px !important;
                width: 100% !important;
                min-width: 0;
                max-width: 100vw;
                box-sizing: border-box;
            }
            .admin-header{
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                margin-bottom: 18px;
            }
            .admin-header h1{
                font-size: 20px;
                line-height: 1.25;
            }
            .admin-header p{
                font-size: 12.5px;
                line-height: 1.45;
            }
            .admin-header-actions{
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .admin-header-actions .btn{
                width: 100%;
                text-align: center;
                justify-content: center;
                padding: 10px 14px;
            }

            /* CARDS & CONTAINERS */
            .admin-card{
                padding: 18px 14px;
                border-radius: 14px;
                margin-bottom: 18px;
                box-shadow: 0 2px 10px rgba(79, 50, 147, 0.04);
            }
            .admin-card-head{
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
                margin-bottom: 14px;
            }
            .admin-card-title{
                font-size: 15.5px;
                line-height: 1.3;
            }

            /* FILTER BARS & SEARCH */
            .admin-filter-bar{
                padding: 14px 12px;
                border-radius: 12px;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                margin-bottom: 16px;
            }
            .admin-filter-bar form{
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .admin-filter-group{
                flex-direction: column;
                align-items: stretch;
                width: 100%;
                gap: 8px;
            }
            .admin-filter-group .btn{
                width: 100%;
                justify-content: center;
            }

            /* FORMS & INPUTS RESPONSIVE */
            .form-grid{
                grid-template-columns: 1fr !important;
                gap: 14px !important;
            }
            form[style*="grid-template-columns"],
            div[style*="grid-template-columns:360px"],
            div[style*="grid-template-columns: 360px"],
            div[style*="grid-template-columns:340px"],
            div[style*="grid-template-columns: 340px"],
            div[style*="grid-template-columns:320px"],
            div[style*="grid-template-columns: 320px"],
            div[style*="grid-template-columns:300px"],
            div[style*="grid-template-columns: 300px"],
            div[style*="grid-template-columns:280px"],
            div[style*="grid-template-columns: 280px"],
            div[style*="grid-template-columns:1fr 1fr"],
            div[style*="grid-template-columns: 1fr 1fr"],
            div[style*="grid-template-columns:2fr 1fr"],
            div[style*="grid-template-columns: 2fr 1fr"],
            div[style*="grid-template-columns:1fr 2fr"],
            div[style*="grid-template-columns: 1fr 2fr"],
            div[style*="grid-template-columns:2fr 1.2fr"],
            div[style*="grid-template-columns: 2fr 1.2fr"],
            div[style*="grid-template-columns:1.2fr 2fr"],
            div[style*="grid-template-columns: 1.2fr 2fr"],
            div[style*="grid-template-columns:1fr 1.5fr"],
            div[style*="grid-template-columns: 1fr 1.5fr"],
            div[style*="grid-template-columns:1.5fr 1fr"],
            div[style*="grid-template-columns: 1.5fr 1fr"],
            div[style*="grid-template-columns:repeat(auto-fit, minmax(280px"],
            div[style*="grid-template-columns:repeat(auto-fit, minmax(260px"],
            div[style*="grid-template-columns:repeat(auto-fit, minmax(240px"],
            div[style*="grid-template-columns: repeat(auto-fit, minmax(240px"],
            div[style*="grid-template-columns:repeat(auto-fill, minmax("],
            div[style*="grid-template-columns: repeat(auto-fill, minmax("],
            .chatbot-admin-grid,
            .chatbot-top-grid,
            .dashboard-split-grid,
            .pengguna-stat-grid,
            .hero-grid-layout {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
            }

            /* HORIZONTAL PILL TABS PADA MOBILE (TIDAK BERTUMPUK) */
            .user-tabs-bar,
            .content-nav-bar,
            div[style*="overflow-x:auto"][style*="flex-wrap:wrap"],
            div[style*="gap:8px"][style*="flex-wrap:wrap"],
            div[style*="gap: 8px"][style*="flex-wrap:wrap"],
            div[style*="gap:6px"][style*="flex-wrap:wrap"],
            div[style*="gap: 6px"][style*="flex-wrap:wrap"] {
                display: flex !important;
                flex-wrap: nowrap !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                gap: 8px !important;
                padding-bottom: 6px !important;
                margin-bottom: 14px !important;
                scrollbar-width: none !important;
                width: 100% !important;
            }
            .user-tabs-bar::-webkit-scrollbar,
            .content-nav-bar::-webkit-scrollbar,
            div[style*="gap:8px"][style*="flex-wrap:wrap"]::-webkit-scrollbar,
            div[style*="gap: 8px"][style*="flex-wrap:wrap"]::-webkit-scrollbar,
            div[style*="gap:6px"][style*="flex-wrap:wrap"]::-webkit-scrollbar,
            div[style*="gap: 6px"][style*="flex-wrap:wrap"]::-webkit-scrollbar {
                display: none !important;
            }
            .content-nav-btn,
            div[style*="gap:8px"][style*="flex-wrap:wrap"] > a,
            div[style*="gap: 8px"][style*="flex-wrap:wrap"] > a,
            div[style*="gap:6px"][style*="flex-wrap:wrap"] > a,
            div[style*="gap: 6px"][style*="flex-wrap:wrap"] > a,
            div[style*="gap:8px"][style*="flex-wrap:wrap"] > button,
            div[style*="gap: 8px"][style*="flex-wrap:wrap"] > button,
            div[style*="gap:6px"][style*="flex-wrap:wrap"] > button,
            div[style*="gap: 6px"][style*="flex-wrap:wrap"] > button {
                flex-shrink: 0 !important;
                white-space: nowrap !important;
            }

            .form-field, .form-group{
                width: 100% !important;
                margin-bottom: 14px !important;
            }
            .form-field label, .form-group label, .form-label{
                display: block !important;
                font-size: 12.5px !important;
                font-weight: 700 !important;
                color: var(--hanania-text) !important;
                margin-bottom: 6px !important;
            }
            input[type="text"], input[type="email"], input[type="tel"], input[type="password"],
            input[type="date"], input[type="time"], input[type="number"], select, textarea, .filter-input, .form-field input, .form-field select, .form-field textarea{
                width: 100% !important;
                max-width: 100% !important;
                min-height: 42px !important;
                font-size: 14px !important;
                border-radius: 10px !important;
                padding: 9px 12px !important;
                box-sizing: border-box !important;
            }
            textarea{
                min-height: 84px !important;
            }

            /* BUTTONS & ACTIONS TOUCH COMFORT */
            .btn{
                min-height: 40px;
                padding: 9px 14px;
                font-size: 13px;
                font-weight: 700;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                box-sizing: border-box;
            }
            .btn-sm{
                min-height: 34px;
                padding: 6px 12px;
                font-size: 12px;
            }

            /* NONAKTIFKAN STICKY COLUMN DI MOBILE AGAR TIDAK MENUTUPI TABEL */
            .sticky-col-right,
            th.sticky-col-right,
            td.sticky-col-right {
                position: static !important;
                width: auto !important;
                box-shadow: none !important;
                background: transparent !important;
            }

            /* ACTION BUTTONS DALAM TABEL (EDIT, HAPUS, LIHAT) */
            td div[style*="display:inline-flex"],
            td div[style*="display:flex"],
            td div[style*="gap:"]{
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 5px !important;
                align-items: center !important;
                justify-content: flex-start !important;
                width: auto !important;
            }
            td .btn, td .btn-sm, td a.btn, td button.btn{
                white-space: nowrap !important;
                min-height: 30px !important;
                padding: 5px 8px !important;
                font-size: 11px !important;
                border-radius: 7px !important;
                margin: 0 !important;
            }

            /* TABLE RESPONSIVE WITH SMOOTH HORIZONTAL SCROLL */
            .admin-table-wrap, .admin-table-responsive, .table-responsive{
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                display: block !important;
                border: 1px solid var(--hanania-border) !important;
                border-radius: 12px !important;
                background: #FFFFFF !important;
                margin-bottom: 16px !important;
            }
            .admin-table-wrap::-webkit-scrollbar,
            .admin-table-responsive::-webkit-scrollbar{
                height: 6px;
            }
            .admin-table-wrap::-webkit-scrollbar-thumb,
            .admin-table-responsive::-webkit-scrollbar-thumb{
                background: #DCCFE8;
                border-radius: 3px;
            }
            table{
                width: 100% !important;
                min-width: 620px;
                border-collapse: collapse !important;
            }
            th, td{
                padding: 10px 12px;
                font-size: 12px;
            }

            /* MODAL DAN POPUP MOBILE */
            .modal-overlay,
            div[id*="modal"][style*="position:fixed"],
            div[id*="Modal"][style*="position:fixed"]{
                padding: 14px !important;
                align-items: center !important;
                justify-content: center !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            .modal-overlay > div,
            div[id*="modal"][style*="position:fixed"] > div,
            div[id*="Modal"][style*="position:fixed"] > div{
                width: calc(100vw - 24px) !important;
                max-width: calc(100vw - 24px) !important;
                max-height: calc(100vh - 40px) !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 18px 14px !important;
                margin: auto !important;
                box-sizing: border-box !important;
                border-radius: 14px !important;
            }

            .admin-main-footer{
                padding: 14px 16px !important;
                font-size: 11px !important;
                flex-direction: column;
                text-align: center;
                gap: 6px;
            }
        }

        @media(max-width:540px){
            .admin-topbar{
                padding: 0 10px;
                height: 56px;
            }
            .admin-page{
                padding: 12px 10px 60px !important;
            }
            .admin-card{
                padding: 14px 12px;
                border-radius: 12px;
            }
            .admin-card-title{
                font-size: 14.5px;
            }
            .admin-header h1{
                font-size: 18px;
            }
            table{
                min-width: 520px;
            }
            th, td{
                padding: 8px 10px;
                font-size: 11.5px;
            }
            .badge{
                font-size: 10px;
                padding: 3px 7px;
            }
            .btn{
                min-height: 38px;
                padding: 8px 12px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- BACKDROP MOBILE -->
    <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

    <!-- SIDEBAR KIRI -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-logo">
                <img src="<?= $base ?>/assets/img/logo.jpg" alt="Logo Klinik" onerror="this.onerror=null;this.parentElement.innerHTML='🩺';">
            </div>
            <div class="sidebar-brand-text">
                <h2>Klinik Pratama<br>Rumah Hanania</h2>
                <p>Portal Admin Klinik</p>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="sidebar-avatar">🛡️</div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= adminEsc($adminUser) ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-nav-title">Menu Utama</div>
            <a href="index.php" class="sidebar-item <?= $activeTab === 'dashboard' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">📊</span>
                <span>Dashboard</span>
            </a>
            <a href="dokter.php" class="sidebar-item <?= $activeTab === 'dokter' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">🩺</span>
                <span>Dokter &amp; Bidan</span>
            </a>
            <a href="jadwal.php" class="sidebar-item <?= $activeTab === 'jadwal' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">📅</span>
                <span>Jadwal Praktik</span>
            </a>
            <a href="pasien.php" class="sidebar-item <?= $activeTab === 'pasien' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">👥</span>
                <span>Data Pasien</span>
            </a>
            <a href="pendaftaran.php" class="sidebar-item <?= $activeTab === 'pendaftaran' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">📝</span>
                <span>Data Pendaftaran</span>
            </a>

            <div class="sidebar-nav-title">Laporan &amp; Analitik</div>
            <a href="laporan.php" class="sidebar-item <?= $activeTab === 'laporan' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">📈</span>
                <span>Laporan Pendaftaran</span>
            </a>

            <div class="sidebar-nav-title">Website Publik</div>
            <a href="layanan.php" class="sidebar-item <?= $activeTab === 'layanan' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">💳</span>
                <span>Tarif &amp; Layanan</span>
            </a>
            <a href="layanan_pendaftaran.php" class="sidebar-item <?= $activeTab === 'layanan_pendaftaran' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">📋</span>
                <span>Layanan Pendaftaran</span>
            </a>
            <a href="poin.php" class="sidebar-item <?= $activeTab === 'poin' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">⭐</span>
                <span>Kelola Poin &amp; Voucher</span>
            </a>
            <a href="ulasan.php" class="sidebar-item <?= $activeTab === 'ulasan' ? 'active' : '' ?>" style="display:flex;align-items:center;justify-content:space-between;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <span class="sidebar-item-icon">💬</span>
                    <span>Verifikasi Ulasan</span>
                </div>
                <?php if ($pendingUlasanCount > 0): ?>
                    <span style="background:#E11D48;color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:999px;box-shadow:0 2px 6px rgba(225,29,72,0.35);">
                        <?= $pendingUlasanCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="konten.php" class="sidebar-item <?= $activeTab === 'konten' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">🖼️</span>
                <span>Kelola Konten</span>
            </a>
            <a href="chatbot.php" class="sidebar-item <?= $activeTab === 'chatbot' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">🤖</span>
                <span>Kelola Chatbot</span>
            </a>

            <div class="sidebar-nav-title">Manajemen Akun</div>
            <a href="pengguna.php" class="sidebar-item <?= $activeTab === 'pengguna' ? 'active' : '' ?>">
                <span class="sidebar-item-icon">🔐</span>
                <span>Kelola Akun</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="<?= $base ?>/index.php" class="sidebar-btn-link" target="_blank" rel="noopener">
                <span>🌐</span>
                <span>Buka Website Publik</span>
            </a>
            <a href="logout.php" class="sidebar-btn-link sidebar-btn-logout">
                <span>🚪</span>
                <span>Keluar (Logout)</span>
            </a>
            <div style="padding:8px 4px 2px;font-size:10.5px;color:#8F82A3;text-align:center;line-height:1.4;border-top:1px dashed var(--hanania-lavender);margin-top:4px;">
                Credit by @nabila.pisesa@gmail.com
            </div>
        </div>
    </aside>

    <!-- AREA KONTEN UTAMA -->
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <button type="button" class="sidebar-toggle-btn" onclick="toggleSidebar()" aria-label="Buka Menu">☰</button>
                <div class="topbar-title">
                    <h3><?= adminEsc($title) ?></h3>
                    <p>Klinik Pratama Rumah Hanania</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-date">
                    <span>🗓️</span>
                    <span><?= date('d M Y') ?></span>
                </div>
                <a href="<?= $base ?>/index.php" class="topbar-btn-website" target="_blank" rel="noopener">
                    Lihat Website &rarr;
                </a>
            </div>
        </header>
    <?php
}

function adminPageEnd(): void {
    ?>
        <footer class="admin-main-footer" style="padding:16px 30px;border-top:1px solid var(--hanania-border);background:#FAF8FD;font-size:12px;color:var(--hanania-muted);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:auto;">
            <span>&copy; <?= date('Y') ?> Klinik Pratama Rumah Hanania. Hak Cipta Dilindungi.</span>
            <span style="font-weight:700;color:var(--hanania-primary);">Credit by @nabila.pisesa@gmail.com</span>
        </footer>
    </div> <!-- /admin-main -->

    <script>
    function toggleSidebar(){
        const sidebar = document.getElementById('adminSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if(!sidebar) return;
        sidebar.classList.toggle('open');
        if(backdrop) backdrop.classList.toggle('active');
    }

    window.addEventListener('resize', function(){
        if(window.innerWidth > 960){
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if(sidebar) sidebar.classList.remove('open');
            if(backdrop) backdrop.classList.remove('active');
        }
    });

    // Pertahankan posisi scroll sidebar admin saat navigasi halaman
    (function() {
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (sidebarNav) {
            const savedScroll = sessionStorage.getItem('admin_sidebar_scroll');
            if (savedScroll !== null) {
                sidebarNav.scrollTop = parseInt(savedScroll, 10);
            }
            sidebarNav.addEventListener('scroll', function() {
                sessionStorage.setItem('admin_sidebar_scroll', sidebarNav.scrollTop);
            }, { passive: true });
        }
    })();
    </script>
</body>
</html>
    <?php
}
