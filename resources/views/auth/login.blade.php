<x-layouts.auth title="Sign in - {{ config('app.name', 'INTSEC') }}" immersive>
    <div class="login-shell">
        <header class="login-header">
            <a href="{{ route('login') }}" class="login-home" aria-label="INTSEC sign in"><x-auth.login-brand /></a>
            <div class="login-status">
                <span class="login-status-primary"><span class="login-status-dot" aria-hidden="true"></span>Security operations</span>
                <span class="login-connection"><x-auth.icon name="lock" />{{ request()->isSecure() ? 'Secure connection' : 'Protected access' }}</span>
            </div>
        </header>

        <main class="login-main">
            <section class="login-introduction" aria-labelledby="platform-title">
                <p class="login-eyebrow">Cybersecurity monitoring</p>
                <h1 id="platform-title" class="login-platform-name">INT<span>SEC</span></h1>
                <p class="login-motto">Detect <span aria-hidden="true">•</span> Monitor <span aria-hidden="true">•</span> Respond</p>
                <p class="login-description">A unified security monitoring platform for a safer, more resilient digital environment.</p>
                <ul class="login-features">
                    <li><x-auth.icon name="shield" /><span>Real-time threat monitoring</span></li>
                    <li><x-auth.icon name="chart" /><span>Security analytics &amp; insights</span></li>
                    <li><x-auth.icon name="network" /><span>Incident management</span></li>
                    <li><x-auth.icon name="gear" /><span>Application-level protection</span></li>
                </ul>
            </section>


            <section class="login-card" aria-labelledby="signin-title">
                <div class="login-card-heading">
                    <x-auth.login-brand />
                    <h2 id="signin-title">Sign in to INTSEC</h2>
                    <p>Access your security monitoring dashboard.<br><span>Authorized personnel only.</span></p>
                </div>

                @if(session('status'))
                    <p class="login-notice" role="status">{{ session('status') }}</p>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="login-form">
                    @csrf
                    <div class="login-field">
                        <label for="email">Email address</label>
                        <div class="login-input-wrap">
                            <x-auth.icon name="mail" />
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="name@company.com" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')<p id="email-error" class="login-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <div class="login-input-wrap">
                            <x-auth.icon name="lock" />
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" class="login-password-toggle" data-password-toggle aria-label="Show password" aria-controls="password" aria-pressed="false"><x-auth.icon name="eye" /></button>
                        </div>
                        @error('password')<p id="password-error" class="login-error" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="login-options">
                        <label class="login-remember"><input name="remember" type="checkbox" value="1" @checked(old('remember'))><span>Remember me</span></label>
                        <a href="#login-help">Need help signing in?</a>
                    </div>

                    <div class="login-captcha">
                        @if($recaptchaSiteKey !== '')
                            <div class="g-recaptcha" data-sitekey="{{ $recaptchaSiteKey }}" data-theme="dark"></div>
                        @else
                            <p class="login-captcha-unavailable" role="alert">Sign-in verification is not configured. Please contact an administrator.</p>
                        @endif
                    </div>
                    @error('g-recaptcha-response')<p class="login-error" role="alert">{{ $message }}</p>@enderror

                    <button type="submit" class="login-submit"><span>Sign in</span><x-auth.icon name="arrow" /></button>
                </form>
                <p class="login-card-security"><x-auth.icon name="lock" />Your access is protected by multi-factor authentication.</p>
            </section>
        </main>

        <footer class="login-footer">
            <div class="login-footer-platform"><p><x-auth.icon name="lock" /><span>INTSEC</span><span class="login-footer-divider" aria-hidden="true">|</span>Security Operations Platform</p><p>Authorized access only. All activity is monitored and logged.</p></div>
            <p id="login-help" class="login-help">Need access or help? <span>Contact your system administrator.</span></p>
        </footer>
    </div>
    @if($recaptchaSiteKey !== '')
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
</x-layouts.auth>
