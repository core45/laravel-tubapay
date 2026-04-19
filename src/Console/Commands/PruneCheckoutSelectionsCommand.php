<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Console\Commands;

use Core45\LaravelTubaPay\Contracts\CheckoutSelectionStore;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class PruneCheckoutSelectionsCommand extends Command
{
    protected $signature = 'tubapay:prune-selections {--before= : Prune selections expiring before this date/time.}';

    protected $description = 'Prune expired TubaPay checkout selections.';

    public function handle(CheckoutSelectionStore $selectionStore): int
    {
        $before = $this->option('before');
        $deleted = $selectionStore->pruneExpired(
            is_string($before) && $before !== '' ? Carbon::parse($before) : null,
        );

        $this->info(sprintf('Pruned %d expired TubaPay checkout selection(s).', $deleted));

        return self::SUCCESS;
    }
}
