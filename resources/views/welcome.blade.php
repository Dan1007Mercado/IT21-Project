<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020a11">
    <meta name="description" content="INTSEC application intrusion monitoring and incident response platform.">
    <title>{{ config('app.name', 'INTSEC') }} — Security Operations Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Orbitron:wght@600;700;800&display=swap" rel="stylesheet">
    <x-app-assets />
</head>
<body class="landing-page">
    <div class="landing-shell">
        <header class="landing-header">
            <a href="{{ url('/') }}" class="landing-brand" aria-label="INTSEC home">
                <img src="{{ Vite::asset('resources/assets/INTSEC.png') }}" alt="INTSEC shield" width="56" height="56">
                <span class="landing-wordmark">INT<span>SEC</span></span>
            </a>
            <div class="landing-header-actions">
                <span class="landing-operational">Security operations ready</span>
                @auth
                    <a href="{{ route('dashboard') }}" class="landing-access">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="landing-access">Sign in</a>
                @endauth
            </div>
        </header>

        <main class="landing-main">
            <section class="landing-hero" aria-labelledby="landing-title">
                <p class="landing-eyebrow">Integrated intrusion monitoring</p>
                <h1 id="landing-title" class="landing-title">Detect. Monitor. <span>Respond.</span></h1>
                <p class="landing-description">A unified security operations platform for monitoring application activity, investigating threats, and coordinating incident response from one trusted workspace.</p>
                <div class="landing-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="landing-primary-action">Enter command center <x-auth.icon name="arrow" /></a>
                    @else
                        <a href="{{ route('login') }}" class="landing-primary-action">Access INTSEC <x-auth.icon name="arrow" /></a>
                    @endauth
                    <a href="#capabilities" class="landing-secondary-action">Explore capabilities</a>
                </div>
            </section>
        </main>

        <section id="capabilities" class="landing-capabilities" aria-label="Platform capabilities">
            <article class="landing-capability"><x-auth.icon name="shield" /><div><strong>Threat monitoring</strong><span>Traceable, deterministic security detection</span></div></article>
            <article class="landing-capability"><x-auth.icon name="chart" /><div><strong>Security intelligence</strong><span>Application activity and actionable context</span></div></article>
            <article class="landing-capability"><x-auth.icon name="network" /><div><strong>Incident response</strong><span>Coordinated investigation and containment</span></div></article>
        </section>
    </div>
</body>
</html>
