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
 * This file contains the library of functions and constants for the matchmysound module
 *
 * @package mod_matchmysound
 * @copyright  2016 T6nis Tartes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// TODO: Switch to core oauthlib once implemented - MDL-30149
use moodle\mod\matchmysound as matchmysound;

require_once($CFG->dirroot.'/mod/matchmysound/OAuth.php');

define('MATCHMYSOUND_URL_DOMAIN_REGEX', '/(?:https?:\/\/)?(?:www\.)?([^\/]+)(?:\/|$)/i');

define('MATCHMYSOUND_LAUNCH_CONTAINER_DEFAULT', 1);
define('MATCHMYSOUND_LAUNCH_CONTAINER_EMBED', 2);
define('MATCHMYSOUND_LAUNCH_CONTAINER_EMBED_NO_BLOCKS', 3);
define('MATCHMYSOUND_LAUNCH_CONTAINER_WINDOW', 4);
define('MATCHMYSOUND_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW', 5);

define('MATCHMYSOUND_TOOL_STATE_ANY', 0);
define('MATCHMYSOUND_TOOL_STATE_CONFIGURED', 1);
define('MATCHMYSOUND_TOOL_STATE_PENDING', 2);
define('MATCHMYSOUND_TOOL_STATE_REJECTED', 3);

define('MATCHMYSOUND_SETTING_NEVER', 0);
define('MATCHMYSOUND_SETTING_ALWAYS', 1);
define('MATCHMYSOUND_SETTING_DELEGATE', 2);

/**
 * Prints a Basic LTI activity
 *
 * $param int $basicmatchmysoundid       Basic LTI activity id
 */
function matchmysound_view($instance, $review = false) {
    global $PAGE, $CFG;
    
    $mms_config = get_config('matchmysound');

    //There is no admin configuration for this tool. Use configuration in the matchmysound instance record plus some defaults.
    $typeconfig = (array)$instance;
    $typeconfig['sendname'] = 1;
    $typeconfig['sendemailaddr'] = 1;
    $typeconfig['customparameters'] = $instance->instructorcustomparameters;
    $typeconfig['acceptgrades'] = 1;
    //$typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
    $typeconfig['forcessl'] = '0';
    
    //Default the organizationid if not specified
    if (empty($typeconfig['organizationid'])) {
        $urlparts = parse_url($CFG->wwwroot);
        $typeconfig['organizationid'] = $urlparts['host'];
    }

    $key = $mms_config->key;
    $secret = $mms_config->password;
    $endpoint = $mms_config->baseurl;
    $endpoint = trim($endpoint);

    $orgid = $typeconfig['organizationid'];

    $course = $PAGE->course;
    
    $requestparams = matchmysound_build_request($instance, $typeconfig, $course);

    $launchcontainer = matchmysound_get_launch_container($instance, $typeconfig);
    $returnurlparams = array('course' => $course->id,
                             'launch_container' => $launchcontainer,
                             'instanceid' => $instance->resourcelinkid,
                             'sesskey' => sesskey());

    if ( $orgid ) {
        $requestparams["tool_consumer_instance_guid"] = $orgid;
    }

    if (empty($key) || empty($secret)) {
        $returnurlparams['unsigned'] = '1';
    }

    // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
    $url = new moodle_url('/mod/matchmysound/return.php', $returnurlparams);
    $returnurl = $url->out(false);

    if ($typeconfig['forcessl'] == '1') {
        $returnurl = matchmysound_ensure_url_is_https($returnurl);
    }

    $requestparams['launch_presentation_return_url'] = $returnurl;
  
    if ($review > 0) {
        $requestparams['custom_review_result_sourcedid'] = $review;
    }
    // Custom
    $requestparams['launch_presentation_document_target'] = 'iframe';
    
    if (!empty($key) && !empty($secret)) {
        $parms = matchmysound_sign_parameters($requestparams, $endpoint, "POST", $key, $secret);

        $endpointurl = new moodle_url($endpoint);
        $endpointparams = $endpointurl->params();

        // Strip querystring params in endpoint url from $parms to avoid duplication.
        if (!empty($endpointparams) && !empty($parms)) {
            foreach (array_keys($endpointparams) as $paramname) {
                if (isset($parms[$paramname])) {
                    unset($parms[$paramname]);
                }
            }
        }
    } else {
        //If no key and secret, do the launch unsigned.
        $parms = $requestparams;
    }
   
    $debuglaunch = $instance->debuglaunch; // 0 = No, 1 = Yes
    
    $content = matchmysound_post_launch_html($parms, $endpoint, $debuglaunch);

    echo $content;
}

function matchmysound_build_sourcedid($instanceid, $userid, $launchid = null, $servicesalt) {
    $data = new stdClass();

    $data->instanceid = $instanceid;
    $data->userid = $userid;
    if (!empty($launchid)) {
        $data->launchid = $launchid;
    } else {
        $data->launchid = mt_rand();
    }

    $json = json_encode($data);

    $hash = hash('sha256', $json . $servicesalt, false);

    $container = new stdClass();
    $container->data = $data;
    $container->hash = $hash;

    return $container;
}

/**
 * This function builds the request that must be sent to the tool producer
 *
 * @param object    $instance       Basic LTI instance object
 * @param array     $typeconfig     Basic LTI tool configuration
 * @param object    $course         Course object
 *
 * @return array    $request        Request details
 */
function matchmysound_build_request($instance, $typeconfig, $course) {
    global $USER, $CFG;

    if (empty($instance->cmid)) {
        $instance->cmid = 0;
    }

    $role = matchmysound_get_ims_role($USER, $instance->cmid, $instance->course);

    $intro = '';
    if (!empty($instance->cmid)) {
        $intro = format_module_intro('matchmysound', $instance, $instance->cmid);
        $intro = html_to_text($intro, 0, false);

        // This may look weird, but this is required for new lines
        // so we generate the same OAuth signature as the tool provider.
        $intro = str_replace("\n", "\r\n", $intro);
    }
    $requestparams = array(
        'resource_link_id' => $instance->resourcelinkid,
        'resource_link_title' => $instance->name,
        'resource_link_description' => $intro,
        'user_id' => $USER->id,
        'roles' => $role,
        'context_id' => $course->id,
        'context_label' => $course->shortname,
        'context_title' => $course->fullname,
        'launch_presentation_locale' => current_language()
    );

    $placementsecret = $instance->servicesalt;

    if ( isset($placementsecret) ) {
        $sourcedid = json_encode(matchmysound_build_sourcedid($instance->resourcelinkid, $USER->id, null, $placementsecret));
    }

    $requestparams['lis_result_sourcedid'] = $sourcedid;

    //Add outcome service URL
    $serviceurl = new moodle_url('/mod/matchmysound/service.php');
    $serviceurl = $serviceurl->out();

    if ($typeconfig['forcessl'] == '1') {
        $serviceurl = matchmysound_ensure_url_is_https($serviceurl);
    }

    $requestparams['lis_outcome_service_url'] = $serviceurl;

    // Send user's name and email data if appropriate
    $requestparams['lis_person_name_given'] =  $USER->firstname;
    $requestparams['lis_person_name_family'] =  $USER->lastname;
    $requestparams['lis_person_name_full'] =  $USER->firstname." ".$USER->lastname;
    $requestparams['lis_person_contact_email_primary'] = $USER->email;

    // Concatenate the custom parameters from the administrator and the instructor
    // Instructor parameters are only taken into consideration if the administrator
    // has giver permission
    $customstr = $typeconfig['customparameters'];
    $instructorcustomstr = $instance->instructorcustomparameters;
    
    $custom = array();
    $instructorcustom = array();
    if ($customstr) {
        $custom = matchmysound_split_custom_parameters($customstr);
    }
    
    if (isset($typeconfig['allowinstructorcustom']) && $typeconfig['allowinstructorcustom'] == MATCHMYSOUND_SETTING_NEVER) {
        $requestparams = array_merge($custom, $requestparams);
    } else {
        if ($instructorcustomstr) {
            $instructorcustom = matchmysound_split_custom_parameters($instructorcustomstr);
        }
        foreach ($instructorcustom as $key => $val) {
            // Ignore the instructor's parameter
            if (!array_key_exists($key, $custom)) {
                $custom[$key] = $val;
            }
        }
        $requestparams = array_merge($custom, $requestparams);
    }

    // Make sure we let the tool know what LMS they are being called from
    $requestparams["ext_lms"] = "moodle-2";
    $requestparams['tool_consumer_info_product_family_code'] = 'moodle';
    $requestparams['tool_consumer_info_version'] = strval($CFG->version);

    // Add oauth_callback to be compliant with the 1.0A spec
    $requestparams['oauth_callback'] = 'about:blank';

    //The submit button needs to be part of the signature as it gets posted with the form.
    //This needs to be here to support launching without javascript.
    $submittext = get_string('press_to_submit', 'matchmysound');
    $requestparams['ext_submit'] = $submittext;

    $requestparams['matchmysound_version'] = 'LTI-1p0';
    $requestparams['matchmysound_message_type'] = 'basic-matchmysound-launch-request';

    return $requestparams;
}

/**
 * Splits the custom parameters field to the various parameters
 *
 * @param string $customstr     String containing the parameters
 *
 * @return Array of custom parameters
 */
function matchmysound_split_custom_parameters($customstr) {
    $lines = preg_split("/[\n;]/", $customstr);
    $retval = array();
    foreach ($lines as $line) {
        $pos = strpos($line, "=");
        if ( $pos === false || $pos < 1 ) {
            continue;
        }
        $key = trim(core_text::substr($line, 0, $pos));
        $val = trim(core_text::substr($line, $pos+1, strlen($line)));
        $key = matchmysound_map_keyname($key);
        $retval['custom_'.$key] = $val;
    }
    return $retval;
}

/**
 * Used for building the names of the different custom parameters
 *
 * @param string $key   Parameter name
 *
 * @return string       Processed name
 */
function matchmysound_map_keyname($key) {
    $newkey = "";
    $key = core_text::strtolower(trim($key));
    foreach (str_split($key) as $ch) {
        if ( ($ch >= 'a' && $ch <= 'z') || ($ch >= '0' && $ch <= '9') ) {
            $newkey .= $ch;
        } else {
            $newkey .= '_';
        }
    }
    return $newkey;
}

/**
 * Gets the IMS role string for the specified user and LTI course module.
 *
 * @param mixed $user User object or user id
 * @param int $cmid The course module id of the LTI activity
 * @return string A role string suitable for passing with an LTI launch
 */
function matchmysound_get_ims_role($user, $cmid, $courseid) {
    $roles = array();

    if (empty($cmid)) {
        //If no cmid is passed, check if the user is a teacher in the course
        //This allows other modules to programmatically "fake" a launch without
        //a real LTI instance
        $coursecontext = context_course::instance($courseid);

        if (has_capability('moodle/course:manageactivities', $coursecontext)) {
            array_push($roles, 'Instructor');
        } else {
            array_push($roles, 'Learner');
        }
    } else {
        $context = context_module::instance($cmid);

        if (has_capability('mod/matchmysound:manage', $context)) {
            array_push($roles, 'Instructor');
        } else {
            array_push($roles, 'Learner');
        }
    }

    if (is_siteadmin($user)) {
        array_push($roles, 'urn:matchmysound:sysrole:ims/lis/Administrator');
    }

    return join(',', $roles);
}

function matchmysound_get_domain_from_url($url) {
    $matches = array();

    if (preg_match(MATCHMYSOUND_URL_DOMAIN_REGEX, $url, $matches)) {
        return $matches[1];
    }
}

/**
 * Transforms a basic LTI object to an array
 *
 * @param object $matchmysoundobject    Basic LTI object
 *
 * @return array Basic LTI configuration details
 */
function matchmysound_get_config($matchmysoundobject) {
    $typeconfig = array();
    $typeconfig = (array)$matchmysoundobject;
    return $typeconfig;
}

/**
 *
 * Generates some of the tool configuration based on the instance details
 *
 * @param int $id
 *
 * @return Instance configuration
 *
 */
function matchmysound_get_type_config_from_instance($id) {
    global $DB;

    $instance = $DB->get_record('matchmysound', array('id' => $id));
    $config = matchmysound_get_config($instance);

    $type = new stdClass();
    $type->matchmysound_fix = $id;

    if (isset($config['instructorchoicesendname'])) {
        $type->matchmysound_sendname = $config['instructorchoicesendname'];
    }
    if (isset($config['instructorchoicesendemailaddr'])) {
        $type->matchmysound_sendemailaddr = $config['instructorchoicesendemailaddr'];
    }
    if (isset($config['instructorchoiceacceptgrades'])) {
        $type->matchmysound_acceptgrades = $config['instructorchoiceacceptgrades'];
    }
    if (isset($config['instructorchoiceallowroster'])) {
        $type->matchmysound_allowroster = $config['instructorchoiceallowroster'];
    }

    if (isset($config['instructorcustomparameters'])) {
        $type->matchmysound_allowsetting = $config['instructorcustomparameters'];
    }
    return $type;
}


/**
 * Signs the petition to launch the external tool using OAuth
 *
 * @param $oldparms     Parameters to be passed for signing
 * @param $endpoint     url of the external tool
 * @param $method       Method for sending the parameters (e.g. POST)
 * @param $oauth_consumoer_key          Key
 * @param $oauth_consumoer_secret       Secret
 * @param $submittext  The text for the submit button
 * @param $orgid       LMS name
 * @param $orgdesc     LMS key
 */
function matchmysound_sign_parameters($oldparms, $endpoint, $method, $oauthconsumerkey, $oauthconsumersecret) {
    //global $lastbasestring;
    $parms = $oldparms;

    $testtoken = '';

    // TODO: Switch to core oauthlib once implemented - MDL-30149
    $hmacmethod = new matchmysound\OAuthSignatureMethod_HMAC_SHA1();
    $testconsumer = new matchmysound\OAuthConsumer($oauthconsumerkey, $oauthconsumersecret, null);
    $accreq = matchmysound\OAuthRequest::from_consumer_and_token($testconsumer, $testtoken, $method, $endpoint, $parms);
    $accreq->sign_request($hmacmethod, $testconsumer, $testtoken);

    // Pass this back up "out of band" for debugging
    //$lastbasestring = $accreq->get_signature_base_string();

    $newparms = $accreq->get_parameters();

    return $newparms;
}

/**
 * Posts the launch petition HTML
 *
 * @param $newparms     Signed parameters
 * @param $endpoint     URL of the external tool
 * @param $debug        Debug (true/false)
 */
function matchmysound_post_launch_html($newparms, $endpoint, $debug=false) {
    $r = "<form action=\"".$endpoint."\" name=\"matchmysoundLaunchForm\" id=\"matchmysoundLaunchForm\" method=\"post\" encType=\"application/x-www-form-urlencoded\">\n";

    $submittext = $newparms['ext_submit'];

    // Contruct html for the launch parameters
    foreach ($newparms as $key => $value) {
        $key = htmlspecialchars($key);
        $value = htmlspecialchars($value);
        if ( $key == "ext_submit" ) {
            $r .= "<input type=\"submit\" name=\"";
        } else {
            $r .= "<input type=\"hidden\" name=\"";
        }
        $r .= $key;
        $r .= "\" value=\"";
        $r .= $value;
        $r .= "\"/>\n";
    }

    if ( $debug ) {
        $r .= "<script language=\"javascript\"> \n";
        $r .= "  //<![CDATA[ \n";
        $r .= "function basicmatchmysoundDebugToggle() {\n";
        $r .= "    var ele = document.getElementById(\"basicmatchmysoundDebug\");\n";
        $r .= "    if (ele.style.display == \"block\") {\n";
        $r .= "        ele.style.display = \"none\";\n";
        $r .= "    }\n";
        $r .= "    else {\n";
        $r .= "        ele.style.display = \"block\";\n";
        $r .= "    }\n";
        $r .= "} \n";
        $r .= "  //]]> \n";
        $r .= "</script>\n";
        $r .= "<a id=\"displayText\" href=\"javascript:basicmatchmysoundDebugToggle();\">";
        $r .= get_string("toggle_debug_data", "matchmysound")."</a>\n";
        $r .= "<div id=\"basicmatchmysoundDebug\" style=\"display:none\">\n";
        $r .=  "<b>".get_string("basicmatchmysound_endpoint", "matchmysound")."</b><br/>\n";
        $r .= $endpoint . "<br/>\n&nbsp;<br/>\n";
        $r .=  "<b>".get_string("basicmatchmysound_parameters", "matchmysound")."</b><br/>\n";
        foreach ($newparms as $key => $value) {
            $key = htmlspecialchars($key);
            $value = htmlspecialchars($value);
            $r .= "$key = $value<br/>\n";
        }
        $r .= "&nbsp;<br/>\n";
        $r .= "</div>\n";
    }
    $r .= "</form>\n";

    if ( ! $debug ) {
        $ext_submit = "ext_submit";
        $ext_submit_text = $submittext;
        $r .= " <script type=\"text/javascript\"> \n" .
            "  //<![CDATA[ \n" .
            "    document.getElementById(\"matchmysoundLaunchForm\").style.display = \"none\";\n" .
            "    nei = document.createElement('input');\n" .
            "    nei.setAttribute('type', 'hidden');\n" .
            "    nei.setAttribute('name', '".$ext_submit."');\n" .
            "    nei.setAttribute('value', '".$ext_submit_text."');\n" .
            "    document.getElementById(\"matchmysoundLaunchForm\").appendChild(nei);\n" .
            "    document.matchmysoundLaunchForm.submit(); \n" .
            "  //]]> \n" .
            " </script> \n";
    }
    return $r;
}


function matchmysound_get_launch_container($matchmysound, $toolconfig) {
    if (empty($matchmysound->launchcontainer)) {
        $matchmysound->launchcontainer = MATCHMYSOUND_LAUNCH_CONTAINER_DEFAULT;
    }

    if ($matchmysound->launchcontainer == MATCHMYSOUND_LAUNCH_CONTAINER_DEFAULT) {
        if (isset($toolconfig['launchcontainer'])) {
            $launchcontainer = $toolconfig['launchcontainer'];
        }
    } else {
        $launchcontainer = $matchmysound->launchcontainer;
    }

    if (empty($launchcontainer) || $launchcontainer == MATCHMYSOUND_LAUNCH_CONTAINER_DEFAULT) {
        $launchcontainer = MATCHMYSOUND_LAUNCH_CONTAINER_EMBED;
    }

    $devicetype = core_useragent::get_device_type();

    //Scrolling within the object element doesn't work on iOS or Android
    //Opening the popup window also had some issues in testing
    //For mobile devices, always take up the entire screen to ensure the best experience
    if ($devicetype === core_useragent::DEVICETYPE_MOBILE || $devicetype === core_useragent::DEVICETYPE_TABLET ) {
        $launchcontainer = MATCHMYSOUND_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW;
    }

    return $launchcontainer;
}

function matchmysound_request_is_using_ssl() {
    global $CFG;
    return (stripos($CFG->httpswwwroot, 'https://') === 0);
}

function matchmysound_ensure_url_is_https($url) {
    if (!strstr($url, '://')) {
        $url = 'https://' . $url;
    } else {
        //If the URL starts with http, replace with https
        if (stripos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }
    }

    return $url;
}
