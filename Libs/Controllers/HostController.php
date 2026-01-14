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

namespace com\kbcmdba\aql\Libs\Controllers ;

use com\kbcmdba\aql\Libs\Exceptions\ControllerException ;
use com\kbcmdba\aql\Libs\Models\HostModel ;

class HostController extends ControllerBase
{

    /**
     * Class constructor
     *
     * @param string $readWriteMode "read", "write", or "admin"
     * @throws ControllerException
     */
    public function __construct($readWriteMode = 'write')
    {
        parent::__construct($readWriteMode) ;
    }

    public function dropTable()
    {
        $sql = "DROP TABLE IF EXISTS host" ;
        $this->doDDL($sql) ;
    }

    public function createTable()
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS host (
  host_id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
, hostname          VARCHAR(64) NOT NULL
, port_number       INT UNSIGNED NOT NULL DEFAULT 3306
, description       TEXT DEFAULT NULL
, db_type           ENUM('MySQL', 'InnoDBCluster', 'MS-SQL', 'Redis', 'OracleDB', 'Cassandra', 'DataStax', 'MongoDB', 'RDS', 'Aurora') NOT NULL DEFAULT 'MySQL'
, db_version        VARCHAR(30) NOT NULL DEFAULT ''
, should_monitor    BOOLEAN NOT NULL DEFAULT 1
, should_backup     BOOLEAN NOT NULL DEFAULT 1
, should_schemaspy  BOOLEAN NOT NULL DEFAULT 0
, revenue_impacting BOOLEAN NOT NULL DEFAULT 1
, decommissioned    BOOLEAN NOT NULL DEFAULT 0
, alert_crit_secs   INT UNSIGNED NOT NULL DEFAULT 0
, alert_warn_secs   INT UNSIGNED NOT NULL DEFAULT 0
, alert_info_secs   INT UNSIGNED NOT NULL DEFAULT 0
, alert_low_secs    INT UNSIGNED NOT NULL DEFAULT -1
, created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
, updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
, last_audited      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
, UNIQUE udx_hostname ( hostname )
, KEY idx_should_monitor ( should_monitor, decommissioned )
, KEY idx_decommissioned ( decommissioned )
) ENGINE=InnoDB

SQL;
        $this->doDDL($sql) ;
    }

    public function dropTriggers()
    {
        return ;
    }

    public function createTriggers()
    {
        return ;
    }

    public function get($id)
    {
        $sql = <<<SQL
SELECT host_id
     , hostname
     , port_number
     , description
     , should_monitor
     , should_backup
     , revenue_impacting
     , decommissioned
     , alert_crit_secs
     , alert_warn_secs
     , alert_info_secs
     , alert_low_secs
     , created
     , updated
     , last_audited
  FROM host
 WHERE host_id = ?
SQL;
        $stmt = $this->_dbh->prepare($sql) ;
        if ((! $stmt) || (! $stmt->bind_param('i', $id))) {
            throw new ControllerException('Failed to prepare SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        if (! $stmt->execute()) {
            throw new ControllerException('Failed to execute SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        $id = $hostName = $portNumber = $description = $shouldMonitor = $shouldBackup = null ;
        $revenueImpacting = $decommissioned = $alertCritSecs = $alertWarnSecs = null ;
        $alertInfoSecs = $alertLowSecs = $created = $updated = $lastAudited = null ;
        if (! $stmt->bind_result(
            $id,
            $hostName,
            $portNumber,
            $description,
            $shouldMonitor,
            $shouldBackup,
            $revenueImpacting,
            $decommissioned,
            $alertCritSecs,
            $alertWarnSecs,
            $alertInfoSecs,
            $alertLowSecs,
            $created,
            $updated,
            $lastAudited
        )) {
            throw new ControllerException('Failed to bind to result: (' . $this->_dbh->error . ')') ;
        }
        if ($stmt->fetch()) {
            $model = new HostModel() ;
            $model->setId($id) ;
            $model->setHostName($hostName) ;
            $model->setPortNumber($portNumber) ;
            $model->setDescription($description) ;
            $model->setShouldMonitor($shouldMonitor) ;
            $model->setShouldBackup($shouldBackup) ;
            $model->setRevenueImpacting($revenueImpacting) ;
            $model->setDecommissioned($decommissioned) ;
            $model->setAlertCritSecs($alertCritSecs) ;
            $model->setAlertWarnSecs($alertWarnSecs) ;
            $model->setAlertInfoSecs($alertInfoSecs) ;
            $model->setAlertLowSecs($alertLowSecs) ;
            $model->setCreated($created) ;
            $model->setUpdated($updated) ;
            $model->setLastAudited($lastAudited) ;
        } else {
            $model = null ;
        }
        return($model) ;
    }

    /**
     * Get host records matching the specified filters.
     *
     * @param array $filters Associative array of column => value pairs for WHERE conditions.
     *                       Supported columns: host_id, hostname, port_number, should_monitor,
     *                       should_backup, revenue_impacting, decommissioned.
     *                       Example: ['should_monitor' => 1, 'decommissioned' => 0]
     * @return HostModel[]
     * @throws ControllerException
     */
    public function getSome(array $filters = [])
    {
        // Whitelist of allowed filter columns to prevent SQL injection
        $allowedColumns = [
            'host_id' => 'i',
            'hostname' => 's',
            'port_number' => 'i',
            'should_monitor' => 'i',
            'should_backup' => 'i',
            'revenue_impacting' => 'i',
            'decommissioned' => 'i'
        ] ;

        $whereClauses = [] ;
        $bindTypes = '' ;
        $bindValues = [] ;

        foreach ($filters as $column => $value) {
            if (!array_key_exists($column, $allowedColumns)) {
                throw new ControllerException("Invalid filter column: $column") ;
            }
            $whereClauses[] = "$column = ?" ;
            $bindTypes .= $allowedColumns[$column] ;
            $bindValues[] = $value ;
        }

        $whereSQL = empty($whereClauses) ? '1 = 1' : implode(' AND ', $whereClauses) ;

        $sql = <<<SQL
SELECT host_id
     , hostname
     , port_number
     , description
     , should_monitor
     , should_backup
     , revenue_impacting
     , decommissioned
     , alert_crit_secs
     , alert_warn_secs
     , alert_info_secs
     , alert_low_secs
     , created
     , updated
     , last_audited
  FROM host
 WHERE $whereSQL

SQL;
        $stmt = $this->_dbh->prepare($sql) ;
        if (! $stmt) {
            throw new ControllerException('Failed to prepare SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        if (!empty($bindValues)) {
            $stmt->bind_param($bindTypes, ...$bindValues) ;
        }
        if (! $stmt->execute()) {
            throw new ControllerException('Failed to execute SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        $id = $hostName = $portNumber = $description = $shouldMonitor = $shouldBackup = null ;
        $revenueImpacting = $decommissioned = $alertCritSecs = $alertWarnSecs = null ;
        $alertInfoSecs = $alertLowSecs = $created = $updated = $lastAudited = null ;
        if (! $stmt->bind_result(
            $id,
            $hostName,
            $portNumber,
            $description,
            $shouldMonitor,
            $shouldBackup,
            $revenueImpacting,
            $decommissioned,
            $alertCritSecs,
            $alertWarnSecs,
            $alertInfoSecs,
            $alertLowSecs,
            $created,
            $updated,
            $lastAudited
        )) {
            throw new ControllerException('Failed to bind to result: (' . $this->_dbh->error . ')') ;
        }
        $models = [] ;
        while ($stmt->fetch()) {
            $model = new HostModel() ;
            $model->setId($id) ;
            $model->setHostName($hostName) ;
            $model->setPortNumber($portNumber) ;
            $model->setDescription($description) ;
            $model->setShouldMonitor($shouldMonitor) ;
            $model->setShouldBackup($shouldBackup) ;
            $model->setRevenueImpacting($revenueImpacting) ;
            $model->setDecommissioned($decommissioned) ;
            $model->setAlertCritSecs($alertCritSecs) ;
            $model->setAlertWarnSecs($alertWarnSecs) ;
            $model->setAlertInfoSecs($alertInfoSecs) ;
            $model->setAlertLowSecs($alertLowSecs) ;
            $model->setCreated($created) ;
            $model->setUpdated($updated) ;
            $model->setLastAudited($lastAudited) ;
            $models[] = $model ;
        }
        return($models) ;
    }

    public function getAll()
    {
        return $this->getSome() ;
    }

    /**
     * @param HostModel $model
     * @see ControllerBase::add()
     */
    public function add($model)
    {
        if ($model->validateForAdd()) {
            try {
                $query = <<<SQL
INSERT host
     ( hostname
     , description
     , port_number
     , should_monitor
     , should_backup
     , revenue_impacting
     , decommissioned
     , alert_crit_secs
     , alert_warn_secs
     , alert_info_secs
     , alert_low_secs
     , lastAudited
     )
VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
SQL;
                $hostName         = $model->getHostName() ;
                $portNumber       = $model->getPortNumber() ;
                $description      = $model->getDescription() ;
                $shouldMonitor    = $model->getShouldMonitor() ;
                $shouldBackup     = $model->getShouldBackup() ;
                $revenueImpacting = $model->getRevenueImpacting() ;
                $decommissioned   = $model->getDecommissioned() ;
                $alertCritSecs    = $model->getAlertCritSecs() ;
                $alertWarnSecs    = $model->getAlertWarnSecs() ;
                $alertInfoSecs    = $model->getAlertInfoSecs() ;
                $alertLowSecs     = $model->getAlertLowSecs() ;
                $lastAudited      = $model->getLastAudited() ;
                $stmt             = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'sisiiiiiiiis',
                    $hostName,
                    $portNumber,
                    $description,
                    $shouldMonitor,
                    $shouldBackup,
                    $revenueImpacting,
                    $decommissioned,
                    $alertCritSecs,
                    $alertWarnSecs,
                    $alertInfoSecs,
                    $alertLowSecs,
                    $lastAudited
                ))) {
                    throw new ControllerException('Binding parameters for prepared statement failed.') ;
                }
                if (! $stmt->execute()) {
                    throw new ControllerException('Failed to execute INSERT statement. ('
                                                 . $this->_dbh->error .
                                                 ')') ;
                }
                $newId = $stmt->insert_id ;
                /**
                 * @SuppressWarnings checkAliases
                 */
                if (! $stmt->close()) {
                    throw new ControllerException('Something broke while trying to close the prepared statement.') ;
                }
                return $newId ;
            } catch (\Exception $e) {
                throw new ControllerException($e->getMessage()) ;
            }
        } else {
            throw new ControllerException("Invalid data.") ;
        }
    }

    /**
     * @param HostModel $model
     * @throws ControllerException
     * @return boolean
     */
    public function update($model)
    {
        if ($model->validateForUpdate()) {
            try {
                $query = <<<SQL
UPDATE host
   SET hostname = ?
     , port_number = ?
     , description = ?
     , should_monitor = ?
     , should_backup = ?
     , revenue_impacting = ?
     , decommissioned = ?
     , alert_crit_secs = ?
     , alert_warn_secs = ?
     , alert_info_secs = ?
     , alert_low_secs = ?
     , lastAudited = ?
 WHERE id = ?
SQL;
                $id               = $model->getId() ;
                $hostName         = $model->getHostName() ;
                $portNumber       = $model->getPortNumber() ;
                $description      = $model->getDescription() ;
                $shouldMonitor    = $model->getShouldMonitor() ;
                $shouldBackup     = $model->getShouldBackup() ;
                $revenueImpacting = $model->getRevenueImpacting() ;
                $decommissioned   = $model->getDecommissioned() ;
                $alertCritSecs    = $model->getAlertCritSecs() ;
                $alertWarnSecs    = $model->getAlertWarnSecs() ;
                $alertInfoSecs    = $model->getAlertInfoSecs() ;
                $alertLowSecs     = $model->getAlertLowSecs() ;
                $lastAudited      = $model->getLastAudited() ;
                $stmt       = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'sisiiiiiiiisi',
                    $hostName,
                    $portNumber,
                    $description,
                    $shouldMonitor,
                    $shouldBackup,
                    $revenueImpacting,
                    $decommissioned,
                    $alertCritSecs,
                    $alertWarnSecs,
                    $alertInfoSecs,
                    $alertLowSecs,
                    $lastAudited,
                    $id
                ))) {
                    throw new ControllerException('Binding parameters for prepared statement failed.') ;
                }
                if (!$stmt->execute()) {
                    throw new ControllerException('Failed to execute UPDATE statement. (' . $this->_dbh->error . ')') ;
                }
                /**
                 * @SuppressWarnings checkAliases
                 */
                if (!$stmt->close()) {
                    throw new ControllerException('Something broke while trying to close the prepared statement.') ;
                }
                return $id ;
            } catch (\Exception $e) {
                throw new ControllerException($e->getMessage()) ;
            }
        } else {
            throw new ControllerException("Invalid data.") ;
        }
    }

    public function delete($model)
    {
        $this->deleteModelById("DELETE FROM host WHERE id = ?", $model) ;
    }
}
