<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceUserInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $workspaces = $user->workspaces()->orderBy('name')->get();
        $currentId = (int) session('workspace_id');
        $current = $workspaces->firstWhere('id', $currentId);

        $canInvite = $current && in_array($current->pivot->role ?? 'member', ['owner', 'admin'], true);

        $members = collect();
        if ($current) {
            $members = $current->users()
                ->orderBy('name')
                ->get();
        }

        return view('workspace.index', [
            'workspaces' => $workspaces,
            'currentWorkspace' => $current,
            'canInvite' => $canInvite,
            'members' => $members,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $workspace = $request->user()->workspaces()->orderBy('name')->first();

        if (! $workspace) {
            return redirect()
                ->route('workspace.index')
                ->with('warning', 'Nenhum workspace disponível para restaurar.');
        }

        session(['workspace_id' => $workspace->id]);

        return redirect()
            ->route('dashboard')
            ->with('success', "Workspace restaurado para {$workspace->name}.");
    }

    public function inviteMember(Request $request, WorkspaceUserInviteService $inviteService): RedirectResponse
    {
        $workspaceId = (int) $request->attributes->get('workspace_id');
        $current = $request->user()->workspaces()->find($workspaceId);

        abort_unless(
            $current && in_array($current->pivot->role ?? 'member', ['owner', 'admin'], true),
            403,
            'Sem permissão para convidar membros neste workspace.',
        );

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:member,admin',
            'send_reset_link' => 'nullable|boolean',
        ]);

        $result = $inviteService->invite(
            email: $validated['email'],
            workspaceId: $workspaceId,
            role: $validated['role'] ?? 'member',
            name: $validated['name'] ?? null,
            invitedBy: null,
            sendResetLink: $request->boolean('send_reset_link', true),
            grantPlatformAccess: false,
        );

        $message = $result['created']
            ? "Convite enviado para {$result['user']->email}. "
            : "{$result['user']->name} foi adicionado ao workspace. ";

        if ($result['reset_link_sent']) {
            $message .= 'Link para definir senha enviado por e-mail.';
        } elseif ($result['created']) {
            $message .= 'Configure o e-mail para enviar o link de senha ou use “Esqueci minha senha”.';
        }

        return redirect()
            ->route('workspace.index')
            ->with('success', trim($message));
    }
}
