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
        $this->info('Next, register a route in your routes file:');
        $this->newLine();
        $this->line("    use App\\Http\\Controllers\\Passages\\{$name};");
        $this->newLine();
        $this->line("    Passage::get('{your-prefix}/{path?}', {$name}::class);");
        $this->newLine();
        $this->info("Then set the upstream base URI in {$name}::getOptions():");
        $this->newLine();
        $this->line('    public function getOptions(): array');
        $this->line('    {');
        $this->line("        return ['base_uri' => 'https://api.example.com/'];");
        $this->line('    }');

        return self::SUCCESS;
    }
}
