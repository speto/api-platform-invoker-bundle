<?php

declare(strict_types=1);

namespace Speto\ApiPlatformInvokerBundle\UriVar;

use Speto\ApiPlatformInvokerBundle\UriVar\Attribute\MapUriVar;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class UriVarValueResolver implements ValueResolverInterface
{
    public function __construct(
        private UriVarInstantiator $instantiator
    ) {
    }

    /**
     * @return iterable<mixed>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attrs = $argument->getAttributes(MapUriVar::class, ArgumentMetadata::IS_INSTANCEOF);

        if ($attrs) {
            // Explicit mapping with #[MapUriVar] attribute
            /** @var MapUriVar $attr */
            $attr = $attrs[0];
            $name = $attr->name;
        } else {
            // Magic mapping: use parameter name as URI variable name
            $parameterName = $argument->getName();
            if (! $request->attributes->has($parameterName)) {
                return [];
            }
            $name = $parameterName;
        }

        if (! $request->attributes->has($name)) {
            return [];
        }

        $type = $argument->getType();
        if (! $type) {
            return [];
        }

        $value = $request->attributes->get($name);

        if ($this->isBuiltinType($type)) {
            yield $this->coerceBuiltinValue($type, $value);
            return;
        }

        /** @var class-string $type */
        yield $this->instantiator->instantiate($type, $value);
    }

    private function isBuiltinType(string $type): bool
    {
        return in_array($type, ['string', 'int', 'float', 'bool', 'array', 'object', 'mixed'], true);
    }

    private function coerceBuiltinValue(string $type, mixed $value): mixed
    {
        return match ($type) {
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
            'array' => is_array($value) ? $value : [$value],
            'object' => is_object($value) ? $value : (object) $value,
            'mixed' => $value,
            default => $value,
        };
    }
}
