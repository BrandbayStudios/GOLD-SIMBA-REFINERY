<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Quotation;
use App\Models\RequestProviderMatch;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * All state changes for a service request go through here so the web UI and the mobile API
 * share identical rules, status logs and notifications.
 */
class RequestWorkflow
{
    public function __construct(
        private ProviderMatcher $matcher,
        private Notifier $notify,
        private PaymentGateway $gateway,
    ) {}

    /** @param UploadedFile[] $photos */
    public function create(array $data, ?User $customer, array $photos = []): ServiceRequest
    {
        $paths = [];
        foreach (array_slice($photos, 0, 5) as $photo) {
            $paths[] = $photo->store('request-photos', 'public');
        }

        $request = DB::transaction(function () use ($data, $customer, $paths) {
            $request = ServiceRequest::create([
                'customer_id' => $customer?->isCustomer() ? $customer->id : null,
                'category_id' => $data['category_id'],
                'service_id' => $data['service_id'] ?? null,
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'description' => $data['description'],
                'address' => $data['address'],
                'directions' => $data['directions'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => ServiceRequest::STATUS_PENDING,
                'photos' => $paths,
            ]);
            $this->log($request, ServiceRequest::STATUS_PENDING, $customer, 'Request submitted');

            return $request;
        });

        $this->notify->sms($request->contact_phone,
            "Request {$request->reference} received. We are finding a provider near you. Track: {$request->trackingUrl()}");
        if ($request->priority === 'emergency') {
            $this->notify->admins('Emergency request '.$request->reference,
                "{$request->category->name} emergency at {$request->address}", route('admin.requests.show', $request), 'emergency');
        }

        $this->match($request);

        return $request->fresh();
    }

    /** Notify eligible nearby providers. Safe to call repeatedly: providers already notified are skipped. */
    public function match(ServiceRequest $request): int
    {
        if (! $request->isOpen()) {
            return 0;
        }
        $new = 0;
        foreach ($this->matcher->candidates($request) as $c) {
            $match = RequestProviderMatch::firstOrCreate(
                ['service_request_id' => $request->id, 'provider_id' => $c['profile']->user_id],
                ['distance_km' => $c['distance']],
            );
            if (! $match->wasRecentlyCreated) {
                continue;
            }
            $new++;
            $dist = $c['distance'] !== null ? " ({$c['distance']} km away)" : '';
            $label = $request->priority === 'emergency' ? 'EMERGENCY job' : 'New job';
            $this->notify->user($c['profile']->user, "{$label}: {$request->category->name}",
                "{$request->reference} at {$request->address}{$dist}. Open the app to quote.",
                route('provider.jobs.show', $request), $request->priority === 'emergency' ? 'emergency' : 'job', true);
        }

        $hasMatches = $request->matches()->exists();
        if ($hasMatches && $request->status === ServiceRequest::STATUS_PENDING) {
            $request->update(['status' => ServiceRequest::STATUS_SEARCHING]);
            $this->log($request, ServiceRequest::STATUS_SEARCHING, null, $request->matches()->count().' nearby provider(s) notified');
        }

        return $new;
    }

    /** When a provider comes online, offer them open requests they are eligible for. */
    public function matchOpenRequestsFor(ProviderProfile $profile): void
    {
        ServiceRequest::whereIn('status', ServiceRequest::OPEN_STATUSES)
            ->whereIn('category_id', $profile->categories()->pluck('categories.id'))
            ->where('created_at', '>=', now()->subDays(2))
            ->get()
            ->each(fn ($r) => $this->match($r));
    }

    public function submitQuote(ServiceRequest $request, User $provider, array $data): Quotation
    {
        $this->ensureProviderEligible($request, $provider);
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['amount' => 'This request is no longer accepting quotations.']);
        }

        $quote = Quotation::updateOrCreate(
            ['service_request_id' => $request->id, 'provider_id' => $provider->id],
            [
                'amount' => $data['amount'],
                'callout_fee' => $data['callout_fee'] ?? 0,
                'eta_minutes' => $data['eta_minutes'],
                'message' => $data['message'] ?? null,
                'status' => 'pending',
            ],
        );
        RequestProviderMatch::updateOrCreate(
            ['service_request_id' => $request->id, 'provider_id' => $provider->id],
            ['status' => 'quoted'],
        );
        $this->log($request, 'quote_received', $provider, ($provider->providerProfile?->displayName() ?? $provider->name).' quoted '.zmw($quote->total()));

        $msg = "New quote for {$request->reference}: ".zmw($quote->total())." from {$provider->name}, arriving in ~{$quote->eta_minutes} min.";
        $request->customer
            ? $this->notify->user($request->customer, 'New quotation received', $msg, route('customer.requests.show', $request), 'quote', true)
            : $this->notify->sms($request->contact_phone, $msg.' Review: '.$request->trackingUrl());

        return $quote;
    }

    public function declineJob(ServiceRequest $request, User $provider): void
    {
        RequestProviderMatch::where('service_request_id', $request->id)->where('provider_id', $provider->id)
            ->update(['status' => 'declined']);
        Quotation::where('service_request_id', $request->id)->where('provider_id', $provider->id)
            ->where('status', 'pending')->update(['status' => 'withdrawn']);
    }

    public function acceptQuote(Quotation $quote, ?User $actor): ServiceRequest
    {
        $request = $quote->serviceRequest;
        if (! $request->isOpen() || $quote->status !== 'pending') {
            throw ValidationException::withMessages(['quote' => 'This quotation can no longer be accepted.']);
        }

        DB::transaction(function () use ($quote, $request, $actor) {
            $quote->update(['status' => 'accepted']);
            $request->quotations()->where('id', '!=', $quote->id)->where('status', 'pending')->update(['status' => 'rejected']);
            $request->update([
                'provider_id' => $quote->provider_id,
                'agreed_amount' => $quote->total(),
                'status' => ServiceRequest::STATUS_ASSIGNED,
                'assigned_at' => now(),
            ]);
            $this->log($request, ServiceRequest::STATUS_ASSIGNED, $actor, 'Quotation of '.zmw($quote->total()).' accepted');
        });

        $this->notify->user($quote->provider, 'Quotation accepted – you got the job',
            "{$request->contact_name} accepted your quote for {$request->reference}. Confirm and head out.",
            route('provider.jobs.show', $request), 'success', true);

        return $request->fresh();
    }

    public function rejectQuote(Quotation $quote): void
    {
        if ($quote->status === 'pending') {
            $quote->update(['status' => 'rejected']);
            $this->notify->user($quote->provider, 'Quotation declined',
                "Your quote for {$quote->serviceRequest->reference} was declined by the customer.",
                route('provider.jobs.show', $quote->serviceRequest), 'info');
        }
    }

    public function assignDirectly(ServiceRequest $request, User $provider, User $admin): void
    {
        if (! $provider->isProvider() || ! $provider->providerProfile?->isApproved()) {
            throw ValidationException::withMessages(['provider_id' => 'Only verified providers can be assigned.']);
        }
        $request->update([
            'provider_id' => $provider->id,
            'status' => ServiceRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $this->log($request, ServiceRequest::STATUS_ASSIGNED, $admin, 'Assigned by administrator');
        $this->notify->user($provider, 'Job assigned by HANDY NOW', "You have been assigned {$request->reference} at {$request->address}.",
            route('provider.jobs.show', $request), 'success', true);
        $this->customerUpdate($request, "{$provider->name} has been assigned to your request {$request->reference}.");
    }

    public function providerUpdateStatus(ServiceRequest $request, User $provider, string $status, ?string $note = null): void
    {
        if ($request->provider_id !== $provider->id) {
            abort(403);
        }
        if (! in_array($status, $request->nextProviderStatuses(), true)) {
            throw ValidationException::withMessages(['status' => 'That status change is not allowed right now.']);
        }

        $attrs = ['status' => $status];
        if ($status === ServiceRequest::STATUS_COMPLETED) {
            $attrs['completed_at'] = now();
        }
        $request->update($attrs);
        $this->log($request, $status, $provider, $note);

        if ($status === ServiceRequest::STATUS_COMPLETED) {
            $provider->providerProfile?->increment('jobs_completed');
        }

        $messages = [
            ServiceRequest::STATUS_ACCEPTED => "{$provider->name} confirmed your job {$request->reference}.",
            ServiceRequest::STATUS_EN_ROUTE => "{$provider->name} is on the way to you. Track live: {$request->trackingUrl()}",
            ServiceRequest::STATUS_ARRIVED => "{$provider->name} has arrived at your location.",
            ServiceRequest::STATUS_IN_PROGRESS => "Work has started on {$request->reference}.",
            ServiceRequest::STATUS_COMPLETED => "Job {$request->reference} is complete. Amount due: ".zmw($request->agreed_amount ?? 0).'. Please pay and rate your provider.',
        ];
        $this->customerUpdate($request, $messages[$status] ?? 'Status updated: '.$request->statusLabel());
    }

    public function cancel(ServiceRequest $request, ?User $actor, string $reason): void
    {
        if ($request->isCompleted() || $request->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'This request can no longer be cancelled.']);
        }
        $request->update(['status' => ServiceRequest::STATUS_CANCELLED, 'cancel_reason' => $reason, 'cancelled_at' => now()]);
        $request->quotations()->where('status', 'pending')->update(['status' => 'rejected']);
        $this->log($request, ServiceRequest::STATUS_CANCELLED, $actor, $reason);

        if ($request->provider) {
            $this->notify->user($request->provider, 'Job cancelled', "{$request->reference} was cancelled: {$reason}",
                route('provider.jobs.show', $request), 'warning', true);
        }
        if ($actor === null || $actor->id !== $request->customer_id) {
            $this->customerUpdate($request, "Your request {$request->reference} was cancelled. Reason: {$reason}");
        }
    }

    public function recordPayment(ServiceRequest $request, array $data, ?User $actor): Payment
    {
        if (! $request->isCompleted() && ! in_array($request->status, [ServiceRequest::STATUS_IN_PROGRESS, ServiceRequest::STATUS_ARRIVED], true)) {
            throw ValidationException::withMessages(['method' => 'Payment can be recorded once the provider is on site.']);
        }
        if ($request->payment_status === 'paid') {
            throw ValidationException::withMessages(['method' => 'This job has already been paid.']);
        }

        $payment = Payment::create([
            'service_request_id' => $request->id,
            'customer_id' => $request->customer_id,
            'provider_id' => $request->provider_id,
            'amount' => $data['amount'] ?? $request->agreed_amount,
            'method' => $data['method'],
            'payer_phone' => $data['payer_phone'] ?? null,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'gateway' => $this->gateway->name(),
            'status' => 'pending',
        ]);
        $payment = $this->gateway->initiate($payment);
        $request->update(['payment_status' => 'pending']);
        $this->log($request, 'payment_recorded', $actor, $payment->methodLabel().' payment of '.zmw($payment->amount).' recorded – awaiting confirmation');

        $this->notify->user($request->provider, 'Payment recorded – please confirm',
            'Customer recorded '.zmw($payment->amount)." via {$payment->methodLabel()} for {$request->reference}.",
            route('provider.jobs.show', $request), 'payment', true);

        return $payment;
    }

    /** Confirms receipt and calculates the platform commission automatically. */
    public function confirmPayment(Payment $payment, User $actor): void
    {
        if ($payment->status === 'confirmed') {
            return;
        }
        $rate = (float) Setting::get('commission_rate', 10);
        $commission = round((float) $payment->amount * $rate / 100, 2);

        DB::transaction(function () use ($payment, $actor, $rate, $commission) {
            $payment->update([
                'status' => 'confirmed',
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'provider_amount' => (float) $payment->amount - $commission,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);
            $request = $payment->serviceRequest;
            $request->update([
                'payment_status' => 'paid',
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'provider_earnings' => (float) $payment->amount - $commission,
            ]);
            $this->log($request, 'payment_confirmed', $actor, zmw($payment->amount).' confirmed. Platform commission '.zmw($commission)." ({$rate}%)");
        });

        $this->customerUpdate($payment->serviceRequest, 'Payment of '.zmw($payment->amount).' for '.$payment->serviceRequest->reference.' confirmed. Thank you!');
    }

    public function rejectPayment(Payment $payment, User $actor): void
    {
        $payment->update(['status' => 'failed', 'confirmed_by' => $actor->id]);
        $payment->serviceRequest->update(['payment_status' => 'unpaid']);
        $this->log($payment->serviceRequest, 'payment_rejected', $actor, 'Payment could not be verified');
        $this->customerUpdate($payment->serviceRequest, "Payment for {$payment->serviceRequest->reference} could not be verified. Please check and try again.");
    }

    public function review(ServiceRequest $request, int $rating, ?string $comment): Review
    {
        if (! $request->isCompleted() || ! $request->provider_id) {
            throw ValidationException::withMessages(['rating' => 'You can rate once the job is completed.']);
        }
        if ($request->review) {
            throw ValidationException::withMessages(['rating' => 'This job has already been rated.']);
        }
        $review = Review::create([
            'service_request_id' => $request->id,
            'customer_id' => $request->customer_id,
            'provider_id' => $request->provider_id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
        $this->refreshRating($request->provider_id);
        $this->notify->user($request->provider, "New {$rating}-star review", $comment ? mb_strimwidth($comment, 0, 120, '…') : "For job {$request->reference}",
            route('provider.reviews'), 'review');

        return $review;
    }

    public function refreshRating(int $providerId): void
    {
        $stats = Review::where('provider_id', $providerId)->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')->first();
        ProviderProfile::where('user_id', $providerId)->update([
            'rating_avg' => round((float) $stats->avg, 2),
            'rating_count' => (int) $stats->cnt,
        ]);
    }

    public function log(ServiceRequest $request, string $status, ?User $user, ?string $note = null): void
    {
        $request->statusLogs()->create(['status' => $status, 'user_id' => $user?->id, 'note' => $note]);
    }

    private function customerUpdate(ServiceRequest $request, string $message): void
    {
        $request->customer
            ? $this->notify->user($request->customer, 'Update on '.$request->reference, $message, route('customer.requests.show', $request), 'status', true)
            : $this->notify->sms($request->contact_phone, $message);
    }

    private function ensureProviderEligible(ServiceRequest $request, User $provider): void
    {
        $profile = $provider->providerProfile;
        if (! $profile || ! $profile->isApproved() || $provider->isSuspended()) {
            throw ValidationException::withMessages(['amount' => 'Your account must be verified before you can quote.']);
        }
        if (! $profile->categories()->where('categories.id', $request->category_id)->exists()) {
            throw ValidationException::withMessages(['amount' => 'You do not offer this service category.']);
        }
    }
}
