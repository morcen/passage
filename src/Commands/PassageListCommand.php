<?php

namespace Morcen\Passage\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageControllerInterface;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'passage:list')]
class PassageListCommand extends Command
{
    public $signature = 'passage:list';

    public $description = 'List all registered Passage proxy routes.';

    public function handle(): int
    {
        if (! (config('passage.enabled') ?? true)) {
            $this->warn('Passage is disabled. Set PASSAGE_ENABLED=true to enable it.');

            return self::FAILURE;
        }

        $rows = [];

        foreach (Route::getRoutes() as $route) {
            $uses = $route->getAction('uses');
            if (! is_string($uses) || ! str_contains($uses, class_basename(PassageController::class).'@handle')) {
                continue;
            }

            $handler = $route->defaults['_passage_handler'] ?? null;
            $rows[] = [
                implode('|', $route->methods()),
                $route->uri(),
                $handler ? $this->resolveTarget($handler) : '(unknown)',
            ];
        }

        if (empty($rows)) {
            $this->warn('No Passage routes registered. Define routes using Passage::get(), Passage::post(), etc.');

            return self::SUCCESS;
        }

        $this->table(['Method', 'URI', 'Target'], $rows);

        return self::SUCCESS;
    }

    private function resolveTarget(string $handler): string
    {
        if (! class_exists($handler) || ! is_subclass_of($handler, PassageControllerInterface::class)) {
            return $handler;
        }

        $instance = new $handler;
        $options = $instance->getOptions();

        if (isset($options['base_uri'])) {
            return $options['base_uri'].' ('.$handler.')';
        }

        return $handler;
    }
}
