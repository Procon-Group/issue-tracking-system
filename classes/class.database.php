<?php

if (!defined('APPLICATION_LOADED') || !APPLICATION_LOADED) {
    die('No direct script access.');
}

/**
 * This Class working only with database
 * extended from class main
 *
 * @author Kiril Kirkov
 */
require_once 'class.mysql.php';

class Database extends Mysql {

    public $project_id;
    public $project_name;
    public $project_abbr;
    public $project_sync;
    public $user_id;
    public $user_name;
    public $dash_filter;
    public $fullname;
    public $permissions;
    public $email;
    public $email_notif;

    public function __construct() {
        parent::__construct();
    }

    public function login($usr_or_email, $password) { //Returns user array, false, or 'default_password'
        $usr_or_email = $this->quote(trim((string) $usr_or_email));
        $arr = $this->query("SELECT id, username, password FROM users WHERE (username=$usr_or_email OR email=$usr_or_email) AND for_account=" . (int) ACCOUNT_ID . " LIMIT 1");
        if ($arr === false) {
            throw new Exception("There is a problem with database:" . DATABASE);
        }
        $info = $arr->fetch_assoc();
        if ($info === null || !verify_pass($password, $info['password'])) {
            return false;
        }
        if (is_default_pass($info['password'])) {
            return 'default_password';
        }
        if (pass_needs_rehash($info['password'])) {
            $this->query("UPDATE users SET password = " . $this->quote(hash_pass($password)) . " WHERE id = " . (int) $info['id']);
        }
        unset($info['password']);
        return $info;
    }

    public function setRememberToken($user_id, $token) { //stores only a hash of the cookie token
        $hash = $token === null ? null : hash('sha256', $token);
        $this->query("UPDATE users SET remember_token = " . $this->quote($hash) . " WHERE id = " . (int) $user_id . " AND for_account=" . (int) ACCOUNT_ID);
    }

    public function checkRememberToken($user_id, $token) {
        $result = $this->query("SELECT id, username, remember_token FROM users WHERE id = " . (int) $user_id . " AND for_account=" . (int) ACCOUNT_ID);
        if ($result === false) {
            return false;
        }
        $info = $result->fetch_assoc();
        if ($info === null || empty($info['remember_token']) || !hash_equals($info['remember_token'], hash('sha256', (string) $token))) {
            return false;
        }
        return array('id' => $info['id'], 'username' => $info['username']);
    }

    public function setLanguage($abbr) {
        $abbr = $this->escape($abbr);
        $result = $this->query("UPDATE default_language SET abbr = '$abbr' WHERE for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function setLoginImage($image) {
        $image = $this->escape($image);
        $result_i = $this->query("SELECT id FROM login_image WHERE for_account=" . ACCOUNT_ID);
        if ($result_i->num_rows > 0) {
            $result_u = $this->query("UPDATE login_image SET image = '$image' WHERE for_account=" . ACCOUNT_ID);
            return $result_u;
        } else {
            $result_i = $this->query("INSERT INTO login_image (image, for_account) VALUES ('$image', " . ACCOUNT_ID . ")");
            return $result_i;
        }
    }

    public function getLoginImage() {
        $result = $this->query("SELECT image FROM login_image WHERE for_account=" . ACCOUNT_ID . " LIMIT 1");
        if ($result !== false && $result->num_rows > 0) {
            $arr = $result->fetch_row();
            return $arr[0];
        } else {
            return false;
        }
    }

    public function dropLoginImage() {
        $this->query("DELETE FROM login_image WHERE for_account=" . ACCOUNT_ID);
    }

    public function getProjects($name = null, $more_info = false) {
        $arr = array();
        $where = ' WHERE projects.for_account =' . ACCOUNT_ID;
        if ($name !== null) {
            $where = " WHERE name='" . $this->escape($name) . "' AND projects.for_account=" . ACCOUNT_ID;
        }
        $info_pr = '';
        if ($more_info == true) {
            $info_pr = ",(SELECT COUNT(id) FROM tickets WHERE project = projects.id AND tickets.for_account=" . ACCOUNT_ID . ") as num_tickets,(SELECT COUNT(id) FROM log_tickets WHERE project_id = projects.id AND log_tickets.for_account=" . ACCOUNT_ID . ") as num_logs_tickets, (SELECT COUNT(id) FROM users WHERE FIND_IN_SET(projects.id, users.projects) > 0 AND users.for_account=" . ACCOUNT_ID . ") as num_users";
        }
        $result = $this->query("SELECT name, id, abbr, timestamp, sync $info_pr FROM projects" . $where);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
            $this->project_id = $arr[0]['id'];
            $this->project_abbr = $arr[0]['abbr'];
            $this->project_sync = $arr[0]['sync'];
        }
        return $arr;
    }

    public function getNaviProjects($name) {
        $result = $this->query("SELECT name, IF(" . $this->quote($name) . " = name, 1, 0) as current FROM projects WHERE id IN (" . $this->intList($this->userProjectIds()) . ") AND for_account=" . ACCOUNT_ID);
        $arr = array();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if ($row['current'] == 1) {
                    $arr['current'] = $row;
                } else {
                    $arr['recents'][] = $row;
                }
            }
        }
        return $arr;
    }

    public function getSyncInfo($id = 0) {
        $where = ' WHERE for_account=' . ACCOUNT_ID;
        $arr = array();
        $id = (int) $id;
        if ($id > 0) {
            $where = "WHERE id = $id AND for_account=" . ACCOUNT_ID;
        }
        $result = $this->query("SELECT * FROM sync_connections " . $where);
        if ($result->num_rows > 0) {
            if ($id == 0) {
                while ($row = $result->fetch_assoc()) {
                    $arr[] = $row;
                }
                return $arr;
            } else {
                $res = $result->fetch_assoc();
                return $res;
            }
        } else {
            return false;
        }
    }

    public function checkUserProjectPremission($project_id) {
        return in_array((int) $project_id, $this->userProjectIds(), true) ? 1 : 0;
    }

    public function userProjectIds() { //ids of the projects the logged-in user may see
        $result = $this->query("SELECT projects FROM users WHERE id = " . (int) $this->user_id . " AND for_account=" . ACCOUNT_ID);
        $row = $result !== false ? $result->fetch_row() : null;
        $ids = array();
        if ($row !== null) {
            foreach (explode(',', (string) $row[0]) as $id) {
                if ((int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    public function ticketProjectId($ticket_db_id) {
        $result = $this->query("SELECT project FROM tickets WHERE id = " . (int) $ticket_db_id . " AND for_account=" . ACCOUNT_ID);
        $row = $result !== false ? $result->fetch_row() : null;
        return $row === null ? 0 : (int) $row[0];
    }

    public function spaceProjectId($space_id) {
        $result = $this->query("SELECT project_id FROM wiki_spaces WHERE id = " . (int) $space_id . " AND for_account=" . ACCOUNT_ID);
        $row = $result !== false ? $result->fetch_row() : null;
        return $row === null ? 0 : (int) $row[0];
    }

    public function pageProjectId($page_id) {
        $result = $this->query("SELECT wiki_spaces.project_id FROM wiki_pages INNER JOIN wiki_spaces ON wiki_spaces.id = wiki_pages.for_space WHERE wiki_pages.id = " . (int) $page_id . " AND wiki_pages.for_account=" . ACCOUNT_ID);
        $row = $result !== false ? $result->fetch_row() : null;
        return $row === null ? 0 : (int) $row[0];
    }

    public function getUserLanguage($user_id) {
        $user_id = (int) $user_id;
        $result = $this->query("SELECT lang FROM users WHERE id = $user_id AND for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            $arr = $result->fetch_row();
            return $arr[0];
        } else {
            return false;
        }
    }

    public function getDefaultLanguage() {
        $result = $this->query("SELECT abbr FROM default_language WHERE for_account=" . ACCOUNT_ID . " LIMIT 1");
        if ($result !== false) {
            $arr = $result->fetch_row();
            return $arr[0];
        } else {
            return false;
        }
    }

    public function setProject($arr) {
        $name = $this->escape($arr['name']);
        $abbr = strtoupper($this->escape($arr['abbr']));
        $now = time();
        $sync_id = 0;
        if (strlen($arr['name']) < 3) {
            return 'Too short name! Must have 3 or more symbols!';
        }
        if (strlen($arr['abbr']) != 3) {
            return 'Abbreviation must have 3 symbols!';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            return 'Not allowed symbols in Name! Only number, letters and down slash can be used!';
        }
        if (!preg_match('/^[a-zA-Z]+$/', $abbr)) {
            return 'Not allowed symbols in abbreviation! Only letters can be used!';
        }
        if (isset($_POST['sync_id'])) {
            $sync_id = (int) $_POST['sync_id'];
        }
        if ($arr['update'] == 0) {
            $check = $this->query("SELECT name, abbr FROM projects WHERE (name='$name' OR abbr = '$abbr') AND for_account=" . ACCOUNT_ID);
            if ($check->num_rows > 0) {
                while ($row = $check->fetch_object()) {
                    if ($row->name == $name) {
                        return 'This name is taken!';
                    } elseif ($row->abbr == $abbr) {
                        return 'This abbreviation is taken!';
                    }
                }
            } else {
                $result = $this->query("INSERT INTO projects (name, for_account, abbr, sync, timestamp) VALUES ('$name', " . ACCOUNT_ID . ",'$abbr', '$sync_id', '$now')");
                $last_id = $this->conn->insert_id;
                $this->query("UPDATE users SET projects = concat(projects, ',$last_id') WHERE for_account=" . ACCOUNT_ID);
                return $result;
            }
        } else {
            $id = (int) $arr['update'];
            unset($arr['update']);
            $sync_to = '';
            if (isset($_POST['sync_to'])) {
                $sync_to = ', sync = ' . (int) $_POST['sync_to'];
            }
            $result = $this->query("UPDATE projects SET name='$name', abbr = '$abbr' $sync_to WHERE id = $id AND for_account=" . ACCOUNT_ID);
            return $result;
        }
    }

    public function getTicketTypes() {
        $arr = array();
        $result = $this->query("SELECT * FROM ticket_types");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getStatuses($id = 0) {
        $arr = array();
        $where = '';
        if ($id != 0) {
            $where = ' WHERE id = ' . (int) $id;
        }
        $result = $this->query("SELECT * FROM ticket_statuses$where ORDER BY id");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getPriority() {
        $arr = array();
        $result = $this->query("SELECT ticket_priority.*, priority_colors.color FROM ticket_priority LEFT JOIN priority_colors ON priority_colors.for_id = ticket_priority.id ORDER BY id DESC");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getUserInfo($id) {
        $id = (int) $id;
        $result = $this->query("SELECT * FROM users WHERE id = $id AND for_account=" . ACCOUNT_ID);
        $row = $result->fetch_assoc();
        if (is_array($row)) {
            unset($row['password'], $row['remember_token']);
        }
        return $row;
    }

    public function getTickets($abbr = false, $id = false, $parameters = null) {
        $arr = array();
        $u_id = (int) $this->user_id;
        $allowed_order_by = array('ticket_priority.power', 'timecreated', 'lastupdate', 'duedate');
        $allowed_order_type = array('ASC', 'DESC');
        if ($abbr === false || $id === false) {
            $fast_watched_join = '';
            $fast_watched_where = '';
//Filter Start
            if (isset($parameters['order_by']) && isset($parameters['order_type'])) {
                $fast_order_by = in_array($parameters['order_by'], $allowed_order_by, true) ? $parameters['order_by'] : 'ticket_priority.power';
                $fast_order_type = strtoupper($parameters['order_type']) === 'DESC' ? 'DESC' : 'ASC';
                isset($parameters['assign-checkbox']) ? $fast_assign_to_me = ' AND assignee = ' . $u_id : $fast_assign_to_me = '';
                if (isset($parameters['watch-checkbox'])) {
                    $fast_watched_join = ' INNER JOIN watchers ON tickets.id = watchers.ticket_id';
                    $fast_watched_where = " AND watchers.user_id = $u_id";
                } else {
                    $fast_watched_join = '';
                    $fast_watched_where = '';
                }
                $order_by = ' ORDER BY ' . $fast_order_by . ' ' . $fast_order_type;
                $for_ser = array('by' => $fast_order_by, 'type' => $fast_order_type, 'assign' => isset($parameters['assign-checkbox']) ? (string) $parameters['assign-checkbox'] : '', 'watch' => isset($parameters['watch-checkbox']) ? (string) $parameters['watch-checkbox'] : '');
                $for_ser_r = serialize($for_ser);
                if ($this->dash_filter != $for_ser) {
                    $this->query("UPDATE users SET dash_filter = " . $this->quote($for_ser_r) . " WHERE id = $u_id");
                }
            } elseif ($this->dash_filter !== false) {
                $dash_ops = $this->dash_filter;
                $_GET['order_by'] = $dash_ops['by'];
                $_GET['order_type'] = $dash_ops['type'];
                $_GET['assign-checkbox'] = $dash_ops['assign'];
                $_GET['watch-checkbox'] = $dash_ops['watch'];
                $dash_ops['assign'] != null ? $fast_assign_to_me = ' AND assignee = ' . $u_id : $fast_assign_to_me = '';
                if ($dash_ops['watch'] != null) {
                    $fast_watched_join = ' INNER JOIN watchers ON tickets.id = watchers.ticket_id';
                    $fast_watched_where = " AND watchers.user_id = $u_id";
                } else {
                    $fast_watched_join = '';
                    $fast_watched_where = '';
                }
                $dash_by = in_array($dash_ops['by'], $allowed_order_by, true) ? $dash_ops['by'] : 'ticket_priority.power';
                $dash_type = in_array($dash_ops['type'], $allowed_order_type, true) ? $dash_ops['type'] : 'ASC';
                $order_by = ' ORDER BY ' . $dash_by . ' ' . $dash_type;
            } else {
                $fast_assign_to_me = '';
                $order_by = 'ORDER BY ticket_priority.power ASC, tickets.id DESC';
            }


            $search_in = '';
            if (isset($parameters['search']) && $parameters['search'] != null) {
                $search = $this->escape(addcslashes(urldecode($parameters['search']), '%_'));
                $search_column = isset($parameters['serach_in']) ? $parameters['serach_in'] : null;
                if ($search_column == 'subject' || $search_column == 'description') {
                    $search_in = " AND $search_column LIKE '%$search%'";
                } else {
                    $search_in = " AND (subject LIKE '%$search%' OR description LIKE '%$search%')";
                }
            }

            $between = '';
            if (isset($parameters['from_date']) && $parameters['from_date'] != null) {
                $from_date = (int) strtotime($parameters['from_date']);
                $between = " AND timecreated >= $from_date";
            }
            if (isset($parameters['to_date']) && $parameters['to_date'] != null) {
                $to_date = (int) strtotime($parameters['to_date']);
                $between = $between . " AND timecreated <= $to_date";
            }

            $type_select = '';
            $status_select = '';
            $priority_select = '';
            if (isset($parameters['type_select']) && is_numeric($parameters['type_select'])) {
                $type_select = (int) $parameters['type_select'];
                $type_select = " AND tickets.type = $type_select";
            }
            if (isset($parameters['status_select']) && is_numeric($parameters['status_select'])) {
                $status_select = (int) $parameters['status_select'];
                $status_select = " AND tickets.status = $status_select";
            }
            if (isset($parameters['priority_select']) && is_numeric($parameters['priority_select'])) {
                $priority_select = (int) $parameters['priority_select'];
                $priority_select = " AND tickets.priority = $priority_select";
            }
            $project_name = $this->quote($this->project_name);

            $where = $search_in . $between . $type_select . $status_select . $priority_select;
//Filter END!

            $result_num = $this->query("SELECT count(tickets.status) as num, ticket_statuses.name, SUM(IF(tickets.duedate!=0 AND tickets.duedate<" . time() . ",1,0)) as expired FROM ticket_statuses INNER JOIN tickets ON ticket_statuses.id = tickets.status INNER JOIN projects ON projects.id=tickets.project $fast_watched_join WHERE tickets.for_account=" . ACCOUNT_ID . " AND projects.name=$project_name $where $fast_assign_to_me $fast_watched_where GROUP BY ticket_statuses.name");
            if ($result_num !== false) {
                while ($row = $result_num->fetch_assoc()) {
                    $arr['nums'][$row['name']]['num'] = $row['num'];
                    $arr['nums'][$row['name']]['expired'] = $row['expired'];
                }
            }

            $result = $this->query("SELECT tickets.ticket_id, tickets.id, tickets.subject, tickets.send, tickets.message_uid, tickets.message_from_email, tickets.message_from_name, tickets.status, tickets.assignee as assignee_id, priority_colors.color, tickets.timecreated, users.fullname, users.username, users.image as assignee_image, usersA.id as addedby, ticket_types.name as type_name, tickets.duedate FROM tickets LEFT JOIN ticket_priority ON ticket_priority.id = tickets.priority INNER JOIN projects ON projects.id=tickets.project LEFT JOIN priority_colors ON priority_colors.for_id = ticket_priority.id LEFT JOIN users ON users.id = tickets.assignee LEFT JOIN users as usersA ON usersA.id = tickets.addedby LEFT JOIN ticket_types ON ticket_types.id = tickets.type $fast_watched_join WHERE tickets.for_account=" . ACCOUNT_ID . " AND projects.name=$project_name $where $fast_assign_to_me $fast_watched_where $order_by");
            if ($result !== false) {
                while ($row = $result->fetch_assoc()) {
                    $arr['tickets'][] = $row;
                }
            }
            return $arr;
        } else {
            if (!is_numeric($id))
                return;
            $id = (int) $id;
            $abbr = $this->escape($abbr);
            $result = $this->query("SELECT tickets.id, tickets.timecreated, tickets.send, tickets.message_to_email, tickets.ticket_id, tickets.message_uid, tickets.message_from_email, tickets.message_attachments, tickets.message_from_name, tickets.subject, tickets.description, ticket_types.name as type_name, ticket_statuses.name as status_name, ticket_priority.name as priority_name, tickets.estimated_seconds, tickets.duedate, tickets.lastupdate, tickets.pph, tickets.pph_c, usersA.fullname as assignee, usersA.username, usersA.id as assignee_id, usersB.fullname as addedby, usersB.id as addedby_id, priority_colors.color as priority_color FROM tickets INNER JOIN projects ON tickets.project = projects.id LEFT JOIN ticket_priority ON ticket_priority.id = tickets.priority LEFT JOIN ticket_statuses ON ticket_statuses.id = tickets.status LEFT JOIN ticket_types ON ticket_types.id = tickets.type LEFT JOIN users as usersA ON usersA.id = tickets.assignee LEFT JOIN users as usersB ON usersB.id=tickets.addedby LEFT JOIN priority_colors ON priority_colors.for_id=ticket_priority.id WHERE tickets.for_account=" . ACCOUNT_ID . " AND tickets.ticket_id = $id AND projects.abbr='$abbr'");
            return $result->fetch_assoc();
        }
    }

    public function getTicketForEdit($abbr, $id) {
        $abbr = $this->escape($abbr);
        $id = (int) $id;
        $result = $this->query("SELECT tickets.id, type, subject, description, status, priority, assignee, duedate, estimated_seconds, pph, pph_c FROM tickets INNER JOIN projects ON tickets.project = projects.id WHERE tickets.for_account=" . ACCOUNT_ID . " AND tickets.ticket_id = $id AND projects.abbr='$abbr'");
        if ($result === false) {
            return;
        } else {
            return $result->fetch_assoc();
        }
    }

    public function setTicket($post, $update) {
        unset($post['setticket']);
        $errors = array();
        if (strlen($post['type']) == 0) {
            $errors[] = 'Not selected type!';
        }
        if (mb_strlen($post['subject']) < 1) {
            $errors[] = 'Too short subject!';
        }
        if (strlen($post['status']) == 0) {
            $errors[] = 'Not selected status!';
        }
        if (strlen($post['priority']) == 0) {
            $errors[] = 'Not selected priority!';
        }
        if (mb_strlen($post['subject']) > 200) {
            $post['subject'] = mb_substr($post['subject'], 0, 200);
        }
        $post['estimated_seconds'] = strtotime($post['estimated_days'] . ' day ' . $post['estimated_hours'] . ' hour ' . $post['estimated_minutes'] . ' minute', 0);
        if($post['estimated_seconds'] === false) {
            $post['estimated_seconds'] = 0;
        }
        unset($post['estimated_days']);
        unset($post['estimated_hours']);
        unset($post['estimated_minutes']);
        $watchers = isset($post['watchers']) ? $post['watchers'] : '';
        $watchers = array_filter(array_map('intval', explode(",", (string) $watchers)));
        unset($post['watchers']);
        empty($post['duedate']) ? $post['duedate'] = 0 : $post['duedate'] = strtotime($post['duedate']);
        $post['pph'] = str_replace(",", ".", $post['pph']);
        $post['pph'] = number_format((int) $post['pph'], 2);
        $issue_links = array(
            'ticket_1_id' => '', //it was set code below
            'update' => isset($post['issue_links_updates']) ? (array) $post['issue_links_updates'] : array(),
            'who_is' => isset($post['issue_links_who_is']) ? (array) $post['issue_links_who_is'] : array(),
            'origins' => isset($post['orig_issue_links_up']) ? $post['orig_issue_links_up'] : '',
            'issue_links' => isset($post['issue_links']) ? (array) $post['issue_links'] : array(),
            'issue_links_types' => isset($post['issue_links_types']) ? (array) $post['issue_links_types'] : array()
        );
        unset($post['issue_links'], $post['issue_links_types'], $post['issue_links_updates'], $post['issue_links_who_is'], $post['orig_issue_links_up']);
        //only these columns may come from the ticket form
        $form_columns = array('type', 'subject', 'description', 'status', 'priority', 'assignee', 'duedate', 'pph', 'pph_c', 'estimated_seconds', 'send', 'message_to_email', 'message_id', 'message_attachments');
        if (empty($errors)) {
            if ($update == true) {
                $id = (int) $post['id'];
                $tid = ticketAbbrParse(url_segment(3));
                $data = $this->onlyColumns($post, $form_columns);
                $data['lastupdate'] = time();
                $result = $this->query("UPDATE tickets SET " . $this->setSql($data) . " WHERE for_account=" . ACCOUNT_ID . " AND id = $id LIMIT 1");
                $this->setTicketLog($this->user_id, $this->project_id, 'update', $tid['id'], $post['description']);
                $last_id = $id; //insert watchers
            } else {
                $data = $this->onlyColumns($post, $form_columns);
                $data['for_account'] = ACCOUNT_ID;
                $data['addedby'] = (int) $this->user_id;
                $data['timecreated'] = isset($post['timecreated']) && (int) $post['timecreated'] > 0 ? (int) $post['timecreated'] : time();
                $project_id = $this->getProjects($this->project_name);
                $data['project'] = (int) $project_id[0]['id'];
                $maxid = $this->query("SELECT MAX(ticket_id) as id FROM tickets WHERE for_account=" . ACCOUNT_ID . " AND project = " . $data['project']);
                $data['ticket_id'] = $maxid->fetch_object()->id + 1;
                if ($data['status'] == 4) {
                    $data['timeclosed'] = time();
                } else {
                    $data['timeclosed'] = 0;
                }
                $result = $this->query($this->insertSql('tickets', $data));
                $last_id = $this->conn->insert_id; //insert watchers
                $watchers_for_log = $watchers;
            }

            $last_id = (int) $last_id;
            $issue_links['ticket_1_id'] = $last_id;
            $this->setIssueLinks($issue_links, $update);

            $ready_watchers = $this->getWatchers($last_id);
            if (isset($ready_watchers['ids']) && $ready_watchers['ids'] !== null) {
                $new_watchers = array_diff($watchers, $ready_watchers['ids']); //new watchers
                $deleted_watchers = array_diff($ready_watchers['ids'], $watchers);
            } else {
                $new_watchers = $watchers; //new watchers
            }
            $now = time();
            if (!empty($new_watchers)) {
                $w_insert = null;
                foreach ($new_watchers as $watcher) {
                    $watcher = (int) $watcher;
                    if ($w_insert == null) {
                        $w_insert = "(" . ACCOUNT_ID . ", $watcher, $last_id, $now)";
                    } else {
                        $w_insert .= ", (" . ACCOUNT_ID . ", $watcher, $last_id, $now)";
                    }
                }
                $this->query("INSERT INTO watchers (for_account, user_id, ticket_id, start_time) VALUES $w_insert");
            }
            if (isset($watchers_for_log)) { //new ticket: log once it and its watchers exist
                $this->setTicketLog($this->user_id, $data['project'], 'create', $data['ticket_id'], $post['description']);
            }
            if (!empty($deleted_watchers)) {
                $w_delete = $this->intList($deleted_watchers);
                $this->query("DELETE FROM watchers WHERE user_id IN ($w_delete) AND ticket_id = $last_id AND for_account=" . ACCOUNT_ID);
            }

            return $result;
        } else {
            return $errors;
        }
    }

    public function getComments($id) {
        $arr = array();
        $id = (int) $id;
        $result = $this->query("SELECT comments.*, users.fullname as user_name, users.username, users.id as user_id, users.image as user_image, tickets.message_from_name FROM comments LEFT JOIN users ON users.id = comments.user LEFT JOIN tickets ON tickets.id = comments.ticket_id WHERE comments.ticket_id = $id AND comments.for_account=" . ACCOUNT_ID);
        while ($row = $result->fetch_assoc()) {
            if ($row['sub_for'] == 0) {
                $arr[$row['id']] = $row;
            } else {
                $arr[$row['sub_for']]['sub'][$row['id']] = $row;
            }
        }
        return $arr;
    }

    public function setComment($post) {
        if (mb_strlen(strip_tags($post['comment'])) <= 0)
            return false;
        unset($post['save_comment']);
        if ($post['timeupdated'] != 0) {
            $id = (int) $post['id'];
            $data = $this->onlyColumns($post, array('comment', 'timeupdated', 'send', 'message_attachments'));
            $row = $this->query("SELECT user FROM comments WHERE id = $id AND for_account=" . ACCOUNT_ID);
            $owner = $row !== false ? $row->fetch_object() : null;
            if ($owner !== null && $owner->user == $this->user_id) {
                $result = $this->query("UPDATE comments SET " . $this->setSql($data) . " WHERE id = $id AND for_account=" . ACCOUNT_ID);
            } else {
                return false;
            }
        } else {
            $data = $this->onlyColumns($post, array('comment', 'ticket_id', 'time', 'timeupdated', 'sub_for', 'send', 'message_attachments'));
            $data['ticket_id'] = isset($data['ticket_id']) ? (int) $data['ticket_id'] : 0;
            $data['sub_for'] = isset($data['sub_for']) ? (int) $data['sub_for'] : 0;
            $data['user'] = (int) $this->user_id; //never trust the posted author
            $data['for_account'] = ACCOUNT_ID;
            $data += array('send' => 0, 'message_attachments' => '', 'time' => time(), 'timeupdated' => 0);
            $result = $this->query($this->insertSql('comments', $data));
        }
        return $result;
    }

    public function deleteComment($id) {
        $id = (int) $id;
        $delete_from = (int) $this->user_id;
        $result = $this->query("DELETE FROM comments WHERE (id = $id OR sub_for = $id) AND user = $delete_from AND for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function changeTicketStatus($ticketid, $tostatusid, $project_id) {
        $ticketid = (int) $ticketid;
        $project_id = (int) $project_id;
        $tostatusid = (int) $tostatusid;
        if ($tostatusid == 4) {
            $timeclosed = time();
        } else {
            $timeclosed = 0;
        }
        $result = $this->query("UPDATE tickets SET status='$tostatusid', timeclosed = '$timeclosed' WHERE ticket_id = '$ticketid' AND project = $project_id AND for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function deleteTicket($id, $t_abbr_id, $project_id) {
        $id = (int) $id;
        $project_id = (int) $project_id;
        $result = $this->query("DELETE FROM tickets WHERE id = '$id' AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM comments WHERE ticket_id = '$id' AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM watchers WHERE ticket_id = '$id' AND for_account=" . ACCOUNT_ID);
        $res_id = $this->query("SELECT id FROM started_track_times WHERE ticket_id = '$id' AND for_account=" . ACCOUNT_ID);
        $paused_row = $res_id !== false ? $res_id->fetch_assoc() : null;
        $id_paused = $paused_row !== null ? (int) $paused_row['id'] : 0;
        $this->query("DELETE FROM paused_trackings WHERE for_id = $id_paused AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM started_track_times WHERE ticket_id = '$id' AND for_account=" . ACCOUNT_ID);
        $t_abbr_id = (int) $t_abbr_id['id'];
        $this->query("DELETE FROM saved_tracktimes WHERE project_id = '$project_id' AND ticket_id = '$t_abbr_id' AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM log_tickets WHERE project_id = '$project_id' AND ticket_id = '$t_abbr_id' AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM connected_tickets WHERE (ticket_1 = $id OR ticket_2 = $id) AND for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function setTrackTime($post) {
        if (trim($post['worked_days']) == '' && trim($post['worked_hours']) == '' && trim($post['worked_minutes']) == '') {
            return false;
        }
        if (isset($post['adddate']) && $post['date_tracked'] != "") {
            $post['date_tracked'] = (int) strtotime($post['date_tracked']);
        } else {
            $post['date_tracked'] = time();
        }
        $post['worked_days'] = (int) $post['worked_days'];
        $post['worked_hours'] = (int) $post['worked_hours'];
        $post['worked_minutes'] = (int) $post['worked_minutes'];
        if ($post['worked_days'] == 0 && $post['worked_hours'] == 0 && $post['worked_minutes'] == 0) {
            return false;
        }
        $post['worked_seconds'] = strtotime($post['worked_days'] . ' day ' . $post['worked_hours'] . ' hour ' . $post['worked_minutes'] . ' minute', 0);
        $data = array(
            'worked_seconds' => (int) $post['worked_seconds'],
            'date_tracked' => (int) $post['date_tracked'],
            'ticket_id' => (int) $post['ticket_id'],
            'user_id' => (int) $this->user_id,
            'project_id' => (int) $this->project_id,
            'for_account' => ACCOUNT_ID
        );
        $result = $this->query($this->insertSql('saved_tracktimes', $data));
        return $result;
    }

    public function getTrackTimes($id) {
        $id = (int) $id;
        $p_id = (int) $this->project_id;
        $result_tbl = $this->query("SELECT id, worked_seconds, date_tracked FROM saved_tracktimes WHERE project_id = $p_id AND ticket_id = $id AND for_account=" . ACCOUNT_ID);
        $result_sum = $this->query("SELECT SUM(worked_seconds) as worked_seconds FROM saved_tracktimes WHERE project_id = $p_id AND ticket_id = $id AND for_account=" . ACCOUNT_ID);
        $arr = array();
        while ($row = $result_tbl->fetch_assoc()) {
            $arr['simple_result'][] = $row;
        }
        while ($row = $result_sum->fetch_assoc()) {
            $arr['sum'] = $row;
        }
        return $arr;
    }

    public function setSync($post, $update = 0) {
        $update = (int) $update;
        $post = $this->onlyColumns((array) $post, array('hostname', 'smtp_hostname', 'protocol', 'port', 'smtp_port', '_ssl', 'smtp_ssl', 'self_signed', 'folder', 'username', 'password'));
        if ($update > 0) {
            $this->query("UPDATE sync_connections SET " . $this->setSql($post) . " WHERE id = $update AND for_account=" . ACCOUNT_ID);
        } else {
            $check = $this->query("SELECT id FROM sync_connections WHERE hostname = " . $this->quote(isset($post['hostname']) ? $post['hostname'] : '') . " AND username = " . $this->quote(isset($post['username']) ? $post['username'] : '') . " AND for_account=" . ACCOUNT_ID);
            if ($check->num_rows == 0) {
                $post['for_account'] = ACCOUNT_ID;
                $this->query($this->insertSql('sync_connections', $post));
                return $this->conn->insert_id;
            } else {
                $fetch = $check->fetch_object();
                return $fetch->id;
            }
        }
    }

    public function deleteSync($id) {
        $id = (int) $id;
        $result = $this->query("DELETE FROM sync_connections WHERE id = $id AND for_account=" . ACCOUNT_ID . " LIMIT 1");
        if ($result === true) {
            $this->query("UPDATE projects SET sync = 0 WHERE sync = $id AND for_account=" . ACCOUNT_ID);
        }
    }

    public function deleteSavedTrackTime($id) {
        $id = (int) $id;
        $result = $this->query("DELETE FROM saved_tracktimes WHERE id = $id AND for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function getWorkedTime($ticket_id, $user_id, $in_pause = false, $track_id = 0) {
        $ticket_id = (int) $ticket_id;
        $user_id = (int) $user_id;
        $track_id = (int) $track_id;
        if ($in_pause == true) {
            $and_where = ' AND to_time != 0';
        } else {
            $and_where = '';
        }
        $sum = $this->query("SELECT IF(to_time=0, 0, SUM(to_time)-SUM(from_time)) as removetimes FROM paused_trackings WHERE for_id = $track_id $and_where GROUP BY to_time UNION SELECT started FROM started_track_times WHERE ticket_id = $ticket_id AND user_id = $user_id AND for_account=" . ACCOUNT_ID);
        $worked = time();
        while ($row = $sum->fetch_assoc()) {
            $worked = $worked - $row['removetimes'];
        }
        return $worked;
    }

    public function getTrackedInfo($ticket_id, $user_id) {
        $ticket_id = (int) $ticket_id;
        $user_id = (int) $user_id;
        $check = $this->query("SELECT id, status, paused_on FROM started_track_times WHERE ticket_id = $ticket_id AND user_id = $user_id AND for_account=" . ACCOUNT_ID);
        $fetch = $check->fetch_object();
        if ($check->num_rows > 0) {
            $std = new stdClass();
            $std->id = $fetch->id;
            $std->status = $fetch->status;
            $std->paused_on = $fetch->paused_on;
            $std->num_rows = $check->num_rows;
            return $std;
        } else {
            return false;
        }
    }

    public function startTracking($status, $ticket_id, $user_id, $project_id) {
        if (!in_array($status, array('start', 'pause', 'stop', 'clear'), true)) {
            return false;
        }
        $ticket_id = (int) $ticket_id;
        $user_id = (int) $user_id;
        $project_id = (int) $project_id;
        $now = time();
        $obj = $this->getTrackedInfo($ticket_id, $user_id);
        if (($status == 'stop' || $status == 'pause' || $status == 'clear') && $obj === false) {
            echo 'You do not have started timer!';
            exit;
        }
        if ($obj !== false) {
            $track_id = $obj->id;
        }
        if ($status == 'start' && $obj === false) {
            $result = $this->query("INSERT INTO started_track_times (for_account, user_id, ticket_id, started, status) VALUES (" . ACCOUNT_ID . ", $user_id, $ticket_id, $now, '$status')");
        } else {
            if ($status == 'pause' && $obj->status == 'start') {
                $result = $this->query("INSERT INTO paused_trackings (for_account, for_id, from_time, to_time) VALUES (" . ACCOUNT_ID . ", $track_id, $now, 0)");
                $worked = $this->getWorkedTime($ticket_id, $user_id, true, $track_id);
                $result = $this->query("UPDATE started_track_times SET status = '$status', paused_on = $worked WHERE user_id = $user_id AND ticket_id = $ticket_id AND for_account=" . ACCOUNT_ID);
            }
            if ($status == 'start' && $obj->status == 'pause') {
                $result = $this->query("UPDATE paused_trackings SET to_time = $now WHERE for_id = $track_id AND for_account=" . ACCOUNT_ID . " AND to_time = 0");
                $result = $this->query("UPDATE started_track_times SET status = '$status', paused_on = 0 WHERE user_id = $user_id AND for_account=" . ACCOUNT_ID . " AND ticket_id = $ticket_id");
            }
            if ($status == 'stop') {
                $this->query("UPDATE paused_trackings SET to_time = $now WHERE for_id = $track_id AND for_account=" . ACCOUNT_ID . " AND to_time = 0");
                $worked = $this->getWorkedTime($ticket_id, $user_id, false, $track_id);
                $this->query("DELETE FROM paused_trackings WHERE for_id = $track_id AND for_account=" . ACCOUNT_ID);
                $this->query("DELETE FROM started_track_times WHERE id = $track_id AND for_account=" . ACCOUNT_ID);
                if ($worked < 60) {
                    echo json_encode(array('error' => 'It seem you work less than minute? This work will not be saved!'));
                } else {
                    $this->query("INSERT INTO saved_tracktimes (worked_seconds, user_id, for_account, project_id, ticket_id, date_tracked) VALUES ($worked, $user_id, " . ACCOUNT_ID . ",$project_id, $ticket_id, $now)");
                    echo json_encode(array('success' => true));
                }
            }
            if ($status == 'clear') {
                $this->query("DELETE FROM paused_trackings WHERE for_id = $track_id AND for_account=" . ACCOUNT_ID);
                $this->query("DELETE FROM started_track_times WHERE ticket_id = $ticket_id AND user_id = $user_id AND for_account=" . ACCOUNT_ID);
            }
        }
    }

    public function getStartetTimers() {
        $arr = array();
        $result = $this->query("SELECT tickets.ticket_id, started, started_track_times.status FROM started_track_times INNER JOIN tickets ON tickets.id = started_track_times.ticket_id WHERE user_id = " . (int) $this->user_id . " AND tickets.for_account=" . ACCOUNT_ID);
        while ($row = $result->fetch_assoc()) {
            $arr[] = $row;
        }
        return $arr;
    }

    public function setTicketLog($user_id, $project_id, $event, $ticket_id, $text = '') {
        $time = time();
        $user_id = (int) $user_id;
        $project_id = (int) $project_id;
        $ticket_id = (int) $ticket_id;
        $res = $this->query("INSERT INTO log_tickets (for_account, user_id, project_id, time, event, text, ticket_id) VALUES (" . ACCOUNT_ID . ",$user_id, $project_id, $time, " . $this->quote($event) . ", " . $this->quote($text) . ", $ticket_id)");
        if ($res === true) {
            $log_id = $this->conn->insert_id;

            $ticket_real_id = $this->query("SELECT id FROM tickets WHERE project = $project_id AND ticket_id = $ticket_id AND for_account=" . ACCOUNT_ID);
            $t_id = $ticket_real_id->fetch_row()[0];

            $this->setNotifications($user_id, $t_id, $log_id, 0);
        }
    }

    public function setWikiLog($user_id, $project_id, $event, $page_id, $space_id, $update_id = 0) {
        $time = time();
        $user_id = (int) $user_id;
        $project_id = (int) $project_id;
        $page_id = (int) $page_id;
        $update_id = (int) $update_id;
        $res = $this->query("INSERT INTO log_wiki (for_account, user_id, project_id, time, event, page_update_id, page_id, space_key) VALUES (" . ACCOUNT_ID . ", $user_id, $project_id, $time, " . $this->quote($event) . ", $update_id, $page_id, " . $this->quote($space_id) . ")");
        if ($res === true) {
            $log_id = $this->conn->insert_id;
            $this->setNotifications($user_id, $page_id, $log_id, 1);
        }
    }

    private function getUserEmailForNotif($ids) {
        $arr = array();
        $ids = $this->intList($ids);
        $result = $this->query("SELECT email FROM users WHERE id IN($ids) AND email_notif = 1 AND for_account=" . ACCOUNT_ID);
        while ($row = $result->fetch_assoc()) {
            $arr[] = $row['email'];
        }
        return $arr;
    }

    private function setNotifications($user_id, $id, $log_id, $what) {
        $id = (int) $id;
        $log_id = (int) $log_id;
        $values = '';
        if ($what === 0) {
            $watchers = $this->query("SELECT user_id FROM watchers WHERE ticket_id = $id AND for_account=" . ACCOUNT_ID);
        } else {
            $watchers = $this->query("SELECT user_id FROM watchers WHERE page_id = $id AND for_account=" . ACCOUNT_ID);
        }
        if ($watchers !== false) {
            $usr_ids = array();
            while ($row = $watchers->fetch_array()) {
                $u_id = $row['user_id'];
                if ($u_id != $user_id) {
                    $u_id = $row['user_id'];
                    $usr_ids[] = $u_id;
                    $values .= " (" . ACCOUNT_ID . ", " . (int) $u_id . ", " . ($what === 0 ? "$log_id, 0" : "0, $log_id") . "),";
                }
            }
            if (!empty($usr_ids)) {
                $emails = $this->getUserEmailForNotif($usr_ids);
                if (!empty($emails)) {
                    send_notification_emails(implode(", ", $emails));
                }
            }
            $values = rtrim($values, ",");
            if (!empty($values)) {
                if ($what === 0) {
                    $this->query("INSERT INTO notifications (for_account, user_id, ticket_log_id, wiki_log_id) VALUES $values");
                } else {
                    $this->query("INSERT INTO notifications (for_account, user_id, ticket_log_id, wiki_log_id) VALUES $values");
                }
            }
        }
    }

    public function getTicketNotifications($project_id, $user_id, $from, $to) {
        $project_id = (int) $project_id;
        $user_id = (int) $user_id;
        $from = (int) $from;
        $to = (int) $to;
        $arr = array();
        $ids = array();
        $result = $this->query("SELECT log_tickets.event, notifications.id as notif_id, users.fullname, users.username ,tickets.subject, projects.abbr, tickets.ticket_id, projects.name as proj_name FROM log_tickets INNER JOIN notifications ON notifications.ticket_log_id = log_tickets.id LEFT JOIN users ON users.id = log_tickets.user_id LEFT JOIN tickets ON tickets.ticket_id = log_tickets.ticket_id AND tickets.project = log_tickets.project_id INNER JOIN projects ON projects.id = tickets.project WHERE notifications.user_id = $user_id AND log_tickets.project_id = $project_id AND notifications.previewed = 0 AND notifications.for_account=" . ACCOUNT_ID . " AND tickets.for_account=" . ACCOUNT_ID . " AND projects.for_account=" . ACCOUNT_ID . " LIMIT $from, $to");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr['notifs'][] = $row;
                $ids[] = $row['notif_id'];
            }
        }
        $ids = $this->intList($ids);
        $this->query("UPDATE notifications SET previewed = 1 WHERE id IN ($ids) AND for_account=" . ACCOUNT_ID);
        $arr['num'] = $this->getTicketNumNotifications($project_id, $user_id);
        return $arr;
    }

    public function getWikiNotifications($project_id, $user_id, $from, $to) {
        $project_id = (int) $project_id;
        $user_id = (int) $user_id;
        $from = (int) $from;
        $to = (int) $to;
        $arr = array();
        $ids = array();
        $result = $this->query("SELECT log_wiki.event, notifications.id as notif_id, users.fullname, wiki_pages.title, wiki_pages.id as page_id, projects.name as proj_name, wiki_spaces.key_space, users.username FROM log_wiki INNER JOIN notifications ON notifications.wiki_log_id = log_wiki.id LEFT JOIN users ON users.id = log_wiki.user_id LEFT JOIN wiki_pages ON wiki_pages.id = log_wiki.page_id INNER JOIN wiki_spaces ON wiki_spaces.id = wiki_pages.for_space INNER JOIN projects ON projects.id = wiki_spaces.project_id WHERE notifications.user_id = $user_id AND log_wiki.project_id = $project_id AND notifications.previewed = 0 AND notifications.for_account=" . ACCOUNT_ID . " AND log_wiki.for_account=" . ACCOUNT_ID . " AND projects.for_account=" . ACCOUNT_ID . " AND wiki_spaces.for_account=" . ACCOUNT_ID . " LIMIT $from, $to");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr['notifs'][] = $row;
                $ids[] = $row['notif_id'];
            }
        }
        $ids = $this->intList($ids);
        $this->query("UPDATE notifications SET previewed = 1 WHERE id IN ($ids) AND for_account=" . ACCOUNT_ID);
        $arr['num'] = $this->getWikiNumNotifications($project_id, $user_id);
        return $arr;
    }

    public function getTicketNumNotifications($project_id = null, $user_id = null) {
        if ($project_id !== null && $user_id !== null) {
            $project_id = (int) $project_id;
            $user_id = (int) $user_id;
        } else {
            $project_id = (int) $this->project_id;
            $user_id = (int) $this->user_id;
        }
        $result = $this->query("SELECT COUNT(notifications.id) FROM log_tickets INNER JOIN notifications ON notifications.ticket_log_id = log_tickets.id WHERE notifications.user_id = $user_id AND log_tickets.project_id = $project_id AND notifications.previewed = 0 AND notifications.for_account=" . ACCOUNT_ID . " AND log_tickets.for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            return $result->fetch_row()[0];
        } else {
//Exception
        }
    }

    public function getWikiNumNotifications($project_id = null, $user_id = null) {
        if ($project_id !== null && $user_id !== null) {
            $project_id = (int) $project_id;
            $user_id = (int) $user_id;
        } else {
            $project_id = (int) $this->project_id;
            $user_id = (int) $this->user_id;
        }
        $result = $this->query("SELECT COUNT(notifications.id) FROM log_wiki INNER JOIN notifications ON notifications.wiki_log_id = log_wiki.id WHERE notifications.user_id = $user_id AND log_wiki.project_id = $project_id AND notifications.previewed = 0 AND notifications.for_account=" . ACCOUNT_ID . " AND log_wiki.for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            return $result->fetch_row()[0];
        } else {
//Exception
        }
    }

    public function getTicketsActivityStream($user_id = 0, $project_id = 0, $limit_from = 0, $limit_to = 10) {
        $arr = array();
        $user_id = (int) $user_id;
        $project_id = (int) $project_id;
        $limit_from = (int) $limit_from;
        $limit_to = (int) $limit_to;

        if ($project_id == 0) { //all projects = all projects this user belongs to
            $w_proj = "AND log_tickets.project_id IN (" . $this->intList($this->userProjectIds()) . ")";
        } else {
            $w_proj = "AND log_tickets.project_id = $project_id";
        }

        $result = $this->query("(SELECT log_tickets.time, log_tickets.id as log_id,log_tickets.event, log_tickets.ticket_id, users.image, users.username, users.fullname, log_tickets.text, projects.name as p_name, projects.abbr as p_abbr ,tickets.subject, if(tickets.assignee = $user_id, 1, 0) as to_me FROM log_tickets INNER JOIN projects ON projects.id = log_tickets.project_id INNER JOIN tickets ON tickets.ticket_id = log_tickets.ticket_id AND tickets.project = log_tickets.project_id LEFT JOIN users ON users.id = log_tickets.user_id WHERE tickets.for_account=" . ACCOUNT_ID . " AND tickets.assignee = $user_id $w_proj ORDER BY log_tickets.time DESC LIMIT $limit_from, $limit_to)
UNION 
(SELECT log_tickets.time, log_tickets.id as log_id, log_tickets.event, log_tickets.ticket_id, users.image, users.username, users.fullname, log_tickets.text, projects.name as p_name, projects.abbr as p_abbr ,tickets.subject, if(tickets.assignee = $user_id, 1, 0) as to_me FROM log_tickets INNER JOIN projects ON projects.id = log_tickets.project_id INNER JOIN tickets ON tickets.ticket_id = log_tickets.ticket_id AND tickets.project = log_tickets.project_id LEFT JOIN users ON users.id = log_tickets.user_id WHERE tickets.for_account=" . ACCOUNT_ID . " AND tickets.assignee != $user_id $w_proj ORDER BY log_tickets.time DESC LIMIT $limit_from, $limit_to)");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                if ($user_id == 0) {
                    $arr[] = $row;
                } else {
                    if ($row['to_me'] == 1) {
                        $arr['mine'][] = $row;
                    } else {
                        $arr['other'][] = $row;
                    }
                }
            }
        }
        return $arr;
    }

    public function getWikiActivityStream($project_id, $limit_from = 0, $limit_to = 10) {
        $project_id = (int) $project_id;
        $limit_from = (int) $limit_from;
        $limit_to = (int) $limit_to;

        if ($project_id == 0) { //all projects = all projects this user belongs to
            $w_proj = "WHERE log_wiki.project_id IN (" . $this->intList($this->userProjectIds()) . ") AND log_wiki.for_account=" . ACCOUNT_ID;
        } else {
            $w_proj = "WHERE log_wiki.project_id = $project_id AND log_wiki.for_account=" . ACCOUNT_ID;
        }

        $arr = array();
        $result = $this->query("SELECT log_wiki.time, log_wiki.space_key, log_wiki.id as log_id,log_wiki.event, log_wiki.page_id, users.image, users.username, users.fullname, projects.name as p_name, wiki_pages.title as page_title, wiki_pages.id as wiki_p_id, wiki_pages_updates.content, wiki_pages_updates.id as update_id FROM log_wiki INNER JOIN projects ON projects.id = log_wiki.project_id LEFT JOIN users ON users.id = log_wiki.user_id INNER JOIN wiki_pages ON wiki_pages.id = log_wiki.page_id LEFT JOIN wiki_pages_updates ON wiki_pages_updates.id = log_wiki.page_update_id $w_proj ORDER BY log_wiki.time DESC LIMIT $limit_from, $limit_to");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getSettingsActivityLog($limit_from = 0, $limit_to = 10) {
        $arr = array();
        $limit_from = (int) $limit_from;
        $limit_to = (int) $limit_to;
        $result = $this->query("(SELECT log_wiki.time, users.image, users.fullname, users.username, log_wiki.event, '0' as is_ticket, projects.name as p_name, '' as p_abbr, '' as ticket_id, '' as subject, '' as text, log_wiki.space_key, wiki_pages.title as page_title, log_wiki.page_id, wiki_pages.id as wiki_p_id, wiki_pages_updates.content, wiki_pages_updates.id as update_id FROM log_wiki INNER JOIN projects ON projects.id = log_wiki.project_id LEFT JOIN users ON users.id = log_wiki.user_id INNER JOIN wiki_pages ON wiki_pages.id = log_wiki.page_id LEFT JOIN wiki_pages_updates ON wiki_pages_updates.id = log_wiki.page_update_id WHERE log_wiki.for_account=" . ACCOUNT_ID . " ORDER BY log_wiki.time DESC LIMIT $limit_from, $limit_to) 
UNION 
(SELECT log_tickets.time, users.image, users.fullname, users.username, log_tickets.event, '1' as is_ticket, projects.name as p_name, projects.abbr as p_abbr, tickets.ticket_id, tickets.subject, log_tickets.text, '', '', '', '', '', '' FROM log_tickets INNER JOIN projects ON projects.id = log_tickets.project_id INNER JOIN tickets ON tickets.ticket_id = log_tickets.ticket_id AND tickets.project = log_tickets.project_id LEFT JOIN users ON users.id = log_tickets.user_id WHERE log_tickets.for_account=" . ACCOUNT_ID . " ORDER BY log_tickets.time DESC LIMIT $limit_from, $limit_to) ORDER BY time DESC");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getWatchers($t_id, $what = 0) {
        $arr = array();
        $t_id = (int) $t_id;
        if ($what === 0) {
            $where = "watchers.ticket_id = $t_id AND watchers.for_account=" . ACCOUNT_ID;
        } else {
            $where = "watchers.page_id = $t_id AND watchers.for_account=" . ACCOUNT_ID;
        }
        $result = $this->query("SELECT users.id, users.fullname FROM watchers INNER JOIN users ON user_id = users.id WHERE $where");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr['ids'][] = $row['id'];
                $arr['names'][] = $row['fullname'];
            }
        }
        return $arr;
    }

    public function changeWatchers($post) {
        $u_id = (int) $post['u_id'];
        $now = time();
        if (isset($post['t_id'])) {
            $t_id = (int) $post['t_id'];
            $q1 = "DELETE FROM watchers WHERE user_id = $u_id AND ticket_id = $t_id AND for_account=" . ACCOUNT_ID;
            $q2 = "INSERT INTO watchers (for_account, user_id, ticket_id, start_time) VALUES (" . ACCOUNT_ID . " ,$u_id, $t_id, $now)";
        } elseif (isset($post['p_id'])) {
            $p_id = (int) $post['p_id'];
            $q1 = "DELETE FROM watchers WHERE user_id = $u_id AND page_id = $p_id AND for_account=" . ACCOUNT_ID;
            $q2 = "INSERT INTO watchers (for_account, user_id, page_id, start_time) VALUES (" . ACCOUNT_ID . " ,$u_id, $p_id, $now)";
        }
        if ($post['status'] == 'delete') {
            $result = $this->query($q1);
            return $result;
        } elseif ($post['status'] == 'add') {
            $result = $this->query($q2);
            return $result;
        } else {
            return false;
        }
    }

    public function assignToMe($t_id, $to_user) {
        $t_id = (int) $t_id;
        $to_user = (int) $to_user;
        $result = $this->query("UPDATE tickets SET assignee = $to_user WHERE id = $t_id AND for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function setLastLogin($id) {
        $now = time();
        $id = (int) $id;
        $this->query("UPDATE users SET last_login = $now WHERE id = $id AND for_account=" . ACCOUNT_ID);
    }

    public function setLastActive($id) {
        $now = time();
        $id = (int) $id;
        $this->query("UPDATE users SET last_active = $now WHERE id = $id AND for_account=" . ACCOUNT_ID);
    }

    public function setProfession($name) {
        $name = $this->escape($name);
        $result = $this->query("SELECT id FROM professions WHERE name = '$name' AND for_account=" . ACCOUNT_ID);
        if(!$result) {
            return null;
        }
        $result = $result->fetch_row();
        if ($result[0] == null) {
            $this->query("INSERT INTO professions (name, for_account) VALUES ('$name', " . ACCOUNT_ID . ")");
            return $this->conn->insert_id;
        } else {
            return $result[0];
        }
    }

    private function idList($values) { //comma separated ids, empty string when none
        $ints = array();
        foreach ((array) $values as $value) {
            foreach (explode(',', (string) $value) as $v) {
                if (trim($v) !== '' && (int) $v > 0) {
                    $ints[] = (int) $v;
                }
            }
        }
        sort($ints);
        return implode(',', array_unique($ints));
    }

    public function setUser($post) { //admin "Profiles" form (settings page only)
        $social = serialize(array('facebook' => $post['facebook'], 'twitter' => $post['twitter'], 'linkedin' => $post['linkedin'], 'skype' => $post['skype']));
        $data = $this->onlyColumns($post, array('username', 'password', 'fullname', 'email', 'image', 'prof', 'lang'));
        $data['social'] = $social;
        if (!isset($data['lang']) || $data['lang'] === '') {
            $data['lang'] = NULL;
        }
        if (isset($post['new-prof']) && $post['new-prof'] == 1) {
            $data['prof'] = $this->setProfession($post['new-prof-name']);
        }
        $data['prof'] = isset($data['prof']) ? (int) $data['prof'] : 0;
        $data['projects'] = isset($post['can_see_proj']) ? $this->idList($post['can_see_proj']) : '';
        if (isset($post['costom-privileges']) && $post['costom-privileges'] == 1) {
            $data['privileges'] = isset($post['custom-priv']) ? $this->idList($post['custom-priv']) : '';
        } else {
            $data['privileges'] = isset($post['default-priv']) ? $this->idList($post['default-priv']) : '';
        }
        $update_ip = isset($post['update']) ? (int) $post['update'] : 0;

        if ($update_ip <= 0) {
            $data['password'] = hash_pass(isset($data['password']) ? $data['password'] : '');
            $data['registered'] = time();
            $data['for_account'] = ACCOUNT_ID;
            $result = $this->query($this->insertSql('users', $data));
            return $result;
        } else {
            unset($data['username']);
            if (!isset($data['password']) || $data['password'] == null) {
                unset($data['password']);
            } else {
                $data['password'] = hash_pass($data['password']);
            }
            $result = $this->query("UPDATE users SET " . $this->setSql($data) . " WHERE id = $update_ip AND for_account=" . ACCOUNT_ID);
            return $result;
        }
    }

    public function updateUser($post) { //user's own profile form - can never change privileges, projects or another user
        $data = $this->onlyColumns($post, array('password', 'fullname', 'email', 'image', 'lang'));
        $data['email_notif'] = isset($post['email_notif']) ? 1 : 0;
        if (!isset($data['lang']) || $data['lang'] === '') {
            $data['lang'] = NULL;
        }
        $id = (int) $this->user_id;
        if (!isset($data['password']) || strlen(trim($data['password'])) == 0) {
            unset($data['password']);
        } else {
            $data['password'] = hash_pass($data['password']);
        }
        if (isset($data['fullname']) && strlen(trim($data['fullname'])) == 0) {
            unset($data['fullname']);
        }
        $data['social'] = serialize(array('facebook' => $post['facebook'], 'twitter' => $post['twitter'], 'linkedin' => $post['linkedin'], 'skype' => $post['skype']));

        $result = $this->query("UPDATE users SET " . $this->setSql($data) . " WHERE id = $id AND for_account=" . ACCOUNT_ID);
        return $result;
    }

    public function deleteUser($id) {
        $id = (int) $id;
        if ($id > 0) {
            $arr_p = array();
            $paused_tracks = $this->query("SELECT id FROM started_track_times WHERE user_id = $id AND for_account=" . ACCOUNT_ID);
            if ($paused_tracks !== false) {
                while ($row = $paused_tracks->fetch_assoc()) {
                    $arr_p[] = $row['id'];
                }
                $deleted_tracktimes = $this->intList($arr_p);
                $this->query("DELETE FROM paused_trackings WHERE for_id IN ($deleted_tracktimes) AND for_account=" . ACCOUNT_ID);
            }
            $this->query("DELETE FROM watchers WHERE user_id = $id AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM saved_tracktimes WHERE user_id = $id AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM started_track_times WHERE user_id = $id AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM notifications WHERE user_id = $id AND for_account=" . ACCOUNT_ID);
            $em = $this->query("SELECT email FROM users WHERE id = $id AND for_account=" . ACCOUNT_ID);
            $em_arr = $em->fetch_assoc();
            $email = $this->quote($em_arr['email']);
            $this->query("DELETE FROM pass_resets WHERE email = $email AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM users WHERE id = $id AND for_account=" . ACCOUNT_ID);
        }
    }

    public function deleteProject($id) {
        $id = (int) $id;
        if ($id > 0) {
            $all_tickets = $this->query("SELECT id FROM tickets WHERE project = $id AND for_account=" . ACCOUNT_ID);

            $arr_t = array();
            while ($row = $all_tickets->fetch_assoc()) {
                $arr_t[] = $row['id'];
            }
            $deleted_tickets = $this->intList($arr_t);

            $arr_p = array();
            $paused_tracks = $this->query("SELECT id FROM started_track_times WHERE ticket_id IN ($deleted_tickets) AND for_account=" . ACCOUNT_ID);
            if ($paused_tracks !== false) {
                while ($row = $paused_tracks->fetch_assoc()) {
                    $arr_p[] = $row['id'];
                }
                $deleted_tracktimes = $this->intList($arr_p);
                $this->query("DELETE FROM paused_trackings WHERE for_id IN ($deleted_tracktimes) AND for_account=" . ACCOUNT_ID);
            }

            $arr_n = array();
            $all_logs = $this->query("SELECT id FROM log_tickets WHERE project_id = $id AND for_account=" . ACCOUNT_ID);
            while ($row = $all_logs->fetch_assoc()) {
                $arr_n[] = $row['id'];
            }
            $deleted_logs = $this->intList($arr_n);

            $this->query("DELETE FROM watchers WHERE ticket_id IN ($deleted_tickets) AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM tickets WHERE project = $id AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM saved_tracktimes WHERE project_id = $id AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM started_track_times WHERE ticket_id IN ($deleted_tickets) AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM comments WHERE ticket_id IN ($deleted_tickets) AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM log_tickets WHERE project_id = $id AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM notifications WHERE ticket_log_id IN ($deleted_logs) AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM projects WHERE id = $id AND for_account=" . ACCOUNT_ID);
        }
    }

    public function getCountProfiles($search = null) {
        $where = 'WHERE for_account=' . ACCOUNT_ID;
        if ($search !== null) {
            $search = $this->escape($search);
            $where = "WHERE (fullname LIKE '%$search%' OR username LIKE '%$search%') AND for_account=" . ACCOUNT_ID;
        }
        $result = $this->query("SELECT COUNT(*) as num FROM users $where");
        return $result->fetch_assoc()['num'];
    }

    public function getUsers($start = 0, $limit = 0, $search = null, $id_or_name = 0) {
        $arr = array();
        if ($start == 0 && $limit == 0) {
            $limit = '';
        } else {
            $limit = "LIMIT " . (int) $start . ", " . (int) $limit;
        }

        $where = 'WHERE users.for_account=' . ACCOUNT_ID;
        if ($search !== null) {
            $search = $this->escape(urldecode($search));
            $where = "WHERE (fullname LIKE '%$search%' OR username LIKE '%$search%') AND users.for_account=" . ACCOUNT_ID;
        }

        if (is_int($id_or_name) && $id_or_name != 0) {
            $where = "WHERE users.id = $id_or_name AND users.for_account=" . ACCOUNT_ID;
        } elseif (is_string($id_or_name)) {
            $id_or_name = $this->escape($id_or_name);
            $where = "WHERE users.username = '$id_or_name' AND users.for_account=" . ACCOUNT_ID;
        }

        if (isset($_GET['for_proj'])) {
            $proj_id = (int) $_GET['for_proj'];
            if ($where == '') {
                $where = "WHERE FIND_IN_SET($proj_id, users.projects) > 0 AND users.for_account=" . ACCOUNT_ID;
            } else {
                $where .= " AND FIND_IN_SET($proj_id, users.projects) > 0";
            }
        }

        $result = $this->query("SELECT users.*, professions.name as profession_name FROM users LEFT JOIN professions ON users.prof = professions.id $where $limit");

        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                unset($row['password'], $row['remember_token']); //never hand hashes to pages or ajax
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getProfessions() {
        $arr = array();
        $result = $this->query("SELECT * FROM professions WHERE for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function emailChange($email) {
        $email = $this->escape($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result = $this->query("SELECT COUNT(id) as num FROM users WHERE email = '$email' AND for_account=" . ACCOUNT_ID);
            $arr = $result->fetch_assoc();

            $result = $this->query("SELECT COUNT(id) as num, reset_code FROM pass_resets WHERE email = '$email' AND for_account=" . ACCOUNT_ID);
            $arr_em = $result->fetch_assoc();

            if ($arr['num'] == 1) {
                if ($arr_em['num'] == 1) {
                    return $arr_em['reset_code'];
                } else {
                    $code = random_string(ALLOWED_CHARS_GEN, 52);
                    $this->query("INSERT INTO pass_resets (for_account, reset_code, email) VALUES (" . ACCOUNT_ID . " ,'$code', '$email')");
                    return $code;
                }
            } else {
                return false;
            }
        }
    }

    public function resetPassFromCode($code) {
        $code = $this->escape($code);
        $result = $this->query("SELECT COUNT(id) as num, email, for_account FROM pass_resets WHERE reset_code = '$code'");
        $arr = $result->fetch_assoc();
        if ($arr['num'] == 1) {
            $email = $this->quote($arr['email']);
            $for_account = (int) $arr['for_account'];
            $this->query("DELETE FROM pass_resets WHERE reset_code = '$code'");

            $new_pass = random_string(ALLOWED_CHARS_PASS_GEN, 12);
            $new_hash = $this->quote(hash_pass($new_pass));
            $update = $this->query("UPDATE users SET password = $new_hash, remember_token = NULL WHERE email = $email AND for_account=" . $for_account);
            return $new_pass;
        } else {
            return false;
        }
    }

    public function getCountSpaces($search = null) {
        $where = 'WHERE for_account=' . ACCOUNT_ID;
        if ($search != null) {
            $search = (int) $search;
            $where = "WHERE project_id = $search AND for_account=" . ACCOUNT_ID;
        }
        $result = $this->query("SELECT COUNT(*) as num FROM wiki_spaces $where");
        return $result->fetch_assoc()['num'];
    }

    public function getSpaces($finder = null, $project_id = null, $start = 0, $limit = 0, $search = null) {
        if ($start == 0 && $limit == 0) {
            $limit = '';
        } else {
            $limit = "LIMIT " . (int) $start . ", " . (int) $limit;
        }
        if ($project_id !== null) {
            $project_id = (int) $project_id;
        }

        $where = 'WHERE wiki_spaces.for_account=' . ACCOUNT_ID;
        if ($project_id == null) {
            $for_proj = "";
        } else {
            $for_proj = "AND projects.id = $project_id";
        }
        if ($finder !== null) {
            if (is_numeric($finder)) {
                $finder = (int) $finder;
                $where = " WHERE wiki_spaces.for_account=" . ACCOUNT_ID . " AND wiki_spaces.id = $finder $for_proj";
            } elseif (is_string($finder)) {
                $finder = urldecode($finder);
                $finder = $this->escape($finder);
                $where = " WHERE wiki_spaces.for_account=" . ACCOUNT_ID . " AND key_space = '$finder' $for_proj";
            }
        } else {
            if ($project_id != null) {
                $where = "WHERE projects.id = $project_id AND wiki_spaces.for_account=" . ACCOUNT_ID;
            }
        }

        if ($search != null) {
            $search = (int) $search;
            $where .= " AND project_id = $search";
        }

        $arr = array();
        $result = $this->query("SELECT wiki_spaces.id, wiki_spaces.timestamp, image, wiki_spaces.name, key_space, (SELECT COUNT(id) FROM wiki_pages WHERE wiki_pages.for_space = wiki_spaces.id) as num_pages, projects.name as proj_name, description FROM wiki_spaces INNER JOIN projects ON projects.id = wiki_spaces.project_id $where $limit");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function spaceKeyCheck($key) {
        $key = $this->escape($key);
        $result = $this->query("SELECT id FROM wiki_spaces WHERE key_space = '$key' AND for_account=" . ACCOUNT_ID);
        return $result->num_rows;
    }

    public function setSpace($post) {
        unset($post['setspace']);
        $now = time();
        $name = $this->escape($post['name']);
        $key_space = $this->escape($post['key_space']);
        $description = $this->escape($post['description']);
        $image = $this->escape($post['image']);
        $project_id = (int) $post['project_id'];
        if ($post['update'] == 0) {
            $result = $this->query("INSERT INTO wiki_spaces (for_account, name, key_space, project_id, description, image, timestamp) VALUES (" . ACCOUNT_ID . ", '$name', '$key_space', $project_id, '$description', '$image', '$now')");
        } else {
            $id = (int) $post['update'];
            $result = $this->query("UPDATE wiki_spaces SET name = '$name', project_id = $project_id, description = '$description', image = '$image' WHERE id = $id AND for_account=" . ACCOUNT_ID);
        }
        return $result;
    }

    public function getPageTemplates($spec = 0) {
        $where = 'WHERE for_account=' . ACCOUNT_ID;
        $field = '';
        if ($spec != 0) {
            $where = 'WHERE default_r = 0 AND for_account=' . ACCOUNT_ID;
            $field = ', content';
        }
        $arr = array();
        $result = $this->query("SELECT id, name $field FROM wiki_page_templates $where");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getTemplateInfo($id) {
        $id = (int) $id;
        $result = $this->query("SELECT * FROM wiki_page_templates WHERE id = $id AND for_account=" . ACCOUNT_ID);
        return $result->fetch_assoc();
    }

    public function setPageTemplate($post) {
        $name = $this->escape($post['name']);
        $content = $this->escape($post['description']);

        if ($post['update'] == 0) {
            $result = $this->query("INSERT INTO wiki_page_templates (for_account, name, content) VALUES (" . ACCOUNT_ID . " ,'$name', '$content')");
        } else {
            $id = (int) $post['update'];
            $result = $this->query("UPDATE wiki_page_templates SET name = '$name', content = '$content' WHERE id = $id AND for_account=" . ACCOUNT_ID);
        }
        return $result;
    }

    public function deteleTemplate($id) {
        $id = (int) $id;
        $this->query("DELETE FROM wiki_page_templates WHERE id = $id AND for_account=" . ACCOUNT_ID . " LIMIT 1");
    }

    public function getSuggestions($s_term, $project_id, $space = 0) {//used for parent suggestios and wiki search
        $and = '';
        $space = (int) $space;
        if ($space > 0) {
            $and = " AND wiki_spaces.id = $space";
        }
        $s_term = $this->escape($s_term);
        $project_id = (int) $project_id;
        $arr = array();
        $result = $this->query("SELECT wiki_pages.id, wiki_pages.title, wiki_spaces.key_space FROM wiki_pages INNER JOIN wiki_spaces ON wiki_spaces.id = wiki_pages.for_space INNER JOIN projects ON projects.id = wiki_spaces.project_id WHERE wiki_pages.for_account=" . ACCOUNT_ID . " AND title LIKE '%$s_term%' AND projects.id = $project_id $and LIMIT 5");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getSettingsSearchResults($s_term) {
        $s_term = $this->escape($s_term);
        $arr = array();
        $result = $this->query("
            (SELECT title, 1 as what, 0 as ticket_id, 0 as abbr, projects.name, wiki_spaces.key_space, wiki_pages.id as page_id FROM wiki_pages INNER JOIN wiki_spaces ON wiki_spaces.id = wiki_pages.for_space INNER JOIN projects ON projects.id = wiki_spaces.project_id WHERE wiki_pages.for_account=" . ACCOUNT_ID . " AND wiki_pages.title LIKE '%$s_term%')
                UNION ALL 
            (SELECT subject, 0 as what, tickets.ticket_id, projects.abbr, projects.name,0 ,0 FROM tickets INNER JOIN projects ON projects.id = tickets.project WHERE tickets.for_account=" . ACCOUNT_ID . " AND subject LIKE '%$s_term%') 
                ORDER BY title LIMIT 5");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getWikiPageTemplate($id = 0) {
        $arr = array();
        $id = (int) $id;
        $result = $this->query("SELECT content FROM wiki_page_templates WHERE id  = $id AND for_account=" . ACCOUNT_ID);
        $arr = $result->fetch_assoc();
        return $arr;
    }

    public function getSpaceShortcuts() {
        $arr = array();
        $result = $this->query("SELECT id, name FROM wiki_page_templates WHERE for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getPagesForTemplate($id, $spaceid) {
        $id = (int) $id;
        $spaceid = (int) $spaceid;
        $arr = array();
        $result = $this->query("SELECT wiki_pages.id as page_id, wiki_pages.title, wiki_pages.created, users.fullname, users.username as username FROM wiki_pages LEFT JOIN users ON users.id = wiki_pages.created_from WHERE wiki_pages.category = $id AND for_space = $spaceid AND wiki_pages.for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function addWikiPage($post) {
        $post['category'] = $post['page_template'];
        unset($post['firstedit'], $post['page_template'], $post['suggesions']);
        $title = $this->escape($post['title']);
        $for_space = (int) $post['for_space'];
        $sub_for = (int) $post['sub_for'];
        $created = time();
        $hash = random_token(16); //unguessable link for public preview
        if (isset($post['category']))
            $category = (int) $post['category'];
        else
            $category = 0;

        $count_spaces = $this->query("SELECT key_space FROM wiki_spaces WHERE id = $for_space AND for_account=" . ACCOUNT_ID);
        if ($count_spaces->num_rows > 0) {
            $k_s = $count_spaces->fetch_assoc();
            $_POST['key_sp'] = $k_s['key_space']; //for wiki_log in editpage
            $this->query("INSERT INTO wiki_pages (for_account, title, for_space, sub_for, category, created, created_from, hash) VALUES (" . ACCOUNT_ID . " ,'$title', '$for_space', '$sub_for', '$category', '$created', '" . (int) $this->user_id . "', '$hash')");
            return $this->conn->insert_id;
        } else {
            throw new Exception("There is no available space with id $for_space !");
            return false;
        }
    }

    public function updateWikiPage($post) {
        $id = (int) $post['page_id'];
        $update_time = time();
        if ($post['updatepage'] == 1) {
            $first = 0;
            $qe = $this->query("SELECT MAX(num)+1 as last_num FROM wiki_pages_updates as aa WHERE aa.page_id = $id AND aa.for_account=" . ACCOUNT_ID);
            $ar = $qe->fetch_assoc();
            if ($ar['last_num'] === null)
                $lnum = 1;
            else
                $lnum = $ar['last_num'];
        } else {
            $first = 1;
            $qe = 1;
        }
        if (!isset($lnum)) {
            $lnum = 0;
        }
        $lnum = (int) $lnum;
        $update_from = (int) $this->user_id;

        if(mb_strlen($post['title']) < 3) {
            return false;
        }
        $content = $this->quote($post['content_edit_p']);
        $title = $this->quote($post['title']);
        $this->query("INSERT INTO wiki_pages_updates (for_account, content, update_time, page_id, update_from, first, num) VALUES (" . ACCOUNT_ID . " ,$content, '$update_time', '$id', '$update_from', $first, $lnum)");
        $last_inserted = $this->conn->insert_id;
        $this->query("UPDATE wiki_pages SET title = $title, content = $content WHERE id = $id AND for_account=" . ACCOUNT_ID . " LIMIT 1");
        return $last_inserted;
    }

    public function getWikiPageEdit($id) {
        $id = (int) $id;
        $result = $this->query("SELECT wiki_pages.title, wiki_pages.content, wiki_pages.created_from, wiki_spaces.key_space FROM wiki_pages INNER JOIN wiki_spaces ON wiki_spaces.id = wiki_pages.for_space WHERE wiki_pages.id  = $id AND wiki_pages.for_account=" . ACCOUNT_ID);
        $arr = $result->fetch_assoc();
        return $arr;
    }

    public function getPagesForSpace($space_id) {
        $space_id = (int) $space_id;
        $arr = array();
        $result = $this->query("SELECT * FROM wiki_pages WHERE for_space = $space_id AND for_account=" . ACCOUNT_ID . " ORDER BY id ASC");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }

            function buildTree(array $elements, $parentId = 0) {
                $branch = array();

                foreach ($elements as $element) {
                    if ($element['sub_for'] == $parentId) {
                        $children = buildTree($elements, $element['id']);
                        if ($children) {
                            $element['children'] = $children;
                        }
                        $branch[] = $element;
                    }
                }

                return $branch;
            }

            return buildTree($arr);
        }
    }

    public function getPageInfo($p_id, $s_id) {
        $arr = array();
        $p_id = (int) $p_id;
        $s_id = (int) $s_id;
        $result = $this->query("SELECT wiki_pages.title, wiki_pages.content, wiki_pages.created, wiki_pages.hash, users.fullname, users.username, wiki_pages_updates.update_time, usersB.fullname as modified_by, usersB.username as modified_username, wiki_pages.created_from FROM wiki_pages LEFT JOIN users ON users.id = wiki_pages.created_from LEFT JOIN wiki_pages_updates ON wiki_pages_updates.page_id = wiki_pages.id AND wiki_pages_updates.first = 0 LEFT JOIN users as usersB ON wiki_pages_updates.update_from = usersB.id WHERE for_space = $s_id AND wiki_pages.id = $p_id AND wiki_pages.for_account=" . ACCOUNT_ID);
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr = $row;
            }
        }
        return $arr;
    }

    public function deletePage($id) {
        $id = (int) $id;
        $deleted_page_subs = $this->query("SELECT id FROM wiki_pages WHERE sub_for = $id AND for_account=" . ACCOUNT_ID);
        if ($deleted_page_subs !== false) {
            $arr_p = array();
            while ($row = $deleted_page_subs->fetch_assoc()) {
                $arr_p[] = $row['id'];
            }
            $del_ids = $this->intList($arr_p);
            $this->query("DELETE FROM wiki_pages WHERE id IN ($del_ids) AND for_account=" . ACCOUNT_ID);
        }
        $this->query("DELETE FROM wiki_pages WHERE id = $id AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM wiki_pages_updates WHERE page_id = $id AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM watchers WHERE page_id = $id AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM log_wiki WHERE page_id = $id AND for_account=" . ACCOUNT_ID);
    }

    public function deleteSpace($id) {
        $id = (int) $id;

        $arr_p = array();
        $deleted_pages_ids = $this->query("SELECT id FROM wiki_pages WHERE for_space = $id AND for_account=" . ACCOUNT_ID);
        if ($deleted_pages_ids !== false) {
            while ($row = $deleted_pages_ids->fetch_assoc()) {
                $arr_p[] = $row['id'];
            }
            $del_ids = $this->intList($arr_p);
            $this->query("DELETE FROM wiki_pages_updates WHERE page_id IN ($del_ids) AND for_account=" . ACCOUNT_ID);
            $this->query("DELETE FROM watchers WHERE page_id IN ($del_ids) AND for_account=" . ACCOUNT_ID);
        }
        $this->query("DELETE FROM wiki_spaces WHERE id = $id AND for_account=" . ACCOUNT_ID);
        $this->query("DELETE FROM wiki_pages WHERE for_space = $id AND for_account=" . ACCOUNT_ID);
    }

    public function getHistoryCount($id) {
        $id = (int) $id;
        $result = $this->query("SELECT COUNT(id) as nums FROM wiki_pages_updates WHERE page_id = $id AND first = 0 AND for_account=" . ACCOUNT_ID);
        $ar = $result->fetch_assoc();
        return $ar['nums'];
    }

    public function getHistoryPage($id, $from = null) {
        $id = (int) $id;
        if ($from !== null) {
            $from = (int) $from;
        }
        if ($from == null) {
            $result = $this->query("SELECT MAX(id) as max_id FROM wiki_pages_updates WHERE page_id = $id AND for_account=" . ACCOUNT_ID);
            $arr = $result->fetch_assoc();
            return $arr['max_id'];
        } else {
            $pages = $this->query("(SELECT MIN(id) as np FROM wiki_pages_updates WHERE id > $from AND page_id = $id AND wiki_pages_updates.for_account=" . ACCOUNT_ID . ") UNION (SELECT MAX(id) FROM wiki_pages_updates WHERE id < $from AND page_id = $id AND wiki_pages_updates.for_account=" . ACCOUNT_ID . ")");
            $current = $this->query("SELECT content, num, update_time, users.fullname as u_fullname, users.username as u_username FROM wiki_pages_updates LEFT JOIN users ON users.id = wiki_pages_updates.update_from WHERE wiki_pages_updates.id = $from AND wiki_pages_updates.for_account=" . ACCOUNT_ID);
            $arr = array();
            if ($pages !== false) {
                while ($row = $pages->fetch_assoc()) {
                    $arr[] = $row;
                }
                @$res['next'] = $arr[0]['np'];
                @$res['prev'] = $arr[1]['np'];
                $res['this_content'] = $current->fetch_assoc();
                return $res;
            }
        }
    }

    public function movePage($parent, $space, $page_id) {
        $parent = (int) $parent;
        $space = (int) $space;
        $page_id = (int) $page_id;
        $childs = $this->query("SELECT id FROM wiki_pages WHERE sub_for = $page_id AND for_account=" . ACCOUNT_ID);
        $ids = array();
        if ($childs !== false) {
            while ($row = $childs->fetch_assoc()) {
                $ids[] = $row['id'];
            }
            $idd = $this->intList($ids);
            $this->query("UPDATE wiki_pages SET for_space = $space WHERE id IN ($idd) AND for_account=" . ACCOUNT_ID);
        }
        if ($parent != $page_id) {
            $result = $this->query("UPDATE wiki_pages SET sub_for = $parent, for_space = $space WHERE id = $page_id AND for_account=" . ACCOUNT_ID);
            return $result;
        } else
            return false;
    }

    public function getTicketStatistics($find) {
        $where = '';
        $where1 = '';
        if ($find !== null) {
            if ($find['project'] != '' && (int) $find['project'] != 0) {
                $proj_id = (int) $find['project'];
                $where .= "WHERE tickets.project = $proj_id AND tickets.for_account=" . ACCOUNT_ID;
                $where1 .= " AND tickets.project = $proj_id AND tickets.for_account=" . ACCOUNT_ID;
            }

            $from_date = $find['from_date'] != '' ? (int) strtotime($find['from_date']) : 0;
            $to_date = $find['to_date'] != '' ? (int) strtotime($find['to_date']) : 0;
            if ($from_date != 0 || $to_date != 0) {
                if ($where == '')
                    $a = 'WHERE tickets.for_account=' . ACCOUNT_ID . ' AND ';
                else
                    $a = 'AND';
                $where .= " $a tickets.timecreated BETWEEN $from_date AND $to_date";
                $where1 .= " AND tickets.timeclosed BETWEEN $from_date AND $to_date AND tickets.for_account=" . ACCOUNT_ID;
            }
        } else {
            $where = 'WHERE tickets.for_account=' . ACCOUNT_ID;
            $where1 = ' AND tickets.for_account=' . ACCOUNT_ID;
        }
        $arr = array();
        $result = $this->query("(SELECT ticket_priority.name, priority_colors.color, tickets.timecreated, tickets.timeclosed, tickets.id, '1' as is_for FROM tickets INNER JOIN ticket_priority ON ticket_priority.id = tickets.priority INNER JOIN priority_colors ON priority_colors.for_id = ticket_priority.id INNER JOIN projects ON projects.id = tickets.project $where ORDER BY tickets.timecreated ASC) 
UNION 
(SELECT ticket_priority.name, priority_colors.color, tickets.timecreated, tickets.timeclosed, tickets.id, '0' as is_for FROM tickets INNER JOIN ticket_priority ON ticket_priority.id = tickets.priority INNER JOIN priority_colors ON priority_colors.for_id = ticket_priority.id INNER JOIN projects ON projects.id = tickets.project WHERE tickets.timeclosed > 0 $where1 ORDER BY tickets.timeclosed ASC)");

        if ($result !== false && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if ($row['is_for'] == 1) {
                    $arr['months'][date('M-Y', $row['timecreated'])] = 0;
                }
                if ($row['is_for'] == 0) {
                    $arr['months_c'][date('M-Y', $row['timeclosed'])] = 0;
                }
                $as[] = $row;
            }

            $added_ids = array();
            $added = array();
            foreach ($as as $rr) {
                if (!in_array($rr['name'], $added)) {
                    if ($rr['is_for'] == 1) {
                        $arr['num_created'][$rr['name']]['nums'] = $arr['months'];
                    }
                    if ($rr['is_for'] == 0) {
                        $arr['num_closed'][$rr['name']]['nums'] = $arr['months_c'];
                    }
                }

                if ($rr['is_for'] == 1) {
                    $arr['num_created'][$rr['name']]['color'] = $rr['color'];
                    @$arr['num_created'][$rr['name']]['nums'][date('M-Y', $rr['timecreated'])] += 1;
                }

                if ($rr['is_for'] == 0) {
                    $arr['num_closed'][$rr['name']]['color'] = $rr['color'];
                    @$arr['num_closed'][$rr['name']]['nums'][date('M-Y', $rr['timeclosed'])] += 1;
                }

                if (!in_array($rr['id'], $added_ids)) {
                    @$arr['num_for_priority'][$rr['name']] += 1;
                    @$arr['num_all'] += 1;
                }

                $added_ids[] = $rr['id'];
                $added[] = $rr['name'];
            }
        }
        return $arr;
    }

    public function getWikiStatistics($find) {
        $where = '';
        if ($find !== null) {
            if ($find['project'] != '' && (int) $find['project'] != 0) {
                $proj_id = (int) $find['project'];
                $where .= "WHERE wiki_spaces.project_id = $proj_id AND wiki_spaces.for_account=" . ACCOUNT_ID;
            }

            $from_date = $find['from_date'] != '' ? (int) strtotime($find['from_date']) : 0;
            $to_date = $find['to_date'] != '' ? (int) strtotime($find['to_date']) : 0;
            if ($from_date != 0 || $to_date != 0) {
                if ($where == '')
                    $a = 'WHERE';
                else
                    $a = 'AND';
                $where .= " $a wiki_pages.created BETWEEN $from_date AND $to_date AND wiki_pages.for_account=" . ACCOUNT_ID;
            }
            ;
        }

        $arr = array();
        $result = $this->query("SELECT wiki_pages.created, wiki_spaces.name as space_name FROM wiki_pages INNER JOIN wiki_spaces ON wiki_spaces.id = wiki_pages.for_space INNER JOIN projects ON wiki_spaces.project_id = projects.id $where ORDER BY wiki_pages.created ASC");
        if ($result !== false && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $arr['months'][date('M-Y', $row['created'])] = 0;
                $as[] = $row;
            }

            $added = array();
            foreach ($as as $rr) {
                if (!in_array($rr['space_name'], $added)) {
                    $arr['num_created'][$rr['space_name']] = $arr['months'];
                }
                @$arr['num_created'][$rr['space_name']][date('M-Y', $rr['created'])] += 1;
                $added[] = $rr['space_name'];
            }
        }
        return $arr;
    }

    public function getCurrencies() {
        $arr = array();
        $result = $this->query("SELECT country, currency, (SELECT IF(currency IS NULL, 0, 1) FROM accounts WHERE accounts.currency=currencies.currency AND accounts.id=" . ACCOUNT_ID . ") as def FROM currencies");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function setDefCurrency($curr) {
        $curr = $this->escape($curr);
        $result = $this->query("UPDATE accounts SET currency = '$curr' WHERE id=" . ACCOUNT_ID);
        return $result;
    }

    public function getTicketsSearch($s_term, $my_ticket) {
        $s_term = $this->escape($s_term);
        $arr = array();
        $not_me = '';
        $my_ticket = (int) $my_ticket;
        if ($my_ticket > 0) {
            $not_me = ' AND tickets.id !=' . $my_ticket;
        }
        $result = $this->query("SELECT tickets.id, subject, ticket_id, abbr, projects.name as name FROM tickets INNER JOIN projects ON projects.id = tickets.project WHERE subject LIKE '%$s_term%' $not_me AND tickets.project IN (" . $this->intList($this->userProjectIds()) . ") AND tickets.for_account=" . ACCOUNT_ID . " LIMIT 5");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function setIssueLinks($issue_links, $update = false) {
        $num = count($issue_links['issue_links']);
        $values = '';
        $new_insert = '';
        $updated = array();
        $update_values = array();
        for ($i = 0; $i < $num; $i++) {
            if (isset($issue_links['issue_links'][$i], $issue_links['issue_links_types'][$i]) && (int) $issue_links['issue_links'][$i] > 0 && strlen($issue_links['issue_links_types'][$i]) != 0) {
                $t_1 = (int) $issue_links['ticket_1_id'];
                $t_2 = (int) $issue_links['issue_links'][$i];
                $type = $this->quote($issue_links['issue_links_types'][$i]);
                $link_id = isset($issue_links['update'][$i]) ? $issue_links['update'][$i] : '';
                if ($update === true) {
                    if ($link_id == '') { //hmm we new for insert
                        $new_insert .= "(" . ACCOUNT_ID . " ,$t_1, $t_2, $type),";
                    } else {
                        $update_values[] = "ticket_1=$t_1, ticket_2=$t_2, type=$type WHERE id=" . (int) $link_id;
                        $updated[] = (int) $link_id;
                    }
                } else {
                    $values .= "(" . ACCOUNT_ID . " ,$t_1, $t_2, $type),";
                }
            }
        }
        if ($update === true) {
            $origins = explode(',', $issue_links['origins']);
            if (empty($updated)) {
                $delete_it = $issue_links['origins'];
            } elseif (!empty($updated) && $issue_links['origins'] != '') {
                $merged = array_merge($origins, $updated);
                $for_delete = array_diff($merged, array_diff_assoc($merged, array_unique($merged)));
                $delete_it = implode(',', $for_delete);
            }
            $delete_it = isset($delete_it) ? trim($this->idList($delete_it), ',') : '';
            if ($delete_it != '') {
                $this->query("DELETE FROM connected_tickets WHERE id IN ($delete_it) AND for_account=" . ACCOUNT_ID);
            }
            if (!empty($update_values)) {
                foreach ($update_values as $update) {
                    $this->query('UPDATE connected_tickets SET ' . $update . ' AND for_account=' . ACCOUNT_ID);
                }
            }
            if ($new_insert != '') {
                $new_insert = rtrim($new_insert, ',');
                $this->query('INSERT INTO connected_tickets (for_account, ticket_1, ticket_2, type) VALUES ' . $new_insert);
            }
        } else {
            if ($values != '') {
                $values = rtrim($values, ',');
                $this->query('INSERT INTO connected_tickets (for_account, ticket_1, ticket_2, type) VALUES ' . $values);
            }
        }
    }

    public function getIssueLinks($t_id) {
        $arr = array();
        $t_id = (int) $t_id;
        $result = $this->query('SELECT id, ticket_1, ticket_2, type, IF(ticket_1=' . $t_id . ', 1, 0) as who_is, IF(ticket_1=' . $t_id . ', ticket_2, ticket_1) as get_from, (SELECT subject FROM tickets WHERE id = get_from) as subject, (SELECT project FROM tickets WHERE id = get_from) as project_id, (SELECT ticket_id FROM tickets WHERE id = get_from) as ticket_id, (SELECT abbr FROM projects WHERE id = project_id) as project_abbr, (SELECT name FROM projects WHERE id = project_id) as project_name FROM connected_tickets WHERE (ticket_1 = ' . $t_id . ' OR ticket_2 = ' . $t_id . ') AND connected_tickets.for_account=' . ACCOUNT_ID);
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

}
