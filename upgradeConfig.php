<?php

/*
 * upgradeConfig.php - Convert aql_config.xml from legacy flat <param> format
 *                     to new grouped element format.
 *
 * Usage:
 *   php upgradeConfig.php                  # Preview new format (dry run)
 *   php upgradeConfig.php --write          # Write new format to aql_config.xml
 *                                          # (saves backup as aql_config.xml.bk)
 *
 * The actual conversion logic lives in Libs/ConfigUpgrader.php so it can
 * be unit tested in isolation. This script is a thin file IO wrapper.
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

require __DIR__ . '/vendor/autoload.php' ;

use com\kbcmdba\aql\Libs\ConfigUpgrader ;

$configFile = __DIR__ . '/aql_config.xml' ;
$doWrite = in_array( '--write', $argv ?? [] ) ;

if ( ! file_exists( $configFile ) ) {
    fwrite( STDERR, "Error: $configFile not found.\n" ) ;
    exit( 1 ) ;
}

$xmlString = file_get_contents( $configFile ) ;
if ( false === $xmlString ) {
    fwrite( STDERR, "Error: Could not read $configFile.\n" ) ;
    exit( 1 ) ;
}

if ( ConfigUpgrader::isAlreadyUpgraded( $xmlString ) ) {
    fwrite( STDERR, "Config is already version 2. Nothing to do.\n" ) ;
    exit( 0 ) ;
}

try {
    $out = ConfigUpgrader::upgrade( $xmlString ) ;
} catch ( \Exception $e ) {
    fwrite( STDERR, "Error: " . $e->getMessage() . "\n" ) ;
    exit( 1 ) ;
}

if ( $doWrite ) {
    // Backup existing config
    $backupFile = $configFile . '.bk' ;
    if ( ! copy( $configFile, $backupFile ) ) {
        fwrite( STDERR, "Error: Could not create backup at $backupFile\n" ) ;
        exit( 1 ) ;
    }
    fwrite( STDERR, "Backup saved to: $backupFile\n" ) ;

    if ( file_put_contents( $configFile, $out ) === false ) {
        fwrite( STDERR, "Error: Could not write to $configFile\n" ) ;
        exit( 1 ) ;
    }
    fwrite( STDERR, "Config upgraded: $configFile\n" ) ;

    // Validate
    $dtdFile = __DIR__ . '/aql_config.dtd' ;
    if ( file_exists( $dtdFile ) ) {
        exec( "xmllint --valid --noout " . escapeshellarg( $configFile ) . " 2>&1", $output, $rc ) ;
        if ( $rc === 0 ) {
            fwrite( STDERR, "DTD validation: PASSED\n" ) ;
        } else {
            fwrite( STDERR, "DTD validation: FAILED\n" ) ;
            fwrite( STDERR, implode( "\n", $output ) . "\n" ) ;
        }
    }
} else {
    // Dry run - print to stdout
    echo $out ;
    fwrite( STDERR, "\n--- Dry run. Use --write to apply changes. ---\n" ) ;
}
