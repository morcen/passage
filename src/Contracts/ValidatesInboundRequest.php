<?php

namespace Morcen\Passage\Contracts;

/**
 * Optional interface for Passage handlers.
 *
 * When a handler implements this interface, Passage will run Laravel validation
 * against the inbound request using the rules returned by rules() BEFORE calling
 * getRequest(). A validation failure returns a 422 response automatically and
 * the request is never forwarded upstream.
 *
 * This keeps validation logic out of getRequest(), which is intended for
 * transformation, not input checking.
 *
 * Example:
 *
 *   class CreateOrderHandler extends PassageHandler implements ValidatesInboundRequest
 *   {
 *       public function rules(): array
 *       {
 *           return [
 *               'product_id' => ['required', 'integer'],
 *               'quantity'   => ['required', 'integer', 'min:1'],
 *           ];
 *       }
 *   }
 */
interface ValidatesInboundRequest
{
    /**
     * Return Laravel validation rules for the inbound request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;
}
