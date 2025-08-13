<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\UriVar;

use ReflectionClass;
use ReflectionMethod;
use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\UriVarConstructor;

final class UriVarInstantiator
{
    /**
     * @param class-string $class
     */
    public function instantiate(string $class, mixed $value): object
    {
        /** @var ReflectionClass<object> $rc */
        $rc = new ReflectionClass($class);

        if ($attr = $rc->getAttributes(UriVarConstructor::class)[0] ?? null) {
            $method = $attr->newInstance()
                ->method;
            $rm = $rc->getMethod($method);
            if (! $rm->isPublic() || ! $rm->isStatic() || $rm->getNumberOfRequiredParameters() !== 1) {
                throw new \LogicException("Invalid UriVarConstructor {$class}::{$method}().");
            }
            if (! ParamType::accepts($rm->getParameters()[0], $value)) {
                throw new \InvalidArgumentException("Value not accepted by {$class}::{$method}().");
            }
            /** @var callable(mixed):object $factory */
            $factory = [$class, $method];
            $coercedValue = $this->coerceValue($rm->getParameters()[0], $value);
            return $factory($coercedValue);
        }

        $candidates = [];

        if (($ctor = $rc->getConstructor())
            && $ctor->isPublic()
            && $ctor->getNumberOfRequiredParameters() === 1
            && ParamType::accepts($ctor->getParameters()[0], $value)
        ) {
            $param = $ctor->getParameters()[0];
            $candidates[] = fn (mixed $v) => new $class($this->coerceValue($param, $v));
        }

        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if (! $m->isStatic() || $m->getNumberOfRequiredParameters() !== 1) {
                continue;
            }
            $ret = $m->getReturnType();
            if (! $ret || ! $ret instanceof \ReflectionNamedType || $ret->isBuiltin() || ! in_array(
                $ret->getName(),
                [$class, 'self'],
                true
            )) {
                continue;
            }
            if (! ParamType::accepts($m->getParameters()[0], $value)) {
                continue;
            }

            $name = $m->getName();
            $param = $m->getParameters()[0];
            $candidates[] = fn (mixed $v) => $class::$name($this->coerceValue($param, $v));
        }

        $count = count($candidates);
        if ($count === 1) {
            $result = $candidates[0]($value);
            if (! $result instanceof $class) {
                throw new \LogicException("Factory for {$class} did not return an instance of {$class}");
            }
            return $result;
        }
        if ($count === 0) {
            throw new \LogicException("No usable constructor/factory for {$class}.");
        }
        throw new \LogicException("Ambiguous factories for {$class}; add #[UriVarConstructor(...)] to disambiguate.");
    }

    private function coerceValue(\ReflectionParameter $param, mixed $value): mixed
    {
        $type = $param->getType();
        if (! $type instanceof \ReflectionNamedType || ! $type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'string' => is_scalar($value) || (is_object($value) && method_exists(
                $value,
                '__toString'
            )) ? (string) $value : $value,
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'bool' => match ($value) {
                'true', '1', 1 => true,
                'false', '0', 0 => false,
                default => (bool) $value,
            },
            default => $value,
        };
    }
}
