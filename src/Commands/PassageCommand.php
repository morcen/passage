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

        if (! preg_match('/^[A-Za-z0-9_\/]+$/', $name)) {
            $this->error("Invalid handler name [{$name}]: only letters, numbers, underscores, and slashes are allowed.");

            return self::FAILURE;
        }

        if (file_exists(app_path('Http/Controllers/Passages/'.$name.'.php'))) {
            $this->error("Passage handler {$name} already exists at app/Http/Controllers/Passages/{$name}.php");

            return self::FAILURE;
        }

        $stubType = $this->resolveStubType($authStyle, $withCache, $withRetry);
        $exitCode = Artisan::call("make:controller Passages/{$name} --type=passage{$stubType}");

        if ($exitCode !== self::SUCCESS) {
            $this->error("Failed to create passage handler {$name}.");

            $output = trim(Artisan::output());
            if ($output !== '') {
                $this->line($output);
            }

            return self::FAILURE;
        }

        $this->info("Passage handler created at app/Http/Controllers/Passages/{$name}.php");
        $this->newLine();
        $this->info('Register a route in your routes file:');
        $this->newLine();
        $this->line("    use App\\Http\\Controllers\\Passages\\{$name};");
        $this->newLine();
        $this->line("    Passage::get('{your-prefix}/{path?}', {$name}::class);");

        return self::SUCCESS;
    }

    private function resolveStubType(?string $authStyle, bool $withCache, bool $withRetry): string
    {
        if ($authStyle !== null) {
            $style = strtolower($authStyle);
            if (in_array($style, ['bearer', 'apikey', 'hmac'], strict: true)) {
                if ($withCache) {
                    $this->warn('--with-cache is ignored when --with-auth is used. Add passage_cache_ttl to getOptions() manually if needed.');
                }
                if ($withRetry) {
                    $this->warn('--with-retry is ignored when --with-auth is used. Add $this->withRetry() to getOptions() manually if needed.');
                }

                return ".{$style}";
            }
            $this->warn("Unknown --with-auth value '{$authStyle}'. Using the default stub.");
        }

        if ($withCache) {
            if ($withRetry) {
                $this->warn('--with-retry is ignored when --with-cache is used. Add $this->withRetry() to getOptions() manually if needed.');
            }

            return '.cached';
        }

        if ($withRetry) {
            return '.retry';
        }

        return '';
    }
}
