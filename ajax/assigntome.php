<?php

$result = $this->assignToMe($_POST['ticket_id'], $this->user_id);
if ($result !== false) {
    echo 1;
} else {
    echo 0;
    //log error
}
?>