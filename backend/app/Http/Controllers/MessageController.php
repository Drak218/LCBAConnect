<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Get all messages for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            // Get conversations list
            $conversations = Message::where('sender_id', $userId)
                ->orWhere('receiver_id', $userId)
                ->with(['sender:id,first_name,last_name,email,profile_picture', 'receiver:id,first_name,last_name,email,profile_picture'])
                ->orderBy('sent_at', 'desc')
                ->get()
                ->groupBy(function($message) use ($userId) {
                    return $message->sender_id == $userId 
                        ? $message->receiver_id 
                        : $message->sender_id;
                })
                ->map(function($messages, $otherUserId) use ($userId) {
                    $otherUser = User::find($otherUserId);
                    $lastMessage = $messages->first();
                    $unreadCount = $messages->where('receiver_id', $userId)
                                           ->where('is_read', false)
                                           ->count();
                    
                    return [
                        'id' => $otherUserId,
                        'other_user' => [
                            'id' => $otherUser->id,
                            'first_name' => $otherUser->first_name,
                            'last_name' => $otherUser->last_name,
                            'email' => $otherUser->email,
                            'profile_picture' => $otherUser->profile_picture,
                        ],
                        'last_message' => [
                            'content' => $lastMessage->content,
                            'sent_at' => $lastMessage->sent_at,
                            'is_read' => $lastMessage->is_read,
                        ],
                        'unread_count' => $unreadCount,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $conversations
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Messages API Index Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages with a specific user
     */
    public function getConversation($otherUserId): JsonResponse
    {
        try {
            $userId = Auth::id();

            $messages = Message::where(function($query) use ($userId, $otherUserId) {
                $query->where('sender_id', $userId)
                      ->where('receiver_id', $otherUserId);
            })->orWhere(function($query) use ($userId, $otherUserId) {
                $query->where('sender_id', $otherUserId)
                      ->where('receiver_id', $userId);
            })
            ->with(['sender:id,first_name,last_name,email,profile_picture', 'receiver:id,first_name,last_name,email,profile_picture'])
            ->orderBy('sent_at', 'asc')
            ->orderBy('id', 'asc') // Secondary sort by ID for tie-breaking
            ->get();

            // Mark messages as read
            Message::where('sender_id', $otherUserId)
                   ->where('receiver_id', $userId)
                   ->where('is_read', false)
                   ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a message
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'content' => 'required|string',
            ]);

            $message = Message::create([
                'sender_id' => Auth::id(),
                'receiver_id' => $validated['receiver_id'],
                'content' => $validated['content'],
                'is_read' => false,
            ]);

            return response()->json([
                'success' => true,
                'data' => $message,
                'message' => 'Message sent successfully'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Messages API Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available users for new message
     */
    public function getAvailableUsers(): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $users = User::where('id', '!=', $userId)
                ->where('role', 'alumni')
                ->select('id', 'first_name', 'last_name', 'email', 'headline', 'program', 'batch')
                ->orderBy('first_name')
                ->get()
                ->map(function($user) {
                    return [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'full_name' => $user->first_name . ' ' . $user->last_name,
                        'email' => $user->email,
                        'headline' => $user->headline,
                        'program' => $user->program,
                        'batch' => $user->batch,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Messages API Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Poll for new messages since timestamp
     */
    public function pollMessages(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $since = $request->query('since');
            $otherUserId = $request->query('other_user_id');

            if (!$since || !$otherUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters'
                ], 400);
            }

            // Get new messages in this conversation
            $messages = Message::where(function($query) use ($userId, $otherUserId) {
                $query->where('sender_id', $userId)
                      ->where('receiver_id', $otherUserId);
            })->orWhere(function($query) use ($userId, $otherUserId) {
                $query->where('sender_id', $otherUserId)
                      ->where('receiver_id', $userId);
            })
            ->where('sent_at', '>', $since)
            ->with(['sender:id,first_name,last_name,email', 'receiver:id,first_name,last_name,email'])
            ->orderBy('sent_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

            // Mark new messages from other user as read
            Message::where('sender_id', $otherUserId)
                   ->where('receiver_id', $userId)
                   ->where('is_read', false)
                   ->update(['is_read' => true]);

            return response()->json([
                'success' => true,
                'data' => $messages
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Messages API Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unread message count
     */
    public function getUnreadCount(): JsonResponse
    {
        try {
            $userId = Auth::id();
            
            $unreadCount = Message::where('receiver_id', $userId)
                                 ->where('is_read', false)
                                 ->count();

            return response()->json([
                'success' => true,
                'data' => ['unread_count' => $unreadCount]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Messages API Error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
