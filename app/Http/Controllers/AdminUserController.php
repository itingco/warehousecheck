<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->orderBy('name')
            ->get();

        return view(
            'admin.users',
            compact('users')
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                'unique:warehouse_users,username',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
            ],
            'role' => [
                'required',
                'in:picker,checker,supervisor,admin',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
            ],
        ]);

        User::create([
            'username' => $data['username'],
            'name' => $data['name'],
            'role' => $data['role'],
            'password' => Hash::make(
                $data['password']
            ),
            'is_active' => true,
        ]);

        return back()->with(
            'success',
            'User berhasil dibuat.'
        );
    }

    public function toggle(User $user)
    {
        if (
            auth()->id() === $user->id
            && $user->is_active
        ) {
            return back()->withErrors([
                'user' =>
                    'Tidak bisa menonaktifkan user sendiri.',
            ]);
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        return back()->with(
            'success',
            'Status user diperbarui.'
        );
    }
}
