<?php

namespace App\Http\Livewire;

use App\Models\Setting;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class Settings extends Component
{

    public $Settings;

    public $value;
    public $type;
    public $settingId;

    public function editSettings($id)
    {
        $Settings = Setting::findOrFail($id);

        $this->settingId = $Settings->id;
        $this->value = $Settings->value;
        $this->type = $Settings->type;
    }

    public function updateSettings()
    {
        // Validate input
        $validatedData = $this->validate([
            'value' => 'required|string',
        ]);

        try {
            $setting = Setting::findOrFail($this->settingId);
            $setting->update([
                'value' => $this->value,
            ]);
            session()->flash('success', 'Setting updated successfully!');
            $this->reset(['value']);
            $this->emit('settingsUpdated');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    public function resetFields() {
        $this->reset(['value']);
    }
    
    public function render()
    {
        $this->Settings = Setting::all();
        return view('livewire.settings');
    }

}
