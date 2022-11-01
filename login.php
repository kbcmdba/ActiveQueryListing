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

require( 'vendor/autoload.php' ) ;
require( 'utility.php' ) ;

use com\kbcmdba\aql\Libs\Tools ;
use com\kbcmdba\aql\Libs\WebPage ;

if ( ! Tools::isNullOrEmptyString( Tools::get( 'logout' ) ) ) {
	session_unset() ;
	// unset( $_SESSION[ 'user' ], $_SESSION[ 'access' ] ) ;
	$_SESSION = array() ;
	session_destroy() ;
}

if  ( !Tools::isNullOrEmptyString( Tools::post( 'user' ) )
   || !Tools::isNullOrEmptyString( Tools::post( 'password' ) )
    ) {
	if ( authenticate( Tools::post( 'user' ), Tools::post( 'password' ) ) ) {
		header( "Location: manageHosts.php" ) ;
		die() ;
	}
    echo "Login failed: Incorrect user name, password, or access.<br />" ;
}

if ( ! Tools::isNullOrEmptyString( Tools::get( 'logout' ) ) ) {
    echo "Logout successful" ;
}

$page = new WebPage( 'AQL Data Management Login' ) ;
$page->setBody( <<<HTML
<h2>AQL Data Management Login</h2>
<div>
    <form method="post" action="login.php">
        <label for="user"><b>Username</b></label>
        <input type="text" placeholder="Enter Username" name="user" required="required" />
        <label for="password"><b>Password</b></label>
        <input type="password" name="password" required="required" />
        <input type="submit" name="submit" value="submit" />
    </form> 
</div>

HTML);
$page->displayPage() ;
