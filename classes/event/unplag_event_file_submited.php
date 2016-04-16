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
namespace plagiarism_unplag\classes\event;

use core\event\base;
use plagiarism_unplag\classes\unplag_core;

require_once(dirname(__FILE__) . '/unplag_abstract_event.php');

/**
 * Class unplag_event_file_submited
 * @package plagiarism_unplag\classes\event
 */
class unplag_event_file_submited extends unplag_abstract_event {
    /** @var */
    private static $instance;

    /**
     * @return static
     */
    public static function instance() {
        return isset(static::$instance) ? static::$instance : static::$instance = new static;
    }

    /**
     * @param unplag_core $unplagcore
     * @param base        $event
     *
     * @return null
     */
    public function handle_event(unplag_core $unplagcore, base $event) {

        if (empty($event->other['pathnamehashes'])) {
            return null;
        }

        $file = get_file_storage()->get_file_by_hash($event->other['pathnamehashes'][0]);
        $plagiarismentity = $unplagcore->get_plagiarism_entity($file);
        $internalfile = $plagiarismentity->upload_file_on_unplag_server();

        mtrace('upload file');

        self::after_hanle_event($internalfile, $plagiarismentity);
    }
}