<?php

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode,
    Behat\Mink\Exception\ExpectationException as ExpectationException,
    Behat\Mink\Exception\DriverException as DriverException,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

class behat_local_eliscore extends behat_base {

    /**
     * @Given a :arg1 record with :arg2 :arg3 exist
     * Note: arg2 json encoded row object for table arg1
     * arg3 = "should" | "should not" ...
     */
    public function aRecordWithExist($arg1, $arg2, $arg3) {
        global $DB;
        if ($DB->record_exists($arg1, (array)json_decode($arg2)) == ($arg3 != "should")) {
            ob_start();
            var_dump($DB->get_records($arg1));
            $tmp = ob_get_contents();
            ob_end_clean();
            error_log("\nTABLE {$arg1} => {$tmp}\n");
            throw new \Exception("Fail: record matching '{$arg2}' in table {$arg1} ".($arg3 == "should" ? 'not ' : '').'found!');
        }
    }

    /**
     * @Given I wait :arg1 minutes and run cron
     */
    public function iWaitMinutesAndRunCron($arg1) {
        sleep((int)(60.0 * $arg1));
        set_config('cronclionly', 0);
        $this->getSession()->visit($this->locate_path('/admin/cron.php'));
    }

    /**
     * @Given I wait until :arg1 and run cron
     * @param string $arg1 string to pass to strtotime()
     */
    public function iWaitUntilAndRunCron($arg1) {
        if (($ts = strtotime($arg1)) === false) {
            throw new \Exception("Could not parse date string: {$arg1}");
        }
        sleep($ts - time());
        set_config('cronclionly', 0);
        $this->getSession()->visit($this->locate_path('/admin/cron.php'));
    }

    /**
     * @Then the following enrolments should exist:
     */
    public function theFollowingEnrolmentsShouldExist(TableNode $table) {
        global $DB;
        $data = $table->getHash();
        foreach ($data as $datarow) {
            if (!is_enrolled(\context_course::instance(
                    $DB->get_field('course', 'id', ['shortname' => $datarow['course']])),
                    $DB->get_field('user', 'id', ['username' => $datarow['user']]))) {
                throw new \Exception("Missing enrolment of {$datarow['user']} in course {$datarow['course']}");
            }
        }
    }

    /**
     * @Given I visit Moodle Course :arg1
     * @param string $arg1 course shortname
     */
    public function iVisitMoodleCourse($arg1) {
        global $DB;
        $crsid = $DB->get_field('course', 'id', ['shortname' => $arg1]);
        if (empty($crsid)) {
            throw new \Exception("Moodle Course with shortname '{$arg1}' not found!");
        }
        $this->getSession()->visit($this->locate_path("/course/view.php?id={$crsid}"));
    }

    /**
     * @Given I update the timemodified for:
     */
    public function iUpdateTheTimemodifiedFor(TableNode $table) {
        global $DB;
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $crsid = $DB->get_field('course', 'id', ['shortname' => $datarow['gradeitem']]);
            if (empty($crsid)) {
                $giid = $DB->get_field('grade_items', 'id', ['itemname' => $datarow['gradeitem']]);
            } else {
                $giid = $DB->get_field('grade_items', 'id', ['itemtype' => 'course', 'courseid' => $crsid]);
            }
            if (empty($giid)) {
                throw new \Exception("No course or grade item found matching {$datarow['gradeitem']}");
            }
            $DB->execute('UPDATE {grade_grades} SET timemodified = '.time().' WHERE itemid = '.$giid);
        }
    }

    /**
     * @Given the following Moodle user profile fields exist:
     */
    public function theFollowingMoodleUserProfileFieldsExist(TableNode $table) {
        global $DB;
        $data = $table->getHash();
        foreach ($data as $datarow) {
            $cat = new \stdClass;
            $cat->name = $datarow['category'];
            if (!($catid = $DB->get_field('user_info_category', 'id', ['name' => $cat->name]))) {
                $catid = $DB->insert_record('user_info_category', $cat);
            }
            $rec = new \stdClass;
            $rec->categoryid = $catid;
            $rec->shortname = $datarow['name'];
            $rec->name = $datarow['name'];
            $rec->datatype = $datarow['type'];
            $rec->defaultdata = $datarow['default'];
            $rec->param1 = str_replace(',', "\n", $datarow['options']);
            $DB->insert_record('user_info_field', $rec);
        }
    }
}
