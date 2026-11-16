<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PriceVariation;
use App\Models\ProviderProfile;
use App\Models\Quotation;
use App\Models\RequestProviderMatch;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\GatewayException;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
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
        private PaymentGatewayManager $gateways,
        private Ledger $ledger,
        private PricingService $pricing,
    ) {}

    /** @param UploadedFile[] $photos */
    public function create(array $data, ?User $customer, array $photos = []): ServiceRequest
    {
        $paths = [];
        foreach (array_slice($photos, 0, 5) as $photo) {
            $paths[] = $photo->store('request-photos', 'public');
        }

        $service = ! empty($data['service_id']) ? Service::find($data['service_id']) : null;
        $routeKm = $service?->needs_destination
            ? $this->pricing->routeKm($data['latitude'] ?? null, $data['longitude'] ?? null, $data['dest_latitude'] ?? null, $data['dest_longitude'] ?? null)
            : null;
        $fixedPrice = $this->pricing->fixedPrice($service, $routeKm);

        $request = DB::transaction(function () use ($data, $customer, $paths, $routeKm, $fixedPrice) {
            $request = ServiceRequest::create([
                'customer_id' => $customer?->isCustomer() ? $customer->id : null,
                'category_id' => $data['category_id'],
                'service_id' => $data['service_id'] ?? null,
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'],
                'description' => $data['description'],
                'address' => $data['address'],
                'directions' => $data['directions'] ?? null,
                'dest_address' => $data['dest_address'] ?? null,
                'dest_latitude' => $data['dest_latitude'] ?? null,
                'dest_longitude' => $data['dest_longitude'] ?? null,
                'route_km' => $routeKm,
                'fixed_price' => $fixedPrice,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => ServiceRequest::STATUS_PENDING,
                'photos' => $paths,
            ]);
            $this->log($request, ServiceRequest::STATUS_PENDING, $customer, 'Request submitted'.($fixedPrice ? ' – fixed price '.zmw($fixedPrice, 0) : ''));

            return $request;
        });

        $this->notify->sms($request->contact_phone,
            "Request {$request->reference} received. We are finding a provider near you".($request->isFixedPrice() ? ' (fixed price '.zmw($request->fixed_price, 0).')' : '').". Track: {$request->trackingUrl()}");
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
            $action = $request->isFixedPrice() ? ' Fixed price '.zmw($request->fixed_price, 0).' – first to accept gets the job.' : ' Open the app to quote.';
            $this->notify->user($c['profile']->user, "{$label}: {$request->category->name}",
                "{$request->reference} at {$request->address}{$dist}.{$action}",
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
        if ($request->isFixedPrice()) {
            throw ValidationException::withMessages(['amount' => 'This is a fixed-price job ('.zmw($request->fixed_price, 0).'). Accept it instead of quoting.']);
        }
        $this->pricing->assertQuoteAllowed($request->service, (float) $data['amount'] + (float) ($data['callout_fee'] ?? 0));

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
        $this->applyBookingFee($request->fresh());

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
            'agreed_amount' => $request->fixed_price ?? $request->agreed_amount,
            'status' => ServiceRequest::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        $this->log($request, ServiceRequest::STATUS_ASSIGNED, $admin, 'Assigned by administrator');
        $this->notify->user($provider, 'Job assigned by HANDY NOW', "You have been assigned {$request->reference} at {$request->address}.",
            route('provider.jobs.show', $request), 'success', true);
        $this->customerUpdate($request, "{$provider->name} has been assigned to your request {$request->reference}.");
        $this->applyBookingFee($request->fresh());
    }

    /** A fixed-price job: the first eligible provider to accept gets it, at the price set by HANDY NOW. */
    public function acceptFixed(ServiceRequest $request, User $provider): ServiceRequest
    {
        $this->ensureProviderEligible($request, $provider);
        if (! $request->isFixedPrice()) {
            throw ValidationException::withMessages(['amount' => 'This job is not a fixed-price job. Send a quotation instead.']);
        }

        $request = DB::transaction(function () use ($request, $provider) {
            $locked = ServiceRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['amount' => 'Another provider has already taken this job.']);
            }
            $locked->update([
                'provider_id' => $provider->id,
                'agreed_amount' => $locked->fixed_price,
                'status' => ServiceRequest::STATUS_ACCEPTED,
                'assigned_at' => now(),
            ]);
            RequestProviderMatch::updateOrCreate(['service_request_id' => $locked->id, 'provider_id' => $provider->id], ['status' => 'accepted']);
            $this->log($locked, ServiceRequest::STATUS_ASSIGNED, $provider, 'Fixed price of '.zmw($locked->fixed_price, 0).' accepted by '.($provider->providerProfile?->displayName() ?? $provider->name));
            $this->log($locked, ServiceRequest::STATUS_ACCEPTED, $provider);

            return $locked;
        });

        $this->applyBookingFee($request->fresh());
        $this->customerUpdate($request->fresh(), "{$provider->name} accepted your request {$request->reference} at the fixed price of ".zmw($request->fixed_price, 0).'.');

        return $request->fresh();
    }

    /** Called whenever a provider is confirmed on a request: the booking fee (if any) becomes due. */
    private function applyBookingFee(ServiceRequest $request): void
    {
        $fee = $this->pricing->bookingFee($request->service);
        if ($fee <= 0) {
            $request->update(['booking_fee_amount' => 0, 'booking_fee_status' => 'none']);

            return;
        }
        $request->update(['booking_fee_amount' => $fee, 'booking_fee_status' => 'pending']);
        $this->log($request, 'booking_fee_due', null, 'Booking fee of '.zmw($fee).' due before contact details are shared');
        $providerName = $request->provider->name;
        $this->customerUpdate($request, "{$providerName} is ready for {$request->reference}. Pay the ".zmw($fee).' booking fee to confirm the booking and see their contact details: '.$request->trackingUrl());
        $this->notify->user($request->provider, 'Waiting for the booking fee', "Customer details for {$request->reference} unlock once the customer pays the booking fee. We will alert you.",
            route('provider.jobs.show', $request), 'info');
    }

    public function providerUpdateStatus(ServiceRequest $request, User $provider, string $status, ?string $note = null): void
    {
        if ($request->provider_id !== $provider->id) {
            abort(403);
        }
        if ($request->bookingFeePending()) {
            throw ValidationException::withMessages(['status' => 'Waiting for the customer to pay the booking fee. You will be alerted as soon as it is paid.']);
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

    /** $customerInitiated: the customer cancelled, so a paid booking fee is kept. Otherwise it is owed back. */
    public function cancel(ServiceRequest $request, ?User $actor, string $reason, bool $customerInitiated = false): void
    {
        if ($request->isCompleted() || $request->isCancelled()) {
            throw ValidationException::withMessages(['reason' => 'This request can no longer be cancelled.']);
        }
        $request->update(['status' => ServiceRequest::STATUS_CANCELLED, 'cancel_reason' => $reason, 'cancelled_at' => now()]);
        $request->quotations()->where('status', 'pending')->update(['status' => 'rejected']);
        $request->variations()->where('status', 'pending')->update(['status' => 'declined', 'decided_at' => now()]);
        $this->log($request, ServiceRequest::STATUS_CANCELLED, $actor, $reason);

        if ($request->booking_fee_status === 'pending') {
            $request->payments()->where('kind', 'booking_fee')->where('status', 'pending')->update(['status' => 'failed', 'failure_reason' => 'Request cancelled']);
            $request->update(['booking_fee_status' => 'none']);
        } elseif ($request->booking_fee_status === 'paid' && ! $customerInitiated) {
            $request->update(['booking_fee_status' => 'refund_due']);
            $this->log($request, 'booking_fee_refund_due', null, 'Booking fee of '.zmw($request->booking_fee_amount).' is owed back to the customer');
            $this->notify->admins('Booking fee refund due', "{$request->reference} was cancelled after the fee was paid. Refund ".zmw($request->booking_fee_amount).' to '.$request->contact_name.'.',
                route('admin.requests.show', $request), 'payment');
        }

        if ($request->provider) {
            $this->notify->user($request->provider, 'Job cancelled', "{$request->reference} was cancelled: {$reason}",
                route('provider.jobs.show', $request), 'warning', true);
        }
        if ($actor === null || $actor->id !== $request->customer_id) {
            $this->customerUpdate($request, "Your request {$request->reference} was cancelled. Reason: {$reason}");
        }
    }

    /** Creates the payment record and, for MTN / Airtel, sends the PIN prompt. Shared by job payments and booking fees. */
    private function createPayment(ServiceRequest $request, array $data, string $kind, float $amount, ?int $providerId): Payment
    {
        $gateway = $this->gateways->forMethod($data['method']);
        if ($gateway->isOnline() && empty($data['payer_phone'])) {
            throw ValidationException::withMessages(['payer_phone' => 'Enter the mobile money number that should receive the payment prompt.']);
        }

        // A new attempt replaces any earlier prompt of the same kind that was never approved.
        $request->payments()->where('kind', $kind)->where('status', 'pending')->get()->each(fn (Payment $old) => $old->update([
            'status' => 'failed', 'failure_reason' => 'Superseded by a new payment attempt',
        ]));

        $payment = Payment::create([
            'service_request_id' => $request->id,
            'kind' => $kind,
            'customer_id' => $request->customer_id,
            'provider_id' => $providerId,
            'amount' => $amount,
            'method' => $data['method'],
            'payer_phone' => $data['payer_phone'] ?? null,
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'gateway' => $gateway->name(),
            'status' => 'pending',
        ]);

        if ($gateway->isOnline()) {
            try {
                $gateway->initiate($payment);
            } catch (GatewayException $e) {
                $payment->update(['status' => 'failed', 'failure_reason' => mb_substr($e->getMessage(), 0, 250)]);
                throw ValidationException::withMessages(['method' => $e->getMessage()]);
            }
        }

        return $payment->fresh();
    }

    /** The customer's payment for the job itself: to the provider (manual) or through the app (MTN / Airtel prompt). */
    public function recordPayment(ServiceRequest $request, array $data, ?User $actor): Payment
    {
        if (! $request->isCompleted() && ! in_array($request->status, [ServiceRequest::STATUS_IN_PROGRESS, ServiceRequest::STATUS_ARRIVED], true)) {
            throw ValidationException::withMessages(['method' => 'Payment can be recorded once the provider is on site.']);
        }
        if ($request->payment_status === 'paid') {
            throw ValidationException::withMessages(['method' => 'This job has already been paid.']);
        }
        if ($request->variations()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['method' => 'Please approve or decline the provider\'s extra-cost request first.']);
        }

        $payment = $this->createPayment($request, $data, 'job', (float) ($data['amount'] ?? $request->agreed_amount), $request->provider_id);
        $request->update(['payment_status' => 'pending']);

        if ($payment->isOnline()) {
            $this->log($request, 'payment_prompt_sent', $actor, $payment->methodLabel().' prompt for '.zmw($payment->amount).' sent to '.$payment->payer_phone);

            return $payment;
        }

        $this->log($request, 'payment_recorded', $actor, $payment->methodLabel().' payment of '.zmw($payment->amount).' recorded – awaiting confirmation');
        $this->notify->user($request->provider, 'Payment recorded – please confirm',
            'Customer recorded '.zmw($payment->amount)." via {$payment->methodLabel()} for {$request->reference}.",
            route('provider.jobs.show', $request), 'payment', true);

        return $payment;
    }

    /** The customer pays HANDY NOW's booking fee. Contact details unlock when it is confirmed. */
    public function recordBookingFee(ServiceRequest $request, array $data, ?User $actor): Payment
    {
        if (! $request->bookingFeePending()) {
            throw ValidationException::withMessages(['method' => 'There is no booking fee to pay on this request.']);
        }
        if (! in_array($data['method'], Payment::FEE_METHODS, true)) {
            throw ValidationException::withMessages(['method' => 'Pay the booking fee by mobile money or bank transfer.']);
        }

        $payment = $this->createPayment($request, $data, 'booking_fee', (float) $request->booking_fee_amount, null);

        if ($payment->isOnline()) {
            $this->log($request, 'booking_fee_prompt_sent', $actor, $payment->methodLabel().' prompt for the '.zmw($payment->amount).' booking fee sent to '.$payment->payer_phone);

            return $payment;
        }
        $this->log($request, 'booking_fee_recorded', $actor, $payment->methodLabel().' booking fee of '.zmw($payment->amount).' recorded – awaiting confirmation');
        $this->notify->admins('Booking fee to confirm', "{$request->reference}: ".zmw($payment->amount)." via {$payment->methodLabel()}".($payment->transaction_reference ? " (ref {$payment->transaction_reference})" : '').'.',
            route('admin.requests.show', $request), 'payment');

        return $payment;
    }

    /**
     * Asks the mobile money operator for the result of a prompt and settles the payment.
     * Used by the callback webhook, the customer's status polling and the payments:sync command.
     */
    public function syncPayment(Payment $payment): string
    {
        if ($payment->status !== 'pending' || ! $payment->isOnline()) {
            return $payment->status;
        }
        $result = $this->gateways->byName($payment->gateway)->checkStatus($payment);

        if ($result['status'] === PaymentGateway::SUCCESSFUL) {
            if ($result['reason'] && ! $payment->transaction_reference) {
                $payment->update(['transaction_reference' => $result['reason']]);
            }
            $this->confirmPayment($payment->fresh(), null);
            if (! $payment->isBookingFee()) {
                $this->notify->user($payment->provider, 'Payment received',
                    zmw($payment->amount).' paid via '.$payment->methodLabel().' for '.$payment->serviceRequest->reference.'.',
                    route('provider.jobs.show', $payment->serviceRequest), 'payment', true);
            }

            return 'confirmed';
        }
        if ($result['status'] === PaymentGateway::FAILED) {
            $payment->update(['status' => 'failed', 'failure_reason' => mb_substr((string) $result['reason'], 0, 250)]);
            if ($payment->isBookingFee()) {
                $this->log($payment->serviceRequest, 'booking_fee_failed', null, $payment->methodLabel().' booking fee not completed: '.$result['reason']);
            } else {
                $payment->serviceRequest->update(['payment_status' => 'unpaid']);
                $this->log($payment->serviceRequest, 'payment_failed', null, $payment->methodLabel().' payment not completed: '.$result['reason']);
            }

            return 'failed';
        }

        return 'pending';
    }

    /** Confirms receipt. Job payments also calculate the platform commission; $actor is null when the operator confirmed it. */
    public function confirmPayment(Payment $payment, ?User $actor): void
    {
        if ($payment->status === 'confirmed') {
            return;
        }
        if ($payment->isBookingFee()) {
            $this->confirmBookingFee($payment, $actor);

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
                'confirmed_by' => $actor?->id,
                'confirmed_at' => now(),
            ]);
            $request = $payment->serviceRequest;
            $request->update([
                'payment_status' => 'paid',
                'commission_rate' => $rate,
                'commission_amount' => $commission,
                'provider_earnings' => (float) $payment->amount - $commission,
            ]);
            $this->ledger->recordPayment($payment->fresh());
            $by = $actor ? '' : ' automatically by '.$payment->methodLabel();
            $this->log($request, 'payment_confirmed', $actor, zmw($payment->amount).' confirmed'.$by.'. Platform commission '.zmw($commission)." ({$rate}%)");
        });

        $this->customerUpdate($payment->serviceRequest, 'Payment of '.zmw($payment->amount).' for '.$payment->serviceRequest->reference.' confirmed. Thank you!');
    }

    private function confirmBookingFee(Payment $payment, ?User $actor): void
    {
        $request = $payment->serviceRequest;
        DB::transaction(function () use ($payment, $actor, $request) {
            $payment->update(['status' => 'confirmed', 'commission_rate' => 0, 'commission_amount' => 0, 'provider_amount' => 0,
                'confirmed_by' => $actor?->id, 'confirmed_at' => now()]);
            $request->update([
                'booking_fee_status' => $request->isCancelled() ? 'refund_due' : 'paid',
                'booking_fee_paid_at' => now(),
            ]);
            $this->log($request, 'booking_fee_paid', $actor, zmw($payment->amount).' booking fee confirmed'.($actor ? '' : ' automatically by '.$payment->methodLabel()));
        });

        if ($request->isCancelled()) {
            $this->notify->admins('Booking fee paid after cancellation', "{$request->reference} was cancelled before the fee was confirmed. Refund ".zmw($payment->amount).' to the customer.', route('admin.requests.show', $request), 'payment');

            return;
        }
        $this->notify->user($request->provider, 'Booking fee paid – details unlocked',
            "The customer for {$request->reference} has paid. Open the job to see their contact details and head out.",
            route('provider.jobs.show', $request), 'success', true);
        $this->customerUpdate($request, 'Booking fee of '.zmw($payment->amount)." received. {$request->provider->name}'s contact details are now available: ".$request->trackingUrl());
    }

    public function rejectPayment(Payment $payment, User $actor): void
    {
        $payment->update(['status' => 'failed', 'confirmed_by' => $actor->id]);
        $request = $payment->serviceRequest;
        if ($payment->isBookingFee()) {
            $this->log($request, 'booking_fee_rejected', $actor, 'Booking fee payment could not be verified');
            $this->customerUpdate($request, "Your booking fee payment for {$request->reference} could not be verified. Please check the reference and try again: ".$request->trackingUrl());

            return;
        }
        $request->update(['payment_status' => 'unpaid']);
        $this->log($request, 'payment_rejected', $actor, 'Payment could not be verified');
        $this->customerUpdate($request, "Payment for {$request->reference} could not be verified. Please check and try again.");
    }

    /** Admin goodwill: the customer does not have to pay the fee, and contact details unlock. */
    public function waiveBookingFee(ServiceRequest $request, User $admin): void
    {
        if (! $request->bookingFeePending()) {
            throw ValidationException::withMessages(['fee' => 'There is no unpaid booking fee on this request.']);
        }
        $request->payments()->where('kind', 'booking_fee')->where('status', 'pending')->update(['status' => 'failed', 'failure_reason' => 'Fee waived']);
        $request->update(['booking_fee_status' => 'waived']);
        $this->log($request, 'booking_fee_waived', $admin, 'Booking fee waived by HANDY NOW');
        $this->notify->user($request->provider, 'Details unlocked', "The booking fee for {$request->reference} was waived. Open the job to see the customer's details.", route('provider.jobs.show', $request), 'success', true);
        $this->customerUpdate($request, "The booking fee for {$request->reference} was waived. {$request->provider->name}'s details are now available: ".$request->trackingUrl());
    }

    /** The admin has sent the money back (mobile money / bank) and records it here. */
    public function markBookingFeeRefunded(ServiceRequest $request, User $admin): void
    {
        if ($request->booking_fee_status !== 'refund_due') {
            throw ValidationException::withMessages(['fee' => 'No booking fee refund is due on this request.']);
        }
        DB::transaction(function () use ($request, $admin) {
            $request->payments()->where('kind', 'booking_fee')->where('status', 'confirmed')->update(['status' => 'refunded']);
            $request->update(['booking_fee_status' => 'refunded']);
            $this->log($request, 'booking_fee_refunded', $admin, 'Booking fee of '.zmw($request->booking_fee_amount).' refunded to the customer');
        });
        $this->customerUpdate($request, 'Your booking fee of '.zmw($request->booking_fee_amount)." for {$request->reference} has been refunded.");
    }

    /** Extra cost found on site. Nothing is added to the price until the customer approves it. */
    public function requestVariation(ServiceRequest $request, User $provider, float $amount, string $reason): PriceVariation
    {
        if ($request->provider_id !== $provider->id) {
            abort(403);
        }
        if (! in_array($request->status, [ServiceRequest::STATUS_ARRIVED, ServiceRequest::STATUS_IN_PROGRESS], true)) {
            throw ValidationException::withMessages(['amount' => 'You can ask for extra costs once you have arrived and before the job is complete.']);
        }
        if ($request->payment_status !== 'unpaid') {
            throw ValidationException::withMessages(['amount' => 'The customer has already started paying for this job.']);
        }
        if ($request->variations()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['amount' => 'You already have a request waiting for the customer.']);
        }
        $added = (float) $request->variations()->where('status', 'approved')->sum('amount');
        $room = $this->pricing->variationRoom((float) $request->agreed_amount - $added, $added, $request->service);
        if ($amount > $room) {
            throw ValidationException::withMessages(['amount' => $room > 0
                ? 'The most you can add to this job is '.zmw($room).'.'
                : 'No more can be added to this job. Contact HANDY NOW support if it needs more work.']);
        }

        $variation = PriceVariation::create(['service_request_id' => $request->id, 'provider_id' => $provider->id, 'amount' => round($amount, 2), 'reason' => $reason, 'status' => 'pending']);
        $this->log($request, 'price_change_requested', $provider, 'Extra '.zmw($amount)." requested: {$reason}");
        $this->customerUpdate($request, "{$provider->name} asks to add ".zmw($amount)." to {$request->reference} ({$reason}). Approve or decline: ".$request->trackingUrl());

        return $variation;
    }

    public function decideVariation(PriceVariation $variation, bool $approve, ?User $actor): void
    {
        DB::transaction(function () use ($variation, $approve, $actor) {
            $variation = PriceVariation::whereKey($variation->id)->lockForUpdate()->first();
            $request = ServiceRequest::whereKey($variation->service_request_id)->lockForUpdate()->first();
            if ($variation->status !== 'pending') {
                throw ValidationException::withMessages(['variation' => 'This request has already been answered.']);
            }
            if ($approve && $request->payment_status !== 'unpaid') {
                throw ValidationException::withMessages(['variation' => 'Payment has already started, so the price can no longer change.']);
            }
            $variation->update(['status' => $approve ? 'approved' : 'declined', 'decided_by' => $actor?->id, 'decided_at' => now()]);
            if ($approve) {
                $request->update(['agreed_amount' => (float) $request->agreed_amount + (float) $variation->amount]);
            }
            $this->log($request, $approve ? 'price_change_approved' : 'price_change_declined', $actor,
                ($approve ? 'Customer approved extra ' : 'Customer declined extra ').zmw($variation->amount).($approve ? '. New price '.zmw($request->agreed_amount) : ''));
        });

        $variation->refresh();
        $this->notify->user($variation->provider, $approve ? 'Extra cost approved' : 'Extra cost declined',
            "{$variation->serviceRequest->reference}: the customer ".($approve ? 'approved' : 'declined').' the extra '.zmw($variation->amount).'.'
                .($approve ? ' New price '.zmw($variation->serviceRequest->agreed_amount).'.' : ' Continue at the agreed price.'),
            route('provider.jobs.show', $variation->serviceRequest), $approve ? 'success' : 'warning', true);
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
