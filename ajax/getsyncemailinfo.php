<?php

$this->requirePermission($GLOBALS['CONFIG']['PERMISSIONS']['SETTINGS_PAGE']); //returns the mailbox password
if (isset($_POST['eid'])) {
    $value = (int) $_POST['eid'];
}
$result = $this->getSyncInfo($value);
if (empty($result)) {
    echo 1;
} else {
    echo json_encode($result);
}
?>