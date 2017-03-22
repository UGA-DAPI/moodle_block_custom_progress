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
 * Progress Bar block configuration form definition
 *
 * @package    contrib
 * @subpackage block_custom_progress
 * @copyright  2010 Michael de Raadt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot.'/blocks/custom_progress/lib.php');

/**
 * Progress Bar block config form class
 *
 * @copyright 2010 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_custom_progress_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $CFG, $COURSE, $DB, $OUTPUT, $SCRIPT;
        $loggingenabled = true;

        // The My home version is not configurable.
        if (block_custom_progress_on_site_page()) {
            return;
        }

        // Check that logging is enabled in 2.7 onwards.
        if (function_exists('get_log_manager')) {
            $logmanager = get_log_manager();
            $readers = $logmanager->get_readers();
            $loggingenabled = !empty($readers);
            if (!$loggingenabled) {
                $warningstring = get_string('config_warning_logstores', 'block_custom_progress');
                $warning = $OUTPUT->notification($warningstring);
                $mform->addElement('html', $warning);
            }
        }

        // Check that logs will be available during course.
        if (isset($CFG->loglifetime) && $CFG->loglifetime > 0) {
            $warningstring = get_string('config_warning_loglifetime', 'block_custom_progress', $CFG->loglifetime);
            $warning = HTML_WRITER::tag('div', $warningstring, array('class' => 'warning custom_progressWarningBox'));
            $mform->addElement('html', $warning);
        }

        $turnallon = optional_param('turnallon', 0, PARAM_INT);
        $dbmanager = $DB->get_manager(); // Loads ddl manager and xmldb classes.
        $count = 0;
        $usingweeklyformat = $COURSE->format == 'weeks' || $COURSE->format == 'weekscss' ||
                             $COURSE->format == 'weekcoll';

        // Start block specific section in config form.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Set Progress block instance title.
        $mform->addElement('text', 'config_custom_progressTitle',
                           get_string('config_title', 'block_custom_progress'));
        $mform->setDefault('config_custom_progressTitle', '');
        $mform->setType('config_custom_progressTitle', PARAM_MULTILANG);
        $mform->addHelpButton('config_custom_progressTitle', 'why_set_the_title', 'block_custom_progress');

        
        $mform->addElement('header', 'custom', get_string('config_header_custom', 'block_custom_progress'));
    
        $mform->addElement('selectyesno', 'config_enablecustom', get_string('usecustom', 'block_custom_progress'));
        $mform->addHelpButton('config_enablecustom', 'usecustom', 'block_custom_progress');
        $mform->setDefault('config_enablecustom', false);
        
        $mform->addElement('text', 'config_levels', get_string('levels', 'block_custom_progress'));
        $mform->disabledIf('config_levels', 'config_enablecustom', 'eq', 0);
        //$mform->addRule('config_levels', get_string('required'), 'required');
        $mform->setType('config_levels', PARAM_INT);

        
        $mform->addElement('selectyesno', 'config_enablecustomlevelpix', get_string('usecustomlevelpix', 'block_custom_progress'));
        $mform->addHelpButton('config_enablecustomlevelpix', 'usecustomlevelpix', 'block_custom_progress');
        $mform->disabledIf('config_enablecustomlevelpix', 'config_enablecustom', 'eq', 0);
        $mform->setDefault('config_enablecustomlevelpix', false);
        $fmoptions = array('subdirs' => 0,'maxfiles' => 20, 'accepted_types' => array('.jpg', '.png'));
        $mform->addElement('filemanager', 'config_levels_pix', get_string('levels_pix', 'block_custom_progress'), null, $fmoptions);
        $mform->disabledIf('config_levels_pix', 'config_enablecustomlevelpix', 'eq', 0);
        $mform->addElement('static', '', '', get_string('levels_pix_desc', 'block_custom_progress'));
        // Get course section information.
        // Allow the block to be visible to a single group.
        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($groups)) {
            $groupsmenu = array();
            $groupsmenu[0] = get_string('allparticipants');
            foreach ($groups as $group) {
                $groupsmenu[$group->id] = format_string($group->name);
            }
            $grouplabel = get_string('config_group', 'block_custom_progress');
            $mform->addElement('select', 'config_group', $grouplabel, $groupsmenu);
            $mform->setDefault('config_group', '0');
            $mform->addHelpButton('config_group', 'how_group_works', 'block_custom_progress');
        }

        $sections = block_custom_progress_course_sections($COURSE->id);

        // Determine the time at the end of the week, less 5min.
        if (!$usingweeklyformat) {
            $currenttime = time();
            $timearray = localtime($currenttime, true);
            $endofweektimearray = localtime($currenttime + (7 - $timearray['tm_wday']) * 86400, true);
            $endofweektime = mktime(23,
                                    55,
                                    0,
                                    $endofweektimearray['tm_mon'] + 1,
                                    $endofweektimearray['tm_mday'],
                                    $endofweektimearray['tm_year'] + 1900);
        }

        // Go through each type of activity/resource that can be monitored to find instances in the course.
        $modules = block_custom_progress_monitorable_modules();
        $unsortedmodules = array();
        foreach ($modules as $module => $details) {

            // Get data about instances of activities/resources of this type in this course.
            unset($instances);
            if ($dbmanager->table_exists($module)) {
                $sql = 'SELECT id, name';
                if ($module == 'assignment') {
                    $sql .= ', assignmenttype';
                }
                if (
                    array_key_exists('defaultTime', $details) &&
                    $dbmanager->field_exists($module, $details['defaultTime'])
                ) {
                    $sql .= ', '.$details['defaultTime'].' as due';
                }
                $sql .= ' FROM {'.$module.'} WHERE course=\''.$COURSE->id.'\' ORDER BY name';
                $instances = $DB->get_records_sql($sql);
            }

            // If there are instances of activities/resources of this type, get more info about them.
            if (!empty($instances)) {
                foreach ($instances as $i => $instance) {
                    $count++;
                    $moduleinfo = new stdClass();
                    $moduleinfo->module = $module;
                    $moduleinfo->instanceid = $instance->id;
                    $moduleinfo->uniqueid = $module.$instance->id;
                    $moduleinfo->label = get_string($module, 'block_custom_progress');
                    $moduleinfo->instancename = $instance->name;
                    $moduleinfo->lockpossible = isset($details['defaultTime']);
                    $moduleinfo->instancedue = isset($instance->due) && ($instance->due != 0);

                    // Get position of activity/resource on course page.
                    $coursemodule = get_coursemodule_from_instance($module, $instance->id, $COURSE->id);
                    $moduleinfo->section = $coursemodule->section;
                    $moduleinfo->position = array_search($coursemodule->id, $sections[$coursemodule->section]->sequence);
                    $moduleinfo->coursemoduleid = $coursemodule->id;
                    $moduleinfo->completion = $coursemodule->completion;
                    $moduleinfo->completionexpected = $coursemodule->completionexpected;

                    // Find type labels for assignment types.
                    $asslabel = '';
                    if (isset($instance->assignmenttype)) {
                        $type = $instance->assignmenttype;
                        if (get_string_manager()->string_exists('type'.$type, 'mod_assignment')) {
                            $asslabel = get_string('type'.$type, 'assignment');
                        } else {
                            $asslabel  = get_string('type'.$type, 'assignment_'.$type);
                        }
                        $moduleinfo->label .= ' ('.$asslabel.')';
                    }

                    // Determine a time/date for a activity/resource.
                    $expected = null;
                    $datetimepropery = 'date_time_'.$module.$instance->id;
                    if (
                        isset($this->block->config) &&
                        property_exists($this->block->config, $datetimepropery)
                    ) {
                        $expected = $this->block->config->$datetimepropery;
                    }

                    // If there is a date associated with the activity/resource, use that.
                    $lockedproperty = 'locked_'.$module.$instance->id;

                    if (
                        isset($details['defaultTime']) &&
                        isset($instance->due) &&
                        $instance->due != 0 && (
                            (
                                isset($this->block->config) &&
                                property_exists($this->block->config, $lockedproperty) &&
                                $this->block->config->$lockedproperty == 1
                            ) ||
                            empty($expected)
                        )
                    ) {
                        $expected = custom_progress_default_value($instance->due);
                        if (
                            isset($this->block->config) &&
                            property_exists($this->block->config, $datetimepropery)
                        ) {
                            $this->block->config->$datetimepropery = $expected;
                            $this->block->config->$lockedproperty = 1;
                        }
                    }

                    if (empty($expected)) {

                        // If a expected date is set in the activity completion, use that.
                        if ($moduleinfo->completion != 0 && $moduleinfo->completionexpected != 0) {
                            $expected = $moduleinfo->completionexpected;
                        }

                        // If positioned in a weekly format, use 5min before end of week.
                        else if ($usingweeklyformat) {
                            $expected = $COURSE->startdate + ($moduleinfo->section > 0 ? $moduleinfo->section : 1) * 604800 - 300;
                        }

                        // Assume 5min before the end of the current week.
                        else {
                            $expected = $endofweektime;
                        }
                    }
                    $moduleinfo->expected = $expected;

                    // Get the list of possible actions for the event.
                    $actions = array();
                    foreach ($details['actions'] as $action => $sql) {

                        // Before allowing pass marks, see that Grade to pass value is set.
                        if ($action == 'passed' || $action == 'passedby') {
                            $params = array('courseid' => $COURSE->id, 'itemmodule' => $module, 'iteminstance' => $instance->id);
                            $gradetopass = $DB->get_record('grade_items', $params, 'id,gradepass', IGNORE_MULTIPLE);
                            if ($gradetopass && $gradetopass->gradepass > 0) {
                                $actions[$action] = get_string($action, 'block_custom_progress');
                            }
                        } else {
                            $actions[$action] = get_string($action, 'block_custom_progress');
                        }
                    }
                    if (!empty($CFG->enablecompletion)) {
                        if ($moduleinfo->completion != 0) {
                            $actions['activity_completion'] = get_string('activity_completion', 'block_custom_progress');
                        }
                    }
                    $moduleinfo->actions = $actions;

                    // Add the module to the array.
                    $unsortedmodules[] = $moduleinfo;
                }
            }
        }

        // Sort the array by coursemodule.
        $modulesinform = array();
        foreach ($unsortedmodules as $key => $moduleinfo) {
            $modulesinform[$moduleinfo->coursemoduleid] = $moduleinfo;
        }

        // Output the form elements for each module.
        if ($count > 0) {

            $dateselectoroptions = array(
                'optional' => false,
            );

            foreach ($sections as $i => $section) {
                if (count($section->sequence) > 0) {

                    // Output the section header.
                    $sectionname = get_string('section').':&nbsp;'.get_section_name($COURSE, $section);
                    $mform->addElement('header', 'section'.$i, format_string($sectionname));
                    if (method_exists($mform, 'setExpanded')) {
                        $mform->setExpanded('section'.$i);
                    }

                    // Display each monitorable activity/resource as a row.
                    foreach ($section->sequence as $coursemoduleid) {
                        if (array_key_exists($coursemoduleid, $modulesinform)) {
                            $moduleinfo = $modulesinform[$coursemoduleid];

                            // Start box.
                            $attributes = array('class' => 'custom_progressConfigBox');
                            $moduleboxstart = HTML_WRITER::start_tag('div', $attributes);
                            $mform->addElement('html', $moduleboxstart);

                            // Icon, module type and name.
                            $modulename = get_string('pluginname', $moduleinfo->module);
                            $attributes = array('class' => 'iconlarge activityicon');
                            $icon = $OUTPUT->pix_icon('icon', $modulename, 'mod_'.$moduleinfo->module, $attributes);
                            $text = '&nbsp;'.$moduleinfo->label.':&nbsp;'.format_string($moduleinfo->instancename);
                            $attributes = array('class' => 'custom_progressConfigModuleTitle');
                            $moduletitle = HTML_WRITER::tag('div', $icon.$text, $attributes);
                            $mform->addElement('html', $moduletitle);

                            // Allow monitoring turned on or off.
                            $mform->addElement('selectyesno', 'config_monitor_'.$moduleinfo->uniqueid,
                                               get_string('config_header_monitored', 'block_custom_progress'));
                            $mform->setDefault('config_monitor_'.$moduleinfo->uniqueid, $turnallon);
                            $mform->addHelpButton('config_monitor_'.$moduleinfo->uniqueid,
                                                  'what_does_monitored_mean', 'block_custom_progress');

                            // Allow locking turned on or off.
                            if ($moduleinfo->lockpossible && $moduleinfo->instancedue) {
                                $mform->addElement('selectyesno', 'config_locked_'.$moduleinfo->uniqueid,
                                                   get_string('config_header_locked', 'block_custom_progress'));
                                $mform->setDefault('config_locked_'.$moduleinfo->uniqueid, 1);
                                $mform->disabledif ('config_locked_'.$moduleinfo->uniqueid,
                                                    'config_monitor_'.$moduleinfo->uniqueid, 'eq', 0);
                                $mform->addHelpButton('config_locked_'.$moduleinfo->uniqueid,
                                                      'what_locked_means', 'block_custom_progress');
                            }

                            // Print the date selector.
                            $mform->addElement('date_time_selector',
                                               'config_date_time_'.$moduleinfo->uniqueid,
                                               get_string('config_header_expected', 'block_custom_progress'),
                                               $dateselectoroptions);
                            $mform->disabledif ('config_date_time_'.$moduleinfo->uniqueid,
                                                'config_locked_'.$moduleinfo->uniqueid, 'eq', 1);
                            $mform->disabledif ('config_date_time_'.$moduleinfo->uniqueid,
                                                'config_monitor_'.$moduleinfo->uniqueid, 'eq', 0);
                            $mform->disabledif('config_date_time_'.$moduleinfo->uniqueid,
                                               'config_orderby', 'eq', 'orderbycourse');
                            if ($moduleinfo->lockpossible && $moduleinfo->instancedue) {
                                $mform->disabledif('config_locked_'.$moduleinfo->uniqueid,
                                                   'config_orderby', 'eq', 'orderbycourse');
                            }
                            $mform->setDefault('config_date_time_'.$moduleinfo->uniqueid, $moduleinfo->expected);
                            $mform->addHelpButton('config_date_time_'.$moduleinfo->uniqueid,
                                                  'what_expected_by_means', 'block_custom_progress');

                            // Print the action selector for the event.
                            if (count($moduleinfo->actions) == 1) {
                                $moduleinfo->actions = array_keys($moduleinfo->actions);
                                $action = $moduleinfo->actions[0];
                                $mform->addElement('static', 'config_action_static_'.$moduleinfo->uniqueid,
                                                   get_string('config_header_action', 'block_custom_progress'),
                                                   get_string($action, 'block_custom_progress'));
                                $mform->addElement('hidden', 'config_action_'.$moduleinfo->uniqueid, $action);
                                $mform->setType('config_action_'.$moduleinfo->uniqueid, PARAM_ALPHANUMEXT);
                                $mform->addHelpButton('config_action_'.$moduleinfo->uniqueid,
                                                  'what_actions_can_be_monitored', 'block_custom_progress');
                            } else {
                                $mform->addElement('select', 'config_action_'.$moduleinfo->uniqueid,
                                                   get_string('config_header_action', 'block_custom_progress'), $moduleinfo->actions);
                                if (
                                    array_key_exists('showsubmittedfirst', $modules[$moduleinfo->module]) &&
                                    $modules[$moduleinfo->module]['showsubmittedfirst']
                                ) {
                                    $mform->addElement('selectyesno', 'config_showsubmitted_'.$moduleinfo->uniqueid,
                                                          get_string('config_header_showsubmitted', 'block_custom_progress'));
                                    $mform->setDefault('config_showsubmitted_'.$moduleinfo->uniqueid, 1);
                                    $mform->disabledif ('config_showsubmitted_'.$moduleinfo->uniqueid,
                                                        'config_action_'.$moduleinfo->uniqueid, 'eq', 'submitted');
                                    $mform->addHelpButton('config_showsubmitted_'.$moduleinfo->uniqueid,
                                                      'what_show_submitted_means', 'block_custom_progress');
                                }
                                if (
                                    (!$moduleinfo->lockpossible || $moduleinfo->instancedue == 0) &&
                                    array_key_exists('activity_completion', $moduleinfo->actions)
                                ) {
                                    $defaultaction = 'activity_completion';
                                } else {
                                    $defaultaction = $modules[$moduleinfo->module]['defaultAction'];
                                }
                                $mform->setDefault('config_action_'.$moduleinfo->uniqueid, $defaultaction);
                                $mform->disabledif ('config_action_group_'.$moduleinfo->uniqueid,
                                                    'config_monitor_'.$moduleinfo->uniqueid, 'eq', 0);
                                $mform->setDefault('config_showsubmitted_'.$moduleinfo->uniqueid, true);
                                $mform->disabledif ('config_showsubmitted_'.$moduleinfo->uniqueid,
                                                    'config_action_'.$moduleinfo->uniqueid, 'eq', 'submitted');
                                $mform->setType('config_action_'.$moduleinfo->uniqueid, PARAM_ALPHANUMEXT);
                                $mform->addHelpButton('config_action_'.$moduleinfo->uniqueid,
                                                      'what_actions_can_be_monitored', 'block_custom_progress');
                            }

                            // End box.
                            $mform->addElement('text', 'config_weight_'.$moduleinfo->uniqueid, get_string('weight', 'block_custom_progress'));
                            $mform->setType('config_weight_'.$moduleinfo->uniqueid, PARAM_INT);
                            $mform->setDefault('config_weight_'.$moduleinfo->uniqueid, 1);
                            $moduleboxend = HTML_WRITER::end_tag('div');
                            $mform->addElement('html', $moduleboxend);
                        }
                    }
                }
            }
        }

        // When there are no activities that can be monitored, prompt teacher to create some.
        else {
            $mform->addElement('html', get_string('no_events_config_message', 'block_custom_progress'));
        }



    }

     public function validation($data, $files) {
        global $USER;
        $errors = array();
        if ($data['config_levels'] < 2) {
            $errors['config_levels'] = get_string('errorlevelsincorrect', 'block_custom_progress');
        }

        

        if ($data['config_enablecustomlevelpix']) {
            // Make sure the user has uploaded all the badges.
            $fs = get_file_storage();
            $usercontext = context_user::instance($USER->id);
            $expected = array_flip(range(0, $data['config_levels']-1));
            $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['config_levels_pix'], 'filename', false);
            foreach ($draftfiles as $file) {
                $pathinfo = pathinfo($file->get_filename());
                unset($expected[$pathinfo['filename']]);
            }
            if (count($expected) > 0) {
                $errors['config_levels_pix'] = get_string('errornotalllevelspixprovided', 'block_custom_progress', implode(', ', array_keys($expected)));
            }
        }

        return $errors;
    }

    function set_data($defaults) {
        if (!empty($this->block->config) && is_object($this->block->config)) {
            $draftid = file_get_submitted_draft_itemid('config_levels_pix');
            file_prepare_draft_area($draftid, $this->block->context->id, 'block_custom_progress', 'badges', 0,  array('subdirs' => 0,'maxfiles' => 20, 'accepted_types' => array('.jpg', '.png')));
            $defaults->config_levels_pix = $draftid;
            $this->block->config->levels_pix = $draftid;
        } 
        parent::set_data($defaults);
    }
}
