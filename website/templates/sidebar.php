<aside class="card">
  <section class="toc">
    <?php if(!empty($_SESSION['username'])): ?>
      <h4>Welcome, <?= htmlspecialchars($_SESSION['username']); ?>!</h4>
      <ul class="small muted">
        <li><strong>Username:</strong> <?= htmlspecialchars($_SESSION['username']); ?></li>
        <li><strong>Email:</strong> <?= htmlspecialchars($_SESSION['email'] ?? 'Not set'); ?></li>
        <li><strong>Role:</strong> <?= htmlspecialchars($_SESSION['role'] ?? 'user'); ?></li>
      </ul>
      <p class="small muted"><a href="?action=profile">View Profile</a> | <a href="?page=Home">Home</a></p>
      <p class="small muted">You can now access all pages and search the wiki.</p>
    <?php else: ?>
      <h4>Welcome, Guest!</h4>
      <p class="small muted">Please <a href="?action=login">log in</a> to view pages and search the wiki.</p>
    <?php endif; ?>
  </section>
</aside>
