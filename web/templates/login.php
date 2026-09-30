<h2 class="page-title">Login</h2>
<div class="login-box">
  <?php if(!empty($login_error)) : ?>
      <div class="muted" style="color:#a33;margin-bottom:8px"><?= h($login_error) ?></div>
  <?php endif; ?>
  <form method="POST" action="?action=login">
    <label for="username">Username</label>
    <input id="username" name="username" type="text" required value="<?= h($_POST['username'] ?? '') ?>">
    <label for="password" style="margin-top:8px">Password</label>
    <input id="password" name="password" type="password" required>
    <div style="margin-top:8px">
      <button class="btn" type="submit">Log in</button>
      <a href="?page=Home" class="small" style="margin-left:8px">Cancel</a>
    </div>
  </form>
</div>
