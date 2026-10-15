<?php
if(!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="<?= e($description) ?>">
        <?= e($tags_inner) ?>
        <title><?= e($title) ?></title>
        <link rel="stylesheet" href="<?= e(base_url('assets/css/bootstrap.min.css')) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/custom.css')) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/css/font-awesome.min.css')) ?>">
        <link rel="stylesheet" href="<?= e(base_url('assets/bootstrap-select-1.9.4/css/bootstrap-select.min.css')) ?>">
        <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
        <script src="<?= e(base_url('assets/js/jquery.min.js')) ?>"></script>
        <script>
            $.ajaxSetup({headers: {'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')}});
        </script>
        <script src="<?= e(base_url($this->lang_js)) ?>"></script>
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body<?= e($to_body) ?>>
        <?php if(DEBUG_MODE === true) { ?>
            <div class="text-center bg-danger"><?= e($this->lang_php['working_on']) ?> <b><?= e($this->lang_php['dev_mode']) ?></b>! <a tabindex="0" data-placement="bottom" data-toggle="popover" data-trigger="focus" title="<?= e($this->lang_php['what_diference']) ?>" data-content="<?= e($this->lang_php['dev_mode_info']) ?>"><?= e($this->lang_php['more_info']) ?></a></div>
        <?php } ?>
        <div id="wrapper">