<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Models\Rate;
use Illuminate\Http\Request;

class RatesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $rates = Rate::where('src_id', 'LIKE', "%$keyword%")
                ->orWhere('dst_id', 'LIKE', "%$keyword%")
                ->orWhere('pair_id', 'LIKE', "%$keyword%")
                ->orWhere('rate', 'LIKE', "%$keyword%")
                ->orWhere('fees', 'LIKE', "%$keyword%")
                ->orWhere('minimum', 'LIKE', "%$keyword%")
                ->orWhere('maximum', 'LIKE', "%$keyword%")
                ->orWhere('message', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $rates = Rate::latest()->paginate($perPage);
        }

        return view('_admin.rates.index', compact('rates'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('_admin.rates.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
		if(env('DEMO')){
			return response()->json(['status' => 'SUCCESS','message' => 'Saving Disables in Demo Mode']);
		}
        $this->validate($request, [
			'src_id' => 'required|numeric',
			'dst_id' => 'required|numeric',
			'src_type' => 'required',
			'pair_id' => 'unique:rates,pair_id',
			'dst_type' => 'required',
			'rate' => 'numeric',
			'fromsym' => 'required',
			'tosym' => 'required',
			'fees' => 'required|numeric|max:100',
			'minimum' => 'required|numeric',
			'maximum' => 'required|numeric'
		]);
        $requestData = $request->all();
        $rate = Rate::create($requestData);
		$rate->pair_id = $request->pair_id;
		$rate->autoupdate = isset($request->autoupdate)?1:0;
		$rate->autocomplete = isset($request->autocomplete)?1:0;
		$rate->save();
		return response()->json(['status' => 'SUCCESS','message' => 'Rate Saved Successfully']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $rate = Rate::findOrFail($id);

        return view('_admin.rates.show', compact('rate'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $rate = Rate::findOrFail($id);

        return view('_admin.rates.edit', compact('rate'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $id)
    {
		if(env('DEMO')){
			return response()->json(['status' => 'SUCCESS','message' => 'Saving Disables in Demo Mode']);
			}
        $this->validate($request, [
			'src_id' => 'required|numeric',
			'dst_id' => 'required|numeric',
			'src_type' => 'required',
			'pair_id' => 'required',
			'dst_type' => 'required',
			'rate' => 'numeric',
			'fees' => 'required|numeric',
			'minimum' => 'required|numeric',
			'maximum' => 'required|numeric'
		]);
        $requestData = $request->all();
        
        $rate = Rate::findOrFail($id);
        $rate->update($requestData);
		$rate->pair_id = $request->pair_id;
		$rate->autoupdate = isset($request->autoupdate)?1:0;
		$rate->autocomplete = isset($request->autocomplete)?1:0;
		/*$syms = explode('_',$rate->pair_id);
		$rate->fromsym = strtoupper($syms[0]);
		$rate->tosym = strtoupper($syms[1]);*/
		$rate->save();
        return response()->json(['status' => 'SUCCESS','message' => 'Rate Saved Successfully']);
    
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
		if(env('DEMO')){
			return response()->json(['status' => 'SUCCESS','message' => 'Saving Disables in Demo Mode']);}
        Rate::destroy($id);
		return response()->json(["URL"=>'/admin/rates','status' => 'SUCCESS','message' => 'Rate Deleted Successfully']);
       
    }
	
	
	
    /**
     * toogle status
     *
     * @param  int  $id
     *
     * @return Response
	 */
    public function toggle($id)
    {
		if(env('DEMO')){
			return response()->json(['status' => 'SUCCESS','message' => 'Saving Disables in Demo Mode']);}
        $rate = Rate::find($id);
		$rate->active = $rate->active == 1?0:1;
		$msg= $rate->active == 1?'Activate':'Deactivated';
		$rate->save();
		return response()->json(["URL"=>route('rates.index'),'status' => 'SUCCESS','message' => 'Rate '.$msg.' Successfully']);
       
    }
	
	
	
	
	 /**
     * toogle Auto Update
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function autoupdate($id)
    {
		if(env('DEMO')){
			return response()->json(['status' => 'SUCCESS','message' => 'Saving Disables in Demo Mode']);}
        $rate = Rate::find($id);
		$rate->autoupdate = $rate->autoupdate == 1?0:1;
		$msg= $rate->autoupdate == 1?'Auto Update Activated':' Auto Update Deactivated';
		$rate->save();
		return response()->json(["URL"=>route('rates.index'),'status' => 'SUCCESS','message' => 'Rate '.$msg.' Successfully']);
       
    }
	
	
	 /**
     * toogle Auto Complete
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function autocomplete($id)
    {
		if(env('DEMO')){
			return response()->json(['status' => 'SUCCESS','message' => 'Saving Disables in Demo Mode']);
		}
        $rate = Rate::find($id);
		$rate->autocomplete = $rate->autocomplete == 1?0:1;
		$msg= $rate->autocomplete == 1?'Auto Complete Activated':' Auto Complete Deactivated';
		$rate->save();
		return response()->json(["URL"=>route('rates.index'),'status' => 'SUCCESS','message' => 'Rate '.$msg.' Successfully']);
       
    }
}
