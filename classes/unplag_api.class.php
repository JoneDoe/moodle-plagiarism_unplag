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
 * unplag_api.class.php - SDK for working with unplag api.
 *
 * @package    plagiarism_unplag
 * @author     Vadim Titov <v.titov@p1k.co.uk>
 * @copyright  UKU Group, LTD, https://www.unplag.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace plagiarism_unplag\classes;

require_once('unplag_api_request.php');

/**
 * Class unplag_api
 * @package plagiarism_unplag\classes
 */
class unplag_api {
    private static $instance = null;
    /** @var string */
    private static $checktype = 'web';

    /**
     * @return null|static
     */
    final public static function instance() {
        return isset(static::$instance) ? static::$instance : static::$instance = new static;
    }

    /**
     * @param \stored_file $file
     *
     * @return mixed
     * @throws \file_exception
     */
    public function upload_file(\stored_file $file) {
        $format = 'html';
        if ($source = $file->get_source()) {
            $format = pathinfo($source, PATHINFO_EXTENSION);
        }

        $postdata = [
            'format'    => $format,
            'file_data' => base64_encode($file->get_content()),
            'name'      => $file->get_filename(),
        ];

        return unplag_api_request::instance()->http_post()->request('file/upload', $postdata);
    }

    /**
     * @param \stdClass $file
     *
     * @return mixed
     */
    public function run_check(\stdClass $file) {
        global $CFG;

        if (empty($file)) {
            throw new \InvalidArgumentException('Invalid argument $file');
        }

        $postdata = [
            'type'         => self::$checktype,
            'file_id'      => $file->external_file_id,
            'callback_url' => sprintf('%1$s%2$s&token=%3$s', $CFG->wwwroot, UNPLAG_CALLBACK_URL, $file->identifier),
        ];

        return unplag_api_request::instance()->http_post()->request('check/create', $postdata);
    }

    /**
     * @param array $checkids
     *
     * @return mixed
     */
    public function get_check_progress(array $checkids) {
        if (empty($checkids)) {
            throw new \InvalidArgumentException('Invalid argument $checkids');
        }
        $postdata = [
            'id' => implode(',', $checkids),
        ];

        return unplag_api_request::instance()->http_get()->request('check/progress', $postdata);
    }

    /**
     * @param $id
     *
     * @return mixed
     */
    public function get_check_data($id) {
        if (empty($id)) {
            throw new \InvalidArgumentException('Invalid argument id');
        }

        $postdata = [
            'id' => $id,
        ];

        return unplag_api_request::instance()->http_get()->request('check/get', $postdata);
    }

    /**
     * @param \stdClass $file
     *
     * @return mixed
     */
    public function delete_check(\stdClass $file) {
        if (empty($file->check_id)) {
            throw new \InvalidArgumentException('Invalid argument check_id');
        }

        $postdata = [
            'id' => $file->check_id,
        ];

        return unplag_api_request::instance()->http_post()->request('check/delete', $postdata);
    }
}