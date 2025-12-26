<?php

namespace App\Http\Livewire;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Hash;

class UserManagement extends Component
{

    public User $user;
    public $Users;

    public $name;
    public $email;
    public $phone;
    public $role = 'superadmin';
    public $status = 'active';
    public $password;
    public $userId;

    public function addUser()
    {
        // Validate the input data
        $validatedData = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:15',
            'role' => 'required|in:superadmin,admin,data_entry',
            'status' => 'required|in:active,inactive',
            'password' => 'required|string|min:8',
        ]);

        try {
            // Hash the password before saving
            $validatedData['password'] = $validatedData['password'];

            // Insert the user into the database
            \App\Models\User::create($validatedData);

            session()->flash('success', 'User added successfully!');
            // Optionally, reset the form fields
            $this->reset(['name', 'email', 'phone', 'role', 'status', 'password']);
            $this->emit('userAdded');
        } catch (\Exception $e) {
            // Emit error message in case of any failure
            session()->flash('error', 'An error occurred while adding the user.');
        }
    }

    public function editUser($id)
    {
        $user = \App\Models\User::findOrFail($id); // Fetch the user

        // Set the user details in the component variables
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->role = $user->role;
        $this->status = $user->status;
    }

    public function updateUser()
    {
        // Validate input
        $validatedData = $this->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|unique:users,email,{$this->userId}",
            'phone' => 'nullable|string|max:15',
            'role' => 'required|in:superadmin,admin,data_entry',
            'status' => 'required|in:active,inactive',
        ]);

        // Include password if provided
        if ($this->password) {
            $validatedData['password'] = $this->password;
        }

        try {
            // Fetch the user and update
            $user = \App\Models\User::findOrFail($this->userId);
            $user->update($validatedData);

            session()->flash('success', 'User updated Successfully!');
            // Optionally, reset the form fields
            $this->reset(['name', 'email', 'phone', 'role', 'status', 'password']);
            $this->emit('userUpdated');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update user: ' . $e->getMessage());
        }
    }

    public function resetFields() {
        $this->reset(['name', 'email', 'phone', 'role', 'status', 'password']);
    }

    public function confirmDelete($id)
    {
        $this->userId = $id;
        $this->dispatchBrowserEvent('swal:confirm', [
            'title' => 'Are you sure?',
            'text' => 'This will permanently delete the user.',
            'type' => 'warning',
            'function' => 'user',
            'showCancelButton' => true,
            'confirmButtonText' => 'Yes, delete it!',
            'cancelButtonText' => 'No, keep it',
        ]);
    }

    public function deleteUser()
    {
        $user = \App\Models\User::findOrFail($this->userId);
        $user->delete();
        session()->flash('success', 'User deleted successfully!');
    }
    
    public function render()
    {
        $this->Users = User::all();
        return view('livewire.user-management');
    }

}
