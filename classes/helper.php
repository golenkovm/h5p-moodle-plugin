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

namespace mod_hvp;

/**
 * Helper class for plugin.
 *
 * @package    mod_hvp
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * Get an installed library.
     *
     * @param array $options Array containing either id or a title and version number.
     * @return \stdClass Record from hvp_libraries.
     */
    public static function get_library(array $options): \stdClass {
        global $DB;
        $defaults = [
            'id' => '',
            'title' => '',
            'version' => '',
        ];

        // Merge valid $options into list of default values.
        foreach ($defaults as $key => $value) {
            if (array_key_exists($key, $options)) {
                $defaults[$key] = $options[$key];
            }
        }
        $options = $defaults;

        if (!empty($options['id'])) {
            $libraries = $DB->get_records('hvp_libraries', ['id' => $options['id']]);
        } else if (!empty($options['title']) && !empty($options['version'])) {
            $versionparts = explode('.', $options['version']);
            if (empty($versionparts) || count($versionparts) !== 3) {
                throw new \moodle_exception('error:invalidversionformat', 'hvp');
            }
            $libraries = $DB->get_records('hvp_libraries', [
                'title' => $options['title'],
                'major_version' => $versionparts[0],
                'minor_version' => $versionparts[1],
                'patch_version' => $versionparts[2],
            ]);
        }

        if (empty($libraries)) {
            throw new \moodle_exception('error:nolibraryexists', 'hvp');
        }

        if (count($libraries) !== 1) {
            throw new \moodle_exception('error:multiplelibrariesfound', 'hvp');
        }

        return reset($libraries);
    }

    /**
     * Get list of libraries dependant on provided library.
     *
     * @param \stdClass $library An hvp_library record.
     * @return array List of titles of libraries depending on provided library.
     */
    public static function get_dependant_libraries(\stdClass $library): array {
        global $DB;
        $titles = [];
        $framework = \mod_hvp\framework::instance('interface');
        list('content' => $content, 'libraries' => $dependentlibraries) = $framework->getLibraryUsage($library->id);

        if (empty($dependentlibraries)) {
            return $titles;
        }

        // If dependencies are found, get a list of titles with version number.
        $dependencies = $DB->get_records('hvp_libraries_libraries', ['required_library_id' => $library->id],
            '', 'library_id');
        $dependencies = array_keys($dependencies);
        list($insql, $params) = $DB->get_in_or_equal($dependencies);
        $sql = "SELECT id, title, major_version, minor_version, patch_version
                  FROM {hvp_libraries} l
                 WHERE id " . $insql;
        $dependencyrecords = $DB->get_records_sql($sql, $params);
        $titles = array_map(function($record) {
            return $record->title . " " . $record->major_version . "." . $record->minor_version . "." . $record->patch_version;
        }, $dependencyrecords);
        return $titles;
    }

    /**
     * Remove activities being used by library.
     *
     * @param \stdClass $library An hvp_library record.
     * @param bool $dryrun If dryrun do not make DB changes.
     * @return int Number of activities to be deleted or successfully deleted depending on dry run.
     */
    public static function remove_dependant_activities(\stdClass $library, bool $dryrun = true): int {
        global $DB;
        $activities = $DB->get_records('hvp', ['main_library_id' => $library->id]);

        // If it's a dry run or there are no activities to be deleted, then we only need the count.
        if ($dryrun || empty($activities)) {
            return count($activities);
        }

        // If an error occurs during an activity deletion, an exception will be thrown.
        foreach ($activities as $activity) {
            list($course, $cm) = get_course_and_cm_from_instance($activity->id, 'hvp');
            course_delete_module($cm->id);
        }
        return count($activities);
    }
}
