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
 * Condition main class.
 *
 * @package availability_activeenrol
 * @copyright 2022 Jorge C.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_activeenrol;
defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core_availability\capability_checker;
use core_availability\info;
use course_enrolment_manager;
use dml_exception;

require_once($CFG->dirroot . '/enrol/locallib.php');


/**
 * Condition main class.
 *
 * @package availability_activeenrol
 * @copyright 2022 Jorge C.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode
     * @throws coding_exception If invalid data structure.
     */
    public function __construct(\stdClass $structure) {
        // Get enrolment method id.
        if (!property_exists($structure, 'id')) {
            $this->activeenrolid = 0;
        } else if (is_int($structure->id)) {
            $this->activeenrolid = $structure->id;
        } else {
            throw new coding_exception('Invalid ->id for enrolment method condition');
        }

        if (!property_exists($structure, 'valid')) {
            $this->activeenrolvalid = 'any';
        } else {
            $this->activeenrolvalid = $structure->valid;
        }
    }

    /**
     * Save.
     *
     * @return object|\stdClass $result
     */
    public function save() {
        $result = (object) array('type' => 'activeenrol');
        if ($this->activeenrolid) {
            $result->id = $this->activeenrolid;
        }
        if ($this->activeenrolvalid) {
            $result->valid = $this->activeenrolvalid;
        }
        return $result;
    }
    /**
     * Check if the item is available with this restriction.
     *
     * @param bool                    $not
     * @param info $info
     * @param bool                    $grabthelot
     * @param int                     $userid
     *
     * @return bool
     */
    public function is_available($not, info $info, $grabthelot, $userid) {
        global $PAGE;
        $course = $info->get_course();

        $allow = true;
        $manager = new course_enrolment_manager($PAGE, $course);
        $userenrolments = $manager->get_user_enrolments($userid);
        $userenrolids = array_column($userenrolments , 'enrolid');
        if (!in_array($this->activeenrolid, $userenrolids)) {
            $allow = false;
        } else {
            switch ($this->activeenrolvalid) {
                case 'enabled': {
                    // Don't check dates.
                    if ($userenrolments[$this->activeenrolid]->status != 0) {
                        $allow = false;
                    }
                    break;
                }
                case 'valid': {
                    if ($userenrolments[$this->activeenrolid]->status != 0 &&
                        (($userenrolments[$this->activeenrolid]->timestart > time()) ||
                        (($userenrolments[$this->activeenrolid]->timeend > 0) &&
                            ($userenrolments[$this->activeenrolid]->timeend < time())))) {
                        $allow = false;
                    }
                    break;
                }
            }
        }
        if ($not) {
            $allow = !$allow;
        }
        return $allow;
    }

    /**
     * Retrieve the description for the restriction.
     *
     * @param bool                    $full
     * @param bool                    $not
     * @param info $info
     *
     * @return string
     * @throws coding_exception
     */
    public function get_description($full, $not, info $info) {
        global $PAGE;
        if ($this->activeenrolid) {
            $course = $info->get_course();
            $manager = new course_enrolment_manager($PAGE, $course);
            $activeenrolnames = $manager->get_enrolment_instance_names(true);

            // If it still doesn't exist, it must have been misplaced.
            if (!array_key_exists($this->activeenrolid, $activeenrolnames)) {
                $name = get_string('missing', 'availability_activeenrol');
            } else {
                // Not safe to call format_string here; use the special function to call it later.
                $name = self::description_format_string($activeenrolnames[$this->activeenrolid]);
            }
        }

        switch ($this->activeenrolvalid) {
            case 'any': {
                return get_string($not ? 'requires_notenrol' : 'requires_enrol',
                        'availability_activeenrol', $name);
            }
            case 'enabled': {
                return get_string($not ? 'requires_notenabledenrol' : 'requires_enabledenrol',
                        'availability_activeenrol', $name);
            }
            case 'valid': {
                return get_string($not ? 'requires_notvaliddenrol' : 'requires_validdenrol',
                        'availability_activeenrol', $name);
            }
        }
    }

    /**
     * Retrieve debugging string.
     *
     * @return string
     */
    protected function get_debug_string() {
        return $this->activeenrolid ? '#' . $this->activeenrolid : 'any';
    }

    /**
     * Adding the availability to restored course items.
     *
     * @param string       $restoreid
     * @param int          $courseid
     * @param \base_logger $logger
     * @param string       $name
     *
     * @return bool
     * @throws dml_exception
     */
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        global $DB;
        if (!$this->activeenrolid) {
            return false;
        }
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'enrol', $this->activeenrolid);
        if (!$rec || !$rec->newitemid) {
            // If we are on the same course (e.g. duplicate) then we can just
            // use the existing one.
            if ($DB->record_exists('enrol',
                    array('id' => $this->activeenrolid, 'courseid' => $courseid))) {
                return false;
            }
            // Otherwise it's a warning.
            $this->activeenrolid = -1;
            $logger->process('Restored item (' . $name .
                    ') has availability condition on enrolment method that was not restored',
                    \backup::LOG_WARNING);
        } else {
            $this->activeenrolid = (int) $rec->newitemid;
        }
        return true;
    }

    /**
     * Checks whether this condition applies to user lists.
     * @return bool
     */
    public function is_applied_to_user_lists() {
        // Enrolment method conditions are assumed to be 'permanent', so they affect the
        // display of user lists for activities.
        return true;
    }

    /**
     * Tests against a user list. Users who cannot access the activity due to
     * availability restrictions will be removed from the list.
     *
     * @param array $users Array of userid => object
     * @param bool $not If tree's parent indicates it's being checked negatively
     * @param info $info Info about current context
     * @param capability_checker $checker Capability checker
     * @return array Filtered version of input array
     */
    public function filter_user_list(array $users, $not, info $info,
            \core_availability\capability_checker $checker) {
        global $PAGE;

        // If the array is empty already, just return it.
        if (!$users) {
            return $users;
        }

        $course = $info->get_course();
        // List users for this course who match the condition.

        $manager = new course_enrolment_manager($PAGE, $course);

        // Filter the user list.
        $result = array();
        foreach ($users as $id => $user) {
            $userenrolments = $manager->get_user_enrolments($id);
            $allow = false;

            foreach ($userenrolments as $userenrolment) {
                if ($this->activeenrolid === (int) $userenrolment->enrolid) {
                    switch ($this->activeenrolvalid) {
                        case 'enabled': {
                            if ($userenrolment->status == 0) {
                                $allow = true;
                            }
                            break;
                        }
                        case 'valid': {
                            if ($userenrolment->status == 0 && 
                                $userenrolment->datestart > time() &&
                                    ($userenrolment->dateend <= time() || $userenrolment->dateend == 0)
                                ) {
                                $allow = true;
                            }
                            break;
                        }
                        default: {
                            $allow = true;
                        }
                    }
                    break;
                }
            }

            if ($not) {
                $allow = !$allow;
            }
            if ($allow) {
                $result[$id] = $user;
            }
        }
        return $result;
    }
}
