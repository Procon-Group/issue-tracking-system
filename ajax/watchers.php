<?php

$_POST['u_id'] = $this->user_id; //watch/unwatch is always for yourself
$this->requireProject(isset($_POST['t_id']) ? $this->ticketProjectId($_POST['t_id']) : $this->pageProjectId(isset($_POST['p_id']) ? $_POST['p_id'] : 0));
$result = $this->changeWatchers($_POST);
if ($result !== false) {
    echo 1;
} else {
    echo 0;
}
?>