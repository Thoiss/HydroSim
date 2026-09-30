<?php
require_once __DIR__ . '/includes/actions.php';
?>
<?php include __DIR__ . '/templates/header.php'; ?>

<div class="wrap">
  <header>
    <div class="brand">
      <div class="logo">HS</div>
      <div>
        <h1>HydroSim Wiki — Water distribution simulation environment</h1>
        <div class="lead">
          Hardware & Software Tools • Application Notes
        </div>
      </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <?php if (!empty($_SESSION['username'])): ?>
        <div class="small muted">Logged in as <strong><?= h($_SESSION['username']); ?></strong>
          (<?= h($_SESSION['role'] ?? 'user'); ?>)</div>
        <form method="POST" action="?action=logout" style="display:inline; margin:0;">
          <button class="btn" type="submit">Log out</button>
        </form>
      <?php else: ?>
        <a class="btn" href="?action=login">Log in</a>
        <a class="btn" href="?action=register">Register</a>
      <?php endif; ?>
    </div>
  </header>



  <main class="card">
    <?php
    if (empty($_SESSION['username'])) {
      if (!in_array($action, ['login', 'register', 'profile']) && !($action === 'view' && $page === 'Home')) {
        echo "<div class='small muted'>Please log in to view pages.</div>";
        $action = 'view';
        $page = 'Home';
      }
    }

    if (!empty($_SESSION['username']) && $action !== 'login' && $action !== 'register') {
      include 'templates/toolbar.php';
    }

    if ($action === 'login')
      include 'templates/login.php';
    elseif ($action === 'register')
      include 'templates/register.php';
    elseif ($action === 'view')
      include 'templates/view.php';
    elseif (in_array($action, ['edit', 'create']))
      include 'templates/edit.php';
    elseif ($action === 'search')
      include 'templates/search.php';
    elseif ($action === 'profile')
      include 'templates/profile.php';
    elseif ($action === 'logs')
      include 'templates/logs.php';
    elseif ($action === 'admin_diagnostics')
      include 'templates/diagnostics.php';
      else
        // For custom actions (like SSRF), actions.php will handle output and exit
    ?>
  </main>

  <?php include 'templates/sidebar.php'; ?>
  <?php include 'templates/footer.php'; ?>
</div>
