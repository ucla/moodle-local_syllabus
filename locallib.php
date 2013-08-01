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
 * Internal library of functions for syllabus.
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the newmodule specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    local_syllabus
 * @copyright  2012 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Constants for syllabus management.
define('SYLLABUS_ACCESS_TYPE_PUBLIC', 1);
define('SYLLABUS_ACCESS_TYPE_LOGGEDIN', 2);
define('SYLLABUS_ACCESS_TYPE_PRIVATE', 3);

define('SYLLABUS_TYPE_PUBLIC', 'public');
define('SYLLABUS_TYPE_PRIVATE', 'private');

define('SYLLABUS_ACTION_ADD', 'add');
define('SYLLABUS_ACTION_DELETE', 'delete');
define('SYLLABUS_ACTION_EDIT', 'edit');
define('SYLLABUS_ACTION_VIEW', 'view');
define('SYLLABUS_ACTION_CONVERT', 'convert');

/**
 * Syllabus manager class.
 * 
 * Main syllabus class. Used to access all syllabus functionality and
 * information.
 * 
 * @copyright   2012 UC Regents
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class syllabus_manager {
    /** @var int The ID of the course for the syllabus. */
    private $courseid;

    /** @var array Configuration for the file picker. */
    private $filemanagerconfig;

    /**
     * Construct a syllabus manager.
     * 
     * @param stdClass $course
     */
    public function __construct($course) {
        $this->courseid = $course->id;

        // Configuration for file picker.
        $maxbytes = get_max_upload_file_size(0, $course->maxbytes);
        $this->filemanagerconfig = array('subdirs' => 0,
                'maxbytes' => $maxbytes, 'maxfiles' => 1,
                'accepted_types' => array('*'));
        // Accept everything '*' - restricting to 'documents' does not seem to work.
    }

    /**
     * Returns if logged in user has the ability to manage syllabi for course.
     * 
     * @return bool
     */
    public function can_manage() {
        $coursecontext = context_course::instance($this->courseid);
        return has_capability('local/syllabus:managesyllabus',
                $coursecontext);
    }

    /**
     * Deletes given syllabus.
     * 
     * @param syllabus $syllabus   Expecting an object that is derived from
     *                             the syllabus class
     */
    public function delete_syllabus($syllabus) {
        global $DB;
        // Do some sanity checks.

        // Make sure parameter is valid object.
        if (!is_object($syllabus) || !($syllabus instanceof syllabus) ||
                empty($syllabus->id)) {
            print_error('err_syllabus_notexist', 'local_syllabus');
        }

        // Make sure that syllabus belongs to course.
        if ($syllabus->courseid != $this->courseid) {
            print_error('err_syllabus_mismatch', 'local_syllabus');
        }

        // First, delete files if they exist.  We may have URL-only syllabus.
        if (!empty($syllabus->stored_file)) {
            $syllabus->stored_file->delete();
        }

        // Next, delete entry in syllabus table.
        $DB->delete_records('syllabus', array('id' => $syllabus->id));

        // Data to handle events.
        $data = new stdClass();
        $data->courseid = $syllabus->courseid;
        $data->access_type = $syllabus->access_type;

        // Trigger necessary events.
        events_trigger('syllabus_deleted', $data);
    }

    /**
     * Convert between public or private syllabi.
     * 
     * @param stdClass $syllabus   Expecting an object that is derived from
     *                             the syllabus class
     * @param int $convertto       SYLLABUS_TYPE_PUBLIC | SYLLABUS_TYPE_PRIVATE
     */
    public function convert_syllabus($syllabus, $convertto) {
        global $DB;

        if (empty($syllabus)) {
            print_error('err_syllabus_notexist', 'local_syllabus');
        }

        // Make sure parameter is valid object.
        if (!is_object($syllabus) || !($syllabus instanceof syllabus) ||
                empty($syllabus->id)) {
            print_error('err_syllabus_notexist', 'local_syllabus');
        }

        // Make sure that syllabus belongs to course.
        if ($syllabus->courseid != $this->courseid) {
            print_error('err_syllabus_mismatch', 'local_syllabus');
        }

        // If a public and private syllabus already exists, then we cannot
        // convert the syllabus.
        if (self::has_public_syllabus($this->courseid) &&
                self::has_private_syllabus($this->courseid)
                ) {
            print_error('err_syllabus_convert', 'local_syllabus');
        }

        $data = new StdClass();
        $data->id = $syllabus->id;
        $data->courseid = $syllabus->courseid;
        $data->display_name = $syllabus->display_name;
        $data->access_type = $convertto;
        $data->is_preview = $syllabus->is_preview;
        $DB->update_record('syllabus', $data);

        $olddata = $data;
        $olddata->access_type = $syllabus->access_type;

        // Trigger events.
        events_trigger('syllabus_deleted', $olddata);
        events_trigger('syllabus_added', $data);
    }

    /**
     * Returns file picker config array.
     * 
     * @return array
     */
    public function get_filemanager_config() {
        return $this->filemanagerconfig;
    }

    /**
     * Site menu block hook.
     * 
     * Only display node if there is a syllabus uploaded. If no syllabus 
     * uploaded, then display node if logged in user has the ability to add one.
     * 
     * @return navigation_node
     */
    public function get_navigation_nodes() {
        global $USER;
        $nodename = null;
        $retval = null;

        // Is there a syllabus uploaded?
        $syllabi = $this->get_syllabi();

        if (!empty($syllabi[SYLLABUS_TYPE_PRIVATE]) &&
                $syllabi[SYLLABUS_TYPE_PRIVATE]->can_view()) {
            // See if logged in user can view private syllabus.
            $nodename = $syllabi[SYLLABUS_TYPE_PRIVATE]->display_name;
        } else if (!empty($syllabi[SYLLABUS_TYPE_PUBLIC]) &&
                $syllabi[SYLLABUS_TYPE_PUBLIC]->can_view()) {
            // Fallback on trying to see if user can view public syllabus.
            $nodename = $syllabi[SYLLABUS_TYPE_PUBLIC]->display_name;
        } else if ($this->can_manage() && !empty($USER->editing)) {
            // If no syllabus, then only show node for instructors to add a
            // syllabus when in editing mode.
            $nodename = get_string('syllabus_needs_setup', 'local_syllabus');
        }
        if (!empty($nodename)) {
            $url = new moodle_url('/local/syllabus/index.php',
                    array('id' => $this->courseid));
            $retval = navigation_node::create($nodename, $url,
                    navigation_node::TYPE_SECTION);
        }

        return $retval;
    }

    /**
     * Returns an array of syllabi for course indexed by type.
     * 
     * @return array
     */
    public function get_syllabi() {
        global $DB;
        $retval = array(SYLLABUS_TYPE_PUBLIC => null,
                         SYLLABUS_TYPE_PRIVATE => null);

        // Get all syllabus entries for course.
        $records = $DB->get_records('syllabus',
                array('courseid' => $this->courseid));

        foreach ($records as $record) {
            switch ($record->access_type) {
                case SYLLABUS_ACCESS_TYPE_PUBLIC:
                case SYLLABUS_ACCESS_TYPE_LOGGEDIN:
                    $retval[SYLLABUS_TYPE_PUBLIC] =
                            new public_syllabus($record->id);
                    break;
                case SYLLABUS_ACCESS_TYPE_PRIVATE:
                    $retval[SYLLABUS_TYPE_PRIVATE] =
                            new private_syllabus($record->id);
                    break;
            }
        }

        return $retval;
    }

    /**
     * Checks if given course has a private syllabus. If so, then returns 
     * syllabus id, otherwise false.
     * 
     * @param int $courseid
     * 
     * @return int              Returns false if no syllabus found
     */
    public static function has_private_syllabus($courseid) {
        global $DB;

        $where = 'courseid=:courseid AND access_type=:private';
        $result = $DB->get_field_select('syllabus', 'id', $where,
                array('courseid' => $courseid,
                      'private' => SYLLABUS_ACCESS_TYPE_PRIVATE));

        return $result;
    }

    /**
     * Checks if given course has a public syllabus. If so, then returns 
     * syllabus id, otherwise false.
     * 
     * @param int $courseid
     * 
     * @return int              Returns false if no syllabus found
     */
    public static function has_public_syllabus($courseid) {
        global $DB;

        $where = 'courseid=:courseid AND (access_type=:public OR access_type=:loggedin)';
        $result = $DB->get_field_select('syllabus', 'id', $where,
                array('courseid' => $courseid,
                      'public' => SYLLABUS_ACCESS_TYPE_PUBLIC,
                      'loggedin' => SYLLABUS_ACCESS_TYPE_LOGGEDIN));

        return $result;
    }

    /**
     * Checks if course has any type of syllabus. If so, then returns true,
     * otherwise false.
     *
     * @return bool
     */
    public function has_syllabus() {
        global $DB;

        return $DB->record_exists('syllabus',
                array('courseid' => $this->courseid));
    }

    /**
     * Returns an appropriately public or private syllabus.
     * 
     * @param int $entryid
     * @return mixed, a public/private syllabus, or null
     */
    public static function instance($entryid) {
        global $DB;

        // First find access_type so we know which.
        $accesstype = $DB->get_field('syllabus', 'access_type',
                array('id' => $entryid));

        // Cast it to the appropiate object type.
        switch ($accesstype) {
            case SYLLABUS_ACCESS_TYPE_PUBLIC:
            case SYLLABUS_ACCESS_TYPE_LOGGEDIN:
                return new public_syllabus($entryid);
            case SYLLABUS_ACCESS_TYPE_PRIVATE:
                return new private_syllabus($entryid);
        }

        return null;
    }

    /**
     * Saves given syllabus data. Can either be an update (id must be set) or
     * insert as a new record.
     * 
     * @param object $data  Data from syllabus form. Assumes it is validated
     * 
     * @return int          Returns recordid of added/updated syllabus
     */
    public function save_syllabus($data) {
        global $DB;

        // First create a entry in syllabus table.
        $syllabusentry = new stdClass();
        $recordid = null;
        $eventname = '';

        $syllabusentry->courseid      = $data->id;
        $syllabusentry->display_name  = $data->display_name;
        $syllabusentry->access_type   = $data->access_types['access_type'];
        $syllabusentry->is_preview    = isset($data->is_preview) ? 1 : 0;
        $syllabusentry->url           = $data->syllabus_url;
        $syllabusentry->timemodified  = time();

        if (isset($data->entryid)) {
            // If id passed, then we are updating a current record.

            // Do quick sanity check to make sure that syllabus entry exists.
            $result = $DB->record_exists('syllabus', array('id' => $data->entryid,
                    'courseid' => $data->id));
            if (empty($result)) {
                print_error(get_string('err_syllabus_mismatch', 'local_syllabus'));
            }
            $recordid = $data->entryid;
            $syllabusentry->id = $data->entryid;

            $DB->update_record('syllabus', $syllabusentry);

            $eventname = 'syllabus_updated';
        } else {
            // Save when this syllabi was created.
            $syllabusentry->timecreated  = time();

            // Insert new record.
            $recordid = $DB->insert_record('syllabus', $syllabusentry);
            if (empty($recordid)) {
                print_error(get_string('cannnot_make_db_entry', 'local_syllabus'));
            }

            $eventname = 'syllabus_added';
        }

        // Then save file, with link to syllabus.
        $coursecontext = context_course::instance($this->courseid);
        file_save_draft_area_files($data->syllabus_file,
                $coursecontext->id, 'local_syllabus', 'syllabus',
                $recordid, $this->filemanagerconfig);

        // No errors, so trigger events.
        events_trigger($eventname, $recordid);

        return $recordid;
    }

}

/**
 * Syllabus class.
 * 
 * Class for properties shared between public and private syllabi.
 * 
 * @copyright   2012 UC Regents
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class syllabus {
    /** 
     * Syllabus properties.
     * 
     * @var mixed In following format:
     *                  [columns from syllabus table]
     *                  ['stored_file'] => stored_file object
     */
    private $properties = null;

    /**
     * Constructor.
     * 
     * If syllabus id is passed, then will fill properties for object. Else,
     * can be used as a shell to save data to create a new syllabus file.
     * 
     * @param int $syllabusid
     */
    public function __construct($syllabusid=null) {
        global $DB;
        if (!empty($syllabusid)) {
            $this->properties = $DB->get_record('syllabus', array('id' => $syllabusid));
            if (empty($this->properties)) {
                throw moodle_exception('Invalid syllabus id');
            }
        } else {
            $this->properties = new stdClass();
        }
    }

    /**
     * Magic getting method.
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        // Lazy load stored_file, since it is pretty complex.
        if ($name == 'stored_file') {
            if (!isset($this->properties->stored_file)) {
                $this->properties->stored_file =  $this->locate_syllabus_file();
            }
        }

        if (!isset($this->properties) || !isset($this->properties->$name)) {
            debugging('syllabus called with invalid $name: ' . $name);
            return null;
        }
        return $this->properties->$name;
    }

    /**
     * Magic isset method.
     * 
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        // Lazy load stored_file, since it is pretty complex.
        if ($name == 'stored_file') {
            $this->stored_file;
        }
        return isset($this->properties->$name);
    }

    /**
     * Magic setter method.
     * 
     * @param string $name name of property to set
     * @param mixed $value value to set the property
     * @return mixed
     */
    public function __set($name, $value) {
        return $this->properties->$name = $value;
    }

    /**
     * Magic unset method.
     * 
     * @param string $name
     * @return bool
     */
    public function __unset($name) {
        unset($this->properties->$name);
    }

    /**
     * Determine if user can view syllabus.
     * 
     * @return bool
     */
    abstract public function can_view();

    /**
     * Returns link to download syllabus file.
     * 
     * @return string   Returns html to generate link to syllabus
     */
    public function get_download_link() {
        $fullurl = $this->get_file_url();
        if (empty($fullurl)) {
            return '';
        }
        $string = html_writer::link($fullurl, get_string('clicktodownload',
                'local_syllabus', $this->properties->display_name));

        return $string;
    }

    /**
     * Get url to syllabus file.
     * 
     * @return  Returns full path to syllabus file, otherwise returns empty string
     */
    public function get_file_url() {
        global $CFG;

        if (empty($this->properties) || !isset($this->stored_file)) {
            return '';
        }

        $file = $this->stored_file;

        $url = "{$CFG->wwwroot}/pluginfile.php/{$file->get_contextid()}/local_syllabus/syllabus";
        $file = $this->stored_file;
        $filename = $file->get_filename();
        $fileurl = $url.$file->get_filepath().$file->get_itemid().'/'.$filename;

        return $fileurl;
    }

    /**
     * Returns mimetype of uploaded syllabus file.
     * 
     * @return string
     */
    public function get_mimetype() {
        if (!isset($this->stored_file)) {
            return '';
        }
        return $this->stored_file->get_mimetype();
    }

    /**
     * Returns syllabus file for syllabus object. Must have properties->id set
     * 
     * @return stored_file          Returns stored_file object, if file was 
     *                              uploaded, otherwise returns null.
     */
    private function locate_syllabus_file() {
        $retval = null;

        if (empty($this->properties->id) || empty($this->properties->courseid)) {
            return null;
        }

        $coursecontext = context_course::instance($this->properties->courseid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($coursecontext->id, 'local_syllabus',
                'syllabus', $this->properties->id, '', false);

        // Should really have just one file uploaded, but handle weird cases.
        if (count($files) < 1 && empty($this->properties->url)) {
            // No files uploaded and no URL added!
            debugging('Warning, no file uploaded for given syllabus entry');
        } else {
            if (count($files) >1) {
                debugging('Warning, more than one syllabus file uploaded for given syllabus entry');
            }

            $retval = reset($files);
            unset($files);
        }

        return $retval;
    }
}

/**
 * Private syllabus class.
 * 
 * Inherits abilities from syllabus class, but defines
 * its own viewing function which differrs from those of
 * public syllabi.
 * 
 * @copyright   2012 UC Regents
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class private_syllabus extends syllabus {
    /**
     * Determine if user can view syllabus.
     * 
     * @return bool
     */
    public function can_view() {
        // Need to check if we have URL.
        if (empty($this->url)) {
            $coursecontext = context::instance_by_id($this->stored_file->get_contextid());
        } else {
            $coursecontext = context_course::instance($this->courseid);
        }
        return is_enrolled($coursecontext) ||
                has_capability('local/syllabus:managesyllabus', $coursecontext);
    }
}

/**
 * Public syllabus class.
 * 
 * Inherits abilities from syllabus class, but defines
 * its own viewing function which differrs from those of 
 * private syllabi.
 * 
 * @copyright   2012 UC Regents
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class public_syllabus extends syllabus {
    /**
     * Determine if user can view syllabus.
     * 
     * @return bool
     */
    public function can_view() {
        $retval = false;
        // Check access type.
        switch ($this->access_type) {
            case SYLLABUS_ACCESS_TYPE_PUBLIC:
                $retval = true;
                break;
            case SYLLABUS_ACCESS_TYPE_LOGGEDIN:
                if (isloggedin() && !isguestuser()) {
                    $retval = true;
                }
                break;
            default:
                break;
        }

        return $retval;
    }
}

/**
 * Sets the editing button in the $PAGE element to be the url passed in.
 * 
 * Code copied from fragments of code in course/view.php to set the "Turn 
 * editing on/off" button.
 * 
 * 
 * @param moodle_url $url   Expecting moodle_url object. If null, then defaults
 *                          redirecting user to $PAGE->url
 */
function set_editing_mode_button($url=null) {
    global $OUTPUT, $PAGE, $USER;

    if (empty($url)) {
        $url = $PAGE->url;
    }

    // See if user is trying to turn editing on/off.
    $edit = optional_param('edit', -1, PARAM_BOOL);
    if (!isset($USER->editing)) {
        $USER->editing = 0;
    }
    if ($PAGE->user_allowed_editing()) {
        if (($edit == 1) and confirm_sesskey()) {
            $USER->editing = 1;
            // Edited to use url specified in function.
            redirect($url);
        } else if (($edit == 0) and confirm_sesskey()) {
            $USER->editing = 0;
            if (!empty($USER->activitycopy) && $USER->activitycopycourse == $course->id) {
                $USER->activitycopy       = false;
                $USER->activitycopycourse = null;
            }
            // Edited to use url specified in function.
            redirect($url);
        }
        // Edited to use url specified in function.
        $buttons = $OUTPUT->edit_button($url);
        $PAGE->set_button($buttons);
    } else {
        $USER->editing = 0;
    }
}

/**
 * Displays flash successful messages from session.
 */
function flash_display() {
    global $OUTPUT;
    if (isset($_SESSION['flash_success_msg'])) {
        echo $OUTPUT->notification($_SESSION['flash_success_msg'], 'notifysuccess');
        unset($_SESSION['flash_success_msg']);
    }
}

/**
 * Copies the $success_msg in a session variable to be used on redirected page
 * via flash_display()
 *
 * @param moodle_url|string $url A moodle_url to redirect to. Strings are not to be trusted!
 * @param string $successmessage The message to display to the user
 */
function flash_redirect($url, $successmessage) {
    // Message to indicate to user that content was edited.
    $_SESSION['flash_success_msg']  = $successmessage;
    redirect($url);
}
