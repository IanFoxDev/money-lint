<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Recognises a Laravel job or mail put on the queue, and whether it waits for the
 * commit.
 *
 * @internal
 */
final class QueuedDispatch
{
    private const SHOULD_QUEUE = 'Illuminate\Contracts\Queue\ShouldQueue';
    private const AFTER_COMMIT = 'Illuminate\Contracts\Queue\ShouldQueueAfterCommit';
    private const MAILABLE = 'Illuminate\Contracts\Mail\Mailable';

    /** Calls that queue whatever they get. class => methods */
    private const ALWAYS_QUEUED = [
        'Illuminate\Support\Facades\Mail' => ['queue', 'later', 'queueon', 'lateron'],
        'Mail' => ['queue', 'later', 'queueon', 'lateron'],
        'Illuminate\Support\Facades\Queue' => ['push', 'pushon', 'later', 'lateron'],
        'Queue' => ['push', 'pushon', 'later', 'lateron'],
    ];

    /** Calls on objects that queue whatever they get. interface or class => methods */
    private const ALWAYS_QUEUED_ON = [
        'Illuminate\Mail\PendingMail' => ['queue', 'later'],
        'Illuminate\Contracts\Mail\MailQueue' => ['queue', 'later'],
        'Illuminate\Contracts\Queue\Queue' => ['push', 'pushon', 'later', 'lateron'],
    ];

    /** Calls that queue a job only if it implements ShouldQueue. */
    private const BUS = [
        'Illuminate\Support\Facades\Bus' => ['dispatch', 'dispatchtoqueue'],
        'Bus' => ['dispatch', 'dispatchtoqueue'],
    ];

    private const BUS_ON = [
        'Illuminate\Contracts\Bus\Dispatcher' => ['dispatch', 'dispatchtoqueue'],
    ];

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    /**
     * What is queued, such as "job SendReceipt" or "mail", when the call queues
     * something that does not wait for the commit; otherwise null.
     */
    public function describe(Expr\CallLike $node, Scope $scope): ?string
    {
        if ($node->getAttribute(TransactionCallbackVisitor::COMMIT_CHAINED) === true || $node->isFirstClassCallable()) {
            return null;
        }

        // dispatch($job), the global helper.
        if ($node instanceof Expr\FuncCall && $node->name instanceof Name
            && $this->reflectionProvider->hasFunction($node->name, $scope)
            && $this->reflectionProvider->getFunction($node->name, $scope)->getName() === 'dispatch') {
            return $this->job($node, $scope, true, true);
        }

        // SendReceipt::dispatch(...) through the Dispatchable trait.
        if ($node instanceof Expr\StaticCall && $node->class instanceof Name && $node->name instanceof Identifier
            && in_array($node->name->toLowerString(), ['dispatch', 'dispatchif', 'dispatchunless'], true)) {
            $className = $scope->resolveName($node->class);
            if ($this->reflectionProvider->hasClass($className)) {
                $class = $this->reflectionProvider->getClass($className);
                if ($class->is(self::SHOULD_QUEUE)) {
                    return $this->waitsForCommit($class) ? null : 'job ' . $class->getNativeReflection()->getShortName();
                }
            }
        }

        if (!($node instanceof Expr\StaticCall || $node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall)
            || !$node->name instanceof Identifier) {
            return null;
        }
        $method = $node->name->toLowerString();

        if ($node instanceof Expr\StaticCall && $node->class instanceof Name) {
            $className = $scope->resolveName($node->class);
            if (in_array($method, self::ALWAYS_QUEUED[$className] ?? [], true)) {
                return $this->job($node, $scope, false);
            }
            if (in_array($method, self::BUS[$className] ?? [], true)) {
                return $this->job($node, $scope, true);
            }

            return null;
        }

        if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) {
            $receiver = $scope->getType($node->var);
            if ($this->onAny($receiver, self::ALWAYS_QUEUED_ON, $method)) {
                return $this->job($node, $scope, false);
            }
            if ($this->onAny($receiver, self::BUS_ON, $method)) {
                return $this->job($node, $scope, true);
            }
        }

        return null;
    }

    /**
     * Looks for the job or the mailable among the arguments.
     *
     * @param bool $onlyIfShouldQueue the call runs a job in the process unless it implements ShouldQueue
     * @param bool $closureQueued the call queues a closure (the dispatch() helper does, Bus::dispatch() does not)
     */
    private function job(Expr\CallLike $node, Scope $scope, bool $onlyIfShouldQueue, bool $closureQueued = false): ?string
    {
        foreach ($node->getArgs() as $arg) {
            if ($arg->value instanceof Expr\MethodCall && $arg->value->name instanceof Identifier
                && $arg->value->name->toLowerString() === 'aftercommit') {
                return null;
            }
            $type = $scope->getType($arg->value);
            if ($type->getObjectClassNames() === ['Closure']) {
                return $closureQueued ? 'closure' : null;
            }
            $jobs = array_values(array_filter(
                $type->getObjectClassReflections(),
                static fn(ClassReflection $class): bool => $class->is(self::SHOULD_QUEUE) || $class->is(self::MAILABLE),
            ));
            if ($jobs === []) {
                if ($onlyIfShouldQueue && $type->isObject()->yes()) {
                    return null;
                }
                continue;
            }
            foreach ($jobs as $class) {
                if ($onlyIfShouldQueue && !$class->is(self::SHOULD_QUEUE)) {
                    return null;
                }
                if ($this->waitsForCommit($class)) {
                    return null;
                }
            }

            return ($jobs[0]->is(self::MAILABLE) ? 'mail ' : 'job ') . $jobs[0]->getNativeReflection()->getShortName();
        }

        return $onlyIfShouldQueue ? null : 'job';
    }

    private function waitsForCommit(ClassReflection $class): bool
    {
        if ($class->is(self::AFTER_COMMIT)) {
            return true;
        }
        $native = $class->getNativeReflection();
        if ($native->hasProperty('afterCommit')) {
            $property = $native->getProperty('afterCommit');
            if ($property->hasDefaultValue() && $property->getDefaultValue() === true) {
                return true;
            }
        }

        // Jobs that use the Queueable trait cannot redeclare $afterCommit, so they
        // turn it on in the constructor: $this->afterCommit() or $this->afterCommit = true.
        $constructor = $native->getConstructor();
        if ($constructor === null) {
            return false;
        }
        $file = $constructor->getFileName();
        $start = $constructor->getStartLine();
        $end = $constructor->getEndLine();
        if ($file === false || $start === false || $end === false || !is_file($file)) {
            return false;
        }
        $lines = file($file);
        if ($lines === false) {
            return false;
        }
        $body = implode('', array_slice($lines, $start - 1, $end - $start + 1));

        return preg_match('/->\s*afterCommit\s*(\(|=\s*true\b)/i', $body) === 1;
    }

    /**
     * @param array<string, list<string>> $classes
     */
    private function onAny(Type $receiver, array $classes, string $method): bool
    {
        foreach ($classes as $class => $methods) {
            if (in_array($method, $methods, true) && (new ObjectType($class))->isSuperTypeOf($receiver)->yes()) {
                return true;
            }
        }

        return false;
    }
}
