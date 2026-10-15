<?php
/*
 * if asced abbrivation is == on this project abbr its okay
 */
if (!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}

$abbr = ticketAbbrParse(url_segment(3));

if (isset($_GET['delete_comment'])) {
    $result_delete_comment = $this->deleteComment($_GET['delete_comment']);
    if ($result_delete_comment == true) {
        $this->set_alert($this->lang_php['delete_success'] . '!', 'success');
    } else {
        $this->set_alert($this->lang_php['delete_problem'], 'danger');
    }
    redirect('', false);
}

if (isset($_GET['remove_track'])) {
    $result_delete_comment = $this->deleteSavedTrackTime($_GET['remove_track']);
    if ($result_delete_comment == true) {
        $this->set_alert($this->lang_php['track_delete_success'] . '!', 'success');
    } else {
        $this->set_alert($this->lang_php['delete_problem'], 'danger');
    }
    redirect('', false);
}

if (isset($_POST['delete'])) {
    $result = $this->deleteTicket($_POST['delete_id'], $abbr, $this->project_id);
    if ($result == true) {
        $this->set_alert($this->lang_php['ticket_delete'] . '!', 'success');
        $this->setTicketLog($this->user_id, $this->project_id, 'delete ticket ', $abbr['id']);
        redirect(base_url($this->url . '/dashboard'));
    } else {
        $this->set_alert($this->lang_php['ticket_delete_err'], 'danger');
    }
}

if (isset($_POST['save_comment'])) {
    $subject = $_POST['subject'];
    unset($_POST['subject']);
    if ($_POST['sendEmail'] !== null) {
        $isHTML = false;
        if ($_POST['isHTML'] !== null) {
            $isHTML = true;
        }
        $sync_info = $this->getSyncInfo($pro['sync']);
        $send_email = array(
            'hostname' => $sync_info[0]['smtp_hostname'],
            'port' => $sync_info[0]['smtp_port'],
            'ssl' => $sync_info[0]['smtp_ssl'],
            'username' => $sync_info[0]['username'],
            'password' => $sync_info[0]['password'],
            'subject' => $subject,
            'body' => $_POST['comment'],
            'is_html' => $isHTML,
            'fromName' => 'support',
            'to_email' => $_POST['sendEmail']
        );
        $result_send = $this->sendEmail($send_email);
        $_POST['send'] = 1;
    }
    unset($_POST['isHTML']);
    unset($_POST['sendEmail']);
    unset($_POST['attachments']);
    if ($_POST['sendEmail'] === null || ($_POST['sendEmail'] !== null && $result_send === true)) {
        $_POST['time'] = time();
        if ($_POST['timeupdated'] == 1) {
            $_POST['timeupdated'] = time();
            $what = $this->lang_php['update_comment_on'];
        } else {
            $what = $this->lang_php['add_comment_to'];
        }
        if (isset($this->serialized_send_files)) {
            $_POST['message_attachments'] = $this->serialized_send_files;
        }
        $result_set_comment = $this->setComment($_POST);
        if ($result_set_comment == true) {
            $this->set_alert($this->lang_php['posted_success'] . '!', 'success');
            $this->setTicketLog($this->user_id, $this->project_id, $what, $abbr['id'], $_POST['comment']);
        } else {
            $this->set_alert($this->lang_php['posting_problem'] . '!', 'danger');
        }
    } elseif ($_POST['sendEmail'] !== null && $result_send === false) {
        $this->set_alert($this->lang_php['problem_send_email'], 'danger');
    }
    redirect();
}

if (isset($_POST['addtracktime'])) {
    $result_track_time = $this->setTrackTime($_POST);
    if ($result_track_time == true) {
        $this->setTicketLog($this->user_id, $this->project_id, 'add tracktime to ', $abbr['id']);
        $this->set_alert($this->lang_php['tracktime_added'] . '!', 'success');
    } else {
        $this->set_alert($this->lang_php['tracktime_add_err'] . '!', 'danger');
    }
    redirect();
}

$result = $this->ticketView(url_segment(3));
if (url_segment(3) == false || $result == false) {
    $this->set_alert($this->lang_php['ticket_not_found'] . '!', 'danger');
    echo $this->get_alert();
} else {
    $this->title = '[' . url_segment(3) . '] ' . $result['subject'];
    $stat_obj = $this->getTrackedInfo($result['id'], $this->user_id);
    if ($stat_obj !== false) {
        $track_stat = $stat_obj->status;
        if ($track_stat == 'pause') {
            secondsToTime($stat_obj->paused_on, true);
        } elseif ($track_stat == 'start') {
            $worked = $this->getWorkedTime($result['id'], $this->user_id, false, $stat_obj->id);
            secondsToTime($worked, true);
        }
    }

    $watchers = $this->getWatchers($result['id']);

    if (in_array($GLOBALS['CONFIG']['PERMISSIONS']['TICKETS']['ADD_EDIT_ALL_TICKETS'], $this->permissions)) {
        $disabled = false;
    } elseif (in_array($GLOBALS['CONFIG']['PERMISSIONS']['TICKETS']['ADD_EDIT_MINE_TICKETS'], $this->permissions)) {
        if ($result['addedby_id'] != $this->user_id && $result['assignee_id'] != $this->user_id) {
            $disabled = true;
        } else {
            $disabled = false;
        }
    } else {
        $disabled = true;
    }
    $currencies = $this->getCurrencies();
    $issue_links = $this->getIssueLinks($result['id']);
    ?>
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bootstrap-datepicker.min.css')) ?>">
    <script src="<?= e(base_url('assets/js/ckeditor/ckeditor.js')) ?>"></script>
    <h1><?= e($result['subject']) ?></h1>
    <ol class="breadcrumb ticket-view">
        <li><a href="<?= e(base_url($this->url . '/dashboard/')) ?>"><?= e($this->lang_php['tickets']) ?></a></li>
        <li class="active"><?= e(url_segment(3)) ?></li>
    </ol>
    <div id="ticket-view" class="bordered">
        <?php if ($disabled != true) { ?>
            <a href="<?= e(base_url($this->url . '/newissue/' . url_segment(3))) ?>" class="btn btn-default"><i class="fa fa-pencil"></i> <?= e($this->lang_php['edit']) ?></a>
            <a href="javascript:void(0)" class="btn btn-default close-ticket-btn <?= e($result['status_name'] == 'Closed' ? 'disabled' : '') ?>"><i class="fa fa-times"></i> <?= e($this->lang_php['close_ticket']) ?></a>
        <?php } if ($disabled != true) { ?>
            <form method="post" class="pull-right" onsubmit="return confirm(<?= ejs($this->lang_php['delete_confirm']) ?>)">
                <input type="hidden" name="delete_id" value="<?= e($result['id']) ?>">
                <input name="delete" type="submit" class="btn btn-danger" value="<?= e($this->lang_php['delete']) ?>">
            </form>
        <?php } ?>
        <div class="row">
            <div class="col-sm-8">
                <div class="details">
                    <h3><?= e($this->lang_php['details']) ?></h3>
                    <div class="row">
                        <div class="col-sm-6">
                            <ul>
                                <li><span class="detail"><b><?= e($this->lang_php['type']) ?>:</b></span> <span><?= e($result['type_name']) ?></span>
                                </li>
                                <li><span class="detail"><b><?= e($this->lang_php['priority']) ?>:</b></span> <span style="border-left:4px solid <?= e($result['priority_color']) ?>; padding-left:3px;"><?= e($result['priority_name']) ?></span></li>
                                <li><span class="detail"><b><?= e($this->lang_php['status']) ?>:</b></span> 
                                    <?php if ($disabled != true) { ?>
                                        <select id="status">
                                            <?php
                                            $closedid = null;
                                            foreach ($this->getStatuses() as $status) {
                                                if ($closedid == null) {
                                                    strtolower(trim($status['name'])) == 'closed' ? $closedid = $status['id'] : $closedid = null;
                                                }
                                                ?>
                                                <option value="<?= e($status['id']) ?>" <?= e($status['name'] == $result['status_name'] ? 'selected' : '') ?> name="<?= e($status['name']) ?>"><?= e($status['name']) ?></option>
                                            <?php } ?>
                                        </select>
                                        <span class="bg-info changed"><?= e($this->lang_php['changed']) ?>!</span>
                                    <?php } ?>
                                </li>
                                <li><span class="detail"><b><?= e($this->lang_php['reporter']) ?>:</b></span>  
                                    <?php if ($result['message_uid'] > 0) { ?>
                                        <span><?= e($result['message_from_email']) ?> (<?= e($result['message_from_name']) ?>) <i class="fa fa-envelope-o text-danger" aria-hidden="true"></i></span>
                                    <?php } else { ?>
                                        <span><i><?= e($result['addedby']) ?></i></span>
                                    <?php } ?>
                                </li>
                                <?php if ($result['send'] > 0) { ?>
                                    <li>
                                        <span class="detail"><b><?= e($this->lang_php['receiver']) ?>:</b></span>  
                                        <span><?= e($result['message_to_email']) ?> <i class="fa fa-envelope-o text-danger" aria-hidden="true"></i></span>
                                    </li>
                                <?php } ?>
                                <li><span class="detail"><b><?= e($this->lang_php['assignee']) ?>:</b></span> <?php if ($disabled != true) { ?> <span><i class="assigned"><?= e($result['assignee'] == null ? '<a href="javascript:void(0)" onClick="assignToMe()">' . $this->lang_php['assign_to_me'] . '</a>' : $result['assignee']) ?></i></span> <?php } ?></li>
                                <li><span class="detail"><b><?= e($this->lang_php['watchers']) ?>:</b></span> <a href="javascript:void(0);" id="watch-status"><?= e(isset($watchers['ids']) && $watchers['ids'] !== null && in_array($this->user_id, $watchers['ids']) ? $this->lang_php['unwatch'] : $this->lang_php['watch']) ?></a> / <span><i id="watchers-list"><?= e(isset($watchers['names']) && $watchers['names'] !== null ? implode(',', $watchers['names']) : '') ?></i></span></li>
                            </ul>
                        </div>
                        <div class="col-sm-6">
                            <ul>
                                <li><span class="detail"><b><?= e($this->lang_php['date_created']) ?>:</b></span> <span><?= e(date(TICKETS_DATE_TYPE, $result['timecreated'])) ?> г.</span></li>
                                <li><span class="detail"><b><?= e($this->lang_php['due_date']) ?>:</b></span> <span <?= e($result['duedate'] != 0 && $result['duedate'] < time() ? 'class="bg-danger" title="expired period"' : ' title="in time"') ?>><?= e($result['duedate'] == 0 ? $this->lang_php['none'] : date(TICKETS_DUEDATE_TYPE, $result['duedate']) . '  г.') ?></span></li>
                                <li><span class="detail"><b><?= e($this->lang_php['last_updated']) ?>:</b></span> <span><?= e($result['lastupdate'] == 0 ? $this->lang_php['none'] : date(TICKETS_DATE_TYPE, $result['lastupdate']) . ' г.') ?></span></li>
                                <li><span class="detail"><b><?= e($this->lang_php['estimated']) ?>:</b></span> <span><?= e(secondsToTime($result['estimated_seconds'])) ?></span></li>
                                <li><span class="detail"><b><?= e($this->lang_php['price_per_hour']) ?>:</b></span> <span><?= e($result['pph'] . ' ' . $result['pph_c']) ?></span></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <hr>
                <h3><?= e($this->lang_php['description']) ?></h3>
                <div>
                    <?= clean_html($result['description']) ?>
                </div>
                <?php if (!empty($issue_links)) { ?>
                    <hr>
                    <h3><?= e($this->lang_php['issue_links']) ?></h3>
                    <div>
                        <?php
                        foreach ($issue_links as $issue_link) {
                            if ($issue_link['who_is'] == 1) {
                                $type = $issue_link['type'];
                            } else {
                                $type = $GLOBALS['CONFIG']['ISSUE_LINKS'][$issue_link['type']];
                            }
                            ?>
                            <div class="issue-types-view">
                                <b><?= e($this->lang_php[$type]) ?></b> <span class="glyphicon glyphicon-hand-right"></span> <a target="_blank" class="issue-link-link" href="<?= e(base_url('tickets/' . $issue_link['project_name'] . '/view/' . $issue_link['project_abbr'] . '-' . $issue_link['ticket_id'] . '')) ?>"><?= e($issue_link['project_abbr'] . '-' . $issue_link['ticket_id']) ?></a>
                            </div>
                        <?php } ?>
                    </div>
                    <?php
                }
                if ($result['message_uid'] > 0 || $result['send'] > 0) {
                    $files = unserialize($result['message_attachments']);
                    ?>
                    <h3><?= e($this->lang_php['attachments']) ?></h3>
                    <?php
                    foreach ($files as $file) {
                        ?>
                        <div>
                            <i class="fa fa-paperclip" aria-hidden="true"></i> <a href="<?= e(base_url($GLOBALS['CONFIG']['ATTACHMENTS_DIR'] . $file)) ?>" target="_blank"><?= e($file) ?></a>
                        </div>
                        <?php
                    }
                    ?>
                <?php } ?>
                <hr>

            </div>
            <div class="col-sm-4 the-tracker">
                <h3><?= e($this->lang_php['tracker']) ?></h3>
                <div class="form-group">
                    <button type="button" class="btn btn-default <?= e($disabled != true ? '' : 'disabled') ?>" data-toggle="modal"  title="<?= e($this->lang_php['add_worked_time']) ?>"  <?= e($disabled != true ? 'data-target="#modal-add-time"' : '') ?>><span class="glyphicon glyphicon-plus"></span></button>
                </div> 
                <div class="form-group tracker">
                    <button type="button" class="btn btn-default track-event <?= e(isset($track_stat) && $track_stat == 'start' ? 'active' : '') ?> <?= e($disabled != true ? '' : 'disabled') ?>" data-track-event="start" title="<?= e($this->lang_php['start']) ?>"><span class="glyphicon glyphicon-play"></span></button>
                    <button type="button" class="btn btn-default track-event  <?= e(isset($track_stat) && $track_stat == 'pause' ? 'active' : '') ?>  <?= e(!isset($track_stat) ? 'disabled' : '') ?>" data-track-event="pause" title="<?= e($this->lang_php['pause']) ?>"><span class="glyphicon glyphicon-pause"></span></button>
                    <button type="button" class="btn btn-default track-event <?= e(!isset($track_stat) ? 'disabled' : '') ?>" data-track-event="stop" title="<?= e($this->lang_php['stop_and_save']) ?>"><span class="glyphicon glyphicon-stop"></span></button>
                    <span class="timer bordered"><span class="d"><?= e(isset($_POST['estimated_days']) ? $_POST['estimated_days'] : '0') ?></span>d <span class="h"><?= e(isset($_POST['estimated_hours']) ? $_POST['estimated_hours'] : '0') ?></span>h <span class="m"><?= e(isset($_POST['estimated_minutes']) ? $_POST['estimated_minutes'] : '0') ?></span>m<sup class="s"><?= e(isset($_POST['estimated_seconds']) ? $_POST['estimated_seconds'] : '0') ?></sup></span>
                    <button type="button" class="btn btn-default track-event <?= e(!isset($track_stat) ? 'disabled' : '') ?>" title="<?= e($this->lang_php['clear_time']) ?>"  data-track-event="clear"><span class="glyphicon glyphicon-remove"></span></button>
                </div>
                <p class="bg-danger track-msg"></p>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= e($this->lang_php['added_on']) ?></th>
                                <th class="text-center" colspan="2"><?= e($this->lang_php['worked']) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $times_res = $this->getTrackTimes($result['id']);
                            if (isset($times_res['simple_result']) && !empty($times_res['simple_result'])) {
                                ?>
                                <?php
                                foreach ($times_res['simple_result'] as $tracktime) {
                                    ?>
                                    <tr>
                                        <td><?= e(date(TRACKTIME_CREATE_DATE_TYPE, $tracktime['date_tracked'])) ?></td>
                                        <td class="text-center"><?= e(secondsToTime($tracktime['worked_seconds'])) ?></td>
                                        <td><?php if ($disabled != true) { ?><a href="?remove_track=<?= e($tracktime['id']) ?>&amp;<?= e(csrf_param()) ?>" onclick="confirm(<?= ejs($this->lang_php['confirm_del_track']) ?>)"><span class="glyphicon glyphicon-remove"></span></a><?php } ?></td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                <?php
                            } else {
                                ?>
                                <tr>
                                    <td colspan="4"><?= e($this->lang_php['no_saved_workings']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php
                $remaining = $result['estimated_seconds'] - $times_res['sum']['worked_seconds'];
                ?>
                <p><b><?= e($this->lang_php['saved_works']) ?>:</b> <?= e($times_res['sum']['worked_seconds'] != null ? secondsToTime($times_res['sum']['worked_seconds']) : 'none') ?></p>
                <p><b><?= e($this->lang_php['remaining']) ?>:</b> <?= e(secondsToTime($remaining)) ?></p>
                <p><b><?= e($this->lang_php['costs_to_date']) ?>:</b> <?php
                    $pph_all = floor($times_res['sum']['worked_seconds'] / (60 * 60)) * $result['pph'];
                    echo e($pph_all . ' ' . $result['pph_c'])
                    ?> <a href="javascript:void(0)" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#modalConvertor"><?= e($this->lang_php['currency_convert']) ?></a></p>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-6">
                <h3><?= e($this->lang_php['leave_comment']) ?></h3>
                <hr>
                <div class="comment-tabs">
                    <ul class="nav nav-tabs" role="tablist">
                        <li role="presentation" class="active"><a href="#comments-users" aria-controls="comments-users" role="tab" data-toggle="tab"><h4 class="text-capitalize"><?= e($this->lang_php['comments']) ?></h4></a></li>
                        <li role="presentation"><a href="#add-comment" aria-controls="add-comment" role="tab" data-toggle="tab"><h4 class="text-capitalize"><?= e($this->lang_php['add_comment']) ?></h4></a></li>
                    </ul>    

                    <div class="tab-content bordered">
                        <div id="comments-users" class="tab-pane fade in active" role="tabpanel">
                            <?php
                            $comments = $this->getComments($result['id']);
                            if (!empty($comments)) {
                                ?>
                                <ul class="media-list">
                                    <?php
                                    foreach ($comments as $comment) {
                                        ?>
                                        <li class="media">
                                            <a class="pull-left profile-img" href="<?= e(base_url('profile/' . $comment['username'])) ?>">
                                                <img class="media-object img-circle" alt="profile" src="<?= e(base_url(returnImageUrl($comment['user_image']))) ?>">
                                            </a>
                                            <div class="media-body">
                                                <?php
                                                $bg_color = '';
                                                if ($comment['message_uid'] > 0) {
                                                    $bg_color = 'background-color:#fcf8e3;';
                                                } elseif ($comment['send'] == 1) {
                                                    $bg_color = 'background-color:#d9edf7;';
                                                }
                                                if ($comment['user_name'] != null) {
                                                    $user_name = $comment['user_name'];
                                                } elseif ($comment['message_from_name'] != null) {
                                                    $user_name = $comment['message_from_name'];
                                                } else {
                                                    $user_name = $this->lang_php['deleted_user'];
                                                }
                                                ?>
                                                <div class="well well-lg" style="<?= e($bg_color) ?>">
                                                    <div class="clearfix">
                                                        <h4 class="media-heading text-uppercase pull-left"><a href="<?= e(base_url('profile/' . $comment['username'])) ?>"><?= e($user_name) ?></a></h4>
                                                        <?php
                                                        if ($comment['timeupdated'] == 0) {
                                                            $time = $this->lang_php['posted_on'] . ': ' . date(TICKETS_DATE_TYPE, $comment['time']);
                                                            $title_for_time = $this->lang_php['no_post_updated'];
                                                        } else {
                                                            $time = $this->lang_php['updated_on'] . ': ' . date(TICKETS_DATE_TYPE, $comment['timeupdated']);
                                                            $title_for_time = $this->lang_php['posted_on'] . ': ' . date(TICKETS_DATE_TYPE, $comment['time']);
                                                        }
                                                        ?>
                                                        <span class="text-uppercase pull-right" title="<?= e($title_for_time) ?>"><?= e($time) ?></span>
                                                    </div>
                                                    <div class="media-comment"> <?= clean_html($comment['comment']) ?> </div>
                                                    <?php if ($comment['message_from_name'] == null && $comment['message_uid'] == '0' && $comment['send'] == '0') { ?>
                                                        <a class="btn btn-info btn-circle text-uppercase reply" href="#add-comment" role="tab" data-toggle="tab" data-subfor-id="<?= e($comment['id']) ?>">
                                                            <span class="glyphicon glyphicon-share-alt"></span>
                                                            <?= e($this->lang_php['reply']) ?>
                                                        </a>
                                                    <?php } if ($this->user_id == $comment['user_id'] && $comment['send'] == '0') { ?>
                                                        <a class="btn btn-info btn-circle text-uppercase edit" href="javascript:void(0)" title="edit" data-unique-id="<?= e($comment['id']) ?>" data-subfor-id="0">
                                                            <span class="glyphicon glyphicon-pencil"></span>
                                                            <?= e($this->lang_php['edit']) ?>
                                                        </a>
                                                        <?php
                                                    }
                                                    if ($comment['message_from_name'] == null && $comment['message_uid'] == '0' && $comment['send'] == '0') {
                                                        ?>
                                                        <a class="btn btn-warning btn-circle text-uppercase" href="#reply<?= e($comment['id']) ?>" data-toggle="collapse">
                                                            <span class="glyphicon glyphicon-comment"></span>
                                                            <?= e(isset($comment['sub']) ? count($comment['sub']) : '0') ?> <?= e($this->lang_php['comments']) ?>
                                                        </a>
                                                    <?php } if ($this->user_id == $comment['user_id'] && $comment['send'] == '0') { ?>
                                                        <a class="btn btn-danger btn-circle text-uppercase" href="?delete_comment=<?= e($comment['id']) ?>&amp;<?= e(csrf_param()) ?>" title="remove" onClick="return confirm('<?= e($this->lang_php['comment_del_confirm']) ?>')">
                                                            <span class="glyphicon glyphicon-remove"></span>
                                                            <?= e($this->lang_php['delete']) ?>
                                                        </a>
                                                        <?php
                                                    }
                                                    $unser_comment = unserialize($comment['message_attachments']);
                                                    if (!empty($unser_comment)) {
                                                        foreach ($unser_comment as $att_file) {
                                                            ?>
                                                            <div><b><?= e($this->lang_php['attachments']) ?>:</b></div>
                                                            <a href="<?= e(base_url($GLOBALS['CONFIG']['ATTACHMENTS_DIR'] . $att_file)) ?>" target="_blank"><?= e($att_file) ?></a>
                                                            <?php
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <?php
                                            if (isset($comment['sub'])) {
                                                ?>
                                                <div id="reply<?= e($comment['id']) ?>" class="collapse">
                                                    <ul class="media-list">
                                                        <?php
                                                        foreach ($comment['sub'] as $sub) {
                                                            ?>
                                                            <li class="media media-replied">
                                                                <a class="pull-left profile-img" href="#">
                                                                    <img class="media-object img-circle" alt="profile" src="<?= e(base_url(returnImageUrl($sub['user_image']))) ?>">
                                                                </a>
                                                                <div class="media-body">
                                                                    <div class="well well-lg">
                                                                        <div class="clearfix">
                                                                            <h4 class="media-heading text-uppercase pull-left">
                                                                                <span class="glyphicon glyphicon-share-alt"></span>
                                                                                <?= e($sub['user_name']) ?>
                                                                            </h4>
                                                                            <?php
                                                                            if ($sub['timeupdated'] == 0) {
                                                                                $time = $this->lang_php['posted_on'] . ': ' . date(TICKETS_DATE_TYPE, $sub['time']);
                                                                                $title_for_time = $this->lang_php['no_post_updated'];
                                                                            } else {
                                                                                $time = $this->lang_php['updated_on'] . ': ' . date(TICKETS_DATE_TYPE, $sub['timeupdated']);
                                                                                $title_for_time = $this->lang_php['posted_on'] . ': ' . date(TICKETS_DATE_TYPE, $sub['time']);
                                                                            }
                                                                            ?>
                                                                            <span class="media-date text-uppercase pull-right" title="<?= e($title_for_time) ?>"><?= e($time) ?></span>
                                                                        </div>
                                                                        <div class="media-comment"> <?= clean_html($sub['comment']) ?> </div>
                                                                        <a class="btn btn-info btn-circle text-uppercase reply" href="#add-comment" role="tab" data-toggle="tab" data-subfor-id="<?= e($comment['id']) ?>">
                                                                            <span class="glyphicon glyphicon-share-alt"></span>
                                                                            <?= e($this->lang_php['reply']) ?>
                                                                        </a>
                                                                        <?php if ($this->user_id == $sub['user_id']) { ?>
                                                                            <a class="btn btn-info btn-circle text-uppercase edit" href="javascript:void(0)" title="edit" data-unique-id="<?= e($sub['id']) ?>" data-subfor-id="<?= e($comment['id']) ?>">
                                                                                <span class="glyphicon glyphicon-pencil"></span>
                                                                                <?= e($this->lang_php['edit']) ?>
                                                                            </a>
                                                                            <a class="btn btn-danger btn-circle text-uppercase" href="?delete_comment=<?= e($sub['id']) ?>&amp;<?= e(csrf_param()) ?>" title="remove" onClick="return confirm('<?= e($this->lang_php['comment_del_confirm']) ?>')">
                                                                                <span class="glyphicon glyphicon-remove"></span>
                                                                                <?= e($this->lang_php['delete']) ?>
                                                                            </a>
                                                                        <?php } ?>
                                                                    </div>
                                                                </div>
                                                            </li>
                                                            <?php
                                                        }
                                                        ?>
                                                    </ul>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <p class="text-center"><?= e($this->lang_php['no_comments_yet']) ?></p>
                            <?php } ?>
                        </div>
                        <div id="add-comment" class="tab-pane fade" role="tabpanel">
                            <form action="#" method="post" class="form-horizontal" id="commentForm" role="form" enctype="multipart/form-data"> 
                                <div class="form-group">
                                    <label for="email" class="col-sm-2 control-label"><?= e($this->lang_php['comment']) ?></label>
                                    <div class="col-sm-10">
                                        <input type="hidden" name="ticket_id" value="<?= e($result['id']) ?>">
                                        <input type="hidden" name="sub_for" value="0">
                                        <input type="hidden" name="id" value="0">
                                        <input type="hidden" name="subject" value="RE: <?= e($result['subject']) ?>">
                                        <input type="hidden" name="timeupdated" value="0">
                                        <input type="hidden" name="user" value="<?= e($this->user_id) ?>">
                                        <textarea name="comment" id="comment"></textarea>
                                        <?php if ($result['message_uid'] > 0 || $result['send'] > 0) { ?>
                                            <label><?= e($this->lang_php['attachments']) ?>:</label>
                                            <input type="file" name="attachments[]" multiple />
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php if ($result['message_uid'] > 0 || $result['send'] > 0) { ?>
                                    <div class="checkbox">
                                        <div class="col-sm-offset-2 col-sm-10"> 
                                            <label><input name="sendEmail" value="<?= e($result['message_from_email'] != null ? $result['message_from_email'] : $result['message_to_email']) ?>" type="checkbox"> <?= e($this->lang_php['send_email']) ?></label>
                                        </div>
                                    </div>
                                    <div class="checkbox">
                                        <div class="col-sm-offset-2 col-sm-10"> 
                                            <label><input name="isHTML" value="1" type="checkbox"> <?= e($this->lang_php['send_in_html']) ?></label>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="form-group">
                                    <div class="col-sm-offset-2 col-sm-10">                    
                                        <button class="btn btn-success btn-circle text-uppercase" name="save_comment" type="submit" id="submitComment"><span class="glyphicon glyphicon-send"></span> <?= e($this->lang_php['summit_comment']) ?></button>
                                        <a class="btn btn-success btn-circle text-uppercase cancel-edit" href="javascript:void(0)"><?= e($this->lang_php['cancel']) ?></a>
                                    </div>
                                </div>            
                            </form>
                        </div>
                    </div>
                </div>
                <hr class="visible-xs">
            </div>
        </div>
        <!-- Modal add tracktime -->
        <div class="modal fade" id="modal-add-time" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="myModalLabel"><?= e($this->lang_php['add_worked_time']) ?></h4>
                    </div>
                    <form method="post">
                        <div class="modal-body">
                            <div class="form-group">
                                <label><?= e($this->lang_php['add_days']) ?></label>
                                <input type="text" class="form-control" placeholder="<?= e($this->lang_php['better_more']) ?>..." name="worked_days">
                            </div>
                            <div class="form-group">
                                <label><?= e($this->lang_php['add_hours']) ?></label>
                                <input type="text" class="form-control" placeholder="<?= e($this->lang_php['hours']) ?>" name="worked_hours">
                            </div>
                            <div class="form-group">
                                <label><?= e($this->lang_php['add_minutes']) ?></label>
                                <input type="text" class="form-control" placeholder="<?= e($this->lang_php['minutes']) ?>" name="worked_minutes">
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" value="adddate" name="adddate"> <?= e($this->lang_php['add_date']) ?>
                                </label>
                            </div>
                            <div class="form-group adddatefield">
                                <input type="text" class="form-control date-pick" placeholder="dd.mm.yy" value="" name="date_tracked">
                            </div>
                            <input type="hidden" name="user_id" value="<?= e($this->user_id) ?>">
                            <input type="hidden" name="ticket_id" value="<?= e($result['id']) ?>">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal"><?= e($this->lang_php['close']) ?></button>
                            <button type="submit" name="addtracktime" class="btn btn-primary"><?= e($this->lang_php['save_time']) ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Convertor Currency -->
        <div class="modal fade" id="modalConvertor" tabindex="-1" role="dialog" aria-labelledby="modalConvertor">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title"><?= e($this->lang_php['currency_convert']) ?></h4>
                    </div>
                    <div class="modal-body">
                        <label class="control-label" for="select_cur"><?= e($this->lang_php['convert_to']) ?>:</label>
                        <div class="form-group">
                            <select class="selectpicker form-control" data-live-search="true" name="select_cur" id="select_cur">
                                <?php foreach ($currencies as $currency) { ?>
                                    <option <?= e(isset($_POST['pph_c']) && $_POST['pph_c'] == $currency['currency'] ? 'selected' : '') ?> value="<?= e($currency['currency']) ?>"><?= e($currency['country']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="text-center">
                            <img src="<?= e(base_url('assets/imgs/settings-search-spinner.gif')) ?>" alt="loading" class="loading-conv" style="display:none;">
                        </div>
                        <div id="new_currency" class="text-center"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><?= e($this->lang_php['close']) ?></button>
                        <button type="button" onclick="currency_ajax_convert(<?= ejs($pph_all) ?>)" class="btn btn-primary"><?= e($this->lang_php['convert']) ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>
<script>
    var ticketView = {
    dashboard_url: <?= js(base_url('dashboard')) ?>,
    user_id: <?= (int) ($this->user_id) ?>,
    project_id: <?= (int) ($this->project_id) ?>,
    tid: <?= (int) ($result['ticket_id']) ?>,
    closeid: <?= js($closedid) ?>,
    dashboard_location: <?= js(base_url($this->url . '/dashboard')) ?>,
    tracker_url: <?= js(base_url('tracker')) ?>,
    result_id: <?= (int) ($result['id']) ?>,
    track_timer: <?= js(isset($track_stat) ? $track_stat : '') ?>,
    stop_and_save_time: <?= js($this->lang_php['stop_and_save_time']) ?>,
    clear_worked_time: <?= js($this->lang_php['clear_worked_time']) ?>,
    timer_indicator: <?= js($this->project_abbr . '-' . $result['ticket_id']) ?>,
    started_timers_href: <?= js(base_url($this->url . '/view/' . $this->project_abbr . '-' . $result['ticket_id'])) ?>,
    started_on: <?= js(date(TICKETS_DATE_TYPE, time())) ?>,
    project_abbr: <?= js($this->project_abbr . '-' . $result['ticket_id']) ?>,
    watchers_url: <?= js(base_url('watchers')) ?>,
    fullname: <?= js($this->fullname) ?>,
    assigntome_url: <?= js(base_url('assigntome')) ?>,
    unwatch_word: <?= js($this->lang_php['unwatch']) ?>,
    watch_word: <?= js($this->lang_php['watch']) ?>,
    pph_c: <?= js($result['pph_c']) ?>,
    currency_conv_url: <?= js(base_url('currency_convertor')) ?>
    };
</script>
<script src="<?= e(base_url('assets/js/ticketView.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/bootstrap-datepicker.min.js')) ?>"></script>