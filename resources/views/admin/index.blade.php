<x-layouts.app title="Administration - INTSEC">
    <div class="ops-page">
        <x-ui.page-header kicker="Administration" title="Security operations workspace" description="Manage access, deterministic security settings, and accountability records."><x-slot:context><span class="ops-context-pill">Administrator access</span></x-slot:context></x-ui.page-header>
        <div class="ops-grid-3">
            <a class="ops-panel ops-panel--padded" href="{{ route('users.index') }}"><h2 class="ops-panel-title">Users</h2><p class="ops-panel-description">Manage identities, roles, and account availability.</p><span class="ops-panel-link">Open user administration</span></a>
            <a class="ops-panel ops-panel--padded" href="{{ route('admin.settings') }}"><h2 class="ops-panel-title">System settings</h2><p class="ops-panel-description">Configure protection, detection, correlation, and enrichment thresholds.</p><span class="ops-panel-link">Open system settings</span></a>
            <a class="ops-panel ops-panel--padded" href="{{ route('admin.audit-logs') }}"><h2 class="ops-panel-title">Audit logs</h2><p class="ops-panel-description">Review security-sensitive and administrative actions.</p><span class="ops-panel-link">Review audit history</span></a>
        </div>
    </div>
</x-layouts.app>
