<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Float;

use IanFoxDev\MoneyLint\MoneyDetector;
use IanFoxDev\MoneyLint\Rules\RuleDocs;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A money parameter typed float. Promoted constructor parameters are properties and
 * are reported by FloatPropertyRule.
 *
 * @implements Rule<Param>
 */
final class FloatParameterRule implements Rule
{
    public function __construct(private readonly MoneyDetector $detector) {}

    public function getNodeType(): string
    {
        return Param::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isPromoted() || !$node->var instanceof Variable || !is_string($node->var->name)) {
            return [];
        }
        $name = $node->var->name;
        if (!FloatTypes::nativeTypeNamesFloat($node->type)
            || !$this->detector->isMoneyDeclaration($name, MoneyDetector::attributeClasses($node->attrGroups))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Parameter $%s holds money and is typed float: floats cannot store most decimal amounts exactly.',
                $name,
            ))
                ->identifier(FloatTypes::IDENTIFIER)
                ->tip(RuleDocs::tip('float', FloatTypes::TIP))
                ->build(),
        ];
    }
}
