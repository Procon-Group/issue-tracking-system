<?php

$this->requireProject($_POST['project_id']);
$result = $this->getWikiNotifications($_POST['project_id'], $this->user_id, $_POST['from'], $_POST['to']);
if(!empty($result['notifs'])) {
    $html = '';
    foreach ($result['notifs'] as $res) {
        $html .= '<div class="notif-design"><a href="' . e(base_url('profile/' . $res['username'])) . '">' . e($res['fullname']) . '</a> <span>' . e($res['event']) . '</span> <a href="' . e(base_url('wiki/' . $res['proj_name'] . '/display/' . $res['key_space'] . '?viewPageId=' . $res['page_id'])) . '">' . e($res['title']) . '</a></div>';
        ?>

        <?php

    }
    $arr = array('html' => $html, 'num' => $result['num']);
} else {
    $arr = array('html' => $this->lang_php['no_notifs'].'!', 'num' => 0);
}
echo json_encode($arr);
?>