<?php
/**
 * Ly枪战辅助 - 卡密验证系统
 * 单文件PHP，无需数据库，数据存储在同目录 auth_data.json
 * 
 * API端点：
 *   ?action=validate&key=卡密&userid=用户ID    - 验证卡密
 *   ?action=bind&key=卡密&userid=用户ID        - 绑定卡密到设备
 *   ?action=getNotice                          - 获取公告
 *   ?action=getConfig                          - 获取配置
 */

$dataFile = __DIR__ . '/auth_data.json';

function loadData() {
    global $dataFile;
    if (!file_exists($dataFile)) {
        return [
            'keys' => [],
            'used' => [],
            'config' => [
                'public_mode' => false,
                'notice' => '欢迎使用 Ly枪战辅助！\n\n📢 公告内容待输入文本\n\n✨ 功能列表：\n• 高级ESP绘制\n• 智能自瞄辅助\n• 子弹追踪系统\n• 自动功能合集\n\n📝 使用说明：\n请仔细阅读功能说明后再开启，\n合理使用，享受游戏乐趣！'
            ]
        ];
    }
    $content = file_get_contents($dataFile);
    if (empty($content)) {
        return [
            'keys' => [],
            'used' => [],
            'config' => [
                'public_mode' => false,
                'notice' => '欢迎使用 Ly枪战辅助！\n\n📢 公告内容待输入文本'
            ]
        ];
    }
    return json_decode($content, true);
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ==================== API 模式 ====================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache, must-revalidate');

    $data = loadData();
    $action = $_GET['action'];

    switch ($action) {
        case 'validate':
            $key = isset($_GET['key']) ? trim($_GET['key']) : '';
            $userid = isset($_GET['userid']) ? trim($_GET['userid']) : '';

            // 公益模式：任何卡密都通过
            if (!empty($data['config']['public_mode']) && $data['config']['public_mode'] === true) {
                echo json_encode([
                    'status' => 'public_mode',
                    'notice' => $data['config']['notice'],
                    'msg' => '当前为公益模式，所有卡密均可使用'
                ]);
                exit;
            }

            if (empty($key) || !in_array($key, $data['keys'])) {
                echo json_encode(['status' => 'invalid', 'msg' => '卡密不存在']);
                exit;
            }

            if (isset($data['used'][$key])) {
                if ($data['used'][$key] === $userid) {
                    echo json_encode([
                        'status' => 'bound',
                        'notice' => $data['config']['notice'],
                        'msg' => '卡密已绑定当前设备'
                    ]);
                } else {
                    echo json_encode(['status' => 'used', 'msg' => '卡密已被其他设备使用']);
                }
                exit;
            }

            echo json_encode([
                'status' => 'valid',
                'notice' => $data['config']['notice'],
                'msg' => '卡密有效，首次使用将自动绑定'
            ]);
            exit;

        case 'bind':
            $key = isset($_GET['key']) ? trim($_GET['key']) : '';
            $userid = isset($_GET['userid']) ? trim($_GET['userid']) : '';

            if (!empty($key) && !empty($userid) && in_array($key, $data['keys'])) {
                $data['used'][$key] = $userid;
                saveData($data);
                echo json_encode(['status' => 'success', 'msg' => '绑定成功']);
            } else {
                echo json_encode(['status' => 'error', 'msg' => '参数错误或卡密无效']);
            }
            exit;

        case 'getNotice':
            echo json_encode([
                'status' => 'success',
                'notice' => $data['config']['notice']
            ]);
            exit;

        case 'getConfig':
            echo json_encode([
                'status' => 'success',
                'config' => $data['config']
            ]);
            exit;

        default:
            echo json_encode(['status' => 'error', 'msg' => '未知操作']);
            exit;
    }
}

// ==================== 管理界面 ====================
$data = loadData();

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $postData = json_decode(file_get_contents('php://input'), true);

    if (!$postData) {
        echo json_encode(['status' => 'error', 'msg' => '无效数据']);
        exit;
    }

    $type = $postData['type'] ?? '';

    switch ($type) {
        case 'addKeys':
            $newKeys = explode("\n", str_replace("\r", "", $postData['keys'] ?? ''));
            $added = 0;
            foreach ($newKeys as $k) {
                $k = trim($k);
                if (!empty($k) && !in_array($k, $data['keys'])) {
                    $data['keys'][] = $k;
                    $added++;
                }
            }
            saveData($data);
            echo json_encode(['status' => 'success', 'added' => $added]);
            exit;

        case 'deleteKey':
            $key = $postData['key'] ?? '';
            $data['keys'] = array_values(array_diff($data['keys'], [$key]));
            unset($data['used'][$key]);
            saveData($data);
            echo json_encode(['status' => 'success']);
            exit;

        case 'togglePublic':
            $data['config']['public_mode'] = !($data['config']['public_mode'] ?? false);
            saveData($data);
            echo json_encode([
                'status' => 'success',
                'public_mode' => $data['config']['public_mode']
            ]);
            exit;

        case 'updateNotice':
            $data['config']['notice'] = $postData['notice'] ?? '';
            saveData($data);
            echo json_encode(['status' => 'success']);
            exit;

        case 'clearUsed':
            $data['used'] = [];
            saveData($data);
            echo json_encode(['status' => 'success']);
            exit;

        case 'clearAll':
            $data['keys'] = [];
            $data['used'] = [];
            saveData($data);
            echo json_encode(['status' => 'success']);
            exit;
    }

    echo json_encode(['status' => 'error', 'msg' => '未知操作']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 Ly枪战辅助 - 卡密管理系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #151520 100%);
            color: #e0e0e0;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            padding: 40px 0;
            margin-bottom: 30px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        .header p { 
            color: #666; 
            font-size: 14px;
            margin-top: 8px;
        }
        .header .api-link {
            display: inline-block;
            margin-top: 12px;
            padding: 6px 16px;
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            border-radius: 20px;
            color: #667eea;
            font-size: 12px;
            font-family: 'Courier New', monospace;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label {
            color: #888;
            font-size: 13px;
            margin-top: 8px;
            font-weight: 500;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 24px;
            transition: border-color 0.3s;
        }
        .card:hover {
            border-color: rgba(255,255,255,0.1);
        }
        .card h2 {
            font-size: 16px;
            margin-bottom: 20px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        .card h2::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 18px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }

        textarea, input[type="text"] {
            width: 100%;
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 12px 14px;
            color: #e0e0e0;
            font-size: 14px;
            resize: vertical;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        textarea:focus, input:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea::placeholder {
            color: #555;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            gap: 6px;
            font-family: inherit;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-primary:hover { 
            opacity: 0.9; 
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-danger {
            background: rgba(220, 53, 69, 0.9);
            color: white;
        }
        .btn-danger:hover { background: #dc3545; }
        .btn-success {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }
        .btn-success:hover { background: #28a745; }
        .btn-warning {
            background: rgba(255, 193, 7, 0.9);
            color: #000;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #333;
            transition: .4s;
            border-radius: 28px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        input:checked + .slider {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .public-mode-card {
            position: relative;
            overflow: hidden;
        }
        .public-mode-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .public-mode-active::before {
            opacity: 1;
        }
        .public-mode-active {
            border-color: rgba(40, 167, 69, 0.3) !important;
            box-shadow: 0 0 30px rgba(40, 167, 69, 0.05);
        }
        .public-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .public-badge.on {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .public-badge.off {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }

        .notice-preview {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 16px;
            margin-top: 12px;
            white-space: pre-wrap;
            font-size: 13px;
            color: #aaa;
            max-height: 200px;
            overflow-y: auto;
            line-height: 1.6;
            font-family: inherit;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        th {
            color: #888;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        tr:hover td {
            background: rgba(255,255,255,0.02);
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        .badge-danger {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        .key-text {
            font-family: 'Courier New', monospace;
            background: rgba(0,0,0,0.3);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            color: #ccc;
            border: 1px solid rgba(255,255,255,0.05);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #555;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .toast.show { transform: translateX(0); }
        .toast.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .toast.error { background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%); }
        .toast.info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            .stats { grid-template-columns: 1fr; }
            .header h1 { font-size: 24px; }
        }

        /* 滚动条美化 */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #444; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Ly枪战辅助 - 卡密管理系统</h1>
            <p>Roblox 脚本授权验证服务端 | 单文件部署 | 无需数据库</p>
            <div class="api-link"><?php echo htmlspecialchars("{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"); ?></div>
        </div>

        <div class="stats">
            <div class="stat-item">
                <div class="stat-value" id="totalKeys">0</div>
                <div class="stat-label">总卡密数</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="usedKeys">0</div>
                <div class="stat-label">已使用 / 已绑定</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="unusedKeys">0</div>
                <div class="stat-label">未使用</div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>添加卡密</h2>
                <textarea id="newKeys" rows="6" placeholder="每行输入一个卡密，支持批量添加...&#10;例如：&#10;ABC123-DEF456&#10;XYZ789-ABC012&#10;..."></textarea>
                <div class="actions">
                    <button class="btn btn-primary" onclick="addKeys()">➕ 添加卡密</button>
                    <button class="btn btn-warning" onclick="clearAll()">🗑️ 清空所有</button>
                </div>
            </div>

            <div class="card public-mode-card" id="publicModeCard">
                <h2>
                    系统设置
                    <span class="public-badge off" id="publicBadge">普通模式</span>
                </h2>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding: 16px; background: rgba(0,0,0,0.2); border-radius: 12px;">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px; color: #fff;">🌟 公益模式</div>
                        <div style="font-size: 13px; color: #888;">开启后任何卡密都能通过验证（测试用）</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="publicMode" onchange="togglePublic()">
                        <span class="slider"></span>
                    </label>
                </div>

                <div style="margin-top: 10px;">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #fff;">📢 公告内容</div>
                    <textarea id="noticeText" rows="5" placeholder="输入公告内容，支持换行..."><?php echo htmlspecialchars($data['config']['notice'] ?? ''); ?></textarea>
                    <div class="notice-preview" id="noticePreview"></div>
                    <div class="actions">
                        <button class="btn btn-primary" onclick="updateNotice()">💾 保存公告</button>
                        <button class="btn btn-success" onclick="clearUsed()">🔄 重置所有绑定</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>卡密列表</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40%">卡密</th>
                            <th style="width: 15%">状态</th>
                            <th style="width: 25%">绑定用户ID</th>
                            <th style="width: 20%">操作</th>
                        </tr>
                    </thead>
                    <tbody id="keysTable"></tbody>
                </table>
            </div>
            <div class="empty-state" id="emptyState" style="display: none;">
                <div class="empty-state-icon">📭</div>
                <div>暂无卡密，请在上方添加</div>
            </div>
        </div>

        <div style="text-align: center; padding: 40px 0; color: #444; font-size: 13px;">
            Ly枪战辅助 · 卡密验证系统 · 服务端版本 v1.0
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
        let currentData = <?php echo json_encode($data, JSON_UNESCAPED_UNICODE); ?>;

        function showToast(msg, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = 'toast ' + type;
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function updateNoticePreview() {
            const text = document.getElementById('noticeText').value;
            document.getElementById('noticePreview').textContent = text || '（暂无内容）';
        }

        document.getElementById('noticeText').addEventListener('input', updateNoticePreview);
        updateNoticePreview();

        function render() {
            const keys = currentData.keys || [];
            const used = currentData.used || {};

            document.getElementById('totalKeys').textContent = keys.length;
            document.getElementById('usedKeys').textContent = Object.keys(used).length;
            document.getElementById('unusedKeys').textContent = keys.length - Object.keys(used).length;

            const tbody = document.getElementById('keysTable');
            const emptyState = document.getElementById('emptyState');

            if (keys.length === 0) {
                tbody.innerHTML = '';
                emptyState.style.display = 'block';
                return;
            }

            emptyState.style.display = 'none';
            tbody.innerHTML = keys.map(key => {
                const isUsed = used.hasOwnProperty(key);
                const userId = isUsed ? used[key] : '-';
                return `
                    <tr>
                        <td><span class="key-text">${escapeHtml(key)}</span></td>
                        <td><span class="badge ${isUsed ? 'badge-danger' : 'badge-success'}">${isUsed ? '已使用' : '未使用'}</span></td>
                        <td style="color: ${isUsed ? '#dc3545' : '#28a745'}; font-family: monospace; font-size: 13px;">${escapeHtml(userId)}</td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="deleteKey('${escapeHtml(key)}')">删除</button>
                        </td>
                    </tr>
                `;
            }).join('');

            const publicMode = currentData.config?.public_mode || false;
            document.getElementById('publicMode').checked = publicMode;
            const card = document.getElementById('publicModeCard');
            const badge = document.getElementById('publicBadge');
            if (publicMode) {
                card.classList.add('public-mode-active');
                badge.className = 'public-badge on';
                badge.textContent = '公益模式开启';
            } else {
                card.classList.remove('public-mode-active');
                badge.className = 'public-badge off';
                badge.textContent = '普通模式';
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function apiRequest(data) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                return await response.json();
            } catch (e) {
                showToast('请求失败: ' + e.message, 'error');
                return { status: 'error' };
            }
        }

        async function addKeys() {
            const textarea = document.getElementById('newKeys');
            const keys = textarea.value.trim();
            if (!keys) {
                showToast('请输入卡密', 'error');
                return;
            }
            const result = await apiRequest({ type: 'addKeys', keys: keys });
            if (result.status === 'success') {
                showToast(`成功添加 ${result.added} 个卡密`, 'success');
                textarea.value = '';
                setTimeout(() => location.reload(), 500);
            } else {
                showToast('添加失败', 'error');
            }
        }

        async function deleteKey(key) {
            if (!confirm(`确定要删除卡密 "${key}" 吗？`)) return;
            const result = await apiRequest({ type: 'deleteKey', key: key });
            if (result.status === 'success') {
                showToast('删除成功', 'success');
                setTimeout(() => location.reload(), 300);
            }
        }

        async function togglePublic() {
            const result = await apiRequest({ type: 'togglePublic' });
            if (result.status === 'success') {
                currentData.config.public_mode = result.public_mode;
                render();
                showToast(result.public_mode ? '公益模式已开启' : '公益模式已关闭', 'info');
            }
        }

        async function updateNotice() {
            const notice = document.getElementById('noticeText').value;
            const result = await apiRequest({ type: 'updateNotice', notice: notice });
            if (result.status === 'success') {
                showToast('公告已保存', 'success');
            }
        }

        async function clearUsed() {
            if (!confirm('确定要重置所有卡密绑定吗？此操作不可恢复！')) return;
            const result = await apiRequest({ type: 'clearUsed' });
            if (result.status === 'success') {
                showToast('已重置所有绑定', 'success');
                setTimeout(() => location.reload(), 300);
            }
        }

        async function clearAll() {
            if (!confirm('确定要清空所有卡密吗？此操作不可恢复！')) return;
            const result = await apiRequest({ type: 'clearAll' });
            if (result.status === 'success') {
                showToast('已清空所有卡密', 'success');
                setTimeout(() => location.reload(), 300);
            }
        }

        render();
    </script>
</body>
</html>