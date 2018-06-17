<?php

namespace App\Notifications;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class NewTransaction extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $tx;

    public function __construct(Transaction $tx)
    {
        $this->tx = $tx;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database','broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toDatabase($notifiable)
    {
		return $this->toArray($notifiable);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
		$wallet = null;
        $eth_balance = $this->tx->user->account->balance;
		$amount = $eth_balance;
		$symbol = "ETH";
		$action = $this->tx->type =='credit'?'Received':'Sent';
		$adj = $this->tx->type =='credit'?'From: '. $this->tx->from:'To: '. $this->tx->to;
		$token_balance = 0;
		if($this->tx->token_id!=1){
			$wallet = $this->tx->token->wallets()->ofAccount($this->tx->account_id)->first();
			$symbol =  $this->tx->token->symbol;
		}else{
			$eth_balance = $this->tx->type =='credit'?$eth_balance+$this->tx->amount:$eth_balance-$this->tx->amount;
		}
         return [
		 	'id'=>$this->tx->id,
			'type'=>'\App\Models\Transaction',
		    'heading'=>'New '.$symbol.' Transaction',
            'message' => $this->tx->description,
			'eth_balance' => number_format($eth_balance,8), 
			'token_balance' => 0,  
			'token' =>  $wallet?$wallet->token->symbol:NULL,
         ];
    }
	
	
	public function toBroadcast($notifiable)
	{
		return new BroadcastMessage($this->toArray($notifiable));
	}
}
