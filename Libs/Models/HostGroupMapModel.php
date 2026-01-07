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

namespace com\kbcmdba\aql\Libs\Models ;

use com\kbcmdba\aql\Libs\Tools ;

/**
 * Host-Group-Map Model
 */
class HostGroupMapModel extends ModelBase
{
    private $hostGroupId ;
    private $hostId ;
    private $created ;
    private $updated ;
    private $lastAudited ;
 
    /**
     * class constructor
     */
    public function __construct()
    {
        parent::__construct() ;
    }

    /**
     * Validate model for insert
     *
     * @return boolean
     */
    public function validateForAdd()
    {
        return  ((! Tools::isNullOrEmptyString(Tools::param('hostGroupId')))
               && (Tools::isNumeric(Tools::param('hostGroupId')))
               && (! Tools::isNullOrEmptyString(Tools::param('hostId')))
               && (Tools::isNumeric(Tools::param('hostId')))
               && (
                   (
                   Tools::isNullOrEmptyString('lastAudited')
                    || $this->validateDate(Tools::param('lastAudited'))
                    || $this->validateTimestamp(Tools::param('lastAudited'))
                     )
                  )
                ) ;
    }

    /**
     * Validate model for update
     *
     * @return boolean
     */
    public function validateForUpdate()
    {
        return  $this->validateForAdd() ;
    }

    /**
     * Populate model from expected form data.
     */
    public function populateFromForm()
    {
        $this->setHostGroupId(Tools::param('hostGroupId')) ;
        $this->setHostId(Tools::param('hostId')) ;
        $this->setCreated(Tools::param('created')) ;
        $this->setUpdated(Tools::param('updated')) ;
        $this->setLastAudited(Tools::param('lastAudited')) ;
    }

    /**
     * @return integer
     */
    public function getHostGroupId()
    {
        return $this->hostGroupId ;
    }

    /**
     * @param integer $hostGroupId
     */
    public function setHostGroupId($hostGroupId)
    {
        $this->hostGroupId = $hostGroupId ;
    }

    /**
     * @return integer
     */
    public function getHostId()
    {
        return $this->hostId ;
    }

    /**
     * @param integer $hostId
     */
    public function setHostId($hostId)
    {
        $this->hostId = $hostId ;
    }

    /**
     * @return string
     */
    public function getCreated()
    {
        return $this->created ;
    }

    /**
     * @param string $created
     */
    public function setCreated($created)
    {
        $this->created = $created ;
    }

    /**
     * @return string
     */
    public function getUpdated()
    {
        return $this->updated ;
    }

    /**
     * @param string $updated
     */
    public function setUpdated($updated)
    {
        $this->updated = $updated ;
    }

    /**
     * @return string
     */
    public function getLastAudited()
    {
        return $this->lastAudited ;
    }

    /**
     * @param string $lastAudited
     */
    public function setLastAudited($lastAudited)
    {
        $this->lastAudited = $lastAudited ;
    }
}
