<?php

$_POST['project_id'] = $this->ticketProjectId($_POST['ticket_id']);
$this->requireProject($_POST['project_id']);
$result = $this->startTracking($_POST['status'], $_POST['ticket_id'], $this->user_id, $_POST['project_id']);
if ($result !== false) {
    
} else {
    echo 0;
    //log error
}
?>