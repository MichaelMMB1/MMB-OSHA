<?php
// logout.php – Log the user out, clear session data and cookies, and redirect to the login page

// 1. Start or resume the session so we can clear it
session_start();

// 2. Unset all session variables
$_SESSION = [];

// 3. If the session is propagated via a cookie, clear that cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),    // name of the session cookie
        '',                // empty value
        time() - 42000,    // set expiration in the past
        $params['path'], 
        $params['domain'], 
        $params['secure'], 
        $params['httponly']
    );
}

// 4. Destroy the session data on the server
session_destroy();

// 5. Send headers to prevent caching of any authenticated pages
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// 6. Redirect the user back to the login (index) page
header('Location: index.php');
exit;
