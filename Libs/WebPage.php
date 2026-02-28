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

use com\kbcmdba\aql\Libs\Config ;
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
        $cacheBuster = time();
        $this->setHead(
            <<<HTML
  <link rel="icon" type="image/x-icon" href="Images/favicon.ico" />
  <link rel="stylesheet" href="css/main.css?v=$cacheBuster" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script type="text/javascript" src="https://code.jquery.com/jquery-latest.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.31.0/js/jquery.tablesorter.js"></script>
  <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
  <script src="js/common.js?v=$cacheBuster"></script>
  <script src="js/klaxon.js?v=$cacheBuster"></script>

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
                   . $this->getNavBar()
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

    /**
     * Generate the sticky navigation bar HTML
     *
     * @return string
     */
    private function getNavBar()
    {
        $version = Config::VERSION ;
        // Build scoreboard items based on enabled DBTypes
        $scoreboardItems = [] ;

        // MySQL/MariaDB is always available
        $scoreboardItems[] = [
            'id' => 'MySQL',
            'label' => 'MySQL',
            'section' => 'nwStatusOverview'
        ] ;

        // Check if Redis monitoring is enabled
        $redisEnabled = false ;
        try {
            $config = new Config() ;
            if ( $config->getRedisEnabled() ) {
                $redisEnabled = true ;
                $scoreboardItems[] = [
                    'id' => 'Redis',
                    'label' => 'Redis',
                    'section' => 'nwRedisOverview'
                ] ;
            }
            // Future: Add other DBTypes here (MongoDB, MS-SQL, etc.)
        } catch ( \Exception $e ) {
            // Config not available, just show MySQL
        }

        // Build Redis submenu items if enabled
        $nwRedisItems = '' ;
        $fullRedisItems = '' ;
        if ( $redisEnabled ) {
            $nwRedisItems = <<<HTML
              <li class="divider"></li>
              <li><a href="index.php#nwRedisOverview">Redis Overview</a></li>
              <li><a href="index.php#nwRedisSlowlog">Redis Slowlog</a></li>
HTML;
            $fullRedisItems = <<<HTML
              <li class="divider"></li>
              <li><a href="index.php#fullRedisOverview">Redis Overview</a></li>
              <li><a href="index.php#fullRedisSlowlog">Redis Slowlog</a></li>
              <li><a href="index.php#fullRedisClients">Redis Clients</a></li>
              <li><a href="index.php#fullRedisCmdStats">Redis Command Stats</a></li>
              <li><a href="index.php#fullRedisMemStats">Redis Memory Stats</a></li>
              <li><a href="index.php#fullRedisStreams">Redis Streams</a></li>
              <li><a href="index.php#fullRedisLatencyHist">Redis Latency History</a></li>
              <li><a href="index.php#fullRedisPending">Redis Stream Pending</a></li>
              <li><a href="index.php#fullRedisDiag">Redis Diagnostics</a></li>
HTML;
        }

        // Generate scoreboard HTML - table format like a baseball scoreboard
        // Format: Label | L9 | L4 | L3 | L2 | L1 | L0 | Total |
        $scoreboardHtml = '' ;
        foreach ( $scoreboardItems as $item ) {
            $scoreboardHtml .= <<<HTML
        <tr id="scoreboard{$item['id']}" class="scoreboard-row" title="{$item['label']}: Loading...">
          <td class="scoreboard-label" onclick="scrollToSection('{$item['section']}')">{$item['label']}</td>
          <td class="scoreboard-level level9" id="scoreboard{$item['id']}L9" onclick="showLevelDrilldown('{$item['id']}', 9)">-</td>
          <td class="scoreboard-level level4" id="scoreboard{$item['id']}L4" onclick="showLevelDrilldown('{$item['id']}', 4)">-</td>
          <td class="scoreboard-level level3" id="scoreboard{$item['id']}L3" onclick="showLevelDrilldown('{$item['id']}', 3)">-</td>
          <td class="scoreboard-level level2" id="scoreboard{$item['id']}L2" onclick="showLevelDrilldown('{$item['id']}', 2)">-</td>
          <td class="scoreboard-level level1" id="scoreboard{$item['id']}L1" onclick="showLevelDrilldown('{$item['id']}', 1)">-</td>
          <td class="scoreboard-level level0" id="scoreboard{$item['id']}L0" onclick="showLevelDrilldown('{$item['id']}', 0)">-</td>
          <td class="scoreboard-blocking" id="scoreboard{$item['id']}Blocking" title="Blocking">-</td>
          <td class="scoreboard-blocked" id="scoreboard{$item['id']}Blocked" title="Blocked">-</td>
          <td class="scoreboard-total" id="scoreboard{$item['id']}Total">-</td>
        </tr>

HTML;
        }

        return <<<HTML
<nav class="navbar navbar-inverse navbar-fixed-top aql-navbar">
  <div class="container-fluid">
    <div class="navbar-header">
      <span class="navbar-brand">AQL $version</span>
    </div>
    <ul class="nav navbar-nav">
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">AQL <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a href="index.php">Active Query Listing</a></li>
          <li class="divider"></li>
          <li><a href="index.php#graphs">Top / Graphs</a></li>
          <li><a href="index.php#dbTypeOverviewHeader">Scoreboards</a></li>
          <li class="divider"></li>
          <li class="dropdown-submenu">
            <a href="#">Noteworthy <span class="caret-right"></span></a>
            <ul class="dropdown-menu">
              <li><a href="index.php#nwSlaveStatus">Slave Status</a></li>
              <li><a href="index.php#nwStatusOverview">Status Overview</a></li>
              <li><a href="index.php#nwProcessListing">Process Listing</a></li>
$nwRedisItems            </ul>
          </li>
          <li class="dropdown-submenu">
            <a href="#">Full <span class="caret-right"></span></a>
            <ul class="dropdown-menu">
              <li><a href="index.php#fullSlaveStatus">Slave Status</a></li>
              <li><a href="index.php#fullStatusOverview">Status Overview</a></li>
              <li><a href="index.php#fullProcessListing">Process Listing</a></li>
$fullRedisItems            </ul>
          </li>
          <li class="divider"></li>
          <li><a href="index.php#renderTimes">AJAX Render Times</a></li>
          <li><a href="index.php#versionSummary">Version Summary</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">History <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a href="blockingHistory.php">Blocking History</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Manage <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a href="manageData.php?data=Hosts">Manage Hosts</a></li>
          <li><a href="manageData.php?data=Groups">Manage Groups</a></li>
          <li><a href="manageData.php?data=MaintenanceWindows">Maintenance Windows</a></li>
          <li class="divider"></li>
          <li><a href="deployDDL.php">Deploy DDL</a></li>
        </ul>
      </li>
      <li class="dropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Tests <span class="caret"></span></a>
        <ul class="dropdown-menu">
          <li><a href="testAQL.php">Test Harness</a></li>
          <li><a href="verifyAQLConfiguration.php">Verify Configuration</a></li>
        </ul>
      </li>
      <li class="dropdown" id="settingsDropdown">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown">Settings <span class="caret"></span></a>
        <ul class="dropdown-menu settings-dropdown-menu">
          <li><a href="#" id="pauseRefreshBtn" onclick="toggleAutoRefresh(); return false;" title="Pause or resume automatic page refresh">⏸ Pause Auto-Refresh</a></li>
          <li id="settingsRefreshItem" class="settings-form-item" style="display:none;" onclick="event.stopPropagation()"></li>
          <li class="divider"></li>
          <li id="settingsDebugItem" class="settings-form-item" style="display:none;" onclick="event.stopPropagation()"></li>
          <li class="divider"></li>
          <li><a href="#" id="themeToggleBtn" onclick="toggleTheme(); return false;" title="Switch to Light Mode"><span id="themeIcon">☀️</span> <span id="themeLabel">Light Mode</span></a></li>
          <li class="divider"></li>
          <li><a href="#" onclick="resetSession(); return false;" title="Clear session data and reload">🔄 Reset Session</a></li>
        </ul>
      </li>
    </ul>
    <!-- Scoreboard: Always-visible status indicators -->
    <table class="scoreboard-table navbar-right" id="scoreboard">
$scoreboardHtml    </table>
  </div>
</nav>

HTML;
    }
}
