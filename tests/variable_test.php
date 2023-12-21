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
 * qtype_formulas variable class tests
 *
 * @package    qtype_formulas
 * @category   test
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_formulas;

class variable_test extends \advanced_testcase {
    /**
     * @dataProvider provide_variables
     */
    public function test_string_representation($expected, $input): void {
        $s = '' . $input;

        self::assertEquals($expected, $s);
    }

    public function provide_variables(): array {
        return [
            ['1', new variable('x', 1, variable::NUMERIC)],
            ['1', new variable('x', 1.0, variable::NUMERIC)],
            ['1.5', new variable('x', 1.5, variable::NUMERIC)],
            ['foo', new variable('x', 'foo', variable::STRING)],
            ['[1, 2, 3]', new variable('x', [1, 2, 3], variable::LIST)],
            ['[a, 1, 1]', new variable('x', ['a', 1, 1.0], variable::LIST)],
            ['[a, [1, 2], 3]', new variable('x', ['a', [1, 2], 3], variable::LIST)],
            ['{1, 2, 3}', new variable('x', [1, 2, 3], variable::SET)],
            ['{1, [2, 3]}', new variable('x', [1, [2, 3]], variable::SET)],
            ['x', new variable('x', 2, variable::ALGEBRAIC)],
            ['foo', new variable('foo', 'bar', variable::ALGEBRAIC)],
            ['x', new variable('x', [1, [2, 3]], variable::ALGEBRAIC)],
        ];
    }
}
