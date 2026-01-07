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
use com\kbcmdba\aql\Libs\Models\HostGroupMapModel ;

class HostGroupMapController extends ControllerBase
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
        $sql = "DROP TABLE IF EXISTS host_group_map" ;
        $this->doDDL($sql) ;
    }

    public function createTable()
    {
        $sql = <<<SQL
CREATE TABLE host_group_map (
  host_group_id     INT UNSIGNED NOT NULL
, host_id           INT UNSIGNED NOT NULL
, created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
, updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
, last_audited      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
, UNIQUE ux_host_group_host_id ( host_group_id, host_id )
) ENGINE=InnoDB ;

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

    /**
     * @param   $hostGroupId
     * @param   $hostId
     * @returns HostGroupMapModel
     * @throws  ControllerException
     */
    public function get($hostGroupId, $hostId)
    {
        $sql = <<<SQL
SELECT host_group_id
     , host_id
     , created
     , updated
     , last_audited
  FROM host_group_map
 WHERE host_group_id = ?
   AND host_id = ?
SQL;
        $stmt = $this->_dbh->prepare($sql) ;
        if ((! $stmt) || (! $stmt->bind_param('ii', $hostGroupId, $hostId))) {
            throw new ControllerException('Failed to prepare SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        if (! $stmt->execute()) {
            throw new ControllerException('Failed to execute SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        $hostGroupId = $hostId = $created = $updated = $lastAudited = null ;
        if (! $stmt->bind_result(
            $hostGroupId,
            $hostId,
            $created,
            $updated,
            $lastAudited
        )) {
            throw new ControllerException('Failed to bind to result: (' . $this->_dbh->error . ')') ;
        }
        if ($stmt->fetch()) {
            $model = new HostGroupModel() ;
            $model->setHostGroupId($hostGroupId) ;
            $model->setHostId($hostId) ;
            $model->setCreated($created) ;
            $model->setUpdated($updated) ;
            $model->setLastUpdated($lastUpdated) ;
        } else {
            $model = null ;
        }
        return($model) ;
    }

    /**
     * Get host group map records matching the specified filters.
     *
     * @param array $filters Associative array of column => value pairs for WHERE conditions.
     *                       Supported columns: host_group_id, host_id.
     *                       Example: ['host_group_id' => 1]
     * @return HostGroupMapModel[]
     * @throws ControllerException
     */
    public function getSome(array $filters = [])
    {
        // Whitelist of allowed filter columns to prevent SQL injection
        $allowedColumns = [
            'host_group_id' => 'i',
            'host_id' => 'i'
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
SELECT host_group_id
     , host_id
     , created
     , updated
     , last_audited
  FROM host_group_map
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
        $hostGroupId = $hostId = $created = $updated = $lastAudited = null ;
        if (! $stmt->bind_result(
            $hostGroupId,
            $hostId,
            $created,
            $updated,
            $lastAudited
        )) {
            throw new ControllerException('Failed to bind to result: (' . $this->_dbh->error . ')') ;
        }
        $models = [] ;
        while ($stmt->fetch()) {
            $model = new HostGroupMapModel() ;
            $model->setHostGroupId($hostGroupId) ;
            $model->setHostId($hostId) ;
            $model->setCreated($created) ;
            $model->setUpdated($updated) ;
            $model->setLastAudited($lastAudited) ;
            $models[] = $model ;
        }
        return($models) ;
    }

    /**
     * @returns HostGroupMapModel[]
     * @throws  ControllerException
     */
    public function getAll()
    {
        return $this->getSome() ;
    }

    /**
     * @param  HostGroupMapModel $model
     * @return 0 on success
     * @throws ControllerException
     * @see    ControllerBase::add()
     */
    public function add($model)
    {
        if ($model->validateForAdd()) {
            try {
                $query = <<<SQL
INSERT host_group_map
     ( host_group_id
     , host_id
     )
VALUES ( ?, ? )
SQL;
                $hostGroupId      = $model->getHostGroupId() ;
                $hostId           = $model->getHostId() ;
                $stmt             = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'ii',
                    $hostGroupId,
                    $hostId
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
     * @param  HostGroupModel $model
     * @throws ControllerException
     * @return 0 on success
     * @see    ControllerBase::update()
     */
    public function update($model)
    {
        if ($model->validateForUpdate()) {
            try {
                $query = <<<SQL
UPDATE host_group_map
   SET last_audited = ?
 WHERE host_group_id = ?
   AND host_id = ?
SQL;
                $hostGroupId  = $model->getHostGroupId() ;
                $hostId       = $model->getHostId() ;
                $lastAudited  = $model->getLastAudited() ;
                $stmt       = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'sii',
                    $lastAudited,
                    $hostGroupId,
                    $hostId
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
                return 0 ;
            } catch (\Exception $e) {
                throw new ControllerException($e->getMessage()) ;
            }
        } else {
            throw new ControllerException("Invalid data.") ;
        }
    }

    /**
     * @param  HostGroupModel $model
     * @return 0 on success
     * @throws ControllerException
     * @see    ControllerBase::delete()
     */
    public function delete($model)
    {
        if ($model->validateForDelete()) {
            try {
                $query = <<<SQL
DELETE 
  FROM host_group_map
 WHERE host_group_id = ?
   AND host_id = ?
SQL;
                $hostGroupId = $model->getHostGroupId() ;
                $hostId      = $model->getHostId() ;
                $stmt        = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'ii',
                    $hostGroupId,
                    $hostId
                ))) {
                    throw new ControllerException('Binding parameters for prepared statement failed.') ;
                }
                if (!$stmt->execute()) {
                    throw new ControllerException('Failed to execute DELETE statement. (' . $this->_dbh->error . ')') ;
                }
                /**
                 * @SuppressWarnings checkAliases
                 */
                if (!$stmt->close()) {
                    throw new ControllerException('Something broke while trying to close the prepared statement.') ;
                }
                return 0 ;
            } catch (\Exception $e) {
                throw new ControllerException($e->getMessage()) ;
            }
        } else {
            throw new ControllerException("Invalid data.") ;
        }
    }
}

