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
use com\kbcmdba\aql\Libs\Models\HostGroupModel ;

class HostGroupController extends ControllerBase
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
        $sql = "DROP TABLE IF EXISTS host_group" ;
        $this->doDDL($sql) ;
    }

    public function createTable()
    {
        $sql = <<<SQL
CREATE TABLE host_group (
  host_group_id     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
, tag               VARCHAR( 16 ) NOT NULL DEFAULT ''
, short_description VARCHAR( 255 ) NOT NULL DEFAULT ''
, full_descripton   TEXT NULL DEFAULT NULL
, created           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
, updated           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
, UNIQUE ux_tag ( tag )
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

    public function get($id)
    {
        $sql = <<<SQL
SELECT host_group_id
     , tag
     , short_description
     , full_description
     , created
     , updated
  FROM host_group
 WHERE host_group_id = ?
SQL;
        $stmt = $this->_dbh->prepare($sql) ;
        if ((! $stmt) || (! $stmt->bind_param('i', $id))) {
            throw new ControllerException('Failed to prepare SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        if (! $stmt->execute()) {
            throw new ControllerException('Failed to execute SELECT statement. (' . $this->_dbh->error . ')') ;
        }
        $id = $tag = $shortDescription = $fullDescription = $created = $updated = null ;
        if (! $stmt->bind_result(
            $id,
            $tag,
            $shortDescription,
            $fullDescription,
            $created,
            $updated
        )) {
            throw new ControllerException('Failed to bind to result: (' . $this->_dbh->error . ')') ;
        }
        if ($stmt->fetch()) {
            $model = new HostGroupModel() ;
            $model->setId($id) ;
            $model->setShortDescription($shortDescription) ;
            $model->setFullDescription($fullDescription) ;
            $model->setCreated($created) ;
            $model->setUpdated($updated) ;
        } else {
            $model = null ;
        }
        return($model) ;
    }

    /**
     * Get host group records matching the specified filters.
     *
     * @param array $filters Associative array of column => value pairs for WHERE conditions.
     *                       Supported columns: host_group_id, tag.
     *                       Example: ['tag' => 'production']
     * @return HostGroupModel[]
     * @throws ControllerException
     */
    public function getSome(array $filters = [])
    {
        // Whitelist of allowed filter columns to prevent SQL injection
        $allowedColumns = [
            'host_group_id' => 'i',
            'tag' => 's'
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
     , tag
     , short_description
     , full_description
     , created
     , updated
  FROM host_group
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
        $id = $tag = $shortDescription = $fullDescription = $created = $updated = null ;
        if (! $stmt->bind_result(
            $id,
            $tag,
            $shortDescription,
            $fullDescription,
            $created,
            $updated
        )) {
            throw new ControllerException('Failed to bind to result: (' . $this->_dbh->error . ')') ;
        }
        $models = [] ;
        while ($stmt->fetch()) {
            $model = new HostGroupModel() ;
            $model->setId($id) ;
            $model->setTag($tag) ;
            $model->setShortDescription($shortDescription) ;
            $model->setFullDescription($fullDescription) ;
            $model->setCreated($created) ;
            $model->setUpdated($updated) ;
            $models[] = $model ;
        }
        return($models) ;
    }

    public function getAll()
    {
        return $this->getSome() ;
    }

    /**
     * @param HostGroupModel $model
     * @see ControllerBase::add()
     */
    public function add($model)
    {
        if ($model->validateForAdd()) {
            try {
                $query = <<<SQL
INSERT host_group
     ( tag
     , short_description
     , full_description
     )
VALUES ( ?, ?, ? )
SQL;
                $tag              = $model->getTag() ;
                $shortDescription = $model->getShortDescription() ;
                $fullDescription  = $model->getFullDescription() ;
                $stmt             = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'sss',
                    $tag,
                    $shortDescription,
                    $fullDescription
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
     * @param HostGroupModel $model
     * @throws ControllerException
     * @return boolean
     */
    public function update($model)
    {
        if ($model->validateForUpdate()) {
            try {
                $query = <<<SQL
UPDATE host_group
   SET tag = ?
     , short_description = ?
     , full_description = ?
 WHERE id = ?
SQL;
                $id               = $model->getId() ;
                $tag              = $model->getTag() ;
                $shortDescription = $model->getShortDescription() ;
                $fullDescription  = $model->getFullDescription() ;
                $stmt       = $this->_dbh->prepare($query) ;
                if (! $stmt) {
                    throw new ControllerException('Prepared statement failed for ' . $query) ;
                }
                if (! ($stmt->bind_param(
                    'sssi',
                    $tag,
                    $shortDescription,
                    $fullDescription,
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

    /**
     * @param HostGroupModel $model
     */
    public function delete($model)
    {
        $this->deleteModelById("DELETE FROM host_group WHERE id = ?", $model) ;
    }
}
