<?php

if (!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}

function redirect($location = '', $get_parameters = true) {
    if (DEBUG_MODE === false) {
        if ($location == '') {
            if ($get_parameters == false) {
                $_SERVER['REQUEST_URI'] = preg_replace('/\?.*/', '', $_SERVER['REQUEST_URI']);
            }
            $location = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        if (!headers_sent()) {
            header('Location: ' . $location . '');
            exit;
        } else {
            echo '<script>document.location.href = ' . js($location) . '</script>';
            exit;
        }
    }
}

function response($code) {
    if ($code == 404) {
        include '_html_parts/error_codes/404.php';
    }
    if ($code == 403) {
        include '_html_parts/error_codes/403.php';
    }
    http_response_code($code);
    exit;
}

function hash_pass($password) {
    return password_hash((string) $password, PASSWORD_DEFAULT);
}

function verify_pass($password, $stored_hash) {
    $stored_hash = (string) $stored_hash;
    if (preg_match('/^[a-f0-9]{32}$/i', $stored_hash)) { //legacy unsalted md5, rehashed on next login
        return hash_equals(strtolower($stored_hash), md5((string) $password));
    }
    return password_verify((string) $password, $stored_hash);
}

function pass_needs_rehash($stored_hash) {
    return password_needs_rehash((string) $stored_hash, PASSWORD_DEFAULT);
}

function is_default_pass($stored_hash) { //the shipped admin/admin password must never be accepted
    return verify_pass('admin', $stored_hash);
}

function random_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function random_string($alphabet, $length) {
    $str = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $str .= $alphabet[random_int(0, $max)];
    }
    return $str;
}

function send_notification_emails($emails) {
    mail($emails, EMAIL_SUBJECT, EMAIL_MESSAGE);
}

function base_url($add = null, $atRoot = FALSE, $atCore = FALSE) {
    if (isset($_SERVER['HTTP_HOST'])) {
        $http = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $hostname = $_SERVER['HTTP_HOST'];
        $dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);

        $core = preg_split('@/@', str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath(dirname(__FILE__))), -1, PREG_SPLIT_NO_EMPTY);
        $core = $core[0];

        $tmplt = $atRoot ? ($atCore ? "%s://%s/%s/" : "%s://%s/") : ($atCore ? "%s://%s/%s/" : "%s://%s%s");
        $end = $atRoot ? ($atCore ? $core : $hostname) : ($atCore ? $core : $dir);
        $base_url = sprintf($tmplt, $http, $hostname, $end);
    } else
        $base_url = 'http://localhost/';

    return $base_url . $add;
}

function url_segment($n = null) {
    $dir = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    if ($dir != '/') {
        $current_link = str_replace($dir, '', $_SERVER["REQUEST_URI"]);
    } else {
        $current_link = ltrim($_SERVER["REQUEST_URI"], '/');
    }
    if (strpos($current_link, '?')) {
        $get = parse_url($current_link, PHP_URL_QUERY);
        $get = explode('&', $get);
        foreach ($get as $g) {
            $g = explode('=', $g);
            $_GET[$g[0]] = $g[1];
        }
        $current_link = preg_replace('/\?.*/', '', $current_link);
    }
    $link_array = explode('/', $current_link);
    if ($n == null) {
        return $current_link;
    } elseif (is_numeric($n) && isset($link_array[$n])) {
        return $link_array[$n];
    } else {
        return false;
    }
}

function word_limiter($word, $limit_to, $start_from = 0) {
    if (mb_strlen($word) <= 100) {
        return $word;
    }
    $cut = mb_substr($word, $start_from, $limit_to);
    $add_dots = $cut . '...';
    return $add_dots;
}

function secondsToTime($seconds, $want_array = false) {
    if ($seconds < 0) {
        $minus = '- ';
    }
    else {
        $minus = '';
    }
    $dtF = new DateTime("@0");
    $dtT = new DateTime("@$seconds");
    if ($want_array === false) {
        return $dtF->diff($dtT)->format($minus . ESTIMATED_TIME_TYPE);
    } else {
        $_POST['estimated_days'] = $dtF->diff($dtT)->format('%a');
        $_POST['estimated_hours'] = $dtF->diff($dtT)->format('%h');
        $_POST['estimated_minutes'] = $dtF->diff($dtT)->format('%i');
        $_POST['estimated_seconds'] = $dtF->diff($dtT)->format('%s');
    }
}

function validTicketAbbr($ticketAbbr) {
    if (preg_match('/^[a-zA-Z]{3}-[0-9]+$/', $ticketAbbr)) {
        return true;
    } else {
        return false;
    }
}

function ticketAbbrParse($ticketAbbr) {
    $arr = array();
    $editable = explode('-', $ticketAbbr);
    $arr['abbr'] = $editable[0];
    $arr['id'] = $editable[1];
    return $arr;
}

function nameDay($timestamp) {
    if ((time() - $timestamp) <= 24 * 60 * 60)
        return 'Today';
    else if ((time() - $timestamp) > 24 * 60 * 60 && (time() - $timestamp) <= 48 * 60 * 60)
        return 'Yesterday';
    else {
        return date('l', $timestamp);
    }
}

function returnImageUrl($image) {
    if ($image != null) {
        return $GLOBALS['CONFIG']['IMAGESUSERSUPLOADDIR'] . $image;
    } else {
        return 'assets/imgs/' . $GLOBALS['CONFIG']['DEFAULTUSERIMAGE'];
    }
}

function returnSpaceImageUrl($image) {
    if ($image != null) {
        return $GLOBALS['CONFIG']['IMAGESSPACESUPLOADDIR'] . $image;
    } else {
        return 'assets/imgs/' . $GLOBALS['CONFIG']['DEFAULTSPACEIMAGE'];
    }
}

function paging($action = '', $query_string = '', $count = 0, $page = 0, $per_page = 20) {
    $base = e(base_url($action));
    $query_string = e($query_string);
    if ($page < 0 || $page >= ceil($count / $per_page))
        $page = 0;

    $paging = '';
    $pz = $page - 1;
    $pp = $page + 1;
    $asa = $page;
    $number_pages = floor(($count - 1) / $per_page) + 1;
    if ($count > $per_page) {
        $paging .= '<nav><ul class="pagination">';

        if ($pp >= 2) {
            $paging .= '<li><a aria-label="Първа" href="' . $base . '?pg=0' . $query_string . '"><span aria-hidden="true">&laquo;&laquo;</span></a></li>';
            $paging .= '<li><a aria-label="Предишна" href="' . $base . '?pg=' . $pz . $query_string . '"><span aria-hidden="true">&laquo;</span></a></li>';
        }

        if ($number_pages > 10 and ( $page < 5 or $page == 5)) {
            $bbn = 1;
            $bbkr = 10;
        } elseif ($number_pages > 10 and $page > 5 and $page < $number_pages - 5) {
            $bbn = $page - 4;
            $bbkr = $page + 5;
        } elseif ($number_pages > 10 and ( $page == 5) and $page < $number_pages - 5) {
            $bbn = 1;
            $bbkr = $page + 5;
        } elseif ($number_pages > 10 and $page > 5 and ( $page > $number_pages - 5 or $page == $number_pages - 5)) {
            $bbn = $number_pages - 9;
            $bbkr = $number_pages;
        } elseif ($number_pages <= 10) {
            $bbn = 1;
            $bbkr = $number_pages;
        }

        if ($page > 2 and $bbn > 2) {
            $paging .= '<li><a href="' . $base . '?pg=0' . $query_string . '" >1</a></li>';
        }

        for ($i = $bbn; $i <= $bbkr; $i++) {
            if ($asa == $i - 1) {
                $paging .= '<li class="active"><a href="#">' . $i . '</a></li>';
            } else {
                $paging .= '<li><a href="' . $base . '?pg=' . ($i - 1) . $query_string . '">' . $i . '</a></li>';
            }
        }

        if ($page < $number_pages and $bbkr < $number_pages - 1) {
            $paging .= '<li><a>...</a></li>';
        }
        if ($page < $number_pages and $bbkr < $number_pages) {
            $paging .= '<li><a href="' . $base . '?pg=' . ($number_pages - 1) . $query_string . '" >' . $number_pages . '</a></li>';
        }
        if ($asa != ($number_pages - 1)) {
            $paging .= '<li><a aria-label="Следваща" href="' . $base . '?pg=' . $pp . $query_string . '"><span aria-hidden="true">&raquo;</span></a></li>';
            $paging .= '<li><a aria-label="Последна" href="' . $base . '?pg=' . ($number_pages - 1) . $query_string . '"><span aria-hidden="true">&raquo;&raquo;</span></a></li>';
        }

        $paging .= '</ul></nav>';
    }

    return $paging;
}

function getQueryStr($get, $unset) {
    if (is_array($unset)) {
        foreach ($unset as $un) {
            unset($get[$un]);
        }
    } else {
        unset($get[$unset]);
    }
    $queryString = '';
    foreach ($get as $key => $value) {
        if ($queryString == '')
            $queryString.='?';
        $queryString .= $key . '=' . $value . '&';
    }
    return rtrim($queryString, "&");
}

function getFolderLanguages() {
    $abbreviations = array();
    $abbrs = array();
    $dir_files = scandir('lang/');
    unset($dir_files[0], $dir_files[1]);
    foreach ($dir_files as $file) {
        preg_match("/^lang_([A-Z]{2,5})\.(php|js|PHP|JS)$/", $file, $abb);
        if (!empty($abb)) {
            $abbrs[$abb[1]][] = $abb[2];
        }
    }
    foreach ($abbrs as $ab_key => $ab) {
        if (array_search("php", $ab) !== false && array_search("js", $ab) !== false) {
            $abbreviations[] = $ab_key;
        }
    }
    return $abbreviations;
}

function writeLog($text, $logfile = 'logs/errors.log') {
    $file = fopen($logfile, "a");
    if ($file !== false) {
        fwrite($file, $text . "\n");
        fclose($file);
    }
}

/*
 * Output escaping - every value printed into a page goes through one of these
 */

function e($value) { //HTML text and attribute values
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function js($value) { //a JavaScript literal inside a <script> block
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}

function ejs($value) { //a JavaScript literal inside an HTML attribute, e.g. onclick="f(ejs-output)"
    return e(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE));
}

function clean_html($html) { //rich text from the editor: keep formatting, drop anything that can run script
    $html = (string) $html;
    if (trim($html) === '') {
        return '';
    }
    static $allowed = array(
        'a' => array('href', 'target', 'rel', 'name', 'title'),
        'img' => array('src', 'alt', 'width', 'height', 'title'),
        'table' => array('border', 'cellpadding', 'cellspacing', 'width', 'summary'),
        'td' => array('colspan', 'rowspan', 'width', 'height', 'valign'),
        'th' => array('colspan', 'rowspan', 'width', 'height', 'valign', 'scope'),
        'col' => array('span', 'width'), 'colgroup' => array('span', 'width'),
        'ol' => array('start', 'type'), 'ul' => array('type'), 'li' => array('value'),
        'font' => array('color', 'face', 'size'),
        'p' => array(), 'br' => array(), 'div' => array(), 'span' => array(), 'b' => array(), 'strong' => array(),
        'i' => array(), 'em' => array(), 'u' => array(), 's' => array(), 'strike' => array(), 'del' => array(), 'ins' => array(),
        'sub' => array(), 'sup' => array(), 'small' => array(), 'big' => array(), 'blockquote' => array(), 'pre' => array(),
        'code' => array(), 'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
        'hr' => array(), 'thead' => array(), 'tbody' => array(), 'tfoot' => array(), 'tr' => array(), 'caption' => array(),
        'dl' => array(), 'dt' => array(), 'dd' => array(), 'abbr' => array(), 'cite' => array(), 'q' => array(),
        'figure' => array(), 'figcaption' => array(), 'address' => array(),
    );
    static $global_attrs = array('class', 'style', 'title', 'align', 'dir', 'lang');
    static $drop_with_content = array('script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'link', 'meta', 'base', 'noscript', 'template', 'title', 'head');

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__clean_root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $doc->getElementById('__clean_root');
    if ($root === null) {
        return e(strip_tags($html));
    }

    $walk = function (DOMNode $node) use (&$walk, $allowed, $global_attrs, $drop_with_content) {
        for ($child = $node->firstChild; $child !== null; $child = $next) {
            $next = $child->nextSibling;
            if ($child instanceof DOMText) {
                continue;
            }
            if (!($child instanceof DOMElement)) { //comments, processing instructions, CDATA
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (in_array($tag, $drop_with_content, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!isset($allowed[$tag])) { //unknown tag: keep its text, lose the tag
                $walk($child);
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $keep = array_merge($allowed[$tag], $global_attrs);
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->nodeName);
                $value = $attr->nodeValue;
                $ok = in_array($name, $keep, true);
                if ($ok && ($name === 'href' || $name === 'src')) {
                    $ok = clean_url_ok($value, $tag === 'img' && $name === 'src');
                }
                if ($ok && $name === 'style') {
                    $ok = !preg_match('/expression|javascript|vbscript|url\s*\(|@import|behavior|-moz-binding|[<>\\\\]/i', $value);
                }
                if (!$ok) {
                    $child->removeAttribute($attr->nodeName);
                }
            }
            if ($tag === 'a' && $child->getAttribute('target') !== '') {
                $child->setAttribute('rel', 'noopener noreferrer');
            }
            $walk($child);
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

function clean_url_ok($url, $allow_data_image = false) {
    $url = trim(preg_replace('/[\x00-\x20]+/', '', html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if ($url === '' || $url[0] === '#' || $url[0] === '/' || $url[0] === '?' || $url[0] === '.') {
        return true;
    }
    if (preg_match('/^(https?|mailto|ftp|tel):/i', $url)) {
        return true;
    }
    if ($allow_data_image && preg_match('#^data:image/(png|gif|jpe?g|webp);base64,#i', $url)) {
        return true;
    }
    return !preg_match('/^[a-z0-9+.-]*:/i', $url); //relative link without a scheme
}

/*
 * CSRF protection - one secret per session; every POST and every action link must carry it
 */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = random_token();
    }
    return $_SESSION['csrf_token'];
}

function csrf_valid($token) {
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_param() { //for links that change data, e.g. ?delete_user=5&csrf_token=...
    return 'csrf_token=' . urlencode(csrf_token());
}

function csrf_inject($html) { //add the hidden token field to every POST form in a page
    return preg_replace('/<form\b[^>]*\bmethod\s*=\s*["\']?post["\']?[^>]*>/i', '$0' . csrf_field(), $html);
}

function set_app_cookie($name, $value, $expire) {
    $secure = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        setcookie($name, $value, array('expires' => $expire, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie($name, $value, $expire, '/; samesite=Lax', '', $secure, true);
    }
}
