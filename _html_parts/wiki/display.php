<?php
if (!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}

if (url_segment(3) !== false && url_segment(3) != null) {
    $result_space = $this->getSpaces(url_segment(3), $this->project_id);
    if (empty($result_space)) {
        redirect(base_url('wiki/' . $project_name . '/home'));
    }
} else {
    redirect(base_url('wiki/' . $project_name . '/home'));
}
$space_info = $result_space[0];
$space_id = $space_info['id'];
$pages_for_space = $this->getPagesForSpace($space_id);

$page_info = array();
if (isset($_GET['viewPageId']) && (int) $_GET['viewPageId'] > 0) {
    $page_info = $this->getPageInfo((int) $_GET['viewPageId'], $space_id);
    if (empty($page_info)) {
        $this->set_alert($this->lang_php['page_not_found'] . '!', 'danger');
        echo $this->get_alert();
    }
}

if (isset($_GET['viewPageId'])) {
    $watchers = $this->getWatchers($_GET['viewPageId'], 1);
    $history_count = $this->getHistoryCount($_GET['viewPageId']);
    if ($history_count > 0) {
        if (!isset($_GET['history'])) {
            $maxh_id = $this->getHistoryPage($_GET['viewPageId']);
        } else {
            $history_nav = $this->getHistoryPage($_GET['viewPageId'], $_GET['history']);
        }
    }
    $this->title = $this->project_name . ' - [' . $space_info['name'] . ']' . $page_info['title'];
} else {
    $this->title = $this->project_name . ' - ' . $this->lang_php['title_wiki'] . ' - ' . $space_info['name'];
}

if (isset($_GET['deletePage'])) {
    $this->deletePage($_GET['deletePage']);
    $this->set_alert($this->lang_php['page_was_deleted'] . 'deleted!', 'success');
    redirect(base_url('wiki/' . $project_name . '/display/' . $space_info['key_space']));
}

$shortcuts = $this->getSpaceShortcuts();
if (isset($_GET['shortcuts']) && sizeof($_GET) == 2) {
    $table_pages_for_templ = $this->getPagesForTemplate($_GET['shortcuts'], $space_id);
}

$actual_link = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true ? 'https://' : 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
?>
<div class="row" id="wiki-display">
    <div class="col-sm-3 col-md-2 left-side">
        <div id="space-name" class="text-center">
            <img src="<?= e(base_url(returnSpaceImageUrl($space_info['image']))) ?>" alt="space image">
            <h3><?= e($space_info['name']) ?></h3>
        </div>
        <hr>
        <div id="tree-bg">
            <p class="space-shorts"><?= e($this->lang_php['space_shortcuts']) ?></p>
            <ul class="shorts">
                <?php foreach ($shortcuts as $short) { ?>
                    <li class="short <?= e(isset($_GET['shortcuts']) && $_GET['shortcuts'] == $short['id'] ? 'active' : '') ?>"><a href="<?= e(base_url('wiki/' . $project_name . '/display/' . $space_info['key_space'] . '?shortcuts=' . $short['id'])) ?>"><i class="fa fa-file-text-o"></i> <?= e($short['name']) ?></a></li>
                <?php } ?>
            </ul>
            <p class="page-tree"><?= e($this->lang_php['page_tree']) ?></p>
            <div id="wiki-tree">
                <?php

                function loop_tree($pages, $is_recursion = false)
                {
                    ?>
                    <ul class="<?= e($is_recursion === true ? 'children' : 'parent') ?>">
                        <?php
                        foreach ($pages as $page) {
                            $children = false;
                            if (isset($page['children']) && !empty($page['children'])) {
                                $children = true;
                            }
                            ?>
                            <li>
                                <?php if ($children === true) {
                                    ?>
                                    <a href="javascript:void(0);" class="show-childrens"><i class="fa fa-chevron-right"></i></a> 
                                <?php } else { ?>
                                    <i class="fa fa-circle"></i>
                                <?php } ?>
                                <a href="?viewPageId=<?= e($page['id']) ?>" class="<?= e(isset($_GET['viewPageId']) && $_GET['viewPageId'] == $page['id'] ? 'active' : '') ?>" id="p_<?= e($page['id']) ?>"><?= e($page['title']) ?></a>
                                <?php
                                if ($children === true) {
                                    loop_tree($page['children'], true);
                                } else {
                                    ?>
                                </li>
                                <?php
                            }
                        }
                        ?>
                    </ul>
                    <?php
                    if ($is_recursion === true) {
                        ?>
                        </li>
                        <?php
                    }
                }

                loop_tree($pages_for_space);
                ?>
            </div>
        </div>
    </div>
    <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2" id="page-preview">
        <?php
        if (!empty($page_info)) {
            if (strlen($page_info['hash']) == 32) {
                $url_h = base_url('public/hash/' . $page_info['hash']);
                $hash_link = '<a target="_blank" href="' . $url_h . '">' . $url_h . '</a>';
            } else {
                $hash_link = null;
            }
            ?>
            <div class="row">
                <div class="col-sm-6">
                    <ol class="breadcrumb page-view">
                        <li><a href="<?= e(base_url('wiki/' . $project_name)) ?>"><?= e($this->lang_php['wiki']) ?></a></li>
                        <li><a href="<?= e(base_url('wiki/' . $project_name . '/display/' . $space_info['key_space'])) ?>"><?= e($space_info['name']) ?></a></li>
                        <li class="active"><?= e($page_info['title']) ?></li>
                    </ol>
                </div>
                <div class="col-sm-6">
                    <div class="pull-right page-options">
                        <?php if (in_array($GLOBALS['CONFIG']['PERMISSIONS']['WIKI']['EDIT_OTHER_PAGES'], $this->permissions) || $page_info['created_from'] == $this->user_id) { ?>
                            <a class="option" href="<?= e(base_url('wiki/' . $project_name . '/editpage/' . $_GET['viewPageId'])) ?>"><i class="fa fa-pencil"></i> <?= e($this->lang_php['edit']) ?></a>
                        <?php } ?>
                        <a class="option" href="javascript:void(0);" id="watch-status"><i class="fa fa-eye"></i> <span id="watch-stat"><?= e(isset($watchers['ids']) && $watchers['ids'] !== null && in_array($this->user_id, $watchers['ids']) ? $this->lang_php['unwatch'] : $this->lang_php['watch']) ?></span></a>
                        <a class="option" id="share-me" data-toggle="popover" href="javascript:void(0);"><i class="fa fa-share-square-o"></i> <?= e($this->lang_php['share']) ?></a>
                        <div class="dropdown more">
                            <a id="dLabel" data-target="#" href="<?= e(base_url()) ?>" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-ellipsis-h"></i>
                            </a>

                            <ul class="dropdown-menu pull-right" aria-labelledby="dLabel">
                                <?php if (in_array($GLOBALS['CONFIG']['PERMISSIONS']['WIKI']['DELETE_PAGES'], $this->permissions)) { ?>
                                    <li class="bg-danger"><a onclick="return confirm(<?= ejs($this->lang_php['page_del_confirm']) ?>)" href="<?= e(base_url('wiki/' . $project_name . '/display/' . $space_info['key_space'] . '?deletePage=' . $_GET['viewPageId'])) ?>"><i class="fa fa-trash"></i> <?= e($this->lang_php['delete']) ?></a></li>
                                <?php } ?>
                                <?php if (in_array($GLOBALS['CONFIG']['PERMISSIONS']['WIKI']['MOVE_PAGES'], $this->permissions)) { ?>
                                    <li><a href="javascript:void(0)" data-toggle="modal" data-target="#pageMove"><i class="fa fa-clone"></i> <?= e($this->lang_php['move']) ?></a></li>
                                <?php } ?>
                                <?php if ($history_count > 0) { ?>
                                    <li class="<?= e(isset($_GET['history']) ? 'active' : '') ?>"><a href="<?= e(!isset($_GET['history']) ? $actual_link . '&history=' . $maxh_id : '#') ?>"><i class="fa fa-history"></i> <?= e($this->lang_php['history']) ?></a></li>
                                <?php } ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (isset($_GET['history'])) { ?>
                <h3><?= e($this->lang_php['versions']) ?>:</h3>
                <div id="page-versions">
                    <div class="prev">
                        <?php if ($history_nav['prev']) { ?>
                            <a class="nav-left pull-left" href="<?= e(strtok($actual_link, '?') . '?viewPageId=' . $_GET['viewPageId'] . '&history=' . $history_nav['prev']) ?>"><i class="fa fa-chevron-left"></i></a>
                        <?php } ?>
                        <div class="content">
                            <?php if ($history_nav['this_content'] != null) { ?>
                                <div class="num"><b><?= e($history_nav['this_content']['num']) ?></b></div>
                                <a class="who" href="<?= e(base_url('profile/' . $history_nav['this_content']['u_username'])) ?>"><?= e($history_nav['this_content']['u_fullname']) ?></a>
                                <div class="time">on <?= e(date(PAGES_UPDATE_TYPE_DATE . ' H:m:s', $history_nav['this_content']['update_time'])) ?></div>
                            <?php } else { ?>
                                none
                            <?php } ?>
                        </div>
                        <?php if ($history_nav['next']) { ?>
                            <a class="nav-right pull-right" href="<?= e(strtok($actual_link, '?') . '?viewPageId=' . $_GET['viewPageId'] . '&history=' . $history_nav['next']) ?>"><i class="fa fa-chevron-right"></i></a>
                        <?php } ?>
                    </div>
                    <div class="current">
                        <div>
                            <a href="<?= e(strtok($actual_link, '?') . '?viewPageId=' . $_GET['viewPageId']) ?>" class="curr-link">Current</a></div>
                        <div>
                            <a href="<?= e(base_url('profile/' . $page_info['modified_username'])) ?>" class="create-link-usr"><?= e($page_info['fullname']) ?></a></div>
                        <div class="create-on"><?= e(date(PAGES_UPDATE_TYPE_DATE, $page_info['update_time'])) ?></div>
                    </div>
                </div>
            <?php } ?>
            <div class="clearfix"></div>
            <h1><?= e($page_info['title']) ?></h1>
            <span class="create-info"><?= e($this->lang_php['created_by']) ?> <a href="<?= e(base_url('profile/' . $page_info['username'])) ?>"><?= e($page_info['fullname']) ?></a> 
                <?php if ($page_info['modified_username'] !== null) { ?>
                    , <?= e($this->lang_php['last_modified']) ?> <a href="<?= e(base_url('profile/' . $page_info['modified_username'])) ?>"><?= e($page_info['modified_by']) ?></a> <?= e($this->lang_php['on']) ?> <a href="" data-toggle="tooltip" data-placement="bottom" title="Diff Preview"><?= e(date(PAGES_UPDATE_TYPE_DATE, $page_info['update_time'])) ?></a>
                <?php } else { ?>
                    <?= e($this->lang_php['on']) ?> <?= e(date(PAGES_UPDATE_TYPE_DATE, $page_info['created'])) ?>
                <?php } ?>
            </span>
            <div id="page-content-div">
                <?= clean_html(isset($_GET['history']) ? $history_nav['this_content']['content'] : $page_info['content']) ?>
            </div>
            <?php
        } else {
            if (isset($table_pages_for_templ)) {
                if (empty($table_pages_for_templ)) {
                    ?>
                    <div class="alert alert-info"><?= e($this->lang_php['no_pages_with_templ']) ?></div>
                    <?php
                } else {
                    ?>
                    <div class="table-responsive">
                        <table class="table table-condensed template-pages">
                            <thead>
                                <tr>
                                    <th><?= e($this->lang_php['title']) ?></th>
                                    <th><?= e($this->lang_php['creator']) ?></th>
                                    <th class="text-right"><?= e($this->lang_php['modified']) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($table_pages_for_templ as $page_) {
                                    ?>
                                    <tr>
                                        <td class="pg-templ"><a href="<?= e(base_url('wiki/' . $project_name . '/display/' . $space_info['key_space'] . '?viewPageId=' . $page_['page_id'])) ?>"><?= e($page_['title']) ?></a></td>
                                        <td class="pg-templ"><a href="<?= e(base_url('profile/' . $page_['username'])) ?>"><?= e($page_['fullname']) ?></a></td>
                                        <td class="pg-templ text-right"><?= e(date(PAGES_UPDATE_TYPE_DATE . ' H:m:s', $page_['created'])) ?></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    <?php
                }
            } else {
                ?>
                <?= clean_html($space_info['description']) ?>
                <?php
            }
        }
        ?>
    </div>
</div>
<!-- Modal Move Page -->
<div class="modal fade" id="pageMove" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel"><?= e($this->lang_php['move_page']) ?></h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger" id="move-result"></div>
                <label><?= e($this->lang_php['select_space']) ?></label>
                <?php if (!empty($spaces)) { ?>
                    <select name="for_space_move" id="for_space_move" class="selectpicker form-control">
                        <?php
                        foreach ($spaces as $space) {
                            ?>
                            <option value="<?= e($space['id']) ?>"><?= e($space['name']) ?></option>
                        <?php } ?>
                    </select>
                <?php } else { ?>
                    No spaces available! <a href="<?= e(base_url('')) ?>">Create</a> first!
                <?php } ?>
                <hr>
                <div id="parent-page-move">
                    <label><?= e($this->lang_php['parent_page']) ?>:</label>
                    <input type="hidden" name="sub_for_move" value="0">
                    <input type="text" name="suggesions_move" value=""  autocomplete="off" class="form-control">
                    <div class="list-group" id="suggestions-move">

                    </div>
                    <a href="javascript:void(0);" id="remove-parent-move">
                        <span class="glyphicon glyphicon-remove"></span>
                    </a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= e($this->lang_php['close']) ?></button>
                <button type="button" class="btn btn-primary move-page"><?= e($this->lang_php['save']) ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    var wikiDisplay = {
        share_content: <?= js($hash_link != null ? $hash_link : 'Link was broken!') ?>,
        watchers_url: <?= js(base_url('watchers')) ?>,
        user_id: <?= (int) ($this->user_id) ?>,
        p_id: <?= (int) (isset($_GET['viewPageId']) ? $_GET['viewPageId'] : 0) ?>,
        watch_word: <?= js($this->lang_php['watch']) ?>,
        unwatch_word: <?= js($this->lang_php['unwatch']) ?>,
        page_move_url: <?= js(base_url('page_move')) ?>,
        locat_href: <?= js(base_url('wiki/' . $project_name)) ?>
    };
</script>
<script src="<?= e(base_url('assets/js/wikiDisplay.js')) ?>"></script>
<script>
$(document).ready(function () {
<?php if (isset($_GET['viewPageId'])) { ?>
$("#p_" +<?= (int) ($_GET['viewPageId']) ?>).prev('a.show-childrens').empty().append('<i class="fa fa-chevron-down"></i>');
$("#p_" +<?= (int) ($_GET['viewPageId']) ?>).parents('ul').show();
$("#p_" +<?= (int) ($_GET['viewPageId']) ?>).parents('ul').prevAll('a.show-childrens').empty().append('<i class="fa fa-chevron-down"></i>');
$("#p_" +<?= (int) ($_GET['viewPageId']) ?>).next('ul').show();
<?php } ?>
});
</script>