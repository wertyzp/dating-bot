<?php

declare(strict_types=1);

namespace App\Chat\Condition;
use App\Chat\Debug;

/**
 * @method static Expression equals(string $field, mixed $value)
 * @method static Expression notEquals(string $field, mixed $value)
 * @method static Expression text(string $field, string $value)
 * @method static Expression startsWith(string $field, string $value)
 * @method static Expression startsWithAny(string $field, array $value)
 * @method static Expression endsWith(string $field, string $value)
 * @method static Expression filled(string $field)
 * @method static Expression notEmpty(string $field)
 * @method static Expression exists(string $field)
 * @method static Expression isString(string $field)
 * @method static Expression isInt(string $field)
 * @method static Expression isBool(string $field)
 * @method static Expression isTrue(string $field)
 * @method static Expression isFalse(string $field)
 * @method static Expression inArray(string $field, array $value)
 * @method static Expression in(string $field, array $value)
 * @method static Expression has(string $field, mixed $value)
*/

class Expression extends ArrayMorphedEvaluable
{
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
        'has'

    ];

    public function __construct(protected string $field, protected string $operator, protected mixed $value)
    {
    }

    public function toArray(): array
    {
        return [
            self::class,
            $this->field,
            $this->operator,
            $this->value,
        ];
    }

    public function evaluate(array $input): bool
    {
        $key = $this->field;
        $rule = $this->operator;
        $value = $this->value;
        $input = $this->getInput($key, $input);
        return match ($rule) {
            'equals', 'text' => $input === $value,
            'notEquals' => $input !== $value,
            'startsWith' => $input && mb_strpos($input, $value) === 0,
            'startsWithAny' => is_string($input) && $this->_startsWithAny($input, $value),
            'endsWith' => is_string($input) && mb_strpos($input, $value) === mb_strlen($input) - mb_strlen($value),
            'isFilled', 'notEmpty', 'exists' => $input !== null,
            'isString' => is_string($input),
            'isInt' => is_int($input),
            'isBool' => is_bool($input) || $input == 1 || $input == 0,
            'isTrue' => $input == true,
            'isFalse' => $input == false,
            'inArray', 'in' => in_array($input, $value),
            'has' => is_array($input) && $this->_has($input, $value),
            default => false,
        };
    }

    protected function _has(array $input, mixed $value): bool
    {

        $parts = explode(':', $value);
        if (count($parts) !== 2) {
            return false;
        }
        [$key, $value] = $parts;

        foreach ($input as $item) {
            if (is_string($item) && $item === $value) {
                // key is ignored since array value is string
                return true;
            } elseif (is_array($item)) {
                $keyValue = $this->getInput($key, $item);

                if ($keyValue === $value) {
                    return true;
                }
            }
        }

        return false;
    }


    protected function _startsWithAny(string $input, array $values): bool
    {
        foreach ($values as $value) {
            if (mb_strpos($input, $value) === 0) {
                return true;
            }
        }
        return false;
    }

    protected function getInput(string $key, array $input): mixed
    {
        $parts = explode('.', $key);
        $current = &$input;
        while(!empty($parts)) {
            $key = array_shift($parts);
            if (!is_array($current)) {
                return null; // not found
            }
            if (!isset($current[$key])) {
                return null; // not found
            }
            $current = &$current[$key];
        }
        return $current;
    }

    public static function __callStatic(string $operator, array $arguments): self
    {
        if (!in_array($operator, self::OPERATORS)) {
            throw new \BadMethodCallException('Unknown operator: ' . $operator);
        }
        if (count($arguments) < 1) {
            throw new \InvalidArgumentException('Expression must have at least 1 argument');
        }
        $field = array_shift($arguments);
        $argument = $arguments[0] ?? null;
        return new self($field, $operator, $argument);
    }

    public static function fromArray(array $data): static
    {
        if (count($data) === 4) {
            array_shift($data);
        }
        if (count($data) !== 3) {
            throw new \InvalidArgumentException('Expression must be an array with 3 elements');
        }
        return new static($data[0], $data[1], $data[2]);
    }

    public function __toString(): string
    {
        if (is_array($this->value)) {
            $value = implode(', ', $this->value);
        } else {
            $value = $this->value;
        }
        return sprintf('%s %s %s', $this->field, $this->operator, $value);
    }
}
