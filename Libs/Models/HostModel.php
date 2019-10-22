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
 * Host Model
 */
class HostModel extends ModelBase
{
    private $id ;
    private $hostName ;
    private $description ;
    private $shouldMonitor ;
    private $shouldBackup ;
    private $revenueImpacting ;
    private $decommissioned ;
    private $alertCritSecs ;
    private $alertWarnSecs ;
    private $alertInfoSecs ;
    private $alertLowSecs ;
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
        return  ((Tools::isNullOrEmptyString(Tools::param('id')))
               && (! Tools::isNullOrEmptyString(Tools::param('hostName')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertCritSecs')))
               && (Tools::isNumeric(Tools::param('alertCritSecs')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertWarnSecs')))
               && (Tools::isNumeric(Tools::param('alertWarnSecs')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertInfoSecs')))
               && (Tools::isNumeric(Tools::param('alertInfoSecs')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertLowSecs')))
               && (Tools::isNumeric(Tools::param('alertLowSecs')))
                ) ;
    }

    /**
     * Validate model for update
     *
     * @return boolean
     */
    public function validateForUpdate()
    {
        return  ((! Tools::isNullOrEmptyString(Tools::param('id')))
               && (Tools::isNumeric(Tools::param('id')))
               && (! Tools::isNullOrEmptyString(Tools::param('hostName')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertCritSecs')))
               && (Tools::isNumeric(Tools::param('alertCritSecs')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertWarnSecs')))
               && (Tools::isNumeric(Tools::param('alertWarnSecs')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertInfoSecs')))
               && (Tools::isNumeric(Tools::param('alertInfoSecs')))
               && (! Tools::isNullOrEmptyString(Tools::param('alertLowSecs')))
               && (Tools::isNumeric(Tools::param('alertLowSecs')))
                ) ;
    }

    
    /**
     * Based on newvalue, set targetValue by reference.
     * Assume result should be true if $newValue is invalid.
     *
     * @param boolean &$targetValue
     * @param boolean $newValue
     */
    private function setBooleanAssumeTrue(&$targetValue, $newValue)
    {
        if ((! isset($newValue))
           || (false === $newValue)
           || (0 === $newValue)
           || ('0' === $newValue)
            ) {
            $targetValue = 0 ;
        } else {
            $targetValue = 1 ;
        }
    }

    /**
     * Populate model from expected form data.
     */
    public function populateFromForm()
    {
        $this->setHostId(Tools::param('id')) ;
        $this->setHostName(Tools::param('hostName')) ;
        $this->setDescription(Tools::param('description')) ;
        $this->setShouldMonitor(Tools::param('shouldMonitor')) ;
        $this->setShouldBackup(Tools::param('shouldBackup')) ;
        $this->setRevenueImpacting(Tools::param('revenueImpacting')) ;
        $this->setDecommissioned(Tools::param('decommissioned')) ;
        $this->setAlertCritSecs(Tools::param('alertCritSecs')) ;
        $this->setAlertWarnSecs(Tools::param('alertWarnSecs')) ;
        $this->setAlertInfoSecs(Tools::param('alertInfoSecs')) ;
        $this->setAlertLowSecs(Tools::param('alertLowSecs')) ;
        $this->setCreated(Tools::param('created')) ;
        $this->setUpdated(Tools::param('updated')) ;
        $this->setLastAudited(Tools::param('lastAudited')) ;
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id ;
    }

    /**
     * @param integer $id
     */
    public function setId($id)
    {
        $this->id = $id ;
    }

    /**
     * @return string
     */
    public function getHostName()
    {
        return $this->hostName ;
    }

    /**
     * @param string $hostName
     */
    public function setHostName($hostName)
    {
        $this->hostName = $hostName ;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description ;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description ;
    }

    /**
     * @return boolean
     */
    public function getShouldMonitor()
    {
        return $this->shouldMonitor ;
    }

    /**
     * @param string $shouldMonitor
     */
    public function setShouldMonitor($shouldMonitor)
    {
        $this->setBooleanAssumeTrue($this->shouldMonitor, $shouldMonitor) ;
    }

    /**
     * @return boolean
     */
    public function getShouldBackup()
    {
        return $this->shouldBackup ;
    }
    
    /**
     * @param string $shouldBackup
     */
    public function setShouldBackup($shouldBackup)
    {
        $this->setBooleanAssumeTrue($this->shouldBackup, $shouldBackup) ;
    }
    
    /**
     * @return boolean
     */
    public function getRevenueImpacting()
    {
        return $this->revenueImpacting ;
    }
    
    /**
     * @param string $revenueImpacting
     */
    public function setRevenueImpacting($revenueImpacting)
    {
        $this->setBooleanAssumeTrue($this->revenueImpacting, $revenueImpacting) ;
    }
    
    /**
     * @return boolean
     */
    public function getDecommissioned()
    {
        return $this->decommissioned ;
    }
    
    /**
     * @param string $decommissioned
     */
    public function setDecommissioned($decommissioned)
    {
        $this->setBooleanAssumeTrue($this->decommissioned, $decommissioned) ;
    }
    
    /**
     * @return int
     */
    public function getAlertCritSecs()
    {
        return $this->alertCritSecs ;
    }
    
    /**
     * @param int $alertCritSecs
     */
    public function setAlertCritSecs($alertCritSecs)
    {
        $this->alertCritSecs = $alertCritSecs ;
    }
    
    /**
     * @return int
     */
    public function getAlertWarnSecs()
    {
        return $this->alertWarnSecs ;
    }

    /**
     * @param int $alertWarnSecs
     */
    public function setAlertWarnSecs($alertWarnSecs)
    {
        $this->alertWarnSecs = $alertWarnSecs ;
    }

    /**
     * @return int
     */
    public function getAlertInfoSecs()
    {
        return $this->alertInfoSecs ;
    }

    /**
     * @param int $alertInfoSecs
     */
    public function setAlertInfoSecs($alertInfoSecs)
    {
        $this->alertInfoSecs = $alertInfoSecs ;
    }

    /**
     * @return int
     */
    public function getAlertLowSecs()
    {
        return $this->alertLowSecs ;
    }

    /**
     * @param int $alertLowSecs
     */
    public function setAlertLowSecs($alertLowSecs)
    {
        $this->alertLowSecs = $alertLowSecs ;
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
