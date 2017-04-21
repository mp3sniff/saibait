<?php

namespace App\Http\Controllers\Account;

use App\Lang;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class TransferCreditsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth:api']);
    }

    public function index()
    {
        $db_settings = \App\SiteSettings::findorfail(1);

        if (auth()->user()->cannot('update-account') || !auth()->user()->isAdmin() && !$db_settings->settings['enable_panel_login']) {
            return response()->json([
                'message' => 'Logged out.',
            ], 401);
        }
        
        $permission['is_admin'] = auth()->user()->isAdmin();
        $permission['manage_user'] = auth()->user()->can('manage-user');

        $site_options['site_name'] = $db_settings->settings['site_name'];
        $site_options['sub_name'] = 'Transfer Credits';
        $site_options['enable_panel_login'] = $db_settings->settings['enable_panel_login'];

        $language = Lang::all();

        if(!auth()->user()->can('manage-user')) {
            return response()->json([
                'message' => 'No permission to access this page.',
                'profile' => ['username' => auth()->user()->username, 'credits' => auth()->user()->credits],
                'language' => $language,
                'permission' => $permission,
            ], 403);
        }

        if(!auth()->user()->isAdmin() && !auth()->user()->distributor) {
            return response()->json([
                'message' => 'No permission to access this page.',
                'profile' => ['username' => auth()->user()->username, 'credits' => auth()->user()->credits],
                'language' => $language,
                'permission' => $permission,
            ], 403);
        }

        return response()->json([
            'site_options' => $site_options,
            'profile' => ['username' => auth()->user()->username, 'credits' => auth()->user()->credits, 'distributor' => auth()->user()->distributor],
            'language' => $language,
            'permission' => $permission
        ], 200);
    }

    public function transfer(Request $request)
    {
        $db_settings = \App\SiteSettings::findorfail(1);

        if (auth()->user()->cannot('update-account') || !auth()->user()->isAdmin() && !$db_settings->settings['enable_panel_login']) {
            return response()->json([
                'message' => 'Logged out.',
            ], 401);
        }

        if(!auth()->user()->distributor && Gate::denies('manage_user')) {
            return response()->json([
                'message' => 'Action not allowed.',
            ], 403);
        }

        if (!auth()->user()->isAdmin() && auth()->user()->credits < $request->credits) {
            return response()->json([
                'message' => 'Input must be lower or equal to your available credits.',
            ], 403);
        }

        try {
            $user = User::where('username', $request->username)->firstorfail();

            $this->validate($request, [
                'username' => 'required',
                'credits' => 'bail|required|integer|between:1,' . $db_settings->settings['max_transfer_credits'],
            ]);

            if($user->isAdmin() || auth()->user()->username == $user->username) {
                return response()->json([
                    'message' => 'Action not allowed.',
                ], 403);
            }
            DB::transaction(function () use ($request) {
                $user = User::where('username', $request->username)->firstorfail();
                if (!auth()->user()->isAdmin()) {
                    $request->user()->credits -= $request->credits;
                    $request->user()->save();
                }
                $user->credits += $request->credits;
                $user->save();
            }, 5);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            return response()->json([
                'username' => ['User not found.'],
            ], 422);
        }

        $withs = $request->credits > 1 ? ' credits' : ' credit';
        return response()->json([
            'message' => 'You have transferred ' . $request->credits . $withs . ' to ' . $request->username . '.',
            'profile' => ['credits' => auth()->user()->credits],
        ], 200);

    }
}
