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
 * LTI web service endpoints
 *
 * @package mod_matchmysound
 * @copyright  2016 T6nis Tartes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);

require_once(dirname(__FILE__) . "/../../config.php");
require_once($CFG->dirroot.'/mod/matchmysound/locallib.php');
require_once($CFG->dirroot.'/mod/matchmysound/servicelib.php');

// TODO: Switch to core oauthlib once implemented - MDL-30149
use moodle\mod\matchmysound as matchmysound;

$rawbody = file_get_contents("php://input");

foreach (matchmysound\OAuthUtil::get_headers() as $name => $value) {
    if ($name === 'Authorization') {
        // TODO: Switch to core oauthlib once implemented - MDL-30149
        $oauthparams = matchmysound\OAuthUtil::split_header($value);

        $consumerkey = $oauthparams['oauth_consumer_key'];
        break;
    }
}

if (empty($consumerkey)) {
    throw new Exception('Consumer key is missing.');
}
$mms_config = get_config('matchmysound');
$sharedsecret = matchmysound_verify_message($consumerkey, array($mms_config->password), $rawbody);

if ($sharedsecret === false) {
    throw new Exception('Message signature not valid');
}

// TODO MDL-46023 Replace this code with a call to the new library.
$origentity = libxml_disable_entity_loader(true);
$xml = simplexml_load_string($rawbody);
if (!$xml) {
    libxml_disable_entity_loader($origentity);
    throw new Exception('Invalid XML content');
}
libxml_disable_entity_loader($origentity);

$body = $xml->imsx_POXBody;
foreach ($body->children() as $child) {
    $messagetype = $child->getName();
}

switch ($messagetype) {
    case 'replaceResultRequest':
        try {
            $parsed = matchmysound_parse_grade_replace_message($xml);
        } catch (Exception $e) {
            $responsexml = matchmysound_get_response_xml(
                'failure',
                $e->getMessage(),
                uniqid(),
                'replaceResultResponse');

            echo $responsexml->asXML();
            break;
        }

        $matchmysoundinstance = $DB->get_record('matchmysound', array('resourcelinkid' => $parsed->instanceid));

        matchmysound_verify_sourcedid($matchmysoundinstance, $parsed);

        $gradestatus = matchmysound_update_grade($matchmysoundinstance, $parsed->userid, $parsed->launchid, $parsed->gradeval);

        $responsexml = matchmysound_get_response_xml(
                $gradestatus ? 'success' : 'failure',
                'Grade replace response',
                $parsed->messageid,
                'replaceResultResponse'
        );

        echo $responsexml->asXML();

        break;

    case 'readResultRequest':
        $parsed = matchmysound_parse_grade_read_message($xml);

        $matchmysoundinstance = $DB->get_record('matchmysound', array('resourcelinkid' => $parsed->instanceid));

        //Getting the grade requires the context is set
        $context = context_course::instance($matchmysoundinstance->course);
        $PAGE->set_context($context);

        matchmysound_verify_sourcedid($matchmysoundinstance, $parsed);

        $grade = matchmysound_read_grade($matchmysoundinstance, $parsed->userid);

        $responsexml = matchmysound_get_response_xml(
                'success',  // Empty grade is also 'success'
                'Result read',
                $parsed->messageid,
                'readResultResponse'
        );

        $node = $responsexml->imsx_POXBody->readResultResponse;
        $node = $node->addChild('result')->addChild('resultScore');
        $node->addChild('language', 'en');
        $node->addChild('textString', isset($grade) ? $grade : '');

        echo $responsexml->asXML();

        break;

    case 'deleteResultRequest':
        $parsed = matchmysound_parse_grade_delete_message($xml);

        $matchmysoundinstance = $DB->get_record('matchmysound', array('resourcelinkid' => $parsed->instanceid));

        matchmysound_verify_sourcedid($matchmysoundinstance, $parsed);

        $gradestatus = matchmysound_delete_grade($matchmysoundinstance, $parsed->userid);

        $responsexml = matchmysound_get_response_xml(
                $gradestatus ? 'success' : 'failure',
                'Grade delete request',
                $parsed->messageid,
                'deleteResultResponse'
        );

        echo $responsexml->asXML();

        break;

    default:
        //Fire an event if we get a web service request which we don't support directly.
        //This will allow others to extend the LTI services, which I expect to be a common
        //use case, at least until the spec matures.
        $data = new stdClass();
        $data->body = $rawbody;
        $data->xml = $xml;
        $data->messageid = matchmysound_parse_message_id($xml);
        $data->messagetype = $messagetype;
        $data->consumerkey = $consumerkey;
        $data->sharedsecret = $sharedsecret;
        $eventdata = array();
        $eventdata['other'] = array();
        $eventdata['other']['messageid'] = $data->messageid;
        $eventdata['other']['messagetype'] = $messagetype;
        $eventdata['other']['consumerkey'] = $consumerkey;

        // Before firing the event, allow subplugins a chance to handle.
        if (matchmysound_extend_matchmysound_services($data)) {
            break;
        }

        //If an event handler handles the web service, it should set this global to true
        //So this code knows whether to send an "operation not supported" or not.
        global $matchmysound_web_service_handled;
        $matchmysound_web_service_handled = false;

        try {
            $event = \mod_matchmysound\event\unknown_service_api_called::create($eventdata);
            $event->set_message_data($data);
            $event->trigger();
        } catch (Exception $e) {
            $matchmysound_web_service_handled = false;
        }

        if (!$matchmysound_web_service_handled) {
            $responsexml = matchmysound_get_response_xml(
                'unsupported',
                'unsupported',
                 matchmysound_parse_message_id($xml),
                 $messagetype
            );

            echo $responsexml->asXML();
        }

        break;
}


//echo print_r(apache_request_headers(), true);

//echo '<br />';

//echo file_get_contents("php://input");
