<?php

namespace Morcen\Passage\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'passage:controller')]
class PassageCommand extends Command
{
    public $signature = 'passage:controller
                         {name : Handler class name}
                         {--with-auth= : Auth style to scaffold: bearer, apikey, or hmac}
                         {--with-cache : Scaffold with response caching enabled}
                         {--with-retry : Scaffold with retry configuration}';

    public $description = 'Create a new Passage handler.';

    public function handle(): int
    {
        $name = $this->argument('name');
        $authStyle = $this->option('with-auth');
        $withCache = $this->option('with-cache');
        $withRetry = $this->option('with-retry');

        if (file_exists(app_path('Http/Controllers/Passages/'.$name.'.php'))) {
            $this->error("Passage handler {$name} already exists at app/Http/Controllers/Passages/{$name}.php");

            return self::FAILURE;
        }

        $stubType = $this->resolveStubType($authStyle, $withCache);
        Artisan::call("make:controller Passages/{$name} --type=passage{$stubType}");

        $this->info("Passage handler created at app/Http/Controllers/Passages/{$name}.php");
        $this->newLine();
        $this->info('Register a route in your routes file:');
        $this->newLine();
        $this->line("    use App\\Http\\Controllers\\Passages\\{$name};");
        $this->newLine();
        $this->line("    Passage::get('{your-prefix}/{path?}', {$name}::class);");

        if ($withRetry) {
            $this->newLine();
            $this->info('Add retry support in getOptions():');
            $this->newLine();
            $this->line('    public function getOptions(): array');
            $this->line('    {');
            $this->line('        return array_merge(');
            $this->line("            ['base_uri' => 'https://api.example.com/'],");
            $this->line('            $this->withRetry(3, 200)');
            $this->line('        );');
            $this->line('    }');
        }

        return self::SUCCESS;
    }

    private function resolveStubType(?string $authStyle, bool $withCache): string
    {
        if ($authStyle !== null) {
            $style = strtolower($authStyle);
            if (in_array($style, ['bearer', 'apikey', 'hmac'], strict: true)) {
                return ".{$style}";
            }
            $this->warn("Unknown --with-auth value '{$authStyle}'. Using the default stub.");
        }

        if ($withCache) {
            return '.cached';
        }

        return '';
    }
}
