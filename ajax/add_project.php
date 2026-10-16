<?php

$this->requirePermission(array($GLOBALS['CONFIG']['PERMISSIONS']['SETTINGS_PAGE'], $GLOBALS['CONFIG']['PERMISSIONS']['CREATE_PROJECT']));
if (isset($_POST['conn_settings']) && is_array($_POST['conn_settings'])) {
    $_POST['sync_id'] = $this->setSync($_POST['conn_settings']);
}
if (isset($_POST['name'])) {
    echo $this->setProject($_POST);
    exit;
}
?>