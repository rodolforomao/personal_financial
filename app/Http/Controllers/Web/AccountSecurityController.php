<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class AccountSecurityController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $session) use ($currentSessionId) {
                return (object) [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => $session->last_activity,
                    'is_current' => $session->id === $currentSessionId,
                ];
            });

        $tokens = $user->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => (object) [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
            ]);

        return view('account.security', [
            'sessions' => $sessions,
            'tokens' => $tokens,
            'plainTextToken' => session('plain_text_token'),
        ]);
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        $deleted = DB::table('sessions')
            ->where('id', $session)
            ->where('user_id', $request->user()->id)
            ->delete();

        abort_unless($deleted, 404);

        if ($session === $request->session()->getId()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('success', 'Sessão encerrada.');
        }

        return redirect()
            ->route('account.security')
            ->with('success', 'Sessão revogada.');
    }

    public function storeToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $token = $request->user()->createToken($validated['name']);

        return redirect()
            ->route('account.security')
            ->with('success', 'Token de API criado. Copie o valor abaixo — ele não será exibido novamente.')
            ->with('plain_text_token', $token->plainTextToken);
    }

    public function revokeToken(Request $request, int $token): RedirectResponse
    {
        $deleted = $request->user()
            ->tokens()
            ->where('id', $token)
            ->delete();

        abort_unless($deleted, 404);

        return redirect()
            ->route('account.security')
            ->with('success', 'Token revogado.');
    }
}
