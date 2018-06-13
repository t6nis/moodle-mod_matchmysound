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
 * This file contains submissions-specific code for the matchmysound module
 *
 * @package mod_matchmysound
 * @copyright  2016 T6nis Tartes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/matchmysound/lib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/mod/matchmysound/servicelib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$l    = optional_param('l', 0, PARAM_INT);           // matchmysound instance ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$download = optional_param('download' , 'none', PARAM_ALPHA); //ZIP download asked for?
$review = optional_param('review', 0, PARAM_INT); // Student ID to review

if ($l) {  // Two ways to specify the module
    $matchmysound = $DB->get_record('matchmysound', array('id' => $l), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('matchmysound', $matchmysound->id, $matchmysound->course, false, MUST_EXIST);
} else {
    $cm = get_coursemodule_from_id('matchmysound', $id, 0, false, MUST_EXIST);
    $matchmysound = $DB->get_record('matchmysound', array('id' => $cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/matchmysound:grade', $context);

$url = new moodle_url('/mod/matchmysound/submissions_review.php', array('id' => $cm->id));
if ($mode !== 'all') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);

$students = get_enrolled_users($context);
$table = new html_table();
$table->head  = array (get_string('name'), get_string('email'), get_string('date'), get_string('grade'), get_string('link', 'mod_matchmysound'));
$table->align = array ('left', 'left', 'left', 'center');
$table->data = array();
foreach ($students as $key => $value) {
    $link = get_string('notsubmitted', 'mod_matchmysound');
    $grade = matchmysound_review_read_grade($matchmysound, $value->id);
    $records = $DB->get_records('matchmysound_submission', array('matchmysoundid' => $matchmysound->id, 'userid' => $value->id), 'datesubmitted');
    $submitteddate = '';
    foreach ($records as $key => $record) {
        if (!empty($record->datesubmitted)) {
            $submitteddate = date('d.m.Y H:i', $record->datesubmitted);
            $link = html_writer::link(new moodle_url('/mod/matchmysound/submissions_review.php', array('id' => $cm->id, 'review' => $value->id)), 'Review' /*array('target' => '_blank')*/);
        }
        if (empty($grade)) {
            $grade = 0;
        }
    }
    $row = array($value->firstname.' '.$value->lastname, $value->email, $submitteddate, $grade, $link);
    $table->data[] = $row;
}

$title = get_string('submissionsfor', 'matchmysound', $matchmysound->name);
// Add MMS embeddersjs.
$mms_config = get_config('matchmysound');
$embedderjs = str_replace('/lti/', '/scripts/embedder.js', $mms_config->baseurl);

$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($matchmysound->name, true, array('context' => $context)));
echo $OUTPUT->heading(get_string('submissions', 'matchmysound'), 3);

if ($review > 0) {
    // Request the launch content with an iframe tag.
    echo '<iframe id="contentframe" data-mms-embed style="height:400px;width:100%" src="launch.php?id='.$cm->id.'&review='.$review.'"></iframe>';

    // Output script to make the iframe be as large as possible.
    $resize = '
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js" type="text/javascript"></script>
        <script src="'.$embedderjs.'" type="text/javascript"></script>
        <script type="text/javascript">// <![CDATA[
        window.onload = function() {
          $(\'iframe\').map(function(ind,obj) { mms_resizer(obj); });
          $(\'html,body\').css({\'overflowY\': \'scroll\'});
        };
        // ]]></script>
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
        //]]
        </script>
';

    echo $resize;

} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
