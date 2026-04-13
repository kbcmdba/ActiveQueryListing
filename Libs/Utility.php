<?php

/**
 * aql
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\aql\Libs ;

/**
 * Pure utility helpers extracted from utility.php for testability.
 * The wrapper functions in utility.php resolve Tools::param() values and
 * delegate here; tests call these methods directly with controlled inputs.
 */
class Utility
{

    /**
     * Append a SQL WHERE clause fragment for a single filter parameter.
     *
     * @param string $paramValue  Already-resolved request parameter value
     * @param string $columnName  Column to filter on (injected verbatim — caller must whitelist)
     * @param string $default     Default value; 'any' means no filter when param is absent
     * @param string &$limits     Accumulating SQL WHERE clause string
     */
    public static function applyParamToLimits(
        string $paramValue,
        string $columnName,
        string $default,
        string &$limits
    ) : void {
        switch ( $paramValue ) {
            case 'any' :
                // Special token: no filter applied
                break ;
            case '1' :
                $limits .= " AND $columnName = 1" ;
                break ;
            case '0' :
                $limits .= " AND $columnName = 0" ;
                break ;
            default :
                if ( 'any' !== $default ) {
                    $limits .= " AND $columnName = $default" ;
                }
                break ;
        }
    }

    /**
     * Build an HTML table row containing Yes / No / Any radio buttons.
     *
     * @param string $label        Display label for the row
     * @param string $name         HTML input name attribute
     * @param string $checkedValue Already-resolved value to pre-select ('1', '0', or 'any')
     * @return string              HTML <tr> string
     */
    public static function buildRadioChoicesHtml(
        string $label,
        string $name,
        string $checkedValue
    ) : string {
        $yesChecked = ( '1'   === $checkedValue ) ? 'checked="checked"' : '' ;
        $noChecked  = ( '0'   === $checkedValue ) ? 'checked="checked"' : '' ;
        $anyChecked = ( 'any' === $checkedValue ) ? 'checked="checked"' : '' ;
        $yes = "<input type=\"radio\" name=\"$name\" value=\"1\" $yesChecked />" ;
        $no  = "<input type=\"radio\" name=\"$name\" value=\"0\" $noChecked />" ;
        $any = "<input type=\"radio\" name=\"$name\" value=\"any\" $anyChecked />" ;
        return "<tr><td>$label</td><td>$yes</td><td>$no</td><td>$any</td></tr>" ;
    }

    /**
     * Set $checked / $result if $paramValue matches $value (case-insensitive).
     * Used to build AND / OR radio pairs.
     *
     * @param string $paramValue  Already-resolved parameter value
     * @param string $value       Target value to match against
     * @param string &$checked    Set to 'checked="checked"' on match
     * @param string &$result     Set to $value on match
     */
    public static function applyAndOr(
        string $paramValue,
        string $value,
        string &$checked,
        string &$result
    ) : void {
        if ( strtoupper( $paramValue ) === strtoupper( $value ) ) {
            $checked = 'checked="checked"' ;
            $result  = $value ;
        }
    }

    /**
     * Append an HTML <option> element to $list.
     *
     * @param string $label        Display text for the option
     * @param string $value        Option value attribute
     * @param array  $paramValues  Already-resolved list of selected values
     * @param string &$list        Accumulating HTML options string
     */
    public static function appendSelectOption(
        string $label,
        string $value,
        array  $paramValues,
        string &$list
    ) : void {
        $selected = in_array( $value, $paramValues, true ) ? 'checked="checked"' : '' ;
        $list .= "<option value=\"$value\" $selected>$label</option>" ;
    }

    /**
     * Append a JavaScript AJAX call block for one monitored host to $js['AjaxCalls'].
     * Increments $js['Blocks'] to give each call a unique variable name.
     *
     * @param array  &$js          JS state: ['Blocks' => int, 'AjaxCalls' => string]
     * @param string $hostname     Hostname (used as JS identifier and URL param)
     * @param string $baseUrl      AJAX endpoint URL (e.g. AJAXgetaql.php)
     * @param mixed  $alertCritSecs
     * @param mixed  $alertWarnSecs
     * @param mixed  $alertInfoSecs
     * @param mixed  $alertLowSecs
     * @param string $debugParam   Already-resolved debug param value ('' = none)
     * @param bool   $debugLocks   Whether to append &debugLocks=1
     */
    public static function buildHostAjaxJs(
        array  &$js,
        string $hostname,
        string $baseUrl,
               $alertCritSecs,
               $alertWarnSecs,
               $alertInfoSecs,
               $alertLowSecs,
        string $debugParam  = '',
        bool   $debugLocks  = false
    ) : void {
        $debug          = ( ! empty( $debugParam ) ) ? '&debug=' . urlencode( $debugParam ) : '' ;
        $debugLocksStr  = $debugLocks ? '&debugLocks=1' : '' ;
        $blockNum       = $js['Blocks'] ;
        $js['Blocks'] ++ ;
        $url = "$baseUrl?hostname=$hostname&alertCritSecs=$alertCritSecs&alertWarnSecs=$alertWarnSecs&alertInfoSecs=$alertInfoSecs&alertLowSecs=$alertLowSecs$debug$debugLocksStr" ;
        $js['AjaxCalls'] .= <<<JSAJAX
    pendingHosts[ '$hostname' ] = true ;
    var ajaxStart_{$blockNum} = Date.now() ;
    \$.getJSON( "$url" )
        .done( function( data ) {
            delete pendingHosts[ '$hostname' ] ;
            var totalMs = Date.now() - ajaxStart_{$blockNum} ;
            var serverMs = ( data && data.renderTimeData && data.renderTimeData.total ) ? data.renderTimeData.total : null ;
            var networkMs = ( serverMs !== null ) ? Math.round( Math.max( 0, totalMs - serverMs ) ) : null ;
            ajaxRenderTimes[ '$hostname' ] = {
                total: totalMs,
                server: serverMs,
                network: networkMs,
                dbType: ( data && data.dbType ) ? data.dbType : 'MySQL',
                renderTimeData: ( data && data.renderTimeData ) ? data.renderTimeData : null,
                error: false
            } ;
            myCallback( $blockNum, data ) ;
        } )
        .fail( function( jqXHR, textStatus, errorThrown ) {
            delete pendingHosts[ '$hostname' ] ;
            var totalMs = Date.now() - ajaxStart_{$blockNum} ;
            ajaxRenderTimes[ '$hostname' ] = {
                total: totalMs,
                server: null,
                network: null,
                dbType: 'Unknown',
                renderTimeData: null,
                error: true,
                errorText: textStatus
            } ;
            console.error( 'AJAX failed for $hostname:', textStatus, errorThrown ) ;
            // Show error in tables so user knows this host failed
            var errorRow = '<tr class="errorNotice"><td>$hostname</td><td>9</td><td colspan="12">Connection failed: ' + textStatus + '</td></tr>' ;
            \$( errorRow ).prependTo( '#nwprocesstbodyid' ) ;
            \$( errorRow ).prependTo( '#fullprocesstbodyid' ) ;
            trackHostByDbType( 'MySQL' ) ;
            trackLevelByDbType( 'MySQL', 9, 1, '$hostname' ) ;
            updateDbTypeOverview() ;
            updateScoreboard() ;
        } )
        .always( onAjaxComplete ) ;

JSAJAX;
    }
}
