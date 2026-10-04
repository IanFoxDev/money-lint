<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Float;

use IanFoxDev\MoneyLint\MoneyDetector;
use IanFoxDev\MoneyLint\Rules\RuleDocs;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A money property typed float, promoted constructor parameters included.
 *
 * @implements Rule<ClassPropertyNode>
 */
final class FloatPropertyRule implements Rule
{
    public function __construct(private readonly MoneyDetector $detector) {}

    public function getNodeType(): string
    {
        return ClassPropertyNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $name = $node->getName();
        $class = $node->getClassReflection();
        $attributes = [];
        if ($class->hasNativeProperty($name)) {
            foreach ($class->getNativeProperty($name)->getAttributes() as $attribute) {
                $attributes[] = $attribute->getName();
            }
        }
        if (!$this->detector->isMoneyDeclaration($name, $attributes)) {
            return [];
        }

        $typeNode = $node->getNativeTypeNode();
        $float = $typeNode !== null
            ? FloatTypes::nativeTypeNamesFloat($typeNode)
            : FloatTypes::phpDocTypeIsFloat($node->getPhpDocType());
        if (!$float) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Property %s::$%s holds money and is typed float: floats cannot store most decimal amounts exactly.',
                $class->getDisplayName(),
                $name,
            ))
                ->identifier(FloatTypes::IDENTIFIER)
                ->tip(RuleDocs::tip('float', FloatTypes::TIP))
                ->build(),
        ];
    }
}
