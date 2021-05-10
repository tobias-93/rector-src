<?php

declare(strict_types=1);

namespace Rector\EarlyReturn\Rector\If_;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\If_;
use Rector\Core\NodeManipulator\IfManipulator;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * @see \Rector\Tests\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector\ChangeOrIfContinueToMultiContinueRectorTest
 */
final class ChangeOrIfContinueToMultiContinueRector extends AbstractRector
{
    public function __construct(
        private IfManipulator $ifManipulator
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Changes if && to early return', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function canDrive(Car $newCar)
    {
        foreach ($cars as $car) {
            if ($car->hasWheels() || $car->hasFuel()) {
                continue;
            }

            $car->setWheel($newCar->wheel);
            $car->setFuel($newCar->fuel);
        }
    }
}
CODE_SAMPLE

                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function canDrive(Car $newCar)
    {
        foreach ($cars as $car) {
            if ($car->hasWheels()) {
                continue;
            }
            if ($car->hasFuel()) {
                continue;
            }

            $car->setWheel($newCar->wheel);
            $car->setFuel($newCar->fuel);
        }
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
        return [If_::class];
    }

    /**
     * @param If_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->ifManipulator->isIfWithOnly($node, Continue_::class)) {
            return null;
        }

        if (! $node->cond instanceof BooleanOr) {
            return null;
        }

        return $this->processMultiIfContinue($node);
    }

    private function processMultiIfContinue(If_ $if): If_
    {
        $node = clone $if;
        /** @var Continue_ $continue */
        $continue = $if->stmts[0];
        $ifs = $this->createMultipleIfs($if->cond, $continue, []);
        foreach ($ifs as $key => $if) {
            if ($key === 0) {
                $this->mirrorComments($if, $node);
            }

            $this->addNodeBeforeNode($if, $node);
        }

        $this->removeNode($node);
        return $node;
    }

    /**
     * @param If_[] $ifs
     * @return If_[]
     */
    private function createMultipleIfs(Expr $expr, Continue_ $continue, array $ifs): array
    {
        while ($expr instanceof BooleanOr) {
            $ifs = array_merge($ifs, $this->collectLeftbooleanOrToIfs($expr, $continue, $ifs));
            $ifs[] = $this->ifManipulator->createIfExpr($expr->right, $continue);

            $expr = $expr->right;
        }

        return $ifs + [$this->ifManipulator->createIfExpr($expr, $continue)];
    }

    /**
     * @param If_[] $ifs
     * @return If_[]
     */
    private function collectLeftbooleanOrToIfs(BooleanOr $booleanOr, Continue_ $continue, array $ifs): array
    {
        $left = $booleanOr->left;
        if (! $left instanceof BooleanOr) {
            return [$this->ifManipulator->createIfExpr($left, $continue)];
        }

        return $this->createMultipleIfs($left, $continue, $ifs);
    }
}
