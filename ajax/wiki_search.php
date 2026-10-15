<?php
$result = $this->getSuggestions($_POST['find'], $_POST['proj_id']);
if(!empty($result)) {
    foreach ($result as $res) {
        ?>
        <a href="<?= e(base_url('wiki/' . $_POST['proj'] . '/display/' . $res['key_space'] . '?viewPageId=' . $res['id'])) ?>" class="list-group-item select-suggestion"><i class="fa fa-file-o"></i> <?= e($res['title']) ?></a>
        <?php
    }
} else {
    echo 0;
}
?>