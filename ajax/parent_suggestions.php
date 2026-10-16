<?php

$this->requireProject($_POST['proj_id']);
$result = $this->getSuggestions($_POST['find'], $_POST['proj_id'], $_POST['space']);
if(!empty($result)) {
    foreach ($result as $res) {
        ?>
        <a href="javascript:void(0);" class="list-group-item select-suggestion" onclick="addParent(<?= ejs($res['title']) ?>, <?= ejs($res['id']) ?>, <?= ejs($_POST['space']) ?>)"><?= e($res['title']) ?></a>
        <?php
    }
} else {
    echo 0;
}
?>