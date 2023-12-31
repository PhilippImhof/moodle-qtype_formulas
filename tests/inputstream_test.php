<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * qtype_formulas unit tests for the inputstream class
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;
use Exception;

class inputstream_test extends \advanced_testcase {

    /**
     * Test read() and peek() function of the inputstream class.
     */
    public function test_read_peek(): void {
        $input = 'abcdefg0123456';
        $reader = new input_stream($input);

        $len = strlen($input);
        for ($i = 0; $i < $len; $i++) {
            // We check that peek()ing does not advance the index, so read()ing will return
            // the same char again.
            self::assertEquals($input[$i], $reader->peek());
            self::assertEquals($input[$i], $reader->read());
        }
        // The string is finished, we should get EOF.
        self::assertEquals($reader::EOF, $reader->peek());
        self::assertEquals($reader::EOF, $reader->read());

        // No error should be raised if we continue reading. The stream just returns EOF.
        self::assertEquals($reader::EOF, $reader->read());
    }

    /**
     * Test that the inputstream class indicates the correct position when die()ing.
     */
    public function test_die(): void {
        $input = "abcde\nfghij\nklmno";
        $reader = new input_stream($input);

        $e = null;
        try {
            $reader->die('foo');

        } catch (Exception $e) {
            self::assertEquals('1:0:foo', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        for ($i = 0; $i < 5; $i++) {
            $reader->read();
        }
        try {
            $reader->die('bar');
        } catch (Exception $e) {
            self::assertEquals('1:5:bar', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        $reader->read();
        try {
            $reader->die('error');
        } catch (Exception $e) {
            self::assertEquals('2:0:error', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        $reader->read();
        try {
            $reader->die('other error');
        } catch (Exception $e) {
            self::assertEquals('2:1:other error', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        for ($i = 0; $i < 5; $i++) {
            $reader->read();
        }
        try {
            $reader->die('foo');
        } catch (Exception $e) {
            self::assertEquals('3:0:foo', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        $reader->read();
        try {
            $reader->die('x');
        } catch (Exception $e) {
            self::assertEquals('3:1:x', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        for ($i = 0; $i < 4; $i++) {
            $reader->read();
        }
        try {
            $reader->die('lastchar');
        } catch (Exception $e) {
            self::assertEquals('3:5:lastchar', $e->getMessage());
        }
        self::assertNotNull($e);

        $e = null;
        $reader->read();
        try {
            $reader->die('shouldnotmovefarther');
        } catch (Exception $e) {
            self::assertEquals('3:5:shouldnotmovefarther', $e->getMessage());
        }
        self::assertNotNull($e);
    }
}
