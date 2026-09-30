<h2 class="page-title">Search results</h2>
<?php
$q = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? 'both';
if (!$q) { echo '<div class="muted">No search term provided.</div>'; return; }

$conn = db_connect();
if (!$conn) { echo '<div>Database error.</div>'; return; }

if ($scope === 'title')
    $sql = "SELECT title, excerpt FROM pages WHERE title LIKE CONCAT('%', ?, '%') LIMIT 50";
else
    $sql = "SELECT title, excerpt FROM pages WHERE title LIKE CONCAT('%', ?, '%') OR content LIKE CONCAT('%', ?, '%') LIMIT 50";

$stmt = $conn->prepare($sql);
if ($scope === 'title') $stmt->bind_param('s', $q);
else $stmt->bind_param('ss', $q, $q);
$stmt->execute();
$res = $stmt->get_result();

echo '<pre class="results">';
if ($res->num_rows === 0)
    echo "No results found for '".h($q)."'.\n";
else
    while ($row = $res->fetch_assoc())
        echo "Page: <a href='?page=".rawurlencode($row['title'])."'>".h($row['title'])."</a> — ".h($row['excerpt'])."\n";
echo '</pre>';

$stmt->close();
$conn->close();
?>
