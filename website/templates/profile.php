<h2 class="page-title">User Profile</h2>

<?php
if (empty($_SESSION['username'])) {
    echo '<div class="muted">Please log in to view your profile.</div>';
    exit;
}

$conn = db_connect();
$username = $_SESSION['username'];

// Check if viewing another user's profile via URL parameter
$view_user = $_GET['user'] ?? $username;

// If no user parameter was provided, redirect to include it in URL
if (!isset($_GET['user'])) {
    header('Location: ?action=profile&user=' . urlencode($username));
    exit;
}

// Fetch full user details from database
$sql = "SELECT id, username, email, role, password FROM users WHERE username = '$view_user' LIMIT 1";
$result = $conn->query($sql);
$user = $result ? $result->fetch_assoc() : null;
$conn->close();

if (!$user) {
    echo '<div class="muted">User not found.</div>';
    exit;
}

?>

<div class="profile-details">
    <table style="width: 100%; border-collapse: collapse;">
        <tr style="border-bottom: 1px solid #ddd;">
            <td style="padding: 12px; font-weight: bold; width: 30%;">Username</td>
            <td style="padding: 12px;"><?= h($user['username']); ?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
            <td style="padding: 12px; font-weight: bold;">Email</td>
            <td style="padding: 12px;"><?= h($user['email'] ?? 'Not set'); ?></td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
            <td style="padding: 12px; font-weight: bold;">Password</td>
            <td style="padding: 12px;">
                <span id="password-display" style="font-family: monospace; background: #f5f5f5; padding: 4px 8px; border-radius: 4px;">••••••••</span>
                <button onclick="togglePassword()" style="margin-left: 8px; padding: 4px 12px; cursor: pointer; border: 1px solid #ccc; background: white; border-radius: 4px;">Show</button>
                <script>
                let passwordVisible = false;
                const passwordHash = <?= json_encode($user['password']); ?>;
                function togglePassword() {
                    const display = document.getElementById('password-display');
                    const button = event.target;
                    passwordVisible = !passwordVisible;
                    if (passwordVisible) {
                        display.textContent = passwordHash;
                        button.textContent = 'Hide';
                    } else {
                        display.textContent = '••••••••';
                        button.textContent = 'Show';
                    }
                }
                </script>
            </td>
        </tr>
        <tr style="border-bottom: 1px solid #ddd;">
            <td style="padding: 12px; font-weight: bold;">Role</td>
            <td style="padding: 12px;">
                <span style="padding: 4px 8px; background: <?= $user['role'] === 'admin' ? '#4CAF50' : '#2196F3'; ?>; color: white; border-radius: 4px; font-size: 0.9em;">
                    <?= h($user['role']); ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px; font-weight: bold;">User ID</td>
            <td style="padding: 12px;"><?= h($user['id']); ?></td>
        </tr>
    </table>
</div>

<div style="margin-top: 24px;">
    <a href="?action=view&page=Home" class="btn">Back to Home</a>
</div>
