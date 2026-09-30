<div
    style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
    <!-- Left: Search form -->
    <div style="flex:0 1 auto; display:flex; align-items:center; gap:6px;">
        <form method="GET" style="display:flex; gap:6px; align-items:center; margin:0;">
            <input type="hidden" name="action" value="search">

            <input name="q" type="text" placeholder="Search pages..."
                value="<?= isset($_GET['q']) ? h($_GET['q']) : '' ?>"
                style="width:180px; height:32px; padding:4px 6px; line-height:24px; box-sizing:border-box;">

            <select id="scope" name="scope" aria-label="Search in"
                style="height:32px; padding:4px 6px; line-height:24px;">
                <option value="both" <?= (isset($_GET['scope']) && $_GET['scope'] === 'title') ? '' : 'selected'; ?>>Title
                    + content</option>
                <option value="title" <?= (isset($_GET['scope']) && $_GET['scope'] === 'title') ? 'selected' : ''; ?>>Title
                    only</option>
            </select>

            <button class="btn" type="submit" style="height:32px; padding:4px 12px;">Search</button>
        </form>
    </div>

    <!-- Right: Admin buttons -->
    <div style="flex:0 0 auto; display:flex; gap:6px;">
        <?php if (is_admin()): ?>
            <?php if ($action === 'view'): ?>
                <a class="btn" href="?action=edit&page=<?= rawurlencode($page) ?>">Edit page</a>
            <?php endif; ?>
            <a class="btn" href="?action=create">Create new page</a>
            <a class="btn" href="?action=logs" style="background: #dc2626; color: white; border-color: #dc2626;">🛡️
                Logs</a>
        <?php endif; ?>
    </div>
</div>

<?php if (is_admin()): ?>
            <a class="btn" href="?action=admin_diagnostics"
                style="background: #4b5563; color: white; border-color: #374151.">🔧 Diagnostics</a>
        <?php endif; ?>

<div style="width:100%; border-bottom:1px solid #000000ff; margin-bottom:12px;"></div>