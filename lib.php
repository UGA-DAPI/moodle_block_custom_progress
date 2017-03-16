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
 * Progress Bar block common configuration and helper functions
 *
 * Instructions for adding new modules so they can be monitored
 * ====================================================================================================================
 * Activities that can be monitored (all resources are treated together) are defined in the
 * block_custom_progress_monitorable_modules() function.
 *
 * Modules can be added with:
 *  - defaultTime (deadline from module if applicable),
 *  - actions (array if action-query pairs) and
 *  - defaultAction (selected by default in config page and needed for backwards compatibility)
 *
 * The module name needs to be the same as the table name for module in the database.
 *
 * Queries need to produce at least one result for completeness to go green, ie there is a record
 * in the DB that indicates the user's completion.
 *
 * Queries may include the following placeholders that are substituted when the query is run. Note
 * that each placeholder can only be used once in each query.
 *  :eventid (the id of the activity in the DB table that relates to it, eg., an assignment id)
 *  :cmid (the course module id that identifies the instance of the module within the course),
 *  :userid (the current user's id) and
 *  :courseid (the current course id)
 *
 * When you add a new module, you need to add a translation for it in the lang file.
 * If you add new action names, you need to add a translation for these in the lang file.
 *
 * Note: Activity completion is automatically available when enabled (sitewide setting) and set for
 * an activity.
 *
 * Passing relies on a passing grade being set for an activity in the Gradebook.
 *
 * If you have added a new module to this array and think other's may benefit from the query you
 * have created, please share it by sending it to michaeld@moodle.com
 * ====================================================================================================================
 *
 * @package    contrib
 * @subpackage block_custom_progress
 * @copyright  2010 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Global defaults.

define('DEFAULT_ENABLE_CUSTOM_PIX', 0);

/**
 * Provides information about monitorable modules
 *
 * @return array
 */
function block_custom_progress_monitorable_modules() {
    global $CFG, $DB;

    $modules = array(
        'aspirelist' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'aspirelist'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_aspirelist'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'assign' => array(
            'defaultTime' => 'duedate',
            'actions' => array(
                'submitted'    => "SELECT id
                                     FROM {assign_submission}
                                    WHERE assignment = :eventid
                                      AND userid = :userid
                                      AND status = 'submitted'",
                'marked'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'marked',
            'alternatelink' => array(
                'url' => '/mod/assign/view.php?id=:cmid&action=grading',
                'capability' => 'mod/assign:grade',
            ),
            'showsubmittedfirst' => true,
        ),
        'assign28on' => array(
            'defaultTime' => 'duedate',
            'actions' => array(
                'submitted'    => "SELECT id
                                     FROM {assign_submission}
                                    WHERE assignment = :eventid
                                      AND userid = :userid
                                      AND latest = 1
                                      AND status = 'submitted'",
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'marked'       => "SELECT a.grade AS finalgrade
                                     FROM {assign_grades} a, {assign_submission} s
                                    WHERE s.assignment = :eventid
                                      AND s.userid = :userid
                                      AND s.latest = 1
                                      AND a.assignment = s.assignment
                                      AND a.userid = s.userid
                                      AND a.attemptnumber = s.attemptnumber
                                      AND a.grade IS NOT NULL
                                    UNION
                                   SELECT g.finalgrade
                                     FROM {grade_items} i, {grade_grades} g
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passed'       => "SELECT a.grade AS finalgrade, i.gradepass
                                     FROM {assign_grades} a, {assign_submission} s, {grade_items} i
                                    WHERE s.assignment = :eventid
                                      AND s.userid = :userid
                                      AND s.latest = 1
                                      AND a.assignment = s.assignment
                                      AND a.userid = s.userid
                                      AND a.attemptnumber = s.attemptnumber
                                      AND a.grade IS NOT NULL
                                      AND i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid1
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_items} i, {grade_grades} g
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid2
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT a.grade
                                     FROM {assign_grades} a, {assign_submission} s, {grade_items} i
                                    WHERE s.assignment = :eventid
                                      AND s.userid = :userid
                                      AND s.latest = 1
                                      AND a.assignment = s.assignment
                                      AND a.userid = s.userid
                                      AND a.attemptnumber = s.attemptnumber
                                      AND a.grade IS NOT NULL
                                      AND i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid1
                                      AND a.grade >= i.gradepass
                                    UNION
                                   SELECT g.finalgrade
                                     FROM {grade_items} i, {grade_grades} g
                                    WHERE i.itemmodule = 'assign'
                                      AND i.iteminstance = :eventid2
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
            ),
            'defaultAction' => 'marked',
            'alternatelink' => array(
                'url' => '/mod/assign/view.php?id=:cmid&action=grading',
                'capability' => 'mod/assign:grade',
            ),
            'showsubmittedfirst' => true,
        ),
        'assignment' => array(
            'defaultTime' => 'timedue',
            'actions' => array(
                'submitted'    => "SELECT id
                                     FROM {assignment_submissions}
                                    WHERE assignment = :eventid
                                      AND userid = :userid
                                      AND (
                                          numfiles >= 1
                                          OR {$DB->sql_compare_text('data2')} <> ''
                                      )",
                'marked'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assignment'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assignment'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assignment'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'assignment'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'submitted'
        ),
        'bigbluebuttonbn' => array(
            'defaultTime' => 'openingtime',
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'bigbluebuttonbn'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_bigbluebuttonbn'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'recordingsbn' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'recordingsbn'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_recordingsbn'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'book' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'book'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_book'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'certificate' => array(
            'actions' => array(
                'awarded'      => "SELECT id
                                     FROM {certificate_issues}
                                    WHERE certificateid = :eventid
                                      AND userid = :userid"
            ),
            'defaultAction' => 'awarded'
        ),
        'chat' => array(
            'actions' => array(
                'posted_to'    => "SELECT id
                                     FROM {chat_messages}
                                    WHERE chatid = :eventid
                                      AND userid = :userid"
            ),
            'defaultAction' => 'posted_to'
        ),
        'choice' => array(
            'defaultTime' => 'timeclose',
            'actions' => array(
                'answered'     => "SELECT id
                                     FROM {choice_answers}
                                    WHERE choiceid = :eventid
                                      AND userid = :userid"
            ),
            'defaultAction' => 'answered'
        ),
        'data' => array(
            'defaultTime' => 'timeviewto',
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'data'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_data'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'dmelearn' => array(
            'actions' => array(
                'finished'     => "SELECT id
                                     FROM {dmelearn_entries}
                                    WHERE dmelearn = :eventid
                                      AND grade >= 100
                                      AND userid = :userid"
            ),
            'defaultAction' => 'finished'
        ),
        'equella' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'equella'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_equella'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'feedback' => array(
            'defaultTime' => 'timeclose',
            'actions' => array(
                'responded_to' => "SELECT id
                                     FROM {feedback_completed}
                                    WHERE feedback = :eventid
                                      AND userid = :userid"
            ),
            'defaultAction' => 'responded_to',
            'alternatelink' => array(
                // Breaks if anonymous feedback is collected.
                'url' => '/mod/feedback/show_entries.php?id=:cmid&do_show=showoneentry&userid=:userid',
                'capability' => 'mod/feedback:viewreports',
            ),
        ),
        'resource' => array(  // AKA file.
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'resource'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_resource'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'flashcardtrainer' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'flashcardtrainer'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_flashcardtrainer'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'folder' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'folder'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_folder'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'forum' => array(
            'actions' => array(
                'posted_to'    => "SELECT id
                                     FROM {forum_posts}
                                    WHERE userid = :userid AND discussion IN (
                                          SELECT id
                                            FROM {forum_discussions}
                                           WHERE forum = :eventid
                                    )"
            ),
            'defaultAction' => 'posted_to'
        ),
        'geogebra' => array(
            'defaultTime' => 'timedue',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {geogebra_attempts}
                                    WHERE geogebra = :eventid
                                      AND userid = :userid",
                'finished'    => "SELECT id
                                     FROM {geogebra_attempts}
                                    WHERE geogebra = :eventid
                                      AND userid = :userid
                                      AND finished = 1"
            ),
            'defaultAction' => 'finished'
        ),
        'glossary' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'glossary'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_glossary'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'hotpot' => array(
            'defaultTime' => 'timeclose',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {hotpot_attempts}
                                    WHERE hotpotid = :eventid
                                      AND userid = :userid",
                'finished'     => "SELECT id
                                     FROM {hotpot_attempts}
                                    WHERE hotpotid = :eventid
                                      AND userid = :userid
                                      AND timefinish <> 0",
            ),
            'defaultAction' => 'finished'
        ),
        'hsuforum' => array(
            'defaultTime' => 'assesstimefinish',
            'actions' => array(
                'posted_to'    => "SELECT id
                                     FROM {hsuforum_posts}
                                    WHERE userid = :userid AND discussion IN (
                                          SELECT id
                                            FROM {hsuforum_discussions}
                                           WHERE forum = :eventid
                                    )"
            ),
            'defaultAction' => 'posted_to'
        ),
        'imscp' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'imscp'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_imscp'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'journal' => array(
            'actions' => array(
                'posted_to'    => "SELECT id
                                     FROM {journal_entries}
                                    WHERE journal = :eventid
                                      AND userid = :userid"
            ),
            'defaultAction' => 'posted_to'
        ),
        'jclic' => array(
            'defaultTime' => 'timedue',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {jclic_sessions}
                                    WHERE jclicid = :eventid
                                      AND user_id = :userid"
            ),
            'defaultAction' => 'attempted'
        ),
        'lesson' => array(
            'defaultTime' => 'deadline',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {lesson_attempts}
                                    WHERE lessonid = :eventid
                                      AND userid = :userid
                                UNION ALL
                                   SELECT id
                                     FROM {lesson_branch}
                                    WHERE lessonid = :eventid1
                                      AND userid = :userid1",
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'lesson'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'lesson'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'lesson'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'lesson'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'attempted',
            'alternatelink' => array(
                'url' => '/mod/lesson/report.php?id=:cmid&action=reportdetail&userid=:userid',
                'capability' => 'mod/lesson:viewreports',
            ),
        ),
        'lti' => array(
            'actions' => array(
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'lti'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'lti'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_lti'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'graded'
        ),
        'ouwiki' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'ouwiki'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_ouwiki'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'page' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'page'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_page'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'panopto' => array(
            'actions' => array(
               'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'panopto'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_panopto'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'questionnaire' => array(
            'defaultTime' => 'closedate',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {questionnaire_attempts}
                                    WHERE qid = :eventid
                                      AND userid = :userid",
                'finished'     => "SELECT id
                                     FROM {questionnaire_response}
                                    WHERE complete = 'y'
                                      AND username = :userid
                                      AND survey_id = :eventid",
            ),
            'defaultAction' => 'finished'
        ),
        'quiz' => array(
            'defaultTime' => 'timeclose',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {quiz_attempts}
                                    WHERE quiz = :eventid
                                      AND userid = :userid",
                'finished'     => "SELECT id
                                     FROM {quiz_attempts}
                                    WHERE quiz = :eventid
                                      AND userid = :userid
                                      AND timefinish <> 0",
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'quiz'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'quiz'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'quiz'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'quiz'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'finished',
            'alternatelink' => array(
                'url' => '/mod/quiz/report.php?id=:cmid&mode=overview',
                'capability' => 'mod/quiz:viewreports',
            ),
        ),
        'scorm' => array(
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {scorm_scoes_track}
                                    WHERE scormid = :eventid
                                      AND userid = :userid",
                'completed'    => "SELECT id
                                     FROM {scorm_scoes_track}
                                    WHERE scormid = :eventid
                                      AND userid = :userid
                                      AND element = 'cmi.core.lesson_status'
                                      AND {$DB->sql_compare_text('value')} = 'completed'",
                'passedscorm'  => "SELECT id
                                     FROM {scorm_scoes_track}
                                    WHERE scormid = :eventid
                                      AND userid = :userid
                                      AND element = 'cmi.core.lesson_status'
                                      AND {$DB->sql_compare_text('value')} = 'passed'"
            ),
            'defaultAction' => 'attempted',
            'alternatelink' => array(
                'url' => '/mod/scorm/report/userreport.php?id=:cmid&user=:userid',
                'capability' => 'mod/scorm:viewreport',
            ),
        ),
        'subcourse' => array(
            'actions' => array(
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'subcourse'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'subcourse'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'subcourse'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'subcourse'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'graded'
        ),
        'survey' => array(
            'actions' => array(
                'submitted'    => "SELECT id
                                     FROM {survey_answers}
                                    WHERE survey = :eventid
                                      AND userid = :userid"
            ),
            'defaultAction' => 'submitted'
        ),
        'turnitintool' => array(
            'defaultTime' => 'defaultdtdue',
            'actions' => array(
                'submitted'    => "SELECT id
                                     FROM {turnitintool_submissions}
                                    WHERE turnitintoolid = :eventid
                                      AND userid = :userid
                                      AND submission_score IS NOT NULL"
            ),
            'defaultAction' => 'submitted'
        ),
        'turnitintooltwo' => array(
            'defaultTime' => 'defaultdtdue',
            'actions' => array(
                'submitted'     => "SELECT id
                                      FROM {turnitintooltwo_submissions}
                                     WHERE turnitintooltwoid = :eventid
                                       AND userid = :userid
                                       AND submission_score IS NOT NULL"
            ),
            'defaultAction' => 'submitted'
        ),
        'url' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'url'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_url'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'video' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'video'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_video'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'videoassessment' => array(
            'defaultTime' => 'timedue',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {videoassessment_video_assocs}
                                    WHERE videoassessment = :eventid
                                      AND associationid = :userid",
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'videoassessment'
                                      AND i.iteminstance = :eventid
                                      AND i.itemnumber = 0
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'videoassessment'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'videoassessment'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'videoassessment'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'attempted'
        ),
        'vpl' => array(
            'defaultTime' => 'duedate',
            'actions' => array(
                'attempted'    => "SELECT id
                                     FROM {vpl_submissions}
                                    WHERE vpl = :eventid
                                      AND userid = :userid",
                'marked'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'vpl'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
                'passed'       => "SELECT g.finalgrade, i.gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'vpl'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND g.finalgrade IS NOT NULL
                                    UNION
                                   SELECT 100 AS finalgrade, 0 AS gradepass
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'vpl'
                                      AND i.iteminstance = :eventid1
                                      AND i.id = g.itemid
                                      AND g.userid = :userid1
                                      AND g.excluded <> 0",
                'passedby'     => "SELECT g.finalgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'vpl'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND ((g.finalgrade IS NOT NULL AND g.finalgrade >= i.gradepass) OR g.excluded <> 0)",
            ),
            'defaultAction' => 'marked'
        ),
        'wiki' => array(
            'actions' => array(
                'viewed' => array (
                    'logstore_legacy'     => "SELECT id
                                                FROM {log}
                                               WHERE course = :courseid
                                                 AND module = 'wiki'
                                                 AND action = 'view'
                                                 AND cmid = :cmid
                                                 AND userid = :userid",
                    'sql_internal_reader' => "SELECT id
                                                FROM {log}
                                               WHERE courseid = :courseid
                                                 AND component = 'mod_wiki'
                                                 AND action = 'viewed'
                                                 AND objectid = :eventid
                                                 AND userid = :userid",
                ),
            ),
            'defaultAction' => 'viewed'
        ),
        'workshop' => array(
            'defaultTime' => 'assessmentend',
            'actions' => array(
                'submitted'    => "SELECT id
                                     FROM {workshop_submissions}
                                    WHERE workshopid = :eventid
                                      AND authorid = :userid",
                'assessed'     => "SELECT s.id
                                     FROM {workshop_assessments} a, {workshop_submissions} s
                                    WHERE s.workshopid = :eventid
                                      AND s.id = a.submissionid
                                      AND a.reviewerid = :userid
                                      AND a.grade IS NOT NULL",
                'graded'       => "SELECT g.rawgrade
                                     FROM {grade_grades} g, {grade_items} i
                                    WHERE i.itemmodule = 'workshop'
                                      AND i.iteminstance = :eventid
                                      AND i.id = g.itemid
                                      AND g.userid = :userid
                                      AND (g.finalgrade IS NOT NULL OR g.excluded <> 0)",
            ),
            'defaultAction' => 'graded',
            'showsubmittedfirst' => true,
        ),
    );

    if ($CFG->version >= 2014072400) {
        $modules['assign'] = $modules['assign28on'];
    }
    unset($modules['assign28on']);

    if ($CFG->version > 2015111604) {
        $modules['assign']['alternatelink']['url'] = '/mod/assign/view.php?id=:cmid&action=grade&userid=:userid';
    }

    return $modules;
}

/**
 * Checks if a variable has a value and returns a default value if it doesn't
 *
 * @param mixed $var The variable to check
 * @param mixed $def Default value if $var is not set
 * @return string
 */
function custom_progress_default_value(&$var, $def = null) {
    return isset($var) ? $var : $def;
}

/**
 * Filters the modules list to those installed in Moodle instance and used in current course
 *
 * @return array
 */
function block_custom_progress_modules_in_use($course) {
    global $DB;

    $dbmanager = $DB->get_manager(); // Used to check if tables exist.
    $modules = block_custom_progress_monitorable_modules();
    $modulesinuse = array();

    foreach ($modules as $module => $details) {
        if (
            $dbmanager->table_exists($module) &&
            $DB->record_exists($module, array('course' => $course))
        ) {
            $modulesinuse[$module] = $details;
        }
    }
    return $modulesinuse;
}

/**
 * Gets event information about modules monitored by an instance of a Progress Bar block
 *
 * @param stdClass $config  The block instance configuration values
 * @param array    $modules The modules used in the course
 * @param stdClass $course  The current course
 * @param int      $userid  The user's ID
 * @return mixed   returns array of visible events monitored,
 *                 empty array if none of the events are visible,
 *                 null if all events are configured to "no" monitoring and
 *                 0 if events are available but no config is set
 */
function block_custom_progress_event_information($config, $modules, $course, $userid = 0) {
    global $DB, $USER;

    $dbmanager = $DB->get_manager(); // Used to check if fields exist.
    $events = array();
    $numevents = 0;
    $numeventsconfigured = 0;

    if ($userid === 0) {
        $userid = $USER->id;
    }

    // Get section information for the course module layout.
    $sections = block_custom_progress_course_sections($course);

    // Check each known module (described in lib.php).
    foreach ($modules as $module => $details) {
        $fields = 'id, name';
        if (
            array_key_exists('defaultTime', $details) &&
            $dbmanager->field_exists($module, $details['defaultTime'])
        ) {
            $fields .= ', '.$details['defaultTime'].' as due';
        }

        // Check if this type of module is used in the course, gather instance info.
        $records = $DB->get_records($module, array('course' => $course), '', $fields);
        foreach ($records as $record) {

            // Is the module being monitored?
            if (isset($config->{'monitor_'.$module.$record->id})) {
                $numeventsconfigured++;
            }
            if (custom_progress_default_value($config->{'monitor_'.$module.$record->id}, 0) == 1) {
                $numevents++;
                // Check the time the module is due.
                if (
                    isset($details['defaultTime']) &&
                    $record->due != 0 &&
                    custom_progress_default_value($config->{'locked_'.$module.$record->id}, 0)
                ) {
                    $expected = custom_progress_default_value($record->due);
                } else {
                    $expected = $config->{'date_time_'.$module.$record->id};
                }

                // Gather together module information.
                $coursemodule = block_custom_progress_get_coursemodule($module, $record->id, $course);
                $events[] = array(
                    'expected' => $expected,
                    'type'     => $module,
                    'id'       => $record->id,
                    'name'     => format_string($record->name),
                    'cm'       => $coursemodule,
                    'section'  => $sections[$coursemodule->section]->section,
                    'position' => array_search($coursemodule->id, $sections[$coursemodule->section]->sequence),
                );
            }
        }
    }

    if ($numeventsconfigured == 0) {
        return 0;
    }
    if ($numevents == 0) {
        return null;
    }

    // Sort by first value in each element, which is time due.
    if (isset($config->orderby) && $config->orderby == 'orderbycourse') {
        usort($events, 'block_custom_progress_compare_events');
    } else {
        usort($events, 'block_custom_progress_compare_times');
    }
    return $events;
}

/**
 * Used to compare two activities/resources based on order on course page
 *
 * @param array $a array of event information
 * @param array $b array of event information
 * @return <0, 0 or >0 depending on order of activities/resources on course page
 */
function block_custom_progress_compare_events($a, $b) {
    if ($a['section'] != $b['section']) {
        return $a['section'] - $b['section'];
    } else {
        return $a['position'] - $b['position'];
    }
}

/**
 * Used to compare two activities/resources based their expected completion times
 *
 * @param array $a array of event information
 * @param array $b array of event information
 * @return <0, 0 or >0 depending on time then order of activities/resources
 */
function block_custom_progress_compare_times($a, $b) {
    if ($a['expected'] != $b['expected']) {
        return $a['expected'] - $b['expected'];
    } else {
        return block_custom_progress_compare_events($a, $b);
    }
}

/**
 * Checked if a user has attempted/viewed/etc. an activity/resource
 *
 * @param array    $modules  The modules used in the course
 * @param stdClass $config   The blocks configuration settings
 * @param array    $events   The possible events that can occur for modules
 * @param int      $userid   The user's id
 * @param int      $course   The course ID
 * @return array   an describing the user's attempts based on module+instance identifiers
 */
function block_custom_progress_attempts($modules, $config, $events, $userid, $course) {
    global $DB;
    $attempts = array();
    $modernlogging = false;
    $cachingused = false;

    // Get readers for 2.7 onwards.
    if (function_exists('get_log_manager')) {
        $modernlogging = true;
        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers();
        $numreaders = count($readers);
    }

    // Get cache store if caching is working 2.4 onwards.
    if (class_exists('cache')) {
        $cachingused = true;
        $cachedlogs = cache::make('block_custom_progress', 'cachedlogs');
        $cachedlogviews = $cachedlogs->get($userid);
        if (empty($cachedlogviews)) {
            $cachedlogviews = array();
        }
        $cachedlogsupdated = false;
    }

    foreach ($events as $event) {
        $module = $modules[$event['type']];
        $uniqueid = $event['type'].$event['id'];
        $parameters = array('courseid' => $course, 'courseid1' => $course, 'courseid2' => $course,
                            'userid' => $userid, 'userid1' => $userid, 'userid2' => $userid,
                            'eventid' => $event['id'], 'eventid1' => $event['id'], 'eventid2' => $event['id'],
                            'cmid' => $event['cm']->id, 'cmid1' => $event['cm']->id, 'cmid2' => $event['cm']->id,
                      );

        // Check for passing grades as unattempted, passed or failed.
        if (isset($config->{'action_'.$uniqueid}) && $config->{'action_'.$uniqueid} == 'passed') {
            $query = $module['actions'][$config->{'action_'.$uniqueid}];
            $graderesult = $DB->get_record_sql($query, $parameters);
            if ($graderesult === false || $graderesult->finalgrade === null) {
                $attempts[$uniqueid] = false;
            } else {
                $attempts[$uniqueid] = $graderesult->finalgrade >= $graderesult->gradepass ? true : 'failed';
            }
        }

        // Checked view actions in the log table/store/cache.
        else if (isset($config->{'action_'.$uniqueid}) && $config->{'action_'.$uniqueid} == 'viewed') {
            $attempts[$uniqueid] = false;

            // Check if the value is cached.
            if ($cachingused && array_key_exists($uniqueid, $cachedlogviews) && $cachedlogviews[$uniqueid]) {
                $attempts[$uniqueid] = true;
            }

            // Check in the logs.
            else {
                if ($modernlogging) {
                    foreach ($readers as $logstore => $reader) {
                        if (
                            $reader instanceof \core\log\sql_internal_table_reader ||
                            $reader instanceof \core\log\sql_internal_reader
                        ) {
                            $logtable = '{'.$reader->get_internal_log_table_name().'}';
                            $query = preg_replace('/\{log\}/', $logtable, $module['actions']['viewed']['sql_internal_reader']);
                        } else if ($reader instanceof logstore_legacy\log\store) {
                            $query = $module['actions']['viewed']['logstore_legacy'];
                        } else {
                            // No logs available.
                            continue;
                        }
                        $attempts[$uniqueid] = $DB->record_exists_sql($query, $parameters) ? true : false;
                        if ($attempts[$uniqueid]) {
                            $cachedlogviews[$uniqueid] = true;
                            $cachedlogsupdated = true;
                            break;
                        }
                    }
                } else {
                    $query = $module['actions']['viewed']['logstore_legacy'];
                    $attempts[$uniqueid] = $DB->record_exists_sql($query, $parameters) ? true : false;
                    if ($cachingused && $attempts[$uniqueid]) {
                        $cachedlogviews[$uniqueid] = true;
                        $cachedlogsupdated = true;
                    }
                }
            }
        } else {

            // If activity completion is used, check completions table.
            if (isset($config->{'action_'.$uniqueid}) && $config->{'action_'.$uniqueid} == 'activity_completion') {
                $query = 'SELECT id
                            FROM {course_modules_completion}
                           WHERE userid = :userid
                             AND coursemoduleid = :cmid
                             AND completionstate >= 1';
            }

            // Determine the set action and develop a query.
            else {
                $action = isset($config->{'action_'.$uniqueid}) ?
                          $config->{'action_'.$uniqueid} :
                          $module['defaultAction'];
                $query = $module['actions'][$action];
            }

            // Check if the user has attempted the module.
            $attempts[$uniqueid] = $DB->record_exists_sql($query, $parameters) ? true : false;

        }

        // Check if activity requires submission first.
        if (
            array_key_exists('showsubmittedfirst', $module) &&
            $module['showsubmittedfirst'] &&
            array_key_exists('submitted', $module['actions']) &&
            isset($config->{'action_'.$uniqueid}) &&
            $config->{'action_'.$uniqueid} != 'submitted' &&
            (!isset($config->{'showsubmitted_'.$uniqueid}) || $config->{'showsubmitted_'.$uniqueid}) &&
            $attempts[$uniqueid] !== true &&
            $attempts[$uniqueid] !== 'failed'
        ) {
            $query = $module['actions']['submitted'];
            $submitted = $DB->record_exists_sql($query, $parameters) ? true : false;
            if ($submitted) {
                $attempts[$uniqueid] = 'submitted';
            }
        }
    }

    // Update log cache if new values were added.
    if ($cachingused && $cachedlogsupdated) {
        $cachedlogs->set($userid, $cachedlogviews);
    }

    return $attempts;
}

/**
 * Draws a custom_progress bar
 *
 * @param array    $modules  The modules used in the course
 * @param stdClass $config   The blocks configuration settings
 * @param array    $events   The possible events that can occur for modules
 * @param int      $userid   The user's id
 * @param int      instance  The block instance (in case more than one is being displayed)
 * @param array    $attempts The user's attempts on course activities
 * @return string  Progress Bar HTML content
 */
function block_custom_progress_bar($modules, $config, $events, $userid, $instance, $attempts, $course) {
    global $OUTPUT, $CFG, $USER;
    $content = '';
    $usingrtl = right_to_left();
    $numevents = count($events);
    
    $percent= block_custom_progress_percentage($events, $attempts);
    $content = '';
  

    // Add the percentage below the custom_progress bar.
    $content .= html_writer::start_tag('div', array('class' => 'block_custom_progress-level-progress'));
    $content .= html_writer::tag('div', '', array('style' => 'width: ' .  $percent . '%;', 'class' => 'bar'));
    $content .= html_writer::tag('div', $percent."% ", array('class' => 'txt'));
    $content .= html_writer::end_tag('div');
       

    return $content;
}

/**
 * Draws a custom_progress badge
 *
 * @param array    $modules  The modules used in the course
 * @param stdClass $config   The blocks configuration settings
 * @param array    $events   The possible events that can occur for modules
 * @param int      $userid   The user's id
 * @param int      instance  The block instance (in case more than one is being displayed)
 * @param array    $attempts The user's attempts on course activities
 * @return string  Progress Bar HTML content
 */
function block_custom_progress_badge($modules, $config, $events, $userid, $instance, $attempts, $course) {
    global $OUTPUT, $CFG, $USER;
    $content = '';
    $usingrtl = right_to_left();
    $numevents = count($events);
    $percent= block_custom_progress_percentage($events, $attempts);
    $defaultlevels = get_config('block_custom_progress', 'levels') ?: 10;
    $levels = isset($config->levels) ? $config->levels : $defaultlevels;
    $user_level= (int)round($percent / $levels);
    $defaultenablecustomlevelpix = get_config('block_custom_progress', 'enablecustomlevelpix') ?: DEFAULT_ENABLE_CUSTOM_PIX;
    $enablecustomlevelpix = isset($config->enablecustomlevelpix) ? $config->enablecustomlevelpix : DEFAULT_ENABLE_CUSTOM_PIX;
    $content = '';
    if (($enablecustomlevelpix != 0)&&($config->enablecustom)){
        $content .= html_writer::tag('div',
            html_writer::empty_tag('img', array('src' => moodle_url::make_pluginfile_url(block_custom_progress_get_block_context($instance)->id, 'block_custom_progress',
            'badges', 0, '/', $user_level))),
            array('class' => 'level-badge current-level level-' . $user_level)
        );
    }elseif ($defaultenablecustomlevelpix != 0){
        $content .= html_writer::tag('div',
            html_writer::empty_tag('img', array('src' => moodle_url::make_pluginfile_url(1, 'block_custom_progress',
            'preset', 0, '/', $user_level))),
            array('class' => 'level-badge current-level level-' . $user_level)
        );
    }else{
        $content .= html_writer::tag('div', $user_level, array('class' => 'current-level level-' . $user_level));
    }
    
    return $content;
}

/**
 * Calculates an overall percentage of custom_progress
 *
 * @param array $events   The possible events that can occur for modules
 * @param array $attempts The user's attempts on course activities
 * @return int  Progress value as a percentage
 */
function block_custom_progress_percentage($events, $attempts) {
    $attemptcount = 0;

    foreach ($events as $event) {
        if ($attempts[$event['type'].$event['id']] == 1) {
            $attemptcount++;
        }
    }

    $custom_progressvalue = $attemptcount == 0 ? 0 : $attemptcount / count($events);

    return (int)round($custom_progressvalue * 100);
}

/**
 * Gathers the course section and activity/resource information for ordering
 *
 * @return array section information
 */
function block_custom_progress_course_sections($course) {
    global $DB;

    $sections = $DB->get_records('course_sections', array('course' => $course), 'section', 'id,section,name,sequence');
    foreach ($sections as $key => $section) {
        if ($section->sequence != '') {
            $sections[$key]->sequence = explode(',', $section->sequence);
        } else {
            $sections[$key]->sequence = null;
        }
    }

    return $sections;
}

/**
 * Filters events that a user cannot see due to grouping constraints
 *
 * @param array  $events The possible events that can occur for modules
 * @param array  $userid The user's id
 * @param string $coursecontext the context value of the course
 * @param string $course the course for filtering visibility
 * @return array The array with restricted events removed
 */
function block_custom_progress_filter_visibility($events, $userid, $coursecontext, $course = 0) {
    global $CFG, $USER;
    $filteredevents = array();

    // Check if the events are empty or none are selected.
    if ($events === 0) {
        return 0;
    }
    if ($events === null) {
        return null;
    }

    // Keep only events that are visible.
    foreach ($events as $key => $event) {

        // Determine the correct user info to check.
        if ($userid == $USER->id) {
            $coursemodule = $event['cm'];
        } else {
            $coursemodule = block_custom_progress_get_coursemodule($event['type'], $event['id'], $course->id, $userid);
        }

        // Check visibility in course.
        if (!$coursemodule->visible && !has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)) {
            continue;
        }

        // Check availability, allowing for visible, but not accessible items.
        if (!empty($CFG->enableavailability)) {
            if (
                isset($coursemodule->available) && !$coursemodule->available && empty($coursemodule->availableinfo) &&
                !has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid)
            ) {
                continue;
            }
        }
        // Check visibility by grouping constraints (includes capability check).
        if (!empty($CFG->enablegroupmembersonly)) {
            if (isset($coursemodule->uservisible)) {
                if ($coursemodule->uservisible != 1 && empty($coursemodule->availableinfo)) {
                    continue;
                }
            } else if (!groups_course_module_visible($coursemodule, $userid)) {
                continue;
            }
        }

        // Save the visible event.
        $filteredevents[] = $event;
    }
    return $filteredevents;
}

/**
 * Checks whether the current page is the My home page.
 *
 * @return bool True when on the My home page.
 */
function block_custom_progress_on_site_page() {
    global $SCRIPT, $COURSE;

    return $SCRIPT === '/my/index.php' || $COURSE->id == 1;
}

/**
 * Gets the course context, allowing for old and new Moodle instances.
 *
 * @param int $courseid The course ID
 * @return stdClass The context object
 */
function block_custom_progress_get_course_context($courseid) {
    if (class_exists('context_course')) {
        return context_course::instance($courseid);
    } else {
        return get_context_instance(CONTEXT_COURSE, $courseid);
    }
}

/**
 * Gets the block context, allowing for old and new Moodle instances.
 *
 * @param int $block The block ID
 * @return stdClass The context object
 */
function block_custom_progress_get_block_context($blockid) {
    if (class_exists('context_block')) {
        return context_block::instance($blockid);
    } else {
        return get_context_instance(CONTEXT_BLOCK, $blockid);
    }
}

/**
 * Gets the course module in a backwards compatible way.
 *
 * @param int $module   the type of module (eg, assign, quiz...)
 * @param int $recordid the instance ID (from its table)
 * @param int $courseid the course ID
 * @return stdClass The course module object
 */
function block_custom_progress_get_coursemodule($module, $recordid, $courseid, $userid = 0) {
    global $CFG;

    if (function_exists('get_fast_modinfo')) {
        return get_fast_modinfo($courseid, $userid)->instances[$module][$recordid];
    } else {
        return get_coursemodule_from_instance($module, $recordid, $courseid);
    }
}

/**
 * File serving.
 *
 * @param stdClass $course The course object.
 * @param stdClass $bi Block instance record.
 * @param context $context The context object.
 * @param string $filearea The file area.
 * @param array $args List of arguments.
 * @param bool $forcedownload Whether or not to force the download of the file.
 * @param array $options Array of options.
 * @return void|false
 */
function block_custom_progress_pluginfile($course, $bi, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $CFG;
    

    $fs = get_file_storage();
    $file = null;

    if (($filearea == 'badges')||($filearea == 'preset') ){
        // For performance reason, and very low risk, we do not restrict the access to the level badges
        // to the participant of the course, nor do we check if they have the required level, etc...
        $itemid = array_shift($args);
        $filename = array_shift($args);
        $filepath = '/';
        $file = $fs->get_file($context->id, 'block_custom_progress', $filearea, $itemid, $filepath, $filename . '.png');
        if (!$file) {
            $file = $fs->get_file($context->id, 'block_custom_progress', $filearea, $itemid, $filepath, $filename . '.jpg');
        }
    }

    if (!$file) {
        return false;
    }

    send_stored_file($file);
}
