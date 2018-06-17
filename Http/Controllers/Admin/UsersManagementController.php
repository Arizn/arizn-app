<?php

namespace App\Http\Controllers\Admin;

use App\Models\Profile;
use App\Models\User;
use App\Traits\CaptureIpTrait;
use Auth;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;
use Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\URL;
use Yajra\Datatables\Datatables;

class UsersManagementController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::paginate(env('USER_LIST_PAGINATION_SIZE'));
        $roles = Role::all();

        return View('usersmanagement.show-users', compact('users', 'roles'));
    }
	
	 public function getdata()
    {
		$user = User::query();
        return Datatables::of($user)
		
		    ->escapeColumns([])
            ->editColumn('name', function ($user) {
               
                return '<a class="btn btn-sm btn-success btn-block" href="'.\URL::to('admin/users/' . $user->id).'" data-toggle="tooltip" title="Show">
							<i class="fa fa-eye fa-fw" aria-hidden="true"></i>
							<span class="hidden-xs hidden-sm hidden-md">'.$user->first_name.' '.$user->last_name.'</span>
                         </a>';

            })
            ->editColumn('email', function ($user) {

                if (\Auth::user()->email == 'demo@admin.com') {

                    return trans("admin.youPERMISSION");
                }

                return '<a href="mailto:'.$user->email.'" title="email '.$user->email.'">'.$user->email.'</a>';
            })


            ->addColumn('status', function ($user) {
				$return = "";
				foreach ($user->roles as $user_role):
					if ($user_role->name == 'User')
					$labelClass = 'primary';
					elseif ($user_role->name == 'Admin')
					$labelClass = 'warning' ;
					elseif ($user_role->name == 'Unverified')
					$labelClass = 'danger' ;
					else
					$labelClass = 'default' ;
					$return.='<span class="label label-'.$labelClass.'">'.$user_role->name.'</span>';
                    endforeach;
				return $return;

            })

            ->addColumn('edit', function ($user) {
				return 
				'<a class="btn btn-sm btn-info btn-block" href="'.\URL::to('admin/users/' . $user->id . '/edit') .'" data-toggle="tooltip" title="Edit">
				<i class="fa fa-pencil fa-fw" aria-hidden="true"></i> <span class="hidden-xs hidden-sm">Edit</span><span class="hidden-xs hidden-sm hidden-md"> User</span>
                                                </a>';
			})
			->addColumn('delete', function ($user) {
				return \Form::open(array('url' => 'admin/users/' . $user->id, 'class' => 'ajax_form', 'data-toggle' => 'tooltip','data-table'=>'users', 'title' => 'Delete'))
					  .\Form::hidden('_method', 'DELETE')
					  .\Form::button('<i class="fa fa-trash-o fa-fw" aria-hidden="true"></i> <span class="hidden-xs hidden-sm">Delete</span><span class="hidden-xs hidden-sm hidden-md"> User</span>', array('class' => 'btn btn-danger btn-sm','type' => 'button', 'style' =>'width: 100%;' ,'data-toggle' => 'modal', 'data-target' => '#confirmDelete', 'data-title' => 'Delete User', 'data-message' => 'Are you sure you want to delete this user ?'))
					  .\Form::close();
			})


            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $roles = Role::all();

        $data = [
            'roles' => $roles,
        ];

        return view('usersmanagement.create-user')->with($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(),
            [
                'name'                  => 'required|max:255|unique:users',
                'first_name'            => '',
                'last_name'             => '',
                'email'                 => 'required|email|max:255|unique:users',
                'password'              => 'required|min:6|max:20|confirmed',
                'password_confirmation' => 'required|same:password',
                'role'                  => 'required',
            ],
            [
                'name.unique'         => trans('auth.userNameTaken'),
                'name.required'       => trans('auth.userNameRequired'),
                'first_name.required' => trans('auth.fNameRequired'),
                'last_name.required'  => trans('auth.lNameRequired'),
                'email.required'      => trans('auth.emailRequired'),
                'email.email'         => trans('auth.emailInvalid'),
                'password.required'   => trans('auth.passwordRequired'),
                'password.min'        => trans('auth.PasswordMin'),
                'password.max'        => trans('auth.PasswordMax'),
                'role.required'       => trans('auth.roleRequired'),
            ]
        );

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $ipAddress = new CaptureIpTrait();
        $profile = new Profile();

        $user = User::create([
            'name'             => $request->input('name'),
            'first_name'       => $request->input('first_name'),
            'last_name'        => $request->input('last_name'),
            'email'            => $request->input('email'),
            'password'         => bcrypt($request->input('password')),
            'token'            => str_random(64),
            'admin_ip_address' => $ipAddress->getClientIp(),
            'activated'        => 1,
        ]);

        $user->profile()->save($profile);
        $user->attachRole($request->input('role'));
        $user->save();

        return redirect('admin/users')->with('success', trans('usersmanagement.createSuccess'));
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find($id);

        return view('usersmanagement.show-user')->withUser($user);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
		
        $user = User::findOrFail($id);
        $roles = Role::all();

        foreach ($user->roles as $user_role) {
            $currentRole = $user_role;
        }

        $data = [
            'user'        => $user,
            'roles'       => $roles,
            'currentRole' => $currentRole,
        ];


        return view('usersmanagement.edit-user')->with($data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
		if(env('DEMO',true)){
				  return back()->with('error', trans('admin.demomode'));
		}
        $currentUser = Auth::user();
        $user = User::find($id);
        $emailCheck = ($request->input('email') != '') && ($request->input('email') != $user->email);
        $ipAddress = new CaptureIpTrait();

        if ($emailCheck) {
            $validator = Validator::make($request->all(), [
                'name'     => 'required|max:255',
                'email'    => 'email|max:255|unique:users',
                'password' => 'present|confirmed|min:6',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'name'     => 'required|max:255',
                'password' => 'nullable|confirmed|min:6',
            ]);
        }

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user->name = $request->input('name');
        $user->first_name = $request->input('first_name');
        $user->last_name = $request->input('last_name');

        if ($emailCheck) {
            $user->email = $request->input('email');
        }

        if ($request->input('password') != null) {
            $user->password = bcrypt($request->input('password'));
        }

        $user->detachAllRoles();
        $user->attachRole($request->input('role'));
        $user->updated_ip_address = $ipAddress->getClientIp();
        $user->save();

        return back()->with('success', trans('usersmanagement.updateSuccess'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
		
        $currentUser = Auth::user();
        $user = User::findOrFail($id);
        $ipAddress = new CaptureIpTrait();

        if ($user->id != $currentUser->id) {
            $user->deleted_ip_address = $ipAddress->getClientIp();
            $user->save();
            $user->delete();
			return response()->json(['status' => 'SUCCESS','message' => trans('usersmanagement.deleteSuccess')]);

            
        }
		return response()->json(['status' => 'ERROR','message' => trans('usersmanagement.deleteSelfError')]);

    }
}
