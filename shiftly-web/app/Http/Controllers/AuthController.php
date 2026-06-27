<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        
        return $user->role === 'manager' 
            ? redirect()->intended(route('manager.dashboard'))
            : redirect()->intended(route('employee.schedule'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login');
    }

    public function updateEmployeeProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        // Update username if provided
        if (isset($validated['name']) && $validated['name'] !== $user->name) {
            $user->name = $validated['name'];
        }

        // Update email if provided and different
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $user->email = $validated['email'];
        }

        // Update password if provided
        if (isset($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return back()->with('success', 'Credentials updated successfully!');
    }

    public function updateManagerProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required',
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'new_password' => 'nullable|min:8|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect']);
        }

        // Update username if provided
        if (isset($validated['name']) && $validated['name'] !== $user->name) {
            $user->name = $validated['name'];
        }

        // Update email if provided and different
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $user->email = $validated['email'];
        }

        // Update password if provided
        if (isset($validated['new_password'])) {
            $user->password = Hash::make($validated['new_password']);
        }

        $user->save();

        return back()->with('success', 'Credentials updated successfully!');
    }
}
