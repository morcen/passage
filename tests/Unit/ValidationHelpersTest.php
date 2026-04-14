<?php

use Morcen\Passage\Contracts\ValidatesInboundRequest;
use Morcen\Passage\PassageHandler;

// Fixture: handler using all validation helpers
class ValidationHelperHandler extends PassageHandler implements ValidatesInboundRequest
{
    public function rules(): array
    {
        return [
            'product_id'   => $this->requiredId(),
            'email'        => $this->requiredEmail(),
            'name'         => $this->requiredString(255),
            'note'         => $this->optionalString(500),
            'quantity'     => $this->requiredPositiveInt(),
            'callback_url' => $this->requiredUrl(),
            'status'       => $this->requiredIn('active', 'inactive'),
            'priority'     => $this->optionalIn('low', 'medium', 'high'),
            'amount'       => $this->requiredNumeric(),
        ];
    }

    public function getOptions(): array
    {
        return ['base_uri' => 'https://api.example.com/'];
    }
}

describe('HasValidationHelpers', function () {
    beforeEach(function () {
        $this->handler = new ValidationHelperHandler;
    });

    it('returns required integer min:1 for requiredId', function () {
        $rules = $this->handler->rules();

        expect($rules['product_id'])->toBe(['required', 'integer', 'min:1']);
    });

    it('returns required email:rfc for requiredEmail', function () {
        $rules = $this->handler->rules();

        expect($rules['email'])->toBe(['required', 'email:rfc']);
    });

    it('returns required string with max for requiredString', function () {
        $rules = $this->handler->rules();

        expect($rules['name'])->toBe(['required', 'string', 'max:255']);
    });

    it('returns required string without max when no argument given', function () {
        $handler = new class extends PassageHandler
        {
            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.example.com/'];
            }

            public function getRules(): array
            {
                return $this->requiredString();
            }
        };

        expect($handler->getRules())->toBe(['required', 'string']);
    });

    it('returns nullable string with max for optionalString', function () {
        $rules = $this->handler->rules();

        expect($rules['note'])->toBe(['nullable', 'string', 'max:500']);
    });

    it('returns nullable string without max when no argument given', function () {
        $handler = new class extends PassageHandler
        {
            public function getOptions(): array
            {
                return ['base_uri' => 'https://api.example.com/'];
            }

            public function getRules(): array
            {
                return $this->optionalString();
            }
        };

        expect($handler->getRules())->toBe(['nullable', 'string']);
    });

    it('returns required integer min:1 for requiredPositiveInt', function () {
        $rules = $this->handler->rules();

        expect($rules['quantity'])->toBe(['required', 'integer', 'min:1']);
    });

    it('returns required url for requiredUrl', function () {
        $rules = $this->handler->rules();

        expect($rules['callback_url'])->toBe(['required', 'url']);
    });

    it('returns required in:values for requiredIn', function () {
        $rules = $this->handler->rules();

        expect($rules['status'])->toBe(['required', 'in:active,inactive']);
    });

    it('returns nullable in:values for optionalIn', function () {
        $rules = $this->handler->rules();

        expect($rules['priority'])->toBe(['nullable', 'in:low,medium,high']);
    });

    it('returns required numeric for requiredNumeric', function () {
        $rules = $this->handler->rules();

        expect($rules['amount'])->toBe(['required', 'numeric']);
    });
});
