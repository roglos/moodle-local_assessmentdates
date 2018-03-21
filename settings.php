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
 * assessmentdates local plugin settings and presets.
 *
 * @package    local_assessmentdates
 * @copyright  2017 RMOelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This is designed as a template to enable creation of local plugins reading from
 * an external assessmentdates table. The external assessmentdates and table are set in a settings
 * page for each plugin create - ie can each point at different Db/tables etc if
 * required. By default this template reads all fields in the set table (SELECT * FROM).
 **/

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_assessmentdates',
        get_string('pluginname', 'local_assessmentdates'));
    $ADMIN->add('localplugins', $settings);

        // Headings.
    $settings->add(new admin_setting_heading('local_assessmentdates_settings', '',
        get_string('pluginname_desc', 'local_assessmentdates')));
    $settings->add(new admin_setting_heading('local_assessmentdates_exdbheader',
        get_string('settingsheaderdb', 'local_assessmentdates'), ''));

    // Db Connection Settings.
    // -----------------------

    // Db type.
    $options = array('',
        "access",
        "ado_access",
        "ado",
        "ado_mssql",
        "borland_ibase",
        "csv",
        "db2",
        "fbsql",
        "firebird",
        "ibase",
        "informix72",
        "informix",
        "mssql",
        "mssql_n",
        "mssqlnative",
        "mysql",
        "mysqli",
        "mysqlt",
        "oci805",
        "oci8",
        "oci8po",
        "odbc",
        "odbc_mssql",
        "odbc_oracle",
        "oracle",
        "pdo",
        "postgres64",
        "postgres7",
        "postgres",
        "proxy",
        "sqlanywhere",
        "sybase",
        "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('local_assessmentdates/dbtype',
        get_string('dbtype', 'local_assessmentdates'),
        get_string('dbtype_desc', 'local_assessmentdates'), '', $options));

    // Db host.
    $settings->add(new admin_setting_configtext('local_assessmentdates/dbhost',
        get_string('dbhost', 'local_assessmentdates'),
        get_string('dbhost_desc', 'local_assessmentdates'), 'localhost'));

    // Db User.
    $settings->add(new admin_setting_configtext('local_assessmentdates/dbuser',
        get_string('dbuser', 'local_assessmentdates'), '', ''));

    // Db Password.
    $settings->add(new admin_setting_configpasswordunmask('local_assessmentdates/dbpass',
        get_string('dbpass', 'local_assessmentdates'), '', ''));

    // Db Name.
    $settings->add(new admin_setting_configtext('local_assessmentdates/dbname',
        get_string('dbname', 'local_assessmentdates'),
        get_string('dbname_desc', 'local_assessmentdates'), ''));

    // Db Encoding.
    $settings->add(new admin_setting_configtext('local_assessmentdates/dbencoding',
        get_string('dbencoding', 'local_assessmentdates'), '', 'utf-8'));

    // Db Setup.
    $settings->add(new admin_setting_configtext('local_assessmentdates/dbsetupsql',
        get_string('dbsetupsql', 'local_assessmentdates'),
        get_string('dbsetupsql_desc', 'local_assessmentdates'), ''));

    // Db Sybase.
    $settings->add(new admin_setting_configcheckbox('local_assessmentdates/dbsybasequoting',
        get_string('dbsybasequoting', 'local_assessmentdates'),
        get_string('dbsybasequoting_desc', 'local_assessmentdates'), 0));

    // AODBC Debug.
    $settings->add(new admin_setting_configcheckbox('local_assessmentdates/debugdb',
        get_string('debugdb', 'local_assessmentdates'),
        get_string('debugdb_desc', 'local_assessmentdates'), 0));

    // Table Settings.
    $settings->add(new admin_setting_heading('local_assessmentdates_remoteheader',
        get_string('settingsheaderremote', 'local_assessmentdates'), ''));

    // Table name - assignment defaults.
    $settings->add(new admin_setting_configtext('local_assessmentdates/remotetable',
        get_string('remotetable', 'local_assessmentdates'),
        get_string('remotetable_desc', 'local_assessmentdates'), ''));
    // Table name - individual settings: extensions, grades etc.
    $settings->add(new admin_setting_configtext('local_assessmentdates/remotegradestable',
        get_string('remotegradestable', 'local_assessmentdates'),
        get_string('remotegradestable_desc', 'local_assessmentdates'), ''));

    // Fields to use.
//    $settings->add(new admin_setting_configtext('local_assessmentdates/linkcode',
//        get_string('linkcode', 'local_assessmentdates'),
//        get_string('linkcode', 'local_assessmentdates'), ''));
//    $settings->add(new admin_setting_configtext('local_assessmentdates/duedate',
//        get_string('duedate', 'local_assessmentdates'),
//        get_string('duedate', 'local_assessmentdates'), ''));
//    $settings->add(new admin_setting_configtext('local_assessmentdates/feedbackdue',
//        get_string('feedbackdue', 'local_assessmentdates'),
//        get_string('feedbackdue', 'local_assessmentdates'), ''));

}
