<?php
namespace App\Http\Livewire;

use App\Models\Webhook;
use Livewire\Component;
use Illuminate\Support\Str;

class WebhookManagement extends Component
{
    public $webhooks;
    public $webhookId;
    public $webhook_name;
    public $url_to_notify;
    public $event_trigger;
    public $content_type = 'application/json';
    public $auth_username;
    public $auth_password;
    public $status = 'active';
    public $custom_parameters = [];
    public $searchText = '';
    public $selectedStatus = '';

    // Available event triggers
    public $eventTriggers = [
        'account.created' => 'Account - Create Account',
        'account.updated' => 'Account - Update Account',
        'account.deleted' => 'Account - Delete Account',
        'order.created' => 'Order - Create Order',
        'order.updated' => 'Order - Update Order',
        'order.completed' => 'Order - Order Completed',
        'user.registered' => 'User - User Registered',
        'user.login' => 'User - User Login',
        'payment.success' => 'Payment - Payment Success',
        'payment.failed' => 'Payment - Payment Failed',
        'scanned.product' => 'Product operations',
        'fetch.order' => 'Fetch orders',
    ];

    public function mount()
    {
        $this->custom_parameters = [
            ['name' => '', 'value' => '']
        ];
    }

    public function addCustomParameter()
    {
        $this->custom_parameters[] = ['name' => '', 'value' => ''];
    }

    public function removeCustomParameter($index)
    {
        unset($this->custom_parameters[$index]);
        $this->custom_parameters = array_values($this->custom_parameters);
    }

    public function addWebhook()
    {
        $validatedData = $this->validate([
            'webhook_name' => 'required|string|max:255',
            'url_to_notify' => 'required',
            'event_trigger' => 'required|string',
            'content_type' => 'required|string',
            'auth_username' => 'nullable|string|max:255',
            'auth_password' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            // Filter out empty custom parameters
            $filteredParams = array_filter($this->custom_parameters, function($param) {
                return !empty($param['name']) || !empty($param['value']);
            });

            $validatedData['custom_parameters'] = json_encode(array_values($filteredParams));
            $validatedData['webhook_key'] = Str::random(32);

            Webhook::create($validatedData);

            session()->flash('success', 'Webhook added successfully!');
            $this->resetFields();
            $this->emit('webhookAdded');
        } catch (\Exception $e) {
            session()->flash('error', 'An error occurred while adding the webhook: ' . $e->getMessage());
        }
    }

    public function editWebhook($id)
    {
        $webhook = Webhook::findOrFail($id);
        
        $this->webhookId = $webhook->id;
        $this->webhook_name = $webhook->webhook_name;
        $this->url_to_notify = $webhook->url_to_notify;
        $this->event_trigger = $webhook->event_trigger;
        $this->content_type = $webhook->content_type;
        $this->auth_username = $webhook->auth_username;
        $this->auth_password = $webhook->auth_password;
        $this->status = $webhook->status;
        
        // Decode custom parameters
        $params = json_decode($webhook->custom_parameters, true);
        $this->custom_parameters = !empty($params) ? $params : [['name' => '', 'value' => '']];
    }

    public function updateWebhook()
    {
        $validatedData = $this->validate([
            'webhook_name' => 'required|string|max:255',
            'url_to_notify' => 'required',
            'event_trigger' => 'required|string',
            'content_type' => 'required|string',
            'auth_username' => 'nullable|string|max:255',
            'auth_password' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
        ]);

        try {
            // Filter out empty custom parameters
            $filteredParams = array_filter($this->custom_parameters, function($param) {
                return !empty($param['name']) || !empty($param['value']);
            });

            $validatedData['custom_parameters'] = json_encode(array_values($filteredParams));

            $webhook = Webhook::findOrFail($this->webhookId);
            $webhook->update($validatedData);

            session()->flash('success', 'Webhook updated successfully!');
            $this->resetFields();
            $this->emit('webhookUpdated');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update webhook: ' . $e->getMessage());
        }
    }

    public function resetFields()
    {
        $this->reset([
            'webhook_name', 
            'url_to_notify', 
            'event_trigger', 
            'content_type', 
            'auth_username', 
            'auth_password', 
            'status'
        ]);
        $this->custom_parameters = [['name' => '', 'value' => '']];
    }

    public function confirmDelete($id)
    {
        $this->webhookId = $id;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the webhook.',
            'type' => 'warning',
            'function' => 'webhook',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function deleteWebhook()
    {
        $webhook = Webhook::findOrFail($this->webhookId);
        $webhook->delete();
        session()->flash('success', 'Webhook deleted successfully!');
    }

    public function testWebhook($id)
    {
        try {
            $webhook = Webhook::findOrFail($id);
            dd($webhook);
            
            // Prepare test payload
            $payload = [
                'event' => $webhook->event_trigger,
                'timestamp' => now()->toIso8601String(),
                'test' => true,
                'data' => [
                    'message' => 'This is a test webhook notification'
                ]
            ];

            // Add custom parameters if any
            $customParams = json_decode($webhook->custom_parameters, true);
            if (!empty($customParams)) {
                foreach ($customParams as $param) {
                    if (!empty($param['name'])) {
                        $payload[$param['name']] = $param['value'];
                    }
                }
            }

            // Prepare headers
            $headers = [
                'Content-Type: ' . $webhook->content_type,
                'X-Webhook-Key: ' . $webhook->webhook_key,
            ];

            // Initialize cURL
            $ch = curl_init($webhook->url_to_notify);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            // Add basic auth if provided
            if ($webhook->auth_username && $webhook->auth_password) {
                curl_setopt($ch, CURLOPT_USERPWD, $webhook->auth_username . ':' . $webhook->auth_password);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            dd($httpCode);

            if ($httpCode >= 200 && $httpCode < 300) {
                session()->flash('success', 'Webhook test successful! Response code: ' . $httpCode);
            } else {
                session()->flash('error', 'Webhook test failed with response code: ' . $httpCode);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to test webhook: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = Webhook::query();

        // Search filter
        if (!empty($this->searchText)) {
            $query->where(function($q) {
                $q->where('webhook_name', 'like', '%' . $this->searchText . '%')
                  ->orWhere('url_to_notify', 'like', '%' . $this->searchText . '%')
                  ->orWhere('event_trigger', 'like', '%' . $this->searchText . '%');
            });
        }

        // Status filter
        if (!empty($this->selectedStatus)) {
            $query->where('status', $this->selectedStatus);
        }

        $this->webhooks = $query->orderBy('created_at', 'desc')->get();

        return view('livewire.webhook-management');
    }
}