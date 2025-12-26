<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use App\Models\UserClients;
use App\Models\UserZap;
use App\Models\UserLeads;
use App\Models\UserLeadDetails;
use App\Http\Livewire\Facebook;
use App\Models\LeadSchedule;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FBLeads extends Component
{
    use WithPagination;

    public $userClients;
    public $userAutomations;
    protected $userLeadsData;
    public $userStatus = [
        0 => "Pending",
        1 => "Pushed to Discord",
        2 => "Junk",
        4 => "Fetched",
        5 => "Scheduled"
    ];
    public $searchText = '';
    public $selectedStatusId = -1;
    public $selectedAutoId = 0;
    public $selectedClientId = 0;
    public $selectedIds = [];
    public $selectAll = false;
    public $sendOption = 'now';
    public $intervalValue = 1;
    public $intervalUnit = 'minutes';

    protected $listeners = [
        'updatedSelectedStatusId',
        'paginationLinkClicked',
        'updatedSelectedAutoId',
        'updatedSelectedClientId'
    ];

    public function mount()
    {
        $this->selectedAutoId = Request::get('selectedAutoId', 0);
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedIds = $this->getUserLeads()->pluck('id')->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    public function hydrate()
    {
        $this->emit('select2');
    }

    public function updatedSelectedStatusId($value)
    {
        $this->selectedStatusId = $value;
    }

    public function updatedSelectedAutoId($value)
    {
        $this->selectedAutoId = $value;
    }

    public function updatedSelectedClientId($value)
    {
        $this->selectedClientId = $value;
    }

    public function deleteSelected()
    {
        UserLeads::whereIn('id', $this->selectedIds)->delete();
        $this->selectAll = false;
    }

    public function sendLeadsToClients()
    {
        $facebookInstance = new Facebook();
        $selectedIds = $this->selectedIds;
        if ($this->sendOption == 'now')
        {
            foreach ($selectedIds as $leadId) {
                if ($facebookInstance) {
                    $userLead = UserLeads::find($leadId);
                    if ($userLead) {
                        $TextforModeration = "";
                        $email = "";
                        $phone = "";
                        $UserLeadDetails = UserLeadDetails::where('lead_id', $leadId)->get();
                        foreach($UserLeadDetails as $LeadDetails)
                        {
                            if ($LeadDetails->lead_key == 'email')
                            {
                                $email = $LeadDetails->lead_value;
                            }
                            if ($LeadDetails->lead_key == 'phone_number')
                            {
                                $phone = $LeadDetails->lead_value;
                            }
                            $TextforModeration .= $LeadDetails->lead_value." ";
                        }
                        $isJunk = $facebookInstance->checkJunk($email,$phone);
                        $ContentModeratorationAPIResponse    = $facebookInstance->ContentModeratorationAPI($TextforModeration);
                        $ContentModeratorationCustomResponse = $facebookInstance->ContentModeratorationCustom($TextforModeration);
                        if ($ContentModeratorationAPIResponse != 1 && $ContentModeratorationCustomResponse != 1 && $isJunk == false)
                        {
                            $facebookInstance->sendLeadToDiscord($userLead->zap_id, $leadId);
                            $facebookInstance->sendLeadToGoogleSheet($userLead->zap_id, $leadId);
                            if ($ContentModeratorationAPIResponse == 2)
                                UserLeads::where('id', $userLead->id)->update(['status' => 3]);
                        }
                        else{
                            UserLeads::where('id', $userLead->id)->update(['status' => 2]);
                        }
                    }
                }
            }
            return redirect()->route('leads')->with('success', 'Leads Sent Successfully !');
        }
        else{
            $delay = 0;
            foreach ($selectedIds as $leadId) {
                $delay += $this->intervalValue * ($this->intervalUnit == 'minutes' ? 60 : 1);
                LeadSchedule::create([
                    'lead_id' => $leadId,
                    'interval_value' => $this->intervalValue,
                    'interval_unit' => $this->intervalUnit,
                    'scheduled_at' => now()->addSeconds($delay)
                ]);
                UserLeads::where('id', $leadId)->update(['status' => 5]);
            }
            return redirect()->route('leads')->with('success', 'Leads sending scheduled successfully!');
        }
    }

    protected function resetSelectedIds()
    {
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function paginationLinkClicked()
    {
        $this->resetSelectedIds();
    }

    public function downloadLeads()
    {
        $userLeads = $this->getUserLeads($this->selectedStatusId, true);
        $data = [];
        $filename = "";
        foreach ($userLeads['data'] as &$lead) {
            if (isset($lead['created_at'])) {
                $createdAt = Carbon::createFromFormat('Y-m-d\TH:i:s.u\Z', $lead['created_at'], 'UTC')
                    ->timezone('Asia/Singapore')
                    ->format('d-m-Y H:i:s');

                $lead['lead_date'] = $createdAt;
                unset($lead['created_at']);
            }
            foreach ($lead['lead_details'] as $detail) {
                $lead[$detail['lead_key']] = $detail['lead_value'];
            }
            if (empty($filename)) {
                $filename = $lead['zap_name'];
            }
            unset($lead['lead_details']);
            unset($lead['id'], $lead['facebook_page_id'], $lead['facebook_form_id'], $lead['zap_id'], $lead['status'], $lead['client_id'], $lead['updated_at'], $lead['deleted_at'], $lead['client_name'],$lead['zap_name']);
            ksort($lead);
            $data[] = $lead;
        }

        if (!empty($data[0])) {
            $firstLead = $data[0];
            $headers = array_keys($firstLead);
            $data = [$headers] + $data;
            array_splice($data, 1, 0, [$firstLead]);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($data);
        $writer = new Xlsx($spreadsheet);
        $filename = $filename.'.xlsx';
        $writer->save($filename);
        return response()->download($filename)->deleteFileAfterSend(true);
    }



    public function render()
    {
        $this->userClients = UserClients::where("user_id", auth()->user()->id)->get();
        $this->userAutomations = UserZap::where("user_id", auth()->user()->id)->get();
        $userLeads = $this->getUserLeads($this->selectedStatusId);
        $this->userLeadsData = $userLeads;
        return view('livewire.f-b-leads', ['userLeads' => $userLeads]);
    }

    protected function getUserLeads($status = 0,$returnArray = false)
    {
        $query = UserLeads::where('user_leads.user_id', Auth::id())
            ->join('user_zaps', 'user_zaps.id', '=', 'user_leads.zap_id')
            ->join('user_clients', 'user_clients.client_id', '=', 'user_leads.client_id')
            ->with('leadDetails')
            ->select('user_zaps.name as zap_name', 'user_clients.name as client_name', 'user_leads.*');

        if ($this->searchText) {
            $query->whereHas('leadDetails', function ($detailsQuery) {
                $detailsQuery->where('lead_value', 'like', '%' . $this->searchText . '%');
            });
        }
        if ($status >= 0) {
            $query->where('user_leads.status', $status);
        }
        
        if ($this->selectedAutoId != 0) {
            $query->where('user_leads.zap_id', $this->selectedAutoId);
        }
        if ($this->selectedClientId != 0) {
            $query->where('user_leads.client_id', $this->selectedClientId);
        }
        
        $query->orderBy('user_leads.created_at', 'desc');

        if ($returnArray) {
            return $query->paginate(500)->toArray();
        } else {
            return $query->paginate(500);
        }
    }
}
