<?php
if (!is_admin()) {
    http_response_code(403);
    echo '<h2>Toegang geweigerd</h2><div class="muted">Only admins may edit or create pages.</div>';
    return;
}

$edit_page = ($action === 'edit') ? $page : '';
$new_page_title = $_POST['new_page'] ?? '';

if ($action === 'create' && !$new_page_title) {
    ?>
    <h2 class="page-title">Create new page</h2>
    <form method="POST" action="?action=create">
      <label for="new_page">New page title</label>
      <input type="text" id="new_page" name="new_page" required>
      <div style="margin-top:8px">
        <button class="btn" type="submit">Continue</button>
      </div>
    </form>
    <?php
    return; 
}

if ($action === 'create') $edit_page = $new_page_title;

$content = '';
$conn = db_connect();
if ($conn && $edit_page && $stmt = $conn->prepare('SELECT content FROM pages WHERE title=? LIMIT 1')) {
    $stmt->bind_param('s', $edit_page);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $content = $row['content'];
    $stmt->close();
    $conn->close();
}
?>
<h2 class="page-title"><?= h($edit_page) ?></h2>

<!-- EDIT / CREATE FORM -->
<form method="POST" action="?action=save">
  <input type="hidden" name="page" value="<?= h($edit_page) ?>">
  <label for="content">Content</label>
  <textarea id="content" name="content" rows="12" style="width:98%; resize:vertical;"><?= h($content) ?></textarea>
  
  <!-- Import from URL -->
  <details style="margin-top:12px;padding:8px;background:#f5f5f5;border:1px solid #ddd;">
    <summary style="cursor:pointer;font-weight:bold;">⚙️ Advanced: Import from URL</summary>
    <div style="margin-top:8px;">
      <label for="import_url">Import content from URL:</label>
      <input type="text" id="import_url" placeholder="https://example.com/content.txt" style="width:70%;">
      <button type="button" class="btn" onclick="importFromUrl()">Import</button>
      <div id="import_result" style="margin-top:8px;color:#666;font-size:0.9em;"></div>
    </div>
  </details>

  <div style="margin-top:8px">
    <button class="btn" type="submit"><?= $content ? 'Save' : 'Create' ?></button>
</form>

<!-- URL Preview Tool -->
<details style="margin-top:16px;padding:8px;background:#f9f9f9;border:1px solid #ddd;">
  <summary style="cursor:pointer;font-weight:bold;">🔍 URL Preview Tool</summary>
  <div style="margin-top:8px;">
    <label for="preview_url">Preview URL content:</label>
    <input type="text" id="preview_url" placeholder="http://example.com" style="width:60%;">
    <button type="button" class="btn" onclick="previewUrl()">Preview</button>
    <div id="preview_result" style="margin-top:8px;padding:8px;background:white;border:1px solid #ddd;max-height:200px;overflow:auto;"></div>
  </div>
</details>

<script>
function importFromUrl() {
  const url = document.getElementById('import_url').value;
  const resultDiv = document.getElementById('import_result');
  
  if (!url) {
    resultDiv.textContent = 'Please enter a URL';
    return;
  }
  
  resultDiv.textContent = 'Importing...';
  
  fetch('?action=import_url', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'import_url=' + encodeURIComponent(url)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      document.getElementById('content').value = data.content;
      resultDiv.textContent = '✓ Imported ' + data.length + ' bytes from ' + url;
      resultDiv.style.color = 'green';
    } else {
      resultDiv.textContent = '✗ Error: ' + data.error;
      resultDiv.style.color = 'red';
    }
  })
  .catch(e => {
    resultDiv.textContent = '✗ Request failed: ' + e.message;
    resultDiv.style.color = 'red';
  });
}

function previewUrl() {
  const url = document.getElementById('preview_url').value;
  const resultDiv = document.getElementById('preview_result');
  
  if (!url) {
    resultDiv.textContent = 'Please enter a URL';
    return;
  }
  
  resultDiv.textContent = 'Loading...';
  
  fetch('?action=preview_url', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'url=' + encodeURIComponent(url)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      resultDiv.style.color = ''; // Reset color
      resultDiv.innerHTML = '<strong>Status:</strong> ' + data.http_code + '<br>' +
                           '<strong>Type:</strong> ' + (data.content_type || 'unknown') + '<br>' +
                           '<strong>Preview:</strong><br><pre style="white-space:pre-wrap;">' + 
                           (data.preview || '') + '</pre>';
    } else {
      resultDiv.textContent = 'Error: ' + data.error;
      resultDiv.style.color = 'red';
    }
  })
  .catch(e => {
    resultDiv.textContent = 'Request failed: ' + e.message;
    resultDiv.style.color = 'red';
  });
}
</script>

<!-- DELETE FORM -->
<?php if ($action === 'edit' && $edit_page !== 'Home'): ?>
<form method="POST" action="?action=delete" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this page?')">
  <input type="hidden" name="page" value="<?= h($edit_page) ?>">
  <button type="submit" class="btn btn-delete">Delete</button>
</form>
<?php endif; ?>
