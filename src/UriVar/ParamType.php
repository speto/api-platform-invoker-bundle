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

        foreach ($types as $tt) {
            if (! $tt instanceof \ReflectionNamedType) {
                continue;
            }

            if ($tt->isBuiltin()) {
                if (match ($tt->getName()) {
                    'string' => is_string($value),
                    'int' => is_int($value),
                    'float' => is_float($value),
                    'bool' => is_bool($value),
                    'array' => is_array($value),
                    'object' => is_object($value),
                    'mixed' => true,
                    default => false,
                }) {
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
