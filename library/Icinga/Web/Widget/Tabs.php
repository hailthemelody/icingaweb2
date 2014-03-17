<?php
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Web\Widget;

use Icinga\Exception\ProgrammingError;
use Icinga\Web\Widget\Tabextension\Tabextension;
use Icinga\Application\Icinga;
use Countable;

/**
 * Navigation tab widget
 */
class Tabs extends AbstractWidget implements Countable
{
    /**
     * Template used for the base tabs
     *
     * @var string
     */
    private $baseTpl = <<< 'EOT'
<ul class="tabs">
  {TABS}
  {DROPDOWN}
</ul>
EOT;

    /**
     * Template used for the tabs dropdown
     *
     * @var string
     */
    private $dropdownTpl = <<< 'EOT'
<li class="dropdown">
  <a href="#" class="dropdown-toggle">⌄</a>
  <ul class="dropdown-menu">
    {TABS}
  </ul>
</li>
EOT;

    /**
     * This is where single tabs added to this container will be stored
     *
     * @var array
     */
    private $tabs = array();

    /**
     * The name of the currently activated tab
     *
     * @var string
     */
    private $active;

    /**
     * Array of tab names which should be displayed in a dropdown
     *
     * @var array
     */
    private $dropdownTabs = array();

    /**
     * Activate the tab with the given name
     *
     * If another tab is currently active it will be deactivated
     *
     * @param   string $name Name of the tab going to be activated
     *
     * @return  self
     *
     * @throws  ProgrammingError When the given tab name doesn't exist
     *
     */
    public function activate($name)
    {
        if ($this->has($name)) {
            if ($this->active !== null) {
                $this->tabs[$this->active]->setActive(false);
            }
            $this->get($name)->setActive();
            $this->active = $name;
        }
        return $this;
    }

    /**
     * Return the name of the active tab
     *
     * @return string
     */
    public function getActiveName()
    {
        return $this->active;
    }

    /**
     * Set the CSS class name(s) for the &lt;ul&gt; element
     *
     * @param   string $name CSS class name(s)
     *
     * @return  self
     */
    public function setClass($name)
    {
        $this->tab_class = $name;
        return $this;
    }

    /**
     * Whether the given tab name exists
     *
     * @param   string $name Tab name
     *
     * @return  bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->tabs);
    }

    /**
     * Whether the given tab name exists
     *
     * @param   string $name The tab you're interested in
     *
     * @return  Tab
     *
     * @throws  ProgrammingError When the given tab name doesn't exist
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            return null;
        }
        return $this->tabs[$name];
    }

    /**
     * Add a new tab
     *
     * A unique tab name is required, the Tab itself can either be an array
     * with tab properties or an instance of an existing Tab
     *
     * @param   string      $name   The new tab name
     * @param   array|Tab   $tab    The tab itself of it's properties
     *
     * @return  self
     *
     * @throws  ProgrammingError When the tab name already exists
     */
    public function add($name, $tab)
    {
        if ($this->has($name)) {
            throw new ProgrammingError(
                sprintf(
                    'Cannot add a tab named "%s" twice"',
                    $name
                )
            );
        }
        return $this->set($name, $tab);
    }

    /**
     * Set a tab
     *
     * A unique tab name is required, will be replaced in case it already
     * exists. The tab can either be an array with tab properties or an instance
     * of an existing Tab
     *
     * @param   string      $name   The new tab name
     * @param   array|Tab   $tab    The tab itself of it's properties
     *
     * @return  self
     */
    public function set($name, $tab)
    {
        if ($tab instanceof Tab) {
            $this->tabs[$name] = $tab;
        } else {
            $this->tabs[$name] = new Tab($tab + array('name' => $name));
        }
        return $this;
    }

    /**
     * Add a tab to the dropdown on the right side of the tab-bar.
     *
     * @param $name
     * @param $tab
     */
    public function addAsDropdown($name, $tab)
    {
        $this->set($name, $tab);
        $this->dropdownTabs[] = $name;
        $this->dropdownTabs = array_unique($this->dropdownTabs);
    }

    /**
     * Render the dropdown area with it's tabs and return the resulting HTML
     *
     * @return  mixed|string
     */
    private function renderDropdownTabs()
    {
        if (empty($this->dropdownTabs)) {
            return '';
        }
        $tabs = '';
        foreach ($this->dropdownTabs as $tabname) {
            $tab = $this->get($tabname);
            if ($tab === null) {
                continue;
            }
            $tabs .= $tab;
        }
        return str_replace('{TABS}', $tabs, $this->dropdownTpl);
    }

    /**
     * Render all tabs, except the ones in dropdown area and return the resulting HTML
     *
     * @return  string
     */
    private function renderTabs()
    {
        $tabs = '';
        foreach ($this->tabs as $name => $tab) {
            // ignore tabs added to dropdown
            if (in_array($name, $this->dropdownTabs)) {
                continue;
            }
            $tabs .= $tab;
        }
        return $tabs;
    }

    /**
     * Render to HTML
     *
     * @see Widget::render
     */
    public function render()
    {
        if (empty($this->tabs)) {
            return '';
        }

        $html = $this->baseTpl;
        $html = str_replace('{TABS}', $this->renderTabs(), $html);
        $html = str_replace('{DROPDOWN}', $this->renderDropdownTabs(), $html);
        return $html;
    }

    public function __toString()
    {
        try {
          $html = $this->render(Icinga::app()->getViewRenderer()->view);
        } catch (Exception $e) {
          return htmlspecialchars($e->getMessage());
        }
        return $html;
    }

    /**
     * Return the number of tabs
     *
     * @return  int
     *
     * @see     Countable
     */
    public function count()
    {
        return count($this->tabs);
    }

    /**
     * Return all tabs contained in this tab panel
     *
     * @return array
     */
    public function getTabs()
    {
        return $this->tabs;
    }

    /**
     * Apply a Tabextension on this tabs object
     *
     * @param   Tabextension $tabextension
     *
     * @return  self
     */
    public function extend(Tabextension $tabextension)
    {
        $tabextension->apply($this);
        return $this;
    }
}
