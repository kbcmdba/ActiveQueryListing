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

namespace com\kbcmdba\aql\Tests\Libs ;

use com\kbcmdba\aql\Libs\Utility ;
use PHPUnit\Framework\TestCase ;

class UtilityTest extends TestCase
{

    // -------------------------------------------------------------------------
    // applyParamToLimits
    // -------------------------------------------------------------------------

    public function testParamAnyDoesNothing() : void
    {
        $limits = 'WHERE 1=1' ;
        Utility::applyParamToLimits( 'any', 'active', 'any', $limits ) ;
        $this->assertSame( 'WHERE 1=1', $limits ) ;
    }

    public function testParamOneAppends1() : void
    {
        $limits = 'WHERE 1=1' ;
        Utility::applyParamToLimits( '1', 'should_monitor', 'any', $limits ) ;
        $this->assertSame( 'WHERE 1=1 AND should_monitor = 1', $limits ) ;
    }

    public function testParamZeroAppends0() : void
    {
        $limits = 'WHERE 1=1' ;
        Utility::applyParamToLimits( '0', 'decommissioned', 'any', $limits ) ;
        $this->assertSame( 'WHERE 1=1 AND decommissioned = 0', $limits ) ;
    }

    public function testParamDefaultWithNonAnyDefault() : void
    {
        // Unrecognised value falls through to default; default != 'any' so it's applied
        $limits = 'WHERE 1=1' ;
        Utility::applyParamToLimits( '', 'revenue_impacting', '1', $limits ) ;
        $this->assertSame( 'WHERE 1=1 AND revenue_impacting = 1', $limits ) ;
    }

    public function testParamDefaultWithAnyDefault() : void
    {
        // Unrecognised value with 'any' default: nothing appended
        $limits = 'WHERE 1=1' ;
        Utility::applyParamToLimits( '', 'revenue_impacting', 'any', $limits ) ;
        $this->assertSame( 'WHERE 1=1', $limits ) ;
    }

    public function testParamAccumulatesMultipleClauses() : void
    {
        $limits = 'WHERE 1=1' ;
        Utility::applyParamToLimits( '1', 'should_monitor',  'any', $limits ) ;
        Utility::applyParamToLimits( '0', 'decommissioned',  'any', $limits ) ;
        Utility::applyParamToLimits( '1', 'revenue_impacting', 'any', $limits ) ;
        $this->assertSame(
            'WHERE 1=1 AND should_monitor = 1 AND decommissioned = 0 AND revenue_impacting = 1',
            $limits
        ) ;
    }

    // -------------------------------------------------------------------------
    // buildRadioChoicesHtml
    // -------------------------------------------------------------------------

    public function testRadioChecksYes() : void
    {
        $html = Utility::buildRadioChoicesHtml( 'Monitored', 'should_monitor', '1' ) ;
        $this->assertStringContainsString( 'value="1" checked="checked"', $html ) ;
        $this->assertStringNotContainsString( 'value="0" checked="checked"', $html ) ;
        $this->assertStringNotContainsString( 'value="any" checked="checked"', $html ) ;
    }

    public function testRadioChecksNo() : void
    {
        $html = Utility::buildRadioChoicesHtml( 'Monitored', 'should_monitor', '0' ) ;
        $this->assertStringContainsString( 'value="0" checked="checked"', $html ) ;
        $this->assertStringNotContainsString( 'value="1" checked="checked"', $html ) ;
        $this->assertStringNotContainsString( 'value="any" checked="checked"', $html ) ;
    }

    public function testRadioChecksAny() : void
    {
        $html = Utility::buildRadioChoicesHtml( 'Monitored', 'should_monitor', 'any' ) ;
        $this->assertStringContainsString( 'value="any" checked="checked"', $html ) ;
        $this->assertStringNotContainsString( 'value="1" checked="checked"', $html ) ;
        $this->assertStringNotContainsString( 'value="0" checked="checked"', $html ) ;
    }

    public function testRadioUnknownValueChecksNothing() : void
    {
        $html = Utility::buildRadioChoicesHtml( 'Monitored', 'should_monitor', 'other' ) ;
        $this->assertStringNotContainsString( 'checked="checked"', $html ) ;
    }

    public function testRadioContainsLabelAndName() : void
    {
        $html = Utility::buildRadioChoicesHtml( 'My Label', 'my_field', 'any' ) ;
        $this->assertStringContainsString( 'My Label', $html ) ;
        $this->assertStringContainsString( 'name="my_field"', $html ) ;
    }

    public function testRadioReturnsTableRow() : void
    {
        $html = Utility::buildRadioChoicesHtml( 'L', 'n', '1' ) ;
        $this->assertStringStartsWith( '<tr>', $html ) ;
        $this->assertStringEndsWith( '</tr>', $html ) ;
    }

    // -------------------------------------------------------------------------
    // applyAndOr
    // -------------------------------------------------------------------------

    public function testAndOrMatchSetsCheckedAndResult() : void
    {
        $checked = '' ;
        $result  = '' ;
        Utility::applyAndOr( 'AND', 'AND', $checked, $result ) ;
        $this->assertSame( 'checked="checked"', $checked ) ;
        $this->assertSame( 'AND', $result ) ;
    }

    public function testAndOrCaseInsensitiveMatch() : void
    {
        $checked = '' ;
        $result  = '' ;
        Utility::applyAndOr( 'and', 'AND', $checked, $result ) ;
        $this->assertSame( 'checked="checked"', $checked ) ;
    }

    public function testAndOrNoMatchLeavesUnchanged() : void
    {
        $checked = '' ;
        $result  = 'AND' ;
        Utility::applyAndOr( 'OR', 'AND', $checked, $result ) ;
        $this->assertSame( '', $checked ) ;
        $this->assertSame( 'AND', $result ) ;  // result unchanged
    }

    public function testAndOrOrValue() : void
    {
        $checked = '' ;
        $result  = '' ;
        Utility::applyAndOr( 'OR', 'OR', $checked, $result ) ;
        $this->assertSame( 'OR', $result ) ;
    }

    // -------------------------------------------------------------------------
    // appendSelectOption
    // -------------------------------------------------------------------------

    public function testSelectOptionSelectedWhenInValues() : void
    {
        $list = '' ;
        Utility::appendSelectOption( 'Production', 'prod', [ 'dev', 'prod', 'staging' ], $list ) ;
        $this->assertStringContainsString( 'value="prod" checked="checked"', $list ) ;
    }

    public function testSelectOptionNotSelectedWhenAbsent() : void
    {
        $list = '' ;
        Utility::appendSelectOption( 'Production', 'prod', [ 'dev', 'staging' ], $list ) ;
        $this->assertStringContainsString( 'value="prod"', $list ) ;
        $this->assertStringNotContainsString( 'checked="checked"', $list ) ;
    }

    public function testSelectOptionContainsLabel() : void
    {
        $list = '' ;
        Utility::appendSelectOption( 'My Label', 'myval', [], $list ) ;
        $this->assertStringContainsString( '>My Label</option>', $list ) ;
    }

    public function testSelectOptionAccumulatesMultiple() : void
    {
        $list = '' ;
        Utility::appendSelectOption( 'Dev',     'dev',     [ 'dev' ], $list ) ;
        Utility::appendSelectOption( 'Staging', 'staging', [ 'dev' ], $list ) ;
        Utility::appendSelectOption( 'Prod',    'prod',    [ 'dev' ], $list ) ;
        $this->assertStringContainsString( 'value="dev" checked="checked"', $list ) ;
        $this->assertStringContainsString( 'value="staging"', $list ) ;
        $this->assertStringContainsString( 'value="prod"', $list ) ;
        // Only dev should be checked
        $this->assertSame( 1, substr_count( $list, 'checked="checked"' ) ) ;
    }

    public function testSelectOptionStrictTypeMatch() : void
    {
        // Uses strict comparison — '1' (string) should NOT match 1 (int)
        $list = '' ;
        Utility::appendSelectOption( 'Active', '1', [ 1 ], $list ) ;
        $this->assertStringNotContainsString( 'checked="checked"', $list ) ;
    }

    // -------------------------------------------------------------------------
    // buildHostAjaxJs
    // -------------------------------------------------------------------------

    private function makeJs() : array
    {
        return [ 'Blocks' => 0, 'AjaxCalls' => '' ] ;
    }

    public function testBuildAjaxJsIncrementsBlocks() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1:3306', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertSame( 1, $js['Blocks'] ) ;
    }

    public function testBuildAjaxJsMultipleHostsIncrement() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1:3306', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        Utility::buildHostAjaxJs( $js, 'db2:3306', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertSame( 2, $js['Blocks'] ) ;
    }

    public function testBuildAjaxJsContainsHostname() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'myhost.example.com:3306', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringContainsString( 'myhost.example.com:3306', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsContainsBaseUrl() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'https://aql.example.com/AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringContainsString( 'https://aql.example.com/AJAXgetaql.php', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsContainsAlertThresholds() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 60, 30, 15, 5 ) ;
        $this->assertStringContainsString( 'alertCritSecs=60', $js['AjaxCalls'] ) ;
        $this->assertStringContainsString( 'alertWarnSecs=30', $js['AjaxCalls'] ) ;
        $this->assertStringContainsString( 'alertInfoSecs=15', $js['AjaxCalls'] ) ;
        $this->assertStringContainsString( 'alertLowSecs=5',  $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsNoDebugByDefault() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringNotContainsString( '&debug=', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsDebugParamIncluded() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5, 'MySQL' ) ;
        $this->assertStringContainsString( '&debug=MySQL', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsDebugParamUrlEncoded() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5, 'MySQL,Redis' ) ;
        $this->assertStringContainsString( '&debug=MySQL%2CRedis', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsDebugLocksOmittedByDefault() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringNotContainsString( 'debugLocks', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsDebugLocksIncluded() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5, '', true ) ;
        $this->assertStringContainsString( '&debugLocks=1', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsUsesBlockNumInVarName() : void
    {
        $js = $this->makeJs() ;
        $js['Blocks'] = 7 ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringContainsString( 'ajaxStart_7', $js['AjaxCalls'] ) ;
        $this->assertStringContainsString( 'myCallback( 7,', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsSecondCallUsesNextBlockNum() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        Utility::buildHostAjaxJs( $js, 'db2', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringContainsString( 'ajaxStart_0', $js['AjaxCalls'] ) ;
        $this->assertStringContainsString( 'ajaxStart_1', $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsContainsPendingHostsTracking() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringContainsString( "pendingHosts[ 'db1' ] = true", $js['AjaxCalls'] ) ;
        $this->assertStringContainsString( "delete pendingHosts[ 'db1' ]", $js['AjaxCalls'] ) ;
    }

    public function testBuildAjaxJsContainsOnAjaxComplete() : void
    {
        $js = $this->makeJs() ;
        Utility::buildHostAjaxJs( $js, 'db1', 'AJAXgetaql.php', 30, 20, 10, 5 ) ;
        $this->assertStringContainsString( 'onAjaxComplete', $js['AjaxCalls'] ) ;
    }
}
