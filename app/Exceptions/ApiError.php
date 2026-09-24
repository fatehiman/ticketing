<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/** A simple JSON error for the bot API: {"ok": false, "error": "...", "message": "..."}. */
class ApiError extends Exception
{
    public function __construct(public string $error, string $message, public int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => $this->error, 'message' => $this->getMessage()], $this->status);
    }
}
