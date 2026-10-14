<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Text of one SMS template of a provider, e.g. "تیکت شماره [param1] انجام شد." */
class MessageTemplate extends Model
{
    public const MSGWAY = 'msgway';

    protected $fillable = ['provider', 'template_id', 'body', 'synced_at'];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    /** The SMS text: [param1], [param2], … and [code] replaced. Null when the template text is unknown. */
    public static function render(int $templateId, array $params = [], ?string $code = null, string $provider = self::MSGWAY): ?string
    {
        $body = static::where('provider', $provider)->where('template_id', $templateId)->value('body');
        if ($body === null) {
            return null;
        }

        $values = ['[code]' => (string) $code];
        foreach (array_values($params) as $i => $value) {
            $values['[param'.($i + 1).']'] = (string) $value;
        }

        return trim(str_replace("\r\n", "\n", strtr($body, $values)));
    }
}
