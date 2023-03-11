<?php

namespace Morcen\Passage\Commands;

use Illuminate\Console\Command;

class PassageCommand extends Command
{
    public $signature = 'passage';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
