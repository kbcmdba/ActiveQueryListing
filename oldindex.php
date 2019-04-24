<?php

/*
 *
 * ActiveQueryListing - Active Query Listing
 *
 * Copyright (C) 2018 Kevin Benton - kbcmdba [at] gmail [dot] com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\ActiveQueryListing ;

require_once('Libs/autoload.php');

/**
 * Since parameters directly map to query options...
 *
 * @param string $optionName
 * @param string $columnName
 * @param string $default
 *            Value of 'any' tells this routine not to do anything in the event that the user doesn't supply a value.
 * @param string $limits
 */
function processParam($optionName, $columnName, $default, &$limits)
{
    switch (Tools::param($optionName)) {
        case 'any':
            // Don't do anything here. This is a special case when it's much
            // easier to not specify the column at all.
            break;
        case '1':
            $limits .= " AND $columnName = 1";
            break;
        case '0':
            $limits .= " AND $columnName = 0";
            break;
        default:
            if ('any' !== $default) {
                $limits .= " AND $columnName = $default";
            }
            break;
    }
}

/**
 * Process the query list for a given host
 *
 * @param mixed $js
 * @param string $hostname
 * @param string $baseUrl
 * @param integer $alertCritSecs
 * @param integer $alertWarnSecs
 * @param integer $alertInfoSecs
 * @param integer $alertLowSecs
 */
function processHost(&$js, $hostname, $baseUrl, $alertCritSecs, $alertWarnSecs, $alertInfoSecs, $alertLowSecs)
{
    $prefix = (0 !== $js['Blocks']) ? ',' : '';
    $blockNum = $js['Blocks'];
    $js['Blocks'] ++;
    $js['WhenBlock'] .= "$prefix\$.getJSON( \"$baseUrl?hostname=$hostname&alertCritSecs=$alertCritSecs&alertWarnSecs=$alertWarnSecs&alertInfoSecs=$alertInfoSecs&alertLowSecs=$alertLowSecs\")";
    $js['ThenParamBlock'] .= "$prefix res$blockNum";
    $js['ThenCodeBlock'] .= "\$.each(res$blockNum, myCallback);";
}

/**
 * Get the radio choices users are given.
 *
 * @param string $label
 * @param string $name
 * @param string $defaultValue
 * @return string
 */
function getChoices($label, $name, $defaultValue)
{
    $checkedValue = Tools::param($name);
    if (! (isset($checkedValue) && ($checkedValue !== ''))) {
        $checkedValue = $defaultValue;
    }
    $yesChecked = ('1' === $checkedValue) ? 'checked="checked"' : '';
    $noChecked = ('0' === $checkedValue) ? 'checked="checked"' : '';
    $anyChecked = ('any' === $checkedValue) ? 'checked="checked"' : '';
    $yes = "<input type=\"radio\" name=\"$name\" value=\"1\" $yesChecked />";
    $no = "<input type=\"radio\" name=\"$name\" value=\"0\" $noChecked />";
    $any = "<input type=\"radio\" name=\"$name\" value=\"any\" $anyChecked />";
    return ("<tr><td>$label</td><td>$yes</td><td>$no</td><td>$any</td></tr>");
}

/**
 * Process the and/or radios
 *
 * @param string $name
 * @param string $value
 * @param
 *            string &$result
 */
function processAndOr($name, $value, &$checked, &$result)
{
    if (strtoupper(Tools::param($name)) === strtoupper($value)) {
        $checked = 'checked="checked"';
        $result = $value;
    }
}

/**
 * Process individual values of the SELECT/OPTION list
 *
 * @param string $label
 * @param string $value
 * @param string &$list
 */
function processSelectOption($name, $label, $value, &$list)
{
    $exists = in_array($value, Tools::params($name), true);
    $checked = ($exists) ? 'checked="checked"' : '';
    $list .= "<option value=\"$value\" $checked>$label</option>";
}

$headerFooterRow = <<<HTML
<tr>
      <th>Server</th>
      <th>Alert<br />Level</th>
      <th>ID</th>
      <th>User</th>
      <th>Host</th>
      <th>DB</th>
      <th>Command</th>
      <th>Time</th>
      <th>State</th>
      <th>Info</th>
      <th>Actions</th>
    </tr>

HTML;
$page = new WebPage('Active Queries List');
$config = new Config();
$reloadSeconds = $config->getDefaultRefresh();
$limits = '';
$js = [] ;
$js['Blocks'] = 0;
$js['WhenBlock'] = '';
$js['ThenParamBlock'] = '';
$js['ThenCodeBlock'] = '';
processParam('decommissioned', 'decommissioned', '0', $limits);
processParam('revenueImpacting', 'revenue_impacting', '1', $limits);
processParam('shouldMonitor', 'should_monitor', '1', $limits);
processParam('shouldBackup', 'should_backup', 'any', $limits);
$choices = getChoices('Monitored Hosts', 'shouldMonitor', '1')
         . getChoices('Revenue Impacting Hosts', 'revenueImpacting', '1')
         . getChoices('Backed-Up Hosts', 'shouldBackup', 'any')
         . getChoices('Decommissioned Hosts', 'decommissioned', '0');
$andOr1Or = $andOr2Or = 'checked="checked"';
$andOr1 = $andOr2 = $andOr1And = $andOr2And = null;
processAndOr('andOr1', 'AND', $andOr1And, $andOr1);
processAndOr('andOr1', 'OR', $andOr1Or, $andOr1);
processAndOr('andOr2', 'AND', $andOr2And, $andOr2);
processAndOr('andOr2', 'OR', $andOr2Or, $andOr2);
$selectedHosts = Tools::params('hosts');
$limits .= " $andOr1 h.hostname IN ( '" . implode($selectedHosts, "', '") . "' )";
// $selectedGroups = Tools::params('groups');
$joinType1 = ('and' === $andOr1) ? "INNER" : "LEFT";
$joinType2 = ('and' === $andOr2) ? "INNER" : "LEFT";
// $limits .= " $andOr2 hg.tag IN ( '"
// . implode( $selectedGroups, "', '" )
// . "' )" ;
$hostQuery = <<<SQL
SELECT h.hostname
     , h.alert_crit_secs
     , h.alert_warn_secs
     , h.alert_info_secs
     , h.alert_low_secs
  FROM aql_db.host AS h
 $joinType1
  JOIN host_group_map AS hgm
    ON hgm.host_id = h.host_id
 $joinType2
  JOIN host_group AS hg
    ON hg.host_group_id = hgm.host_group_id
 WHERE 1 = 1 $limits
 
SQL;
$allHostsQuery = "SELECT hostname FROM host";
$groupQuery = 'SELECT tag FROM aql_db.host_group';
$allHostsList = /* $hostList = */ $groupList = '';
try {
    $config = new Config();
    $dbc = new DBConnection();
    $dbh = $dbc->getConnection();
    $result = $dbh->query($allHostsQuery);
    if ($result) {
        while ($row = $result->fetch_row()) {
            $serverName = htmlentities($row[0]);
            $allHostsList .= "  <option value=\"$serverName\">$serverName</option>\n";
        }
    }
    $result = $dbh->query($hostQuery);
    if ($result) {
        while ($row = $result->fetch_row()) {
            $serverName = htmlentities($row[0]);
//            $hostList .= "  <option value=\"$serverName\">$serverName</option>\n";
            processHost($js, $row[0], $config->getBaseUrl(), $row[1], $row[2], $row[3], $row[4]);
        }
    }
    processSelectOption('groups', 'All', 'All', $groupList);
    $result = $dbh->query($groupQuery);
    if ($result) {
        while ($row = $result->fetch_row()) {
            $groupName = $row[0];
            processSelectOption('groups', $groupName, $groupName, $groupList);
        }
    }
    $whenBlock = $js['WhenBlock'];
    $thenParamBlock = $js['ThenParamBlock'];
    $thenCodeBlock = $js['ThenCodeBlock'];
    $page->setBottom(
        <<<JS
<script>

function loadPage() {
    \$("#tbodyid").html( '<tr id="figment"><td colspan="11"><center>Data loading</center></td></tr>' ) ;
    \$.when($whenBlock).then(
        function ( $thenParamBlock ) {
            $thenCodeBlock
            \$("#figment").remove() ;
        }
    );
    \$('#tbodyid').on('click', '.morelink', flipFlop) ;
}

\$(document).ready( loadPage ) ;
setInterval( loadPage, $reloadSeconds * 1000 ) ;
</script>

JS
);
    $page->setBody(
        <<<HTML
<h1>Active Queries List</h1>
<form method=GET>
  <table border=0 cellspacing="0" cellpadding="2" width="100%">
    <tr>
      <td>
        <table border=0 cellspacing="0" cellpadding="2" id="choices">
          <tr><th>Limit To</th><th>Yes</th><th>No</th><th>Either</th></tr>
$choices
        </table>
      </td>
      <td>
        <table border=0 cellspacing="0" cellpadding="2" id="andOr1">
          <tr>
            <td><input type="radio" name="andOr1" value="and" $andOr1And /> And</td>
          </tr>
          <tr>
            <td><input type="radio" name="andOr1" value="or" $andOr1Or /> Or</td>
          </tr>
        </table>
      </td>
      <td>
        <table border=0 cellspacing="0" cellpadding="2" id="hostlist">
          <tr>
            <th>Hosts</th>
          </tr>
          <tr>
            <td><select size="7" name="hosts[]" multiple="multiple">$allHostsList</select></td>
          </tr>
        </table>
      </td>
      <td>
        <table border=0 cellspacing="0" cellpadding="2" id="andOr2">
          <tr>
            <td><input type="radio" name="andOr2" value="and" $andOr2And /> And</td>
          </tr>
          <tr>
            <td><input type="radio" name="andOr2" value="or" $andOr2Or /> Or</td>
          </tr>
        </table>
      </td>
      <td>
        <table border=0 cellspacing="0" cellpadding="2" id="grouplist">
          <tr>
            <th>Groups</th>
          </tr>
          <tr>
            <td><select size="7" name="groups[]" multiple="multiple">$groupList</select></td>
          </tr>
        </table>
      </td>
      <td>
        <table border=0 cellspacing="0" cellpadding="2" id="grouplist">
          <tr>
            <td>
              Refresh Every
              <input type="text" size="5" width="5" value="$reloadSeconds" />
              Seconds
            </td>
          </tr>
          <td>
            <center><button type="button">Pause Refresh</button></center>
          </td>
          </tr>
        </table>
      </td>
    </tr>
    <tr><td colspan="6"><center><input type="submit" value="Redisplay with these choices" /></center></td></tr>
  </table>
</form>
        
<table border=1 cellspacing=0 cellpadding=2 id="dataTable" width="100%">
  <thead>
    $headerFooterRow
  </thead>
  <tbody id="tbodyid"><tr id="figment"><td colspan="10"><center>Data loading</center></td></tr></tbody>
  <tfoot>
    $headerFooterRow
  </tfoot>
</table>
<p />
<table border=1 cellspacing=0 cellpadding=2 id="legend" width="100%">
  <caption>Legend</caption>
  <tr><th>Level</th><th>Description</th></tr>
  <tr class="error" ><td>-</td><td>An error has occurred while communicating with the host described.</td></tr>
  <tr class="level4"><td>4</td><td>The shown query has reached a critical alert level and should be investigated.</td></tr>
  <tr class="level3"><td>3</td><td>The shown query has reached a warning alert level.</td></tr>
  <tr class="level2"><td>2</td><td>The shown query is running longer than expected.</td></tr>
  <tr class="level1"><td>1</td><td>The shown query is running within normal time parameters.</td></tr>
  <tr class="level0"><td>0</td><td>The shown query has run for less time than expected so far.</td></tr>
</table>
        
HTML
      ) ;
} catch (DaoException $e) {
    $page->appendBody(
        "<pre>Error interacting with the database\n\n"
                  . $e->getMessage() . "\n</pre>\n"
                  ) ;
}
$page->displayPage() ;
