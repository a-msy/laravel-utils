<?php

namespace App\Services;

use App\Mail\Register;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Ramsey\Uuid\Uuid;
use App\User;
use Laravel\Socialite\Facades\Socialite;
use App\Providers\RouteServiceProvider;
use Laravel\Socialite\Two\FacebookProvider;

class FacebookService
{
    protected $redirectToRegi = '/registered';
    protected $emailExist = false;

    public function returnDefaultRedirect($request)
    {
        if (empty($request->redirect)) {
            return RouteServiceProvider::HOME;
        } else {
            Session::put('registered_back', $request->redirect);
            return $request->redirect;
        }
    }
    public function getSocialData($social)
    {
        return Socialite::driver($social)->stateless()->user();
    }
    public function getSocialDataLogin($social)
    {
        return Socialite::extend($social, fn ($app) => Socialite::buildProvider(FacebookProvider::class, config('services.facebook_login')))->driver($social)->stateless()->user();
    }
    public function getSocialDataLink($social)
    {
        return Socialite::extend($social, fn ($app) => Socialite::buildProvider(FacebookProvider::class, config('services.facebook_link')))->driver($social)->stateless()->user();
    }

    public function getAvatarUrl($socialData)
    {
        $client = new \GuzzleHttp\Client();
        $response = json_decode($client->request("GET", $socialData->avatar_original . "&width=500&redirect=false&access_token=" . $socialData->token)->getBody());
        return $response->data->url;
    }
    public function makeAvatar(User $user, $socialData){
        if(empty($user->photo)){
            $path = 'public/profile_images/userPhoto/'.$user->facebook_id.'.jpg';
            Storage::put($path, Image::make($this->getAvatarUrl($socialData))->resize(null, 200, function ($constraint) {
                $constraint->aspectRatio();
            })->crop(200, 200)->stream('png'));
            $user->photo = $path;
            $user->save();
        }
    }

    public function getUserDataFromSocialEmail($socialEmail)
    {
        $user = User::where(['email' => $socialEmail])->first();
        return $user;
    }

    public function getUserDataFromSocialId($socialId)
    {
        $user = User::where(['facebook_id' => $socialId])->first();
        return $user;
    }

    public function getUserDataFromSocial($socialData)
    {
        $userDataFromEmail = $this->getUserDataFromSocialEmail($socialData->getEmail());
        $userDataFromId = $this->getUserDataFromSocialId($socialData->getId());
        if ($userDataFromId) {
            return $userDataFromId;
        } elseif ($userDataFromEmail) {
            $this->emailExist = true;
            return $userDataFromEmail;
        } else {
            return false;
        }
    }

    public function returnLoginRedirectURL($redirectTo)
    {
        if (Session::has('registered_back')) {
            return Session::get('registered_back');
        } else {
            return $redirectTo;
        }
    }

    public function returnLoginRedirect($userData, $redirectTo)
    {
        if ($userData) {
            if ($this->emailExist) {
                return redirect(route('login'))->with('error', "一度メールアドレスでログイン後、プロフィール編集よりFacebook連携を行ってください。");
            } else {
                Auth::login($userData);
                return redirect($this->returnLoginRedirectURL($redirectTo))->with('success', 'Facebookでログインしました');
            }
        } else {
            return redirect(route('register'))->with('error', "会員登録されていません。新規登録をよろしくお願いします！");
        }
    }

    public function createNewUser($socialData)
    {
        $newuser = new User();
        $newuser->name = $socialData->getName();
        $newuser->email = $socialData->getEmail();
        $newuser->facebook_email = $socialData->getEmail();
        $newuser->facebook_id = $socialData->getId();
        $newuser->invitation_code = Uuid::uuid4();
        $newuser->last_login_at = Carbon::now()->format("Y-m-d H:i:s");
        if (Session::has('school_name')) {
            $newuser->school_name = Session::get('school_name');
        }
        $newuser->save();
        $this->makeAvatar($newuser, $socialData);
        return $newuser;
    }

    public function returnRegisterRedirectURL($socialData)
    {
        $registerService = new RegisterService();

        $newuser = $this->createNewUser($socialData);

        Mail::to($newuser->email)->send(new Register($newuser->name, $newuser->email, $password = NULL));

        if (Session::has('from')) {
            $tmpRedirect = $this->redirectToRegi;
            $this->redirectToRegi = $this->registerService->modifyRedirectToByFrom($tmpRedirect, Session::get('from'));
        }
        if (Session::has('invitation_code')) {
            $registerService->invitationProcess(Session::get('invitation_code'), $newuser);
        }

        //そのままログイン
        Auth::login($newuser);
        return $this->redirectToRegi;
    }
    public function returnRegisterRedirect($userData, $socialData)
    {
        if(empty($socialData->getEmail())){
            return redirect(route('register'))->with('error', "Facebookに登録されているメールアドレスがありません。\n一度Facebookへメールアドレスをご登録いただくか、メアドでの登録をしてください。");
        }
        else if ($userData) {
            return redirect(route('login'))->with('error', "Facebookに登録されているメールアドレスで既にCOMPUSに登録されています。\nプロフィール編集よりFacebook連携を行ってください。");
        } else {
            //なければ登録（初回）
            return redirect($this->returnRegisterRedirectURL($socialData))->with('success', 'Facebookで新規登録しました');
        }
    }

    public function returnLinkRedirect(User $logineduser, $socialData)
    {
        // facebook ID mailを取得し保存．
        $logineduser->facebook_id = $socialData->getId();
        $logineduser->facebook_email = $socialData->getEmail();
        $logineduser->save();

        // 写真がなければ取得して保存
        $this->makeAvatar($logineduser, $socialData);
        // editページに成功リダイレクト
        return redirect(route('user.users.edit',['user'=>$logineduser->id]))->with('success','facebook連携が完了しました');
    }
}
