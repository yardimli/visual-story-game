<?php

namespace App\Services;

use App\Models\User;

class ChatHistory
{
    public function snapshot($conversation, User $user): array
    {
        // A terminated PHP process cannot report its own failure. Do not leave
        // the editor waiting forever, and never retry a billable request here.
        (clone $conversation)->where('response_status', 'running')
            ->where('created_at', '<', now()->subMinutes(5))
            ->update(['response_status' => 'failed', 'response_error' =>
                'The request stopped before a complete answer was saved. Check AI history before sending again; no automatic retry was made.']);

        $messages = (clone $conversation)->latest('id')->limit(40)->get()->reverse()->values();

        return [
            'messages' => $messages,
            'pending' => $messages->where('response_status', 'running')->map(fn ($message) => [
                'id' => $message->id,
                'content' => $message->response_partial ?? '',
            ])->values(),
            'remaining' => $user->fresh()->availableCredit() / 1000000,
        ];
    }
}
