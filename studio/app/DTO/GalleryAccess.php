<?php

declare(strict_types=1);

namespace App\DTO;

use App\Models\TokenType;

/**
 * The result of resolving a gallery link.
 *
 * Carries both the decision and the reason, so a controller can render the
 * right page (password form, expired notice, the gallery itself) without
 * re-deriving anything, and so the audit log records why access was refused.
 */
final class GalleryAccess
{
    public const OK              = 'ok';
    public const NOT_FOUND       = 'not_found';
    public const REVOKED         = 'revoked';
    public const EXPIRED         = 'expired';
    public const WRONG_TYPE      = 'wrong_type';
    public const GALLERY_EXPIRED = 'gallery_expired';
    public const GALLERY_CLOSED  = 'gallery_closed';
    public const PASSWORD_REQUIRED = 'password_required';
    public const DOWNLOAD_DISABLED = 'download_disabled';

    /**
     * @param array<string, mixed>|null $gallery
     * @param array<string, mixed>|null $token
     */
    private function __construct(
        public readonly string $status,
        public readonly ?array $gallery = null,
        public readonly ?array $token = null
    ) {
    }

    /** @param array<string, mixed> $gallery @param array<string, mixed> $token */
    public static function granted(array $gallery, array $token): self
    {
        return new self(self::OK, $gallery, $token);
    }

    /** @param array<string, mixed>|null $gallery @param array<string, mixed>|null $token */
    public static function denied(string $status, ?array $gallery = null, ?array $token = null): self
    {
        return new self($status, $gallery, $token);
    }

    public function isGranted(): bool
    {
        return $this->status === self::OK;
    }

    public function needsPassword(): bool
    {
        return $this->status === self::PASSWORD_REQUIRED;
    }

    public function galleryId(): ?int
    {
        return $this->gallery === null ? null : (int) $this->gallery['id'];
    }

    public function tokenId(): ?int
    {
        return $this->token === null ? null : (int) $this->token['id'];
    }

    public function tokenType(): ?string
    {
        return $this->token === null ? null : (string) $this->token['token_type'];
    }

    public function allowsDownload(): bool
    {
        if (!$this->isGranted() || $this->token === null || $this->gallery === null) {
            return false;
        }

        // Both conditions must hold: the link must be a DOWNLOAD link, and the
        // photographer must still have downloads enabled on the gallery. Either
        // one alone is not enough.
        return TokenType::allowsDownload((string) $this->token['token_type'])
            && (bool) $this->gallery['download_enabled'];
    }

    public function allowsSelection(): bool
    {
        return $this->isGranted()
            && $this->gallery !== null
            && (bool) $this->gallery['selection_enabled'];
    }

    /** Message shown on the "no longer available" page. */
    public function message(): string
    {
        return match ($this->status) {
            self::EXPIRED, self::GALLERY_EXPIRED => "Cette galerie n'est plus disponible : le lien a expiré.",
            self::REVOKED                        => "Cette galerie n'est plus disponible : le lien a été désactivé.",
            self::GALLERY_CLOSED                 => "Cette galerie n'est plus disponible.",
            self::WRONG_TYPE                     => "Ce lien ne donne pas accès à cette page.",
            self::DOWNLOAD_DISABLED              => "Le téléchargement n'est pas activé pour cette galerie.",
            default                              => "Cette galerie n'est plus disponible.",
        };
    }

    /** HTTP status for the refusal page. */
    public function httpStatus(): int
    {
        return match ($this->status) {
            self::NOT_FOUND  => 404,
            self::OK         => 200,
            default          => 410,
        };
    }
}
