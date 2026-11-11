<?php
/**
 * Generic "numbered member list" section (ID prefix + number, title, first/last name).
 * Config: slug,label,singular,icon,table,prefix,pad,page (public page slug),shortcode
 */
class JHC_Members_Module extends JHC_Module {
    const TITLES = ['Mr.', 'Mrs.', 'Ms.', 'Dr.', 'Prof.', 'Rev.'];
    const PER_PAGE = 50;
    private $c;

    public function __construct(array $config) {
        $this->c = $config;
        add_shortcode($config['shortcode'], [$this, 'shortcode']);
    }
    public function slug()  { return $this->c['slug']; }
    public function label() { return $this->c['label']; }
    public function icon()  { return $this->c['icon']; }
    private function t() { global $wpdb; return $wpdb->prefix . $this->c['table']; }
    private function code($no) { return $this->c['prefix'] . str_pad((string)$no, $this->c['pad'], '0', STR_PAD_LEFT); }
    private function url($args = []) { return JHC_Panel::url($this->slug(), $args); }

    /* ----- install / import legacy page table ----- */
    public function install() {
        global $wpdb; $t = $this->t();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $t (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_no INT UNSIGNED NOT NULL,
            salutation VARCHAR(20) NOT NULL DEFAULT 'Mr.',
            first_name VARCHAR(120) NOT NULL,
            last_name VARCHAR(120) NOT NULL DEFAULT '',
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY member_no (member_no)
        ) " . $wpdb->get_charset_collate() . ';');
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $t") > 0) return;
        $page = get_page_by_path($this->c['page']);
        if (!$page || strpos($page->post_content, '<table') === false) return;
        preg_match_all('#<tr>(.*?)</tr>#s', $page->post_content, $rows);
        $re = '/^' . preg_quote($this->c['prefix'], '/') . '(\d+)$/';
        foreach ($rows[1] as $row) {
            preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $cells);
            $c = array_map(fn($x) => trim(html_entity_decode(wp_strip_all_tags($x))), $cells[1]);
            if (count($c) < 4 || !preg_match($re, $c[0], $m)) continue;
            $wpdb->replace($t, ['member_no' => (int)$m[1], 'salutation' => $c[1], 'first_name' => $c[2], 'last_name' => $c[3], 'updated_at' => current_time('mysql')]);
        }
        wp_update_post(['ID' => $page->ID, 'post_content' => '[' . $this->c['shortcode'] . ']']);
    }

    /* ----- public page ----- */
    public function shortcode() {
        global $wpdb; $t = $this->t();
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY member_no");
        $upd = $wpdb->get_var("SELECT MAX(updated_at) FROM $t");
        ob_start();
        if ($upd) echo '<p><em>Updated on ' . esc_html(mysql2date('M j, Y', $upd)) . '</em></p>';
        echo '<table><tbody><tr><td>Member ID</td><td>Salutation</td><td>First Name</td><td>Last Name</td></tr>';
        foreach ($rows as $r) printf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            esc_html($this->code($r->member_no)), esc_html($r->salutation), esc_html($r->first_name), esc_html($r->last_name));
        return ob_get_clean() . '</tbody></table>';
    }

    /* ----- dashboard hooks ----- */
    public function stats() {
        global $wpdb; return ['label' => $this->label(), 'count' => (int)$wpdb->get_var('SELECT COUNT(*) FROM ' . $this->t())];
    }
    public function recent($n = 5) {
        global $wpdb; $out = [];
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $this->t() . ' ORDER BY updated_at DESC, id DESC LIMIT %d', $n)) as $r)
            $out[] = ['title' => $this->code($r->member_no) . ' · ' . trim($r->first_name . ' ' . $r->last_name), 'time' => $r->updated_at, 'url' => $this->url(['do' => 'edit', 'id' => $r->id])];
        return $out;
    }
    public function quick_actions() { return [['label' => '+ Add ' . $this->c['singular'], 'url' => $this->url(['do' => 'new'])]]; }

    /* ----- controller ----- */
    public function dispatch() {
        global $wpdb; $t = $this->t(); $do = $_GET['do'] ?? '';
        if (isset($_POST['jhc_save']) && check_admin_referer('jhc_save_' . $this->slug())) return $this->save();
        if ($do === 'delete' && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'jhc_del_' . $this->slug())) {
            $wpdb->delete($t, ['id' => (int)($_GET['id'] ?? 0)]);
            JHC_Panel::flash(ucfirst($this->c['singular']) . ' deleted.'); JHC_Panel::redirect($this->url());
        }
        if ($do === 'new') {
            $next = 1 + (int)$wpdb->get_var("SELECT MAX(member_no) FROM $t");
            return $this->form((object)['id' => 0, 'member_no' => $next, 'salutation' => 'Mr.', 'first_name' => '', 'last_name' => '']);
        }
        if ($do === 'edit' && ($m = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", (int)($_GET['id'] ?? 0))))) return $this->form($m);
        $this->listing();
    }

    private function save() {
        global $wpdb; $t = $this->t();
        $id = (int)($_POST['id'] ?? 0); $no = (int)($_POST['member_no'] ?? 0);
        $m = (object)['id' => $id, 'member_no' => $no,
            'salutation' => in_array($_POST['salutation'] ?? '', self::TITLES, true) ? $_POST['salutation'] : 'Mr.',
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? ''))];
        if ($no < 1 || $m->first_name === '') return $this->form($m, 'Member number and first name are required.');
        if ($wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE member_no=%d AND id<>%d", $no, $id)))
            return $this->form($m, $this->code($no) . ' already exists.');
        $data = ['member_no' => $no, 'salutation' => $m->salutation, 'first_name' => $m->first_name, 'last_name' => $m->last_name, 'updated_at' => current_time('mysql')];
        $id ? $wpdb->update($t, $data, ['id' => $id]) : $wpdb->insert($t, $data);
        JHC_Panel::flash($this->code($no) . ($id ? ' updated.' : ' added.'));
        JHC_Panel::redirect($this->url());
    }

    /* ----- views ----- */
    private function listing() {
        global $wpdb; $t = $this->t();
        $q = trim(wp_unslash($_GET['q'] ?? '')); $pg = max(1, (int)($_GET['pg'] ?? 1));
        $where = ''; $args = [];
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where = "WHERE first_name LIKE %s OR last_name LIKE %s OR CONCAT(%s, LPAD(member_no, %d, '0')) LIKE %s";
            $args = [$like, $like, $this->c['prefix'], $this->c['pad'], $like];
        }
        $prep = fn($sql, $extra = []) => ($a = array_merge($args, $extra)) ? $wpdb->prepare($sql, $a) : $sql;
        $count = (int)$wpdb->get_var($prep("SELECT COUNT(*) FROM $t $where"));
        $rows = $wpdb->get_results($prep("SELECT * FROM $t $where ORDER BY member_no LIMIT %d OFFSET %d", [self::PER_PAGE, ($pg - 1) * self::PER_PAGE]));
        $pages = max(1, (int)ceil($count / self::PER_PAGE)); ?>
<div class="page-head"><h1><?php echo esc_html($this->label()); ?> <small><?php echo $count . ($q !== '' ? ' found' : ' total'); ?></small></h1>
<a class="btn" href="<?php echo esc_url($this->url(['do' => 'new'])); ?>">+ Add <?php echo esc_html($this->c['singular']); ?></a></div>
<div class="card">
<form method="get" class="search"><input type="search" name="q" value="<?php echo esc_attr($q); ?>" placeholder="Search name or ID…"><button class="btn sec">Search</button>
<?php if ($q !== '') echo '<a class="btn sec" href="' . esc_url($this->url()) . '">Clear</a>'; ?></form>
<div class="scroll"><table><thead><tr><th>ID</th><th class="hide-sm">Title</th><th>First name</th><th>Last name</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r) : $code = $this->code($r->member_no); ?>
<tr><td><?php echo esc_html($code); ?></td><td class="hide-sm"><?php echo esc_html($r->salutation); ?></td><td><?php echo esc_html($r->first_name); ?></td><td><?php echo esc_html($r->last_name); ?></td>
<td class="acts"><a class="btn sec sm" href="<?php echo esc_url($this->url(['do' => 'edit', 'id' => $r->id])); ?>">Edit</a>
<a class="btn del sm" onclick="return confirm('Delete <?php echo esc_js($code); ?>?')" href="<?php echo esc_url($this->url(['do' => 'delete', 'id' => $r->id, '_wpnonce' => wp_create_nonce('jhc_del_' . $this->slug())])); ?>">Delete</a></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="5">Nothing found.</td></tr>'; ?>
</tbody></table></div>
<?php if ($pages > 1) : ?><div class="pager"><?php for ($i = 1; $i <= $pages; $i++)
    printf('<a class="%s" href="%s">%d</a>', $i === $pg ? 'on' : '', esc_url($this->url(array_filter(['q' => $q, 'pg' => $i]))), $i); ?></div><?php endif; ?>
</div>
<?php }

    private function form($m, $err = '') { ?>
<div class="page-head"><h1><?php echo $m->id ? 'Edit' : 'Add'; ?> <?php echo esc_html($this->c['singular']); ?></h1></div>
<div class="card narrow">
<?php if ($err) echo '<div class="note bad">' . esc_html($err) . '</div>'; ?>
<form method="post"><?php wp_nonce_field('jhc_save_' . $this->slug()); ?><input type="hidden" name="id" value="<?php echo (int)$m->id; ?>">
<div class="grid">
<div><label>Member number (<?php echo esc_html($this->c['prefix']); ?>…)</label><input type="number" min="1" name="member_no" value="<?php echo (int)$m->member_no; ?>" required></div>
<div><label>Title</label><select name="salutation"><?php foreach (self::TITLES as $s) printf('<option%s>%s</option>', selected($m->salutation, $s, false), esc_html($s)); ?></select></div>
<div><label>First name</label><input name="first_name" value="<?php echo esc_attr($m->first_name); ?>" required autofocus></div>
<div><label>Last name</label><input name="last_name" value="<?php echo esc_attr($m->last_name); ?>"></div></div>
<p class="form-actions"><button class="btn" name="jhc_save" value="1">Save</button> <a class="btn sec" href="<?php echo esc_url($this->url()); ?>">Cancel</a></p>
</form></div>
<?php }
}
