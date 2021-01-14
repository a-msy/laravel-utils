<?php

namespace App\Services;

use App\User;
use App\Invitation;
use App\Mail\InvitationNoticeToAdmin;
use Illuminate\Support\Facades\Mail;

class RegisterService
{
    public function invitationProcess($invitation_code, $user)
    {
        $owner = User::where('invitation_code', $invitation_code)->first();
        Invitation::create([
            'owner_id' => $owner->id,
            'user_id' => $user->id
        ]);
        Mail::to(config('const.dev_admin_email'))->send(new InvitationNoticeToAdmin($user, $owner));
        return;
    }

    public function modifyRedirectToByFrom($redirectTo, $from)
    {
        return $redirectTo .= '?from=' . $from;
    }
}
