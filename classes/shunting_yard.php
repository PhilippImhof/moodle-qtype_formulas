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

namespace qtype_formulas;
use Error;

/**
 * Helper class implementing Dijkstra's shunting yard algorithm.
 *
 * @package    qtype_formulas
 * @copyright  2022 Philipp Imhof
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class shunting_yard {
    /**
     * Return numeric precedence value for an operator
     *
     * @param string $operator operator or function name
     * @return int
     */
    private static function get_precedence(string $operator): int {
        switch ($operator) {
            case '**':
                return 160;
            case '_':
                return 150;
            case '~':
                return 140;
            case '!':
                return 130;
            case '*':
            case '/':
            case '%':
                return 120;
            case '+':
            case '-':
                return 110;
            case '<<':
            case '>>':
                return 100;
            case '<':
            case '>':
            case '>=':
            case '<=':
                return 90;
            case '!=':
            case '==':
                return 80;
            case '&':
                return 70;
            case '^':
                return 60;
            case '|':
                return 50;
            case '&&':
                return 40;
            case '||':
                return 30;
            case '?':
            case ':':
                return 20;
            case '=':
                return 10;
        }
    }

    /**
     * Return whether an operator is left-associative
     *
     * @param string $operator operator name
     * @return bool
     */
    private static function is_left_associative(string $operator): bool {
        switch ($operator) {
            case '=':
            case '**':
                return false;
            case '*':
            case '/':
            case '%':
            case '+':
            case '-':
            case '<<':
            case '>>':
            case '&':
            case '^':
            case '|':
            case '&&':
            case '||':
                return true;
            // The following operators are not associative at all, either
            // because they are unary (like _ or ~ or !) or because associativity
            // does not make sense for them.
            case '_':
            case '~':
            case '!':
            case '<':
            case '>':
            case '>=':
            case '<=':
            case '!=':
            case '==':
                return false;
            // In PHP, the ternary operator is not associative (it was left-associative before 8.0.0)
            // but many languages (e.g. JavaScript) define it to be right-associative which allows
            // for easy chaining, i.e. condition1 ? value1 : condition2 ? value2 : value 3.
            case '?':
                return false;
            case ':':
                return false;
        }
    }

    /**
     * Pop elements from the end of an array as long as the callback function returns true. If desired,
     * popped elements can be appended to another array; otherwise they will be discarded. Also,
     * the last element can be left in the input array or removed from it. If it is removed, it can be
     * appended to the output or discarded. The function modifies the input array and resets its internal
     * pointer.
     *
     * @param array &$input input array, will be modified and have its internal pointer reset
     * @param callable $callback custom comparison function
     * @param array &$out optional output array, will be modified and have its internal pointer reset
     * @param boolean $poplast whether the last element should be popped or not
     * @param boolean $discardlast whether the last element should be discarded when popping it
     * @throws Error if the last element should not be discarded, but is not to be popped
     */
    private static function flush_while(array &$input, callable $callback, ?array &$out = null,
            bool $poplast = false, bool $discardlast = false) {
        if (!$poplast && $discardlast) {
            throw new Error('Cannot move last element to output queue if it is not to be popped.');
        }
        $head = end($input);
        while ($head !== false) {
            // Entries that do not follow the criteria will not be flushed.
            if (!$callback($head)) {
                break;
            }
            $out[] = $head;
            $head = prev($input);
            array_pop($input);
        }
        if ($poplast) {
            $tmp = array_pop($input);
            if (!$discardlast && !is_null($tmp)) {
                $out[] = $tmp;
            }
        }
    }

    /**
     * Flush the operator queue until we reach the %%ternary sentinel pseudo-operator.
     *
     * @param array $opstack operator stack, will be modified
     * @param array $output output queue, will be modified
     * @return void
     */
    private static function flush_ternary_part(array &$opstack, array &$output): void {
        self::flush_while($opstack, function($token) {
            return $token->value !== '%%ternary';
        }, $output);
    }

    /**
     * Flush operators with higher or same precedence from the operator stack.
     *
     * @param array $opstack operator stack, will be modified
     * @param integer $precedence precedence value to compare with
     * @param array $output output queue, will be modified
     * @return void
     */
    private static function flush_higher_precedence(array &$opstack, int $precedence, array &$output): void {
        self::flush_while($opstack, function($operator) use ($precedence) {
            return $precedence <= self::get_precedence($operator->value);
        }, $output);
    }

    /**
     * Flush all remaining operators (but not parens or functions) from the operator stack.
     * FIXME: should probably flush functions as well...
     *
     * @param array $opstack operator stack, will be modified
     * @param array $output output queue, will be modified
     * @return void
     */
    private static function flush_all_operators(array &$opstack, array &$output): void {
        self::flush_while($opstack, function($operator) {
            return $operator->type === token::OPERATOR;
        }, $output);
    }

    /**
     * Flush everything from the operator stack until we reach the desired (opening) parenthesis.
     * The parenthesis itself will be popped and discarded.
     *
     * @param array $opstack operator stack, will be modified
     * @param integer $type type of (opening) parenthesis to look for
     * @param array $output output queue, will be modified
     * @return void
     */
    private static function flush_until_paren(array &$opstack, int $type, array &$output): void {
        self::flush_while($opstack, function($operator) use ($type) {
            return $operator->type !== $type;
        }, $output, true, true);
    }

    /**
     * Flush everything from the operator stack to the output queue.
     *
     * @param array $opstack operator stack, will be modified
     * @param array $output output queue, will be modified
     * @return void
     */
    private static function flush_all(array &$opstack, array &$output): void {
        self::flush_while($opstack, function($operator) {
            return true;
        }, $output, true);
    }

    /**
     * Translate statement from infix into RPN notation via Dijkstra's shunting yard algorithm,
     * because this makes evaluation much easier. The method is declared as static, because it
     * should be possible to use it for arbitrary arithmetic expression with no context at all.
     *
     * @param array $tokens the tokens forming the statement that is to be translated
     * @return array
     */
    public static function infix_to_rpn(array $tokens): array {
        $output = [];
        $opstack = [];
        $counters = ['functionargs' => [], 'arrayelements' => []];
        $separatortype = [];
        $lasttoken = null;
        $lasttype = null;
        $unarypossible = true;

        foreach ($tokens as $token) {
            $type = $token->type;
            $value = $token->value;
            if (!is_null($lasttoken)) {
                $lasttype = $lasttoken->type;
            }
            // Unary + and - are possible after an operator and after an opening parenthesis.
            // Also, in order to correctly interpret arrays and function calls, we must allow
            // unary + and - after an opening bracket and after a comma.
            if (in_array($lasttype, [token::OPENING_PAREN, token::ARG_SEPARATOR, token::OPENING_BRACKET, token::OPERATOR])) {
                $unarypossible = true;
            }
            // Insert inplicit multiplication sign, if
            // - current token is a variable, a function name, a number or an opening parenthesis
            // - last token was a variable, a number or a closing parenthesis
            // For accurate error reporting (e.g. if the multiplication reveals itself as impossible
            // during evaluation), the row and column number of the implicit multiplication token are
            // copied over from the current token which triggered the multiplication.
            if (in_array($type, [token::VARIABLE, token::FUNCTION, token::NUMBER, token::OPENING_PAREN])) {
                if (in_array($lasttype, [token::VARIABLE, token::NUMBER, token::CLOSING_PAREN])) {
                    self::flush_higher_precedence($opstack, self::get_precedence('*'), $output);
                    $opstack[] = new token(token::OPERATOR, '*', $token->row, $token->column);
                }
            }
            switch ($type) {
                // Literals (numbers or strings), constants and variable names go straight to the output queue.
                case token::NUMBER:
                case token::STRING:
                case token::VARIABLE:
                case token::CONSTANT:
                    $output[] = $token;
                    break;
                // If we encounter an argument separator (,) *and* there is a pending function or array,
                // we increase the last argument or element counter. Otherwise, this is a syntax error.
                case token::ARG_SEPARATOR:
                    $mostrecent = end($separatortype);
                    if ($mostrecent === false) {
                        self::die('unexpected token: ,', $token);
                    }
                    self::flush_all_operators($opstack, $output);
                    $index = count($counters[$mostrecent]);
                    ++$counters[$mostrecent][$index - 1];
                    break;
                // Opening parenthesis goes straight to the operator stack.
                case token::OPENING_PAREN:
                    $opstack[] = $token;
                    break;
                // Opening bracket goes straight to the operator stack. At the same time,
                // we must set up a new array element counter.
                // Also, we check whether this bracket means the start of a new array or
                // rather an index to a variable, e.g. a[1].
                case token::OPENING_BRACKET:
                    // By default, let's assume we are building a new array, unless the parser marked
                    // the opening bracket as '[r' signalling we are building a range. In that case,
                    // we use a different sentinel and change the bracket back to its original value.
                    if ($value === '[r') {
                        $sentinel = new token(token::OPERATOR, '%%rangebuild', $token->row, $token->column);
                        $token->value = '[';
                    } else {
                        $sentinel = new token(token::OPERATOR, '%%arraybuild', $token->row, $token->column);
                    }
                    // An index is possible if the last token was a variable, the closing bracket
                    // of an array (or other index, e.g. for a multi-dimensional array) or the closing
                    // parenthesis of a function call which might return an array. We cannot reliably
                    // know whether the parenthesis really comes from a function, but if it does not,
                    // the user will run into an evaluation error later.
                    if (in_array($lasttype, [token::VARIABLE, token::CLOSING_BRACKET, token::CLOSING_PAREN])) {
                        $sentinel->value = '%%arrayindex';
                    }
                    $opstack[] = $sentinel;
                    $opstack[] = $token;
                    $separatortype[] = 'arrayelements';
                    $counters['arrayelements'][] = 0;
                    break;
                // Function name goes straight to the operator stack. At the same time,
                // we must set up a new function argument counter.
                // FIXME: function call will be done in the evaluator.
                case token::FUNCTION:
                    $opstack[] = $token;
                    $separatortype[] = 'functionargs';
                    $counters['functionargs'][] = 0;
                    break;
                // Classic operators are treated according to precedence.
                case token::OPERATOR:
                    // First we check whether the operator could be unary:
                    // An unary + will be silently dropped, an unary - will be changed to negation.
                    if ($unarypossible) {
                        if ($value === '+') {
                            // Jump straight to the next iteration of the loop with no further processing.
                            continue 2;
                        }
                        if ($value === '-') {
                            $value = ($token->value = '_');
                        }
                    }
                    $thisprecedence = self::get_precedence($value);
                    // For the ? part of a ternary operator, we
                    // - flush all operators on the stack with lower precedence (if any)
                    // - send the ? to the output queue
                    // - put a pseudo-token on the operator stack as a sentinel
                    // - break, in order to NOT put the ? on the operator stack.
                    if ($value === '?') {
                        self::flush_higher_precedence($opstack, $thisprecedence, $output);
                        $output[] = $token;
                        $opstack[] = new token(token::OPERATOR, '%%ternary');
                        break;
                    }
                    // For the : part of a ternary operator, we
                    // - flush all operators on the stack until we reach the ?
                    // - do NOT flush the ? but leave it on the operator stack as a sentinel
                    // - send the : to the output queue.
                    // - break, in order to NOT put the : on the operator stack.
                    if ($value === ':') {
                        self::flush_ternary_part($opstack, $output);
                        $output[] = $token;
                        break;
                    }
                    // For left associative operators, all pending operators with higher precedence go
                    // to the output queue first.
                    if (self::is_left_associative($value)) {
                        self::flush_higher_precedence($opstack, $thisprecedence, $output);
                    }
                    // Finally, put the operator on the stack.
                    $opstack[] = $token;
                    break;
                // Closing bracket means we flush pending operators until we get to the
                // matching opening bracket.
                case token::CLOSING_BRACKET:
                    self::flush_until_paren($opstack, token::OPENING_BRACKET, $output);
                    $head = end($opstack);
                    // This should not happen, because the parser already verified that all parens are balanced.
                    if ($head === false) {
                        self::die('syntax error: no matching [ found for this bracket', $token);
                    }
                    $index = count($counters['arrayelements']);
                    // Increase argument counter, unless closing parenthesis directly follows opening parenthesis.
                    if ($index === 0) {
                        self::die(
                            'unknown error: there should be an array element counter in place! please file a bug report.', $token
                        );
                    }
                    if ($lasttype !== token::OPENING_BRACKET) {
                        ++$counters['arrayelements'][$index - 1];
                    }
                    // Pop the most recent array element counter. For %%arrayindex, we just check it's 1.
                    // For %%arraybuild, we don't check it, but add it to the output queue.
                    $numofelements = array_pop($counters['arrayelements']);
                    if ($head->value === '%%arrayindex') {
                        if ($numofelements !== 1) {
                            self::die('syntax error: when accessing array elements, only one index is allowed at a time', $token);
                        }
                    } else if (in_array($head->value, ['%%arraybuild', '%%rangebuild'])) {
                        $output[] = new token(token::NUMBER, $numofelements);
                    } else {
                        self::die('syntax error: unknown parse error', $token);
                    }
                    // Move the pseudo-token %%arraybuild or %%arrayindex to the output queue.
                    $output[] = array_pop($opstack);
                    // Remove last separatortype.
                    array_pop($separatortype);
                    break;
                // Closing parenthesis means we flush all operators until we get to the
                // matching opening parenthesis.
                case token::CLOSING_PAREN:
                    self::flush_until_paren($opstack, token::OPENING_PAREN, $output);
                    $head = end($opstack);
                    // This should not happen, because the parser already verified that all parens are balanced.
                    if ($head === false) {
                        self::die('syntax error: no matching ( found for this parenthesis', $token);
                    }
                    if ($head->type === token::FUNCTION) {
                        // Increase argument counter, unless closing parenthesis directly follows opening parenthesis.
                        $index = count($counters['functionargs']);
                        if ($index === 0) {
                            self::die(
                                'unknown error: there should be a function argument counter in place! please file a bug report.',
                                $token
                            );
                        }
                        if ($lasttype !== token::OPENING_PAREN) {
                            ++$counters['functionargs'][$index - 1];
                        }
                        // Remove last argument counter and put it to output queue, followed by the function name.
                        $output[] = new token(token::NUMBER, array_pop($counters['functionargs']));
                        $output[] = array_pop($opstack);
                        // Remove last separatortype.
                        array_pop($separatortype);
                    }
                    break;
                // The PREFIX token has already served its purpose, we can just ignore it.
                case token::PREFIX:
                    break;
                // At this point, all identifiers should have been classified as functions or variables.
                // No token should have the general IDENTIFIER type anymore.
                case token::IDENTIFIER:
                    self::die("syntax error: did not expect to see an unclassified identifier: $value", $token);
                    break;
                // We should not have to deal with multiple statements, so there should be no end-of-statement
                // marker.
                case token::END_OF_STATEMENT:
                    self::die('unexpected semicolon', $token);
                    break;
                default:
                    self::die("unexpected token: $value", $token);
                    break;
            }
            $lasttoken = $token;
            // We have passed the first token, so generally there can be no unary operator.
            // For the specific cases where unary operators are possible, this will be dealt with
            // at the start of the loop.
            $unarypossible = false;
        }
        self::flush_all($opstack, $output);
        return $output;
    }

    /**
     * Stop processing and indicate the human readable position (row/column) where the error occurred.
     *
     * @param string $message error message
     * @param token $offendingtoken the token that caused the error
     * @return void
     * @throws Exception
     */
    private static function die(string $message, token $offendingtoken): never {
        throw new \Exception($offendingtoken->row . ':' . $offendingtoken->column . ':' . $message);
    }

}
