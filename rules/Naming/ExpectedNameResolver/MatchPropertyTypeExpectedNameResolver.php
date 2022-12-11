<?php

declare(strict_types=1);

namespace Rector\Naming\ExpectedNameResolver;

use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Property;
use PHPStan\Reflection\ClassReflection;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfoFactory;
use Rector\Core\NodeManipulator\PropertyManipulator;
use Rector\Core\Reflection\ReflectionResolver;
use Rector\Naming\Naming\PropertyNaming;
use Rector\Naming\ValueObject\ExpectedName;
use Rector\NodeNameResolver\NodeNameResolver;
use Rector\StaticTypeMapper\StaticTypeMapper;

final class MatchPropertyTypeExpectedNameResolver
{
    public function __construct(
        private readonly PropertyNaming $propertyNaming,
        private readonly PhpDocInfoFactory $phpDocInfoFactory,
        private readonly NodeNameResolver $nodeNameResolver,
        private readonly PropertyManipulator $propertyManipulator,
        private readonly ReflectionResolver $reflectionResolver,
        private readonly StaticTypeMapper $staticTypeMapper
    ) {
    }

    public function resolve(Property $property, ClassLike $classLike): ?string
    {
        if (! $classLike instanceof Class_) {
            return null;
        }

        $classReflection = $this->reflectionResolver->resolveClassReflection($property);
        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        $propertyName = $this->nodeNameResolver->getName($property);
        if ($this->propertyManipulator->isUsedByTrait($classReflection, $propertyName)) {
            return null;
        }

        $expectedName = $this->resolveExpectedName($property);
        if (! $expectedName instanceof ExpectedName) {
            return null;
        }

        // skip if already has suffix
        $currentName = $this->nodeNameResolver->getName($property);
        if ($this->nodeNameResolver->endsWith($currentName, $expectedName->getName())) {
            return null;
        }

        return $expectedName->getName();
    }

    private function resolveExpectedName(Property $property): ?ExpectedName
    {
        // property type first
        if ($property->type instanceof \PhpParser\Node) {
            $propertyType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($property->type);
            return $this->propertyNaming->getExpectedNameFromType($propertyType);
        }

        // fallback to docblock
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($property);
        if ($phpDocInfo instanceof PhpDocInfo) {
            return $this->propertyNaming->getExpectedNameFromType($phpDocInfo->getVarType());
        }

        return null;
    }
}
