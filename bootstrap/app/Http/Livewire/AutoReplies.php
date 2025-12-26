<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\UserFacebookAccount;
use App\Models\UserFacebookPage;
use Illuminate\Support\Facades\DB;


class AutoReplies extends Component
{
    public $userFacebookAccountsList;
    public $userFacebookAccountsListId;
    public $userFacebookPagesList = [];
    public $t_customer, $t_address, $t_order, $t_invoice, $t_shipped, $t_comment;
    public $editPageId;

    public function editMessage($id)
    {
        $this->resetFields();
        $this->editPageId = $id;
        $page = UserFacebookPage::where("id", $id)->first();

        if ($page) {
            $this->t_customer = $page->t_customer;
            $this->t_address = $page->t_address;
            $this->t_order = $page->t_order;
            $this->t_invoice = $page->t_invoice;
            $this->t_shipped = $page->t_shipped;
            $this->t_comment = $page->t_comment;
        } else {
            session()->flash('error', 'Page not found or you don\'t have access to edit this page.');
        }
    }

    public function updateMessage()
    {
        $this->validate([
            't_customer' => 'nullable|string',
            't_address' => 'nullable|string',
            't_order' => 'nullable|string',
            't_invoice' => 'nullable|string',
            't_shipped' => 'nullable|string',
            't_comment' => 'nullable|string',
        ]);

        $page = UserFacebookPage::find($this->editPageId);

        if ($page) {
            $page->update([
                't_customer' => $this->t_customer,
                't_address' => $this->t_address,
                't_order' => $this->t_order,
                't_invoice' => $this->t_invoice,
                't_shipped' => $this->t_shipped,
                't_comment' => $this->t_comment,
            ]);
            $this->resetFields();
            session()->flash('success', 'Page messages updated successfully.');
            $this->emit('messageUpdated');   
        } else {
            session()->flash('error', 'Unable to update messages. Page not found.');
        }
    }

    public function resetFields()
    {
        $this->reset(['t_customer', 't_address', 't_order', 't_invoice', 't_shipped','t_comment','editPageId']);
    }


    public function render()
    {
        $this->userFacebookAccountsList = UserFacebookAccount::all();
        if (count($this->userFacebookAccountsList) > 0 )
        {
            $this->userFacebookAccountsListId  = $this->userFacebookAccountsList[0]->facebook_id;
            $this->userFacebookPagesList       = UserFacebookPage::where("facebook_id",$this->userFacebookAccountsListId)->get();
        }
        return view('livewire.auto-replies');
    }
}
