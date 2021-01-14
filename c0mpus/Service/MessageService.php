<?php


namespace App\Services;


use App\AdminCompanyMessage;
use App\AdminUserMessage;
use App\Apply;
use App\Message;
use App\Intern;
use App\User;
use App\Utils\KeyGroup;
use App\Utils\UserCheck;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MessageService
{

    function getMessagingCompanies($userId): Collection
    {
        return Message::where('user_id', $userId)->with('company')->get()->unique(function ($it) {
            return $it->company_id;
        })->map(function ($it) {
            return $it->company;
        });
    }

    function getMessagingUsers($company_id): Collection
    {
        return Message::where('company_id', $company_id)->get()->unique(function ($it) {
            return $it->user_id;
        })->map(function ($it) {
            return $it->user;
        });
    }

    function getMessages($userId, $companyId): Collection
    {
        return Message::where('user_id', $userId)->where('company_id', $companyId)->get();
    }

    function getAllMessages(): LengthAwarePaginator
    {
        return Message::paginate(50);
    }

    function getAllMessagesGroupByCompany(){
        $messages = Message::with('company')->with('user')->get()->toArray();
        return KeyGroup::array_group_by($messages,"company_id");
    }

    function sendMessage($userId, $companyId, $message, $from): Message
    {
        UserCheck::isDeleted(User::find($userId));

        return Message::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'from' => $from,
            'message' => $message,
        ]);
    }

    function sendAdminMessageFromUser($userId, $message): AdminUserMessage
    {
        UserCheck::isDeleted(User::find($userId));

        return AdminUserMessage::create([
            'user_id' => $userId,
            'from' => 'user',
            'message' => $message,
        ]);
    }

    function sendAdminMessageFromCompany($companyId, $message): AdminCompanyMessage
    {
        return AdminCompanyMessage::create([
            'company_id' => $companyId,
            'from' => 'company',
            'message' => $message,
        ]);
    }

    function sendAdminMessageToUser($userId, $message): AdminUserMessage
    {
        return AdminUserMessage::create([
            'user_id' => $userId,
            'from' => 'admin',
            'message' => $message,
        ]);
    }

    function sendAdminMessageToCompany($companyId, $message): AdminCompanyMessage
    {
        return AdminCompanyMessage::create([
            'company_id' => $companyId,
            'from' => 'admin',
            'message' => $message,
        ]);
    }

    function getAdminMessagesToUser($userId): Collection
    {
        return AdminUserMessage::where('user_id', $userId)->get();
    }

    function getAdminMessagesToCompany($companyId): Collection
    {
        return AdminCompanyMessage::where('company_id', $companyId)->get();
    }

    function isExistAdminMessageToUser($userId): bool
    {
        return AdminUserMessage::where('user_id', $userId)->get()->isNotEmpty();
    }

    function isExistAdminMessageToCompany($companyId): bool
    {
        return AdminCompanyMessage::where('company_id', $companyId)->get()->isNotEmpty();
    }

    function markAsRead($userId, $companyId, $from)
    {
        DB::table('messages')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('from', $from)
            ->update(['is_read' => 1]);
    }

    function getUnReadCountMessage($userId, $companyId, $from)
    {
        return DB::table('messages')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('from', $from)
            ->where('is_read', 0)
            ->count();
    }

    function markAsReadAdminUserMessage($userId, $from)
    {
        DB::table('admin_user_messages')
            ->where('from', $from)
            ->where('user_id', $userId)
            ->update(['is_read' => 1]);
    }

    function markAsReadAdminCompanyMessage($companyId, $from)
    {
        DB::table('admin_company_messages')
            ->where('from', $from)
            ->where('company_id', $companyId)
            ->update(['is_read' => 1]);
    }

    function getUnReadCountAdminMessage($userId, $companyId, $from)
    {
        if($userId == -1) $table = 'admin_company_messages';
        else $table = 'admin_user_messages';
        return DB::table($table)
            ->where('from', $from)
            ->where(function ($query) use ($userId, $companyId) {
                if($userId == -1){
                    $query->where('company_id', $companyId);
                }else{
                    $query->where('user_id', $userId);
                }
            })
            ->where('is_read', 0)
            ->count();
    }

    function getLastMessage($user_id,$company_id){
        return Message::select('updated_at')
            ->where('company_id',$company_id)
            ->where('user_id',$user_id)
            ->orderBy('updated_at','desc')
            ->first()->updated_at->format('Y/m/d H:i');
    }

    function getOpenMessage($user_id,$company_id){
        return Message::select('created_at')
            ->where('company_id',$company_id)
            ->where('user_id',$user_id)
            ->orderBy('created_at','asc')
            ->first()->created_at->format('Y/m/d H:i');
    }

    function validateCompany($user_id,$company_id){
        $flag = false;
        $applies = Apply::where('user_id',$user_id)->where('can_message',1)->with('intern')->get();
        foreach ($applies as $apply){
            if($apply->intern->company->id == $company_id){
                $flag = true;
                break;
            }
        }
        return $flag;
    }
    function validateUser($user_id,$company_id){
        $flag = false;
        $applies = Apply::whereIn('intern_id',Intern::where('company_id',$company_id)->get()->pluck('id')->toArray())
            ->where('can_message',1)
            ->with('user')->get();
        foreach ($applies as $apply){
            if($apply->user_id == $user_id){
                $flag = true;
                break;
            }
        }
        return $flag;
    }
}
