<?php

$this->requireProject($_POST['projectid']);
$result = $this->changeTicketStatus($_POST['ticketid'], $_POST['tostatusid'], $_POST['projectid']);
if ($result !== false) {
    $st_id = $this->getStatuses($_POST['tostatusid']);
    $this->setTicketLog($this->user_id, $_POST['projectid'],  $this->lang_php['change_status_to'].' ' . $st_id[0]['name'], $_POST['ticketid']);
    echo 1;
} else {
    echo 0;
}
?>