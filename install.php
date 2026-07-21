<?php
// install.php
session_start();

// 如果已安装，重定向到首页
if (file_exists(__DIR__ . '/.installed')) {
    header('Location: index.php');
    exit;
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? 1;
    
    if ($step == 1) {
        // 第一步：数据库配置
        try {
            $host = $_POST['host'] ?? 'localhost';
            $dbname = $_POST['dbname'];
            $username = $_POST['username'];
            $password = $_POST['password'];
            
            $conn = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 创建数据库
            $conn->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->exec("USE `$dbname`");
            
            // 创建表
            $conn->exec("
                CREATE TABLE site_config (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    site_name VARCHAR(100) NOT NULL,
                    welcome_text TEXT NOT NULL,
                    avatar_path VARCHAR(255),
                    qq VARCHAR(20),
                    email VARCHAR(100),
                    footer_text TEXT,
                    icp VARCHAR(100),
                    custom_footer_html LONGTEXT,
                    color_scheme LONGTEXT,
                    background_config LONGTEXT,
                    donate_config LONGTEXT,
                    admin_password VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            $conn->exec("
                CREATE TABLE projects (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    icon_path VARCHAR(255),
                    link VARCHAR(255),
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            $conn->exec("
                CREATE TABLE articles (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    title VARCHAR(200) NOT NULL,
                    content LONGTEXT NOT NULL,
                    views INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
            
            $conn->exec("
                CREATE TABLE visits (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    ip VARCHAR(45) NOT NULL,
                    visit_date DATE NOT NULL,
                    count INT DEFAULT 1,
                    UNIQUE KEY unique_visit (ip, visit_date)
                )
            ");
            
            $_SESSION['db_config'] = compact('host', 'dbname', 'username', 'password');
            $_SESSION['step'] = 2;
            
        } catch (PDOException $e) {
            $error = "数据库错误: " . $e->getMessage();
        }
        
    } elseif ($step == 2) {
        // 第二步：网站基本设置和头像上传
        if (!empty($_FILES['avatar']['name'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                $error = "只允许上传 JPG, PNG, GIF 格式的图片";
            } elseif ($_FILES['avatar']['size'] > $max_size) {
                $error = "图片大小不能超过 2MB";
            } else {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . time() . '.' . $ext;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    $_SESSION['site_config'] = [
                        'site_name' => $_POST['site_name'],
                        'welcome_text' => $_POST['welcome_text'],
                        'avatar_path' => $upload_path
                    ];
                    $_SESSION['step'] = 3;
                } else {
                    $error = "头像上传失败";
                }
            }
        }
        
    } elseif ($step == 3) {
        // 第三步：设置管理员密码
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($password !== $confirm_password) {
            $error = "两次输入的密码不一致";
        } elseif (strlen($password) < 6) {
            $error = "密码长度至少6位";
        } else {
            // 保存配置到文件
            $config_content = "<?php\nreturn " . var_export($_SESSION['db_config'], true) . ";\n?>";
            file_put_contents('config.inc.php', $config_content);
            
            // 连接数据库并保存配置
            $config = $_SESSION['db_config'];
            $conn = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", 
                          $config['username'], $config['password']);
            
            $site_config = $_SESSION['site_config'];
            $stmt = $conn->prepare("INSERT INTO site_config (site_name, welcome_text, avatar_path, admin_password) VALUES (?, ?, ?, ?)");
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$site_config['site_name'], $site_config['welcome_text'], $site_config['avatar_path'], $hashed_password]);
            
            // 创建me.md文件
            file_put_contents('me.md', '# 个人简介' . PHP_EOL . '请在此处编辑您的个人简介...');
            
            // 标记已安装
            file_put_contents('.installed', date('Y-m-d H:i:s'));
            
            // 清空session
            session_destroy();
            
            echo '<!DOCTYPE html>
            <html lang="zh-CN">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>安装完成</title>
                <style>
                    body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                    .success { color: green; font-size: 24px; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="success">安装完成，即将跳转后台完成个性化设置</div>
                <script>
                    setTimeout(function() {
                        window.location.href = "admin.php";
                    }, 3000);
                </script>
            </body>
            </html>';
            exit;
        }
    }
}

$current_step = $_SESSION['step'] ?? 1;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hei Introduction 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0f7fa 0%, #e8f5e9 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #4fc3f7, #81c784);
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #757575;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }
        
        .step.active {
            background: #4fc3f7;
            color: white;
            transform: scale(1.1);
        }
        
        .step.completed {
            background: #81c784;
            color: white;
        }
        
        .step-label {
            position: absolute;
            top: 40px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 12px;
            color: #757575;
        }
        
        .step.active .step-label {
            color: #4fc3f7;
            font-weight: bold;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #424242;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"],
        input[type="file"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus,
        textarea:focus {
            outline: none;
            border-color: #4fc3f7;
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.1);
        }
        
        .error {
            color: #f44336;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        
        .error.show {
            display: block;
        }
        
        .btn {
            background: linear-gradient(90deg, #4fc3f7, #81c784);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hei Introduction 安装向导</h1>
        
        <div class="step-indicator">
            <div class="step <?= $current_step >= 1 ? 'active' : '' ?>">
                1
                <div class="step-label">数据库配置</div>
            </div>
            <div class="step <?= $current_step >= 2 ? 'active' : '' ?>">
                2
                <div class="step-label">网站设置</div>
            </div>
            <div class="step <?= $current_step >= 3 ? 'active' : '' ?>">
                3
                <div class="step-label">管理员密码</div>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="error show"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="step" value="<?= $current_step ?>">
            
            <?php if ($current_step == 1): ?>
                <div class="form-group">
                    <label for="host">数据库主机</label>
                    <input type="text" id="host" name="host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="dbname">数据库名</label>
                    <input type="text" id="dbname" name="dbname" required>
                </div>
                
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password">
                </div>
                
            <?php elseif ($current_step == 2): ?>
                <div class="form-group">
                    <label for="site_name">网站名称</label>
                    <input type="text" id="site_name" name="site_name" required>
                </div>
                
                <div class="form-group">
                    <label for="welcome_text">欢迎语</label>
                    <textarea id="welcome_text" name="welcome_text" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="avatar">个人头像 (不超过2MB)</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/gif">
                </div>
                
            <?php elseif ($current_step == 3): ?>
                <div class="form-group">
                    <label for="password">管理员密码</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
            <?php endif; ?>
            
            <button type="submit" class="btn">
                <?= $current_step == 3 ? '完成安装' : '下一步' ?>
            </button>
        </form>
    </div>
</body>
</html>