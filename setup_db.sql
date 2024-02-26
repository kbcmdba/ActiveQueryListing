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

-- WARNING - using this script could wipe out any existing data in
-- aql_db so make sure you take a backup first if it's needed.
--
-- The following line will destroy existing data if uncommented.
-- DROP DATABASE IF EXISTS aql_db ;

CREATE DATABASE IF NOT EXISTS aql_db DEFAULT CHARACTER SET = 'utf8mb4' DEFAULT COLLATE = 'utf8mb4_bin' ;
USE aql_db ;

CREATE TABLE IF NOT EXISTS host (
       host_id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
     , hostname          VARCHAR( 64 ) NOT NULL
     , port_number       SMALLINT UNSIGNED NOT NULL DEFAULT 3306
     , description       TEXT NULL DEFAULT NULL
     , should_monitor    BOOLEAN NOT NULL DEFAULT 1
     , should_backup     BOOLEAN NOT NULL DEFAULT 1
     , should_schemaspy  BOOLEAN NOT NULL DEFAULT 0
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
     , UNIQUE udx_hostname_port_number ( hostname, port_number )
     , KEY idx_should_monitor ( should_monitor, decommissioned )
     , KEY idx_decommissioned ( decommissioned )
     ) ENGINE=InnoDB ;

INSERT host
VALUES ( 1                 -- id
       , 'localhost'       -- hostname
       , 3306              -- port_number
       , 'This host'       -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 0                 -- should_schemaspy
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , CURRENT_TIMESTAMP -- created
       , CURRENT_TIMESTAMP -- updated
       , CURRENT_TIMESTAMP -- last_audited
     )
     , ( 2                 -- id
       , '127.0.0.1'       -- hostname
       , 3306              -- port_number
       , 'localhostx2'     -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 0                 -- should_schemaspy
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , CURRENT_TIMESTAMP -- created
       , CURRENT_TIMESTAMP -- updated
       , CURRENT_TIMESTAMP -- last_audited
     )
     , ( 3                 -- id
       , '192.168.256.256' -- hostname
       , 3306              -- port_number
       , 'Bad host'        -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 0                 -- should_schemaspy
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , CURRENT_TIMESTAMP -- created
       , CURRENT_TIMESTAMP -- updated
       , CURRENT_TIMESTAMP -- last_audited
    ), ( 4                 -- id
       , 'localhost'       -- hostname
       , 3307              -- port_number
       , 'Second instance on this host' -- description
       , 1                 -- should_monitor
       , 1                 -- should_backup
       , 0                 -- should_schemaspy
       , 1                 -- revenue_impacting
       , 0                 -- decommissioned
       , 10                -- alert_crit_secs
       , 5                 -- alert_warn_secs
       , 2                 -- alert_info_secs
       , -1                -- alert_low_secs
       , CURRENT_TIMESTAMP -- created
       , CURRENT_TIMESTAMP -- updated
       , CURRENT_TIMESTAMP -- last_audited
     )
    ON DUPLICATE KEY
UPDATE updated = CURRENT_TIMESTAMP
     ;

CREATE TABLE IF NOT EXISTS host_group (
       host_group_id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
     , tag               VARCHAR( 16 ) NOT NULL DEFAULT ''
     , short_description VARCHAR( 255 ) NOT NULL DEFAULT ''
     , full_description  TEXT NULL DEFAULT NULL
     , created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
     , updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP
     , UNIQUE ux_tag ( tag )
     ) ENGINE=InnoDB ;

INSERT host_group
VALUES ( 1, 'localhost', 'localhost', 'localhost in all forms', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 2, 'prod'     , 'prod'     , 'Production'            , CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 3, 'pilot'    , 'pilot'    , 'Pilot'                 , CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 4, 'stage'    , 'stage'    , 'Staging'               , CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 5, 'qa'       , 'qa'       , 'QA'                    , CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 6, 'dev'      , 'dev'      , 'Development'           , CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
    ON DUPLICATE KEY
UPDATE updated = CURRENT_TIMESTAMP
     ;


CREATE TABLE IF NOT EXISTS host_group_map (
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
VALUES ( 1, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 1, 2, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
     , ( 1, 4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP )
    ON DUPLICATE KEY
UPDATE updated = CURRENT_TIMESTAMP
     ;
