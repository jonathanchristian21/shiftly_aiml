<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ManagerAccountController extends Controller
{
    public function index()
    {
        $managers = User::where('role', 'manager')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('manager.accounts.index', compact('managers'));
    }

    public function create()
    {
        return view('manager.accounts.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'manager',
        ]);

        return redirect()->route('manager.accounts.index')
            ->with('success', 'Manager account created successfully!');
    }

    public function destroy(User $account)
    {
        // Cek hanya bisa delete akun sendiri
        if ($account->id !== auth()->id()) {
            return back()->with('error', 'You can only delete your own account!');
        }

        // Cek minimal harus ada 1 manager
        $managerCount = User::where('role', 'manager')->count();
        if ($managerCount <= 1) {
            return back()->with('error', 'Cannot delete the last manager account!');
        }

        auth()->logout();
        $account->delete();

        return redirect()->route('login')
            ->with('success', 'Your account has been deleted successfully!');
    }
}
