<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Validator;
use Gate;
use Auth as Door;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;
use App\Posts;
use App\Entrys;
use App\Order;
use App\User;
use App\Ticket;
use App\Mobilemoney;
use Carbon\Carbon;
class PaymentsController extends Controller
{
	public function __construct(){
        parent::__construct();
        $this->middleware('auth',['except' => ['poll', 'sms']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
   public function index(Request $request){
		 $inputs = $request->all();
		 try{
			$ticket = Entrys::findOrFail($inputs['entry_id']);				
		  }catch(ModelNotFoundException $e){
			  return response()->json(array('status'=> 'TICKET IS NOT ACTIVE'),  200);
		  }
		  try{
			$post = Posts::findOrFail($ticket->post_id);			
		  }catch(ModelNotFoundException $e){
			  return response()->json(array('status'=> 'EVENT IS NOT ACTIVE'),  200);
		  }
		  
		  if ($post->user_id === Auth::user()->id ) {
				return response()->json(array('status'=> 'Author Cant Buy Own item'),  200);
        	}
			if ( Auth::user()->usertype === 'Admin') {
				return response()->json(array('status'=> 'Admin Cant Buy items'),  200);
        	}
			
			if ( Auth::user()->balance < $post->price ) {
				if ( !isset($inputs['txid']) ) 
				return response()->json(array('status'=> 'Your Account Balance is Low'),  200);
				try{
					$mobilemoney = Mobilemoney::where(['txid'=>$inputs['txid'],'status'=>0])->firstOrFail();
					$mobilemoney->user_id = Auth::user()->id;
					$mobilemoney->status = 1;
					$mobilemoney->save(); // for tracking
				}catch(ModelNotFoundException $e){
					return response()->json(array('status'=> 'Transaction ID is Pending or Invalid Try Again in 2 minutes'),  200);
				}
				$user = User::findOrFail(Auth::user()->id);
				$user->balance += $mobilemoney->amount;
				$user->save(); 
        	}
			$user = User::findOrFail(Auth::user()->id); // get user again
			$order = new Order;
			// price now.
			$ticket_price = $ticket->price;
			if(!is_null($ticket->discount)){
				$time = \Carbon\Carbon::parse($post->start)->diffInSeconds(\Carbon\Carbon::parse($post->created_at));
				$now =  \Carbon\Carbon::parse($post->start)->diffInSeconds(\Carbon\Carbon::now());
				$add =  ($now/$time)* ($ticket->price - $ticket->discount);
				$tprice =  $ticket->price - $add;
				if($user->is_agent == 1){
					$fee = Mobilemoney::fees($tprice, 'send');
					$tprice -= $fee;
				}
				// just in case user tries to hack
				$ticket_price = $inputs['price'] < $tprice ? $tprice: $inputs['price']; 		
			}
			
			$order->amount = $ticket_price;
			$percent = (int)getCong('AuthorPercent');
			$order->author_amount = $percent/100*$ticket_price ;
			if($user->is_agent == 1){
				 $order->agent_id = Auth::user()->id;
				 $order->agent_amount = (int)getCong('AgentAmount');
				 $ticket_price-= $order->agent_amount;
				 $order->author_amount -= $order->agent_amount;
			 }
			if ( $user->balance >= $ticket_price ) {
				 $user->balance -= $ticket_price ;
				 $user->save();
				 $order->post_id = $post->id;
				 $order->author_id = $post->user_id;
				 $order->entry_id = $ticket->id;
				 $order->user_id = Auth::user()->id;
				 $order->status = 4;
				 $order->access = 0;
				 $order->save(); 
				 $tkt = new Ticket;
				 $tkt->ticket = $this->random_string('alnum',12);
				 $tkt->serial = $this->random_string('numeric',12);
				 $tkt->secret = $this->random_string('numeric',9);
				 $tkt->client_id = $order->user_id;
				 $tkt->user_id = $order->author_id;
				 $tkt->post_id = $order->post_id;
				 $tkt->entry_id = $order->entry_id;
				 if(!is_null($order->agent_id)){
					 $tkt->vendor_id = $order->agent_id;
				 }
				 $tkt->value = $ticket->price;
				 $tkt->paid = $ticket_price ;
				 $tkt->status = 0;
				 $tkt->email = $inputs['email'];
				 $tkt->phone = $inputs['phone'];
				 $tkt->name = $inputs['name'];
				 $order->ticket()->save($tkt);
				 $msg = str_replace(['[TKT]','[SRL]','[EVT]','[DT]','[SECRET]'],[$tkt->ticket,$tkt->serial,$post->title,$post->start,$tkt->secret],'TKT: [TKT] . SERIAL: [SRL] . EVENT: [EVT] . DATE:[DT] . SecretCode:[SECRET]');
		  		 Mobilemoney::send_sms($msg,$tkt->phone);
				 $kraft = new \Jenssegers\Optimus\Optimus(15485761, 1751008449, 836799276); 	
				 \Mail::send('emails.ticket', ['ticket' => $tkt, 'event' => $post ,'url'=>'tickets/'.$kraft->encode($tkt->id)], function ($message)use($tkt)
				{
					$message->subject('Hello '.$tkt->name.': Your Ticket Details');
					$message->from('sales@ticketsug.com', 'Tickets Uganda');
					$message->to($tkt->email);
					$message->replyTo('ofuzak@gmail.com');
				});
				 return response()->json(array('status'=> 'OK'),  200);
        	}
		 return response()->json(array('status'=> 'Your Balance of '.trans('index.currency').$user->balance.' is low. Try again'),  200);
	}
	
	function poll(Request $request)
	{
		if ( $request->get('task') === 'send')
		{
			
				$queue = \App\Smslog::where(['sync'=>1])->get();	
				if($queue->count() > 0){
					$msg = [];
					foreach ($queue as $sms){
						$msg[] = [
							"to" => $sms->to,
							"message" => $sms->message,
							"uuid" => $sms->msgid,
						];
						$sms->sync = 2;
						$sms->save();
					 }
					 
					 return response()->json(["payload"=>[
						"success"=>'true',
						"task"=>"send",
						"secret" => "4umccentre_number*#77o00",
						"messages"=>array_values($msg)]
					]);
				}
				
		}
	}
	
	
	public function sms(Request $request)
	{
		$error = NULL;
		$success = false;
		$from = $request->input('from');
	    if(is_null($from))
	    $error = 'The from variable was not set';
		$message = $request->input('message');
		if(is_null($message))
		$error = 'The message variable was not set';
		$secret = $request->input('secret');
		$sent_timestamp = $request->input('sent_timestamp');
		$sent_to = $request->input('sent_to');
		$message_id = $request->input('message_id');
		$device_id = $request->input('device_id');

		 
		if ((strlen($from) > 0) && (strlen($message) > 0) && (strlen($sent_timestamp) > 0 )&&(strlen($message_id) > 0))
		{
			/* The screte key. Make sure you enter
			 * that on SMSsync.
			 */
			 
			if ( ( $secret == '4umccentre_number*#77o00'))
			{
				$success = true;
				$mobilemoney = new Mobilemoney ;
				$mobilemoney->sender_id = $from;
				$mobilemoney->message=$message;
				$mobilemoney->in_timestamp=$sent_timestamp;
				$mobilemoney->message_id=$message_id;
				//$mobilemoney->smscentre=$sent_to;
				$mobilemoney->device_id=$device_id;
				$sms = str_replace(",", "", $message); // Airtel uses no decimal digits 
                preg_match_all('!\d+!', $sms, $match);
              
                $matches = $match[0];
				if($from == 'AirtelMoney'){
					 $mobilemoney->amount = $matches[0];
					 //$mobilemoney->from = $matches[1];
					 //$mobilemoney->balance = $matches[1];
					 $mobilemoney->txid = $matches[2];
				}
				if($from == 'MtnMobMoney'){
					 $mobilemoney->amount = $matches[0];
					 $mobilemoney->from = $matches[1];
					 //$mobilemoney->balance = $matches[3];
					 $mobilemoney->txid = $matches[4];
				}
				 
				$mobilemoney->save();
				
			} 
			else
			{
				$error = "The secret value sent from the device does not match the one on the server";
			}
			
		}
		 return response()->json([
			"payload"=> [
				"success"=>$success,
				"error" => $error
				]
			]);

	}
	
	public function request_withdraw(Request $request){
		$inputs = $request->all();
		try{
			$userinfo = User::findOrFail($inputs['user_id']);
			//$credentials = $request->only('email', 'password');
			if (!(Door::attempt(['email'=>$userinfo->email , 'password'=>$inputs['password']], $request->has('remember'))))						
				return response()->json(array('status'=> $userinfo->password ),  200);
		}catch(ModelNotFoundException $e){
			return response()->json(array('status'=> 'Permission Denied. User Missing'),  200);
		}
		$this->authorize('if-authuser-allows', $userinfo);
		$total_available = Order::where('withdraw_status',0)->where('author_id', $inputs['user_id'])->sum('author_amount');
		if($total_available < 10000){
			return response()->json(array('status'=> 'Minimum Withdraw Amount is 10000' ),  200);
		}
		Order::where('status',1)->where('author_id', $inputs['user_id'])->update(['withdraw_status'=>2,'withdraw_to'=>$userinfo->phone]);
		$total = Order::where('withdraw_status',2)->where('author_id', $inputs['user_id'])->sum('author_amount');
		return response()->json(array('status'=> 'OK','message'=>$total),  200);
	}
	

	public function refill(Request $request){
		$inputs = $request->all();
		if ( !isset($inputs['txid']) ) 
		return response()->json(array('status'=> 'Pliz enter a Transaction ID and try again'),  200);
		try{
			$mobilemoney = Mobilemoney::where(['txid'=>$inputs['txid'],'status'=>0])->firstOrFail();
			$mobilemoney->user_id = Auth::user()->id;
			$mobilemoney->status = 1;
			$mobilemoney->save(); // for tracking
		}catch(ModelNotFoundException $e){
			return response()->json(array('status'=> 'Transaction ID is Pending or Invalid Try Again in 2 minutes'),  200);
		}
		$user = User::findOrFail(Auth::user()->id);
		$user->balance += $mobilemoney->amount;
		$user->save(); 
		return response()->json(array('status'=> 'OK','message'=>$user->balance),  200);
	}
	
	
	
  public function download_files(Request $request){
		$pid = $request->segment(2);
		$eid = base64_decode(urldecode($pid));
		$entry = Entrys::findOrFail($eid);
		$post = Posts::findOrFail($entry->post_id);
	        if ($post->approve == 'no' or $post->approve == 'draft' ) {
	            if (Auth::check() == false or $post->user_id !== Auth::user()->id) {
	                if (Auth::user()->usertype !== 'Admin') {
	                   return redirect('404');
	                }
	            }
	        }
		if (Auth::check() !== false){
			try{
				$order = Ticket::where(['status'=>'1','client_id'=>Auth::user()->id, 'entry_id'=>$entry->id])->firstOrFail();
				$purchased = true;
			}catch(ModelNotFoundException $e){
				$purchased = false;
			}
			if ($post->user_id === Auth::user()->id || Auth::user()->usertype === 'Admin') {
				$purchased = true;
				
        	}
		}
	    
		$file_url = public_path().$entry->{$entry->type};
		if ($purchased){
			return response()->download($file_url, $entry->fname);
		 }
		 return redirect('404');
	}
	
	public function smscoupon(Request $request){
		$inputs = $request->all();
		if ( !isset($inputs['phone']) ||!Mobilemoney::is_phone($inputs['phone']) ) 
		return response()->json(array('status'=> 'Pliz enter a valid Phone and try again'),  200);
		
		 try{
			$entry = Entrys::findOrFail($inputs['entry_id']);
		  }catch(ModelNotFoundException $e){
			  return response()->json(array('status'=> 'COUPON IS NOT ACTIVE'),  200);
		  }
		 try{
			$coupon = \App\Coupon::where(['entry_id'=>$entry->id,'phone'=>$inputs['phone']])->firstOrFail();
		  }catch(ModelNotFoundException $e){
			  $coupon  = new \App\Coupon; 
			  $coupon->coupon = $this->random_string('numeric',6);
			  $coupon->phone = $inputs['phone'];
			  $coupon->entry_id = $inputs['entry_id'];
			  $coupon->post_id = $entry->post_id;
			  $coupon->user_id = Auth::check()?Auth::user()->id:NULL;
			  $coupon->save();			  
		  }	  
		  $msg = str_replace('[CODE]', $coupon->coupon ,is_null($entry->source)?'Your Coupon Code is: [CODE]':$entry->source);
		  Mobilemoney::send_sms($msg,$coupon->phone);
		  return response()->json(['status'=> 'OK','message'=>'Code sent'],  200);		 
	}
	
	function showTicket(Request $request){
		 $kraft = new \Jenssegers\Optimus\Optimus(15485761, 1751008449, 836799276);
		 try{
			$ticket = Ticket::findOrFail($kraft->decode($request->segment(2)));
		  }catch(ModelNotFoundException $e){
			 return redirect('404'); 		  
		  }
		 return view("pages.ticket", compact('ticket'));
	}
	
	function rates(){
	
	        
	        
		return response()->json(Mobilemoney::fees(0,'rates'));
	}
	
		
	private function random_string($type = 'alnum', $len = 8)
	{
		switch ($type)
		{
			case 'basic':
				return mt_rand();
			case 'alnum':
			case 'numeric':
			case 'nozero':
			case 'alpha':
				switch ($type)
				{
					case 'alpha':
						$pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
						break;
					case 'alnum':
						$pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
						break;
					case 'numeric':
						$pool = '0123456789';
						break;
					case 'nozero':
						$pool = '123456789';
						break;
				}
				return substr(str_shuffle(str_repeat($pool, ceil($len / strlen($pool)))), 0, $len);
			case 'unique': // todo: remove in 3.1+
			case 'md5':
				return md5(uniqid(mt_rand()));
			case 'encrypt': // todo: remove in 3.1+
			case 'sha1':
				return sha1(uniqid(mt_rand(), TRUE));
		}
	}	 	
    
}
