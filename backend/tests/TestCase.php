<?php

declare(strict_types=1);

namespace Tests;

use App\Integrations\Bitrix24\Contracts\BitrixServiceAccountInterface;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Mockery\MockInterface;

abstract class TestCase extends BaseTestCase
{
    /**
     * Binds a stand-in for $interface whose $method runs $using, while every
     * other method runs the real implementation. Repositories are final, so
     * this wraps the interface instead of a partial mock of the class.
     *
     * @param  class-string  $interface
     */
    protected function replaceMethod(string $interface, string $method, callable $using): MockInterface
    {
        $real = resolve($interface);
        $double = Mockery::mock($interface);

        foreach (get_class_methods($interface) as $name) {
            $double->shouldReceive($name)->andReturnUsing(
                $name === $method ? $using : fn (mixed ...$arguments): mixed => $real->{$name}(...$arguments),
            );
        }

        $this->app->instance($interface, $double);

        return $double;
    }

    /** Background sync (callAsService, batches) uses $holder's token instead of the installed portal's. */
    protected function useBitrixServiceAccount(HasBitrixToken $holder): void
    {
        // Not `new readonly class`: anonymous readonly classes need PHP 8.3, the project runs 8.2.
        $this->app->instance(BitrixServiceAccountInterface::class, new class($holder) implements BitrixServiceAccountInterface
        {
            public function __construct(private readonly HasBitrixToken $holder) {}

            public function holder(): HasBitrixToken
            {
                return $this->holder;
            }
        });
    }
}
