<?php

declare(strict_types=1);

namespace App\Chat\Condition;

use App\Chat\Contracts\ArrayMorphs;
use App\Chat\Debug;
use Illuminate\Support\Facades\Log;

/**
 * 'equals', 'text' => $input === $value,
 * 'starts_with' => mb_strpos($input, $value) === 0,
 * 'ends_with' => mb_strpos($input, $value) === mb_strlen($input) - mb_strlen($value),
 * 'filled', 'not_empty', 'exists' => $input !== null,
 * 'string' => is_string($input),
 * 'int' => is_int($input),
 * 'bool' => is_bool($input) || $input == 1 || $input == 0,
 * 'true' => $input == true,
 * 'false' => $input == false,
 * 'in_array' => in_array($input, explode(',', $value)),
 */

/**
 * @method Builder equals(string $field, mixed $value)
 * @method Builder text(string $field, string $value)
 * @method Builder startsWith(string $field, string $value)
 * @method Builder startsWithAny(string $field, array $value)
 * @method Builder endsWith(string $field, string $value)
 * @method Builder filled(string $field)
 * @method Builder notEmpty(string $field)
 * @method Builder exists(string $field)
 * @method Builder isString(string $field)
 * @method Builder isInt(string $field)
 * @method Builder isBool(string $field)
 * @method Builder isTrue(string $field)
 * @method Builder isFalse(string $field)
 * @method Builder inArray(string $field)
 * @method static Builder equals(string $field, mixed $value)
 * @method static Builder notEquals(string $field, mixed $value)
 * @method static Builder text(string $field, string $value)
 * @method static Builder startsWith(string $field, string $value)
 * @method static Builder endsWith(string $field, string $value)
 * @method static Builder filled(string $field)
 * @method static Builder notEmpty(string $field)
 * @method static Builder exists(string $field)
 * @method static Builder isString(string $field)
 * @method static Builder isInt(string $field)
 * @method static Builder isBool(string $field)
 * @method static Builder isTrue(string $field)
 * @method static Builder isFalse(string $field)
 * @method static Builder inArray(string $field, array $value)
 * @method static Builder in(string $field, array $value)
*/
class Builder extends ArrayMorphedEvaluable
{
    protected array $expressions = [];
    protected array $operators = [];
    protected const OPERATORS = [
        'equals',
        'notEquals',
        'text',
        'startsWith',
        'startsWithAny',
        'endsWith',
        'filled',
        'notEmpty',
        'exists',
        'isString',
        'isInt',
        'isBool',
        'isTrue',
        'isFalse',
        'inArray',
        'in',

        'or'
    ];

    public const BOOLOP_AND = 'and';
    public const BOOLOP_OR = 'or';
    protected bool $useOr = false;

    public function __construct(array $expressions = [], array $operators = [])
    {
        $this->expressions = $expressions;
        $this->operators = $operators;
    }

    public static function fromArray(array $data): self
    {
        if (count($data) === 4) {
            array_shift($data);
        }
        if (count($data) !== 2) {
            throw new \InvalidArgumentException('Invalid data');
        }
        $expressions = array_map(fn ($expression) => Expression::fromArray($expression), $data[0]);
        return new self($expressions, $data[1]);
    }

    public function __call(string $operator, array $arguments): self
    {
        if (!in_array($operator, self::OPERATORS)) {
            throw new \BadMethodCallException("Method {$operator} does not exist");
        }
        $this->expressions[] = new Expression($arguments[0], $operator, $arguments[1] ?? null);

        $boolOp = $this->useOr ? self::BOOLOP_OR : self::BOOLOP_AND;
        $this->operators[] = $boolOp;
        if ($this->useOr) {
            $this->useOr = false;
        }

        return $this;
    }

    public static function __callStatic(string $operator, array $arguments): self
    {
        return (new self)->__call($operator, $arguments);
    }

    public function or(?callable $callback = null): self
    {
        return $this->boolOp(self::BOOLOP_OR, $callback);
    }

    public function and(?callable $callback = null): self
    {
        return $this->boolOp(self::BOOLOP_AND, $callback);
    }

    protected function boolOp(string $operator, ?callable $callback = null): self
    {
        if ($callback !== null) {
            $builder = new Builder();
            $callback($builder);
            $this->__call($operator, [$builder]);
        } else {
            $this->useOr = $operator === self::BOOLOP_OR;
        }
        return $this;
    }

    public function evaluate(array $input): bool
    {
        $results = [];
        $operators = $this->operators;
        // remove first bool operator
        array_shift($operators);
        foreach ($this->expressions as $expression) {
            $results[] = $expression->evaluate($input);
        }
        if (count($results) === 1) {
            return array_shift($results);
        }
        $lvalue = array_shift($results);
        while (!empty($results)) {
            $boolOp = array_shift($operators);
            $rvalue = array_shift($results);
            if ($boolOp === self::BOOLOP_OR) {
                $lvalue = $lvalue || $rvalue;
            } else {
                $lvalue = $lvalue && $rvalue;
            }
        }
        return $lvalue;
    }

    public function toArray(): array
    {
        $expressions = array_map(fn ($expression) => $expression->toArray(), $this->expressions);
        return [
            self::class,
            $expressions,
            $this->operators
        ];
    }

    public function __toString(): string
    {
        $expressions = array_map(fn ($expression) => "($expression)", $this->expressions);
        $results = [];
        $operators = $this->operators;
        foreach ($expressions as $expression) {
            $operator = array_shift($operators);
                $results[] = $operator;
                $results[] = "$expression";
        }
        array_shift($results);
        return implode(' ', $results);
    }
}
