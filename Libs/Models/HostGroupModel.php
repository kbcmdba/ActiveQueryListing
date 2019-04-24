<?php

/**
 * ActiveQueryListing
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

namespace com\kbcmdba\ActiveQueryListing ;

/**
 * Host-Group Model
 */
class HostGroupModel extends ModelBase
{
    private $id ;
    private $tag ;
    private $shortDescription ;
    private $fullDescription ;
    private $created ;
    private $updated ;
 
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
               && (! Tools::isNullOrEmptyString(Tools::param('tag')))
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
               && (! Tools::isNullOrEmptyString(Tools::param('tag')))
                ) ;
    }

    /**
     * Populate model from expected form data.
     */
    public function populateFromForm()
    {
        $this->setId(Tools::param('id')) ;
        $this->setTag(Tools::param('tag')) ;
        $this->setShortDescription(Tools::param('shortDescription')) ;
        $this->setFullDescription(Tools::param('fullDescription')) ;
        $this->setCreated(Tools::param('created')) ;
        $this->setUpdated(Tools::param('updated')) ;
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
    public function getTag()
    {
        return $this->tag ;
    }

    /**
     * @param string $tag
     */
    public function setTag($tag)
    {
        $this->tag = $tag ;
    }

    /**
     * @return string
     */
    public function getShortDescription()
    {
        return $this->shortDescription ;
    }

    /**
     * @param string $fullDescription
     */
    public function setFullDescription($fullDescription)
    {
        $this->fullDescription = $fullDescription ;
    }

    /**
     * @return string
     */
    public function getFullDescription()
    {
        return $this->fullDescription ;
    }

    /**
     * @param string $fullDescription
     */
    public function setFullDescription($fullDescription)
    {
        $this->fullDescription = $fullDescription ;
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
}
