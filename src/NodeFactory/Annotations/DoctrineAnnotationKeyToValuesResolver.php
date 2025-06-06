<?php

declare(strict_types=1);

namespace Rector\Symfony\NodeFactory\Annotations;

use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use Rector\PhpParser\Node\Value\ValueResolver;

final readonly class DoctrineAnnotationKeyToValuesResolver
{
    public function __construct(
        private ValueResolver $valueResolver,
        private StringValueQuoteWrapper $stringValueQuoteWrapper,
    ) {
    }

    /**
     * @return array<string|null, mixed>|mixed[]
     */
    public function resolveFromExpr(Expr $expr): array
    {
        $annotationKeyToValues = [];

        if ($expr instanceof Array_) {
            foreach ($expr->items as $arrayItem) {
                if (! $arrayItem instanceof ArrayItem) {
                    continue;
                }

                $key = $this->resolveKey($arrayItem);

                $value = $this->valueResolver->getValue($arrayItem->value);
                $value = $this->stringValueQuoteWrapper->wrap($value, $key);

                // implicit key with no name
                if ($key === null) {
                    $annotationKeyToValues[] = $value;
                } else {
                    $annotationKeyToValues[$key] = $value;
                }
            }
        } else {
            $singleValue = $this->valueResolver->getValue($expr);
            $singleValue = $this->stringValueQuoteWrapper->wrap($singleValue, null);
            return [$singleValue];
        }

        return $annotationKeyToValues;
    }

    private function resolveKey(ArrayItem $arrayItem): ?string
    {
        if (! $arrayItem->key instanceof Expr) {
            return null;
        }

        return $this->valueResolver->getValue($arrayItem->key);
    }
}
