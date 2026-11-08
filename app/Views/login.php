<?php /** Login page */ ?>
<div class="login-container">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="d-inline-flex align-items-center justify-content-center mb-3"
                 style="width:3.5rem;height:3.5rem;border-radius:0.875rem;background-color:var(--primary);color:var(--primary-foreground);font-size:1.5rem;">
                <i class="bi bi-shield-check"></i>
            </div>
            <h1 class="h3 mb-1">INSOPHINIA POS</h1>
            <p class="text-muted-fg mb-0" style="font-size:0.875rem;">Sign in to your account</p>
        </div>

        <form id="login-form">
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="bi bi-person me-1"></i> Username
                </label>
                <input type="text" class="form-control" id="username" name="username" autocomplete="username" required autofocus placeholder="Enter username">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="bi bi-key me-1"></i> Password
                </label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required placeholder="Enter password">
                    <button class="btn btn-outline-secondary" type="button" id="toggle-password" tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100" id="login-btn">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
            </button>
        </form>

        <div class="text-center mt-4">
            <p class="text-muted-fg mb-0" style="font-size:0.75rem;">
                Default: <code>master</code> / <code>Master@Admin123!</code>
            </p>
        </div>

        <!-- Theme toggle in corner -->
        <button class="theme-toggle position-absolute" type="button" style="top:1rem;right:1rem;" aria-label="Toggle theme">
            <i class="bi bi-moon-stars"></i>
        </button>
    </div>
</div>

<script>
$(function () {
    // Show/hide password
    $('#toggle-password').on('click', function () {
        const inp = $('#password');
        const icon = $(this).find('i');
        if (inp.attr('type') === 'password') {
            inp.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            inp.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    $('#login-form').on('submit', async function (e) {
        e.preventDefault();
        const btn = $('#login-btn');
        const original = btn.html();
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in…');
        try {
            const username = $('#username').val().trim();
            const password = $('#password').val();
            const r = await Insophinia.auth.login(username, password);
            if (r.success) {
                Insophinia.toast.success('Login successful. Redirecting…');
                setTimeout(() => window.location.href = '/dashboard', 600);
            } else {
                Insophinia.toast.error(r.message || 'Login failed');
                btn.prop('disabled', false).html(original);
            }
        } catch (err) {
            btn.prop('disabled', false).html(original);
        }
    });
});
</script>
