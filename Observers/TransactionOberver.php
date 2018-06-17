<?php
namespace App\Observers;

use App\Notifications\NewTransaction;
use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $Transaction)
    {
		//dd($Trasaction);
		$Transaction->user->notify(new NewTransaction($Transaction));
    }
}
?>