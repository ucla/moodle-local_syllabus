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
 * Event handlers for events.
 *
 * @package    local_ucla_syllabus
 * @copyright  2013 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/local/ucla_syllabus/locallib.php');

/**
 * Delete a course's syllabus when a course is deleted.
 *
 * NOTE: Unfortunately cannot use ucla_syllabus_manager to delete syllabus
 * entry and files, because course context is already deleted. Need to manually
 * find the syllabus entries and delete associated files.
 *
 * @param object $course
 */
function delete_syllabi($course) {
    global $DB;

    // Get all syllabus entries for course.
    $syllabi = $DB->get_records('ucla_syllabus',
            array('courseid' => $course->id));

    if (empty($syllabi)) {
        return true;
    }

    $fs = get_file_storage();
    foreach ($syllabi as $syllabus) {
        // Delete any files associated with syllabus entry.
        $files = $fs->get_area_files($course->context->id,
                'local_ucla_syllabus', 'syllabus', $syllabus->id, '', false);
        if (!empty($files)) {
            foreach ($files as $file) {
                $file->delete();
            }
        }

        // Next, delete entry in syllabus table.
        $DB->delete_records('ucla_syllabus', array('id' => $syllabus->id));

        // This is the data needed to handle events.
        $data = new stdClass();
        $data->courseid = $course->id;
        $data->access_type = $syllabus->access_type;

        // Trigger any necessary events.
        events_trigger('ucla_syllabus_deleted', $data);
    }
}
