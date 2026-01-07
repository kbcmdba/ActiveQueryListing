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

// @todo 01 Implement Host/Group Limiter
// @done 02 Protect code from SQL injection (2026-01-06)
// @done 02 Sort results table - TableSorter implemented
// @done 03 Set minimum refresh time to 15 seconds - $minRefresh in Config
// @done 04 Implement refresh setting - refresh param in index.php
// @done 10 Create host editor - manageData.php
// @done 15 Create group editor - manageData.php
// @done 20 Implement Kill button - AJAXKillProc.php
// @done 20 Add login capability to enable kill button as well as editing hosts/groups - LDAP auth
// @done 30 Detect duplicate and similar queries per-host
// @done 40 Detect queries that are blocked by other threads and display (2026-01-06)
