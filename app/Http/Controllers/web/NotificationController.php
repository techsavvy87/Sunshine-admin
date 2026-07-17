<?php

namespace App\Http\Controllers\web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    public function listByUser(Request $request)
    {
        $notifications = Notification::with('sender.profile')->where('user_id', Auth::id())
            ->where('is_read', false)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Fetched notifications successfully',
            'result' => $notifications
        ], 200);
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $search = $request->get('search');

        if (!empty($search)) {
            $notifications = Notification::with('sender.profile')->where('user_id', Auth::id())
                ->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%' . $search . '%')
                        ->orWhere('message', 'like', '%' . $search . '%');
                })
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } else {
            $notifications = Notification::with('sender.profile')->where('user_id', Auth::id())
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        return view('notifications.index', compact('notifications', 'search', 'perPage'));
    }

    public function delete(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:notifications,id'
        ]);

        $notification = Notification::findOrFail($request->notification_id);
        if (!$notification) {
            return redirect()->back()->with([
                'status' => 'fail',
                'message' => 'Notification not found.'
            ]);
        }

        $notification->delete();
        return redirect()->route('notifications')->with([
            'status' => 'success',
            'message' => 'Notification deleted successfully.'
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);

        if (!$notification) {
            return response()->json([
                'status' => false,
                'message' => 'Notification not found.'
            ], 200);
        }

        if ($notification->is_read) {
            $notification->is_read = false;
        } else {
            $notification->is_read = true;
        }
        $notification->save();

        return response()->json([
            'status' => true,
            'message' => 'Notification status updated successfully.',
            'result' => $notification
        ], 200);
    }

    public function markReadUser()
    {
        Notification::where('user_id', Auth::id())->where('is_read', false)->update(['is_read' => true]);

        return response()->json([
            'status' => true,
            'message' => 'All notifications marked as read successfully.'
        ], 200);
    }

    public function open($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$notification->is_read) {
            $notification->is_read = true;
            $notification->save();
        }

        $metadata = is_array($notification->metadata)
            ? $notification->metadata
            : (json_decode($notification->metadata ?? '[]', true) ?: []);

        if (in_array($notification->type, ['pet_questionnaire'], true) && !empty($metadata['pet_id'])) {
            return redirect()->route('edit-pet', [
                'id' => $metadata['pet_id'],
                'questionnaire_id' => $metadata['questionnaire_id'] ?? null,
            ]);
        }

        if (in_array($notification->type, ['pet_vaccination', 'pet_vaccine_expired', 'pet_vaccine_expiration_warning'], true) && !empty($metadata['pet_id'])) {
            return redirect()->route('edit-pet', [
                'id' => $metadata['pet_id'],
                'target' => 'vaccinations',
            ]);
        }

        if ($notification->type === 'customer_profile' && !empty($notification->sender_id)) {
            return redirect()->route('edit-customer', ['id' => $notification->sender_id]);
        }

        if (!empty($metadata['appointment_id'])) {
            return redirect()->route('appointment-dashboard', ['id' => $metadata['appointment_id']]);
        }

        return redirect()->route('notifications');
    }
}
