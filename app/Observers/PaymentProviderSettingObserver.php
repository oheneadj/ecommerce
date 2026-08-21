<?php

/**
 * Keeps the `public` disk in sync with the `logo_path` column — deletes the
 * old logo file when it's replaced, so swapping a provider's logo doesn't
 * leave the previous file orphaned in storage forever. Rows are never
 * deleted (one per PaymentProvider enum case, synced not destroyed), so
 * there's no delete-time cleanup to mirror from BrandObserver.
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\PaymentProviderSetting;
use Illuminate\Support\Facades\Storage;

class PaymentProviderSettingObserver
{
    public function saving(PaymentProviderSetting $setting): void
    {
        if (! $setting->isDirty('logo_path')) {
            return;
        }

        $original = $setting->getOriginal('logo_path');

        if ($original) {
            Storage::disk('public')->delete($original);
        }
    }
}
