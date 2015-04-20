<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module newsletter
 *
 * @package    mod_newsletter
 * @copyright  2013 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

////////////////////////////////////////////////////////////////////////////////
// Newsletter internal constants                                              //
////////////////////////////////////////////////////////////////////////////////

define('NEWSLETTER_CRON_TEMP_FILENAME', 'newsletter_cron.tmp');
define('NEWSLETTER_LOCK_DIR', $CFG->dataroot . '/temp/mod/newsletter');
define('NEWSLETTER_LOCK_SUFFIX', 'lock');
define('NEWSLETTER_TEMP_DIR', NEWSLETTER_LOCK_DIR);
define('NEWSLETTER_BASE_STYLESHEET_PATH', 'reset.css');

define('NEWSLETTER_FILE_AREA_STYLESHEETS', 'stylesheets');
define('NEWSLETTER_FILE_AREA_ATTACHMENTS', 'attachments');

define('NEWSLETTER_FILE_OPTIONS_SUBDIRS', 0);

define('NEWSLETTER_DELIVERY_STATUS_UNKNOWN', 0);
define('NEWSLETTER_DELIVERY_STATUS_DELIVERED', 1);
define('NEWSLETTER_DELIVERY_STATUS_FAILED', 2);

define('NEWSLETTER_SUBSCRIBER_STATUS_OK', 0);
define('NEWSLETTER_SUBSCRIBER_STATUS_PROBLEMATIC', 1);
define('NEWSLETTER_SUBSCRIBER_STATUS_BLACKLISTED', 2);
define('NEWSLETTER_SUBSCRIBER_STATUS_UNSUBSCRIBED', 4);

define('NEWSLETTER_ACTION_VIEW_NEWSLETTER', 'view');
define('NEWSLETTER_ACTION_CREATE_ISSUE', 'createissue');
define('NEWSLETTER_ACTION_EDIT_ISSUE', 'editissue');
define('NEWSLETTER_ACTION_READ_ISSUE', 'readissue');
define('NEWSLETTER_ACTION_DELETE_ISSUE', 'deleteissue');
define('NEWSLETTER_ACTION_MANAGE_SUBSCRIPTIONS', 'managesubscriptions');
define('NEWSLETTER_ACTION_EDIT_SUBSCRIPTION', 'editsubscription');
define('NEWSLETTER_ACTION_DELETE_SUBSCRIPTION', 'deletesubscription');
define('NEWSLETTER_ACTION_SUBSCRIBE_COHORTS', 'subscribecohorts');
define('NEWSLETTER_ACTION_SUBSCRIBE', 'subscribe');
define('NEWSLETTER_ACTION_UNSUBSCRIBE', 'unsubscribe');

define('NEWSLETTER_GROUP_ISSUES_BY_YEAR', 'year');
define('NEWSLETTER_GROUP_ISSUES_BY_MONTH', 'month');
define('NEWSLETTER_GROUP_ISSUES_BY_WEEK', 'week');
define('NEWSLETTER_GROUP_ISSUES_BY_DAY', 'day');

define('NEWSLETTER_SUBSCRIPTION_MODE_OPT_IN', 0);
define('NEWSLETTER_SUBSCRIPTION_MODE_OPT_OUT', 1);
define('NEWSLETTER_SUBSCRIPTION_MODE_FORCED', 2);
define('NEWSLETTER_SUBSCRIPTION_MODE_NONE', 3);

define('NEWSLETTER_NEW_USER', -1);

define('NEWSLETTER_NO_ISSUE', 0);
define('NEWSLETTER_NO_USER', 0);

define('NEWSLETTER_DEFAULT_STYLESHEET', 0);

define('NEWSLETTER_GROUP_BY_DEFAULT', NEWSLETTER_GROUP_ISSUES_BY_WEEK);
define('NEWSLETTER_FROM_DEFAULT', 0);
define('NEWSLETTER_COUNT_DEFAULT', 30);
define('NEWSLETTER_TO_DEFAULT', 0);
define('NEWSLETTER_SUBSCRIPTION_DEFAULT', 0);

define('NEWSLETTER_PREFERENCE_COUNT', 'newsletter_count');
define('NEWSLETTER_PREFERENCE_GROUP_BY', 'newsletter_group_by');

define('NEWSLETTER_PARAM_ID', 'id');
define('NEWSLETTER_PARAM_ACTION', 'action');
define('NEWSLETTER_PARAM_ISSUE', 'issue');
define('NEWSLETTER_PARAM_GROUP_BY', 'groupby');
define('NEWSLETTER_PARAM_FROM', 'from');
define('NEWSLETTER_PARAM_COUNT', 'count');
define('NEWSLETTER_PARAM_TO', 'to');
define('NEWSLETTER_PARAM_USER', 'user');
define('NEWSLETTER_PARAM_CONFIRM', 'confirm');
define('NEWSLETTER_PARAM_HASH', 'hash');
define('NEWSLETTER_PARAM_SUBSCRIPTION', 'sub');
define('NEWSLETTER_PARAM_DATA', 'data');

define('NEWSLETTER_CONFIRM_YES', 1);
define('NEWSLETTER_CONFIRM_NO', 0);
define('NEWSLETTER_CONFIRM_UNKNOWN', -1);

define('NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_EMAIL', 'col_email');
define('NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_NAME', 'col_name');
define('NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_HEALTH', 'col_health');
define('NEWSLETTER_SUBSCRIPTION_LIST_COLUMN_ACTIONS', 'col_actions');

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function newsletter_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:         return true;
        default:                        return null;
    }
}

/**
 * Saves a new instance of the newsletter into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $newsletter An object from the form in mod_form.php
 * @param mod_newsletter_mod_form $mform
 * @return int The id of the newly inserted newsletter record
 */
function newsletter_add_instance(stdClass $newsletter, mod_newsletter_mod_form $mform = null) {
    global $DB;

    $newsletter->timecreated = time();
    $newsletter->timemodified = time();

    $newsletter->id = $DB->insert_record('newsletter', $newsletter);

    $fileoptions = array('subdirs' => NEWSLETTER_FILE_OPTIONS_SUBDIRS,
                         'maxbytes' => 0,
                         'maxfiles' => -1);

    $context = context_module::instance($newsletter->coursemodule);

    if ($mform && $mform->get_data() && $mform->get_data()->stylesheets) {
        file_save_draft_area_files($mform->get_data()->stylesheets, $context->id, 'mod_newsletter', NEWSLETTER_FILE_AREA_STYLESHEETS, $newsletter->id, $fileoptions);
    }

    if ($newsletter->subscriptionmode == NEWSLETTER_SUBSCRIPTION_MODE_OPT_OUT ||
        $newsletter->subscriptionmode == NEWSLETTER_SUBSCRIPTION_MODE_FORCED) {

        $users = get_enrolled_users($context);
        foreach ($users as $user) {
            if (!$DB->record_exists("newsletter_subscriptions", array("userid" => $user->id, "newsletterid" => $newsletter->id))) {
                $sub = new stdClass();
                $sub->userid  = $user->id;
                $sub->newsletterid = $newsletter->id;
                $sub->health = NEWSLETTER_SUBSCRIBER_STATUS_OK;
                $DB->insert_record("newsletter_subscriptions", $sub, true, true);
            }
        }
    }

    return $newsletter->id;
}

/**
 * Updates an instance of the newsletter in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $newsletter An object from the form in mod_form.php
 * @param mod_newsletter_mod_form $mform
 * @return boolean Success/Fail
 */
function newsletter_update_instance(stdClass $newsletter, mod_newsletter_mod_form $mform = null) {
    global $DB;

    $newsletter->timemodified = time();
    $newsletter->id = $newsletter->instance;

    $fileoptions = array('subdirs' => NEWSLETTER_FILE_OPTIONS_SUBDIRS,
                         'maxbytes' => 0,
                         'maxfiles' => -1);

    $cmid = get_coursemodule_from_instance('newsletter', $newsletter->id);
    $context = context_module::instance($cmid->id);

    if ($mform && $mform->get_data() && $mform->get_data()->stylesheets) {
        file_save_draft_area_files($mform->get_data()->stylesheets, $context->id, 'mod_newsletter', NEWSLETTER_FILE_AREA_STYLESHEETS, $newsletter->id, $fileoptions);
    }

    if ($newsletter->subscriptionmode == NEWSLETTER_SUBSCRIPTION_MODE_OPT_OUT ||
        $newsletter->subscriptionmode == NEWSLETTER_SUBSCRIPTION_MODE_FORCED) {

        $users = get_enrolled_users($context);
        foreach ($users as $user) {
            if (!$DB->record_exists("newsletter_subscriptions", array("userid" => $user->id, "newsletterid" => $newsletter->id))) {
                $sub = new stdClass();
                $sub->userid  = $user->id;
                $sub->newsletterid = $newsletter->id;
                $sub->health = NEWSLETTER_SUBSCRIBER_STATUS_OK;
                $DB->insert_record("newsletter_subscriptions", $sub, true, true);
            }
        }
    }

    return $DB->update_record('newsletter', $newsletter);
}

/**
 * Removes an instance of the newsletter from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function newsletter_delete_instance($id) {
    global $DB;

    if (!$newsletter = $DB->get_record('newsletter', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('newsletter', $newsletter->id);
    $context = context_module::instance($cm->id);

    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, 'mod_newsletter', NEWSLETTER_FILE_AREA_STYLESHEETS, $newsletter->id);
    foreach ($files as $f) {
        $file->delete();
    }

    $issues = $DB->get_records('newsletter_issues', array('newsletterid' => $newsletter->id));
    foreach ($issues as $issue) {
        $files = $fs->get_area_files($contextid, 'mod_newsletter', NEWSLETTER_FILE_AREA_ATTACHMENTS, $issue->id);
        foreach ($files as $f) {
            $file->delete();
        }
    }

    $DB->delete_records('newsletter_subscriptions', array('newsletterid' => $newsletter->id));
    $DB->delete_records('newsletter_issues', array('newsletterid' => $newsletter->id));
    $DB->delete_records('newsletter', array('id' => $newsletter->id));

    return true;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all newsletter subscriptions in the database
 * and clean up any related data.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function newsletter_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/newsletter/locallib.php');

    $status = array();

    $sql = "SELECT n.id FROM {newsletter} n WHERE n.course = :courseid";
    $params = array('courseid' => $data->courseid);
    if ($newsletterids = $DB->get_fieldset_sql($sql, $params)) {
        foreach ($newsletterids as $newsletterid) {
            $cm = get_coursemodule_from_instance('newsletter', $newsletterid, $data->courseid, false, MUST_EXIST);
            $newsletter = new newsletter($cm->id);
            $status = array_merge($status, $newsletter->reset_userdata($data));
        }
    }

    return $status;
}


/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function newsletter_user_outline($course, $user, $mod, $newsletter) {
    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $newsletter the module instance record
 * @return void, is supposed to echo directly
 */
function newsletter_user_complete($course, $user, $mod, $newsletter) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in newsletter activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function newsletter_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link newsletter_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function newsletter_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see newsletter_get_recent_mod_activity()}

 * @return void
 */
function newsletter_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function newsletter_cron() {
    global $DB, $CFG;

    $config = get_config('newsletter');

    $debugoutput = $config->debug;
    if ($debugoutput) {
        echo "\n";
    }

    if ($debugoutput) {
        echo "Deleting expired inactive user accounts...\n";
    }

    $query = "SELECT u.id
                FROM {user} u
               INNER JOIN {newsletter_subscriptions} ns ON u.id = ns.userid
               WHERE u.confirmed = 0
                 AND :now - u.timecreated > :limit";
    $ids = $DB->get_fieldset_sql($query, array('now' => time(), 'limit' => $config->activation_timeout));

    if (!empty($ids)) {
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('user', "id " . $insql, $params);
        $DB->delete_records_select('newsletter_subscriptions', "userid " . $insql, $params);
    }

    if ($debugoutput) {
        echo "Done.\n";
    }

    require_once('cron_helper.php');
    cron_helper::lock();

    if (!is_dir(NEWSLETTER_TEMP_DIR)) {
        mkdir(NEWSLETTER_TEMP_DIR, 0777, true);
    }

    $tempfilename = NEWSLETTER_TEMP_DIR . '/' . NEWSLETTER_CRON_TEMP_FILENAME;

    $continue = file_exists($tempfilename);

    $undeliveredissues = array();
    $issuestatuses = array();

    require_once('locallib.php');

    $unsublinks = array();

    if ($continue) {
        if ($debugoutput) {
            echo "Temp file found, continuing cron job...\n";
            echo "Reading data from temp file...\n";
        }
        $issuestatuses = json_decode(file_get_contents($tempfilename), true);

        $newsletters = $DB->get_records('newsletter');
        foreach ($newsletters as $newsletter) {
            $coursemodule = get_coursemodule_from_instance('newsletter', $newsletter->id, 0, false, MUST_EXIST);
            $newsletterobject = new newsletter($coursemodule->id);
            $issues = $DB->get_records('newsletter_issues', array('newsletterid' => $newsletter->id));
            foreach ($issues as $issue) {
                if ($issue->publishon <= time() && !$issue->delivered) {
                    $issue->newsletter = $newsletterobject;
                    $undeliveredissues[$issue->id] = $issue;
                }
            }
            if ($newsletter->subscriptionmode != NEWSLETTER_SUBSCRIPTION_MODE_FORCED) {
                $url = new moodle_url('/mod/newsletter/subscribe.php', array('id' => $newsletterobject->get_course_module()->id));
                $unsublinks[$newsletterobject->get_instance()->id] = $url;
            }
        }
    } else {
        if ($debugoutput) {
            echo "Starting cron job...\n";
            echo "Collecting data...\n";
        }
        $newsletters = $DB->get_records('newsletter');
        foreach ($newsletters as $newsletter) {
            $coursemodule = get_coursemodule_from_instance('newsletter', $newsletter->id, 0, false, MUST_EXIST);
            $newsletterobject = new newsletter($coursemodule->id);
            $issues = $DB->get_records('newsletter_issues', array('newsletterid' => $newsletter->id));
            foreach ($issues as $issue) {
                if ($issue->publishon <= time() && !$issue->delivered) {
                    $issue->newsletter = $newsletterobject;
                    $undeliveredissues[$issue->id] = $issue;
                    if ($issue->status) {
                        $issuestatuses[$issue->id] = json_decode($issue->status, true);
                    } else {
                        $issuestatuses[$issue->id] = array();
                        $recipients = newsletter_get_all_valid_recipients($newsletter->id);
                        foreach ($recipients as $recipient) {
                            $issuestatuses[$issue->id][$recipient->userid] = NEWSLETTER_DELIVERY_STATUS_UNKNOWN;
                        }
                        $DB->set_field('newsletter_issues', 'status', json_encode($issuestatuses[$issue->id]), array('id' => $issue->id));
                    }
                }
            }
            if ($newsletter->subscriptionmode != NEWSLETTER_SUBSCRIPTION_MODE_FORCED) {
                $url = new moodle_url('/mod/newsletter/subscribe.php', array('id' => $newsletterobject->get_course_module()->id));
                $unsublinks[$newsletterobject->get_instance()->id] = $url;
            }
        }
        file_put_contents($tempfilename, json_encode($issuestatuses));
    }

    if ($debugoutput) {
        echo "Data collection complete. Delivering...\n";
    }
    require_once('locallib.php');
    foreach ($undeliveredissues as $issueid => $issue) {
        if ($debugoutput) {
            echo "Processing newsletter (id = {$issue->newsletterid}), issue \"{$issue->title}\" (id = {$issue->id})...";
        }
        $newsletter = $issue->newsletter;
        $fs = get_file_storage();
        $files = $fs->get_area_files($newsletter->get_context()->id, 'mod_newsletter', 'attachments', $issue->id, "", false);
        $attachments = array();
        foreach ($files as $file) {
            $attachments[$file->get_filename()] = $file->copy_content_to_temp();
        }

        if (isset($unsublinks[$newsletter->get_instance()->id])) {
            $url = $unsublinks[$newsletter->get_instance()->id];
            $url->param(NEWSLETTER_PARAM_USER, 'replacewithuserid');
            $a = array(
            'link' => $url->__toString(),
            'text' => get_string('unsubscribe_link_text', 'newsletter'));
            $issue->htmlcontent .= get_string('unsubscribe_link', 'newsletter', $a);
        }

        $plaintexttmp = newsletter_convert_html_to_plaintext($issue->htmlcontent);
        $htmltmp = $newsletter->inline_css($issue->htmlcontent, $issue->stylesheetid);

        foreach ($issuestatuses[$issueid] as $subscriberid => $status) {
            if ($status != NEWSLETTER_DELIVERY_STATUS_DELIVERED) {
                $recipient = $DB->get_record('user', array('id' => $subscriberid));
                if ($debugoutput) {
                    echo "Sending message to {$recipient->email}... ";
                }
                $plaintext = str_replace('replacewithuserid', $subscriberid, $plaintexttmp);
                $html = str_replace('replacewithuserid', $subscriberid, $htmltmp);
                
                $result = newsletter_email_to_user(
                        $recipient,
                        $newsletter->get_instance()->name,
                        $issue->title,
                        $plaintext,
                        $html,
                        $attachments);
                if ($debugoutput) {
                    echo (NEWSLETTER_DELIVERY_STATUS_DELIVERED ? "OK" : "FAILED") . "!\n";
                }
                $issuestatuses[$issueid][$subscriberid] = $result ? NEWSLETTER_DELIVERY_STATUS_DELIVERED : NEWSLETTER_DELIVERY_STATUS_FAILED;
                file_put_contents($tempfilename, json_encode($issuestatuses));
            }
        }
    }

    if ($debugoutput) {
        echo "Delivery complete. Updating database...\n";
    }
    foreach ($issuestatuses as $issueid => $statuses) {
        $DB->set_field('newsletter_issues', 'status', json_encode($statuses), array('id' => $issueid));
        $completed = true;
        foreach ($statuses as $status) {
            if ($status != NEWSLETTER_DELIVERY_STATUS_DELIVERED) {
                $completed = false;
                break;
            }
        }
        $DB->set_field('newsletter_issues', 'delivered', $completed, array('id' => $issueid));
    }
    if ($debugoutput) {
        echo "Database update complete. Cleaning up...\n";
    }

    unlink($tempfilename);
    cron_helper::unlock();

    return true;
}

/**
 * 
 * @param unknown $newsletterid
 */
function newsletter_get_all_valid_recipients($newsletterid) {
    global $DB;
    $validstatuses = array(NEWSLETTER_SUBSCRIBER_STATUS_OK, NEWSLETTER_SUBSCRIBER_STATUS_PROBLEMATIC);
    list($insql, $params) = $DB->get_in_or_equal($validstatuses, SQL_PARAMS_NAMED);
    $params['newsletterid'] = $newsletterid;
    $sql = "SELECT *
              FROM {newsletter_subscriptions} ns
        INNER JOIN {user} u ON ns.userid = u.id
             WHERE ns.newsletterid = :newsletterid
               AND u.confirmed = 1
               AND ns.health $insql";
    return $DB->get_records_sql($sql, $params);
}

function newsletter_convert_html_to_plaintext($content) {
    global $CFG;
    require_once($CFG->libdir . '/html2text.php');
    $html2text = new html2text($content);
    return $html2text->get_text();
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function newsletter_get_extra_capabilities() {
    return array();
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function newsletter_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for newsletter file areas
 *
 * @package mod_newsletter
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function newsletter_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the newsletter file areas
 *
 * @package mod_newsletter
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the newsletter's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function newsletter_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, false, $cm);

    if (!$newsletter = $DB->get_record('newsletter', array('id' => $cm->instance))) {
        return false;
    }

    $fileareas = array(NEWSLETTER_FILE_AREA_STYLESHEETS, NEWSLETTER_FILE_AREA_ATTACHMENTS);
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $itemid = (int)array_shift($args);

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    if ($filearea == NEWSLETTER_FILE_AREA_STYLESHEETS) {
        if ($newsletter->id != $itemid) {
            return false;
        }
        $fullpath = "/$context->id/mod_newsletter/$filearea/$itemid/$relativepath";
    } else if ($filearea == NEWSLETTER_FILE_AREA_ATTACHMENTS) {
        if (!$DB->record_exists('newsletter_issues', array('id' => $itemid, 'newsletterid' => $newsletter->id))) {
            return false;
        }
        $fullpath = "/$context->id/mod_newsletter/$filearea/$itemid/$relativepath";
    } else {
        return false;
    }
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true, $options);
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding newsletter nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the newsletter module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function newsletter_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the newsletter settings
 *
 * This function is called when the context for the page is a newsletter module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $newsletternode {@link navigation_node}
 */
function newsletter_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $newsletternode=null) {
}
////////////////////////////////////////////////////////////////////////////////
// Event handlers                                                             //
////////////////////////////////////////////////////////////////////////////////

function newsletter_user_enrolled($user) {
    global $DB;

    $sql = "SELECT n.id, cm.id AS cmid
              FROM {newsletter} n
              JOIN {course_modules} cm ON cm.instance = n.id
              JOIN {modules} m ON m.id = cm.module
         LEFT JOIN {newsletter_subscriptions} ns ON ns.newsletterid = n.id AND ns.userid = :userid
             WHERE n.course = :courseid
               AND (n.subscriptionmode = :submode1
                OR n.subscriptionmode = :submode2)
               AND m.name = 'newsletter'
               AND ns.id IS NULL";
    $params = array('courseid' => $user->courseid,
                      'userid' => $user->id,
                     'submode1' => NEWSLETTER_SUBSCRIPTION_MODE_OPT_OUT,
                     'submode2' => NEWSLETTER_SUBSCRIPTION_MODE_FORCED);

    $newsletters = $DB->get_records_sql($sql, $params);
    foreach ($newsletters as $newsletter) {
        $cm = get_coursemodule_from_instance('newsletter', $newsletter->id);
        $newsletter = new newsletter($cm->id);
        $newsletter->subscribe($user->id);
    }

    return true;
}

function newsletter_user_unenrolled($cp) {
    global $DB;

    if ($cp->lastenrol) {
        $params = array('userid' => $cp->userid, 'courseid' => $cp->courseid);
        $DB->delete_records_select('newsletter_subscriptions', 'userid = :userid AND newsletterid IN (SELECT n.id FROM {newsletter} n WHERE n.course = :courseid)', $params);
    }

    return true;
}

function newsletter_user_created($user) {
    if (($user->username != $user->email || $user->username != $user->lastname) && $user->suspended) { //TODO: implement a better check!
        $user->courseid = 1;
        return newsletter_user_enrolled($user);
    } else {
        return true;
    }
}

/**
 * 
 * @param unknown $user
 * @return boolean
 */
function newsletter_user_deleted($user) {
    global $DB;

    $params = array('userid' => $user->id);
    $DB->delete_records_select('newsletter_subscriptions', 'userid = :userid', $params);

    return true;
}

////////////////////////////////////////////////////////////////////////////////
// Mail utility functions                                                     //
////////////////////////////////////////////////////////////////////////////////

/**
 * Send an email to a specified user
 * This is a version of the core function of the similar name modified to allow
 * for multiple attachments. Everything else remains identical.
 *
 * @global object
 * @global string
 * @global string IdentityProvider(IDP) URL user hits to jump to mnet peer.
 * @uses SITEID
 * @param stdClass $user  A {@link $USER} object
 * @param stdClass $from A {@link $USER} object
 * @param string $subject plain text subject line of the email
 * @param string $messagetext plain text version of the message
 * @param string $messagehtml complete html version of the message (optional)
 * @param array $attachment an array of files to be attached in the form of
 * attachmentname => attachmentfilepath
 * @param bool $usetrueaddress determines whether $from email address should
 *          be sent out. Will be overruled by user profile setting for maildisplay
 * @param string $replyto Email address to reply to
 * @param string $replytoname Name of reply to recipient
 * @param int $wordwrapwidth custom word wrap width, default 79
 * @return bool Returns true if mail was sent OK and false if there was an error.
 */
function newsletter_email_to_user($user, $from, $subject, $messagetext, $messagehtml = '', $attachment = array(), $usetrueaddress = true, $replyto = '', $replytoname = '', $wordwrapwidth = 79) {
    global $CFG;
    require_once($CFG->libdir . '/moodlelib.php');

    if (empty($user) || empty($user->email)) {
        $nulluser = 'User is null or has no email';
        error_log($nulluser);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$nulluser);
        }
        return false;
    }

    if (!empty($user->deleted)) {
        // do not mail deleted users
        $userdeleted = 'User is deleted';
        error_log($userdeleted);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$userdeleted);
        }
        return false;
    }

    if (!empty($CFG->noemailever)) {
        // hidden setting for development sites, set in config.php if needed
        $noemail = 'Not sending email due to noemailever config setting';
        error_log($noemail);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$noemail);
        }
        return true;
    }

    if (!empty($CFG->divertallemailsto)) {
        $subject = "[DIVERTED {$user->email}] $subject";
        $user = clone($user);
        $user->email = $CFG->divertallemailsto;
    }

    // skip mail to suspended users - all user accounts created by the module are created suspended
    if ((isset($user->auth) && $user->auth=='nologin') or (isset($user->suspended) && $user->suspended)) {
        return true;
    }

    if (!validate_email($user->email)) {
        // we can not send emails to invalid addresses - it might create security issue or confuse the mailer
        $invalidemail = "User $user->id (".fullname($user).") email ($user->email) is invalid! Not sending.";
        error_log($invalidemail);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$invalidemail);
        }
        return false;
    }

    if (over_bounce_threshold($user)) {
        $bouncemsg = "User $user->id (".fullname($user).") is over bounce threshold! Not sending.";
        error_log($bouncemsg);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$bouncemsg);
        }
        return false;
    }

    // If the user is a remote mnet user, parse the email text for URL to the
    // wwwroot and modify the url to direct the user's browser to login at their
    // home site (identity provider - idp) before hitting the link itself
    if (is_mnet_remote_user($user)) {
        require_once($CFG->dirroot.'/mnet/lib.php');

        $jumpurl = mnet_get_idp_jump_url($user);
        $callback = partial('mnet_sso_apply_indirection', $jumpurl);

        $messagetext = preg_replace_callback("%($CFG->wwwroot[^[:space:]]*)%",
                $callback,
                $messagetext);
        $messagehtml = preg_replace_callback("%href=[\"'`]($CFG->wwwroot[\w_:\?=#&@/;.~-]*)[\"'`]%",
                $callback,
                $messagehtml);
    }
    $mail = get_mailer();

    if (!empty($mail->SMTPDebug)) {
        echo '<pre>' . "\n";
    }

    $temprecipients = array();
    $tempreplyto = array();

    $supportuser = core_user::get_support_user() ;

    // make up an email address for handling bounces
    if (!empty($CFG->handlebounces)) {
        $modargs = 'B'.base64_encode(pack('V',$user->id)).substr(md5($user->email),0,16);
        $mail->Sender = generate_email_processing_address(0,$modargs);
    } else {
        $mail->Sender = $supportuser->email;
    }

    if (is_string($from)) { // So we can pass whatever we want if there is need
        $mail->From     = $CFG->noreplyaddress;
        $mail->FromName = $from;
    } else if ($usetrueaddress and $from->maildisplay) {
        $mail->From     = $from->email;
        $mail->FromName = fullname($from);
    } else {
        $mail->From     = $CFG->noreplyaddress;
        $mail->FromName = fullname($from);
        if (empty($replyto)) {
            $tempreplyto[] = array($CFG->noreplyaddress, get_string('noreplyname'));
        }
    }

    if (!empty($replyto)) {
        $tempreplyto[] = array($replyto, $replytoname);
    }

    $mail->Subject = substr($subject, 0, 900);

    $temprecipients[] = array($user->email, fullname($user));

    $mail->WordWrap = $wordwrapwidth;                   // set word wrap

    if (!empty($from->customheaders)) {                 // Add custom headers
        if (is_array($from->customheaders)) {
            foreach ($from->customheaders as $customheader) {
                $mail->AddCustomHeader($customheader);
            }
        } else {
            $mail->AddCustomHeader($from->customheaders);
        }
    }

    if (!empty($from->priority)) {
        $mail->Priority = $from->priority;
    }

    if ($messagehtml && !empty($user->mailformat) && $user->mailformat == 1) { // Don't ever send HTML to users who don't want it
        $mail->IsHTML(true);
        $mail->Encoding = 'quoted-printable';           // Encoding to use
        $mail->Body    =  $messagehtml;
        $mail->AltBody =  "\n$messagetext\n";
    } else {
        $mail->IsHTML(false);
        $mail->Body =  "\n$messagetext\n";
    }

    foreach ($attachment as $attachname => $attachlocation) {
        if (preg_match( "~\\.\\.~" ,$attachlocation)) {    // Security check for ".." in dir path
            $temprecipients[] = array($supportuser->email, fullname($supportuser, true));
            $mail->AddStringAttachment('Error in attachment.  User attempted to attach a filename with a unsafe name.', 'error.txt', '8bit', 'text/plain');
        } else {
            require_once($CFG->libdir.'/filelib.php');
            $mimetype = mimeinfo('type', $attachname);
            $mail->AddAttachment($attachlocation, $attachname, 'base64', $mimetype);
        }
    }

    // Check if the email should be sent in an other charset then the default UTF-8
    if ((!empty($CFG->sitemailcharset) || !empty($CFG->allowusermailcharset))) {

        // use the defined site mail charset or eventually the one preferred by the recipient
        $charset = $CFG->sitemailcharset;
        if (!empty($CFG->allowusermailcharset)) {
            if ($useremailcharset = get_user_preferences('mailcharset', '0', $user->id)) {
                $charset = $useremailcharset;
            }
        }

        // convert all the necessary strings if the charset is supported
        $charsets = get_list_of_charsets();
        unset($charsets['UTF-8']);
        if (in_array($charset, $charsets)) {
            $mail->CharSet  = $charset;
            $mail->FromName = textlib::convert($mail->FromName, 'utf-8', strtolower($charset));
            $mail->Subject  = textlib::convert($mail->Subject, 'utf-8', strtolower($charset));
            $mail->Body     = textlib::convert($mail->Body, 'utf-8', strtolower($charset));
            $mail->AltBody  = textlib::convert($mail->AltBody, 'utf-8', strtolower($charset));

            foreach ($temprecipients as $key => $values) {
                $temprecipients[$key][1] = textlib::convert($values[1], 'utf-8', strtolower($charset));
            }
            foreach ($tempreplyto as $key => $values) {
                $tempreplyto[$key][1] = textlib::convert($values[1], 'utf-8', strtolower($charset));
            }
        }
    }

    foreach ($temprecipients as $values) {
        $mail->AddAddress($values[0], $values[1]);
    }
    foreach ($tempreplyto as $values) {
        $mail->AddReplyTo($values[0], $values[1]);
    }

    if ($mail->Send()) {
        set_send_count($user);
        if (!empty($mail->SMTPDebug)) {
            echo '</pre>';
        }
        return true;
    } else {
        add_to_log(SITEID, 'library', 'mailer', qualified_me(), 'ERROR: '. $mail->ErrorInfo);
        if (CLI_SCRIPT) {
            mtrace('Error: lib/moodlelib.php email_to_user(): '.$mail->ErrorInfo);
        }
        if (!empty($mail->SMTPDebug)) {
            echo '</pre>';
        }
        return false;
    }
}