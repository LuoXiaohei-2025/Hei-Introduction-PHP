<?php
// 配置文件
// 包含网站核心功能和工具函数
session_start();
date_default_timezone_set('Asia/Shanghai');

// 检查网站是否已安装
function check_installed() {
    return file_exists(__DIR__ . '/.installed');
}

// 标记网站为已安装状态
function mark_installed() {
    file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
}

// 获取数据库连接
// 使用静态变量实现单例模式，避免重复连接
function get_db_connection() {
    static $conn = null;
    if ($conn === null) {
        if (!file_exists(__DIR__ . '/config.inc.php')) {
            header('Location: install.php');
            exit;
        }
        
        $config = require __DIR__ . '/config.inc.php';
        try {
            $conn = new PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    return $conn;
}

// 获取网站配置信息
function get_site_config() {
    $conn = get_db_connection();
    $stmt = $conn->query("SELECT * FROM site_config LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        // 默认配置
        $config = [
            'site_name' => '个人主页',
            'welcome_text' => '欢迎来到我的个人主页',
            'avatar_path' => '',
            'qq' => '',
            'email' => '',
            'footer_text' => '',
            'icp' => '',
            'admin_password' => '',
            'custom_footer_html' => '',
            'color_scheme' => '',
            'background_config' => '',
            'donate_config' => ''
        ];
    }
    
    // 读取个人简介文件
    $config['me_content'] = file_exists('me.md') ? file_get_contents('me.md') : '# 个人简介\n请在此处编辑您的个人简介...';
    
    return $config;
}

// 将图片转换为webp格式
// 支持自动检测图片类型和调整大小
function convert_to_webp($source, $destination, $quality = 70) {
    if (!file_exists($source)) {
        return false;
    }
    
    // 检查GD库是否可用
    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
        return false;
    }
    
    // 使用imagecreatefromstring自动检测图片类型，支持临时文件
    $image = imagecreatefromstring(file_get_contents($source));
    
    if ($image) {
        // 检查是否为调色板图像，如果是则转换为真彩色图像
        if (!imageistruecolor($image)) {
            $temp_image = imagecreatetruecolor(imagesx($image), imagesy($image));
            // 保持透明度
            imagealphablending($temp_image, false);
            imagesavealpha($temp_image, true);
            // 填充透明背景
            $transparent = imagecolorallocatealpha($temp_image, 255, 255, 255, 127);
            imagefilledrectangle($temp_image, 0, 0, imagesx($image), imagesy($image), $transparent);
            // 复制图像
            imagecopy($temp_image, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $temp_image;
        }
        
        // 调整图片大小，限制最大宽度为800px，保持比例
        $width = imagesx($image);
        $height = imagesy($image);
        $max_width = 800;
        
        if ($width > $max_width) {
            $new_width = $max_width;
            $new_height = ($height / $width) * $new_width;
            $resized_image = imagecreatetruecolor($new_width, $new_height);
            
            // 保持透明度
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            
            imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            $result = imagewebp($resized_image, $destination, $quality);
            imagedestroy($resized_image);
        } else {
            // 直接转换，不调整大小
            $result = imagewebp($image, $destination, $quality);
        }
        
        imagedestroy($image);
        return $result;
    }
    
    return false;
}

// 记录访问IP
// 使用session避免重复记录
function record_visit() {
    if (!isset($_SESSION['visitor_recorded'])) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $date = date('Y-m-d');
        
        $conn = get_db_connection();
        $stmt = $conn->prepare("INSERT INTO visits (ip, visit_date) VALUES (?, ?) ON DUPLICATE KEY UPDATE count = count + 1");
        $stmt->execute([$ip, $date]);
        
        $_SESSION['visitor_recorded'] = true;
    }
}

// 获取访问统计数据
function get_visit_stats() {
    $conn = get_db_connection();
    $today = date('Y-m-d');
    
    // 当日访问IP数
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT ip) as count FROM visits WHERE visit_date = ?");
    $stmt->execute([$today]);
    $today_count = $stmt->fetch()['count'];
    
    // 近7日访问统计
    $seven_days_ago = date('Y-m-d', strtotime('-6 days'));
    $stmt = $conn->prepare("
        SELECT visit_date, COUNT(DISTINCT ip) as count 
        FROM visits 
        WHERE visit_date BETWEEN ? AND ? 
        GROUP BY visit_date 
        ORDER BY visit_date
    ");
    $stmt->execute([$seven_days_ago, $today]);
    $weekly_stats = $stmt->fetchAll();
    
    return [
        'today' => $today_count,
        'weekly' => $weekly_stats
    ];
}

// 处理文章中的图片
// 将base64编码的图片转换为文件并保存
function process_article_images($content) {
    // 创建图片目录
    $picture_dir = 'picture/';
    if (!is_dir($picture_dir)) {
        mkdir($picture_dir, 0755, true);
    }
    
    // 查找base64编码的图片并上传
    $pattern = '/!\[(.*?)\]\((data:image\/(png|jpg|jpeg|gif);base64,([^)]+))\)/';
    preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $alt_text = $match[1];
        $base64_data = $match[2];
        $image_type = $match[3];
        $base64_image = $match[4];
        
        // 解码base64图片
        $image_data = base64_decode($base64_image);
        
        // 生成唯一文件名
        $filename = uniqid() . '.' . $image_type;
        $temp_path = $picture_dir . $filename;
        
        // 保存临时文件
        file_put_contents($temp_path, $image_data);
        
        // 转换为webp格式
        $webp_filename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
        $webp_path = $picture_dir . $webp_filename;
        
        if (convert_to_webp($temp_path, $webp_path)) {
            // 替换文章中的图片链接
            $new_markdown = '![' . $alt_text . '](' . $webp_path . ')';
            $content = str_replace($match[0], $new_markdown, $content);
            
            // 删除临时文件
            if (file_exists($temp_path)) {
                unlink($temp_path);
            }
        }
    }
    
    return $content;
}

// 修复图片路径
// 处理相对路径和完整URL
function fix_image_path($path) {
    if (empty($path)) {
        return '';
    }
    
    // 如果已经是完整URL，直接返回
    if (strpos($path, 'http') === 0) {
        return $path;
    }
    
    // 确保路径正确
    if (file_exists($path)) {
        return $path;
    }
    
    // 检查相对路径
    $relative_paths = [
        $path,
        'uploads/' . basename($path),
        'uploads/projects/' . basename($path),
        'picture/' . basename($path)
    ];
    
    foreach ($relative_paths as $relative_path) {
        if (file_exists($relative_path)) {
            return $relative_path;
        }
    }
    
    return '';
}

// 简单的markdown解析器
// 支持基本的markdown语法
function parse_markdown($content) {
    // 处理图片
    $content = preg_replace('/!\[(.*?)\]\((.*?)\)/', 
        '<img src="$2" alt="$1" loading="lazy" style="max-width:100%;height:auto;">', 
        $content);
    
    // 处理标题
    $content = preg_replace('/### (.*?)(\n|$)/', '<h3>$1</h3>', $content);
    $content = preg_replace('/## (.*?)(\n|$)/', '<h2>$1</h2>', $content);
    $content = preg_replace('/# (.*?)(\n|$)/', '<h1>$1</h1>', $content);
    
    // 处理粗体和斜体
    $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
    $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
    
    // 处理链接
    $content = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', $content);
    
    // 处理代码块
    $content = preg_replace('/```(\w*)\n(.*?)\n```/s', '<pre><code class="$1">$2</code></pre>', $content);
    
    // 处理内联代码
    $content = preg_replace('/`(.*?)`/', '<code>$1</code>', $content);
    
    // 处理引用
    $content = preg_replace('/> (.*?)(\n|$)/', '<blockquote>$1</blockquote>', $content);
    
    // 处理列表
    $lines = explode("\n", $content);
    $in_list = false;
    foreach ($lines as $i => &$line) {
        if (preg_match('/^- (.*)/', $line, $matches)) {
            if (!$in_list) {
                $line = '<ul><li>' . $matches[1];
                $in_list = true;
            } else {
                $line = '<li>' . $matches[1];
            }
        } else {
            if ($in_list) {
                $line = '</ul>' . $line;
                $in_list = false;
            }
        }
    }
    $content = implode("\n", $lines);
    if ($in_list) {
        $content .= '</ul>';
    }
    
    // 转换换行符
    $content = nl2br($content);
    
    return $content;
}

// 预设配色方案
function get_color_presets() {
    return [
        'default' => [
            'name' => '默认·天青',
            'primary_light' => '#e0f7fa',
            'primary_blue' => '#4fc3f7',
            'accent_green' => '#81c784',
        ],
        'sunset' => [
            'name' => '日落·橙红',
            'primary_light' => '#fff3e0',
            'primary_blue' => '#ff8a65',
            'accent_green' => '#f06292',
        ],
        'ocean' => [
            'name' => '深海·蓝紫',
            'primary_light' => '#e8eaf6',
            'primary_blue' => '#5c6bc0',
            'accent_green' => '#26c6da',
        ],
        'forest' => [
            'name' => '森林·翠绿',
            'primary_light' => '#e8f5e9',
            'primary_blue' => '#66bb6a',
            'accent_green' => '#aed581',
        ],
        'lavender' => [
            'name' => '薰衣草·粉紫',
            'primary_light' => '#f3e5f5',
            'primary_blue' => '#ba68c8',
            'accent_green' => '#f48fb1',
        ],
        'dark' => [
            'name' => '暗夜·深邃',
            'primary_light' => '#263238',
            'primary_blue' => '#78909c',
            'accent_green' => '#4db6ac',
        ],
    ];
}

// 获取当前配色方案的 CSS 变量
// 返回可直接注入 <style> 的 CSS 字符串
function get_color_scheme_css($color_scheme_json) {
    $presets = get_color_presets();

    // 解析数据库中存储的配色方案
    $scheme = !empty($color_scheme_json) ? json_decode($color_scheme_json, true) : [];

    // 确定模式：preset 或 custom
    $mode = $scheme['mode'] ?? 'preset';
    $preset_name = $scheme['preset'] ?? 'default';

    if ($mode === 'preset' && isset($presets[$preset_name])) {
        $colors = $presets[$preset_name];
    } elseif ($mode === 'custom' && !empty($scheme['primary_blue']) && !empty($scheme['accent_green'])) {
        $colors = [
            'primary_light' => $scheme['primary_light'] ?? '#e0f7fa',
            'primary_blue' => $scheme['primary_blue'],
            'accent_green' => $scheme['accent_green'],
        ];
    } else {
        // 兜底使用默认
        $colors = $presets['default'];
    }

    return ":root {
    --primary-light: {$colors['primary_light']};
    --primary-blue: {$colors['primary_blue']};
    --accent-green: {$colors['accent_green']};
}";
}

// 获取背景配置的 CSS 和 JS
// 返回 ['css' => string, 'js' => string]
function get_background_render($background_config_json) {
    $result = ['css' => '', 'js' => ''];
    $config = !empty($background_config_json) ? json_decode($background_config_json, true) : [];
    $mode = $config['mode'] ?? 'none';

    if ($mode === 'none') {
        return $result;
    }

    // 公共背景样式
    $result['css'] = '
body {
    position: relative;
}
body::before {
    content: "";
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    z-index: -1;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
}
.container, .hero, .article-header {
    position: relative;
    z-index: 1;
}
';

    if ($mode === 'api') {
        $api_url = $config['api_url'] ?? '';
        if (!empty($api_url)) {
            $escaped_url = htmlspecialchars($api_url, ENT_QUOTES);
            $result['css'] .= "body::before { background-image: url('{$escaped_url}'); }";
            // API 模式：每次加载刷新图片
            $result['js'] = "fetch('{$escaped_url}', {cache:'no-cache'}).then(()=>{}).catch(()=>{});";
        }
    } elseif ($mode === 'image') {
        $images = $config['images'] ?? [];
        $display = $config['display'] ?? 'fixed';

        if (!empty($images)) {
            // 转义图片路径
            $safe_images = array_map(function($p) { return htmlspecialchars($p, ENT_QUOTES); }, $images);

            if ($display === 'fixed' || count($safe_images) === 1) {
                $result['css'] .= "body::before { background-image: url('{$safe_images[0]}'); }";
            } else {
                // 轮播模式
                $interval = intval($config['interval'] ?? 10);
                if ($interval < 3) $interval = 3;
                $images_json = json_encode($safe_images);
                $result['css'] .= "body::before { background-image: url('{$safe_images[0]}'); transition: background-image 1s ease; }";
                $result['js'] = "(function(){
    var imgs = {$images_json};
    var idx = 0;
    var interval = {$interval} * 1000;
    setInterval(function(){
        idx = (idx + 1) % imgs.length;
        document.body.style.setProperty('--bg-image', 'url(' + imgs[idx] + ')');
        var before = document.body;
        before.classList.add('bg-transitioning');
        var sheet = document.styleSheets[0];
        for(var i=0; i<sheet.cssRules.length; i++){
            if(sheet.cssRules[i].selectorText === 'body::before'){
                sheet.deleteRule(i);
                sheet.insertRule('body::before{content:\"\";position:fixed;top:0;left:0;right:0;bottom:0;z-index:-1;background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:fixed;background-image:url('+imgs[idx]+');transition:opacity 1s ease;}', i);
                break;
            }
        }
    }, interval);
})();";
            }
        }
    }

    return $result;
}

// 生成易支付支付链接
// 易支付标准接口签名方式：参数按字母排序拼接后 MD5
function generate_epay_url($donate_config_json, $amount, $type = 'alipay') {
    $config = !empty($donate_config_json) ? json_decode($donate_config_json, true) : [];

    $epay_url = $config['epay_url'] ?? '';
    $pid = $config['epay_pid'] ?? '';
    $key = $config['epay_key'] ?? '';
    $site_name = $config['title'] ?? '捐款';

    if (empty($epay_url) || empty($pid) || empty($key)) {
        return '';
    }

    $out_trade_no = 'DN' . date('YmdHis') . mt_rand(1000, 9999);
    $notify_url = '';  // 异步通知地址，可由用户在后台配置
    $return_url = '';  // 同步跳转地址

    $params = [
        'pid' => $pid,
        'type' => $type,
        'out_trade_no' => $out_trade_no,
        'notify_url' => $notify_url,
        'return_url' => $return_url,
        'name' => $site_name,
        'money' => number_format($amount, 2, '.', ''),
    ];

    // 按键名字典序排列
    ksort($params);

    // 拼接待签名字符串
    $sign_str = '';
    foreach ($params as $k => $v) {
        if ($v !== '') {
            $sign_str .= "{$k}={$v}&";
        }
    }
    $sign_str = rtrim($sign_str, '&');
    $sign = md5($sign_str . $key);

    // 构建完整支付链接
    $query = http_build_query($params) . '&sign=' . $sign . '&sign_type=MD5';
    return rtrim($epay_url, '/') . '/submit.php?' . $query;
}
?>