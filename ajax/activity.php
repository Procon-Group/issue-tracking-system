<?php

if ((int) $_POST['projectid'] != 0) {
    $this->requireProject($_POST['projectid']);
}
require 'templates/activitystream.php';

$result = $this->getTicketsActivityStream($_POST['userid'], $_POST['projectid'], $_POST['from'], $_POST['to']);

if (!empty($result['mine']) && $_POST['type'] == 'true') {
    activityForeach($result['mine'], 0);
} elseif (!empty($result['other']) && $_POST['type'] == 'false') {
    activityForeach($result['other'], 0);
}
?>