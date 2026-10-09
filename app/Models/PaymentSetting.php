<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-managed settings for a payment provider.
 *
 * The credential columns use Laravel's `encrypted` cast, so the database only
 * ever holds authenticated ciphertext produced with the application APP_KEY.
 * Reading a column decrypts it in memory; nothing here is ever logged,
 * serialised into a response, or written back in the clear.
 *
 * A null credential means "not set here", which makes the environment value the
 * fallback. That keeps a deployment that already configures `.env` working
 * exactly as before while still allowing an admin to override any single field.
 *
 * @property int $id
 * @property string $provider
 * @property string|null $api_key
 * @property string|null $webhook_secret
 * @property string|null $webhook_url
 * @property bool $enabled
 * @property bool $verify_on_webhook
 * @property int|null $updated_by
 */
class PaymentSetting extends Model
{
    use HasFactory;

    public const PROVIDER_ABLINER = 'abliner';

    /**
     * The credential fields, i.e. the ones that are encrypted and the ones an
     * admin may clear to fall back to the environment.
     *
     * @var list<string>
     */
    public const SECRET_FIELDS = ['api_key', 'webhook_secret'];

    /**
     * @var list<string>
     */
    public const TOGGLES = ['enabled', 'verify_on_webhook'];

    protected $fillable = [
        'provider',
        'api_key',
        'webhook_secret',
        'webhook_url',
        'enabled',
        'verify_on_webhook',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            // Authenticated encryption using APP_KEY. A value that cannot be
            // decrypted (for example after APP_KEY was rotated) must fail
            // loudly rather than silently becoming an empty string.
            'api_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'enabled' => 'boolean',
            'verify_on_webhook' => 'boolean',
        ];
    }

    /**
     * The single settings row for a provider, if the admin has ever saved one.
     */
    public static function forProvider(string $provider = self::PROVIDER_ABLINER): ?self
    {
        return static::query()->where('provider', $provider)->first();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasStored(string $field): bool
    {
        return in_array($field, self::SECRET_FIELDS, true) && filled($this->getRawOriginal($field));
    }
}
