<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();

        $query = Notification::where('user_id', $userId)
            ->orderByDesc('id');

        if ($request->boolean('unread_only')) {
            $query->where('is_read', 0);
        }

        if ($request->filled('limit')) {
            $query->limit((int) $request->limit);
        }

        return $this->sendResponse($query->get());
    }

    public function unreadCount()
    {
        $count = Notification::where('user_id', Auth::id())
            ->where('is_read', 0)
            ->count();

        return $this->sendResponse(['count' => $count]);
    }

    public function markRead(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $updated = Notification::where('user_id', Auth::id())
            ->where('id', $request->id)
            ->update(['is_read' => 1]);

        if (!$updated) {
            return $this->sendError(['message' => 'Notification not found.'], 404);
        }

        return $this->sendResponse(['message' => 'Notification marked as read.']);
    }

    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        return $this->sendResponse(['message' => 'All notifications marked as read.']);
    }
}
