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
 * Database events.
 * 
 * This file contains the event handlers for the Moodle event API.
 * 
 * @package local_ucla_syllabus
 * @subpackage db
 * @copyright 2012 UC Regents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$handlers = array (
    'course_deleted' => array(
        'handlerfile'      => '/local/ucla_syllabus/eventlib.php',
        'handlerfunction'  => 'delete_syllabi',
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
);