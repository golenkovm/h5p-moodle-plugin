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
 * Pluginfile proxy script.
 *
 * @package   mod_hvp
 * @author    Kevin Pham <kevinpham@catalyst-au.net>
 * @copyright Catalyst IT, 2021
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_login();

$relativepath = get_file_argument();

if (empty($relativepath)) {
    exit();
}

// This should target the custom content types. We also filter out the
// Content-Length Header in this case to avoid truncation.
$jstobereplaced = stripos($relativepath, 'cachedassets') !== false;

$curl = new curl();

if (!empty($_SERVER['HTTP_RANGE'])) {
    $curl->setHeader("Range: {$_SERVER['HTTP_RANGE']}");
}

$curl->setopt([
    'CURLOPT_ENCODING'       => 'identity',
    'CURLOPT_CERTINFO'       => 1,
    'CURLOPT_SSL_VERIFYPEER' => true,
    'CURLOPT_HEADERFUNCTION' => function ($curl, $header) use ($jstobereplaced) {
        if (stripos($header, 'Set-Cookie') !== false) {
            // Do not set the cookies when returning to the client.
            return strlen($header);
        }

        if (!$jstobereplaced ||  stripos($header, 'content-length') === false) {
            header($header);
        }
        return strlen($header);
    },
    'CURLOPT_WRITEFUNCTION'     => function ($curl, $body) use ($jstobereplaced, $relativepath) {
        // Even if we modify the body, we need to return the original length.
        $originalbodylength = strlen($body);

        if ($jstobereplaced) {
            // Remove the transitionend event which occurs in the referenced
            // link, to prevent card focusing on page load, as this might be
            // included as embedded content, and a page scroll/focus does not
            // make sense.
            // https://github.com/h5p/h5p-dialogcards/blob/7a7580aad3424c60f45cb65eac52ff14bc83b540/src/scripts/h5p-dialogcards-card.js#L482-L484.
            $body = str_replace('.one("transitionend",', '.off("_removed_",', $body);
        }

        echo $body;

        return $originalbodylength;
    },
]);

$url = new moodle_url('/pluginfile.php/'.ltrim($relativepath, '/'));
$curl->get($url);
