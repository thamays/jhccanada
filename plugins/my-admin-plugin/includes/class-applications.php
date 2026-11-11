<?php
/**
 * Membership applications: public web form ([jhc_membership_form]) + admin review.
 * An application only becomes a member in the Life/Annual list once an admin approves it.
 */
class JHC_Applications extends JHC_Module {
    const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
    const TYPES = ['life' => ['Life', 150, 'life-members'], 'annual' => ['Annual', 20, 'annual-members']];

    public function __construct() {
        add_shortcode('jhc_membership_form', [$this, 'form_shortcode']);
        add_action('template_redirect', [$this, 'handle_public_post'], 9);
    }
    public function slug()  { return 'applications'; }
    public function label() { return 'Applications'; }
    public function icon()  { return '✉'; }
    private function t() { global $wpdb; return $wpdb->prefix . 'jhc_applications'; }
    private function url($args = []) { return JHC_Panel::url($this->slug(), $args); }
    private function pending() { global $wpdb; return (int)$wpdb->get_var("SELECT COUNT(*) FROM " . $this->t() . " WHERE status='pending'"); }
    public function badge() { return $this->pending(); }

    public function install() {
        global $wpdb; $t = $this->t();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE $t (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            title VARCHAR(20) NOT NULL DEFAULT 'Mr.',
            last_name VARCHAR(120) NOT NULL,
            first_name VARCHAR(120) NOT NULL,
            address TEXT NOT NULL,
            mobile VARCHAR(40) NOT NULL,
            email VARCHAR(190) NOT NULL,
            edu_from SMALLINT UNSIGNED NULL,
            edu_to SMALLINT UNSIGNED NULL,
            batch_ol SMALLINT UNSIGNED NULL,
            batch_al SMALLINT UNSIGNED NULL,
            membership_type VARCHAR(10) NOT NULL,
            signature VARCHAR(190) NOT NULL,
            signed_date DATE NOT NULL,
            member_code VARCHAR(20) NOT NULL DEFAULT '',
            admin_note TEXT NULL,
            reviewed_by VARCHAR(60) NOT NULL DEFAULT '',
            reviewed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) " . $wpdb->get_charset_collate() . ';');
        // Replace the old embedded Google Form with the native one (original content kept in post meta).
        $page = get_page_by_path('membership-online');
        if ($page && strpos($page->post_content, 'jhc_membership_form') === false) {
            update_post_meta($page->ID, '_jhc_old_content', $page->post_content);
            wp_update_post(['ID' => $page->ID, 'post_content' => '[jhc_membership_form]']);
        }
    }

    /* =================== public form =================== */
    private function fields_from_post() {
        $g = fn($k) => sanitize_text_field(wp_unslash($_POST[$k] ?? ''));
        $yr = fn($k) => ($v = (int)($_POST[$k] ?? 0)) >= 1900 && $v <= (int)date('Y') + 1 ? $v : null;
        return ['title' => in_array($g('title'), JHC_Members_Module::TITLES, true) ? $g('title') : 'Mr.',
            'last_name' => $g('last_name'), 'first_name' => $g('first_name'),
            'address' => sanitize_textarea_field(wp_unslash($_POST['address'] ?? '')), 'mobile' => $g('mobile'),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'edu_from' => $yr('edu_from'), 'edu_to' => $yr('edu_to'), 'batch_ol' => $yr('batch_ol'), 'batch_al' => $yr('batch_al'),
            'membership_type' => isset(self::TYPES[$_POST['membership_type'] ?? '']) ? $_POST['membership_type'] : '',
            'signature' => $g('signature'), 'signed_date' => $g('signed_date')];
    }

    public function handle_public_post() {
        if (empty($_POST['jhc_apply'])) return;
        $GLOBALS['jhc_apply_result'] = ['errors' => [], 'values' => []];
        $r = &$GLOBALS['jhc_apply_result'];
        if (!wp_verify_nonce($_POST['_jhcnonce'] ?? '', 'jhc_apply')) { $r['errors'][] = 'Your session expired. Please reload the page and try again.'; return; }
        if (!empty($_POST['website'])) { wp_safe_redirect(add_query_arg('applied', 1, get_permalink())); exit; } // honeypot: pretend success
        $d = $r['values'] = $this->fields_from_post(); $e = &$r['errors'];
        if ($d['last_name'] === '')  $e[] = 'Last name is required.';
        if ($d['first_name'] === '') $e[] = 'First name is required.';
        if ($d['address'] === '')    $e[] = 'Address is required.';
        if ($d['mobile'] === '' || !preg_match('/^[0-9 +().-]{7,25}$/', $d['mobile'])) $e[] = 'Please enter a valid mobile number.';
        if (!is_email($d['email'])) $e[] = 'Please enter a valid email address.';
        if (!$d['batch_ol'] && !$d['batch_al']) $e[] = 'Please enter your O/L or A/L batch year.';
        if (!$d['membership_type']) $e[] = 'Please choose a type of membership.';
        foreach (['agree_display', 'agree_contact', 'agree_constitution'] as $k) if (empty($_POST[$k])) { $e[] = 'Please tick all three agreements.'; break; }
        if ($d['signature'] === '') $e[] = 'Please type your full name as your signature.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['signed_date'])) $e[] = 'Please enter the date.';
        $ip = 'jhc_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? ''); $n = (int)get_transient($ip);
        if ($n >= 5) $e[] = 'Too many submissions from your connection. Please try again later.';
        if ($e) return;
        global $wpdb;
        $wpdb->insert($this->t(), array_merge($d, ['created_at' => current_time('mysql'), 'status' => 'pending']));
        set_transient($ip, $n + 1, HOUR_IN_SECONDS);
        wp_safe_redirect(add_query_arg('applied', 1, get_permalink())); exit;
    }

    public function form_shortcode() {
        wp_enqueue_style('jhc-form', JHC_URL . 'assets/form.css', [], filemtime(JHC_DIR . 'assets/form.css'));
        if (!empty($_GET['applied'])) return '<div class="jhc-form"><div class="jhc-ok"><h3>Thank you!</h3><p>Your application has been received. It will be reviewed by the association, and you will be added to the members list once it is approved. Please complete your payment using the instructions below.</p></div>' . $this->payment_box() . '</div>';
        $res = $GLOBALS['jhc_apply_result'] ?? ['errors' => [], 'values' => []]; $v = $res['values'] + ['title' => 'Mr.', 'last_name' => '', 'first_name' => '', 'address' => '', 'mobile' => '', 'email' => '', 'edu_from' => '', 'edu_to' => '', 'batch_ol' => '', 'batch_al' => '', 'membership_type' => '', 'signature' => '', 'signed_date' => date('Y-m-d')];
        $e = fn($s) => esc_attr((string)$s);
        ob_start(); ?>
<div class="jhc-form">
<p class="jhc-head"><b>Jaffna Hindu College Association – Canada</b><br>Membership Application</p>
<?php if ($res['errors']) { echo '<div class="jhc-err"><ul>'; foreach ($res['errors'] as $m) echo '<li>' . esc_html($m) . '</li>'; echo '</ul></div>'; } ?>
<form method="post" novalidate>
<input type="hidden" name="_jhcnonce" value="<?php echo esc_attr(wp_create_nonce('jhc_apply')); ?>">
<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="jhc-row"><label>Title<select name="title"><?php foreach (JHC_Members_Module::TITLES as $t) printf('<option%s>%s</option>', selected($v['title'], $t, false), esc_html($t)); ?></select></label></div>
<div class="jhc-grid">
<label>1. Last name *<input name="last_name" value="<?php echo $e($v['last_name']); ?>" required maxlength="120"></label>
<label>2. First name *<input name="first_name" value="<?php echo $e($v['first_name']); ?>" required maxlength="120"></label></div>
<label>3. Address *<textarea name="address" rows="3" required><?php echo esc_textarea($v['address']); ?></textarea></label>
<div class="jhc-grid">
<label>4. Mobile number *<input type="tel" name="mobile" value="<?php echo $e($v['mobile']); ?>" required maxlength="25"></label>
<label>5. Email address *<input type="email" name="email" value="<?php echo $e($v['email']); ?>" required maxlength="190"></label></div>
<fieldset><legend>6. Period of education in the college</legend><div class="jhc-grid">
<label>From (year)<input type="number" name="edu_from" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo $e($v['edu_from']); ?>" placeholder="e.g. 1990"></label>
<label>To (year)<input type="number" name="edu_to" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo $e($v['edu_to']); ?>" placeholder="e.g. 2003"></label></div></fieldset>
<fieldset><legend>7. Your batch year</legend><div class="jhc-grid">
<label>O/L<input type="number" name="batch_ol" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo $e($v['batch_ol']); ?>"></label>
<label>A/L<input type="number" name="batch_al" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo $e($v['batch_al']); ?>"></label></div></fieldset>
<fieldset><legend>8. Type of membership *</legend><div class="jhc-grid">
<?php foreach (self::TYPES as $k => $t) printf('<label class="jhc-opt"><input type="radio" name="membership_type" value="%s"%s> %s – $%s.00</label>', $k, checked($v['membership_type'], $k, false), esc_html($t[0]), number_format($t[1], 2, '.', '')); ?></div></fieldset>
<div class="jhc-agree">
<label><input type="checkbox" name="agree_display" value="1" <?php checked(!empty($_POST['agree_display'])); ?>> I agree to display my first and last names with no other personal information on the Jaffna Hindu College Association – Canada website.</label>
<label><input type="checkbox" name="agree_contact" value="1" <?php checked(!empty($_POST['agree_contact'])); ?>> I also agree to receive phone call, text and social media communication only related to Jaffna Hindu College Association.</label>
<label><input type="checkbox" name="agree_constitution" value="1" <?php checked(!empty($_POST['agree_constitution'])); ?>> I agree to abide by the constitution of Jaffna Hindu College Association – Canada.</label></div>
<div class="jhc-grid">
<label>Signature of applicant (type your full name) *<input name="signature" value="<?php echo $e($v['signature']); ?>" required maxlength="190"></label>
<label>Date *<input type="date" name="signed_date" value="<?php echo $e($v['signed_date']); ?>" required></label></div>
<p><button class="jhc-btn" type="submit" name="jhc_apply" value="1">Submit application</button></p>
</form>
<?php echo $this->payment_box(); ?>
</div>
<?php return ob_get_clean();
    }

    private function payment_box() {
        return '<div class="jhc-pay"><h3>Payment</h3><ul>
<li>Payment by e-transfer is recommended: send the funds to <b>treasurer@jaffnahinducanada.com</b> and add your full name in the comment. <span class="red">(Please don’t include the answer to the security question in the comment or send it by email.)</span> Provide the answer to the security question by calling or sending SMS directly to the treasurer’s phone number on this website, and include the applicant’s full name.</li>
<li>Please contact the Treasurer at treasurer@jaffnahinducanada.com for payment by cheque or cash.</li>
<li>You may deposit directly at any Royal Bank branch and send the deposit slip to us, adding the applicant’s full name. Account Number # 101 1949 · Bank Transit # 00022 · RBC Bank Number # 003</li>
<li><b>New members:</b> after submitting this form, email the proof of payment to secretary@jaffnahinducanada.com and cc treasurer@jaffnahinducanada.com.</li>
<li><b>Renewal of annual members:</b> email proof of payment to secretary@jaffnahinducanada.com and cc treasurer@jaffnahinducanada.com, with any updates to address, telephone number or e-mail. <span class="red">Annual members can upgrade to lifetime by paying the fee difference of $130 one month before the expiration of the annual membership.</span></li></ul></div>';
    }

    /* =================== admin =================== */
    public function stats() { return ['label' => 'Pending applications', 'count' => $this->pending()]; }
    public function recent($n = 5) {
        global $wpdb; $o = [];
        foreach ($wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $this->t() . ' ORDER BY created_at DESC LIMIT %d', $n)) as $r)
            $o[] = ['title' => 'Application · ' . trim($r->first_name . ' ' . $r->last_name) . ' (' . self::STATUSES[$r->status] . ')', 'time' => $r->created_at, 'url' => $this->url(['do' => 'view', 'id' => $r->id])];
        return $o;
    }
    public function quick_actions() { return $this->pending() ? [['label' => 'Review ' . $this->pending() . ' pending', 'url' => $this->url()]] : []; }

    private function get($id) { global $wpdb; return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $this->t() . ' WHERE id=%d', $id)); }

    public function dispatch() {
        global $wpdb; $do = $_GET['do'] ?? ''; $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        if (isset($_POST['jhc_approve']) && check_admin_referer('jhc_app_' . $id)) return $this->approve($id);
        if (isset($_POST['jhc_reject']) && check_admin_referer('jhc_app_' . $id)) {
            $wpdb->update($this->t(), ['status' => 'rejected', 'admin_note' => sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? '')), 'reviewed_by' => wp_get_current_user()->user_login, 'reviewed_at' => current_time('mysql')], ['id' => $id, 'status' => 'pending']);
            JHC_Panel::flash('Application rejected.'); JHC_Panel::redirect($this->url());
        }
        if ($do === 'delete' && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'jhc_appdel_' . $id)) {
            $wpdb->delete($this->t(), ['id' => $id, 'status' => 'rejected']);
            JHC_Panel::flash('Application deleted.'); JHC_Panel::redirect($this->url(['status' => 'rejected']));
        }
        if ($do === 'view' && ($a = $this->get($id))) return $this->view($a);
        $this->listing();
    }

    private function approve($id) {
        global $wpdb; $a = $this->get($id);
        if (!$a || $a->status !== 'pending') { JHC_Panel::flash('That application is no longer pending.', 'bad'); JHC_Panel::redirect($this->url()); }
        $mod = JHC_Panel::modules()[self::TYPES[$a->membership_type][2]] ?? null;
        $g = fn($k) => sanitize_text_field(wp_unslash($_POST[$k] ?? ''));
        $res = $mod->add_member($g('title'), $g('first_name'), $g('last_name'), (int)($_POST['member_no'] ?? 0));
        if (is_wp_error($res)) { JHC_Panel::flash($res->get_error_message(), 'bad'); JHC_Panel::redirect($this->url(['do' => 'view', 'id' => $id])); }
        $wpdb->update($this->t(), ['status' => 'approved', 'member_code' => $res, 'title' => $g('title'), 'first_name' => $g('first_name'), 'last_name' => $g('last_name'),
            'admin_note' => sanitize_textarea_field(wp_unslash($_POST['admin_note'] ?? '')), 'reviewed_by' => wp_get_current_user()->user_login, 'reviewed_at' => current_time('mysql')], ['id' => $id]);
        JHC_Panel::flash("Approved and added to the members list as $res."); JHC_Panel::redirect($this->url());
    }

    private function listing() {
        global $wpdb; $t = $this->t(); $st = $_GET['status'] ?? 'pending'; if ($st !== 'all' && !isset(self::STATUSES[$st])) $st = 'pending';
        $counts = array_column($wpdb->get_results("SELECT status, COUNT(*) c FROM $t GROUP BY status", ARRAY_A), 'c', 'status');
        $rows = $st === 'all' ? $wpdb->get_results("SELECT * FROM $t ORDER BY created_at DESC LIMIT 300")
            : $wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE status=%s ORDER BY created_at DESC LIMIT 300", $st)); ?>
<div class="page-head"><h1>Membership applications</h1></div>
<div class="tabs"><?php foreach (self::STATUSES + ['all' => 'All'] as $k => $l) printf('<a class="%s" href="%s">%s%s</a>', $k === $st ? 'on' : '', esc_url($this->url(['status' => $k])), esc_html($l), $k !== 'all' ? ' (' . (int)($counts[$k] ?? 0) . ')' : ''); ?></div>
<div class="card"><div class="scroll"><table><thead><tr><th>Submitted</th><th>Name</th><th class="hide-sm">Type</th><th class="hide-sm">Email</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r) : ?>
<tr><td><?php echo esc_html(mysql2date('M j, Y', $r->created_at)); ?></td><td><?php echo esc_html($r->title . ' ' . $r->first_name . ' ' . $r->last_name); ?></td>
<td class="hide-sm"><?php echo esc_html(self::TYPES[$r->membership_type][0] ?? ''); ?></td><td class="hide-sm"><?php echo esc_html($r->email); ?></td>
<td><span class="pill <?php echo esc_attr($r->status); ?>"><?php echo esc_html(self::STATUSES[$r->status]); ?></span></td>
<td class="acts"><a class="btn sm" href="<?php echo esc_url($this->url(['do' => 'view', 'id' => $r->id])); ?>"><?php echo $r->status === 'pending' ? 'Review' : 'View'; ?></a></td></tr>
<?php endforeach; if (!$rows) echo '<tr><td colspan="6">No applications here.</td></tr>'; ?>
</tbody></table></div></div>
<?php }

    private function view($a) {
        $type = self::TYPES[$a->membership_type] ?? ['?', 0, '']; $mod = JHC_Panel::modules()[$type[2]] ?? null;
        $row = fn($k, $v) => printf('<dt>%s</dt><dd>%s</dd>', esc_html($k), esc_html($v === null || $v === '' ? '—' : $v)); ?>
<div class="page-head"><h1>Application #<?php echo (int)$a->id; ?> <span class="pill <?php echo esc_attr($a->status); ?>"><?php echo esc_html(self::STATUSES[$a->status]); ?></span></h1><a class="btn sec" href="<?php echo esc_url($this->url()); ?>">← Back</a></div>
<div class="card"><dl class="det">
<?php $row('Submitted', mysql2date('M j, Y g:i a', $a->created_at)); $row('Name', $a->title . ' ' . $a->first_name . ' ' . $a->last_name); $row('Address', $a->address); $row('Mobile', $a->mobile); $row('Email', $a->email);
$row('Education period', ($a->edu_from ?: '?') . ' – ' . ($a->edu_to ?: '?')); $row('Batch O/L', $a->batch_ol); $row('Batch A/L', $a->batch_al);
$row('Membership', $type[0] . ' – $' . number_format($type[1], 2)); $row('Agreed to terms', 'Yes (display name, contact, constitution)'); $row('Signature', $a->signature . ' · ' . $a->signed_date);
if ($a->status !== 'pending') { $row('Reviewed by', $a->reviewed_by . ' · ' . ($a->reviewed_at ? mysql2date('M j, Y', $a->reviewed_at) : '')); if ($a->member_code) $row('Member ID', $a->member_code); $row('Note', $a->admin_note); } ?>
</dl></div>
<?php if ($a->status === 'pending' && $mod) : ?>
<div class="card narrow"><h2>Approve and add to <?php echo esc_html($mod->label()); ?></h2>
<p class="meta">Only the title, first and last name are shown publicly. Check that payment has been received before approving.</p>
<form method="post"><?php wp_nonce_field('jhc_app_' . $a->id); ?><input type="hidden" name="id" value="<?php echo (int)$a->id; ?>">
<div class="grid">
<div><label>Title</label><select name="title"><?php foreach ($mod->titles() as $s) printf('<option%s>%s</option>', selected($a->title, $s, false), esc_html($s)); ?></select></div>
<div><label>Member number (<?php echo esc_html(rtrim($mod->format_code(0), '0')); ?>…)</label><input type="number" min="1" name="member_no" value="<?php echo (int)$mod->next_no(); ?>" required></div>
<div><label>First name</label><input name="first_name" value="<?php echo esc_attr($a->first_name); ?>" required></div>
<div><label>Last name</label><input name="last_name" value="<?php echo esc_attr($a->last_name); ?>"></div></div>
<label>Internal note (optional)</label><input name="admin_note" placeholder="e.g. e-transfer received Nov 12">
<p class="form-actions"><button class="btn" name="jhc_approve" value="1">✓ Approve &amp; add to list</button>
<button class="btn del" name="jhc_reject" value="1" onclick="return confirm('Reject this application?')">Reject</button></p></form></div>
<?php elseif ($a->status === 'rejected') : ?>
<a class="btn del" onclick="return confirm('Delete this application permanently?')" href="<?php echo esc_url($this->url(['do' => 'delete', 'id' => $a->id, '_wpnonce' => wp_create_nonce('jhc_appdel_' . $a->id)])); ?>">Delete application</a>
<?php endif;
    }
}
