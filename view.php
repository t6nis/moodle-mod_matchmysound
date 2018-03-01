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
 * This file contains all necessary code to view a matchmysound activity instance
 *
 * @package    mod_matchmysound
 * @copyright  2016 T6nis Tartes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/matchmysound/lib.php');
require_once($CFG->dirroot.'/mod/matchmysound/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
$l  = optional_param('l', 0, PARAM_INT);  // matchmysound ID

if ($l) {  // Two ways to specify the module
    $matchmysound = $DB->get_record('matchmysound', array('id' => $l), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('matchmysound', $matchmysound->id, $matchmysound->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('matchmysound', $id, 0, false, MUST_EXIST);
    $matchmysound = $DB->get_record('matchmysound', array('id' => $cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$toolconfig = array();

$PAGE->set_cm($cm, $course); // set's up global $COURSE
$context = context_module::instance($cm->id);
$PAGE->set_context($context);

$url = new moodle_url('/mod/matchmysound/view.php', array('id'=>$cm->id));
$PAGE->set_url($url);

$launchcontainer = matchmysound_get_launch_container($matchmysound, $toolconfig);

if ($launchcontainer == MATCHMYSOUND_LAUNCH_CONTAINER_EMBED_NO_BLOCKS) {
    $PAGE->set_pagelayout('frametop'); //Most frametops don't include footer, and pre-post blocks
    $PAGE->blocks->show_only_fake_blocks(); //Disable blocks for layouts which do include pre-post blocks
} else if ($launchcontainer == MATCHMYSOUND_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW) {
    redirect('launch.php?id=' . $cm->id);
} else {
    $PAGE->set_pagelayout('incourse');
}

require_login($course);

// Mark viewed by user (if required).
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$params = array(
    'context' => $context,
    'objectid' => $matchmysound->id
);
$event = \mod_matchmysound\event\course_module_viewed::create($params);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('matchmysound', $matchmysound);
$event->trigger();

$pagetitle = strip_tags($course->shortname.': '.format_string($matchmysound->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

// Print the page header
echo $OUTPUT->header();

if ($matchmysound->showtitlelaunch) {
    // Print the main part of the page
    echo $OUTPUT->heading(format_string($matchmysound->name, true, array('context' => $context)));
}

if ($matchmysound->showdescriptionlaunch && $matchmysound->intro) {
    echo $OUTPUT->box(format_module_intro('matchmysound', $matchmysound, $cm->id), 'generalbox description', 'intro');
}

// Add MMS embeddersjs.
$mms_config = get_config('matchmysound');
$embedderjs = str_replace('/lti/', '/scripts/embedder.js', $mms_config->baseurl);

//has_capability('mod/matchmysound:grade', $context) ||
if ($launchcontainer == MATCHMYSOUND_LAUNCH_CONTAINER_WINDOW ) {
    echo "<script language=\"javascript\">//<![CDATA[\n";
    echo "window.open('launch.php?id=".$cm->id."','matchmysound');";
    echo "//]]\n";
    echo "</script>\n";
    echo "<p>".get_string("basicmatchmysound_in_new_window", "matchmysound")."</p>\n";
} else {
    // Request the launch content with an iframe tag.
    echo '<iframe id="contentframe" height="600px" width="100%" src="launch.php?id='.$cm->id.'"></iframe>';

    // Output script to make the iframe be as large as possible.
    $resize = '
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js" type="text/javascript"></script>
        <script src="'.$embedderjs.'" type="text/javascript"></script>
        <script type="text/javascript">
        //<![CDATA[
            YUI().use("yui2-dom", function(Y) {
                //Take scrollbars off the outer document to prevent double scroll bar effect
                document.body.style.overflow = "hidden";
                var dom = Y.YUI2.util.Dom;
                var frame = document.getElementById("contentframe");
                var padding = 15; //The bottom of the iframe wasn\'t visible on some themes. Probably because of border widths, etc.
                var lastHeight;

                var resize = function(){
                    var viewportHeight = dom.getViewportHeight();

                    if(lastHeight !== Math.min(dom.getDocumentHeight(), viewportHeight)){

                        frame.style.height = viewportHeight - dom.getY(frame) - padding + "px";

                        lastHeight = Math.min(dom.getDocumentHeight(), dom.getViewportHeight());
                    }
                };
                resize();
                setInterval(resize, 250);
            });
            window.onload = function() {              
              $(\'iframe\').map(function(ind,obj) { mms_resizer(obj); });
              $(\'body\').css({\'overflowY\': \'scroll\'});              
            };
            var ssh = function() { $(\'iframe\').map(function(ind,obj) {
            $(obj).css({\'height\':obj.height});  }); };
            window.onresize = function() { setTimeout(ssh,500); };
        //]]
        </script>
';

    echo $resize;
}

// Finish the page
echo $OUTPUT->footer();
