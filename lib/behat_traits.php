<?php
/**
 * ELIS(TM): Enterprise Learning Intelligence Suite
 * Copyright (C) 2008-2017 Remote Learner.net Inc http://www.remote-learner.net
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    local_eliscore
 * @author     Remote-Learner.net Inc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2015 Onwards Remote-Learner.net Inc (http://www.remote-learner.net)
 *
 */

/**
 * Common behat methods to be included with other components.
 */
trait local_eliscore_behat_trait {

    /**
     * Check checkbox
     * @param string $id base element name.
     */
    public function checkCheckbox($id) {
        $page = $this->getSession()->getPage();
        if (($chkbox = $page->find('xpath', "//input[@id='{$id}']"))) {
            $chkbox->check();
            $chkbox->setValue(true);
        } else {
            throw new \Exception("The expected '{$fullid}' checkbox was not found!");
        }
    }

    /**
     * Click radio
     * @param string $id base element name.
     ^ @param string $val the value to set/click.
     */
    public function clickRadio($id, $val) {
        $page = $this->getSession()->getPage();
        $fullid = "id_{$id}_{$val}";
        $radio = $page->find('xpath', "//input[@id='{$fullid}']");
        if (!empty($radio)) {
            $radio->click();
        } else {
            throw new \Exception("The expected '{$fullid}' radio button was not found!");
        }
    }

    /**
     * Select option.
     * @param string $id base element name.
     * @param string $val the option to select.
     * @param bool $ignoremissing if true no exception for missing element.
     * @return bool true if element found (default), false if not found and $ignoremissing true;
     *         Otherwise throws exception if element not found.
     */
    public function selectOption($id, $val, $ignoremissing = false) {
        $page = $this->getSession()->getPage();
        $sel = $page->find('xpath', "//select[@id='{$id}']");
        if (!empty($sel)) {
            $sel->selectOption($val);
        } else if (!$ignoremissing) {
            throw new \Exception("The expected '{$id}' select element was not found!");
        } else {
            return false;
        }
        return true;
    }

    /**
     * Save screenshot.
     * @param string $fname the path and filename to sabe the screenshot to.
     */
    public function rlSaveScreenshot($fname) {
        $idata = $this->getSession()->getDriver()->getScreenshot();
        file_put_contents($fname, $idata);
    }
}
