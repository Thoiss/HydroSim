<?php
$action = $_REQUEST['action'] ?? 'view';
$page = $_REQUEST['page'] ?? 'Home';

require_once __DIR__ . '/init_session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/modules/login_helpers.php';
require_once __DIR__ . '/modules/password_validation.php';
require_once __DIR__ . '/modules/output_escaping.php';
require_once __DIR__ . '/modules/authorization.php';
require_once __DIR__ . '/modules/diagnostics.php';
require_once __DIR__ . '/modules/api_test.php';
require_once __DIR__ . '/modules/api_fetch.php';
require_once __DIR__ . '/modules/api_tokens.php';
require_once __DIR__ . '/modules/error_display.php';
require_once __DIR__ . '/modules/verify_password.php';
require_once __DIR__ . '/ssrf_secure.php';

// --- ACTIONS ---
switch ($action) {

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $conn = db_connect();

            if ($conn && $stmt = $conn->prepare('SELECT * FROM users WHERE username=? LIMIT 1')) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    $stored = $row['password'];
                    $status = check_account_status($row, $username);
                    $login_error = $status['login_error'];
                    if ($status['ok']) {
                        $ok = verify_password($password, $stored);
                        if ($ok) {
                            handle_successful_login($conn, $row['id'], $row['username']);


                            $_SESSION['username'] = $row['username'];
                            $_SESSION['email'] = $row['email'];
                            $_SESSION['role'] = $row['role'];

                            if (function_exists('jwt_token_builder')) {
                                $api_token = jwt_token_builder($row);
                                $_SESSION['api_token'] = $api_token;
                            }

                            header('Location: ?page=' . rawurlencode($page));
                            exit;
                        } else {
                            $login_error = handle_failed_login($conn, $row['id'], $username, $row);
                        }
                    }
                } else {
                    $login_error = 'Invalid username or password.';
                }

                $stmt->close();
                $conn->close();
            } else {
                $login_error = function_exists('verbose_database_error') 
                                      ? verbose_database_error() 
                                      : 'Database error.';

            }
        }
        break;

    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {


            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $password_confirm = trim($_POST['password_confirm'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (!$username || !$password || !$password_confirm || !$email) {
                $register_error = 'All fields are required.';
                break;
            }

            $password_errors = validate_password($password, $password_confirm);

            if (!empty($password_errors)) {
                $register_error = implode(' ', $password_errors);
                break;
            }


            // Database
            $conn = db_connect();
            if ($conn) {
                $stmt = $conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $register_error = 'Username exists.';
                    $stmt->close();
                    $conn->close();
                    break;
                }
                $stmt->close();

                $hash = isset($generate_hash) 
                    ? $generate_hash($password, false) // Generates unsafe hash as md5 function from the input. 
                    : password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');

                $role = $_REQUEST['role'] ?? 'user';

$allowed_roles = ['user', 'admin'];
if (!in_array($role, $allowed_roles, true)) {
    $role = 'user';
}


                $stmt->bind_param('ssss', $username, $hash, $email, $role);

                if ($stmt->execute()) {
 

                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = $role;
                    $_SESSION['email'] = $email;

                    ActivityLogger::logRegistration($username, $role);

                    header('Location: ?page=Home');
                    exit;
                } else {
                    $register_error = 'Database error: could not create user.';
                }

                $stmt->close();
                $conn->close();
            } else {
                $register_error = 'Database connection error.';
            }
        }
        break;

    case 'logout':

        $logout_username = $_SESSION['username'] ?? 'unknown';
        ActivityLogger::logLogout($logout_username);
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
        header('Location: ?page=Home');
        exit;

    case 'save':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }
        if (!is_admin()) {
            ActivityLogger::logUnauthorizedAccess('page_save');
            http_response_code(403);
            die('Forbidden: only admins can modify pages.');
        }

        $save_page = $_POST['page'] ?? 'Home';
        $save_content = $_POST['content'] ?? '';
        $conn = db_connect();
        if ($conn) {
            if ($stmt = $conn->prepare('SELECT id FROM pages WHERE title=? LIMIT 1')) {
                $stmt->bind_param('s', $save_page);
                $stmt->execute();
                $res = $stmt->get_result();
                $excerpt = mb_substr($save_content, 0, 140);
                if ($row = $res->fetch_assoc()) {
                    $upd = $conn->prepare('UPDATE pages SET content=?, excerpt=? WHERE id=?');
                    $upd->bind_param('ssi', $save_content, $excerpt, $row['id']);
                    $upd->execute();
                    $upd->close();
                    ActivityLogger::logPageUpdate($save_page);
                } else {
                    $ins = $conn->prepare('INSERT INTO pages (title, content, excerpt) VALUES (?, ?, ?)');
                    $ins->bind_param('sss', $save_page, $save_content, $excerpt);
                    $ins->execute();
                    $ins->close();
                    ActivityLogger::logPageCreate($save_page);
                }
                $stmt->close();
            }
            $conn->close();
            header('Location: ?page=' . rawurlencode($save_page));
            exit;
        }
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }
        if (!is_admin()) {
            ActivityLogger::logUnauthorizedAccess('page_delete');
            http_response_code(403);
            die('Forbidden: only admins can delete pages.');
        }



// API token handling
if (function_exists('jwt_token_builder')) {
    $api_token = jwt_token_builder($row);
    $_SESSION['api_token'] = $api_token;
    header('Location: ?page=' . rawurlencode($page) . '&api_token=' . $api_token);
    exit;
}

        $del_page = $_POST['page'] ?? '';
        if ($del_page === 'Home') {
            echo "<script>alert('The Home page cannot be deleted.'); window.location='?page=Home';</script>";
            exit;
        }
        $conn = db_connect();
        if ($conn && $stmt = $conn->prepare('DELETE FROM pages WHERE title=? LIMIT 1')) {
            $stmt->bind_param('s', $del_page);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            ActivityLogger::logPageDelete($del_page);
        }
        header('Location: ?page=Home');
        exit;
}
