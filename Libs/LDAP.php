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
        if ( empty( $user ) || empty( $password ) ) {
            return false;
        }
        $adServer = $oConfig->getLDAPHost() ;
        $adUser = $oConfig->getLDAPUserDomain() . '\\' . $user ;
        $ldap = ldap_connect( $adServer ) ;
        if ( false === $ldap ) {
            echo "LDAP is mis-configured or blocked.\n" ;
            die() ;
        }
        ldap_set_option( $ldap, LDAP_OPT_PROTOCOL_VERSION, 3 ) ;
        ldap_set_option( $ldap, LDAP_OPT_REFERRALS, 0 ) ;
        ldap_set_option( $ldap, LDAP_OPT_NETWORK_TIMEOUT, 10 ) ;
        $bind = @ldap_bind( $ldap, $adUser, $password ) ;
        if ( false === $bind ) {
            return false ;
        }
        // check group(s)
        $filter = "(sAMAccountName=" . $user . ")" ;
        $attr = array( "memberof" ) ;
        $result = ldap_search( $ldap, $oConfig->getLDAPDomainName(), $filter, $attr )
                or exit( "Unable to search LDAP server" ) ;
        $entries = ldap_get_entries( $ldap, $result ) ;
        ldap_unbind( $ldap ) ;
        $canAccess = 0;
        foreach ( $entries[ 0 ][ 'memberof' ] as $grps ) {
            if ( strpos($grps, $oConfig->getLDAPUserGroup() ) ) {
                $canAccess = 1;
            }
        }
        if ( 1 === $canAccess) {
            $_SESSION[ 'AuthUser' ] = $user ;
            $_SESSION[ 'AuthCanAccess' ] = $canAccess ;
            return true ;
        }
        return false ;
    } // END OF authenticate( $user, $password )

}
