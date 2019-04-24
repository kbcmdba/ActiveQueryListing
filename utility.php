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

require_once('vendor/autoload.php') ;

use com\kbcmdba\ActiveQueryListing\Libs\Tools;

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
    $debug = ( Tools::param('debug')==='1' ) ? '&debug=1' : '' ;
    $prefix = (0 !== $js['Blocks']) ? ',' : '';
    $blockNum = $js['Blocks'];
    $js['Blocks'] ++;
    $js['WhenBlock'] .= "$prefix\$.getJSON( \"$baseUrl?hostname=$hostname&alertCritSecs=$alertCritSecs&alertWarnSecs=$alertWarnSecs&alertInfoSecs=$alertInfoSecs&alertLowSecs=$alertLowSecs$debug\")";
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
 * @param
 *            mixed &$list
 */
function processSelectOption($name, $label, $value, &$list)
{
    $exists = in_array($value, Tools::params($name), true);
    $checked = ($exists) ? 'checked="checked"' : '';
    $list .= "<option value=\"$value\" $checked>$label</option>";
}
