<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()
                ->select(['id', 'name', 'email', 'email_verified_at', 'created_at'])
                ->where('user_type', 'ADMIN')
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            ...$request->validated(),
            'user_type' => 'ADMIN',
        ]);

        return to_route('admin.users.index')->with('success', 'User created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if(! $user->isAdmin(), 404);

        $validated = $request->validated();
        $attributes = Arr::except($validated, ['password', 'password_confirmation']);

        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = $validated['password'];
        }

        $user->fill($attributes);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('admin.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if(! $user->isAdmin(), 404);

        if ($request->user()?->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own admin account.',
            ]);
        }

        $user->delete();

        return to_route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
