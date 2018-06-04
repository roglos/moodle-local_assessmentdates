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
 * A scheduled task for scripted database integrations.
 *
 * @package    local_assessmentdates - template
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_assessmentdates\task;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * A scheduled task for scripted external database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assessmentdates extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_assessmentdates');
    }

    /**
     * Run sync.
     */
    public function execute() {
        // Access global variables.
        global $CFG, $DB;
        // Set default submission and feedback times per policy.
        $submissiontime = date('H:i:s', strtotime('6pm'));
        $feedbacktime = date('H:i:s', strtotime('9am'));

        // Database connection and setup checks.
        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$this->get_config('dbtype')) {
            echo 'Database not defined.<br>';
            return 0;
        } else {
            echo 'Database: ' . $this->get_config('dbtype') . '<br>';
        }
        // Check remote assessments table - usr_data_assessments.
        if (!$this->get_config('remotetable')) {
            echo 'Assessments Table not defined.<br>';
            return 0;
        } else {
            echo 'Assessments Table: ' . $this->get_config('remotetable') . '<br>';
        }
        // Check remote student grades table - usr_data_student_assessments.
        if (!$this->get_config('remotegradestable')) {
            echo 'Student Grades Table not defined.<br>';
            return 0;
        } else {
            echo 'Student Grades Table: ' . $this->get_config('remotetable') . '<br>';
        }
        echo 'Starting connection...<br>';
        // Report connection error if occurs.
        if (!$extdb = $this->db_init()) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        // Create arrays to work on rather than constant reading/writing from Db.
        // Get duedate and gradingduedate from assign/quiz tables where assignment has link code.
        /********************************************************
         * ARRAY (LINK CODE-> StdClass Object)                  *
         *     idnumber                                         *
         *     id                                               *
         *     name                                             *
         *     duedate (UNIX timestamp)                         *
         *     gradingduedate (UNIX timestamp)                  *
         ********************************************************/
        // Get assignments.
        $sqldates = $DB->get_records_sql(
            'SELECT a.id as id,m.id as cm, m.idnumber as linkcode,a.name,a.duedate,a.gradingduedate
            FROM {course_modules} m
            JOIN {assign} a ON m.instance = a.id
            JOIN {modules} mo ON m.module = mo.id
            WHERE m.idnumber IS NOT null AND m.idnumber != "" AND mo.name = "assign"'
            );
        // Get quizes.
        $sqlquizdates = $DB->get_records_sql(
            'SELECT q.id as id,m.id as cm, m.idnumber as linkcode, q.name, q.timeclose as duedate, null as gradingduedate
            FROM {course_modules} m
            JOIN {quiz} q ON m.instance = q.id
            JOIN {modules} mo ON m.module = mo.id
            WHERE m.idnumber IS NOT null AND m.idnumber != "" AND mo.name = "quiz"'
            );
        // Create reference array of assignment id and link code from mdl.
        $assignmdl = array();
        foreach ($sqldates as $sd) {
            $assignmdl[$sd->linkcode]['id'] = $sd->id;
            $assignmdl[$sd->linkcode]['cm'] = $sd->cm;
            $assignmdl[$sd->linkcode]['lc'] = $sd->linkcode;
            $assignmdl[$sd->linkcode]['name'] = $sd->name;
            $assignmdl[$sd->linkcode]['duedate'] = $sd->duedate;
        }
        // Add quiz dates to assignments array.
        foreach ($sqlquizdates as $sd) {
            $assignmdl[$sd->linkcode]['id'] = $sd->id;
            $assignmdl[$sd->linkcode]['cm'] = $sd->cm;
            $assignmdl[$sd->linkcode]['lc'] = $sd->linkcode;
            $assignmdl[$sd->linkcode]['name'] = $sd->name;
            $assignmdl[$sd->linkcode]['duedate'] = $sd->duedate;
        }

        // Get external assessments table name.
        $tableassm = $this->get_config('remotetable');
        // Ensure array is empty.
        $assessments = array();
        // Read assessment data from external table into array.
        /********************************************************
         * ARRAY                                                *
         *     id                                               *
         *     mav_idnumber                                     *
         *     assessment_number                                *
         *     assessment_name                                  *
         *     assessment_type                                  *
         *     assessment_weight                                *
         *     assessment_idcode - THIS IS THE MAIN LINK ID     *
         *     assessment_markscheme_name                       *
         *     assessment_markscheme_code                       *
         *     assessment_duedate                               *
         *     assessment_feedbackdate                          *
         ********************************************************/
        // Fetch from external database.
        $sql = $this->db_get_sql($tableassm, array(), array(), true);
        // Read database results into usable array.
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    $assessments[] = $fields;
                }
            }
            $rs->Close();
        } else {
            // Report error if required.
            $extdb->Close();
            echo 'Error reading data from the external assessments table<br>';
            return 4;
        }

        // Set due dates and feedback/grade by dates.
        // Echo statements output to cron or when task run immediately for debugging.
        foreach ($assessments as $a) {
            // Error trap - ensure we have an assessment link id.
            if ($key = array_key_exists($a['assessment_idcode'], $assignmdl)) {
                // Set main key fields (makes code more readable only).
                $idcode = $assignmdl[$a['assessment_idcode']]['id'];
                $linkcode = $assignmdl[$a['assessment_idcode']]['lc'];

                echo '<br><br>'.$linkcode.':'.$idcode.' - Assessment dates<br>';

                // Convert Moodle due date UNIX time stamp to Y-m-d H:i:s format.
                $due = date('Y-m-d H:i:s', $assignmdl[$a['assessment_idcode']]['duedate']);
                $duedate = date('Y-m-d', $assignmdl[$a['assessment_idcode']]['duedate']);
                $mdlduetime = date('H:i:s', $assignmdl[$a['assessment_idcode']]['duedate']);
                $duetime = $submissiontime;
                echo 'Mdl-due date/time '.$due.' - Mdl Due Date '.$duedate.' : Mdl Due Time  '.$mdlduetime.'<br>';
                echo 'Ext-due date '.$a['assessment_duedate'].' Ext due time '.$a['assessment_duetime'].'<br>';

                // Set duedate in external Db.
                if (!empty($a['assessment_duedate'])) { // If external Db already has a due date.
                    // And external duedate is different, set duedate value as Moodle value.
                    if ($a['assessment_duedate'] != $duedate || $a['assessment_duetime'] != $duetime) {
                        $sql = "UPDATE " . $tableassm . "
                        SET assessment_duedate = '" . $duedate . "',
                            assessment_duetime = '" . $duetime . "', assessment_changebymoodle = 1
                        WHERE assessment_idcode = '" . $linkcode . "';";
                        echo $sql;
                        $extdb->Execute($sql);
                        echo $idcode . " Due Date updated on external Db - " . $duedate . "<br><br>";
                    }
                } else { // If external Db doesn't have a due date set.
                    if (isset($assignmdl[$a['assessment_idcode']]['duedate'])) { // But MDL does, set duedate value as Moodle value.
                        $sql = "UPDATE " . $tableassm . " SET assessment_duedate = '" . $duedate . "',
                            assessment_duetime = '" . $duetime . "', assessment_changebymoodle = 1
                        WHERE assessment_idcode = '" . $linkcode . "';";
                        echo $sql;
                        $extdb->Execute($sql);
                        echo $idcode . ' Due Date xported.<br>';
                    }
                }

                // Get gradeby date from external Db and apply to Mdl if different.
                if (isset($sqldates[$idcode]) ) {
                    // Get times from Moodle and external database.
                    $gradingduedate = date('Y-m-d', $sqldates[$idcode]->gradingduedate);
                    $gradingduetime = $feedbacktime;
                    echo 'Mdl-Feedback due date/time '.date('Y-m-d H:i:s', $sqldates[$idcode]->gradingduedate)
                        .' - Mdl Feedback Due Date '.$gradingduedate.' : Mdl Feedback Due Time  '.$gradingduetime.'<br>';
                    echo 'Ext-Feedback due date '.$a['assessment_feedbackdate'].' Ext Feedback due time '
                        .$a['assessment_feedbacktime'].'<br>';
                    // If Moodle feedback due date and time dont match external.
                    if ($gradingduedate != $a['assessment_feedbackdate'] || $gradingduetime != $a['assessment_feedbacktime']) {
                        // Create array of time settings, with Assignment id.
                        $assignmentdates = array();
                        $assignmentdates['id'] = $sqldates[$idcode]->id;
                        // Convert external database times to Unix timestamp.
                        $assignmentdates['gradingduedate'] = strtotime($a['assessment_feedbackdate'].' '.$gradingduetime);
                        $assignmentdates['cutoffdate'] = strtotime($a['assessment_feedbackdate'].' '.$gradingduetime);
                        // Set times/dates.
                        $DB->update_record('assign', $assignmentdates, false);
                        echo $idcode . ' Feedback due date and CutOff date set.<br>';
                    }
                }
            }
        }

        // Reset change flags.
        $sql = "UPDATE " . $tablegrades . " SET assessment_changebydw = 0 WHERE assessment_changebydw = 1;";
        $extdb->Execute($sql);
        $sql = "UPDATE " . $tableassm . " SET assessment_changebydw = 0 WHERE assessment_changebydw = 1;";
        $extdb->Execute($sql);

        // Free memory.
        $extdb->Close();
    }

    /* Db functions cloned from enrol/db plugin.
     * ========================================= */

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    public function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->get_config('dbtype'));
        if ($this->get_config('debugdb')) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->get_config('dbhost'),
                $this->get_config('dbuser'),
                $this->get_config('dbpass'),
                $this->get_config('dbname'), true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->get_config('dbsetupsql')) {
            $extdb->Execute($this->get_config('dbsetupsql'));
        }
        return $extdb;
    }

    public function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->get_config('dbsybasequoting')) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }

    public function db_encode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    public function db_decode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    public function db_get_sql($table, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";
        return $sql;
    }

    // RO function - adjust standard db_get_sql function to provide LIKE condition.
    public function db_get_sql_like($table2, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key LIKE '%$value%'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql2 = "SELECT $distinct $fields
                  FROM $table2
                 $where
                  $sort";
        return $sql2;
    }

    /**
     * Returns plugin config value
     * @param  string $name
     * @param  string $default value if config does not exist yet
     * @return string value or default
     */
    public function get_config($name, $default = null) {
        $this->load_config();
        return isset($this->config->$name) ? $this->config->$name : $default;
    }

    /**
     * Sets plugin config value
     * @param  string $name name of config
     * @param  string $value string config value, null means delete
     * @return string value
     */
    public function set_config($name, $value) {
        $settingspluginname = 'assessmentsettings';
        $this->load_config();
        if ($value === null) {
            unset($this->config->$name);
        } else {
            $this->config->$name = $value;
        }
        set_config($name, $value, "local_$settingspluginname");
    }

    /**
     * Makes sure config is loaded and cached.
     * @return void
     */
    public function load_config() {
        if (!isset($this->config)) {
            $settingspluginname = 'assessmentsettings';
            $this->config = get_config("local_$settingspluginname");
        }
    }
}
