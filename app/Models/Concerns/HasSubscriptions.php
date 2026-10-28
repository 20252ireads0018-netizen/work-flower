<?php

namespace App\Models\Concerns;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Use no model User: `use HasSubscriptions;` */
trait HasSubscriptions
{
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Assinatura mais recente (qualquer status), para exibir na tela. */
    public function latestSubscription(): ?Subscription
    {
        return $this->subscriptions()->with('plan')->latest('id')->first();
    }

    /** Assinatura que hoje dá acesso ao sistema (ou null). */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->with('plan')
            ->latest('id')
            ->get()
            ->first(fn (Subscription $s) => $s->grantsAccess());
    }

    /** $planSlugs opcional: ex. ['pro', 'premium']. */
    public function isSubscribed(?array $planSlugs = null): bool
    {
        $subscription = $this->activeSubscription();

        if (! $subscription) {
            return false;
        }

        return $planSlugs === null || in_array($subscription->plan->slug, $planSlugs, true);
    }
}

