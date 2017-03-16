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
 * Progress block settings
 *
 * @package   block_custom_progress
 * @copyright 2010 Michael de Raadt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/blocks/custom_progress/lib.php');

if ($ADMIN->fulltree) {

$settings->add(new admin_setting_configtext('block_custom_progress/levels',
        new lang_string('levels', 'block_custom_progress'),
        new lang_string('levels_desc', 'block_custom_progress'), 10, PARAM_INT)
    );
        
    $settings->add(new admin_setting_configcheckbox('block_custom_progress/enablecustomlevelpix',
        get_string('enablecustomlevelpix', 'block_custom_progress'),
        '',
        DEFAULT_ENABLE_CUSTOM_PIX)
    );
    $settings->add(new admin_setting_configstoredfile('block_custom_progress/levels_pix',
        new lang_string('levels_pix', 'block_custom_progress'),
        new lang_string('levels_pix_desc', 'block_custom_progress'),'preset', 0,
        array('subdirs' => 0,'maxfiles' => 20, 'accepted_types' => array('.png','.jpg')))
    );
    


    $settings->add(new admin_setting_configcheckbox('block_custom_progress/showinactive',
        get_string('showinactive', 'block_custom_progress'),
        '',
        DEFAULT_CUSTOM_SHOWINACTIVE)
    );
        
}
