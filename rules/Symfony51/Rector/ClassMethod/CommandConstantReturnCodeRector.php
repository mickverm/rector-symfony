<?php

declare(strict_types=1);

namespace Rector\Symfony\Symfony51\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Reflection\ClassReflection;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\Rector\AbstractRector;
use Rector\Reflection\ReflectionResolver;
use Rector\Symfony\ValueObject\ConstantMap\SymfonyCommandConstantMap;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * https://symfony.com/blog/new-in-symfony-5-1-misc-improvements-part-1#added-constants-for-command-exit-codes
 *
 * @see \Rector\Symfony\Tests\Symfony51\Rector\ClassMethod\CommandConstantReturnCodeRector\CommandConstantReturnCodeRectorTest
 */
final class CommandConstantReturnCodeRector extends AbstractRector
{
    public function __construct(
        private readonly ReflectionResolver $reflectionResolver,
        private readonly BetterNodeFinder $betterNodeFinder,
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Changes int return from execute to use Symfony Command constants.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class SomeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 0;
    }

}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class SomeCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        $classReflection = $this->reflectionResolver->resolveClassReflection($node);
        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if (! $classReflection->is('Symfony\Component\Console\Command\Command')) {
            return null;
        }

        if (! $this->isName($node, 'execute')) {
            return null;
        }

        $hasChanged = false;

        /** @var Return_[] $returns */
        $returns = $this->betterNodeFinder->findInstancesOfInFunctionLikeScoped($node, [Return_::class]);

        foreach ($returns as $return) {
            if (! $return->expr instanceof Int_) {
                continue;
            }
            $classConstFetch = $this->convertNumberToConstant($return->expr);
            if (! $classConstFetch instanceof ClassConstFetch) {
                continue;
            }

            $hasChanged = true;
            $return->expr = $classConstFetch;
        }

        if ($hasChanged) {
            return $node;
        }

        return null;
    }

    private function convertNumberToConstant(Int_ $int): ?ClassConstFetch
    {
        if (! isset(SymfonyCommandConstantMap::RETURN_TO_CONST[$int->value])) {
            return null;
        }

        return $this->nodeFactory->createClassConstFetch(
            'Symfony\Component\Console\Command\Command',
            SymfonyCommandConstantMap::RETURN_TO_CONST[$int->value]
        );
    }
}
