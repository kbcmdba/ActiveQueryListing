--
--
-- aql - Active Query Listing
--
-- Copyright (C) 2018 Kevin Benton - kbcmdba [at] gmail [dot] com
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License along
-- with this program; if not, write to the Free Software Foundation, Inc.,
-- 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
--

-- Script to drop and create aql_db

-- WARNING - using this script will wipe out any existing data in
-- aql_db so make sure you take a backup first if it's needed.

DROP DATABASE IF EXISTS aql_db ;
CREATE DATABASE aql_db ;
USE aql_db ;

CREATE TABLE host (
       host_id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
     , hostname          VARCHAR( 64 ) NOT NULL
     , description       TEXT NULL DEFAULT NULL
     , should_monitor    BOOLEAN NOT NULL DEFAULT 1
     , should_backup     BOOLEAN NOT NULL DEFAULT 1
     , revenue_impacting BOOLEAN NOT NULL DEFAULT 1
     , decommissioned    BOOLEAN NOT NULL DEFAULT 0
     , alert_crit_secs   INT NOT NULL DEFAULT 0
     , alert_warn_secs   INT NOT NULL DEFAULT 0
     , alert_info_secs   INT NOT NULL DEFAULT 0
     , alert_low_secs    INT NOT NULL DEFAULT -1
     , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP
     , last_audited      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , KEY idx_hostname ( hostname )
     , KEY idx_should_monitor ( should_monitor, decommissioned )
     , KEY idx_decommissioned ( decommissioned )
     ) ENGINE=InnoDB ;

INSERT host
VALUES ( 1                 -- id
       , 'localhost'       -- hostname
       , 'This host'       -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , NULL              -- created
       , NULL              -- updated
       , NULL              -- last_audited
    ), ( 2                 -- id
       , '127.0.0.1'       -- hostname
       , 'localhostx2'     -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , NULL              -- created
       , NULL              -- updated
       , NULL              -- last_audited
    ), ( 3                 -- id
       , '192.168.256.256' -- hostname
       , 'Bad host'        -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , NULL              -- created
       , NULL              -- updated
       , NULL              -- last_audited
     ) ;

CREATE TABLE host_group (
       host_group_id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
     , tag               VARCHAR( 16 ) NOT NULL DEFAULT ''
     , short_description VARCHAR( 255 ) NOT NULL DEFAULT ''
     , full_descripton   TEXT NULL DEFAULT NULL
     , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP
     , UNIQUE ux_tag ( tag )
     ) ENGINE=InnoDB ;

INSERT host_group
VALUES ( 1, 'localhost', 'localhost', 'localhost in all forms', NULL, NULL )
     , ( 2, 'prod'     , 'prod'     , 'Production'            , NULL, NULL )
     , ( 3, 'pilot'    , 'pilot'    , 'Pilot'                 , NULL, NULL )
     , ( 4, 'stage'    , 'stage'    , 'Staging'               , NULL, NULL )
     , ( 5, 'qa'       , 'qa'       , 'QA'                    , NULL, NULL )
     , ( 6, 'dev'      , 'dev'      , 'Development'           , NULL, NULL )
     ;

CREATE TABLE host_group_map (
       host_group_id INT UNSIGNED NOT NULL
     , host_id       INT UNSIGNED NOT NULL
     , created       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , updated       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                       ON UPDATE CURRENT_TIMESTAMP
     , last_audited  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , PRIMARY KEY ux_host_group_host ( host_id, host_group_id )
     , FOREIGN KEY ( host_group_id ) REFERENCES host_group( host_group_id )
                                      ON DELETE RESTRICT ON UPDATE RESTRICT
     , FOREIGN KEY ( host_id ) REFERENCES host( host_id )
                                      ON DELETE RESTRICT ON UPDATE RESTRICT
     ) ENGINE=InnoDB COMMENT='Many-many relationship of groups and host' ;

INSERT host_group_map
VALUES ( 1, 1, NULL, NULL, NULL )
     , ( 1, 2, NULL, NULL, NULL )
     ;

