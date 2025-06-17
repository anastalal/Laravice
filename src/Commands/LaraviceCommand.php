<?php

namespace AnasTalal\Laravice\Commands;

use Illuminate\Console\Command;

class LaraviceCommand extends Command
{
    public $signature = 'laravice';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
