<?php

namespace Morcen\Passage\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'passage:controller')]
class PassageCommand extends Command
{
    public $signature = 'passage:controller {name}';

    public $description = 'Create a new passage controller.';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (file_exists(app_path('Http/Controllers/Passages/'.$name.'.php'))) {
            $this->error("Passage controller $name already exists at app/Http/Controllers/Passages/{$name}.php");

            return self::FAILURE;
        }

        Artisan::call("make:controller Passages/$name --type=passage ");

        $this->info("Passage controller created at app/Http/Controllers/Passages/{$name}.php successfully!");
        $this->newLine();
        $this->info('Add the following to config.passage.services, if not already: ');
        $this->newLine();

        $service = strtolower(str_replace(['PassageController', 'Controller'], '', $name));
        $this->info('// config/passage.php');
        $this->info('services => [');
        $this->info('    ...');
        $this->line("    '{$service}' => App\Http\Controllers\Passages\\{$name}::class,");
        $this->info("    // replace '$service' with the name of the service you want to use in your route.");
        $this->info(']');

        return self::SUCCESS;
    }
}
