<?php
/**
 * Debug information script - 用于查看PHP配置和错误日志位置
 * 使用后请删除此文件以确保安全
 */

// 尝试多种路径来找到config.php
$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php', 
    __DIR__ . '/../../../../config.php',
    dirname(__DIR__, 4) . '/config.php'
];

$config_loaded = false;
foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        require_once($config_path);
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    die('无法找到Moodle配置文件。请确保此脚本位于正确的插件目录中。');
}

// 确保只有管理员可以访问
try {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
} catch (Exception $e) {
    die('权限不足：只有管理员可以访问此页面。');
}

echo "<h1>AI Check Plugin Debug Information</h1>";

echo "<h2>文件路径信息</h2>";
echo "<p><strong>当前脚本路径:</strong> " . __DIR__ . "</p>";
echo "<p><strong>Moodle根目录:</strong> " . $CFG->dirroot . "</p>";
echo "<p><strong>插件目录:</strong> " . $CFG->dirroot . '/mod/assign/submission/ai_check/' . "</p>";

echo "<h2>PHP错误日志配置</h2>";
echo "<p><strong>log_errors:</strong> " . (ini_get('log_errors') ? 'On' : 'Off') . "</p>";
echo "<p><strong>error_log:</strong> " . (ini_get('error_log') ?: 'System default') . "</p>";
echo "<p><strong>display_errors:</strong> " . (ini_get('display_errors') ? 'On' : 'Off') . "</p>";

echo "<h2>Moodle配置</h2>";
echo "<p><strong>Moodle调试模式:</strong> " . ($CFG->debug ? 'Enabled (Level: ' . $CFG->debug . ')' : 'Disabled') . "</p>";
echo "<p><strong>Moodle数据目录:</strong> " . $CFG->dataroot . "</p>";
echo "<p><strong>Moodle版本:</strong> " . $CFG->version . "</p>";

echo "<h2>AI Check插件状态</h2>";

// 检查插件文件是否存在
$plugin_files = [
    'locallib.php',
    'classes/observer.php',
    'db/events.php',
    'version.php'
];

echo "<h3>插件文件检查:</h3>";
foreach ($plugin_files as $file) {
    $filepath = $CFG->dirroot . '/mod/assign/submission/ai_check/' . $file;
    $exists = file_exists($filepath);
    $status = $exists ? '✓ 存在' : '✗ 缺失';
    echo "<p><strong>{$file}:</strong> {$status}</p>";
}

// 检查数据库表
global $DB;
try {
    $table_exists = $DB->get_manager()->table_exists('assignsubmission_ai_check_grades');
    echo "<p><strong>数据库表存在:</strong> " . ($table_exists ? '是' : '否') . "</p>";
    
    if ($table_exists) {
        $count = $DB->count_records('assignsubmission_ai_check_grades');
        echo "<p><strong>AI批改记录数量:</strong> {$count}</p>";
    }
} catch (Exception $e) {
    echo "<p><strong>数据库错误:</strong> " . $e->getMessage() . "</p>";
}

// 检查最近的AI处理记录
try {
    if ($table_exists) {
        $recent_records = $DB->get_records('assignsubmission_ai_check_grades', null, 'timemodified DESC', '*', 0, 5);
        if ($recent_records) {
            echo "<h3>最近5条AI处理记录:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>提交ID</th><th>状态</th><th>错误信息</th><th>创建时间</th></tr>";
            foreach ($recent_records as $record) {
                $time = date('Y-m-d H:i:s', $record->timemodified);
                echo "<tr>";
                echo "<td>{$record->id}</td>";
                echo "<td>{$record->submission_id}</td>";
                echo "<td>{$record->status}</td>";
                echo "<td>" . ($record->error_message ?: 'None') . "</td>";
                echo "<td>{$time}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>没有找到AI处理记录</p>";
        }
    }
} catch (Exception $e) {
    echo "<p><strong>查询错误:</strong> " . $e->getMessage() . "</p>";
}

// 检查事件观察者
echo "<h2>事件观察者状态</h2>";
try {
    if (class_exists('assignsubmission_ai_check\observer')) {
        echo "<p><strong>Observer类:</strong> ✓ 存在</p>";
    } else {
        echo "<p><strong>Observer类:</strong> ✗ 不存在</p>";
    }
    
    // 检查events.php文件
    $events_file = $CFG->dirroot . '/mod/assign/submission/ai_check/db/events.php';
    if (file_exists($events_file)) {
        echo "<p><strong>events.php:</strong> ✓ 存在</p>";
        
        // 读取events.php内容检查
        $events_content = file_get_contents($events_file);
        if (strpos($events_content, 'assignsubmission_ai_check\observer') !== false) {
            echo "<p><strong>Observer注册:</strong> ✓ 正确</p>";
        } else {
            echo "<p><strong>Observer注册:</strong> ✗ 有问题</p>";
        }
    } else {
        echo "<p><strong>events.php:</strong> ✗ 不存在</p>";
    }
} catch (Exception $e) {
    echo "<p><strong>检查错误:</strong> " . $e->getMessage() . "</p>";
}

// 检查插件版本
try {
    $version_file = $CFG->dirroot . '/mod/assign/submission/ai_check/version.php';
    if (file_exists($version_file)) {
        // 安全地读取版本信息
        $plugin = null;
        $version_content = file_get_contents($version_file);
        
        if (preg_match('/\$plugin->version\s*=\s*(\d+)/', $version_content, $matches)) {
            $version = $matches[1];
            echo "<p><strong>插件版本:</strong> {$version}</p>";
        } else {
            echo "<p><strong>插件版本:</strong> 无法解析</p>";
        }
        
        if (preg_match('/\$plugin->release\s*=\s*[\'"]([^\'"]+)[\'"]/', $version_content, $matches)) {
            $release = $matches[1];
            echo "<p><strong>插件发布版本:</strong> {$release}</p>";
        } else {
            echo "<p><strong>插件发布版本:</strong> 无法解析</p>";
        }
    } else {
        echo "<p><strong>version.php:</strong> ✗ 文件不存在</p>";
    }
} catch (Exception $e) {
    echo "<p><strong>版本检查错误:</strong> " . $e->getMessage() . "</p>";
}

echo "<h2>服务器错误日志位置</h2>";
$possible_log_locations = [
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log', 
    '/var/log/nginx/error.log',
    '/var/log/php_errors.log',
    $CFG->dataroot . '/error.log'
];

echo "<p>请检查以下可能的日志文件位置：</p>";
echo "<ul>";
foreach ($possible_log_locations as $log_path) {
    $exists = file_exists($log_path);
    $status = $exists ? '✓ 存在' : '- 不存在';
    echo "<li><strong>{$log_path}:</strong> {$status}</li>";
}
echo "</ul>";

echo "<h2>建议的调试步骤</h2>";
echo "<ol>";
echo "<li>检查上述错误日志文件中的'AI Check Observer'相关条目</li>";
echo "<li>启用Moodle调试模式：网站管理 → 开发 → 调试</li>";
echo "<li>查看任务队列：网站管理 → 服务器 → 任务 → 任务队列</li>";
echo "<li>查看Moodle系统日志：网站管理 → 报告 → 日志</li>";
echo "<li>如果问题仍存在，提供上述信息给开发者</li>";
echo "</ol>";

echo "<p style='color: red;'><strong>重要提醒:</strong> 查看完调试信息后，请立即删除此文件以确保安全！</p>";
?> 