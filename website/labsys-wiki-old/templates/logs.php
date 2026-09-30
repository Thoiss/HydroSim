<h2 class="page-title">Security & Activity Logs</h2>

<?php if (!is_admin()): ?>
    <div class="muted" style="color:#a33;">Access denied. Administrator privileges required.</div>
<?php else: ?>
    
<div style="margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
        <input type="hidden" name="action" value="logs">
        
        <div>
            <label class="small muted" for="severity">Severity:</label><br>
            <select name="severity" id="severity" class="input" style="min-width: 150px;">
                <option value="">All</option>
                <option value="info" <?= ($_GET['severity'] ?? '') === 'info' ? 'selected' : '' ?>>Info</option>
                <option value="warning" <?= ($_GET['severity'] ?? '') === 'warning' ? 'selected' : '' ?>>Warning</option>
                <option value="critical" <?= ($_GET['severity'] ?? '') === 'critical' ? 'selected' : '' ?>>Critical</option>
                <option value="security" <?= ($_GET['severity'] ?? '') === 'security' ? 'selected' : '' ?>>Security</option>
            </select>
        </div>
        
        <div>
            <label class="small muted" for="filter_username">Username:</label><br>
            <input type="text" name="filter_username" id="filter_username" class="input" 
                   value="<?= h($_GET['filter_username'] ?? '') ?>" placeholder="Filter by user" style="min-width: 150px;">
        </div>
        
        <div>
            <label class="small muted" for="limit">Show:</label><br>
            <select name="limit" id="limit" class="input">
                <option value="50" <?= ($_GET['limit'] ?? '100') === '50' ? 'selected' : '' ?>>50 logs</option>
                <option value="100" <?= ($_GET['limit'] ?? '100') === '100' ? 'selected' : '' ?>>100 logs</option>
                <option value="250" <?= ($_GET['limit'] ?? '100') === '250' ? 'selected' : '' ?>>250 logs</option>
                <option value="500" <?= ($_GET['limit'] ?? '100') === '500' ? 'selected' : '' ?>>500 logs</option>
            </select>
        </div>
        
        <div>
            <button type="submit" class="btn">Filter</button>
            <a href="?action=logs" class="btn" style="text-decoration: none;">Clear</a>
        </div>
    </form>
</div>

<?php
$severity_filter = !empty($_GET['severity']) ? $_GET['severity'] : null;
$username_filter = !empty($_GET['filter_username']) ? $_GET['filter_username'] : null;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

$logs = ActivityLogger::getRecentLogs($limit, $severity_filter, $username_filter);
?>

<div style="margin-bottom: 15px;">
    <strong>Total logs shown:</strong> <?= count($logs) ?>
    <?php if ($severity_filter): ?>
        | <strong>Severity:</strong> <?= h($severity_filter) ?>
    <?php endif; ?>
    <?php if ($username_filter): ?>
        | <strong>Username:</strong> <?= h($username_filter) ?>
    <?php endif; ?>
</div>

<?php if (empty($logs)): ?>
    <div class="small muted">No logs found.</div>
<?php else: ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">
                    <th style="padding: 8px; text-align: left;">Timestamp</th>
                    <th style="padding: 8px; text-align: left;">Username</th>
                    <th style="padding: 8px; text-align: left;">IP Address</th>
                    <th style="padding: 8px; text-align: left;">Action</th>
                    <th style="padding: 8px; text-align: left;">Details</th>
                    <th style="padding: 8px; text-align: left;">Severity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $severity_colors = [
                        'info' => '#555',
                        'warning' => '#f59e0b',
                        'critical' => '#dc2626',
                        'security' => '#7c2d12'
                    ];
                    $severity_bg = [
                        'info' => '#f9fafb',
                        'warning' => '#fffbeb',
                        'critical' => '#fef2f2',
                        'security' => '#fef2f2'
                    ];
                    $color = $severity_colors[$log['severity']] ?? '#555';
                    $bg = $severity_bg[$log['severity']] ?? '#fff';
                    ?>
                    <tr style="border-bottom: 1px solid #eee; background: <?= $bg ?>;">
                        <td style="padding: 8px; white-space: nowrap; font-family: monospace; font-size: 12px;">
                            <?= h($log['timestamp']) ?>
                        </td>
                        <td style="padding: 8px; font-weight: 500;">
                            <?= h($log['username']) ?>
                        </td>
                        <td style="padding: 8px; font-family: monospace; font-size: 12px;">
                            <?= h($log['ip_address']) ?>
                        </td>
                        <td style="padding: 8px; white-space: nowrap;">
                            <code style="font-size: 11px; background: #e5e7eb; padding: 2px 6px; border-radius: 3px;">
                                <?= h($log['action']) ?>
                            </code>
                        </td>
                        <td style="padding: 8px; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                            <?= h($log['details']) ?>
                        </td>
                        <td style="padding: 8px; white-space: nowrap;">
                            <span style="color: <?= $color ?>; font-weight: 600; text-transform: uppercase; font-size: 11px;">
                                <?php if ($log['severity'] === 'security' || $log['severity'] === 'critical'): ?>
                                    ⚠️
                                <?php endif; ?>
                                <?= h($log['severity']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div style="margin-top: 20px; padding: 15px; background: #f9fafb; border-left: 4px solid #3b82f6;">
    <h3 style="margin: 0 0 10px 0; font-size: 14px;">Log Severity Levels</h3>
    <ul style="margin: 0; padding-left: 20px; font-size: 13px;">
        <li><strong>Info:</strong> Normal operations (logins, page views, etc.)</li>
        <li><strong>Warning:</strong> Notable events that may require attention (multiple failed logins, page deletions)</li>
        <li><strong>Critical:</strong> Critical system events</li>
        <li><strong>Security:</strong> Security-related events (CSRF failures, unauthorized access, account lockouts)</li>
    </ul>
</div>

<?php
// Detect suspicious patterns for current session user if logged in
if (!empty($_SESSION['username'])) {
    $alerts = ActivityLogger::detectSuspiciousPatterns($_SESSION['username']);
    if (!empty($alerts)):
?>
    <div style="margin-top: 20px; padding: 15px; background: #fef2f2; border-left: 4px solid #dc2626;">
        <h3 style="margin: 0 0 10px 0; font-size: 14px; color: #dc2626;">⚠️ Suspicious Activity Detected</h3>
        <ul style="margin: 0; padding-left: 20px; font-size: 13px; color: #7c2d12;">
            <?php foreach ($alerts as $alert): ?>
                <li><?= h($alert) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php
    endif;
}
?>

<?php endif; ?>
