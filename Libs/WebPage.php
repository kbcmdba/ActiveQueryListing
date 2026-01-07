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

namespace com\kbcmdba\aql\Libs ;

use com\kbcmdba\aql\Libs\Exceptions\WebPageException ;

/**
 * Web Page
 */
class WebPage
{
    private $pageTitle ;
    private $mimeType ;
    private $meta ;
    private $head ;
    private $styles ;
    private $top ;
    private $body ;
    private $bottom ;
    private $data ;

    /**
     * Class constructor
     *
     * @param string Title
     */
    public function __construct($title = '')
    {
        $this->setPageTitle($title) ;
        $this->setMimeType('text/html') ;
        $this->setMeta([ 'Access-Control-Allow-Origin: *'
                       , "Expires: Wed, 21 Feb 2018 00:00:00 GMT"
                       , 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
                       , 'Cache-Control: post-check=0, pre-check=0', false
                       , 'Pragma: no-cache'
                       ]) ;
        $this->setHead(
            <<<HTML
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script type="text/javascript" src="https://code.jquery.com/jquery-latest.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.0/js/jquery.tablesorter.js"></script>
  <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script src="js/common.js"></script>
  <script src="js/klaxon.js"></script>

HTML
        ) ;
        $this->setStyles('') ;
        $this->setTop('') ;
        $this->setBody('') ;
        $this->setBottom('') ;
        $this->setData('') ;
    }

    /**
     * What will the web page look like if it's rendered now?
     *
     * @return string
     */
    public function __toString()
    {
        if ('text/html' === $this->getMimeType()) {
            //@formatter:off
            return ("<!DOCTYPE HTML>\n"
                   . "<html>\n"
                   . "<head>\n"
                   . "<!-- StartOfPage -->\n"
                   . '  <title>' . $this->getPageTitle() . "</title>\n"
                   . $this->getHead()
                   . $this->getStyles()
                   . "</head>\n"
                   . "<body>\n"
                   . $this->getTop()
                   . $this->getBody()
                   . $this->getBottom()
                   . "\n<!-- EndOfPage --></body>\n"
                   . "</html>\n"
                   ) ;
        // @formatter:on
        } else {
            return ($this->getData()) ;
        }
    }

    /**
     *
     * Get the full contents of the page.
     *
     * @return void
     * @SuppressWarnings indentation
     */
    public function displayPage()
    {
        header('Content-type: ' . $this->getMimeType()) ;
        foreach ($this->getMeta() as $meta) {
            header($meta) ;
        }
        echo $this->__toString() ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setPageTitle($pageTitle)
    {
        $this->pageTitle = $pageTitle ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getPageTitle()
    {
        return $this->pageTitle ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setHead($head)
    {
        $this->head = $head ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getHead()
    {
        return $this->head ;
    }

    /**
     *
     * Append array of meta strings with another string
     *
     * @param String $metaString
     * @throws WebPageException
     */
    public function appendMeta($metaString)
    {
        if (is_string($metaString)) {
            array_push($this->meta, $metaString) ;
        } else {
            throw ( new WebPageException("Improper usage of appendMeta") ) ;
        }
    }

    /**
     * Setter
     *
     * @param mixed $meta Array of values to pass to header()
     * @throws WebPageException
     */
    public function setMeta($meta)
    {
        if (is_array($meta)) {
            $this->meta = $meta ;
        } else {
            throw ( new WebPageException("setMeta requires an array") ) ;
        }
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getMeta()
    {
        return $this->meta ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setStyles($styles)
    {
        $this->styles = $styles ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getStyles()
    {
        return $this->styles ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setTop($top)
    {
        $this->top = $top ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getTop()
    {
        return $this->top ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setBody($body)
    {
        $this->body = $body ;
    }

    /**
     * Append the body string
     *
     * @param string
     */
    public function appendBody($body)
    {
        $this->body .= $body ;
    }
    
    /**
     * Getter
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setBottom($bottom)
    {
        $this->bottom = $bottom ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getBottom()
    {
        return $this->bottom ;
    }

    /**
     * Setter
     *
     * @param string
     */
    public function setData($data)
    {
        $this->data = $data ;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getData()
    {
        return $this->data ;
    }
}
