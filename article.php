<?php
// 文章详情页
// 显示单篇文章的详细内容，包括标题、发布日期、阅读量和文章正文
require_once 'config.php';

if (!check_installed()) {
    header('Location: install.php');
    exit;
}

// 获取文章ID
$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// 获取文章信息
$conn = get_db_connection();
$stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

// 如果文章不存在，重定向到首页
if (!$article) {
    header('Location: index.php');
    exit;
}

// 增加阅读量
$stmt = $conn->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
$stmt->execute([$id]);

// 获取网站配置
$config = get_site_config();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($article['title']) ?> - <?= htmlspecialchars($config['site_name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        <?= get_color_scheme_css($config['color_scheme'] ?? '') ?>

        <?php
        $bg_render = get_background_render($config['background_config'] ?? '');
        echo $bg_render['css'];
        ?>

        .article-header {
            text-align: center;
            padding: 80px 0 40px;
            background: linear-gradient(135deg, var(--primary-light) 0%, #ffffff 100%);
            margin-bottom: 50px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }
        
        .article-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(79, 195, 247, 0.1), transparent);
            z-index: 1;
        }
        
        .article-title {
            font-size: 3.2em;
            color: var(--text-dark);
            margin-bottom: 25px;
            line-height: 1.3;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 2;
            animation: fadeInDown 1s ease-out;
        }
        
        .article-meta {
            color: var(--text-light);
            font-size: 1em;
            display: flex;
            justify-content: center;
            gap: 25px;
            flex-wrap: wrap;
            position: relative;
            z-index: 2;
            animation: fadeInUp 1s ease-out 0.3s both;
        }
        
        .article-meta span {
            padding: 8px 16px;
            background: var(--glass-bg);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        
        .article-content {
            font-size: 1.1em;
            line-height: 1.8;
            color: #444;
        }
        
        .article-content h2 {
            color: var(--text-dark);
            margin: 40px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
        }
        
        .article-content h3 {
            color: var(--text-dark);
            margin: 30px 0 15px;
        }
        
        .article-content p {
            margin-bottom: 20px;
        }
        
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .article-content code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        
        .article-content pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            margin: 20px 0;
        }
        
        .article-content blockquote {
            border-left: 4px solid var(--primary-blue);
            padding-left: 20px;
            margin: 20px 0;
            color: var(--text-light);
            font-style: italic;
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 40px;
            transition: var(--magic-transition);
            padding: 12px 24px;
            background: var(--glass-bg);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
            animation: fadeInLeft 1s ease-out 0.5s both;
        }
        
        .back-button:hover {
            transform: translateX(-10px) translateY(-3px);
            box-shadow: 0 10px 30px rgba(79, 195, 247, 0.2);
            border-color: var(--primary-blue);
        }
        
        @keyframes fadeInDown {
            0% {
                opacity: 0;
                transform: translateY(-50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(50px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInLeft {
            0% {
                opacity: 0;
                transform: translateX(-50px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <div class="article-header">
        <div class="container">
            <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
            <div class="article-meta">
                <span>发布日期: <?= date('Y年m月d日', strtotime($article['created_at'])) ?></span>
                <span>阅读量: <?= $article['views'] ?></span>
                <span>最后更新: <?= date('Y年m月d日', strtotime($article['updated_at'])) ?></span>
            </div>
        </div>
    </div>
    
    <div class="container">
        <a href="index.php" class="back-button">
            ← 返回首页
        </a>
        
        <div class="card">
            <div class="article-content">
                <?= parse_markdown($article['content']) ?>
            </div>
        </div>
    </div>
    
    <script>
        // 为代码块添加行号
        document.querySelectorAll('pre code').forEach((block) => {
            const lines = block.innerHTML.split('\n');
            const lineNumbers = lines.map((_, i) => `<span class="line-number">${i + 1}</span>`).join('\n');
            
            const wrapper = document.createElement('div');
            wrapper.className = 'code-block';
            wrapper.innerHTML = `
                <div class="line-numbers">${lineNumbers}</div>
                <div class="code-content">${block.innerHTML}</div>
            `;
            
            block.parentNode.replaceChild(wrapper, block);
        });
        
        // 添加代码高亮样式
        const style = document.createElement('style');
        style.textContent = `
            .code-block {
                display: flex;
                background: #2c3e50;
                border-radius: 12px;
                overflow: hidden;
                margin: 25px 0;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            }
            
            .line-numbers {
                background: #34495e;
                color: #7f8c8d;
                padding: 20px 15px;
                text-align: right;
                user-select: none;
                font-family: 'Courier New', monospace;
                border-right: 1px solid #4a637b;
            }
            
            .line-numbers span {
                display: block;
                line-height: 1.5;
            }
            
            .code-content {
                flex: 1;
                padding: 20px;
                overflow-x: auto;
                color: #ecf0f1;
                font-family: 'Courier New', monospace;
                line-height: 1.5;
            }
            
            .code-content .keyword { color: #e74c3c; }
            .code-content .string { color: #2ecc71; }
            .code-content .comment { color: #95a5a6; }
            .code-content .function { color: #3498db; }
        `;
        document.head.appendChild(style);
        
        // 滚动动画效果
        (function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // 观察需要动画的元素
            document.querySelectorAll('.card, .article-content h2, .article-content h3, .article-content p, .article-content img, .article-content blockquote, .code-block').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(50px)';
                el.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                observer.observe(el);
            });
        })();
    </script>
    <?php if (!empty($bg_render['js'])): ?>
    <script><?= $bg_render['js'] ?></script>
    <?php endif; ?>
</body>
</html>