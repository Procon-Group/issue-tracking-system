<?php

if (!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}
/**
 * This class render pages 
 * Here are included header, footer, alerts, etc.. of pages
 * 
 * @author Kiril Kirkov
 */
require_once 'inc/db.php';
require_once 'inc/config.php';
require_once 'inc/settings.php';
require_once 'inc/helpers.php';
require_once 'classes/class.database.php';

class Main extends Database {

    private $alert;
    private $day = 30; //Login cookie expire time (in days)
    private $page;
    private $title;
    private $description;
    private $lang_js;
    private $lang_php;
    public $default_lang_abbr;
    public $user_lang = NULL;
    public $url;

    public $dash_filter;
    public $social;
    public $username;
    public $fullname;
    public $image;
    public $permissions;
    public $profession;
    public $email;
    public $email_notif;
    public $user_id;
    public $user_name;
    public $lastMessageId;
    public $serialized_send_files;

    public function __construct() {
        parent::__construct();
    }

    private function csrfCheck($page) { //every POST, every action link and logout must carry the session's token
        $action_params = array('delete', 'delete_user', 'delete_project', 'delete_space', 'delete_tmpl', 'deletePage', 'delete_comment', 'remove_track', 'removeLoginImage');
        $is_action = $_SERVER['REQUEST_METHOD'] === 'POST' || strtolower($page) === 'logout' || count(array_intersect_key($_GET, array_flip($action_params))) > 0;
        if (!$is_action) {
            return true;
        }
        if (isset($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['csrf_token'])) {
            $token = $_GET['csrf_token'];
        } else {
            $token = null;
        }
        unset($_POST['csrf_token']); //handlers that save $_POST must never see it
        return csrf_valid($token);
    }

    public function run($page, $is_ajax = false) {
        $this->loadLanguage();
        if ($this->csrfCheck($page) === false) {
            http_response_code(403);
            if ($is_ajax !== true) {
                echo '<!DOCTYPE html><meta charset="utf-8"><title>Request blocked</title><p>This request was blocked because it did not come from a page of this site, or your session expired. <a href="' . e(base_url('home')) . '">Go back</a>, reload the page and try again.</p>';
            }
            exit;
        }
        if ($is_ajax === true) {
            $arr = explode('/', url_segment());
            $ajax_page = strtolower(end($arr));
            $public_ajax = array('resetemail'); //used from the login page
            if (!in_array($ajax_page, $public_ajax, true) && $this->authenticate() === false) {
                http_response_code(403);
                exit;
            }
            if (preg_match('/^[a-z0-9_]+$/', $ajax_page) && file_exists('ajax/' . $ajax_page . '.php')) {
                include 'ajax/' . strtolower($ajax_page) . '.php';
            } else {
                response(404);
            }
        } else {
            $this->page = $page;
            $this->loginCheck();
            ob_start();
            $this->render($page);
            $page_content = ob_get_clean();
            ob_start();
            $this->_header($this->title, $this->description);
            echo $page_content;
            $this->_footer();
            echo csrf_inject(ob_get_clean());
        }
    }

    private function loadLanguage() {
        $abbr = $this->getDefaultLanguage();
        $this->default_lang_abbr = $abbr;
        if (isset($_SESSION['logged']) || isset($_COOKIE['logged'])) {
            //only picks the interface language; the cookie itself is verified later in authenticate()
            $user_id = isset($_SESSION['logged']) ? (int) $_SESSION['logged']['id'] : (int) explode(':', (string) $_COOKIE['logged'])[0];
            $lang_abbr = $this->getUserLanguage($user_id);
        }
        if (isset($lang_abbr) && $lang_abbr !== null) {
            $abbr = $lang_abbr;
            $this->user_lang = $lang_abbr;
        }
        if (file_exists('lang/lang_' . $abbr . '.php') && file_exists('lang/lang_' . $abbr . '.js')) {
            $this->setLanguageArr($abbr);
        } else if (file_exists('lang/lang_EN.php') && file_exists('lang/lang_EN.js')) {
            $this->setLanguageArr('EN');
        } else {
            throw new Exception("Cant find language file! " . 'lang/lang_' . $abbr . '.php OR ' . 'lang/lang_' . $abbr . '.js');
        }
    }

    private function setLanguageArr($abbr) {
        require_once 'lang/lang_' . $abbr . '.php';
        $this->lang_php = $LANG;
        $this->lang_js = 'lang/lang_' . $abbr . '.js';
        unset($LANG);
    }

    private function _header($title, $description, $tags = null, $to_body = null) {
        if (is_array($tags)) {
            $tags_inner = implode("\n", $tags);
        } elseif (!is_array($tags) && $tags !== null) {
            $tags_inner = $tags;
        } else {
            $tags_inner = '';
        }
        include '_html_parts/header.php';
    }

    private function _footer() {
        include '_html_parts/footer.php';
    }

    public function render($page) {
        $page = strtolower($page);
        if (file_exists('_html_parts/' . $page . '.php')) {
            $this->page = $page;
            include '_html_parts/' . $page . '.php';
        } else {
            response(404);
        }
    }

    public function get_alert() {
        if (isset($_SESSION['alert'])) {
            $this->alert = $_SESSION['alert'];
            unset($_SESSION['alert']);
        }
        $alert = $this->alert;
        unset($this->alert);
        return $alert;
    }

    public function set_alert($msg, $type, $login_alert = false) {
        if ($login_alert === true) {
            $login_alert = 'login-alert';
        } else {
            $login_alert = '';
        }
        //messages are plain text; line breaks become <br>
        $_SESSION['alert'] = '<div class="alert alert-' . e($type) . ' ' . $login_alert . ' top-20">' . nl2br(e($msg), false) . '</div>';
    }

    private function setRememberCookie($value, $expire) {
        set_app_cookie("logged", $value, $expire);
    }

    public function setLogged($cookie, $info_array) {
        $info_array = array('id' => (int) $info_array['id'], 'username' => $info_array['username']);
        session_regenerate_id(true);
        $this->setLastLogin($info_array['id']);
        if ($cookie === true) {
            //cookie holds a random token; only its hash is stored, so it cannot be forged or replayed from the db
            $token = random_token();
            $this->setRememberToken($info_array['id'], $token);
            $seconds = 60 * 60 * 24 * $this->day;
            $this->setRememberCookie($info_array['id'] . ':' . $token, time() + $seconds);
        }
        $_SESSION['logged'] = $info_array;
    }

    public function authenticate() { //true if the visitor has a valid session or remember-me cookie
        if (!isset($_SESSION['logged']) && isset($_COOKIE['logged'])) {
            $parts = explode(':', (string) $_COOKIE['logged'], 2);
            $info = count($parts) === 2 ? $this->checkRememberToken((int) $parts[0], $parts[1]) : false;
            if ($info === false) {
                $this->setRememberCookie('', time() - 3600);
                unset($_COOKIE['logged']);
                return false;
            }
            session_regenerate_id(true);
            $_SESSION['logged'] = $info;
        }
        if (!isset($_SESSION['logged'])) {
            return false;
        }
        $this->user_id = (int) $_SESSION['logged']['id'];
        $this->user_name = $_SESSION['logged']['username'];
        $usr_info = $this->getUsers(0, 0, null, $this->user_id);
        if (empty($usr_info)) {
            unset($_SESSION['logged']);
            return false;
        }
        $this->setLastActive($this->user_id);
        $this->dash_filter = unserialize($usr_info[0]['dash_filter'], array('allowed_classes' => false));
        $this->social = unserialize($usr_info[0]['social'], array('allowed_classes' => false));
        $this->username = $usr_info[0]['username'];
        $this->fullname = $usr_info[0]['fullname'];
        $this->image = $usr_info[0]['image'];
        $this->permissions = explode(",", $usr_info[0]['privileges']);
        $this->profession = $usr_info[0]['profession_name'];
        $this->email = $usr_info[0]['email'];
        $this->email_notif = $usr_info[0]['email_notif'];
        return true;
    }

    public function requirePermission($permission_ids) { //stop unless the user has one of these permissions
        foreach ((array) $permission_ids as $permission) {
            if (in_array($permission, (array) $this->permissions)) {
                return;
            }
        }
        http_response_code(403);
        exit;
    }

    public function requireProject($project_id) { //stop unless the user belongs to this project
        if ((int) $project_id <= 0 || $this->checkUserProjectPremission($project_id) == 0) {
            http_response_code(403);
            exit;
        }
    }

    public function loginCheck() {
        $logged = $this->authenticate();
        if ($logged === true && $this->page == 'login') {
            redirect(base_url('home'));
        } elseif ($logged === false && $this->page != 'login') {
            redirect(base_url('login'));
        }
    }

    public function ticketView($ticketAbbr) {
        $ticketAbbr = ticketAbbrParse($ticketAbbr);
        $result = $this->getTickets($this->project_abbr, $ticketAbbr['id']);
        if ($result === null || validTicketAbbr(url_segment(3)) == false) {
            return false;
        } else {
            return $result;
        }
    }

    public function uploadImage($where) {
        $image = null;
        if ($_FILES["image"]["name"] != null) {
            $target_file = $where . basename($_FILES["image"]["name"]);
            $imageFileType = pathinfo($target_file, PATHINFO_EXTENSION);
            $check = getimagesize($_FILES["image"]["tmp_name"]);
            if ($check !== false && $imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg" || $imageFileType == "gif") {
                if (file_exists($target_file)) {
                    $_FILES["image"]["name"] = time() . $_FILES["image"]["name"];
                    $target_file = $where . basename($_FILES["image"]["name"]);
                }
                $result_move = move_uploaded_file($_FILES["image"]["tmp_name"], $target_file);
                if ($result_move === false) {
                    writeLog('Cant move uploaded image for login form from admin panel. Function move_uploaded_file return false!');
                }
                if ($_FILES['image']['error'] == 0) {
                    $image = $_FILES["image"]["name"];
                } else {
                    $this->set_alert($this->lang_php['err_image_upload_status'] . ' - ' . $_FILES['image']['error'] . '!', 'danger');
                }
            } else {
                $this->set_alert($this->lang_php['not_image_upload'] . '!', 'danger');
            }
        }
        return $image;
    }

    public function removeLoginImage($image) {
        unlink($image);
        $this->dropLoginImage();
    }

    public function sendEmail($send_email) {
        require 'classes/PHPMailer/PHPMailerAutoload.php';
        $mail = new PHPMailer;
        if (!$mail instanceof PHPMailer) {
            writeLog(date('H:i:s / d.m.Y') . ' - Cant instance PHPMailer, maybe library is not loaded!! Contact developers!');
        }

        $mail->isSMTP();
        if (DEBUG_MODE === true) {
            $mail->SMTPDebug = 4;
        }
        $mail->Host = $send_email['hostname'];
        $mail->SMTPAuth = true;
        $mail->Username = $send_email['username'];
        $mail->Password = $send_email['password'];
        if ($send_email['ssl'] == 1) {
            $mail->SMTPSecure = 'ssl';
        }
        $mail->Port = $send_email['port'];
        $mail->setFrom($send_email['username'], $send_email['fromName']);
        $mail->addAddress($send_email['to_email'], $send_email['subject']);
        $myFile = $_FILES['attachments'];
        $fileCount = count($myFile["name"]);
        if ($fileCount > 0 && $myFile["name"][0] != "") {
            $filenames = array();
            for ($i = 0; $i < $fileCount; $i++) {
                $target_file = $GLOBALS['CONFIG']['ATTACHMENTS_DIR'] . basename($myFile["name"][$i]);
                if (file_exists($target_file)) {
                    $myFile["name"][$i] = time() . $myFile["name"][$i];
                    $target_file = $GLOBALS['CONFIG']['ATTACHMENTS_DIR'] . basename($myFile["name"][$i]);
                }
                move_uploaded_file(end($myFile["tmp_name"]), $target_file);
                $result_add_attachment = $mail->addAttachment($target_file);
                if ($result_add_attachment === true) {
                    $filenames[] = $myFile["name"][$i];
                } else {
                    writeLog(date('H:i:s / d.m.Y') . ' - Error add attachments: ' . $result_add_attachment);
                }
            }
            $this->serialized_send_files = serialize($filenames);
        }
        $mail->isHTML($send_email['is_html']);

        $mail->Subject = $send_email['subject'];
        $mail->Body = $send_email['body'] == '' ? ' ' : $send_email['body'];

        if (!$mail->send()) {
            writeLog(date('H:i:s / d.m.Y') . ' - Mailer Error (Test in debug mode for advanced info): ' . $mail->ErrorInfo);
            return false;
        } else {
            $this->lastMessageId = $mail->getLastMessageID();
            return true;
        }
    }

    public function logOut() {
        if (isset($_SESSION['logged']['id'])) {
            $this->setRememberToken($_SESSION['logged']['id'], null);
        }
        unset($_SESSION['logged']);
        $this->setRememberCookie('', time() - 3600);
        redirect(base_url('login'));
        exit;
    }

}
