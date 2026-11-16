<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Services\Notifier;
use Illuminate\Http\Request;

/** In-platform chat between the customer and the assigned provider. */
class RequestMessageController extends Controller
{
    public function index(Request $request, ServiceRequest $serviceRequest)
    {
        $this->authorizeParticipant($request, $serviceRequest);
        $serviceRequest->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => $serviceRequest->messages()->with('sender:id,name,role')->get()->map(fn ($m) => [
            'id' => $m->id,
            'mine' => $m->sender_id === $request->user()->id,
            'sender' => $m->sender->name,
            'body' => $m->body,
            'time' => $m->created_at->format('d M, H:i'),
        ])]);
    }

    public function store(Request $request, ServiceRequest $serviceRequest, Notifier $notify)
    {
        $this->authorizeParticipant($request, $serviceRequest);
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $serviceRequest->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);

        $me = $request->user();
        $other = $me->id === $serviceRequest->provider_id ? $serviceRequest->customer : $serviceRequest->provider;
        if ($other) {
            $url = $other->isProvider() ? route('provider.jobs.show', $serviceRequest) : route('customer.requests.show', $serviceRequest);
            $notify->user($other, 'New message from '.$me->name, mb_strimwidth($data['body'], 0, 120, '…'), $url, 'message');
        }

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Message sent.');
    }

    private function authorizeParticipant(Request $request, ServiceRequest $sr): void
    {
        $id = $request->user()->id;
        abort_unless($sr->provider_id && ($id === $sr->customer_id || $id === $sr->provider_id || $request->user()->isAdmin()), 403);
    }
}
