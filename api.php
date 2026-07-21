<?php
// API接口文件
// 提供网站数据接口和图片上传功能
require_once 'config.php';

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// 确保没有输出任何内容
ob_start();

if (!check_installed()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '系统未安装']);
    ob_end_flush();
    exit;
}

// 启用错误报告用于调试
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 处理跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_all_projects':
            // 获取所有项目
            $conn = get_db_connection();
            $stmt = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");
            $result = $stmt->fetchAll();
            
            // 修复项目图标路径
            foreach ($result as &$project) {
                if (!empty($project['icon_path'])) {
                    $project['icon_path'] = fix_image_path($project['icon_path']);
                }
            }
            
            echo json_encode($result);
            break;
            
        case 'get_all_articles':
            // 获取所有文章（仅返回标题和创建时间）
            $conn = get_db_connection();
            $stmt = $conn->query("SELECT id, title, created_at FROM articles ORDER BY created_at DESC");
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'search_articles':
            // 搜索文章
            $keyword = $_GET['keyword'] ?? '';
            $conn = get_db_connection();
            $stmt = $conn->prepare("SELECT id, title, created_at FROM articles WHERE title LIKE ? ORDER BY created_at DESC");
            $stmt->execute(["%$keyword%"]);
            echo json_encode($stmt->fetchAll());
            break;
            
        case 'upload_image':
            // 处理图片上传
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $error = '没有上传文件或上传出错';
                if (isset($_FILES['image']['error'])) {
                    switch ($_FILES['image']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error = '文件大小超过限制';
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error = '文件只有部分被上传';
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error = '没有文件被上传';
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                            $error = '临时文件夹不存在';
                            break;
                        case UPLOAD_ERR_CANT_WRITE:
                            $error = '写入磁盘失败';
                            break;
                        case UPLOAD_ERR_EXTENSION:
                            $error = 'PHP扩展阻止了文件上传';
                            break;
                    }
                }
                echo json_encode(['success' => false, 'error' => $error]);
                break;
            }
            
            $file = $_FILES['image'];
            $temp_file = $file['tmp_name'];
            $original_name = $file['name'];
            $file_type = $file['type'];
            $file_size = $file['size'];
            
            // 检查文件类型
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            if (!in_array($file_type, $allowed_types, true)) {
                echo json_encode(['success' => false, 'error' => '只允许上传 JPG, PNG, GIF 格式的图片']);
                break;
            }
            
            // 检查文件大小（2MB限制）
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($file_size > $max_size) {
                echo json_encode(['success' => false, 'error' => '图片大小不能超过 2MB']);
                break;
            }
            
            // 检查文件是否实际存在
            if (!file_exists($temp_file)) {
                echo json_encode(['success' => false, 'error' => '临时文件不存在']);
                break;
            }
            
            // 检查文件是否可读
            if (!is_readable($temp_file)) {
                echo json_encode(['success' => false, 'error' => '无法读取上传的文件']);
                break;
            }
            
            // 创建图片目录
            $upload_dir = 'picture/';
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    echo json_encode(['success' => false, 'error' => '无法创建图片目录']);
                    break;
                }
            }
            
            // 检查目录是否可写
            if (!is_writable($upload_dir)) {
                echo json_encode(['success' => false, 'error' => '图片目录不可写']);
                break;
            }
            
            // 生成唯一文件名
            $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $unique_id = uniqid('img_', true);
            $temp_filename = $unique_id . '.' . $ext;
            $temp_path = $upload_dir . $temp_filename;
            
            // 移动上传的文件到临时位置
            if (!move_uploaded_file($temp_file, $temp_path)) {
                echo json_encode(['success' => false, 'error' => '移动上传文件失败']);
                break;
            }
            
            // 转换为webp格式
            $webp_filename = $unique_id . '.webp';
            $webp_path = $upload_dir . $webp_filename;
            
            // 检查GD库是否可用
            if (!function_exists('imagecreatefromjpeg') || !function_exists('imagewebp')) {
                // 如果GD库不可用，使用原图
                $response = [
                    'success' => true,
                    'url' => $temp_path,
                    'filename' => $temp_filename,
                    'warning' => 'GD库不可用，使用原图格式'
                ];
                echo json_encode($response);
                break;
            }
            
            // 执行转换
            $conversion_success = convert_to_webp($temp_path, $webp_path, 80);
            
            if ($conversion_success) {
                // 删除临时文件
                if (file_exists($temp_path)) {
                    unlink($temp_path);
                }
                
                // 返回webp图片URL
                $response = [
                    'success' => true,
                    'url' => $webp_path,
                    'filename' => $webp_filename,
                    'size' => filesize($webp_path),
                    'message' => '图片上传并转换成功'
                ];
            } else {
                // 如果转换失败，使用原图并尝试修复
                if (file_exists($temp_path)) {
                    // 尝试修复图片
                    $repaired = false;
                    try {
                        switch ($ext) {
                            case 'jpg':
                            case 'jpeg':
                                $image = imagecreatefromjpeg($temp_path);
                                if ($image) {
                                    imagejpeg($image, $temp_path, 90);
                                    imagedestroy($image);
                                    $repaired = true;
                                }
                                break;
                            case 'png':
                                $image = imagecreatefrompng($temp_path);
                                if ($image) {
                                    imagepng($image, $temp_path, 9);
                                    imagedestroy($image);
                                    $repaired = true;
                                }
                                break;
                            case 'gif':
                                // GIF可能不需要修复
                                $repaired = true;
                                break;
                        }
                    } catch (Exception $e) {
                        // 修复失败，继续使用原图
                    }
                    
                    $response = [
                        'success' => true,
                        'url' => $temp_path,
                        'filename' => $temp_filename,
                        'warning' => '图片转换失败，使用原图格式',
                        'repaired' => $repaired
                    ];
                } else {
                    echo json_encode(['success' => false, 'error' => '临时文件丢失']);
                    break;
                }
            }
            
            echo json_encode($response);
            break;

        case 'upload_background':
            // 背景图片上传
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => '没有上传文件或上传出错']);
                break;
            }

            $file = $_FILES['image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
            if (!in_array($file['type'], $allowed_types, true)) {
                echo json_encode(['success' => false, 'error' => '只允许上传 JPG, PNG, GIF, WebP 格式的图片']);
                break;
            }

            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => '图片大小不能超过 5MB']);
                break;
            }

            $upload_dir = 'uploads/background/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = uniqid('bg_') . '.' . $ext;
            $dest = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['success' => true, 'path' => $dest, 'filename' => $filename]);
            } else {
                echo json_encode(['success' => false, 'error' => '保存文件失败']);
            }
            break;

        case 'delete_background':
            // 删除背景图片
            $filepath = $_POST['filepath'] ?? $_GET['filepath'] ?? '';
            if (empty($filepath)) {
                echo json_encode(['success' => false, 'error' => '未指定文件']);
                break;
            }
            // 安全检查：只允许删除 uploads/background/ 下的文件
            $realpath = realpath($filepath);
            $basedir = realpath('uploads/background/');
            if ($realpath && $basedir && strpos($realpath, $basedir) === 0 && file_exists($realpath)) {
                unlink($realpath);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => '文件不存在或无权删除']);
            }
            break;

        case 'upload_qrcode':
            // 捐款二维码上传
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'error' => '没有上传文件或上传出错']);
                break;
            }
            $file = $_FILES['image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg', 'image/webp'];
            if (!in_array($file['type'], $allowed_types, true)) {
                echo json_encode(['success' => false, 'error' => '只允许上传 JPG, PNG, GIF, WebP 格式的图片']);
                break;
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'error' => '图片大小不能超过 2MB']);
                break;
            }
            $upload_dir = 'uploads/donate/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'qrcode_' . time() . '.' . $ext;
            $dest = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                echo json_encode(['success' => true, 'path' => $dest]);
            } else {
                echo json_encode(['success' => false, 'error' => '保存文件失败']);
            }
            break;

        case 'generate_epay':
            // 生成易支付链接
            $amount = floatval($_GET['amount'] ?? 0);
            $type = $_GET['type'] ?? 'alipay';
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'error' => '金额无效']);
                break;
            }
            $conn = get_db_connection();
            $stmt = $conn->query("SELECT donate_config FROM site_config LIMIT 1");
            $row = $stmt->fetch();
            $url = generate_epay_url($row['donate_config'] ?? '', $amount, $type);
            if (!empty($url)) {
                echo json_encode(['success' => true, 'url' => $url]);
            } else {
                echo json_encode(['success' => false, 'error' => '易支付未配置或配置不完整']);
            }
            break;

        default:
            // 未知操作
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => '未知操作']);
            break;
    }
} catch (Exception $e) {
    // 捕获所有异常并返回JSON错误
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '服务器内部错误',
        'debug' => '请检查服务器配置和PHP错误日志',
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    // 捕获PHP错误
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'PHP错误',
        'debug' => '请检查PHP配置和错误日志',
        'message' => $e->getMessage()
    ]);
}

// 确保没有其他输出
ob_end_flush();
?>