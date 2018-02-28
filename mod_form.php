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
//
// This file is part of BasicLTI4Moodle
//
// BasicLTI4Moodle is an IMS BasicLTI (Basic Learning Tools for Interoperability)
// consumer for Moodle 1.9 and Moodle 2.0. BasicLTI is a IMS Standard that allows web
// based learning tools to be easily integrated in LMS as native ones. The IMS BasicLTI
// specification is part of the IMS standard Common Cartridge 1.1 Sakai and other main LMS
// are already supporting or going to support BasicLTI. This project Implements the consumer
// for Moodle. Moodle is a Free Open source Learning Management System by Martin Dougiamas.
// BasicLTI4Moodle is a project iniciated and leaded by Ludo(Marc Alier) and Jordi Piguillem
// at the GESSI research group at UPC.
// SimpleLTI consumer for Moodle is an implementation of the early specification of LTI
// by Charles Severance (Dr Chuck) htp://dr-chuck.com , developed by Jordi Piguillem in a
// Google Summer of Code 2008 project co-mentored by Charles Severance and Marc Alier.
//
// BasicLTI4Moodle is copyright 2009 by Marc Alier Forment, Jordi Piguillem and Nikolas Galanis
// of the Universitat Politecnica de Catalunya http://www.upc.edu
// Contact info: Marc Alier Forment granludo @ gmail.com or marc.alier @ upc.edu

/**
 * This file defines the main matchmysound configuration form
 *
 * @package mod_matchmysound
 * @copyright  2016 T6nis Tartes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/matchmysound/locallib.php');

class mod_matchmysound_mod_form extends moodleform_mod {

    public function definition() {
        global $DB, $PAGE, $OUTPUT, $USER, $COURSE;

        if ($type = optional_param('type', false, PARAM_ALPHA)) {
            component_callback("matchmysoundsource_$type", 'add_instance_hook');
        }

        $mform =& $this->_form;
        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are shown
        $mform->addElement('header', 'general', get_string('general', 'form'));
        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('basicmatchmysoundname', 'matchmysound'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        // Adding the optional "intro" and "introformat" pair of fields
        $this->standard_intro_elements(get_string('basicmatchmysoundintro', 'matchmysound'));

        // Display the label to the right of the checkbox so it looks better & matches rest of the form
        $coursedesc = $mform->getElement('showdescription');
        if(!empty($coursedesc)){
            $coursedesc->setText(' ' . $coursedesc->getLabel());
            $coursedesc->setLabel('&nbsp');
        }

        $mform->addElement('checkbox', 'showtitlelaunch', '&nbsp;', ' ' . get_string('display_name', 'matchmysound'));

        $mform->setDefault('showtitlelaunch', true);
        $mform->addHelpButton('showtitlelaunch', 'display_name', 'matchmysound');

        $mform->addElement('checkbox', 'showdescriptionlaunch', '&nbsp;', ' ' . get_string('display_description', 'matchmysound'));

        $mform->addHelpButton('showdescriptionlaunch', 'display_description', 'matchmysound');

        $launchoptions=array();
        $launchoptions[MATCHMYSOUND_LAUNCH_CONTAINER_DEFAULT] = get_string('default', 'matchmysound');
        $launchoptions[MATCHMYSOUND_LAUNCH_CONTAINER_EMBED] = get_string('embed', 'matchmysound');
        $launchoptions[MATCHMYSOUND_LAUNCH_CONTAINER_EMBED_NO_BLOCKS] = get_string('embed_no_blocks', 'matchmysound');
        $launchoptions[MATCHMYSOUND_LAUNCH_CONTAINER_WINDOW] = get_string('new_window', 'matchmysound');

        $mform->addElement('select', 'launchcontainer', get_string('launchinpopup', 'matchmysound'), $launchoptions);
        $mform->setDefault('launchcontainer', MATCHMYSOUND_LAUNCH_CONTAINER_EMBED);
        $mform->addHelpButton('launchcontainer', 'launchinpopup', 'matchmysound');

        $mform->addElement('textarea', 'instructorcustomparameters', get_string('custom', 'matchmysound'), array('rows'=>4, 'cols'=>60));
        $mform->setType('instructorcustomparameters', PARAM_TEXT);
        $mform->setAdvanced('instructorcustomparameters');
        $mform->addHelpButton('instructorcustomparameters', 'custom', 'matchmysound');

        $mform->addElement('hidden', 'instructorchoicesendname', 1, array( 'id' => 'id_instructorchoicesendname' ));
        $mform->setType('instructorchoicesendname', PARAM_INT);
        $mform->addElement('hidden', 'instructorchoicesendemailaddr', 1, array( 'id' => 'id_instructorchoicesendemailaddr' ));
        $mform->setType('instructorchoicesendemailaddr', PARAM_INT);
        $mform->addElement('hidden', 'instructorchoiceacceptgrades', 1, array( 'id' => 'id_instructorchoiceacceptgrades' ));
        $mform->setType('instructorchoiceacceptgrades', PARAM_INT);

        
        $debugoptions=array();
        $debugoptions[0] = get_string('debuglaunchoff', 'lti');
        $debugoptions[1] = get_string('debuglaunchon', 'lti');

        $mform->addElement('select', 'debuglaunch', get_string('debuglaunch', 'lti'), $debugoptions);
        $mform->setDefault('debuglaunch', '0');
        $mform->setAdvanced('debuglaunch');

        // Grade settings.
        $this->standard_grading_coursemodule_elements();
        
        //-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        $mform->setAdvanced('cmidnumber');
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }

    /**
     * Make fields editable or non-editable depending on the administrator choices
     * @see moodleform_mod::definition_after_data()
     */
    public function definition_after_data() {
        parent::definition_after_data();
    }

    /**
     * Function overwritten to change default values using
     * global configuration
     *
     * @param array $default_values passed by reference
     */
    public function data_preprocessing(&$default_values) {
        if (!empty($default_values['grade'])) {
            $default_values['grade'] = intval($default_values['grade']);
        }
    }
}

