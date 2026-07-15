<?php

namespace App\Console\Commands;

use App\Services\MobilePairing;
use Illuminate\Console\Command;
use RuntimeException;

class CreateMobilePairingCode extends Command
{
    protected $signature = 'casa:mobile-pairing-code {--json : Print a machine-readable result for the Windows helper}';

    protected $description = 'Create a short-lived, single-use mobile demo pairing code';

    public function handle(MobilePairing $pairing): int
    {
        try {
            $result = $pairing->issue();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Mobile pairing code: '.$result['code']);
        $this->line('Backend: '.$result['base_url']);
        $this->line('Expires: '.$result['expires_at']);

        return self::SUCCESS;
    }
}
