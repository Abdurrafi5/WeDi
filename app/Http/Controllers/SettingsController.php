<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        return view('settings', ['user' => $user, 'journalCount' => $user->journals()->count(), 'targetCount' => $user->targets()->count(), 'weekCount' => $user->weeklyDiaries()->count()]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255'], 'nickname' => ['nullable', 'string', 'max:60'], 'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);
        $user = $request->user();
        unset($validated['avatar']);
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $directory = public_path('uploads/avatars');
            if (!is_dir($directory)) mkdir($directory, 0755, true);
            if ($user->avatar_path && is_file(public_path($user->avatar_path))) @unlink(public_path($user->avatar_path));
            $file = $request->file('avatar');
            $filename = Str::uuid()->toString() . '.' . strtolower($file->getClientOriginalExtension());
            $file->move($directory, $filename);
            $validated['avatar_path'] = 'uploads/avatars/' . $filename;
        }
        $user->forceFill($validated)->save();
        return redirect()->route('settings')->with('success', __('Profile updated successfully.'));
    }

    public function updateLocale(Request $request)
    {
        $locale = $request->validate(['locale' => ['required', 'in:id,en']])['locale'];

        $request->session()->put('locale', $locale);
        Cookie::queue('locale', $locale, 60 * 24 * 365);
        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }
        app()->setLocale($locale);

        return back()->with('success', __('Language changed successfully.'));
    }

    public function updateAppearance(Request $request)
    {
        $colorful = $request->boolean('colorful');
        $request->user()->forceFill(['colorful' => $colorful])->save();

        return back()->with('success', $colorful ? __('Colorful mode enabled.') : __('Colorful mode turned off.'));
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate(['current_password' => ['required'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        if (!Hash::check($validated['current_password'], $user->password)) return back()->withErrors(['current_password' => __('Current password is incorrect.')]);
        $user->update(['password' => Hash::make($validated['password'])]);
        return redirect()->route('settings')->with('success', __('Password changed successfully.'));
    }
}
