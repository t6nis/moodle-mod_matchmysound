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
 * This file contains a library of functions and constants for the matchmysound module
 *
 * @package mod_matchmysound
 * @copyright  2016 T6nis Tartes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Returns all other caps used in module.
 *
 * @return array
 */
function matchmysound_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

/**
 * List of features supported in URL module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function matchmysound_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted basicmatchmysound record
 **/
function matchmysound_add_instance($matchmysound, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/matchmysound/locallib.php');

    $matchmysound->timecreated = time();
    $matchmysound->timemodified = $matchmysound->timecreated;
    $matchmysound->servicesalt = uniqid('', true);    
    $matchmysound->resourcelinkid = mt_rand(100000, 999999).'-'.mt_rand(10000, 99999);

    if (!isset($matchmysound->grade)) {
        $matchmysound->grade = 100; // TODO: Why is this harcoded here and default @ DB
    }

    $matchmysound->id = $DB->insert_record('matchmysound', $matchmysound);

    if (!isset($matchmysound->cmidnumber)) {
        $matchmysound->cmidnumber = '';
    }
        
    matchmysound_grade_item_update($matchmysound);

    return $matchmysound->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function matchmysound_update_instance($matchmysound, $mform) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/matchmysound/locallib.php');
    
    $mms = $DB->get_record('matchmysound', array("id" => $matchmysound->instance));    
    $matchmysound->timemodified = time();
    $matchmysound->id = $matchmysound->instance;
    
    if (!isset($matchmysound->showtitlelaunch)) {
        $matchmysound->showtitlelaunch = 0;
    }

    if (!isset($matchmysound->showdescriptionlaunch)) {
        $matchmysound->showdescriptionlaunch = 0;
    }

    if (!isset($matchmysound->grade)) {
        $matchmysound->grade = $DB->get_field('matchmysound', 'grade', array('id' => $matchmysound->id));
    }

    matchmysound_grade_item_update($matchmysound);

    return $DB->update_record('matchmysound', $matchmysound);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function matchmysound_delete_instance($id) {
    global $DB;

    if (! $basicmatchmysound = $DB->get_record("matchmysound", array("id" => $id))) {
        return false;
    }

    $result = true;

    # Delete any dependent records here #
    matchmysound_grade_item_delete($basicmatchmysound);

    return $DB->delete_records("matchmysound", array("id" => $basicmatchmysound->id));
}


/**
 * Given a coursemodule object, this function returns the extra
 * information needed to print this activity in various places.
 * For this module we just need to support external urls as
 * activity icons
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info info
 */
function matchmysound_get_coursemodule_info($coursemodule) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/matchmysound/locallib.php');

    if (!$matchmysound = $DB->get_record('matchmysound', array('id' => $coursemodule->instance),
            'intro, introformat, name, launchcontainer')) {
        return null;
    }

    $info = new cached_cm_info();

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $info->content = format_module_intro('matchmysound', $matchmysound, $coursemodule->id, false);
    }

    $toolconfig = array();
    
    $launchcontainer = matchmysound_get_launch_container($matchmysound, $toolconfig);
    if ($launchcontainer == MATCHMYSOUND_LAUNCH_CONTAINER_WINDOW) {
        $launchurl = new moodle_url('/mod/matchmysound/launch.php', array('id' => $coursemodule->id));
        $info->onclick = "window.open('" . $launchurl->out(false) . "', 'matchmysound'); return false;";
    }

    $info->name = $matchmysound->name;

    return $info;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @TODO: implement this moodle function (if needed)
 **/
function matchmysound_user_outline($course, $user, $mod, $basicmatchmysound) {
    return null;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @TODO: implement this moodle function (if needed)
 **/
function matchmysound_user_complete($course, $user, $mod, $basicmatchmysound) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in basicmatchmysound activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @uses $CFG
 * @return boolean
 * @TODO: implement this moodle function
 **/
function matchmysound_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @uses $CFG
 * @return boolean
 **/
function matchmysound_cron () {
    return true;
}

/**
 * Must return an array of grades for a given instance of this module,
 * indexed by user.  It also returns a maximum allowed grade.
 *
 * Example:
 *    $return->grades = array of grades;
 *    $return->maxgrade = maximum allowed grade;
 *
 *    return $return;
 *
 * @param int $basicmatchmysoundid ID of an instance of this module
 * @return mixed Null or object with an array of grades and with the maximum grade
 *
 * @TODO: implement this moodle function (if needed)
 **/
function matchmysound_grades($basicmatchmysoundid) {
    return null;
}

/**
 * This function returns if a scale is being used by one basicmatchmysound
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $basicmatchmysoundid ID of an instance of this module
 * @return mixed
 *
 * @TODO: implement this moodle function (if needed)
 **/
function matchmysound_scale_used ($basicmatchmysoundid, $scaleid) {
    $return = false;

    //$rec = get_record("basicmatchmysound","id","$basicmatchmysoundid","scale","-$scaleid");
    //
    //if (!empty($rec)  && !empty($scaleid)) {
    //    $return = true;
    //}

    return $return;
}

/**
 * Checks if scale is being used by any instance of basicmatchmysound.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any basicmatchmysound
 *
 */
function matchmysound_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('matchmysound', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function matchmysound_install() {
     return true;
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function matchmysound_uninstall() {
    return true;
}

/**
 * Returns available Basic LTI types
 *
 * @return array of basicLTI types
 */
function matchmysound_get_matchmysound_types() {
    global $DB;

    return $DB->get_records('matchmysound_types');
}

/**
 * Create grade item for given basicmatchmysound
 *
 * @category grade
 * @param object $basicmatchmysound object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function matchmysound_grade_item_update($basicmatchmysound, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $params = array('itemname'=>$basicmatchmysound->name, 'idnumber'=>$basicmatchmysound->cmidnumber);

    if ($basicmatchmysound->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $basicmatchmysound->grade;
        $params['grademin']  = 0;

    } else if ($basicmatchmysound->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$basicmatchmysound->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_TEXT; // allow text comments only
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/matchmysound', $basicmatchmysound->course, 'mod', 'matchmysound', $basicmatchmysound->id, 0, $grades, $params);
}

/**
 * Delete grade item for given basicmatchmysound
 *
 * @category grade
 * @param object $basicmatchmysound object
 * @return object basicmatchmysound
 */
function matchmysound_grade_item_delete($basicmatchmysound) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/matchmysound', $basicmatchmysound->course, 'mod', 'matchmysound', $basicmatchmysound->id, 0, null, array('deleted'=>1));
}

function matchmysound_extend_settings_navigation($settings, $parentnode) {
    global $PAGE;

    if (has_capability('mod/matchmysound:grade', context_module::instance($PAGE->cm->id))) {
        $keys = $parentnode->get_children_key_list();
        
        $node = navigation_node::create('Submissions review',
            new moodle_url('/mod/matchmysound/submissions_review.php', array('id'=>$PAGE->cm->id)),
            navigation_node::TYPE_SETTING, null, 'mod_matchmysound_submissions_review');
        
        $parentnode->add_node($node, $keys[1]);
    }
}
