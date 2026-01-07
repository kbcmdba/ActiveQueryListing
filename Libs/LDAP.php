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

namespace com\kbcmdba\aql\Libs ;

use com\kbcmdba\aql\Libs\Config ;
use com\kbcmdba\aql\Libs\Exceptions\ConfigurationException ;

/**
 * A LDAP authentication class.
*/
class LDAP
{
    /**
     * Class Constructor - never intended to be instantiated.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        throw new \Exception("Improper use of Tools class") ;
    }

    /**
     * Return true when the configuration of LDAP is not done or when the
     * user has been authenticated properly. Return false otherwise.
     *
     * @return boolean When user authentication is accepted, returns true.
     */
    static function authenticate( $user, $password ) {
        $oConfig = null ;
        try {
            $oConfig = new Config() ;
            if ( ! $oConfig->getDoLDAPAuthentication() ) {
                return true ;
            }
        }
        catch ( ConfigurationException $e ) {
            return true ;
        }
        $debugEnabled = $oConfig->getLDAPDebugConnection() ;
        $debug = function( $msg ) use ( $debugEnabled ) {
            if ( $debugEnabled ) {
                echo "<pre>LDAP DEBUG: " . htmlspecialchars( $msg, ENT_QUOTES, 'UTF-8' ) . "</pre>\n" ;
            }
        } ;

        if ( empty( $user ) || empty( $password ) ) {
            $debug( "Empty user or password" ) ;
            return false;
        }
        $adServer = $oConfig->getLDAPHost() ;
        $adUser = $oConfig->getLDAPUserDomain() . '\\' . $user ;
        $debug( "Connecting to: $adServer" ) ;
        $debug( "Binding as: $adUser" ) ;
        $ldap = ldap_connect( $adServer ) ;
        if ( false === $ldap ) {
            $debug( "ldap_connect() failed" ) ;
            echo "LDAP is mis-configured or blocked.\n" ;
            die() ;
        }
        $debug( "ldap_connect() OK" ) ;
        ldap_set_option( $ldap, LDAP_OPT_PROTOCOL_VERSION, 3 ) ;
        ldap_set_option( $ldap, LDAP_OPT_REFERRALS, 0 ) ;
        ldap_set_option( $ldap, LDAP_OPT_NETWORK_TIMEOUT, 10 ) ;
        if ( ! $oConfig->getLDAPVerifyCert() ) {
            ldap_set_option( null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER ) ;
            $debug( "SSL certificate verification disabled" ) ;
        }
        $bind = @ldap_bind( $ldap, $adUser, $password ) ;
        if ( false === $bind ) {
            $debug( "ldap_bind() FAILED - " . ldap_error( $ldap ) ) ;
            ldap_unbind( $ldap ) ;
            return false ;
        }
        $debug( "ldap_bind() OK" ) ;
        // check group(s)
        $filter = "(sAMAccountName=" . $user . ")" ;
        $attr = array( "memberof" ) ;
        $debug( "Searching: " . $oConfig->getLDAPDomainName() . " with filter: $filter" ) ;
        $result = ldap_search( $ldap, $oConfig->getLDAPDomainName(), $filter, $attr ) ;
        if ( false === $result ) {
            $debug( "ldap_search() FAILED - " . ldap_error( $ldap ) ) ;
            ldap_unbind( $ldap ) ;
            return false ;
        }
        $debug( "ldap_search() OK" ) ;
        $entries = ldap_get_entries( $ldap, $result ) ;
        ldap_unbind( $ldap ) ;
        $debug( "Found " . $entries[ 'count' ] . " entries" ) ;
        if ( $entries[ 'count' ] === 0 ) {
            $debug( "User not found in directory" ) ;
            return false ;
        }
        $canAccess = 0;
        $memberOf = $entries[ 0 ][ 'memberof' ] ?? [] ;
        $debug( "Looking for group: " . $oConfig->getLDAPUserGroup() ) ;
        $debug( "User has " . ( $memberOf[ 'count' ] ?? 0 ) . " group memberships" ) ;
        foreach ( $memberOf as $key => $grps ) {
            if ( $key === 'count' ) continue ;
            $debug( "  - $grps" ) ;
            if ( strpos($grps, $oConfig->getLDAPUserGroup() ) !== false ) {
                $canAccess = 1;
                $debug( "  ^ MATCH!" ) ;
            }
        }
        if ( 1 === $canAccess) {
            $_SESSION[ 'AuthUser' ] = $user ;
            $_SESSION[ 'AuthCanAccess' ] = $canAccess ;
            $debug( "SUCCESS - Access granted" ) ;
            return true ;
        }
        $debug( "FAILED - User not in required group" ) ;
        return false ;
    } // END OF authenticate( $user, $password )

}
