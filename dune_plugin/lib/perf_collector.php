<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

class Perf_Collector
{
    const TIME = 'Time';
    const USER_MODE_TIME = 'UserModeTime';
    const MEMORY_LIMIT = 'MemoryLimit';
    const MEMORY_USAGE_KB = 'MemoryUsageKb';
    const MEMORY_USAGE_MB = 'MemoryUsageMb';
    const PEAK_MEMORY_USAGE_KB = 'PeakMemoryUsageKb';
    const PEAK_MEMORY_USAGE_MB = 'PeakMemoryUsageMb';

    const STAT_TIME = 'time';
    const STAT_UTIME = 'utime';
    const STAT_MEMORY = 'memory';
    const STAT_PMEMORY = 'peak_memory';
    const STAT_USAGE = 'usage';

    /**
     * @var array
     */
    private $labels = array();

    /**
     * Clear all labels
     */
    public function reset($firstLabel = '')
    {
        $this->labels = array();
        if (!empty($firstLabel)) {
            $this->setLabel($firstLabel);
        }
    }

    /**
     * Add a new label for measure
     * @param string $label Name for the label
     */
    public function setLabel($label)
    {
        if (array_key_exists($label, $this->labels)) {
            hd_debug_print("Tried to add a already exisiting label!");
            return;
        }

        $this->labels[$label] = array(
            self::STAT_TIME => microtime(true),
            self::STAT_MEMORY => memory_get_usage(),
            self::STAT_PMEMORY => memory_get_peak_usage(),
            self::STAT_USAGE => getrusage()
        );
    }

    /**
     * Remove label from measure
     * @param string $label Name for the label
     */
    public function unsetLabel($label)
    {
        unset($this->labels[$label]);
    }

    /**
     * Get the labels array
     * @return array
     */
    public function getLabels()
    {
        return $this->labels;
    }

    /**
     * Obtain a memory limit set in php.ini
     */
    public static function getMemoryLimit()
    {
        return ini_get('memory_limit');
    }

    /**
     * Obtain a report array with the measures between two labels
     * @param string|bool $startLabel Start label
     * @param string $endLabel End label
     * @return array
     */
    public function getFullReport($startLabel = false, $endLabel = false)
    {
        if ($startLabel === false) {
            reset($this->labels);
            $startLabel = (string)key($this->labels);
        }

        if ($endLabel === false) {
            $endLabel = (string)key(array_slice($this->labels, -1, 1, true));
        }

        if (!isset($this->labels[$startLabel])) {
            hd_print("Wrong start label: $startLabel");
        }

        if (!isset($this->labels[$endLabel])) {
            hd_print("Wrong end label: $endLabel");
        }

        $time = $this->labels[$endLabel][self::STAT_TIME] - $this->labels[$startLabel][self::STAT_TIME];
        $memory = $this->labels[$endLabel][self::STAT_MEMORY] - $this->labels[$startLabel][self::STAT_MEMORY];
        $usage = $this->getUsageDifference($startLabel, $endLabel);
        $memoryPeak = memory_get_peak_usage();

        // Prepare report.
        $report[self::TIME] = $time;
        $report[self::USER_MODE_TIME] = $usage[self::STAT_UTIME];
        $report[self::MEMORY_LIMIT] = self::getMemoryLimit();
        $report[self::MEMORY_USAGE_KB] = round($memory / 1024);
        $report[self::MEMORY_USAGE_MB] = round($memory / 1024 / 1024, 2);
        $report[self::PEAK_MEMORY_USAGE_KB] = round($memoryPeak / 1024);
        $report[self::PEAK_MEMORY_USAGE_MB] = round($memoryPeak / 1024 / 1024, 2);

        return $report;
    }

    /**
     * Obtain a report item with the measures between two labels
     * if no start label set - used first label in array
     * if no end label set - used last label in array
     * @param string|false $startLabel Start label
     * @param string|false $endLabel End label
     * @return mixed
     */
    public function getReportItem($item, $startLabel = false, $endLabel = false)
    {
        if (empty($this->labels)) {
            return array();
        }

        $report = $this->getFullReport($startLabel, $endLabel);

        return $report[$item];
    }

    /**
     * Obtain a report array with the measures between start label and values at current call
     * @param string|false $startLabel Start label
     * @return mixed
     */
    public function getReportItemCurrent($item, $startLabel = false)
    {
        if (empty($this->labels)) {
            return array();
        }

        $this->setLabel('temporaryLabel');

        $report = $this->getFullReport($startLabel, 'temporaryLabel');

        $this->unsetLabel('temporaryLabel');

        return $report[$item];
    }

    ////////////////////////////////////////////////////////////
    // private functions

    /**
     * Get the usage difference between two labels
     * @param string $startLabel Start label to measure usage against
     * @param string $endLabel End label to compare usage against start label
     * @return array Usage array with times compared
     */
    private function getUsageDifference($startLabel, $endLabel)
    {
        $arr_start = $this->labels[$startLabel][self::STAT_USAGE];
        $arr_end = $this->labels[$endLabel][self::STAT_USAGE];

        // Add user mode time.
        $arr_start[self::STAT_UTIME] = ($arr_start['ru_utime.tv_usec'] / 1000000) + $arr_start['ru_utime.tv_sec'];
        $arr_end[self::STAT_UTIME] = ($arr_end['ru_utime.tv_usec'] / 1000000) + $arr_end['ru_utime.tv_sec'];

        // Unset time splits.
        unset(
            $arr_start['ru_utime.tv_usec'],
            $arr_start['ru_utime.tv_sec'],
            $arr_end['ru_utime.tv_usec'],
            $arr_end['ru_utime.tv_sec']
        );

        foreach ($arr_start as $key => $value) {
            $arrDiff[$key] = $arr_end[$key] - $value;
        }

        return $arrDiff;
    }
}
