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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace com\kbcmdba\aql ;

session_start() ;

require_once 'vendor/autoload.php' ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\DBConnection ;
use com\kbcmdba\aql\Libs\MaintenanceWindow ;
use com\kbcmdba\aql\Libs\Tools ;

header( 'Content-type: application/json' ) ;

$response = [ 'success' => false, 'error' => '' ] ;

try {
    $config = new Config() ;

    // Check if maintenance windows feature is enabled
    if ( ! $config->getEnableMaintenanceWindows() ) {
        throw new \Exception( 'Maintenance windows feature is not enabled' ) ;
    }

    // Get parameters
    $targetType = Tools::param( 'targetType' ) ;  // 'host' or 'group'
    $targetId = Tools::param( 'targetId' ) ;
    $duration = Tools::param( 'duration' ) ;      // minutes
    $description = Tools::param( 'description' ) ;

    // Validate parameters
    if ( ! in_array( $targetType, [ 'host', 'group' ] ) ) {
        throw new \Exception( 'Invalid target type. Must be "host" or "group".' ) ;
    }

    if ( ! is_numeric( $targetId ) || $targetId <= 0 ) {
        throw new \Exception( 'Invalid target ID.' ) ;
    }

    if ( ! is_numeric( $duration ) || $duration <= 0 || $duration > 10080 ) {  // max 7 days
        throw new \Exception( 'Invalid duration. Must be between 1 and 10080 minutes (7 days).' ) ;
    }

    // Get user from session or default
    $createdBy = $_SESSION['AuthUser'] ?? $_SESSION['dba_auth']['user'] ?? 'anonymous' ;

    // Check DBA session if configured
    $sessionTimeout = $config->getDbaSessionTimeout() ;
    if ( isset( $_SESSION['dba_auth'] ) ) {
        if ( $_SESSION['dba_auth']['expires'] < time() ) {
            unset( $_SESSION['dba_auth'] ) ;
            // Session expired but don't require re-auth for now - just use anonymous
        } else {
            $createdBy = $_SESSION['dba_auth']['user'] ;
        }
    }

    // Default description if empty
    if ( empty( $description ) ) {
        $description = 'Quick silence from AQL' ;
    }

    // Create the ad-hoc maintenance window
    $dbc = new DBConnection() ;
    $dbh = $dbc->getConnection() ;

    $windowId = MaintenanceWindow::createAdhocWindow(
        $targetType,
        (int) $targetId,
        (int) $duration,
        $description,
        $createdBy,
        $dbh
    ) ;

    $response['success'] = true ;
    $response['windowId'] = $windowId ;
    $response['message'] = ucfirst( $targetType ) . ' silenced for ' . $duration . ' minutes.' ;
    $response['expiresAt'] = date( 'Y-m-d H:i:s', time() + ( $duration * 60 ) ) ;

} catch ( \Exception $e ) {
    $response['error'] = $e->getMessage() ;
}

echo json_encode( $response ) ;
