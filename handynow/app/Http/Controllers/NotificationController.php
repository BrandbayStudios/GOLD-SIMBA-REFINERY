<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function open(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return redirect($n->data['url'] ?? route('notifications.index'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    public function count(Request $request)
    {
        $latest = $request->user()->unreadNotifications()->first();

        return response()->json([
            'unread' => $request->user()->unreadNotifications()->count(),
            'latest' => $latest ? ['id' => $latest->id, 'title' => $latest->data['title'] ?? '', 'body' => $latest->data['body'] ?? '', 'kind' => $latest->data['kind'] ?? 'info'] : null,
        ]);
    }
}
