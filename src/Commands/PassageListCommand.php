<?php

namespace Morcen\Passage\Commands;

use Illuminate\Console\Command;
use Morcen\Passage\Support\PassageRouteRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'passage:list')]
class PassageListCommand extends Command
{
    public $signature = 'passage:list';

    public $description = 'List all registered Passage proxy routes.';

    public function __construct(protected readonly PassageRouteRegistry $routeRegistry)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! (config('passage.enabled') ?? true)) {
            $this->warn('Passage is disabled. Set PASSAGE_ENABLED=true to enable it.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($this->routeRegistry->routes() as $route) {
            $handler = $this->routeRegistry->handlerClassFor($route);
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
        if (! $this->routeRegistry->isValidHandler($handler)) {
            return $handler;
        }

        try {
            $instance = $this->routeRegistry->resolveHandler($handler);
            $options = $this->routeRegistry->optionsFor($instance);
        } catch (Throwable) {
            return $handler.' (could not resolve target)';
        }

        if (isset($options['base_uri'])) {
            return $options['base_uri'].' ('.$handler.')';
        }

        return $handler;
    }
}
