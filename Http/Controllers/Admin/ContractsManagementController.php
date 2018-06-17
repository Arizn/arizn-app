<?php

namespace App\Http\Controllers\Admin;

use App\Models\Profile;
use App\Models\Contract;
use App\Models\Transaction;
use App\Models\Order;
use Auth;
use Illuminate\Http\Request;
use jeremykenedy\LaravelRoles\Models\Role;
use Validator;
use Illuminate\Support\Facades\Form;
use Illuminate\Support\Facades\URL;
use Yajra\Datatables\Datatables;

class ContractsManagementController extends \App\Http\Controllers\Controller
{
	/**
     * construct.
     *
     * @return \Illuminate\Http\Response
     */
	public function __construct(){
		$this->middleware('auth');
	}
	
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
		return View('_admin.contracts.list');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
		return view('_admin.contracts.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
        $request->validate([
			'name' => 'required|unique:contracts',
			'abi' => 'required',
			'bin' => 'required',
			'buy_tokens_function' => 'required',
			'deploy_price' => 'required',
			'contract' => 'required|unique:contracts',
			'contract'=>'required'
        ]);
		
        $contract = Contract::create([
			'name' => $request->input('name'),
			'bin' => $request->input('bin'),
			'abi' => $request->input('abi'),
			'deploy_price' => $request->input('deploy_price'),
			'admin_functions' => $request->input('admin_functions',NULL),
			'buy_tokens_function' => $request->input('buy_tokens_function'),
			'contract' => $request->input('contract'),
			'description' => $request->input('description'),	
        ]);
        $contract->save();
		return response()->json(['status' => 'SUCCESS','message' => 'Contract Saved Successfully']);
    
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
		$contract = Contract::find($id);
        return view('_admin.contracts.show')->with(['contract'=>$contract]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
		$contract = Contract::findOrFail($id);
        return view('_admin.contracts.edit')->with(['contract'=>$contract]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
		$contract = Contract::find($id);
		$request->validate([
			'name' => 'required',
			'abi' => 'required',
			'bin' => 'required',
			'deploy_price' => 'required',
			'contract' => 'required',
			'contract'=>'required'
        ]);
        $contract->name = $request->input('name');
		$contract->bin = $request->input('bin');
		$contract->abi = $request->input('abi');
		$contract->buy_tokens_function = $request->input('buy_tokens_function',NULL);
		$contract->admin_functions = $request->input('admin_functions');
		$contract->deploy_price = $request->input('deploy_price');
		$contract->contract = $request->input('contract');
        $contract->description = $request->input('description','');
        $contract->save();
        return response()->json(['status' => 'SUCCESS','message' => 'Contract Saved Successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
		$contract =Contract::findOrFail($id);
        $contract->delete();
		return response()->json(['status' => 'SUCCESS','message' => 'Token Deleted Successfully']);
    }
	
	public function toggleActivate($id)
    {
        $contract =Contract::findOrFail($id);
        $contract->active = $contract->active==1?0:1;
		$contract->save();
		$action= $contract->active==1?'Activated':'Deactivated';
        return response()->json(['status' => 'SUCCESS','message' => 'Token '.$action.' Successfully']);
    }
	
	
	
	public function getdata()
    {
		$contract =Contract::query();
        return Datatables::of($contract)
		    ->escapeColumns([]) 
			->editColumn('name', function($contract){
				return '<a href="'.route('contracts.show',$contract->id).'">'.$contract->name.'</a>';
			})
			->editColumn('actions', function($contract){
				return '<a class="btn btn-success btn-sm" href="'.route('contracts.edit',$contract->id).'"><i class="fa fa-edit"></i> Edit</a>'.
					   '&nbsp;&nbsp;<a table="contracts" data-confirm="Do you really want to delete this ?" class="btn btn-danger btn-sm ajax_link confirm" data-_method="DELETE" href="'.route('contracts.destroy',$contract->id).'"><i class="fa fa-times"></i>DELETE</a>';
			})
			->editColumn('deployed', function($contract){
				return $contract->tokens->count();
			})
			
			->toJson();
	}
	
	
}
