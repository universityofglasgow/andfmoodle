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
 *
 * @package    local_gugcat
 * @copyright  2020
 * @author     Accenture
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_gugcat;

use local_gugcat;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once('gcat_item.php');

 /**
 * Grade capture class.
 */

class grade_capture{

     /**
     * Returns rows for grade capture table
     *
     * @param mixed $course
     * @param mixed $module
     * @param mixed $students
     */
    public static function get_rows($course, $module, $students){
        $captureitems = array();
        global $gradeitems;
        $grading_info = grade_get_grades($course->id, 'mod', $module->modname, $module->instance, array_keys($students));
        $gradeitems = local_gugcat::get_grade_grade_items($course, $module);

        //---------ids needed for grade discrepancy
        if(!$agreedgradeid = local_gugcat::get_grade_item_id($course->id, $module->id, get_string('gi_agreedgrade', 'local_gugcat'))){
            $secondgradeid = local_gugcat::get_grade_item_id($course->id, $module->id, get_string('gi_secondgrade', 'local_gugcat'));
            $thirdgradeid = local_gugcat::get_grade_item_id($course->id, $module->id, get_string('gi_thirdgrade', 'local_gugcat'));    
        };

        $i = 1;
        foreach ($students as $student) {
            $gbgrade = $grading_info->items[0]->grades[$student->id]->grade;
            $firstgrade = is_null($gbgrade) ? get_string('nograde', 'local_gugcat') : local_gugcat::convert_grade($gbgrade);
            $gradecaptureitem = new gcat_item();
            $gradecaptureitem->cnum = $i;
            $gradecaptureitem->studentno = $student->id;
            $gradecaptureitem->surname = $student->lastname;
            $gradecaptureitem->forename = $student->firstname;
            $gradecaptureitem->firstgrade = $firstgrade;
            $gradecaptureitem->discrepancy = false;
            $gradecaptureitem->grades = array();
            foreach ($gradeitems as $item) {
                if(isset($item->grades[$student->id])){
                    $rawgrade = $item->grades[$student->id]->finalgrade;
                    if($item->id === local_gugcat::$PRVGRADEID){
                        $grade = is_null($rawgrade) ? $firstgrade : local_gugcat::convert_grade($rawgrade);
                        $gradecaptureitem->provisionalgrade = $grade;
                    }else{
                        $grdobj = new stdClass();
                        $grade = is_null($rawgrade) ? 'N/A' : local_gugcat::convert_grade($rawgrade);
                        $grdobj->grade = $grade;
                        $grdobj->discrepancy = false;
                        //check grade discrepancy
                        if(!$agreedgradeid && $gbgrade){
                            if($item->id === $secondgradeid || $item->id === $thirdgradeid){
                                $grdobj->discrepancy = is_null($rawgrade) ? false : (($rawgrade != $gbgrade) ? true : false);
                            }
                        }
                        if($grdobj->discrepancy){
                            $gradecaptureitem->discrepancy = true;
                        }
                        array_push($gradecaptureitem->grades, $grdobj);
                    }
                }
            }
    
            array_push($captureitems, $gradecaptureitem);
            $i++;
        }
        return $captureitems;
    }

    /**
     * Returns columns for grade capture table
     *
     */
    public static function get_columns($module){
        //global $gradeitems from get rows function
        global $gradeitems;
        $date = date("(j/n/Y)", strtotime(userdate($module->added)));
        $firstgrade = get_string('gradebookgrade', 'local_gugcat').'<br>'.$date;
        $columns = array($firstgrade);
        foreach ($gradeitems as $item) {
            $columns[$item->id] = $item->itemname;
        }
        //remove provisional column
        unset($columns[local_gugcat::$PRVGRADEID]);
        return $columns;
    }
}