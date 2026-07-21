<?php
// 捐款页面
// 显示捐款二维码和在线支付入口
require_once 'config.php';

if (!check_installed()) {
    header('Location: install.php');
    exit;
}

$config = get_site_config();
$donate_config = !empty($config['donate_config']) ? json_decode($config['donate_config'], true) : [];

// 如果捐款功能未启用，跳转首页
if (empty($donate_config['enabled'])) {
    header('Location: index.php');
    exit;
}

$title = $donate_config['title'] ?? '请我喝杯咖啡';
$desc = $donate_config['desc'] ?? '感谢您的支持与鼓励';
$alipay_qrcode = $donate_config['alipay_qrcode'] ?? '';
$wechat_qrcode = $donate_config['wechat_qrcode'] ?? '';
$qq_qrcode = $donate_config['qq_qrcode'] ?? '';
$epay_enabled = !empty($donate_config['epay_url']) && !empty($donate_config['epay_pid']) && !empty($donate_config['epay_key']);
$preset_amounts = $donate_config['preset_amounts'] ?? [5, 10, 20, 50];

$bg_render = get_background_render($config['background_config'] ?? '');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - <?= htmlspecialchars($config['site_name']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        <?= get_color_scheme_css($config['color_scheme'] ?? '') ?>
        <?= $bg_render['css'] ?>

        .donate-header {
            text-align: center;
            padding: 60px 0 30px;
            background: linear-gradient(135deg, var(--primary-light) 0%, #ffffff 100%);
            margin-bottom: 40px;
            border-radius: 0 0 30px 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
        }

        .donate-title {
            font-size: 2.5em;
            color: var(--text-dark);
            margin-bottom: 15px;
            font-weight: 700;
            animation: fadeInDown 1s ease-out;
        }

        .donate-desc {
            color: var(--text-light);
            font-size: 1.1em;
            animation: fadeInUp 1s ease-out 0.3s both;
        }

        .donate-section {
            margin-bottom: 40px;
        }

        .donate-section-title {
            font-size: 1.4em;
            color: var(--text-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
        }

        .qrcode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
        }

        .qrcode-card {
            text-align: center;
            padding: 25px;
            background: var(--glass-bg);
            border-radius: 16px;
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            transition: var(--magic-transition);
        }

        .qrcode-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }

        .qrcode-card h3 {
            margin-bottom: 15px;
            color: var(--text-dark);
            font-size: 1.1em;
        }

        .qrcode-card img {
            width: 200px;
            height: 200px;
            object-fit: contain;
            border-radius: 10px;
            border: 1px solid #eee;
        }

        .qrcode-card p {
            margin-top: 10px;
            color: var(--text-light);
            font-size: 0.9em;
        }

        .epay-section {
            padding: 30px;
            background: var(--glass-bg);
            border-radius: 16px;
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
        }

        .amount-presets {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .amount-btn {
            padding: 10px 25px;
            border: 2px solid var(--primary-blue);
            background: white;
            color: var(--primary-blue);
            border-radius: 12px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .amount-btn:hover,
        .amount-btn.active {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-green));
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 195, 247, 0.4);
        }

        .custom-amount {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .custom-amount input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .custom-amount input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.1);
        }

        .pay-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .pay-btn {
            padding: 14px 30px;
            border: none;
            border-radius: 14px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pay-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .pay-btn.alipay {
            background: linear-gradient(135deg, #1677ff, #4096ff);
        }

        .pay-btn.wxpay {
            background: linear-gradient(135deg, #07c160, #2dc770);
        }

        .pay-btn.qqpay {
            background: linear-gradient(135deg, #12b7f5, #3fc1f7);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 30px;
            transition: var(--magic-transition);
            padding: 12px 24px;
            background: var(--glass-bg);
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .back-button:hover {
            transform: translateX(-10px) translateY(-3px);
            box-shadow: 0 10px 30px rgba(79, 195, 247, 0.2);
        }

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .donate-title { font-size: 1.8em; }
            .qrcode-grid { grid-template-columns: 1fr; }
            .pay-buttons { flex-direction: column; }
            .pay-btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="donate-header">
        <div class="container">
            <h1 class="donate-title"><?= htmlspecialchars($title) ?></h1>
            <p class="donate-desc"><?= htmlspecialchars($desc) ?></p>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-button">&larr; 返回首页</a>

        <?php if (!empty($alipay_qrcode) || !empty($wechat_qrcode) || !empty($qq_qrcode)): ?>
        <div class="donate-section">
            <h2 class="donate-section-title">扫码捐款</h2>
            <div class="qrcode-grid">
                <?php if (!empty($alipay_qrcode)): ?>
                <div class="qrcode-card">
                    <h3>支付宝</h3>
                    <img src="<?= htmlspecialchars(fix_image_path($alipay_qrcode)) ?>" alt="支付宝二维码">
                    <p>打开支付宝扫一扫</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($wechat_qrcode)): ?>
                <div class="qrcode-card">
                    <h3>微信支付</h3>
                    <img src="<?= htmlspecialchars(fix_image_path($wechat_qrcode)) ?>" alt="微信二维码">
                    <p>打开微信扫一扫</p>
                </div>
                <?php endif; ?>
                <?php if (!empty($qq_qrcode)): ?>
                <div class="qrcode-card">
                    <h3>QQ 钱包</h3>
                    <img src="<?= htmlspecialchars(fix_image_path($qq_qrcode)) ?>" alt="QQ二维码">
                    <p>打开 QQ 扫一扫</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($epay_enabled): ?>
        <div class="donate-section">
            <h2 class="donate-section-title">在线支付</h2>
            <div class="epay-section">
                <div class="amount-presets" id="amountPresets">
                    <?php foreach ($preset_amounts as $amt): ?>
                    <button class="amount-btn" onclick="selectAmount(this, <?= $amt ?>)">&yen;<?= $amt ?></button>
                    <?php endforeach; ?>
                </div>

                <div class="custom-amount">
                    <span>&yen;</span>
                    <input type="number" id="customAmount" placeholder="输入自定义金额" min="0.01" step="0.01" oninput="clearPreset()">
                </div>

                <div class="pay-buttons">
                    <button class="pay-btn alipay" onclick="goPay('alipay')">支付宝支付</button>
                    <button class="pay-btn wxpay" onclick="goPay('wxpay')">微信支付</button>
                    <button class="pay-btn qqpay" onclick="goPay('qqpay')">QQ 钱包支付</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        let selectedAmount = 0;

        function selectAmount(btn, amount) {
            selectedAmount = amount;
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('customAmount').value = '';
        }

        function clearPreset() {
            selectedAmount = 0;
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('active'));
        }

        function goPay(type) {
            let amount = selectedAmount || parseFloat(document.getElementById('customAmount').value);
            if (!amount || amount <= 0) {
                alert('请选择或输入捐款金额');
                return;
            }
            fetch('api.php?action=generate_epay&amount=' + amount + '&type=' + type)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.url;
                    } else {
                        alert('支付发起失败：' + data.error);
                    }
                })
                .catch(() => alert('请求失败，请稍后重试'));
        }
    </script>
    <?php if (!empty($bg_render['js'])): ?>
    <script><?= $bg_render['js'] ?></script>
    <?php endif; ?>
</body>
</html>
