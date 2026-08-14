<?php

namespace Morcen\Passage\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Morcen\Passage\Http\Controllers\PassageController;
use Morcen\Passage\PassageControllerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'passage:health')]
class PassageHealthCommand extends Command
{
    public $signature = 'passage:health
                         {--timeout=5 : Seconds to wait for each health check}';

    public $description = 'Ping the base_uri of every registered Passage route and report connectivity.';

    public function handle(): int
    {
        if (! config('passage.enabled', true)) {
            $this->warn('Passage is disabled (PASSAGE_ENABLED=false).');

            return self::SUCCESS;
        }

        $routes = $this->passageRoutes();

        if ($routes->isEmpty()) {
            $this->warn('No Passage routes are registered.');

            return self::SUCCESS;
        }

        $timeout = (int) $this->option('timeout');
        $rows = [];
        $anyFailed = false;

        foreach ($routes as $route) {
            $handler = $route->defaults['_passage_handler'] ?? null;

            if (! $handler) {
                continue;
            }

            if (! class_exists($handler) || ! is_subclass_of($handler, PassageControllerInterface::class)) {
                $rows[] = [class_basename($handler), '—', '<fg=red>FAIL</>', 'Invalid or missing handler class'];
                $anyFailed = true;

                continue;
            }

            try {
                $handlerInstance = app($handler);
                $options = array_merge(config('passage.options', []), $handlerInstance->getOptions());
            } catch (Throwable $e) {
                $rows[] = [class_basename($handler), '—', '<fg=red>FAIL</>', 'Could not resolve handler: '.$e->getMessage()];
                $anyFailed = true;

                continue;
            }

            $baseUri = $options['base_uri'] ?? null;

            if (! $baseUri) {
                $rows[] = [$handler, $baseUri ?? '—', '<fg=yellow>SKIPPED</>', 'No base_uri configured'];

                continue;
            }

            [$status, $code, $message] = $this->probe($baseUri, $timeout);

            if (! $status) {
                $anyFailed = true;
            }

            $rows[] = [
                class_basename($handler),
                rtrim($baseUri, '/'),
                $status ? '<fg=green>OK</>' : '<fg=red>FAIL</>',
                $message,
            ];
        }

        $this->table(['Handler', 'Base URI', 'Status', 'Detail'], $rows);

        return $anyFailed ? self::FAILURE : self::SUCCESS;
    }

    private function probe(string $baseUri, int $timeout): array
    {
        try {
            $response = Http::timeout($timeout)->head($baseUri);
            $code = $response->status();

            // Any response (even 4xx/5xx) means the host is reachable.
            return [true, $code, "HTTP {$code}"];
        } catch (Throwable $e) {
            return [false, 0, $e->getMessage()];
        }
    }

    private function passageRoutes(): Collection
    {
        return collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_contains(
                $route->getActionName(),
                PassageController::class.'@handle'
            ));
    }
}
