<?php
class JHC_Dashboard extends JHC_Module {
    public function slug()  { return 'dashboard'; }
    public function label() { return 'Dashboard'; }
    public function icon()  { return '⌂'; }
    public function dispatch() {
        $mods = array_filter(JHC_Panel::modules(), fn($m) => !($m instanceof self));
        echo '<h1>Dashboard</h1><div class="stats">';
        foreach ($mods as $m) if ($s = $m->stats())
            printf('<a class="stat" href="%s"><span>%s</span><b>%s</b></a>', esc_url(JHC_Panel::url($m->slug())), esc_html($s['label']), esc_html(number_format_i18n($s['count'])));
        echo '</div><div class="cols"><div class="card"><h2>Quick actions</h2><p>';
        foreach ($mods as $m) foreach ($m->quick_actions() as $a)
            printf('<a class="btn" href="%s">%s</a> ', esc_url($a['url']), esc_html($a['label']));
        echo '</p></div><div class="card"><h2>Recently changed</h2><ul class="recent">';
        $all = [];
        foreach ($mods as $m) foreach ($m->recent(5) as $r) { $r['mod'] = $m->label(); $all[] = $r; }
        usort($all, fn($a, $b) => strcmp($b['time'], $a['time']));
        foreach (array_slice($all, 0, 8) as $r)
            printf('<li><a href="%s">%s</a><span>%s · %s</span></li>', esc_url($r['url']), esc_html($r['title']), esc_html($r['mod']), esc_html(human_time_diff(strtotime($r['time']), current_time('timestamp')) . ' ago'));
        if (!$all) echo '<li>Nothing yet.</li>';
        echo '</ul></div></div>';
    }
}
