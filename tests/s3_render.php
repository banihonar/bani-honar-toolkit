<?php
require __DIR__ . '/harness.php';
$out = getenv('T_DIR') . '/html'; is_dir($out) || mkdir($out);
$p = BHTK_Plugin::instance(); $m = $p->get_modules()['visitor-stats']; $shield = $p->get_modules()['code-shield'];
$GLOBALS['t']['admin']=true; BHTK_Admin::instance()->register_menu();
$db = $GLOBALS['wpdb']->raw();

// Seed varied data over 30 days (the per-minute ceiling is for real traffic, not for seeding).
add_filter('bhtk_visits_max_per_minute', fn()=>0);
mt_srand(7);
$uas = [
 ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/124.0.0.0 Mobile Safari/537.36', ['hm'=>'SM-A546B','hp'=>'Android','hv'=>'14.0.0','hmob'=>'1','sw'=>'412','sh'=>'915','dpr'=>'2.6']],
 ['Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 Chrome/124.0.0.0 Mobile Safari/537.36', ['hm'=>'2201116SG','hp'=>'Android','hv'=>'13.0.0','hmob'=>'1']],
 ['Mozilla/5.0 (Linux; Android 13; Redmi Note 12) AppleWebKit/537.36 Chrome/120.0.0.0 Mobile Safari/537.36', []],
 ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Version/17.2 Mobile/15E148 Safari/604.1', ['sw'=>'390','sh'=>'844','dpr'=>'3']],
 ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36', ['hp'=>'Windows','hv'=>'15.0.0','sw'=>'1920','sh'=>'1080']],
 ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36 Edg/120.0.2210.91', []],
 ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.1 Safari/605.1.15', []],
 ['Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0', []],
];
$refs = ['https://www.google.com/', 'https://t.me/x/1', 'https://l.instagram.com/?u=1', '', '', 'https://eitaa.com/a', 'https://www.digikala.com/p', 'https://www.bing.com/', ''];
$pages = ['/'=>'کانون آگهی و تبلیغات بانی هنر', '/tv-ads/'=>'تبلیغات تلویزیونی', '/home-video/'=>'تبلیغات در شبکه نمایش خانگی', '/services/'=>'خدمات تبلیغاتی', '/contact/'=>'تماس با ما'];
$countries = ['IR','IR','IR','IR','IR','IR','DE','US','TR','AE','AF','IQ','GB','CA'];
$s = $m->get_settings(); $s['geo_online']=0; update_option('bhtk_mod_visitor_stats',$s);
$GLOBALS['t']['logged_in']=false;
$rec = new ReflectionMethod($m,'record_hit'); $rec->setAccessible(true);
for ($i=0;$i<420;$i++) {
  $u = $uas[array_rand($uas)]; $ip = mt_rand(2,223).'.'.mt_rand(0,255).'.'.mt_rand(0,255).'.'.mt_rand(1,254);
  $path = array_rand($pages); $sid = substr(md5("s$i"),0,16);
  $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>$ip,'HTTP_USER_AGENT'=>$u[0],'HTTP_CF_IPCOUNTRY'=>$countries[array_rand($countries)]];
  $_POST=array_merge(['p'=>$path,'t'=>$pages[$path],'r'=>$refs[array_rand($refs)],'sid'=>$sid,'n'=>'1','l'=>'fa-IR','tz'=>'Asia/Tehran'],$u[1]);
  if ($i%9==0) $_POST['q']='?utm_source=telegram&utm_campaign=Aban-Sale';
  $rec->invoke($m);
  if (mt_rand(0,2)>0) { $_POST['p']='/services/'; $_POST['n']='0'; $_POST['r']='https://banihonarpars.ir/'; $rec->invoke($m); }
}
// Spread over 30 days with a weekly rhythm.
$ids = $db->query("SELECT id FROM wp_bhtk_visits")->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $id) { $d = (int) floor(pow(mt_rand(0,1000)/1000, 1.6) * 30); $day=(new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')))->modify("-$d days")->format('Y-m-d'); $db->exec("UPDATE wp_bhtk_visits SET visit_day='$day', visited_at='$day 10:".sprintf('%02d',mt_rand(0,59)).":00' WHERE id=$id"); }
$db->exec("UPDATE wp_bhtk_visits SET visited_at=datetime('now','-2 minutes'), visit_day='".(new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')))->format('Y-m-d')."' WHERE id IN (SELECT id FROM wp_bhtk_visits ORDER BY id DESC LIMIT 3)");

function page($cb,$get=[]) { $_GET=$get; $_SERVER['REQUEST_URI']='/wp-admin/admin.php?'.http_build_query($get); return t_out($cb); }
$render = ['BHTK_Visits_Page','render'];
$pages_out = [
 'overview'   => page($render,['page'=>'bhtk-visits']),
 'overview_7' => page($render,['page'=>'bhtk-visits','range'=>'7','country'=>'IR']),
 'overview_all'=> page($render,['page'=>'bhtk-visits','range'=>'all']),
 'log'        => page($render,['page'=>'bhtk-visits','view'=>'log']),
 'log_filtered'=> page($render,['page'=>'bhtk-visits','view'=>'log','country'=>'DE','ip'=>'1.','paged'=>'2']),
 'settings'   => page($render,['page'=>'bhtk-visits','view'=>'settings']),
 'shield_on'  => page([$shield,'render_page'],['page'=>'bhtk-shield']),
 'dashboard'  => page([BHTK_Admin::instance(),'render_dashboard'],['page'=>'bani-honar-toolkit']),
 'custom_range'=> page($render,['page'=>'bhtk-visits','range'=>'custom','from'=>'2026-10-01','to'=>'2026-10-20']),
 'xss'        => page($render,['page'=>'bhtk-visits','view'=>'log','source'=>'"><script>alert(1)</script>','path'=>"/x'><img src=x onerror=alert(1)>"]),
];
$p->set_module_enabled('code-shield', false);
$pages_out['shield_off'] = page([$shield,'render_page'],['page'=>'bhtk-shield']);
$p->set_module_enabled('visitor-stats', false);
$pages_out['visits_off'] = page($render,['page'=>'bhtk-visits']);
$p->set_module_enabled('visitor-stats', true);
foreach ($pages_out as $k=>$h) file_put_contents("$out/$k.html",$h);

t_ok('overview has tiles + chart svg + 8 cards', str_contains($pages_out['overview'],'<svg') && substr_count($pages_out['overview'],'bhtk-card--list')>=6 && str_contains($pages_out['overview'],'Page views'));
t_ok('overview lists IP addresses in latest visits', preg_match('/bhtk-iplink[^>]*>\d+\.\d+\.\d+\.\d+</',$pages_out['overview'])===1);
t_ok('overview shows Samsung Galaxy A54 5G and Xiaomi', str_contains($pages_out['overview'],'Samsung Galaxy A54 5G') && str_contains($pages_out['overview'],'Xiaomi 2201116SG'));
t_ok('overview shows jalali dates (fa)', preg_match('#14\d\d/\d\d/\d\d#',$pages_out['overview'])===1);
t_ok('overview shows flags and Iran (fa)', str_contains($pages_out['overview'],'ایران') && str_contains($pages_out['overview'],'bhtk-flag'));
t_ok('filtered page shows chip and clear link', str_contains($pages_out['overview_7'],'bhtk-chips') && str_contains($pages_out['overview_7'],'Clear all filters'));
t_ok('log has details rows and paging', str_contains($pages_out['log'],'bhtk-edit-v') && str_contains($pages_out['log'],'page-numbers'));
t_ok('log export form with nonce', str_contains($pages_out['log'],'bhtk_visits_export') && str_contains($pages_out['log'],'nonce:bhtk_visits_export'));
t_ok('settings has data tools', str_contains($pages_out['settings'],'bhtk_visits_clear') && str_contains($pages_out['settings'],'bhtk_mod[visitor-stats][retention]'));
t_ok('power switch on module tabs', str_contains($pages_out['overview'],'bhtk-power is-on') && str_contains($pages_out['shield_off'],'bhtk-power is-off') && str_contains($pages_out['visits_off'],'bhtk-power is-off'));
t_ok('visits off still shows saved data + warning', str_contains($pages_out['visits_off'],'Counting is off') && str_contains($pages_out['visits_off'],'<svg'));
t_ok('shield tab has settings form posting to generic handler', str_contains($pages_out['shield_on'],'bhtk_save_module') && str_contains($pages_out['shield_on'],'bhtk_mod[code-shield][menu]'));
t_ok('dashboard card has Open for tab', str_contains($pages_out['dashboard'],'page=bhtk-shield') && str_contains($pages_out['dashboard'],'page=bhtk-visits'));
t_ok('XSS payloads are escaped', ! str_contains($pages_out['xss'],'<script>alert') && ! str_contains($pages_out['xss'],'<img src=x'));
t_ok('filter links are url-encoded', ! preg_match('/href="[^"]*[?&]source=[^"&]*["\'<>]/', $pages_out['xss']) );
t_ok('no stray PHP notices in any page', ! preg_grep('/Warning:|Notice:|Fatal error|Undefined/', array_map(fn($h)=>$h, $pages_out)));
// empty state
$db->exec("DELETE FROM wp_bhtk_visits");
$empty = page($render,['page'=>'bhtk-visits']); file_put_contents("$out/empty.html",$empty);
t_ok('empty state', str_contains($empty,'No visits recorded yet') && str_contains($empty,'private window'));
echo ($GLOBALS['t_fails'] ?? 0) ? "\nFAILED: {$GLOBALS['t_fails']}\n" : "\nRENDER OK\n";
