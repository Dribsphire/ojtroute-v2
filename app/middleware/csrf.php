<?php
/**
 * CSRF protection for every logged-in page.
 *
 * Loaded by the auth middlewares (requireAdmin/Instructor/Student/Auth), so any
 * page using one is covered automatically.
 *
 * - Every POST must carry the session's token, either as a form field
 *   `csrf_token` or an `X-CSRF-Token` header. Otherwise: 403.
 * - Pages don't need changes: a small script is inserted before </head> that adds
 *   the token to every POST form, fetch() and XMLHttpRequest automatically.
 */

if (defined('OJT_CSRF_LOADED')) {
    return;
}
define('OJT_CSRF_LOADED', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token()
{
    return $_SESSION['csrf_token'];
}

// ---- Verify state-changing requests ------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(403);
        // A normal form submit (page navigation) gets an HTML page; fetch/AJAX gets JSON
        $fetchMode = $_SERVER['HTTP_SEC_FETCH_MODE'] ?? '';
        $isNavigation = $fetchMode === 'navigate'
            || ($fetchMode === '' && stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') !== false);
        $wantsJson = !$isNavigation;
        $msg = 'Your session expired or the request was not sent from this site. Please reload the page and try again.';
        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg, 'error' => $msg]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Request blocked</title></head><body style="font-family:sans-serif;padding:2rem">'
                . '<h2>Request blocked</h2><p>' . htmlspecialchars($msg) . '</p><p><a href="javascript:history.back()">Go back</a></p></body></html>';
        }
        exit;
    }
}

// ---- Put the token into every HTML page ---------------------------------------
ob_start(function ($buffer) {
    if (stripos($buffer, '</head>') === false || stripos($buffer, 'name="csrf-token"') !== false) {
        return $buffer;
    }
    $token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
    $script = <<<HTML
<meta name="csrf-token" content="{$token}">
<script>
(function () {
    var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    function sameOrigin(url) {
        try { return new URL(url, location.href).origin === location.origin; } catch (e) { return true; }
    }
    function addField(form) {
        if ((form.method || '').toLowerCase() !== 'post' || form.querySelector('input[name="csrf_token"]')) return;
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'csrf_token'; i.value = token;
        form.appendChild(i);
    }
    // Forms: normal submit and form.submit()
    document.addEventListener('submit', function (e) { addField(e.target); }, true);
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () { addField(this); return nativeSubmit.call(this); };
    // fetch()
    if (window.fetch) {
        var nativeFetch = window.fetch;
        window.fetch = function (input, init) {
            init = init || {};
            var method = (init.method || (input && input.method) || 'GET').toUpperCase();
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            if (method !== 'GET' && method !== 'HEAD' && sameOrigin(url)) {
                var headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
                headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
            return nativeFetch.call(this, input, init);
        };
    }
    // XMLHttpRequest (jQuery etc.)
    var nativeOpen = XMLHttpRequest.prototype.open, nativeSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) {
        this._csrfNeeded = String(method).toUpperCase() !== 'GET' && sameOrigin(url);
        return nativeOpen.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () {
        if (this._csrfNeeded) this.setRequestHeader('X-CSRF-Token', token);
        return nativeSend.apply(this, arguments);
    };
})();
</script>
HTML;
    return preg_replace('~</head>~i', $script . '</head>', $buffer, 1);
});
