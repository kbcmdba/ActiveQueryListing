<?php

/*
 *
 * aql - Active Query Listing
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

namespace com\kbcmdba\aql ;

require_once 'vendor/autoload.php';

use com\kbcmdba\aql\Libs\Tools ;
use com\kbcmdba\aql\Libs\Utility ;

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
    Utility::applyParamToLimits( Tools::param( $optionName ) ?? '', $columnName, $default, $limits ) ;
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
    Utility::buildHostAjaxJs(
        $js, $hostname, $baseUrl,
        $alertCritSecs, $alertWarnSecs, $alertInfoSecs, $alertLowSecs,
        Tools::param( 'debug' ) ?? '',
        Tools::param( 'debugLocks' ) === '1'
    ) ;
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
    $checkedValue = Tools::param( $name ) ;
    if ( ! ( isset( $checkedValue ) && ( $checkedValue !== '' ) ) ) {
        $checkedValue = $defaultValue ;
    }
    return Utility::buildRadioChoicesHtml( $label, $name, $checkedValue ) ;
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
    Utility::applyAndOr( Tools::param( $name ) ?? '', $value, $checked, $result ) ;
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
    Utility::appendSelectOption( $label, $value, Tools::params( $name ), $list ) ;
}
