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

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/modinfolib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/hvp/lib.php');

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'id' => '',
    'title' => '',
    'version' => '',
    'run' => false,
    'force' => false,
], [
    'h' => 'help',
    'r' => 'run',
    'f' => 'force',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(get_string('cli:help', 'hvp'));
    exit(0);
}

$framework = \mod_hvp\framework::instance('interface');

// Get the installed library objects.
try {
    $library = \mod_hvp\helper::get_library($options);
} catch (moodle_exception $e) {
    cli_error($e->getMessage());
}

// 1. Check if there is any dependency on the lib about to be deleted and stop process with message if one is found.
$dependantlibraries = \mod_hvp\helper::get_dependant_libraries($library);
if (!empty($dependantlibraries) && empty($options['force'])) {
    cli_error('The library can\'t be deleted as it is a dependency for other libraries: ' . implode(',', $dependantlibraries)
            . ' Use --force to delete the library, leaving orphaned dependencies that may break the library tree.');
} else {
    cli_writeln("Library '{$library->title} {$library->major_version}.{$library->minor_version}.{$library->patch_version}' can be deleted.");
}

// 2. Search for all activities that are using the lib and delete them.
try {
    $countactivities = \mod_hvp\helper::remove_dependant_activities($library, !$options['run']);
} catch (moodle_exception $e) {
    if (!empty($options['force'])) {
        cli_writeln('The library can\'t be deleted as some activities couldn\'t be deleted');
        cli_error($e->getMessage());
    }
    cli_writeln('Some activities couldn\'t be deleted');
}
$message = $options['run'] ? $countactivities . ' activities successfully deleted.' : $countactivities . ' activities to be deleted.';
cli_writeln($message);

// 3. Delete the library. We need to make sure that we don't break the dependency tree.
if ($options['run']) {
    $framework->deleteLibrary($library);
    cli_writeln("Library '{$library->title} {$library->major_version}.{$library->minor_version}.{$library->patch_version}' was successfully deleted.");
} else {
    cli_writeln('Use option --run to commit changes.');
}
