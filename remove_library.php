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
 * Remove h5p library.
 *
 * @package    mod_hvp
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2022 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT;
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/modinfolib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/hvp/lib.php');

require_admin();

$options = [
    'help' => optional_param('help', false, PARAM_BOOL),
    'id' => optional_param('id', '', PARAM_RAW),
    'title' => optional_param('title', '', PARAM_RAW),
    'version' => optional_param('version', '', PARAM_RAW),
    'run' => optional_param('run', false, PARAM_BOOL),
    'force' => optional_param('force', false, PARAM_BOOL),
];

$output = '';

if ((empty($options['id']) && empty($options['title']) && empty($options['version']))
        || $options['help']) {
    $output = get_string('query:help', 'hvp');
} else {
    $framework = \mod_hvp\framework::instance('interface');

    // Get the installed library objects.
    $library = \mod_hvp\helper::get_library($options);

    // 1. Check if there is any dependency on the lib about to be deleted and stop process with message if one is found.
    $dependantlibraries = \mod_hvp\helper::get_dependant_libraries($library);
    if (!empty($dependantlibraries) && empty($options['force'])) {
        throw new moodle_exception('error:librarydependency', 'hvp', '', implode(',', $dependantlibraries));
    } else {
        $output .= html_writer::tag('p', "Library '{$library->title} {$library->major_version}.{$library->minor_version}.{$library->patch_version}' can be deleted.");
    }

    // 2. Search for all activities that are using the lib and delete them.
    $countactivities = 0;
    try {
        $countactivities = \mod_hvp\helper::remove_dependant_activities($library, !$options['run']);
    } catch (moodle_exception $e) {
        $output .= html_writer::tag('p', "The library can\'t be deleted as some activities couldn\'t be deleted");
        if (empty($options['force'])) {
            throw $e;
        }
    }
    $message = $options['run'] ? $countactivities . ' activities successfully deleted.' : $countactivities . ' activities to be deleted.';
    $output .= html_writer::tag('p', $message);

    // 3. Delete the library. We need to make sure that we don't break the dependency tree.
    if ($options['run']) {
        $framework->deleteLibrary($library);
        $output .= html_writer::tag('p', "Library '{$library->title} {$library->major_version}.{$library->minor_version}.{$library->patch_version}' was successfully deleted.");
    } else {
        $output .= html_writer::tag('p', "Use parameter 'run=true' to commit changes.");
    }
}

// Setup page.
$PAGE->set_url(new moodle_url('/mod/hvp/remove_library.php'));
$PAGE->set_context(context_system::instance());

// Render page.
echo $OUTPUT->header();
echo $output;
echo $OUTPUT->footer();
