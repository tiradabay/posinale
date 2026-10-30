<?php
/**
 * Authentication and Authorization Guard
 * Gabol's Coffee & More POS System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protected responses must never be reused from the browser's HTTP cache.
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function logout_token() {
    if (empty($_SESSION['logout_token'])) {
        $_SESSION['logout_token'] = bin2hex(random_bytes(32));
    }
    return htmlspecialchars($_SESSION['logout_token'], ENT_QUOTES, 'UTF-8');
}

function render_session_history_guard() {
    $urls = get_auth_redirect_urls();
    $base = dirname(dirname($urls['login']));
    $roles = $GLOBALS['auth_page_roles'] ?? [];
    $version = filemtime(__DIR__ . '/../assets/session_history_guard.js');
    echo '<script defer src="' . htmlspecialchars($base . '/assets/session_history_guard.js?v=' . $version, ENT_QUOTES, 'UTF-8') . '" data-session-url="' . htmlspecialchars($base . '/login/check_session.php', ENT_QUOTES, 'UTF-8') . '" data-login-url="' . htmlspecialchars($urls['login'], ENT_QUOTES, 'UTF-8') . '" data-allowed-roles="' . htmlspecialchars(implode(',', $roles), ENT_QUOTES, 'UTF-8') . '"></script>';
}

/** Accept only identities and exact role values established by staff login. */
function get_authenticated_role() {
    $id = $_SESSION['user_id'] ?? null;
    $role = $_SESSION['role'] ?? null;
    if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/D', (string)$id) || (float)$id > 2147483647) {
        return null;
    }
    return is_string($role) && in_array($role, ['admin', 'cashier'], true) ? $role : null;
}

/**
 * Helper to determine base URL for redirecting
 */
function get_auth_redirect_urls() {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($scriptName));
    
    // If running inside /components or /login, strip the trailing folder
    $lastFolder = strtolower(basename($dir));
    if ($lastFolder === 'components' || $lastFolder === 'login') {
        $base = dirname($dir);
    } else {
        $base = $dir;
    }
    
    $base = rtrim(str_replace('\\', '/', $base), '/');
    if ($base === '.' || $base === '/') {
        $base = '';
    }

    return [
        'login'   => $base . '/login/F_login.php',
        'admin'   => $base . '/admin_dash.php',
        'cashier' => $base . '/cashier_dash.php',
        'landing' => $base . '/landing_page.php'
    ];
}

/**
 * Enforce authentication and role permissions for web pages (HTML)
 *
 * @param array $allowed_roles List of allowed roles, e.g. ['admin'], ['admin', 'cashier']. Empty array means any logged-in user.
 */
function require_auth(array $allowed_roles = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $urls = get_auth_redirect_urls();
    $userRole = get_authenticated_role();
    $isLoggedIn = $userRole !== null;

    if (!$isLoggedIn) {
        header("Location: " . $urls['login']);
        exit;
    }

    if (!empty($allowed_roles)) {
        if (!in_array($userRole, $allowed_roles, true)) {
            // Logged in, but unauthorized for this page
            if ($userRole === 'cashier') {
                header("Location: " . $urls['cashier']);
                exit;
            } elseif ($userRole === 'admin') {
                header("Location: " . $urls['admin']);
                exit;
            } else {
                header("Location: " . $urls['login']);
                exit;
            }
        }
    }
    $GLOBALS['auth_page_roles'] = $allowed_roles ?: ['admin', 'cashier'];
}

/**
 * Enforce authentication and role permissions for API endpoints (JSON)
 *
 * @param array $allowed_roles List of allowed roles
 */
function require_api_auth(array $allowed_roles = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json');

    $userRole = get_authenticated_role();
    $isLoggedIn = $userRole !== null;

    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized: Please log in to access this resource'
        ]);
        exit;
    }

    if (!empty($allowed_roles)) {
        if (!in_array($userRole, $allowed_roles, true)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Forbidden: You do not have permission to perform this action'
            ]);
            exit;
        }
    }
}
?>
