<?php

namespace App\Notifications;
use App\Models\Token;
use App\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class TokenCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    protected $token;

    public function __construct(Token $token)
    {
        $this->token = $token;
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
        $eth_balance = $this->token->user->account->balance;
         return [
		 	'id'=>$this->token->id,
			'type'=>'\App\Models\Token',
		    'heading'=>'New '.$symbol.' Token',
            'message' => 'Your Token '.$this->token->name.' ( '.$this->token->symbol.' ) Contract creation has been Confirmed',
			'eth_balance' => number_format($eth_balance,8),  
			'token' =>  NULL,
         ];
    }
	
	
	public function toBroadcast($notifiable)
	{
		return new BroadcastMessage($this->toArray($notifiable));
	}
}
