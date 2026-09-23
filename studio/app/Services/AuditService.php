<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Repositories\AuditLogRepository;

/**
 * Writes the audit trail.
 *
 * Failure to log must never break the action being logged — a full disk
 * should not stop a photographer delivering photos — so every write is
 * guarded and degrades to the application log.
 */
final class AuditService
{
    public function __construct(private ?AuditLogRepository $logs = null)
    {
        $this->logs = $logs ?? new AuditLogRepository();
    }

    /** @param array<string, mixed> $context */
    public function record(
        string $action,
        ?Request $request = null,
        ?int $galleryId = null,
        ?int $photoId = null,
        array $context = [],
        ?int $userId = null
    ): void {
        try {
            $this->logs->insert([
                'user_id'    => $userId ?? Auth::id(),
                'gallery_id' => $galleryId,
                'photo_id'   => $photoId,
                'action'     => $action,
                'context'    => $context === [] ? null : json_encode($this->scrub($context), JSON_UNESCAPED_UNICODE),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Audit write failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Strip anything that must not be persisted.
     *
     * Raw tokens are the main risk: the audit trail is read by support staff
     * and exported, and a row containing a working gallery link would defeat
     * storing only token hashes.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function scrub(array $context): array
    {
        $forbidden = ['token', 'raw', 'raw_token', 'password', 'password_hash', 'media_token'];
        $clean = [];

        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }

    /** @return array<int, array<string, mixed>> */
    public function recent(int $limit = 10): array
    {
        return $this->logs->recent($limit);
    }
}
