<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderBy('name')
            ->paginate(25);

        return view('admin.users.index', compact('users'));
    }

    public function toggleInternal(Request $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'is_platform_internal' => ! $user->is_platform_internal,
        ])->save();

        $label = $user->is_platform_internal ? 'ativado como interno' : 'desativado como interno';

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Usuário {$user->name} {$label}. IA da plataforma sem cobrança.");
    }
}
