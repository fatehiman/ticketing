<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = ['ticket_id', 'user_id', 'path', 'original_name', 'mime', 'size'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function isImage(): bool
    {
        return in_array($this->mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'], true);
    }

    public function icon(): string
    {
        $ext = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));

        return match (true) {
            $this->isImage() => 'bi-file-earmark-image text-info',
            $ext === 'pdf' => 'bi-file-earmark-pdf text-danger',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf']) => 'bi-file-earmark-word text-primary',
            in_array($ext, ['xls', 'xlsx', 'xlsm', 'csv', 'ods']) => 'bi-file-earmark-excel text-success',
            in_array($ext, ['ppt', 'pptx', 'odp']) => 'bi-file-earmark-ppt text-warning',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']) => 'bi-file-earmark-zip text-secondary',
            str_starts_with((string) $this->mime, 'video/') => 'bi-file-earmark-play text-danger',
            str_starts_with((string) $this->mime, 'audio/') => 'bi-file-earmark-music text-info',
            default => 'bi-file-earmark-text text-secondary',
        };
    }

    public function humanSize(): string
    {
        $size = $this->size;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($size < 1024) {
                return round($size, 1).' '.$unit;
            }
            $size /= 1024;
        }

        return round($size, 1).' TB';
    }
}
