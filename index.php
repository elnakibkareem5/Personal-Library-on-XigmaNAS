<?php
/*
 * Kindle Library - single-file PHP book browser/downloader
 * Designed for e-ink browsers: no JavaScript, no animations, high contrast,
 * big tap targets, paginated lists (few screen refreshes).
 */
error_reporting(E_ALL & ~E_NOTICE);
@set_time_limit(300);

$APP_NAME = 'My Library';
$PER_PAGE = 15;

$DATA     = __DIR__ . '/data';
if (!is_dir($DATA)) { @mkdir($DATA, 0777, true); }
$CFG_FILE = $DATA . '/config.json';
$IDX_FILE = $DATA . '/index.json';

// extension => mime type
$TYPES = array(
    'epub' => 'application/epub+zip',
    'pdf'  => 'application/pdf',
    'mobi' => 'application/x-mobipocket-ebook',
    'azw'  => 'application/vnd.amazon.ebook',
    'azw3' => 'application/vnd.amazon.ebook',
    'azw4' => 'application/vnd.amazon.ebook',
    'kfx'  => 'application/octet-stream',
    'kf8'  => 'application/octet-stream',
    'prc'  => 'application/x-mobipocket-ebook',
    'txt'  => 'text/plain',
    'rtf'  => 'application/rtf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'odt'  => 'application/vnd.oasis.opendocument.text',
    'fb2'  => 'application/x-fictionbook+xml',
    'djvu' => 'image/vnd.djvu',
    'cbz'  => 'application/vnd.comicbook+zip',
    'cbr'  => 'application/vnd.comicbook-rar',
    'lit'  => 'application/x-ms-reader',
    'chm'  => 'application/vnd.ms-htmlhelp',
    'htm'  => 'text/html',
    'html' => 'text/html',
);

/* ---------- helpers ---------- */
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function load_json($f) {
    $j = @file_get_contents($f);
    $a = $j ? json_decode($j, true) : null;
    return is_array($a) ? $a : array();
}

function save_json($f, $a) {
    return @file_put_contents($f, json_encode($a), LOCK_EX) !== false;
}

function human($b) {
    $u = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($b >= 1024 && $i < 3) { $b /= 1024; $i++; }
    return ($i ? round($b, 1) : $b) . ' ' . $u[$i];
}

function scan_books($root, $types) {
    $out  = array();
    $root = rtrim($root, '/\\');
    try {
        $dir = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $flt = new RecursiveCallbackFilterIterator($dir, function ($cur) {
            return substr($cur->getFilename(), 0, 1) !== '.';   // skip hidden files/folders
        });
        $it = new RecursiveIteratorIterator($flt, RecursiveIteratorIterator::LEAVES_ONLY,
                                            RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (!isset($types[$ext])) continue;
            $rel = substr($f->getPathname(), strlen($root) + 1);
            $out[] = array(
                'p' => str_replace('\\', '/', $rel),
                's' => $f->getSize(),
                'm' => $f->getMTime(),
                'e' => $ext,
            );
        }
    } catch (Exception $e) { /* ignore unreadable folders */ }
    usort($out, function ($a, $b) { return strnatcasecmp($a['p'], $b['p']); });
    return $out;
}

function self() { return $_SERVER['SCRIPT_NAME']; }

function link_to($over = array()) {
    $q = array();
    foreach (array('q', 't', 's', 'p') as $k) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') $q[$k] = $_GET[$k];
    }
    foreach ($over as $k => $v) {
        if ($v === null || $v === '') unset($q[$k]); else $q[$k] = $v;
    }
    return self() . ($q ? '?' . http_build_query($q) : '');
}

/* ---------- state ---------- */
$cfg  = load_json($CFG_FILE);
$root = isset($cfg['root']) ? $cfg['root'] : '';
$a    = isset($_GET['a']) ? $_GET['a'] : '';
$msg  = '';

/* ---------- download ---------- */
if ($a === 'dl') {
    $rel  = isset($_GET['f']) ? $_GET['f'] : '';
    $rr   = $root ? realpath($root) : false;
    $full = $rr ? realpath($rr . '/' . $rel) : false;
    $ext  = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
    if (!$full || !is_file($full) || !isset($TYPES[$ext])
        || strpos($full, rtrim($rr, '/\\') . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(404);
        exit('File not found');
    }
    while (ob_get_level()) { ob_end_clean(); }
    $name = basename($full);
    header('Content-Type: ' . $TYPES[$ext]);
    header('Content-Length: ' . filesize($full));
    header('Content-Disposition: attachment; filename="' . addslashes(preg_replace('/[^\x20-\x7E]/', '_', $name))
           . '"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('X-Content-Type-Options: nosniff');
    readfile($full);
    exit;
}

/* ---------- rescan ---------- */
if ($a === 'scan') {
    if ($root && is_dir($root)) save_json($IDX_FILE, scan_books($root, $TYPES));
    header('Location: ' . self());
    exit;
}

/* ---------- save settings ---------- */
if ($a === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $p  = trim(isset($_POST['root']) ? $_POST['root'] : '');
    $rp = $p !== '' ? realpath($p) : false;
    if ($rp && is_dir($rp) && is_readable($rp)) {
        $cfg['root'] = $rp;
        if (!save_json($CFG_FILE, $cfg)) {
            $msg = 'Could not write data/config.json - make the "data" folder writable.';
            $a = 'settings';
        } else {
            save_json($IDX_FILE, scan_books($rp, $TYPES));
            header('Location: ' . self());
            exit;
        }
    } else {
        $msg = 'Folder not found or not readable by the web server: ' . $p;
        $a = 'settings';
    }
}

/* ---------- page head ---------- */
function head($title) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . h($title) . '</title><style>'
       . 'body{margin:0;padding:8px;background:#fff;color:#000;font-family:Georgia,serif;font-size:22px;line-height:1.3}'
       . 'a{color:#000;text-decoration:none}'
       . 'h1{font-size:30px;margin:4px 0 10px}'
       . '.bar a,.chip,.btn{display:inline-block;border:2px solid #000;padding:8px 14px;margin:0 6px 8px 0;background:#fff}'
       . '.on{background:#000;color:#fff!important}'
       . '.on a{color:#fff}'
       . 'input[type=text]{font-size:22px;padding:8px;border:2px solid #000;width:60%;box-sizing:border-box}'
       . 'button{font-size:22px;padding:8px 16px;border:2px solid #000;background:#fff;color:#000}'
       . '.book{display:block;border-top:2px solid #000;padding:14px 4px}'
       . '.book b{font-size:24px}'
       . '.meta{font-size:18px}'
       . '.pager{margin:12px 0}'
       . '.pager a,.pager span{display:inline-block;border:2px solid #000;padding:10px 18px;margin-right:8px}'
       . '.dim{border-style:dashed!important}'
       . '.err{border:2px solid #000;padding:10px;margin:10px 0;font-weight:bold}'
       . '</style></head><body>';
}

/* ---------- settings page ---------- */
if ($a === 'settings') {
    head('Settings');
    echo '<h1>Settings</h1><div class="bar"><a href="' . h(self()) . '">&larr; Library</a></div>';
    if ($msg) echo '<div class="err">' . h($msg) . '</div>';
    echo '<form method="post" action="' . h(self()) . '?a=save">'
       . '<p>Folder where your books are (on the NAS):</p>'
       . '<input type="text" name="root" style="width:100%" value="' . h($root) . '" placeholder="/mnt/data/books"> '
       . '<p><button type="submit">Save &amp; scan</button></p></form>'
       . '<p class="meta">Subfolders are scanned too. Formats: ' . h(strtoupper(implode(', ', array_keys($TYPES)))) . '</p>';
    echo '</body></html>';
    exit;
}

/* ---------- library page ---------- */
$idx = load_json($IDX_FILE);
if ($root && is_dir($root) && !$idx && !file_exists($IDX_FILE)) {
    $idx = scan_books($root, $TYPES);
    save_json($IDX_FILE, $idx);
}

head($APP_NAME);
echo '<h1>' . h($APP_NAME) . '</h1>';
echo '<div class="bar"><a href="' . h(self()) . '">Home</a>'
   . '<a href="' . h(self()) . '?a=scan">Rescan</a>'
   . '<a href="' . h(self()) . '?a=settings">Settings</a></div>';

if (!$root) {
    echo '<div class="err">No books folder set yet.</div>'
       . '<p><a class="btn" href="' . h(self()) . '?a=settings">Set books folder</a></p></body></html>';
    exit;
}

// counts per format
$counts = array();
foreach ($idx as $b) { $counts[$b['e']] = isset($counts[$b['e']]) ? $counts[$b['e']] + 1 : 1; }
arsort($counts);

// filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$t = isset($_GET['t']) ? strtolower($_GET['t']) : '';
$s = isset($_GET['s']) ? $_GET['s'] : 'n';
$p = max(1, (int)(isset($_GET['p']) ? $_GET['p'] : 1));

$items = array();
$words = $q !== '' ? preg_split('/\s+/', $q) : array();
foreach ($idx as $b) {
    if ($t !== '' && $b['e'] !== $t) continue;
    $ok = true;
    foreach ($words as $w) { if (stripos($b['p'], $w) === false) { $ok = false; break; } }
    if ($ok) $items[] = $b;
}

if ($s === 'd')      usort($items, function ($x, $y) { return $y['m'] - $x['m']; });
elseif ($s === 'z')  usort($items, function ($x, $y) { return $y['s'] - $x['s']; });
else                 usort($items, function ($x, $y) { return strnatcasecmp(basename($x['p']), basename($y['p'])); });

$total = count($items);
$pages = max(1, (int)ceil($total / $PER_PAGE));
if ($p > $pages) $p = $pages;
$slice = array_slice($items, ($p - 1) * $PER_PAGE, $PER_PAGE);

// search
echo '<form method="get" action="' . h(self()) . '">'
   . '<input type="text" name="q" value="' . h($q) . '" placeholder="Search title / folder"> '
   . ($t !== '' ? '<input type="hidden" name="t" value="' . h($t) . '">' : '')
   . '<input type="hidden" name="s" value="' . h($s) . '">'
   . '<button type="submit">Search</button></form><div style="height:8px"></div>';

// format filter
echo '<div>';
echo '<a class="chip' . ($t === '' ? ' on' : '') . '" href="' . h(link_to(array('t' => null, 'p' => null))) . '">ALL ' . count($idx) . '</a>';
foreach ($counts as $ext => $n) {
    echo '<a class="chip' . ($t === $ext ? ' on' : '') . '" href="' . h(link_to(array('t' => $ext, 'p' => null))) . '">'
       . h(strtoupper($ext)) . ' ' . $n . '</a>';
}
echo '</div>';

// sort
echo '<div class="meta" style="margin-bottom:6px">Sort: ';
foreach (array('n' => 'A-Z', 'd' => 'Newest', 'z' => 'Largest') as $k => $label) {
    echo '<a class="chip' . ($s === $k ? ' on' : '') . '" href="' . h(link_to(array('s' => $k, 'p' => null))) . '">' . $label . '</a>';
}
echo '</div>';

// pager function
function pager($p, $pages, $total) {
    echo '<div class="pager">';
    echo $p > 1 ? '<a href="' . h(link_to(array('p' => $p - 1))) . '">&larr; Prev</a>' : '<span class="dim">&larr; Prev</span>';
    echo '<span style="border:0">Page ' . $p . '/' . $pages . ' (' . $total . ')</span>';
    echo $p < $pages ? '<a href="' . h(link_to(array('p' => $p + 1))) . '">Next &rarr;</a>' : '<span class="dim">Next &rarr;</span>';
    echo '</div>';
}

pager($p, $pages, $total);

if (!$slice) echo '<div class="err">No books found.</div>';

foreach ($slice as $b) {
    $title  = pathinfo($b['p'], PATHINFO_FILENAME);
    $folder = dirname($b['p']);
    echo '<a class="book" href="' . h(self() . '?a=dl&f=' . rawurlencode($b['p'])) . '">'
       . '<b>' . h($title) . '</b><br>'
       . '<span class="meta">' . h(strtoupper($b['e'])) . ' &middot; ' . human($b['s'])
       . ($folder !== '.' ? ' &middot; ' . h($folder) : '') . '</span></a>';
}
echo '<div style="border-top:2px solid #000"></div>';

pager($p, $pages, $total);
echo '</body></html>';
