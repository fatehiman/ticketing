<?php

namespace App\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * msgway.com HTTP client. msgway is template based: we send a template ID, the [paramN] values
 * as a positional array, and the [code] value as a separate top-level field (if it is put in
 * params, msgway sends a random number instead).
 */
class MsgwayClient
{
    /** Error code msgway returns for a wrong payload (params as object, wrong count). Never retry it. */
    public const BAD_REQUEST = '2001010102';

    /**
     * @return array{ok: bool, reference_id?: ?string, error?: string, retryable?: bool}
     */
    public function send(string $mobile, int $templateId, array $params = [], ?string $code = null, string $method = 'sms'): array
    {
        $config = config('services.msgway');
        if (empty($config['key'])) {
            return ['ok' => false, 'error' => 'MSGWAY_API_KEY is not set', 'retryable' => false];
        }

        $payload = [
            'mobile' => $mobile,
            'method' => $method,
            'templateID' => $templateId,
            'params' => array_values(array_map('strval', $params)),
        ];
        if ($code !== null) {
            $payload['code'] = $code;
        }
        if ($method === 'ivr') {
            $payload['provider'] = (int) $config['ivr_provider'];
        }

        try {
            $response = Http::withHeaders(['apiKey' => $config['key']])
                ->asJson()
                ->timeout((int) $config['timeout'])
                ->post(rtrim($config['url'], '/').'/send', $payload);
        } catch (\Throwable $e) {
            Log::warning('sms.msgway.transport_error', ['mobile' => $mobile, 'template' => $templateId, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'transport: '.$e->getMessage(), 'retryable' => true];
        }

        $body = $response->json() ?? [];
        if (($body['status'] ?? null) === 'success') {
            return ['ok' => true, 'reference_id' => isset($body['referenceID']) ? (string) $body['referenceID'] : null];
        }

        $err = $body['error'] ?? [];
        $errCode = isset($err['code']) ? (string) $err['code'] : 'http '.$response->status();
        Log::warning('sms.msgway.error', ['mobile' => $mobile, 'template' => $templateId, 'http' => $response->status(), 'body' => $body]);

        $clientError = $response->status() >= 400 && $response->status() < 500 && $response->status() !== 429;

        return [
            'ok' => false,
            'error' => trim($errCode.' '.($err['message'] ?? '').(isset($err['traceID']) ? ' trace '.$err['traceID'] : '')),
            'retryable' => $errCode !== self::BAD_REQUEST && ! $clientError,
        ];
    }
}
