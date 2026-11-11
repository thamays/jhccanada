<?php
/** Routing, authentication, layout and flash messages for /jhc-admin/. */
class JHC_Panel {
    private static $modules = [];

    public static function register(JHC_Module $m) { self::$modules[$m->slug()] = $m; }
    public static function modules() { return self::$modules; }

    public static function add_rewrite() {
        add_rewrite_rule('^' . JHC_ADMIN_SLUG . '(?:/([^/]+))?/?$', 'index.php?jhc_admin=1&jhc_module=$matches[1]', 'top');
    }
    public static function boot() {
        self::add_rewrite();
        add_filter('query_vars', fn($v) => array_merge($v, ['jhc_admin', 'jhc_module']));
        add_action('template_redirect', [__CLASS__, 'route']);
    }

    public static function can() { return is_user_logged_in() && current_user_can('manage_options'); }
    public static function url($module = '', $args = []) {
        return add_query_arg($args, home_url('/' . JHC_ADMIN_SLUG . '/' . ($module ? $module . '/' : '')));
    }

    /* ----- flash messages (survive one redirect) ----- */
    public static function flash($msg, $type = 'ok') { set_transient('jhc_flash_' . get_current_user_id(), [$type, $msg], 60); }
    private static function take_flash() {
        $k = 'jhc_flash_' . get_current_user_id(); $f = get_transient($k); delete_transient($k); return $f;
    }
    public static function redirect($url) { wp_safe_redirect($url); exit; }

    /* ----- router ----- */
    public static function route() {
        if (!get_query_var('jhc_admin')) return;
        nocache_headers();
        $err = '';
        if (isset($_POST['jhc_login']) && check_admin_referer('jhc_login')) {
            $u = wp_signon(['user_login' => wp_unslash($_POST['log'] ?? ''), 'user_password' => wp_unslash($_POST['pwd'] ?? ''), 'remember' => true], is_ssl());
            if (is_wp_error($u)) $err = 'Incorrect username or password.'; else self::redirect(self::url());
        }
        if (($_GET['do'] ?? '') === 'logout' && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'jhc_logout')) { wp_logout(); self::redirect(self::url()); }
        if (!self::can()) { self::login_view($err); exit; }

        $slug = sanitize_title(get_query_var('jhc_module'));
        $mod = self::$modules[$slug] ?? null;
        if (!$mod && $slug === '') $mod = reset(self::$modules);
        if (!$mod) { status_header(404); $mod = reset(self::$modules); }

        ob_start(); $mod->dispatch(); $body = ob_get_clean();   // dispatch may redirect before output
        self::layout($mod, $body); exit;
    }

    /* ----- views ----- */
    private static function head($title) { ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html($title); ?> – JHCA Admin</title>
<link rel="stylesheet" href="<?php echo esc_url(JHC_URL . 'assets/admin.css?v=' . filemtime(JHC_DIR . 'assets/admin.css')); ?>">
</head><?php }

    private static function login_view($err) { self::head('Log in'); ?>
<body class="login"><div class="card login-card"><h1>JHCA Canada Admin</h1>
<?php if ($err) echo '<div class="note bad">' . esc_html($err) . '</div>'; ?>
<form method="post"><?php wp_nonce_field('jhc_login'); ?>
<label>Username or email</label><input name="log" autocomplete="username" required autofocus>
<label>Password</label><input type="password" name="pwd" autocomplete="current-password" required>
<p><button class="btn" name="jhc_login" value="1">Log in</button></p></form></div></body></html>
<?php }

    private static function layout(JHC_Module $active, $body) {
        $flash = self::take_flash(); $user = wp_get_current_user();
        self::head($active->label()); ?>
<body><div class="shell">
<aside class="side" id="side">
  <div class="brand">JHCA Canada<small>Admin panel</small></div>
  <nav>
  <?php foreach (self::$modules as $m) printf('<a href="%s" class="%s"><span class="ic">%s</span>%s</a>',
      esc_url(self::url($m instanceof JHC_Dashboard ? '' : $m->slug())), $m === $active ? 'on' : '', esc_html($m->icon()), esc_html($m->label())); ?>
  </nav>
  <div class="side-foot"><a href="<?php echo esc_url(home_url('/')); ?>" target="_blank">View site ↗</a>
  <a href="<?php echo esc_url(self::url('', ['do' => 'logout', '_wpnonce' => wp_create_nonce('jhc_logout')])); ?>">Log out (<?php echo esc_html($user->user_login); ?>)</a></div>
</aside>
<div class="main">
  <header class="bar-top"><button class="burger" onclick="document.getElementById('side').classList.toggle('open')" aria-label="Menu">☰</button>
  <b><?php echo esc_html($active->label()); ?></b></header>
  <main class="content">
  <?php if ($flash) echo '<div class="note ' . esc_attr($flash[0]) . '">' . esc_html($flash[1]) . '</div>'; echo $body; ?>
  </main>
</div></div></body></html>
<?php }
}
