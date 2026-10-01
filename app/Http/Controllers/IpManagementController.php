<?php

namespace App\Http\Controllers;

use App\Models\BlockedIp;
use App\Models\IpIntelligence;
use App\Services\Security\IpManagementService;
use App\Services\Security\IpNetwork;
use App\Validation\IpOrCidrRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IpManagementController extends Controller
{
    public function __construct(protected IpManagementService $ipManagement) {}

    public function index(Request $request): View
    {
        $query = BlockedIp::query()->with(['administrator', 'alert', 'incident']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($nested) use ($search) {
                $nested->where('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('reason', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('action') && in_array($request->input('action'), ['allow', 'block'], true)) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('source') && in_array($request->input('source'), BlockedIp::SOURCES, true)) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('state')) {
            match ($request->input('state')) {
                'active' => $query->enforcing(),
                'expired' => $query->whereNotNull('expires_at')->where('expires_at', '<=', now()),
                'disabled' => $query->where(function ($nested) {
                    $nested->where('is_enabled', false)->orWhere('status', '!=', 'active');
                }),
                default => null,
            };
        }

        $rules = $query->orderByDesc('blocked_at')->paginate(15)->appends($request->query());

        $summary = [
            'total' => BlockedIp::query()->count(),
            'allowed' => BlockedIp::query()->where('action', 'allow')->count(),
            'blocked' => BlockedIp::query()->where('action', 'block')->count(),
            'active' => (clone $query)->enforcing()->count(),
            'expiring_soon' => BlockedIp::query()->enforcing()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDay())
                ->count(),
        ];

        $intelligence = IpIntelligence::query()
            ->whereIn('ip_address', $rules->getCollection()->pluck('ip_address')->filter(fn (string $ip): bool => ! IpNetwork::isCidr($ip)))
            ->get()
            ->keyBy('ip_address');
        $intel = $rules->getCollection()->mapWithKeys(fn (BlockedIp $rule): array => [
            $rule->id => $intelligence->get($rule->ip_address)?->toArray(),
        ])->all();

        return view('ip-management.index', [
            'rules' => $rules,
            'summary' => $summary,
            'intel' => $intel,
            'myIp' => $request->ip(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ip_address' => ['required', 'string', 'max:64', new IpOrCidrRule],
            'action' => ['required', 'in:allow,block'],
            'reason' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:120'],
            'is_enabled' => ['nullable', 'boolean'],
            'expiration' => ['nullable', 'in:permanent,30m,1h,24h,7d,30d,custom'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'confirm_self_block' => ['nullable', 'boolean'],
        ]);

        $normalized = IpNetwork::normalize($validated['ip_address']);

        // Safety: never silently lock the acting administrator out.
        if ($validated['action'] === 'block'
            && IpNetwork::matches($normalized, $request->ip() ?? '')
            && ! $request->boolean('confirm_self_block')
        ) {
            return back()
                ->withInput()
                ->withErrors(['ip_address' => 'This rule would block your own IP address ('.$request->ip().'). Tick the confirmation to proceed.'])
                ->with('self_block_warning', $request->ip());
        }

        try {
            $this->ipManagement->createRule([
                'ip_address' => $normalized,
                'action' => $validated['action'],
                'reason' => $validated['reason'],
                'description' => $validated['description'] ?? null,
                'name' => $validated['name'] ?? null,
                // Provenance is server-assigned: UI-created rules are always
                // manual. Alert/incident/automatic sources are set only by
                // their dedicated server-side flows, never by form input.
                'source' => 'manual',
                'is_enabled' => $request->boolean('is_enabled', true),
                'expires_at' => $this->resolveExpiration($validated),
            ], $request->user(), $request->ip());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['ip_address' => $e->getMessage()]);
        }

        return redirect()->route('ip-management.index')->with('status', 'rule-created');
    }

    public function update(Request $request, BlockedIp $rule): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:allow,block'],
            'reason' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'name' => ['nullable', 'string', 'max:120'],
            'is_enabled' => ['nullable', 'boolean'],
            'expiration' => ['nullable', 'in:permanent,30m,1h,24h,7d,30d,custom'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'confirm_self_block' => ['nullable', 'boolean'],
        ]);

        if ($validated['action'] === 'block'
            && IpNetwork::matches($rule->ip_address, $request->ip() ?? '')
            && ! $request->boolean('confirm_self_block')
        ) {
            return back()
                ->withInput()
                ->withErrors(['action' => 'This change would block your own IP address ('.$request->ip().'). Tick the confirmation to proceed.'])
                ->with('self_block_warning', $request->ip());
        }

        $this->ipManagement->updateRule($rule, [
            'action' => $validated['action'],
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'name' => $validated['name'] ?? null,
            'is_enabled' => $request->boolean('is_enabled', true),
            'expires_at' => $this->resolveExpiration($validated),
        ], $request->user(), $request->ip());

        return redirect()->route('ip-management.index')->with('status', 'rule-updated');
    }

    public function toggle(Request $request, BlockedIp $rule): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'confirm_self_block' => ['nullable', 'boolean'],
        ]);

        if ($validated['enabled']
            && $rule->isBlockRule()
            && IpNetwork::matches($rule->ip_address, $request->ip() ?? '')
            && ! $request->boolean('confirm_self_block')
        ) {
            return back()->withErrors([
                'enabled' => 'Enabling this rule would block your own IP address ('.$request->ip().'). Confirm explicitly to proceed.',
            ]);
        }

        $this->ipManagement->setEnabled($rule, (bool) $validated['enabled'], $request->user(), $request->ip());

        return redirect()->route('ip-management.index')->with('status', 'rule-toggled');
    }

    public function switchAction(Request $request, BlockedIp $rule): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:allow,block'],
            'confirm_self_block' => ['nullable', 'boolean'],
        ]);

        if ($validated['action'] === 'block'
            && IpNetwork::matches($rule->ip_address, $request->ip() ?? '')
            && ! $request->boolean('confirm_self_block')
        ) {
            return back()->withErrors([
                'action' => 'Switching this rule to BLOCK would block your own IP address ('.$request->ip().'). Confirm explicitly to proceed.',
            ]);
        }

        $this->ipManagement->updateRule($rule, [
            'action' => $validated['action'],
        ], $request->user(), $request->ip());

        return redirect()->route('ip-management.index')->with('status', 'rule-updated');
    }

    public function destroy(Request $request, BlockedIp $rule): RedirectResponse
    {
        $this->ipManagement->deleteRule($rule, $request->user(), $request->ip());

        return redirect()->route('ip-management.index')->with('status', 'rule-deleted');
    }

    protected function resolveExpiration(array $validated): mixed
    {
        $mode = $validated['expiration'] ?? null;

        if ($mode === null || $mode === 'permanent') {
            // Explicit custom datetime without a mode still applies.
            return ! empty($validated['expires_at']) ? $validated['expires_at'] : null;
        }

        if ($mode === 'custom') {
            return $validated['expires_at'] ?? null;
        }

        return match ($mode) {
            '30m' => now()->addMinutes(30),
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            default => null,
        };
    }
}
