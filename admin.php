<?php
// 后台管理页面
// 包含登录验证、网站配置、项目管理和文章管理功能
require_once 'config.php';

if (!check_installed()) {
    header('Location: install.php');
    exit;
}

session_start();

// 处理登出请求
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// 验证管理员登录状态
if (!isset($_SESSION['admin_logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        
        $conn = get_db_connection();
        $stmt = $conn->query("SELECT admin_password FROM site_config LIMIT 1");
        $config = $stmt->fetch();
        
        if (password_verify($password, $config['admin_password'])) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $error = '密码错误';
        }
    }
}

// 未登录则显示登录页面
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>后台登录</title>
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #e0f7fa 0%, #f5f5f5 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .login-container {
                background: white;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                width: 90%;
                max-width: 400px;
                padding: 40px;
                text-align: center;
            }
            
            h2 {
                color: #2c3e50;
                margin-bottom: 30px;
            }
            
            input[type="password"] {
                width: 100%;
                padding: 15px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 16px;
                margin-bottom: 20px;
            }
            
            .btn {
                background: linear-gradient(90deg, #4fc3f7, #81c784);
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                width: 100%;
            }
            
            .error {
                color: #f44336;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>后台管理登录</h2>
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="请输入管理员密码" required>
                <button type="submit" class="btn">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 获取网站配置信息
$config = get_site_config();
$conn = get_db_connection();

// 处理请求参数，默认为dashboard页面
$action = $_GET['action'] ?? 'dashboard';

// 获取访问统计数据
$stats = get_visit_stats();

// 提取近7天的访问数据，用于生成图表
$weekly_stats = $stats['weekly'];

// 确保必要的目录存在
$directories = ['uploads/', 'uploads/projects/', 'picture/'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?= htmlspecialchars($config['site_name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }
        
        .admin-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(79, 195, 247, 0.2), transparent);
            z-index: 1;
        }
        
        .admin-header h1 {
            position: relative;
            z-index: 2;
            font-size: 1.8em;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        
        .admin-header a {
            position: relative;
            z-index: 2;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--magic-transition);
        }
        
        .admin-header a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .admin-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }
        
        .sidebar {
            width: 280px;
            background: var(--glass-bg);
            padding: 25px;
            box-shadow: 2px 0 20px rgba(0,0,0,0.08);
            backdrop-filter: blur(15px);
            border-right: 1px solid var(--glass-border);
        }
        
        .sidebar nav ul {
            list-style: none;
        }
        
        .sidebar nav li {
            margin-bottom: 10px;
        }
        
        .sidebar nav a {
            display: block;
            padding: 15px 20px;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--magic-transition);
            position: relative;
            overflow: hidden;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .sidebar nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(79, 195, 247, 0.2), transparent);
            transition: var(--transition);
        }
        
        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: linear-gradient(90deg, var(--primary-blue), var(--accent-green));
            color: white;
            transform: translateX(10px) translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 195, 247, 0.3);
            border-color: transparent;
        }
        
        .sidebar nav a:hover::before,
        .sidebar nav a.active::before {
            left: 100%;
        }
        
        .sidebar nav li {
            margin-bottom: 12px;
        }
        
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: var(--magic-transition);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-blue), var(--accent-green));
            transform: scaleX(0);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card h3 {
            color: var(--text-light);
            font-size: 1em;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .stat-card .number {
            font-size: 3em;
            font-weight: bold;
            color: var(--text-dark);
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .chart-container {
            background: var(--glass-bg);
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            transition: var(--magic-transition);
        }
        
        .chart-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1em;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: white;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4fc3f7;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05), 0 0 0 3px rgba(79, 195, 247, 0.1), 0 8px 30px rgba(79, 195, 247, 0.2);
            transform: translateY(-2px);
        }
        
        textarea.form-control {
            min-height: 180px;
            resize: vertical;
            font-family: monospace;
        }
        
        .btn {
            background: linear-gradient(90deg, #4fc3f7, #81c784);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--magic-transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(79, 195, 247, 0.4);
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: var(--transition);
        }
        
        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 40px rgba(79, 195, 247, 0.6);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:active {
            transform: translateY(-2px) scale(0.98);
        }
        
        .btn-danger {
            background: linear-gradient(90deg, #f44336, #ff5722);
        }
        
        .btn-secondary {
            background: linear-gradient(90deg, #424242, #616161);
        }
        
        .btn-info {
            background: linear-gradient(90deg, #2196F3, #03A9F4);
        }
        
        .btn-warning {
            background: linear-gradient(90deg, #ff9800, #ff5722);
        }
        
        .table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .table tr:hover {
            background: #f8f9fa;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .actions a {
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .edit-btn {
            background: #4fc3f7;
            color: white;
            box-shadow: 0 2px 8px rgba(79, 195, 247, 0.4);
        }
        
        .edit-btn:hover {
            background: #29b6f6;
            box-shadow: 0 4px 12px rgba(79, 195, 247, 0.6);
            transform: translateY(-1px);
        }
        
        .delete-btn {
            background: #f44336;
            color: white;
            box-shadow: 0 2px 8px rgba(244, 67, 54, 0.4);
        }
        
        .delete-btn:hover {
            background: #d32f2f;
            box-shadow: 0 4px 12px rgba(244, 67, 54, 0.6);
            transform: translateY(-1px);
        }
        
        .editor-toolbar {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px 8px 0 0;
            border: 2px solid #e0e0e0;
            border-bottom: none;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .editor-toolbar button {
            padding: 8px 15px;
            border: 1px solid #4fc3f7;
            background: linear-gradient(135deg, #ffffff, #f0f9ff);
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            color: #2c3e50;
            transition: all 0.3s ease;
        }
        
        .editor-toolbar button:hover {
            background: linear-gradient(135deg, #4fc3f7, #81c784);
            color: white;
            border-color: #4fc3f7;
            box-shadow: 0 2px 8px rgba(79, 195, 247, 0.4);
        }
        
        .editor-content {
            border: 2px solid #e0e0e0;
            border-radius: 0 0 8px 8px;
            padding: 20px;
            min-height: 400px;
            outline: none;
            font-family: monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            background: white;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .editor-content:focus {
            border-color: #4fc3f7;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.05), 0 0 0 3px rgba(79, 195, 247, 0.1);
        }
        
        .editor-content.placeholder {
            color: #999;
            font-style: italic;
        }
        
        .preview-area {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #ddd;
            max-height: 400px;
            overflow-y: auto;
        }
        
        /* 通知样式 */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            max-width: 400px;
            word-wrap: break-word;
        }
        
        .notification.success {
            background: #4CAF50;
        }
        
        .notification.error {
            background: #f44336;
        }
        
        .notification.info {
            background: #2196F3;
        }
        
        .notification.warning {
            background: #ff9800;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        /* 上传进度指示器 */
        .upload-progress {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 10001;
            text-align: center;
            display: none;
        }
        
        .progress-bar {
            width: 300px;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4fc3f7, #81c784);
            width: 0%;
            transition: width 0.3s;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .notification {
                left: 20px;
                right: 20px;
                max-width: none;
            }
        }

        /* 配色方案选择器样式 */
        .scheme-mode-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .scheme-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .scheme-tab input[type="radio"] {
            display: none;
        }

        .scheme-tab.active {
            border-color: #4fc3f7;
            background: linear-gradient(135deg, rgba(79, 195, 247, 0.1), rgba(129, 199, 132, 0.1));
            color: #2c3e50;
        }

        .scheme-tab:hover {
            border-color: #4fc3f7;
            transform: translateY(-2px);
        }

        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .preset-card {
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            padding: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .preset-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }

        .preset-card.selected {
            border-color: #4fc3f7;
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.3);
        }

        .preset-preview {
            height: 50px;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .preset-name {
            font-size: 0.85em;
            font-weight: 600;
            color: #2c3e50;
        }

        .color-picker {
            width: 100%;
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 4px;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .color-picker:hover {
            border-color: #4fc3f7;
        }

        /* 背景配置样式 */
        .bg-upload-area {
            border: 2px dashed #ccc;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            color: #888;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }

        .bg-upload-area:hover {
            border-color: #4fc3f7;
            background: rgba(79, 195, 247, 0.05);
        }

        .bg-upload-area.dragover {
            border-color: #81c784;
            background: rgba(129, 199, 132, 0.1);
        }

        .bg-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .bg-thumb {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #e0e0e0;
            aspect-ratio: 16/9;
            transition: all 0.3s ease;
        }

        .bg-thumb:hover {
            border-color: #4fc3f7;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        .bg-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .bg-thumb-remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            background: rgba(244, 67, 54, 0.85);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .bg-thumb:hover .bg-thumb-remove {
            opacity: 1;
        }

        /* 捐款配置样式 */
        .qrcode-upload-card {
            text-align: center;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 14px;
            transition: all 0.3s ease;
        }

        .qrcode-upload-card:hover {
            border-color: var(--primary-blue);
        }

        .qrcode-upload-card h4 {
            margin-bottom: 12px;
            color: #2c3e50;
        }

        .qrcode-preview {
            width: 160px;
            height: 160px;
            margin: 0 auto 10px;
            border-radius: 10px;
            border: 2px dashed #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fafafa;
        }

        .qrcode-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .qrcode-preview p {
            color: #bbb;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1><?= htmlspecialchars($config['site_name']) ?> - 后台管理</h1>
        <a href="index.php" style="color: white; text-decoration: none;">返回首页</a>
    </div>
    
    <div class="admin-container">
        <div class="sidebar">
            <nav>
                <ul>
                    <li><a href="?action=dashboard" class="<?= $action == 'dashboard' ? 'active' : '' ?>">📊 首页</a></li>
                    <li><a href="?action=config" class="<?= $action == 'config' ? 'active' : '' ?>">⚙️ 网站基本配置</a></li>
                    <li><a href="?action=projects" class="<?= $action == 'projects' ? 'active' : '' ?>">📁 项目管理</a></li>
                    <li><a href="?action=articles" class="<?= $action == 'articles' ? 'active' : '' ?>">📝 文章管理</a></li>
                    <li><a href="?action=donate" class="<?= $action == 'donate' ? 'active' : '' ?>">💖 捐款配置</a></li>
                    <li><a href="admin.php?logout=1" style="color: #f44336;">🚪 退出登录</a></li>
                </ul>
            </nav>
        </div>
        
        <div class="main-content">
            <?php if ($action == 'dashboard'): ?>
                <h2>数据统计</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>当日访问IP数</h3>
                        <div class="number"><?= $stats['today'] ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3>近7日访问IP总数</h3>
                        <div class="number">
                            <?= array_sum(array_column($weekly_stats, 'count')) ?>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <h3>近7日访问趋势</h3>
                    <div style="height: 300px; display: flex; align-items: flex-end; gap: 10px; margin-top: 30px;">
                        <?php 
                        // 确保有7天的数据
                        $dates = [];
                        for ($i = 6; $i >= 0; $i--) {
                            $dates[date('Y-m-d', strtotime("-$i days"))] = 0;
                        }
                        
                        foreach ($weekly_stats as $stat) {
                            $dates[$stat['visit_date']] = $stat['count'];
                        }
                        
                        // 计算最大访问数
                        $max_count = max(array_values($dates));
                        // 计算缩放比例，确保最高的条形图不超过250px
                        $scale = $max_count > 0 ? 250 / $max_count : 1;
                        
                        foreach ($dates as $date => $count): 
                        // 计算条形图高度，最小为20px
                        $bar_height = max($count * $scale, 20);
                        ?>
                            <div style="flex: 1; text-align: center;">
                                <div style="height: <?= $bar_height ?>px; 
                                            background: linear-gradient(to top, #4fc3f7, #81c784); 
                                            border-radius: 5px 5px 0 0;"></div>
                                <div style="margin-top: 10px; font-size: 0.9em;"><?= 
                                    date('m-d', strtotime($date)) ?></div>
                                <div style="font-weight: bold;"><?= $count ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
            <?php elseif ($action == 'config'): ?>
                <h2>网站基本配置</h2>
                
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $site_name = $_POST['site_name'] ?? '';
                    $welcome_text = $_POST['welcome_text'] ?? '';
                    $qq = $_POST['qq'] ?? '';
                    $email = $_POST['email'] ?? '';
                    $footer_text = $_POST['footer_text'] ?? '';
                    $icp = $_POST['icp'] ?? '';
                    $me_content = $_POST['me_content'] ?? '';
                    $custom_footer_html = $_POST['custom_footer_html'] ?? '';
                    $color_scheme = $_POST['color_scheme'] ?? '';
                    $background_config = $_POST['background_config'] ?? '';
                    $donate_config = $_POST['donate_config'] ?? '';

                    // 更新数据库配置
                    $stmt = $conn->prepare("
                        UPDATE site_config
                        SET site_name = ?, welcome_text = ?, qq = ?, email = ?, footer_text = ?, icp = ?, custom_footer_html = ?, color_scheme = ?, background_config = ?, donate_config = ?
                        WHERE id = 1
                    ");
                    $stmt->execute([$site_name, $welcome_text, $qq, $email, $footer_text, $icp, $custom_footer_html, $color_scheme, $background_config, $donate_config]);
                    
                    // 保存me.md文件
                    file_put_contents('me.md', $me_content);
                    
                    echo '<div class="notification success">配置已保存！</div>';
                    
                    // 重新获取配置
                    $config = get_site_config();
                }
                ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label>网站名称</label>
                        <input type="text" name="site_name" class="form-control" 
                               value="<?= htmlspecialchars($config['site_name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>欢迎语</label>
                        <textarea name="welcome_text" class="form-control" rows="3" required><?= 
                            htmlspecialchars($config['welcome_text']) ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>QQ号（选填）</label>
                        <input type="text" name="qq" class="form-control" 
                               value="<?= htmlspecialchars($config['qq'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>邮箱（选填）</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($config['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>页脚内容（选填）</label>
                        <textarea name="footer_text" class="form-control" rows="2"><?= 
                            htmlspecialchars($config['footer_text'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>备案号（选填）</label>
                        <input type="text" name="icp" class="form-control"
                               value="<?= htmlspecialchars($config['icp'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>自定义页脚HTML（选填，支持直接写入HTML代码）</label>
                        <textarea name="custom_footer_html" class="form-control" rows="8"
                                  style="font-family: monospace;"
                                  placeholder="例如：<div style=&quot;text-align:center;&quot;><a href=&quot;https://example.com&quot;>友情链接</a></div>"><?= htmlspecialchars($config['custom_footer_html'] ?? '') ?></textarea>
                        <small style="color: #666;">输入的HTML将直接渲染在页面页脚区域，请确保代码安全</small>
                    </div>

                    <div class="form-group">
                        <label>网站配色方案</label>
                        <?php
                        $current_scheme = !empty($config['color_scheme']) ? json_decode($config['color_scheme'], true) : [];
                        $current_mode = $current_scheme['mode'] ?? 'preset';
                        $current_preset = $current_scheme['preset'] ?? 'default';
                        $presets = get_color_presets();
                        ?>
                        <div class="color-scheme-section">
                            <div class="scheme-mode-tabs">
                                <label class="scheme-tab <?= $current_mode === 'preset' ? 'active' : '' ?>">
                                    <input type="radio" name="scheme_mode" value="preset" <?= $current_mode === 'preset' ? 'checked' : '' ?> onchange="toggleSchemeMode('preset')"> 预设渐变
                                </label>
                                <label class="scheme-tab <?= $current_mode === 'custom' ? 'active' : '' ?>">
                                    <input type="radio" name="scheme_mode" value="custom" <?= $current_mode === 'custom' ? 'checked' : '' ?> onchange="toggleSchemeMode('custom')"> 自定义渐变
                                </label>
                            </div>

                            <div id="presetSection" style="<?= $current_mode !== 'preset' ? 'display:none' : '' ?>">
                                <div class="preset-grid">
                                    <?php foreach ($presets as $key => $preset): ?>
                                    <div class="preset-card <?= $current_preset === $key ? 'selected' : '' ?>"
                                         onclick="selectPreset('<?= $key ?>')">
                                        <div class="preset-preview" style="background: linear-gradient(135deg, <?= $preset['primary_blue'] ?>, <?= $preset['accent_green'] ?>);"></div>
                                        <div class="preset-name"><?= $preset['name'] ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div id="customSection" style="<?= $current_mode !== 'custom' ? 'display:none' : '' ?>">
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 10px;">
                                    <div>
                                        <label style="font-size:0.9em; margin-bottom:5px;">背景浅色</label>
                                        <input type="color" id="custom_primary_light" value="<?= htmlspecialchars($current_scheme['primary_light'] ?? '#e0f7fa') ?>" class="color-picker">
                                    </div>
                                    <div>
                                        <label style="font-size:0.9em; margin-bottom:5px;">主色调</label>
                                        <input type="color" id="custom_primary_blue" value="<?= htmlspecialchars($current_scheme['primary_blue'] ?? '#4fc3f7') ?>" class="color-picker">
                                    </div>
                                    <div>
                                        <label style="font-size:0.9em; margin-bottom:5px;">强调色</label>
                                        <input type="color" id="custom_accent_green" value="<?= htmlspecialchars($current_scheme['accent_green'] ?? '#81c784') ?>" class="color-picker">
                                    </div>
                                </div>
                                <div style="margin-top:15px;">
                                    <label style="font-size:0.9em; margin-bottom:5px;">预览</label>
                                    <div id="customPreview" style="height:40px; border-radius:8px; background: linear-gradient(135deg, <?= htmlspecialchars($current_scheme['primary_blue'] ?? '#4fc3f7') ?>, <?= htmlspecialchars($current_scheme['accent_green'] ?? '#81c784') ?>);"></div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="color_scheme" id="colorSchemeInput" value="<?= htmlspecialchars($config['color_scheme'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>自定义背景</label>
                        <?php
                        $bg_config = !empty($config['background_config']) ? json_decode($config['background_config'], true) : [];
                        $bg_mode = $bg_config['mode'] ?? 'none';
                        $bg_api_url = $bg_config['api_url'] ?? '';
                        $bg_images = $bg_config['images'] ?? [];
                        $bg_display = $bg_config['display'] ?? 'fixed';
                        $bg_interval = $bg_config['interval'] ?? 10;
                        ?>
                        <div class="bg-section">
                            <div class="scheme-mode-tabs" style="margin-bottom:15px;">
                                <label class="scheme-tab <?= $bg_mode === 'none' ? 'active' : '' ?>">
                                    <input type="radio" name="bg_mode" value="none" <?= $bg_mode === 'none' ? 'checked' : '' ?> onchange="toggleBgMode('none')"> 无背景
                                </label>
                                <label class="scheme-tab <?= $bg_mode === 'api' ? 'active' : '' ?>">
                                    <input type="radio" name="bg_mode" value="api" <?= $bg_mode === 'api' ? 'checked' : '' ?> onchange="toggleBgMode('api')"> API 随机图
                                </label>
                                <label class="scheme-tab <?= $bg_mode === 'image' ? 'active' : '' ?>">
                                    <input type="radio" name="bg_mode" value="image" <?= $bg_mode === 'image' ? 'checked' : '' ?> onchange="toggleBgMode('image')"> 上传图片
                                </label>
                            </div>

                            <div id="bgNoneSection" style="<?= $bg_mode !== 'none' ? 'display:none' : '' ?>">
                                <p style="color:#888;">使用默认渐变背景</p>
                            </div>

                            <div id="bgApiSection" style="<?= $bg_mode !== 'api' ? 'display:none' : '' ?>">
                                <div class="form-group" style="margin-bottom:10px;">
                                    <label style="font-size:0.9em;">API 地址</label>
                                    <input type="url" id="bgApiUrl" class="form-control" value="<?= htmlspecialchars($bg_api_url) ?>"
                                           placeholder="例如：https://api.example.com/random.jpg" oninput="updateBgConfig()">
                                </div>
                                <small style="color:#666;">输入随机图片 API 地址，每次访问页面会获取不同背景图</small>
                            </div>

                            <div id="bgImageSection" style="<?= $bg_mode !== 'image' ? 'display:none' : '' ?>">
                                <div class="bg-upload-area" id="bgDropZone">
                                    <p>点击或拖拽图片到此处上传（支持多张）</p>
                                    <input type="file" id="bgFileInput" accept="image/*" multiple style="display:none" onchange="handleBgUpload(this.files)">
                                </div>

                                <div class="bg-gallery" id="bgGallery">
                                    <?php foreach ($bg_images as $idx => $img): ?>
                                    <div class="bg-thumb" data-path="<?= htmlspecialchars($img) ?>">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="背景图">
                                        <div class="bg-thumb-remove" onclick="removeBgImage(<?= $idx ?>)">&times;</div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="bg-display-options" style="margin-top:15px;">
                                    <label style="font-size:0.9em; margin-bottom:8px; display:block;">显示方式</label>
                                    <div style="display:flex; gap:10px;">
                                        <label class="scheme-tab <?= $bg_display === 'fixed' ? 'active' : '' ?>" style="padding:8px 16px;">
                                            <input type="radio" name="bg_display" value="fixed" <?= $bg_display === 'fixed' ? 'checked' : '' ?> onchange="toggleBgDisplay('fixed')"> 固定背景
                                        </label>
                                        <label class="scheme-tab <?= $bg_display === 'slideshow' ? 'active' : '' ?>" style="padding:8px 16px;">
                                            <input type="radio" name="bg_display" value="slideshow" <?= $bg_display === 'slideshow' ? 'checked' : '' ?> onchange="toggleBgDisplay('slideshow')"> 轮播切换
                                        </label>
                                    </div>
                                </div>

                                <div id="bgIntervalSection" style="margin-top:15px; <?= $bg_display !== 'slideshow' ? 'display:none' : '' ?>">
                                    <label style="font-size:0.9em; margin-bottom:5px;">切换间隔（秒）</label>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <input type="range" id="bgIntervalRange" min="3" max="60" value="<?= $bg_interval ?>" style="flex:1;" oninput="document.getElementById('bgIntervalValue').textContent=this.value; updateBgConfig()">
                                        <span id="bgIntervalValue" style="font-weight:bold; min-width:30px; text-align:center;"><?= $bg_interval ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="background_config" id="bgConfigInput" value="<?= htmlspecialchars($config['background_config'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>个人简介（Markdown格式）</label>
                        <textarea name="me_content" class="form-control" rows="15" style="font-family: monospace;"><?= 
                            htmlspecialchars($config['me_content'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">保存配置</button>
                </form>
                
            <?php elseif ($action == 'projects'): ?>
                <h2>项目管理</h2>
                
                <?php
                // 处理项目操作
                if (isset($_GET['delete'])) {
                    $id = intval($_GET['delete']);
                    $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
                    $stmt->execute([$id]);
                    echo '<div class="notification success">项目已删除！</div>';
                }
                
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? 0;
                    $name = $_POST['name'] ?? '';
                    $link = $_POST['link'] ?? '';
                    $description = $_POST['description'] ?? '';
                    
                    // 处理图标上传
                    $icon_path = $_POST['existing_icon'] ?? '';
                    if (!empty($_FILES['icon']['name'])) {
                        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        if (in_array($_FILES['icon']['type'], $allowed)) {
                            $upload_dir = 'uploads/projects/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            // 转换为webp格式
                            $webp_filename = uniqid() . '.webp';
                            $webp_path = $upload_dir . $webp_filename;
                            
                            if (convert_to_webp($_FILES['icon']['tmp_name'], $webp_path)) {
                                $icon_path = $webp_path;
                            } else {
                                // 如果转换失败，保存原图
                                $original_name = $_FILES['icon']['name'];
                                $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                                $filename = uniqid() . '.' . $ext;
                                move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $filename);
                                $icon_path = $upload_dir . $filename;
                            }
                        }
                    }
                    
                    if ($id > 0) {
                        // 更新项目
                        $stmt = $conn->prepare("
                            UPDATE projects 
                            SET name = ?, icon_path = ?, link = ?, description = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $icon_path, $link, $description, $id]);
                    } else {
                        // 新增项目
                        $stmt = $conn->prepare("
                            INSERT INTO projects (name, icon_path, link, description) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $icon_path, $link, $description]);
                    }
                    
                    echo '<div class="notification success">项目已保存！</div>';
                }
                
                // 编辑项目
                $edit_id = $_GET['edit'] ?? 0;
                $edit_project = null;
                if ($edit_id) {
                    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
                    $stmt->execute([$edit_id]);
                    $edit_project = $stmt->fetch();
                }
                ?>
                
                <!-- 项目表单 -->
                <h3><?= $edit_id ? '编辑项目' : '新增项目' ?></h3>
                <form method="POST" enctype="multipart/form-data" style="margin-bottom: 30px;">
                    <input type="hidden" name="id" value="<?= $edit_id ?>">
                    
                    <div class="form-group">
                        <label>项目名称</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?= htmlspecialchars($edit_project['name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>项目图标（选填，自动转为webp格式）</label>
                        <?php if (!empty($edit_project['icon_path'])): ?>
                            <div style="margin-bottom: 10px;">
                                <?php $icon_path = fix_image_path($edit_project['icon_path']); ?>
                                <?php if (!empty($icon_path) && file_exists($icon_path)): ?>
                                    <img src="<?= htmlspecialchars($icon_path) ?>" alt="当前图标" style="height: 50px;">
                                <?php else: ?>
                                    <div style="color: #666;">图标文件不存在</div>
                                <?php endif; ?>
                                <input type="hidden" name="existing_icon" value="<?= htmlspecialchars($edit_project['icon_path']) ?>">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="icon" class="form-control" accept="image/*">
                        <small style="color: #666;">支持 JPG、PNG、GIF 格式，最大 2MB</small>
                    </div>
                    
                    <div class="form-group">
                        <label>项目链接（选填）</label>
                        <input type="url" name="link" class="form-control" 
                               value="<?= htmlspecialchars($edit_project['link'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>项目简介</label>
                        <textarea name="description" class="form-control" rows="5" required><?= 
                            htmlspecialchars($edit_project['description'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn">保存项目</button>
                    <?php if ($edit_id): ?>
                        <a href="?action=projects" class="btn btn-secondary" style="margin-left: 10px;">取消编辑</a>
                    <?php endif; ?>
                </form>
                
                <!-- 项目列表 -->
                <h3>项目列表</h3>
                <?php
                $stmt = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");
                $projects = $stmt->fetchAll();
                ?>
                
                <?php if (empty($projects)): ?>
                    <p>暂无项目，请添加项目。</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>名称</th>
                                <th>链接</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $project): ?>
                                <tr>
                                    <td><?= $project['id'] ?></td>
                                    <td><?= htmlspecialchars($project['name']) ?></td>
                                    <td><?= !empty($project['link']) ? htmlspecialchars($project['link']) : '无' ?></td>
                                    <td><?= date('Y-m-d', strtotime($project['created_at'])) ?></td>
                                    <td class="actions">
                                        <a href="?action=projects&edit=<?= $project['id'] ?>" class="edit-btn">编辑</a>
                                        <a href="?action=projects&delete=<?= $project['id'] ?>" 
                                           class="delete-btn" 
                                           onclick="return confirm('确定要删除这个项目吗？')">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
            <?php elseif ($action == 'articles'): ?>
                <h2>文章管理</h2>
                
                <?php
                // 处理文章操作
                if (isset($_GET['delete'])) {
                    $id = intval($_GET['delete']);
                    $stmt = $conn->prepare("DELETE FROM articles WHERE id = ?");
                    $stmt->execute([$id]);
                    echo '<div class="notification success">文章已删除！</div>';
                }
                
                // 处理文章保存
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $id = $_POST['id'] ?? 0;
                    $title = $_POST['title'] ?? '';
                    $content = $_POST['content'] ?? '';
                    
                    // 处理文章中的图片上传
                    $content = process_article_images($content);
                    
                    if ($id > 0) {
                        // 更新文章
                        $stmt = $conn->prepare("
                            UPDATE articles 
                            SET title = ?, content = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([$title, $content, $id]);
                    } else {
                        // 新增文章
                        $stmt = $conn->prepare("
                            INSERT INTO articles (title, content) 
                            VALUES (?, ?)
                        ");
                        $stmt->execute([$title, $content]);
                    }
                    
                    echo '<div class="notification success">文章已保存！</div>';
                    
                    // 如果是编辑状态，重新获取文章
                    if ($id > 0) {
                        $edit_id = $id;
                        $stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
                        $stmt->execute([$edit_id]);
                        $edit_article = $stmt->fetch();
                    }
                }
                
                // 编辑文章
                $edit_id = $_GET['edit'] ?? 0;
                $edit_article = null;
                if ($edit_id) {
                    $stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
                    $stmt->execute([$edit_id]);
                    $edit_article = $stmt->fetch();
                }
                ?>
                
                <!-- 文章编辑器 -->
                <h3><?= $edit_id ? '编辑文章' : '发布文章' ?></h3>
                <form method="POST" id="articleForm" style="margin-bottom: 30px;">
                    <input type="hidden" name="id" value="<?= $edit_id ?>">
                    
                    <div class="form-group">
                        <label>文章标题</label>
                        <input type="text" name="title" id="articleTitle" class="form-control" 
                               value="<?= htmlspecialchars($edit_article['title'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>文章内容（Markdown格式）</label>
                        
                        <div class="editor-toolbar">
                            <button type="button" onclick="insertText('# ', 'h1')" title="一级标题">H1</button>
                            <button type="button" onclick="insertText('## ', 'h2')" title="二级标题">H2</button>
                            <button type="button" onclick="insertText('### ', 'h3')" title="三级标题">H3</button>
                            <button type="button" onclick="insertText('**', 'bold')" title="加粗"><strong>B</strong></button>
                            <button type="button" onclick="insertText('*', 'italic')" title="斜体"><em>I</em></button>
                            <button type="button" onclick="insertText('[链接文字](URL)', 'link')" title="链接">🔗</button>
                            <button type="button" onclick="triggerImageUpload()" title="上传图片">📤 上传图片</button>
                            <input type="file" id="imageUpload" accept="image/*" style="display: none;" 
                                   onchange="uploadImageFromFile(this.files[0])">
                            <button type="button" onclick="insertText('```\n代码\n```', 'code')" title="代码块">📝</button>
                            <button type="button" onclick="insertText('> ', 'quote')" title="引用">❝</button>
                            <button type="button" onclick="insertText('- ', 'list')" title="列表">•</button>
                        </div>
                        
                        <div class="editor-content" 
                             id="articleContent" 
                             contenteditable="true"
                             oninput="updateContent()"
                             onfocus="removePlaceholder()"
                             onblur="addPlaceholder()">
                            <?php if (!empty($edit_article['content'])): ?>
                                <?= htmlspecialchars($edit_article['content']) ?>
                            <?php else: ?>
                                <span class="placeholder">在此处编写文章内容...</span>
                            <?php endif; ?>
                        </div>
                        
                        <textarea name="content" id="hiddenContent" class="form-control" rows="15" 
                                  style="display: none;"><?= htmlspecialchars($edit_article['content'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>预览</label>
                        <div class="preview-area" id="preview">
                            <!-- 预览内容将通过JavaScript生成 -->
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">发布文章</button>
                    <?php if ($edit_id): ?>
                        <a href="?action=articles" class="btn btn-secondary" style="margin-left: 10px;">取消编辑</a>
                    <?php endif; ?>
                </form>
                
                <!-- 文章列表 -->
                <h3>文章列表</h3>
                <?php
                $stmt = $conn->query("SELECT * FROM articles ORDER BY created_at DESC");
                $articles = $stmt->fetchAll();
                ?>
                
                <?php if (empty($articles)): ?>
                    <p>暂无文章，请发布文章。</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>标题</th>
                                <th>阅读量</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                                <tr>
                                    <td><?= $article['id'] ?></td>
                                    <td><?= htmlspecialchars($article['title']) ?></td>
                                    <td><?= $article['views'] ?></td>
                                    <td><?= date('Y-m-d', strtotime($article['created_at'])) ?></td>
                                    <td class="actions">
                                        <a href="article.php?id=<?= $article['id'] ?>" target="_blank" class="btn" 
                                           style="padding: 5px 10px; font-size: 0.9em;">查看</a>
                                        <a href="?action=articles&edit=<?= $article['id'] ?>" class="edit-btn">编辑</a>
                                        <a href="?action=articles&delete=<?= $article['id'] ?>" 
                                           class="delete-btn" 
                                           onclick="return confirm('确定要删除这篇文章吗？')">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <script>
                    // 处理占位符
                    function removePlaceholder() {
                        const editor = document.getElementById('articleContent');
                        const placeholder = editor.querySelector('.placeholder');
                        if (placeholder) {
                            placeholder.remove();
                            // 清空编辑器内容，确保没有空白字符
                            editor.innerHTML = '';
                            // 定位光标到编辑器开始位置
                            const range = document.createRange();
                            const sel = window.getSelection();
                            range.setStart(editor, 0);
                            range.collapse(true);
                            sel.removeAllRanges();
                            sel.addRange(range);
                        }
                    }
                    
                    function addPlaceholder() {
                        const editor = document.getElementById('articleContent');
                        const content = editor.innerText.trim();
                        if (content === '') {
                            const placeholder = document.createElement('span');
                            placeholder.className = 'placeholder';
                            placeholder.textContent = '在此处编写文章内容...';
                            editor.appendChild(placeholder);
                        }
                    }
                    
                    // 编辑器功能
                    function insertText(syntax, type) {
                        const editor = document.getElementById('articleContent');
                        const selection = window.getSelection();
                        
                        // 先移除占位符
                        removePlaceholder();
                        
                        if (selection.rangeCount > 0) {
                            const range = selection.getRangeAt(0);
                            const text = range.toString();
                            
                            let newText = '';
                            switch (type) {
                                case 'bold':
                                    newText = '**' + text + '**';
                                    break;
                                case 'italic':
                                    newText = '*' + text + '*';
                                    break;
                                case 'link':
                                    newText = '[链接文字](URL)';
                                    break;
                                case 'image':
                                    newText = '![图片描述](URL)';
                                    break;
                                default:
                                    newText = syntax;
                            }
                            
                            range.deleteContents();
                            range.insertNode(document.createTextNode(newText));
                        } else {
                            editor.focus();
                            document.execCommand('insertText', false, syntax);
                        }
                        
                        updateContent();
                    }
                    
                    function updateContent() {
                        const editor = document.getElementById('articleContent');
                        // 检查是否有占位符
                        const placeholder = editor.querySelector('.placeholder');
                        let content = editor.innerText;
                        
                        // 如果有占位符，设置为空字符串
                        if (placeholder) {
                            content = '';
                        }
                        
                        const hiddenInput = document.getElementById('hiddenContent');
                        hiddenInput.value = content;
                        updatePreview(content);
                    }
                    
                    function updatePreview(markdown) {
                        const preview = document.getElementById('preview');
                        
                        // 简单的markdown预览
                        let html = markdown
                            .replace(/^# (.*$)/gm, '<h1>$1</h1>')
                            .replace(/^## (.*$)/gm, '<h2>$1</h2>')
                            .replace(/^### (.*$)/gm, '<h3>$1</h3>')
                            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                            .replace(/\*(.*?)\*/g, '<em>$1</em>')
                            .replace(/!\[(.*?)\]\((.*?)\)/g, '<img src="$2" alt="$1" style="max-width: 100%;">')
                            .replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>')
                            .replace(/```[\s\S]*?```/g, '<pre><code>代码块</code></pre>')
                            .replace(/`(.*?)`/g, '<code>$1</code>')
                            .replace(/^> (.*$)/gm, '<blockquote>$1</blockquote>')
                            .replace(/^- (.*$)/gm, '<li>$1</li>')
                            .replace(/\n/g, '<br>');
                        
                        preview.innerHTML = html;
                    }
                    
                    // 触发图片上传
                    function triggerImageUpload() {
                        document.getElementById('imageUpload').click();
                    }
                    
                    // 从文件上传图片 - 修复版
                    function uploadImageFromFile(file) {
                        if (!file) return;
                        
                        // 检查文件类型
                        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                        if (!allowedTypes.includes(file.type)) {
                            showNotification('只允许上传 JPG, PNG, GIF 格式的图片', 'error');
                            return;
                        }
                        
                        // 检查文件大小（限制2MB）
                        if (file.size > 2 * 1024 * 1024) {
                            showNotification('图片大小不能超过 2MB', 'error');
                            return;
                        }
                        
                        const formData = new FormData();
                        formData.append('image', file);
                        
                        // 显示上传中提示
                        const editor = document.getElementById('articleContent');
                        editor.focus();
                        document.execCommand('insertText', false, '![上传中...]()');
                        updateContent();
                        
                        // 显示加载指示器
                        showNotification('正在上传图片...', 'info');
                        
                        // 显示上传进度
                        showUploadProgress();
                        
                        fetch('api.php?action=upload_image', {
                            method: 'POST',
                            body: formData,
                            // 不设置 Content-Type，让浏览器自动设置
                        })
                        .then(response => {
                            // 首先检查响应状态
                            if (!response.ok) {
                                throw new Error(`HTTP错误: ${response.status}`);
                            }
                            
                            // 检查内容类型是否为 JSON
                            const contentType = response.headers.get('content-type');
                            if (!contentType || !contentType.includes('application/json')) {
                                // 尝试以文本形式获取响应
                                return response.text().then(text => {
                                    console.error('非JSON响应:', text.substring(0, 200));
                                    throw new Error('服务器返回的不是 JSON 格式');
                                });
                            }
                            
                            return response.json();
                        })
                        .then(data => {
                            hideUploadProgress();
                            
                            if (data.success) {
                                // 删除上传中提示，插入真实图片
                                const editorContent = document.getElementById('articleContent');
                                let content = editorContent.innerText;
                                content = content.replace('![上传中...]()', `![图片描述](${data.url})`);
                                editorContent.innerText = content;
                                updateContent();
                                
                                // 显示成功消息
                                showNotification(data.message || '图片上传成功！', 'success');
                            } else {
                                // 删除上传中提示
                                const editorContent = document.getElementById('articleContent');
                                let content = editorContent.innerText;
                                content = content.replace('![上传中...]()', '');
                                editorContent.innerText = content;
                                updateContent();
                                
                                showNotification('图片上传失败：' + (data.error || '未知错误'), 'error');
                            }
                        })
                        .catch(error => {
                            console.error('上传错误:', error);
                            hideUploadProgress();
                            
                            // 删除上传中提示
                            const editorContent = document.getElementById('articleContent');
                            let content = editorContent.innerText;
                            content = content.replace('![上传中...]()', '');
                            editorContent.innerText = content;
                            updateContent();
                            
                            showNotification('上传失败：' + error.message, 'error');
                        });
                    }
                    
                    // 显示上传进度
                    function showUploadProgress() {
                        let progressDiv = document.getElementById('uploadProgress');
                        if (!progressDiv) {
                            progressDiv = document.createElement('div');
                            progressDiv.id = 'uploadProgress';
                            progressDiv.className = 'upload-progress';
                            progressDiv.innerHTML = `
                                <h3>上传中...</h3>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressFill"></div>
                                </div>
                                <p id="progressText">正在处理图片...</p>
                            `;
                            document.body.appendChild(progressDiv);
                            
                            // 模拟进度更新
                            let progress = 0;
                            const interval = setInterval(() => {
                                progress += 5;
                                if (progress <= 90) {
                                    updateProgress(progress, '正在上传...');
                                }
                            }, 200);
                            
                            progressDiv.dataset.interval = interval;
                        }
                        progressDiv.style.display = 'block';
                    }
                    
                    function hideUploadProgress() {
                        const progressDiv = document.getElementById('uploadProgress');
                        if (progressDiv) {
                            clearInterval(progressDiv.dataset.interval);
                            progressDiv.style.display = 'none';
                            updateProgress(100, '上传完成！');
                        }
                    }
                    
                    function updateProgress(percent, text) {
                        const progressFill = document.getElementById('progressFill');
                        const progressText = document.getElementById('progressText');
                        if (progressFill) progressFill.style.width = percent + '%';
                        if (progressText) progressText.textContent = text;
                    }
                    
                    // 显示通知
                    function showNotification(message, type = 'info') {
                        // 移除已有的通知
                        const existingNotifications = document.querySelectorAll('.notification');
                        existingNotifications.forEach(notification => {
                            notification.style.animation = 'slideOut 0.3s ease';
                            setTimeout(() => notification.remove(), 300);
                        });
                        
                        const notification = document.createElement('div');
                        notification.className = `notification ${type}`;
                        notification.textContent = message;
                        document.body.appendChild(notification);
                        
                        // 自动移除通知
                        setTimeout(() => {
                            if (notification.parentNode) {
                                notification.style.animation = 'slideOut 0.3s ease';
                                setTimeout(() => {
                                    if (notification.parentNode) {
                                        notification.remove();
                                    }
                                }, 300);
                            }
                        }, 5000);
                    }
                    
                    // 初始化预览
                    updateContent();
                </script>
                
            <?php elseif ($action == 'donate'): ?>
                <?php
                $donate_config = !empty($config['donate_config']) ? json_decode($config['donate_config'], true) : [];
                $donate_enabled = $donate_config['enabled'] ?? false;
                $donate_title = $donate_config['title'] ?? '请我喝杯咖啡';
                $donate_desc = $donate_config['desc'] ?? '感谢您的支持与鼓励';
                $alipay_qrcode = $donate_config['alipay_qrcode'] ?? '';
                $wechat_qrcode = $donate_config['wechat_qrcode'] ?? '';
                $qq_qrcode = $donate_config['qq_qrcode'] ?? '';
                $epay_url = $donate_config['epay_url'] ?? '';
                $epay_pid = $donate_config['epay_pid'] ?? '';
                $epay_key = $donate_config['epay_key'] ?? '';
                $preset_amounts = $donate_config['preset_amounts'] ?? [5, 10, 20, 50];
                $preset_str = implode(',', $preset_amounts);
                ?>

                <div class="content-header">
                    <h2>💖 捐款配置</h2>
                </div>

                <form method="POST" class="config-form">
                    <input type="hidden" name="action" value="config">

                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                            <input type="checkbox" name="donate_enabled" value="1" id="donateEnabled" <?= $donate_enabled ? 'checked' : '' ?> onchange="updateDonateConfig()">
                            <span>启用捐款页面</span>
                        </label>
                        <small style="color:#666; margin-left:28px;">启用后可在首页访问捐款页面</small>
                    </div>

                    <div id="donateSettings" style="<?= !$donate_enabled ? 'opacity:0.5;pointer-events:none;' : '' ?>">
                        <div class="form-group">
                            <label>页面标题</label>
                            <input type="text" id="donateTitle" class="form-control" value="<?= htmlspecialchars($donate_title) ?>" oninput="updateDonateConfig()">
                        </div>

                        <div class="form-group">
                            <label>页面描述</label>
                            <input type="text" id="donateDesc" class="form-control" value="<?= htmlspecialchars($donate_desc) ?>" oninput="updateDonateConfig()">
                        </div>

                        <div class="form-group">
                            <label>预设金额（逗号分隔，单位：元）</label>
                            <input type="text" id="donatePresets" class="form-control" value="<?= htmlspecialchars($preset_str) ?>" placeholder="5,10,20,50" oninput="updateDonateConfig()">
                        </div>

                        <h3 style="margin:25px 0 15px; color:#2c3e50; border-bottom:2px solid var(--primary-light); padding-bottom:8px;">收款二维码</h3>

                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:20px; margin-bottom:20px;">
                            <!-- 支付宝二维码 -->
                            <div class="qrcode-upload-card">
                                <h4>支付宝二维码</h4>
                                <?php if (!empty($alipay_qrcode)): ?>
                                <div class="qrcode-preview" id="alipayPreview">
                                    <img src="<?= htmlspecialchars($alipay_qrcode) ?>" alt="支付宝">
                                </div>
                                <?php else: ?>
                                <div class="qrcode-preview" id="alipayPreview">
                                    <p>点击上传</p>
                                </div>
                                <?php endif; ?>
                                <input type="file" id="alipayFile" accept="image/*" style="display:none" onchange="uploadQrcode('alipay', this)">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('alipayFile').click()" style="margin-top:8px;">上传二维码</button>
                            </div>

                            <!-- 微信二维码 -->
                            <div class="qrcode-upload-card">
                                <h4>微信二维码</h4>
                                <?php if (!empty($wechat_qrcode)): ?>
                                <div class="qrcode-preview" id="wechatPreview">
                                    <img src="<?= htmlspecialchars($wechat_qrcode) ?>" alt="微信">
                                </div>
                                <?php else: ?>
                                <div class="qrcode-preview" id="wechatPreview">
                                    <p>点击上传</p>
                                </div>
                                <?php endif; ?>
                                <input type="file" id="wechatFile" accept="image/*" style="display:none" onchange="uploadQrcode('wechat', this)">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('wechatFile').click()" style="margin-top:8px;">上传二维码</button>
                            </div>

                            <!-- QQ 二维码 -->
                            <div class="qrcode-upload-card">
                                <h4>QQ 钱包二维码</h4>
                                <?php if (!empty($qq_qrcode)): ?>
                                <div class="qrcode-preview" id="qqPreview">
                                    <img src="<?= htmlspecialchars($qq_qrcode) ?>" alt="QQ">
                                </div>
                                <?php else: ?>
                                <div class="qrcode-preview" id="qqPreview">
                                    <p>点击上传</p>
                                </div>
                                <?php endif; ?>
                                <input type="file" id="qqFile" accept="image/*" style="display:none" onchange="uploadQrcode('qq', this)">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('qqFile').click()" style="margin-top:8px;">上传二维码</button>
                            </div>
                        </div>

                        <h3 style="margin:25px 0 15px; color:#2c3e50; border-bottom:2px solid var(--primary-light); padding-bottom:8px;">易支付接入</h3>

                        <div class="form-group">
                            <label>易支付接口地址</label>
                            <input type="url" id="epayUrl" class="form-control" value="<?= htmlspecialchars($epay_url) ?>" placeholder="例如：https://pay.example.com" oninput="updateDonateConfig()">
                        </div>

                        <div class="form-group">
                            <label>商户 ID (PID)</label>
                            <input type="text" id="epayPid" class="form-control" value="<?= htmlspecialchars($epay_pid) ?>" oninput="updateDonateConfig()">
                        </div>

                        <div class="form-group">
                            <label>商户密钥 (Key)</label>
                            <input type="password" id="epayKey" class="form-control" value="<?= htmlspecialchars($epay_key) ?>" placeholder="输入商户密钥" oninput="updateDonateConfig()">
                        </div>

                        <small style="color:#888;">配置易支付后，捐款页面将显示在线支付按钮，支持支付宝/微信/QQ钱包自动跳转支付</small>
                    </div>

                    <input type="hidden" name="donate_config" id="donateConfigInput" value="<?= htmlspecialchars($config['donate_config'] ?? '') ?>">

                    <button type="submit" class="btn btn-primary" style="margin-top:20px;">💾 保存捐款配置</button>
                </form>

            <?php endif; ?>
        </div>
    </div>
    
    <!-- 页面加载完成后的初始化脚本 -->
    <script>
        // 页面加载完成后，自动移除任何残留的通知
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟移除初始通知
            setTimeout(() => {
                const notifications = document.querySelectorAll('.notification');
                notifications.forEach(notification => {
                    if (notification.textContent.includes('已保存') || 
                        notification.textContent.includes('已删除')) {
                        notification.style.animation = 'slideOut 0.3s ease';
                        setTimeout(() => notification.remove(), 300);
                    }
                });
            }, 3000);
            
            // 处理文件粘贴
            const editorContent = document.getElementById('articleContent');
            if (editorContent) {
                editorContent.addEventListener('paste', function(e) {
                    // 检查是否粘贴了图片
                    const items = e.clipboardData.items;
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            const file = items[i].getAsFile();
                            if (file) {
                                uploadImageFromFile(file);
                            }
                            break;
                        }
                    }
                });
            }
        });

        // 配色方案交互逻辑
        let selectedPreset = '<?= addslashes($current_preset) ?>';
        let schemeMode = '<?= addslashes($current_mode) ?>';

        function toggleSchemeMode(mode) {
            schemeMode = mode;
            document.getElementById('presetSection').style.display = mode === 'preset' ? '' : 'none';
            document.getElementById('customSection').style.display = mode === 'custom' ? '' : 'none';
            document.querySelectorAll('.scheme-tab').forEach(tab => tab.classList.remove('active'));
            event.target.closest('.scheme-tab').classList.add('active');
            updateColorSchemeInput();
        }

        function selectPreset(key) {
            selectedPreset = key;
            document.querySelectorAll('.preset-card').forEach(card => card.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            updateColorSchemeInput();
        }

        function updateColorSchemeInput() {
            let data;
            if (schemeMode === 'preset') {
                data = { mode: 'preset', preset: selectedPreset };
            } else {
                data = {
                    mode: 'custom',
                    primary_light: document.getElementById('custom_primary_light').value,
                    primary_blue: document.getElementById('custom_primary_blue').value,
                    accent_green: document.getElementById('custom_accent_green').value,
                };
            }
            document.getElementById('colorSchemeInput').value = JSON.stringify(data);
        }

        // 自定义颜色选择器实时预览
        ['custom_primary_light', 'custom_primary_blue', 'custom_accent_green'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', function() {
                    const blue = document.getElementById('custom_primary_blue').value;
                    const green = document.getElementById('custom_accent_green').value;
                    document.getElementById('customPreview').style.background =
                        'linear-gradient(135deg, ' + blue + ', ' + green + ')';
                    updateColorSchemeInput();
                });
            }
        });

        // 表单提交前确保配色数据已写入
        const configForm = document.querySelector('form[method="POST"]');
        if (configForm) {
            configForm.addEventListener('submit', function() {
                updateColorSchemeInput();
                updateBgConfig();
            });
        }

        // ===== 背景配置交互 =====
        let bgMode = '<?= addslashes($bg_mode) ?>';
        let bgImages = <?= json_encode($bg_images) ?>;
        let bgDisplay = '<?= addslashes($bg_display) ?>';

        function toggleBgMode(mode) {
            bgMode = mode;
            document.getElementById('bgNoneSection').style.display = mode === 'none' ? '' : 'none';
            document.getElementById('bgApiSection').style.display = mode === 'api' ? '' : 'none';
            document.getElementById('bgImageSection').style.display = mode === 'image' ? '' : 'none';
            document.querySelectorAll('[name="bg_mode"]').forEach(r => {
                r.closest('.scheme-tab').classList.toggle('active', r.value === mode);
            });
            updateBgConfig();
        }

        function toggleBgDisplay(display) {
            bgDisplay = display;
            document.getElementById('bgIntervalSection').style.display = display === 'slideshow' ? '' : 'none';
            document.querySelectorAll('[name="bg_display"]').forEach(r => {
                r.closest('.scheme-tab').classList.toggle('active', r.value === display);
            });
            updateBgConfig();
        }

        function updateBgConfig() {
            let data;
            if (bgMode === 'none') {
                data = { mode: 'none' };
            } else if (bgMode === 'api') {
                data = { mode: 'api', api_url: document.getElementById('bgApiUrl').value };
            } else {
                data = {
                    mode: 'image',
                    images: bgImages,
                    display: bgDisplay,
                    interval: parseInt(document.getElementById('bgIntervalRange')?.value || 10)
                };
            }
            document.getElementById('bgConfigInput').value = JSON.stringify(data);
        }

        // 点击上传区域
        document.getElementById('bgDropZone')?.addEventListener('click', function() {
            document.getElementById('bgFileInput').click();
        });

        // 拖拽上传
        const dropZone = document.getElementById('bgDropZone');
        if (dropZone) {
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            dropZone.addEventListener('dragleave', function() {
                this.classList.remove('dragover');
            });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                handleBgUpload(e.dataTransfer.files);
            });
        }

        function handleBgUpload(files) {
            if (!files || files.length === 0) return;

            Array.from(files).forEach(file => {
                if (!file.type.startsWith('image/')) return;
                if (file.size > 5 * 1024 * 1024) {
                    alert('图片 ' + file.name + ' 超过5MB限制');
                    return;
                }

                const formData = new FormData();
                formData.append('image', file);

                fetch('api.php?action=upload_background', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            bgImages.push(data.path);
                            renderBgGallery();
                            updateBgConfig();
                        } else {
                            alert('上传失败：' + data.error);
                        }
                    })
                    .catch(() => alert('上传请求失败'));
            });
        }

        function removeBgImage(index) {
            const path = bgImages[index];
            // 从服务器删除文件
            fetch('api.php?action=delete_background&filepath=' + encodeURIComponent(path))
                .then(r => r.json())
                .then(() => {
                    bgImages.splice(index, 1);
                    renderBgGallery();
                    updateBgConfig();
                })
                .catch(() => {
                    bgImages.splice(index, 1);
                    renderBgGallery();
                    updateBgConfig();
                });
        }

        function renderBgGallery() {
            const gallery = document.getElementById('bgGallery');
            if (!gallery) return;
            gallery.innerHTML = '';
            bgImages.forEach((img, idx) => {
                const thumb = document.createElement('div');
                thumb.className = 'bg-thumb';
                thumb.dataset.path = img;
                thumb.innerHTML = '<img src="' + img + '" alt="背景图">' +
                    '<div class="bg-thumb-remove" onclick="removeBgImage(' + idx + ')">&times;</div>';
                gallery.appendChild(thumb);
            });
        }

        // ===== 捐款配置交互 =====
        let donateQrcodes = {
            alipay: '<?= addslashes($alipay_qrcode) ?>',
            wechat: '<?= addslashes($wechat_qrcode) ?>',
            qq: '<?= addslashes($qq_qrcode) ?>'
        };

        function updateDonateConfig() {
            const enabled = document.getElementById('donateEnabled').checked;
            const settings = document.getElementById('donateSettings');
            if (settings) {
                settings.style.opacity = enabled ? '1' : '0.5';
                settings.style.pointerEvents = enabled ? 'auto' : 'none';
            }

            const presets = (document.getElementById('donatePresets')?.value || '5,10,20,50')
                .split(',').map(s => parseFloat(s.trim())).filter(n => n > 0);

            const data = {
                enabled: enabled,
                title: document.getElementById('donateTitle')?.value || '请我喝杯咖啡',
                desc: document.getElementById('donateDesc')?.value || '感谢您的支持与鼓励',
                preset_amounts: presets,
                alipay_qrcode: donateQrcodes.alipay,
                wechat_qrcode: donateQrcodes.wechat,
                qq_qrcode: donateQrcodes.qq,
                epay_url: document.getElementById('epayUrl')?.value || '',
                epay_pid: document.getElementById('epayPid')?.value || '',
                epay_key: document.getElementById('epayKey')?.value || '',
            };
            document.getElementById('donateConfigInput').value = JSON.stringify(data);
        }

        function uploadQrcode(type, input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            if (!file.type.startsWith('image/')) return;

            const formData = new FormData();
            formData.append('image', file);

            fetch('api.php?action=upload_qrcode', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        donateQrcodes[type] = data.path;
                        const preview = document.getElementById(type + 'Preview');
                        if (preview) {
                            preview.innerHTML = '<img src="' + data.path + '" alt="' + type + '">';
                        }
                        updateDonateConfig();
                    } else {
                        alert('上传失败：' + data.error);
                    }
                })
                .catch(() => alert('上传请求失败'));
        }
    </script>
</body>
</html>