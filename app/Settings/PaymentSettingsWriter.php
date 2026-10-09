<?php

namespace App\Settings;

use App\Models\PaymentSetting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Writes the admin-managed payment configuration.
 *
 * Everything here is deliberately narrow: it stores, rotates and clears
 * credentials, and it never returns a secret to the caller. The only secret
 * that ever leaves this class is the one the reveal action hands straight to a
 * response, which is audited.
 */
class PaymentSettingsWriter
{
    public function __construct(
        private readonly PaymentProviderConfig $config,
    ) {}

    /**
     * Save the settings form.
     *
     * A blank credential field means "keep what is already stored" rather than
     * "erase it", because an admin editing one field should not silently
     * destroy the other. Erasing is a separate, explicit action.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(array $input, User $actor): PaymentSetting
    {
        $row = $this->config->rowOrCreate();

        $changes = [];

        // Toggles. A present value wins; an absent one keeps the current state,
        // so an unrelated partial save cannot silently re-enable payments.
        foreach (PaymentSetting::TOGGLES as $toggle) {
            if (array_key_exists($toggle, $input)) {
                $value = filter_var($input[$toggle], FILTER_VALIDATE_BOOL);

                if ((bool) $row->{$toggle} !== $value) {
                    $changes[] = $toggle;
                }

                $row->{$toggle} = $value;
            }
        }

        if (array_key_exists('webhook_url', $input)) {
            $url = trim((string) $input['webhook_url']);
            $url = $url === '' ? null : $url;

            if ($row->webhook_url !== $url) {
                $changes[] = 'webhook_url';
            }

            $row->webhook_url = $url;
        }

        // Credentials. Only written when a non-empty value was actually typed.
        foreach (PaymentSetting::SECRET_FIELDS as $field) {
            $submitted = trim((string) ($input[$field] ?? ''));

            if ($submitted === '') {
                continue;
            }

            if ($submitted === $this->currentValue($field)) {
                // Re-submitting the value that is already stored would rewrite
                // the ciphertext and change nothing else; skip it so the
                // "last changed" stamp stays meaningful.
                continue;
            }

            $row->{$field} = $submitted;
            $changes[] = $field;
        }

        $row->updated_by = $actor->id;
        $row->save();

        // The next read in this same request must see the new values.
        $this->config->refresh();

        $this->audit($actor, $changes === [] ? 'reviewed' : 'updated', $changes);

        return $row;
    }

    /**
     * Forget one stored credential, so the environment value takes effect again.
     *
     * @throws ValidationException
     */
    public function clearSecret(string $field, User $actor): PaymentSetting
    {
        if (! in_array($field, PaymentSetting::SECRET_FIELDS, true)) {
            throw ValidationException::withMessages([
                'field' => 'That is not a credential that can be cleared.',
            ]);
        }

        $row = $this->config->row();

        if ($row === null || ! $row->hasStored($field)) {
            // Nothing to do; still a success so the UI is idempotent.
            $this->config->refresh();

            return $row ?? $this->config->rowOrCreate();
        }

        $row->{$field} = null;
        $row->updated_by = $actor->id;
        $row->save();

        $this->config->refresh();

        $this->audit($actor, 'cleared', [$field]);

        return $row;
    }

    /**
     * Delete the whole saved row, returning every field to the environment.
     */
    public function resetToEnvironment(User $actor): void
    {
        $this->config->row()?->delete();

        $this->config->refresh();

        $this->audit($actor, 'reset', []);
    }

    /**
     * The decrypted value of a stored credential, for the audited reveal.
     * Nothing else in the application should need this.
     */
    public function reveal(string $field, User $actor): ?string
    {
        if (! in_array($field, PaymentSetting::SECRET_FIELDS, true)) {
            return null;
        }

        $value = $field === 'api_key'
            ? $this->config->apiKey()
            : $this->config->webhookSecret();

        // Record the access, never the value.
        $this->audit($actor, 'revealed', [$field]);

        return $value;
    }

    private function currentValue(string $field): ?string
    {
        $value = $field === 'api_key'
            ? $this->config->apiKey()
            : $this->config->webhookSecret();

        return $value === null ? null : (string) $value;
    }

    /**
     * Safe audit line: who did what to which fields. Never any value.
     *
     * @param  list<string>  $changes
     */
    private function audit(User $actor, string $action, array $changes): void
    {
        Log::channel('stack')->info('payment_settings.'.$action, [
            'actor_id' => $actor->id,
            'actor_email' => $actor->email,
            'fields' => $changes,
        ]);
    }
}
