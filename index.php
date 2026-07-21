<?php
// 网站首页
// 显示网站主页内容，包括欢迎信息、项目列表和文章列表
require_once 'config.php';

// 检查是否已安装
if (!check_installed()) {
    header('Location: install.php');
    exit;
}

// 记录访问
record_visit();

// 获取网站配置
$config = get_site_config();

// 获取项目列表
$conn = get_db_connection();
$stmt = $conn->query("SELECT * FROM projects ORDER BY created_at DESC");
$projects = $stmt->fetchAll();

// 获取文章列表（限制最新15篇）
$stmt = $conn->query("SELECT id, title, created_at FROM articles ORDER BY created_at DESC LIMIT 15");
$articles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site_name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        <?= get_color_scheme_css($config['color_scheme'] ?? '') ?>

        <?php
        $bg_render = get_background_render($config['background_config'] ?? '');
        echo $bg_render['css'];
        ?>

        /* 首页特有样式 */
        .hero {
            text-align: center;
            padding: 60px 0;
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-green));
            color: white;
            margin-bottom: 40px;
            border-radius: 0 0 30px 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(79, 195, 247, 0.3);
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            right: -50%;
            bottom: -50%;
            background: radial-gradient(circle at center, transparent 30%, rgba(255,255,255,0.1) 70%);
            animation: float 25s linear infinite;
        }
        
        .hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
            z-index: 1;
        }
        
        @keyframes float {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
            100% { transform: rotate(360deg) scale(1); }
        }
        
        .hero h1 {
            font-size: 2.5em;
            margin-bottom: 15px;
            position: relative;
            z-index: 2;
            font-weight: 700;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            animation: fadeInDown 1s ease-out;
        }
        
        .hero p {
            font-size: 1.2em;
            opacity: 0.95;
            position: relative;
            z-index: 2;
            font-weight: 500;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 1s ease-out 0.3s both;
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
        
        .section-title {
            font-size: 2.5em;
            color: var(--text-dark);
            margin-bottom: 40px;
            position: relative;
            padding-bottom: 20px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            animation: fadeInLeft 1s ease-out;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--accent-green));
            border-radius: 2px;
            box-shadow: 0 2px 10px rgba(79, 195, 247, 0.3);
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
        
        .view-more {
            text-align: center;
            margin-top: 30px;
        }
        
        .view-more .btn {
            padding: 12px 40px;
            font-size: 18px;
        }
        
        /* 项目图标修复样式 */
        .project-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .project-icon span {
            font-size: 28px;
            font-weight: bold;
            color: white;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .hero {
                padding: 30px 0;
                margin-bottom: 20px;
                border-radius: 0 0 15px 15px;
            }
            
            .hero h1 {
                font-size: 1.5em;
                margin-bottom: 8px;
            }
            
            .hero p {
                font-size: 0.9em;
            }
        }
        
        @media (max-width: 480px) {
            .hero {
                padding: 20px 0;
                margin-bottom: 15px;
                border-radius: 0 0 10px 10px;
            }
            
            .hero h1 {
                font-size: 1.2em;
                margin-bottom: 5px;
            }
            
            .hero p {
                font-size: 0.8em;
            }
        }
    </style>
</head>
<body>
    <div class="hero">
        <h1><?= htmlspecialchars($config['site_name']) ?></h1>
        <p>个人主页 | 项目展示 | 技术分享</p>
    </div>
    
    <div class="container">
        <!-- 欢迎区域 -->
        <div class="card welcome-section">
            <div class="welcome-avatar">
                <?php if (!empty($config['avatar_path'])): ?>
                    <?php $avatar_path = fix_image_path($config['avatar_path']); ?>
                    <img src="<?= htmlspecialchars($avatar_path) ?>" 
                         alt="个人头像" 
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48Y2lyY2xlIGN4PSIxMDAiIGN5PSIxMDAiIHI9Ijk1IiBmaWxsPSIjNGZjM2Y3IiBvcGFjaXR5PSIwLjIiLz48dGV4dCB4PSIxMDAiIHk9IjExMCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjQwIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNGZjM2Y3Ij4/PC90ZXh0Pjwvc3ZnPg=='">
                <?php else: ?>
                    <div style="width:100%;height:100%;background:linear-gradient(135deg, #4fc3f7, #81c784);display:flex;align-items:center;justify-content:center;color:white;font-size:60px;">?</div>
                <?php endif; ?>
            </div>
            <div class="welcome-text">
                <div class="typing-text" id="welcomeText"></div>
            </div>
        </div>
        
        <!-- 个人介绍 -->
        <h2 class="section-title">关于我</h2>
        <div class="card intro-card">
            <?php
            // 解析markdown内容为HTML
            if (!empty($config['me_content'])) {
                echo parse_markdown($config['me_content']);
            } else {
                echo '<p style="text-align: center; color: var(--text-light);">请到后台设置个人简介...</p>';
            }
            ?>
        </div>
        
        <!-- 我的项目 -->
        <?php if (!empty($projects)): ?>
            <h2 class="section-title">我的项目</h2>
            <div class="projects-grid" id="projectsGrid">
                <?php 
                $project_count = 0;
                foreach ($projects as $project): 
                    if ($project_count >= 6) break;
                    $project_count++;
                ?>
                <div class="card project-card" onclick="showProjectModal(<?= htmlspecialchars(json_encode($project)) ?>)">
                    <div class="project-icon">
                        <?php 
                        // 修复图片路径
                        $icon_path = '';
                        if (!empty($project['icon_path'])) {
                            $icon_path = fix_image_path($project['icon_path']);
                        }
                        ?>
                        
                        <?php if (!empty($icon_path) && file_exists($icon_path)): ?>
                            <img src="<?= htmlspecialchars($icon_path) ?>" 
                                 alt="<?= htmlspecialchars($project['name']) ?>"
                                 onerror="this.parentElement.innerHTML='?'">
                        <?php else: ?>
                            <span>?</span>
                        <?php endif; ?>
                    </div>
                    <h3><?= htmlspecialchars($project['name']) ?></h3>
                    <p style="color: var(--text-light); margin-top: 10px;">
                        <?= mb_strlen($project['description']) > 100 ? 
                           mb_substr($project['description'], 0, 100) . '...' : 
                           $project['description'] ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($projects) > 6): ?>
                <div class="view-more">
                    <button class="btn" onclick="showAllProjects()">查看全部项目</button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- 我的文章 -->
        <?php if (!empty($articles)): ?>
            <h2 class="section-title">我的文章</h2>
            <div class="card">
                <div class="search-box">
                    <input type="text" placeholder="搜索文章标题..." oninput="searchArticles(this.value)">
                </div>
                <div class="articles-list" id="articlesList">
                    <?php foreach ($articles as $article): ?>
                        <a href="article.php?id=<?= $article['id'] ?>" class="article-item">
                            <h3><?= htmlspecialchars($article['title']) ?></h3>
                            <small style="color: var(--text-light);">
                                <?= date('Y-m-d', strtotime($article['created_at'])) ?>
                            </small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php if (count($articles) >= 15): ?>
                <div class="view-more">
                    <button class="btn" onclick="showAllArticles()">查看全部文章</button>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- 联系我 -->
        <?php if (!empty($config['qq']) || !empty($config['email'])): ?>
            <h2 class="section-title">联系我</h2>
            <div class="card">
                <div class="contact-section">
                    <?php if (!empty($config['qq'])): ?>
                        <div class="contact-item" onclick="copyToClipboard('<?= htmlspecialchars($config['qq']) ?>', 'QQ号')">
                            <i>📱</i>
                            <span>QQ: <?= htmlspecialchars($config['qq']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($config['email'])): ?>
                        <div class="contact-item" onclick="copyToClipboard('<?= htmlspecialchars($config['email']) ?>', '邮箱')">
                            <i>✉️</i>
                            <span>Email: <?= htmlspecialchars($config['email']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $donate_config = !empty($config['donate_config']) ? json_decode($config['donate_config'], true) : [];
        if (!empty($donate_config['enabled'])):
        ?>
        <div class="donate-entry" style="text-align:center; margin:30px 0;">
            <a href="donate.php" style="display:inline-flex; align-items:center; gap:10px; padding:14px 32px; background:linear-gradient(135deg, var(--primary-blue), var(--accent-green)); color:white; text-decoration:none; border-radius:30px; font-weight:600; font-size:1.1em; transition:all 0.3s ease; box-shadow:0 6px 20px rgba(79,195,247,0.3);" onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 30px rgba(79,195,247,0.4)'" onmouseout="this.style.transform=''; this.style.boxShadow='0 6px 20px rgba(79,195,247,0.3)'">
                💖 <?= htmlspecialchars($donate_config['title'] ?? '请我喝杯咖啡') ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- 页脚 -->
        <div class="footer">
            <?php if (!empty($config['custom_footer_html'])): ?>
                <div class="custom-footer"><?= $config['custom_footer_html'] ?></div>
            <?php endif; ?>
            <?php if (!empty($config['footer_text'])): ?>
                <p><?= htmlspecialchars($config['footer_text']) ?></p>
            <?php endif; ?>
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($config['site_name']) ?> 保留所有权利</p>
        </div>
    </div>
    
    <!-- 项目详情模态框 -->
    <div class="modal-overlay" id="projectModal">
        <div class="modal">
            <div class="close-modal" onclick="closeModal()">&times;</div>
            <div id="modalContent"></div>
        </div>
    </div>
    
    <!-- 通知 -->
    <div class="notification" id="notification"></div>
    
    <script>
        // 打字机效果 - 兼容性优化版
        (function() {
            const welcomeText = `<?= addslashes($config['welcome_text']) ?>`;
            let charIndex = 0;
            const typingElement = document.getElementById('welcomeText');
            
            // 检查元素是否存在
            if (!typingElement) {
                console.error('欢迎语元素不存在');
                return;
            }
            
            // 清空初始内容
            typingElement.textContent = '';
            
            function typeWriter() {
                if (charIndex < welcomeText.length) {
                    // 使用textContent而不是innerHTML，更安全且兼容性更好
                    typingElement.textContent += welcomeText.charAt(charIndex);
                    charIndex++;
                    
                    // 使用requestAnimationFrame获得更流畅的动画
                    setTimeout(typeWriter, 80);
                } else {
                    // 打字结束后添加完成类
                    typingElement.classList.add('typing-done');
                }
            }
            
            // 页面加载完成后开始打字效果
            if (document.readyState === 'loading') {
                window.addEventListener('load', function() {
                    setTimeout(typeWriter, 500);
                });
            } else {
                // 如果已经加载完成，直接执行
                setTimeout(typeWriter, 500);
            }
        })();
        
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
            document.querySelectorAll('.card, .section-title, .projects-grid, .articles-list').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(50px)';
                el.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                observer.observe(el);
            });
        })();
        
        // 显示项目模态框
        function showProjectModal(project) {
            const modalContent = `
                <h2 style="color: var(--text-dark); margin-bottom: 20px;">${project.name}</h2>
                ${project.link ? `<p style="margin-bottom: 20px;"><strong>链接: </strong><a href="${project.link}" target="_blank" style="color: var(--primary-blue);">${project.link}</a></p>` : ''}
                <div style="background: #f5f5f5; padding: 20px; border-radius: 10px;">
                    <h3 style="color: var(--text-dark); margin-bottom: 10px;">项目介绍</h3>
                    <p style="line-height: 1.8; color: var(--text-light);">${project.description}</p>
                </div>
            `;
            
            document.getElementById('modalContent').innerHTML = modalContent;
            document.getElementById('projectModal').style.display = 'flex';
            
            // 防止背景滚动
            document.body.style.overflow = 'hidden';
        }
        
        // 关闭模态框
        function closeModal() {
            document.getElementById('projectModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // 显示所有项目
        function showAllProjects() {
            fetch('api.php?action=get_all_projects')
                .then(response => response.json())
                .then(projects => {
                    const grid = document.getElementById('projectsGrid');
                    grid.innerHTML = '';
                    
                    projects.forEach(project => {
                        const projectCard = `
                            <div class="card project-card" onclick="showProjectModal(${JSON.stringify(project).replace(/"/g, '&quot;')})">
                                <div class="project-icon">
                                    ${project.icon_path ? 
                                        `<img src="${project.icon_path}" alt="${project.name}" onerror="this.parentElement.innerHTML='?'">` : 
                                        '<span>?</span>'
                                    }
                                </div>
                                <h3>${project.name}</h3>
                                <p style="color: var(--text-light); margin-top: 10px;">
                                    ${project.description.length > 100 ? 
                                        project.description.substring(0, 100) + '...' : 
                                        project.description}
                                </p>
                            </div>
                        `;
                        grid.innerHTML += projectCard;
                    });
                    
                    // 隐藏"查看全部"按钮
                    document.querySelector('.view-more').style.display = 'none';
                });
        }
        
        // 搜索文章
        function searchArticles(keyword) {
            if (keyword.length === 0) {
                // 重置为默认列表
                location.reload();
                return;
            }
            
            fetch(`api.php?action=search_articles&keyword=${encodeURIComponent(keyword)}`)
                .then(response => response.json())
                .then(articles => {
                    const list = document.getElementById('articlesList');
                    list.innerHTML = '';
                    
                    articles.forEach(article => {
                        const articleItem = `
                            <a href="article.php?id=${article.id}" class="article-item">
                                <h3>${article.title}</h3>
                                <small style="color: var(--text-light);">
                                    ${article.created_at.substring(0, 10)}
                                </small>
                            </a>
                        `;
                        list.innerHTML += articleItem;
                    });
                });
        }
        
        // 显示所有文章
        function showAllArticles() {
            fetch('api.php?action=get_all_articles')
                .then(response => response.json())
                .then(articles => {
                    const list = document.getElementById('articlesList');
                    list.innerHTML = '';
                    
                    articles.forEach(article => {
                        const articleItem = `
                            <a href="article.php?id=${article.id}" class="article-item">
                                <h3>${article.title}</h3>
                                <small style="color: var(--text-light);">
                                    ${article.created_at.substring(0, 10)}
                                </small>
                            </a>
                        `;
                        list.innerHTML += articleItem;
                    });
                    
                    // 隐藏"查看全部"按钮
                    document.querySelectorAll('.view-more')[1].style.display = 'none';
                });
        }
        
        // 复制到剪贴板
        function copyToClipboard(text, type) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                showNotification(`${type}已复制到剪贴板！`);
            } catch (err) {
                showNotification('复制失败，请手动复制', 'error');
            }
            
            document.body.removeChild(textArea);
        }
        
        // 显示通知
        function showNotification(message, type = 'success') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.style.background = type === 'error' ? '#f44336' : 'var(--accent-green)';
            notification.classList.add('show');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }
        
        // 点击模态框外部关闭
        document.getElementById('projectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
    <?php if (!empty($bg_render['js'])): ?>
    <script><?= $bg_render['js'] ?></script>
    <?php endif; ?>
</body>
</html>