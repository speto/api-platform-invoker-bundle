<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\UriVar;

final class ParamType
{
    public static function accepts(\ReflectionParameter $p, mixed $value): bool
    {
        $t = $p->getType();
        if (! $t) {
            return true;
        }
        $types = $t instanceof \ReflectionUnionType ? $t->getTypes() : [$t];
        $isUnion = $t instanceof \ReflectionUnionType;

        foreach ($types as $tt) {
            if (! $tt instanceof \ReflectionNamedType) {
                continue;
            }

            if ($value === null && $tt->allowsNull()) {
                return true;
            }

            if ($tt->isBuiltin()) {
                $matches = match ($tt->getName()) {
                    'string' => is_string($value) || (! $isUnion && (is_int($value) || is_float($value) || is_bool(
                        $value
                    ))),
                    'int' => is_int($value) || (! $isUnion && is_string($value) && is_numeric($value)),
                    'float' => is_float($value) || (! $isUnion && (is_int($value) || (is_string($value) && is_numeric(
                        $value
                    )))),
                    'bool' => is_bool($value) || (! $isUnion && in_array(
                        $value,
                        ['true', 'false', '1', '0', 1, 0],
                        true
                    )),
                    'array' => is_array($value),
                    'object' => is_object($value),
                    'mixed' => true,
                    default => false,
                };

                if ($matches) {
                    return true;
                }
            } else {
                if (is_object($value) && is_a($value, $tt->getName())) {
                    return true;
                }
            }
        }
        return false;
    }
}
