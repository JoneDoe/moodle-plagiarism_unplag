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
 * default_settings.php - Displays default values to use inside assignments for UNPLAG
 *
 * @author      Dan Marsden <Dan@danmarsden.com>
 * @author      Mikhail Grinenko <m.grinenko@p1k.co.uk>
 * @copyright   2014 Dan Marsden <Dan@danmarsden.com>
 * @copyright   UKU Group, LTD, https://www.unplag.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use plagiarism_unplag\classes\unplag_core;

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->dirroot . '/plagiarism/unplag/lib.php');

require_login();
admin_externalpage_setup('plagiarismunplag');

//$context = context_system::instance();

$id = optional_param('id', 0, PARAM_INT);
$resetuser = optional_param('reset', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$sort = optional_param('tsort', '', PARAM_ALPHA);
$dir = optional_param('dir', '', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);

$exportfilename = 'UnplagDebugOutput.csv';

$limit = 20;
$baseurl = new moodle_url('debugging.php', ['page' => $page, 'sort' => $sort]);

$table = new flexible_table('unplagfiles');

// Get list of Events in queue.
$a = new stdClass();
$a->countallevents = $DB->count_records('events_queue_handlers');
$a->countheld = $DB->count_records_select('events_queue_handlers', 'status > 0');

if (!$table->is_downloading($download, $exportfilename)) {
    echo $OUTPUT->header();
    $currenttab = 'unplagdebug';

    require_once('unplag_tabs.php');

    $lastcron = $DB->get_field_sql('SELECT MAX(lastcron) FROM {modules}');
    if ($lastcron < time() - 3600 * 0.5) { // Check if run in last 30min.
        echo $OUTPUT->box(get_string('cronwarning', 'plagiarism_unplag'), 'generalbox admin warning');
    }

    $warning = '';
    if (!empty($a->countallevents)) {
        $warning = ' warning';
    }

    if ($resetuser == 1 && $id && confirm_sesskey()) {
        if (plagiarism_plugin_unplag::unplag_reset_file($id)) {
            echo $OUTPUT->notification(get_string('fileresubmitted', 'plagiarism_unplag'));
        }
    } else if ($resetuser == 2 && $id && confirm_sesskey()) {
        $plagiarismfile = $DB->get_record('plagiarism_unplag_files', ['id' => $id], '*', MUST_EXIST);
        $file = plagiarism_plugin_unplag::unplag_get_score(unplag_core::get_settings(), $plagiarismfile, true);
        // Reset attempts as this was a manual check.
        $file->attempt = $file->attempt - 1;
        $DB->update_record('plagiarism_unplag_files', $file);
        if ($file->statuscode == UNPLAG_STATUSCODE_ACCEPTED) {
            echo $OUTPUT->notification(get_string('scorenotavailableyet', 'plagiarism_unplag'));
        } else if ($file->statuscode == UNPLAG_STATUSCODE_PROCESSED) {
            echo $OUTPUT->notification(get_string('scoreavailable', 'plagiarism_unplag'));
        } else {
            echo $OUTPUT->notification(get_string('unknownwarning', 'plagiarism_unplag'));
            print_object($file);
        }
    }

    if (!empty($delete) && confirm_sesskey()) {
        $DB->delete_records('plagiarism_unplag_files', ['id' => $id]);
        echo $OUTPUT->notification(get_string('filedeleted', 'plagiarism_unplag'));
    }
}
$heldevents = [];

// Now show files in an error state.
$userfields = get_all_user_name_fields(true, 'u');
$sqlallfiles = "SELECT t.*, " . $userfields . ", m.name as moduletype, " .
    "cm.course as courseid, cm.instance as cminstance FROM " .
    "{plagiarism_unplag_files} t, {user} u, {modules} m, {course_modules} cm " .
    "WHERE m.id=cm.module AND cm.id=t.cm AND t.userid=u.id " .
    "AND t.errorresponse is not null ";

$sqlcount = "SELECT COUNT(id) FROM {plagiarism_unplag_files} WHERE statuscode <> 'Analyzed'";

// Now do sorting if specified.
$orderby = '';
if (!empty($sort)) {
    if ($sort == "name") {
        $orderby = " ORDER BY u.firstname, u.lastname";
    } else if ($sort == "module") {
        $orderby = " ORDER BY cm.id";
    } else if ($sort == "status") {
        $orderby = " ORDER BY t.errorresponse";
    } else if ($sort == "id") {
        $orderby = " ORDER BY t.id";
    }
    if (!empty($orderby) && ($dir == 'asc' || $dir == 'desc')) {
        $orderby .= " " . $dir;
    }
}

$count = $DB->count_records_sql($sqlcount);

$unplagfiles = $DB->get_records_sql($sqlallfiles . $orderby, null, $page * $limit, $limit);

$table->define_columns(['id', 'name', 'module', 'identifier', 'status', 'attempts', 'action']);

$table->define_headers([
    get_string('id', 'plagiarism_unplag'),
    get_string('user'),
    get_string('module', 'plagiarism_unplag'),
    get_string('identifier', 'plagiarism_unplag'),
    get_string('status', 'plagiarism_unplag'),
    get_string('attempts', 'plagiarism_unplag'), '',
]);
$table->define_baseurl('debugging.php');
$table->sortable(true);
$table->no_sorting('file', 'action');
$table->collapsible(true);

$table->set_attribute('cellspacing', '0');
$table->set_attribute('class', 'generaltable generalbox');

$table->show_download_buttons_at([TABLE_P_BOTTOM]);
$table->setup();

$fs = get_file_storage();
foreach ($unplagfiles as $tf) {
    $modulecontext = context_module::instance($tf->cm);
    $coursemodule = get_coursemodule_from_id($tf->moduletype, $tf->cm);

    $user = "<a href='" . $CFG->wwwroot . "/user/profile.php?id=" . $tf->userid . "'>" . fullname($tf) . "</a>";
    if ($tf->statuscode == UNPLAG_STATUSCODE_ACCEPTED) { // Sanity Check.
        $reset = '<a href="debugging.php?reset=2&id=' . $tf->id . '&sesskey=' . sesskey() . '">' .
            get_string('getscore', 'plagiarism_unplag') . '</a> | ';
    } else {
        $reset = '<a href="debugging.php?reset=1&id=' . $tf->id . '&sesskey=' . sesskey() . '">' .
            get_string('resubmit', 'plagiarism_unplag') . '</a> | ';
    }
    $reset .= '<a href="debugging.php?delete=1&id=' . $tf->id . '&sesskey=' . sesskey() . '">' . get_string('delete') . '</a>';
    $cmurl = new moodle_url($CFG->wwwroot . '/mod/' . $tf->moduletype . '/view.php', ['id' => $tf->cm]);
    $cmlink = html_writer::link($cmurl, shorten_text($coursemodule->name, 40, true), ['title' => $coursemodule->name]);
    if ($table->is_downloading()) {
        $row = [$tf->id, $tf->userid, $tf->cm . ' ' . $tf->moduletype, $tf->identifier, $tf->statuscode, $tf->attempt, $tf->errorresponse];
    } else {
        $row = [$tf->id, $user, $cmlink, $tf->identifier, $tf->errorresponse, $tf->attempt, $reset];
    }

    $table->add_data($row);
}

if ($table->is_downloading()) {
    // Include some extra debugging information in the table.
    // Add some extra lines first.
    $table->add_data([]);
    $table->add_data([]);
    $table->add_data([]);
    $table->add_data([]);
    $lastcron = $DB->get_field_sql('SELECT MAX(lastcron) FROM {modules}');
    if ($lastcron < time() - 3600 * 0.5) { // Check if run in last 30min.
        $table->add_data(['', 'errorcron', 'lastrun: ' . userdate($lastcron), 'not run in last 30min']);;
    }
    $table->add_data([]);
    $table->add_data([]);

    $configrecords = $DB->get_records('plagiarism_unplag_config');
    $table->add_data(['id', 'cm', 'name', 'value']);
    foreach ($configrecords as $cf) {
        $table->add_data([$cf->id, $cf->cm, $cf->name, $cf->value]);
    }
}

if (!$table->is_downloading()) {
    echo $OUTPUT->heading(get_string('unplagfiles', 'plagiarism_unplag'));
    echo $OUTPUT->box(get_string('explainerrors', 'plagiarism_unplag'));
}
$table->finish_output();
if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}