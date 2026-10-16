<?php

$this->requirePermission($GLOBALS['CONFIG']['PERMISSIONS']['WIKI']['MOVE_PAGES']);
$this->requireProject($this->pageProjectId($_POST['page_id']));
$this->requireProject($this->spaceProjectId($_POST['space']));
if ((int) $_POST['parent'] > 0 && $this->pageProjectId($_POST['parent']) !== $this->spaceProjectId($_POST['space'])) {
    http_response_code(403);
    exit;
}
if($_POST['page_id'] == 0 || $_POST['space'] <= 0) {
    echo 0;
    exit;
}
$result = $this->movePage($_POST['parent'], $_POST['space'], $_POST['page_id']);
if(!empty($result)) {
    echo 1;
} else {
    echo 0;
}
?>