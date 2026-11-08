/* ============================================================================
 * INSOPHINIA POS v7 — app.js (auth + CSRF + AJAX wrapper + toasts)
 * ----------------------------------------------------------------------------
 * Mirrors the useAuth.tsx + use-toast.ts functionality from the Next.js app.
 *
 * Public API:
 *   Insophinia.auth.getUser()       → fetches /api/auth/me, returns user or null
 *   Insophinia.auth.login(u, p)     → POSTs to /api/auth/login
 *   Insophinia.auth.logout()        → POSTs to /api/auth/logout
 *   Insophinia.api.get(url)
 *   Insophinia.api.post(url, body)
 *   Insophinia.api.put(url, body)
 *   Insophinia.api.patch(url, body)
 *   Insophinia.api.delete(url)
 *   Insophinia.toast.success(msg)
 *   Insophinia.toast.error(msg)
 *   Insophinia.toast.info(msg)
 *   Insophinia.toast.warning(msg)
 *   Insophinia.format.money(num)    → "Rs.1,234.56"
 *   Insophinia.format.date(iso)     → "2025-01-15 14:30"
 * ==========================================================================*/

window.Insophinia = (function () {
    'use strict';

    // ---- Toast system (Sonner-like) ----
    const toast = {
        _container: null,
        _ensure() {
            if (!this._container) {
                this._container = document.createElement('div');
                this._container.className = 'toast-container-fixed';
                document.body.appendChild(this._container);
            }
        },
        show(message, type = 'info', duration = 4000) {
            this._ensure();
            const el = document.createElement('div');
            el.className = 'toast-item ' + type;
            el.textContent = message;
            this._container.appendChild(el);
            setTimeout(() => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(8px)';
                setTimeout(() => el.remove(), 250);
            }, duration);
        },
        success(msg, d) { this.show(msg, 'success', d); },
        error(msg, d)   { this.show(msg, 'error', d || 6000); },
        info(msg, d)    { this.show(msg, 'info', d); },
        warning(msg, d) { this.show(msg, 'warning', d); },
    };

    // ---- CSRF token (read from cookie) ----
    function getCsrfToken() {
        const match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }

    // ---- Read auth token from cookie (HttpOnly cookie is not readable by JS,
    // so the browser sends it automatically on each request; we don't need to
    // attach it manually). ----

    // ---- AJAX wrapper ----
    async function request(method, url, body) {
        const opts = {
            method: method,
            headers: {
                'Accept': 'application/json',
            },
            credentials: 'same-origin', // send cookies
        };
        if (body !== undefined && method !== 'GET' && method !== 'DELETE') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
            const csrf = getCsrfToken();
            if (csrf) opts.headers['X-CSRF-Token'] = csrf;
        } else if (method === 'DELETE') {
            const csrf = getCsrfToken();
            if (csrf) opts.headers['X-CSRF-Token'] = csrf;
        }
        let resp;
        try {
            resp = await fetch(url, opts);
        } catch (e) {
            toast.error('Network error: ' + e.message);
            throw e;
        }
        let data = null;
        const ct = resp.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            data = await resp.json();
        } else if (ct.includes('text/html')) {
            // Page response (e.g. PDF preview)
            const text = await resp.text();
            if (resp.ok) return text;
            throw new Error('Server returned HTML');
        } else if (ct.includes('application/pdf')) {
            data = await resp.blob();
            return data;
        }
        if (!resp.ok) {
            const msg = data && data.message ? data.message : 'Request failed (' + resp.status + ')';
            if (resp.status === 401) {
                // Redirect to login
                if (!window.location.pathname.startsWith('/login')) {
                    window.location.href = '/login';
                }
            } else if (resp.status === 419) {
                toast.error('Session expired — refreshing…');
                setTimeout(() => window.location.reload(), 1500);
            } else if (resp.status === 429) {
                toast.error('Rate limit exceeded. Please wait a moment.');
            } else {
                toast.error(msg);
            }
            const err = new Error(msg);
            err.status = resp.status;
            err.data = data;
            throw err;
        }
        return data;
    }

    const api = {
        get:    (url)    => request('GET',    url),
        post:   (url, b) => request('POST',   url, b),
        put:    (url, b) => request('PUT',    url, b),
        patch:  (url, b) => request('PATCH',  url, b),
        delete: (url)    => request('DELETE', url),
    };

    // ---- Auth helpers ----
    const auth = {
        async login(username, password) {
            const r = await api.post('/api/auth/login', { username, password });
            return r;
        },
        async logout() {
            try { await api.post('/api/auth/logout', {}); } catch (e) { /* ignore */ }
            window.location.href = '/login';
        },
        async getUser() {
            try {
                const r = await api.get('/api/auth/me');
                return r.success ? r.data : null;
            } catch (e) {
                return null;
            }
        },
        async getCsrfToken() {
            try {
                const r = await api.get('/api/csrf');
                return r.success ? r.data.token : '';
            } catch (e) {
                return '';
            }
        },
    };

    // ---- Format helpers ----
    const format = {
        money(num) {
            const n = Number(num) || 0;
            return 'Rs.' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        date(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d.getTime())) return iso;
            return d.getFullYear() + '-' +
                   String(d.getMonth() + 1).padStart(2, '0') + '-' +
                   String(d.getDate()).padStart(2, '0') + ' ' +
                   String(d.getHours()).padStart(2, '0') + ':' +
                   String(d.getMinutes()).padStart(2, '0');
        },
        dateOnly(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d.getTime())) return iso;
            return d.getFullYear() + '-' +
                   String(d.getMonth() + 1).padStart(2, '0') + '-' +
                   String(d.getDate()).padStart(2, '0');
        },
    };

    // ---- Pagination renderer (shared) ----
    function renderPagination(p, paginationSelector, infoSelector, loadFn) {
        const pEl = $(paginationSelector);
        const iEl = $(infoSelector);
        if (iEl.length) iEl.text('Page ' + p.page + ' of ' + p.pages + ' (' + p.total + ' items)');
        pEl.empty();
        if (p.pages <= 1) return;
        const addLi = function (label, page, disabled, active) {
            const li = $('<li class="page-item ' + (disabled ? 'disabled' : '') + (active ? 'active' : '') + '">');
            li.append('<a class="page-link" href="#">' + label + '</a>');
            if (!disabled) li.on('click', function (e) { e.preventDefault(); loadFn(page); });
            pEl.append(li);
        };
        addLi('«', p.page - 1, p.page === 1, false);
        // Show up to 7 page numbers around the current
        const start = Math.max(1, p.page - 3);
        const end = Math.min(p.pages, p.page + 3);
        if (start > 1) addLi('1', 1, false, p.page === 1);
        if (start > 2) pEl.append('<li class="page-item disabled"><span class="page-link">…</span></li>');
        for (let i = start; i <= end; i++) addLi(String(i), i, false, i === p.page);
        if (end < p.pages - 1) pEl.append('<li class="page-item disabled"><span class="page-link">…</span></li>');
        if (end < p.pages) addLi(String(p.pages), p.pages, false, p.page === p.pages);
        addLi('»', p.page + 1, p.page === p.pages, false);
    }

    return { toast, api, auth, format, request, getCsrfToken, renderPagination };
})();
