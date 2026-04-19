<?php

declare(strict_types=1);

namespace Core45\LaravelTubaPay\Console\Commands;

use Core45\TubaPay\TubaPay;
use Illuminate\Console\Command;

final class CheckConnectionCommand extends Command
{
    protected $signature = 'tubapay:check-connection';

    protected $description = 'Check whether TubaPay credentials can authenticate against the API.';

    public function handle(TubaPay $tubaPay): int
    {
        $status = $tubaPay->checkConnection();

        if ($status->successful) {
            $this->info($status->message);

            return self::SUCCESS;
        }

        $this->error($status->message);

        return self::FAILURE;
    }
}
