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
 * List of all resource type modules in course
 *
 * @package   moodlecore
 * @copyright 2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once("$CFG->libdir/resourcelib.php");

$id = required_param('id', PARAM_INT); // course id

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$PAGE->set_pagelayout('course');
require_course_login($course, true);

// get list of all resource-like modules
$allmodules = $DB->get_records('modules', array('visible'=>1));
$modules = array();
foreach ($allmodules as $key=>$module) {
    $modname = $module->name;
    $libfile = "$CFG->dirroot/mod/$modname/lib.php";
    if (!file_exists($libfile)) {
        continue;
    }
    $archetype = plugin_supports('mod', $modname, FEATURE_MOD_ARCHETYPE, MOD_ARCHETYPE_OTHER);
    if ($archetype != MOD_ARCHETYPE_RESOURCE) {
        continue;
    }

    $modules[$modname] = get_string('modulename', $modname);
    //some hacky nasic logging
    add_to_log($course->id, $modname, 'view all', "index.php?id=$course->id", '');
}

$strresources    = get_string('resources');
$strsectionname  = get_string('sectionname', 'format_'.$course->format);
$strname         = get_string('name');
$strintro        = get_string('moduleintro');
$strlastmodified = get_string('lastmodified');

$PAGE->set_url('/course/resources.php', array('id' => $course->id));
$PAGE->set_title($course->shortname.': '.$strresources);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($strresources);
echo $OUTPUT->header();

$modinfo = get_fast_modinfo($course);
$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $sections = get_all_sections($course->id);
}
$cms = array();
$resources = array();
foreach ($modinfo->cms as $cm) {
    if (!$cm->uservisible) {
        continue;
    }
    if (!array_key_exists($cm->modname, $modules)) {
        continue;
    }
    if (!$cm->has_view()) {
        // Exclude label and similar
        continue;
    }
    $cms[$cm->id] = $cm;
    $resources[$cm->modname][] = $cm->instance;
}

// preload instances
foreach ($resources as $modname=>$instances) {
    $additionalfields = '';
    if (plugin_supports('mod', $modname, FEATURE_MOD_INTRO)) {
        $additionalfields = ',intro,introformat';
    }
    $resources[$modname] = $DB->get_records_list($modname, 'id', $instances, 'id', 'id,name'.$additionalfields);
}

if (!$cms) {
    notice(get_string('thereareno', 'moodle', $strresources), "$CFG->wwwroot/course/view.php?id=$course->id");
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head  = array ($strsectionname, $strname, $strintro);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strlastmodified, $strname, $strintro);
    $table->align = array ('left', 'left', 'left');
}

$currentsection = '';
foreach ($cms as $cm) {
    if (!isset($resources[$cm->modname][$cm->instance])) {
        continue;
    }
    $resource = $resources[$cm->modname][$cm->instance];
    $printsection = '';
    if ($usesections) {
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $printsection = get_section_name($course, $sections[$cm->sectionnum]);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $cm->sectionnum;
        }
    }

    $extra = empty($cm->extra) ? '' : $cm->extra;
    if (!empty($cm->icon)) {
        $icon = '<img src="'.$OUTPUT->pix_url($cm->icon).'" class="activityicon" alt="'.get_string('modulename', $cm->modname).'" /> ';
    } else {
        $icon = '<img src="'.$OUTPUT->pix_url('icon', $cm->modname).'" class="activityicon" alt="'.get_string('modulename', $cm->modname).'" /> ';
    }

    if (isset($resource->intro) && isset($resource->introformat)) {
        $intro = format_module_intro('resource', $resource, $cm->id);
    } else {
        $intro = '';
    }

    $class = $cm->visible ? '' : 'class="dimmed"'; // hidden modules are dimmed
    $table->data[] = array (
        $printsection,
        "<a $class $extra href=\"$CFG->wwwroot/mod/$cm->modname/view.php?id=$cm->id\">".$icon.format_string($resource->name)."</a>",
        $intro);
}

echo html_writer::table($table);

echo $OUTPUT->footer();
