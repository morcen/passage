<?php

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Morcen\Passage\Facades\Passage;
use Morcen\Passage\PassageControllerInterface;

class ListCommandTestPassageController implements PassageControllerInterface
{
    public function getRequest(Request $request): Request
    {
        return $request;
    }

    public function getResponse(Request $request, Response $response): Response
    {
        return $response;
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.github.com'];
    }
}

describe('PassageListCommand', function () {
    it('warns when passage is disabled', function () {
        config()->set('passage.enabled', false);

        $this->artisan('passage:list')
            ->expectsOutputToContain('Passage is disabled')
            ->assertExitCode(1);
    });

    it('warns when no Passage routes are registered', function () {
        config()->set('passage.enabled', true);

        $this->artisan('passage:list')
            ->expectsOutputToContain('No Passage routes registered')
            ->assertExitCode(0);
    });

    it('lists registered Passage routes', function () {
        config()->set('passage.enabled', true);

        Passage::get('github/{path?}', ListCommandTestPassageController::class);

        $this->artisan('passage:list')
            ->expectsTable(
                ['Method', 'URI', 'Target'],
                [['GET|HEAD', 'github/{path?}', 'https://api.github.com ('.ListCommandTestPassageController::class.')']]
            )
            ->assertExitCode(0);
    });

    it('shows multiple registered routes', function () {
        config()->set('passage.enabled', true);

        Passage::get('github/{path?}', ListCommandTestPassageController::class);
        Passage::post('github/{path?}', ListCommandTestPassageController::class);

        $this->artisan('passage:list')
            ->expectsOutputToContain('github/{path?}')
            ->assertExitCode(0);
    });
});
