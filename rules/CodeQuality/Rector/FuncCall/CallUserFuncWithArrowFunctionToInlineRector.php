<?php

declare(strict_types=1);

namespace Rector\CodeQuality\Rector\FuncCall;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use Rector\Core\Rector\AbstractRector;
use Rector\Php74\NodeAnalyzer\ClosureArrowFunctionAnalyzer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @changelog https://3v4l.org/fuuEF
 *
 * @see \Rector\Tests\CodeQuality\Rector\FuncCall\CallUserFuncWithArrowFunctionToInlineRector\CallUserFuncWithArrowFunctionToInlineRectorTest
 */
final class CallUserFuncWithArrowFunctionToInlineRector extends AbstractRector
{
    public function __construct(
        private ClosureArrowFunctionAnalyzer $closureArrowFunctionAnalyzer
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Refactor call_user_func() with arrow function to direct call', [
            new CodeSample(
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        $result = \call_user_func(fn () => 100);
    }
}
CODE_SAMPLE

                ,
                <<<'CODE_SAMPLE'
final class SomeClass
{
    public function run()
    {
        $result = 100;
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /**
     * @param FuncCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isName($node, 'call_user_func')) {
            return null;
        }

        if (count($node->args) !== 1) {
            return null;
        }

        // change the node
        $firstArgValue = $node->args[0]->value;
        if ($firstArgValue instanceof ArrowFunction) {
            return $firstArgValue->expr;
        }

        if ($firstArgValue instanceof Closure) {
            return $this->closureArrowFunctionAnalyzer->matchArrowFunctionExpr($firstArgValue);
        }

        return null;
    }
}
