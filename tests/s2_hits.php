<?php
require __DIR__ . '/harness.php';
$p = BHTK_Plugin::instance(); $m = $p->get_modules()['visitor-stats'];
$rec = new ReflectionMethod($m,'record_hit'); $rec->setAccessible(true);
$fin = new ReflectionMethod($m,'finish_country'); $fin->setAccessible(true);
$db = $GLOBALS['wpdb']->raw();
$rows = fn($w='1=1') => $db->query("SELECT * FROM wp_bhtk_visits WHERE $w ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
function hit($ip,$ua,$post,$server=[]){ global $rec,$m; $_SERVER=array_merge(['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>$ip,'HTTP_USER_AGENT'=>$ua],$server); $_POST=$post; return $rec->invoke($m); }
$GLOBALS['t']['logged_in']=false;

$S23="Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36";
// 1. Android Chrome with hints, CDN says Iran, came from Telegram, UTM-less
$r = hit('5.120.10.20',$S23,['p'=>'/home-video/','q'=>'','t'=>'تبلیغات در شبکه نمایش خانگی','r'=>'https://t.me/somechannel/22?x=1','sid'=>'abcd1234abcd1234','n'=>'1','sw'=>'412','sh'=>'915','dpr'=>'2.6','l'=>'fa-IR','tz'=>'Asia/Tehran','tp'=>'5','hm'=>'SM-S918B','hp'=>'Android','hv'=>'14.0.0','hmob'=>'1'],['HTTP_CF_IPCOUNTRY'=>'IR']);
$a = $rows()[0] ?? [];
t_ok('1 row stored', count($rows())===1);
t_ok('1 returns no pending lookup (country from CDN)', $r===null);
t_ok('1 country IR', $a['country']==='IR');
t_ok('1 device Samsung Galaxy S23 Ultra', $a['device_brand']==='Samsung' && $a['device_model']==='Galaxy S23 Ultra' && $a['device_code']==='SM-S918B', json_encode($a));
t_ok('1 os Android 14', $a['os']==='Android' && $a['os_version']==='14');
t_ok('1 source Telegram social, query stripped', $a['ref_type']==='social' && $a['ref_name']==='Telegram' && $a['referrer']==='https://t.me/somechannel/22', $a['referrer']);
t_ok('1 persian title kept, entry flag, screen', $a['title']==='تبلیغات در شبکه نمایش خانگی' && $a['is_entry']==1 && $a['screen']==='412x915');
t_ok('1 ip stored in full', $a['ip']==='5.120.10.20');
t_ok('1 visit_day is local date', $a['visit_day']===( new DateTimeImmutable('now',new DateTimeZone('Asia/Tehran')) )->format('Y-m-d'));

// 2. second page, same session, internal referrer, not an entry
hit('5.120.10.20',$S23,['p'=>'/services/','r'=>'https://banihonarpars.ir/home-video/','sid'=>'abcd1234abcd1234','n'=>'0','hm'=>'SM-S918B','hp'=>'Android','hv'=>'14.0.0','hmob'=>'1'],['HTTP_CF_IPCOUNTRY'=>'IR']);
$b = $rows()[1];
t_ok('2 internal, not entry, same visitor hash', $b['ref_type']==='internal' && $b['is_entry']==0 && $b['visitor_hash']===$a['visitor_hash'] && $b['session_id']===$a['session_id']);

// 3. iPhone, no CDN header -> online lookup pending, then resolved; third hit reuses stored country
$IP="Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1";
$GLOBALS['t']['http']=fn($u)=> str_contains($u,'ipwho.is') ? ['code'=>200,'body'=>'{"ip":"91.99.1.1","success":true,"country_code":"DE"}'] : new WP_Error('x','y');
// Country lookup online is opt-in: by default nothing is sent anywhere.
$GLOBALS['t']['http_log']=[];
$off = hit('91.99.2.2',$IP,['p'=>'/off/','sid'=>'offf9999offf9999','n'=>'1']);
t_ok('3a default (opt-in off): no pending lookup, no http call', null===$off && 0===count($GLOBALS['t']['http_log'] ?? []));
$s0 = $m->get_settings(); $s0['geo_online']=1; update_option('bhtk_mod_visitor_stats',$s0);
$pend = hit('91.99.1.1',$IP,['p'=>'/','q'=>'?utm_source=Instagram&utm_medium=story&utm_campaign=Aban-Sale','r'=>'','sid'=>'zzzz9999zzzz9999','n'=>'1','sw'=>'390','sh'=>'844','dpr'=>'3','l'=>'en-US']);
t_ok('3 pending lookup returned', is_array($pend) && $pend['ip']==='91.99.1.1');
$c = $rows("ip='91.99.1.1'")[0];
t_ok('3 country empty before lookup', $c['country']==='');
t_ok('3 campaign parsed', $c['ref_type']==='campaign' && $c['ref_name']==='Instagram' && $c['utm_campaign']==='Aban-Sale' && $c['utm_medium']==='story', json_encode($c));
t_ok('3 iPhone guess', $c['device_brand']==='Apple' && $c['device_model']==='iPhone 12 / 13 / 14' && $c['os']==='iOS' && $c['os_version']==='17.2');
$fin->invoke($m,$pend);
t_ok('3 country filled by online lookup', $rows("ip='91.99.1.1'")[0]['country']==='DE');
t_ok('3 lookup called once, url encoded ip', count($GLOBALS['t']['http_log'])===1 && str_contains($GLOBALS['t']['http_log'][0],'ipwho.is/91.99.1.1'));
$GLOBALS['t']['http_log']=[];
$again = hit('91.99.1.1',$IP,['p'=>'/about/','sid'=>'zzzz9999zzzz9999','n'=>'0']);
t_ok('3b known IP: no pending, no http, country reused', $again===null && count($GLOBALS['t']['http_log'] ?? [])===0 && $rows("ip='91.99.1.1'")[1]['country']==='DE');

// 4. Ignored requests
$before = count($rows());
hit('66.249.66.1','Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',['p'=>'/','sid'=>'bot00000bot00000','n'=>'1']);
t_ok('4 bot ignored', count($rows())===$before);
hit('1.2.3.4',$S23,['p'=>'not-a-path','sid'=>'aaaaaaaa11111111']);
hit('1.2.3.4',$S23,['p'=>'','sid'=>'aaaaaaaa11111111']);
t_ok('4 invalid path ignored', count($rows())===$before);
$GLOBALS['t']['logged_in']=true; $GLOBALS['t']['can']=true;
hit('1.2.3.4',$S23,['p'=>'/','sid'=>'aaaaaaaa11111111','n'=>'1']);
t_ok('4 logged-in admin ignored', count($rows())===$before);
$GLOBALS['t']['logged_in']=false;
$GLOBALS['t']['http']=null;
$s = $m->get_settings(); $s['exclude_ips']="203.0.113.0/24\n9.9.9.9"; update_option('bhtk_mod_visitor_stats',$s);
hit('203.0.113.99',$S23,['p'=>'/','sid'=>'bbbbbbbb22222222','n'=>'1']); hit('9.9.9.9',$S23,['p'=>'/','sid'=>'bbbbbbbb22222222','n'=>'1']);
t_ok('4 excluded IPs ignored', count($rows())===$before);
$s['exclude_ips']=''; update_option('bhtk_mod_visitor_stats',$s);
$_SERVER=['REQUEST_METHOD'=>'GET','REMOTE_ADDR'=>'1.1.1.1','HTTP_USER_AGENT'=>$S23]; $_POST=[]; // GET never reaches record_hit via handle_hit

// 5. Persian path (percent-encoded by the browser) is stored readable; plain permalinks keep ?p=
hit('5.200.1.1',$S23,['p'=>'/%D8%AE%D8%AF%D9%85%D8%A7%D8%AA/%D8%AA%D9%84%D9%88%DB%8C%D8%B2%DB%8C%D9%88%D9%86/','sid'=>'cccccccc33333333','n'=>'1','hm'=>'Pixel 7'],['HTTP_CF_IPCOUNTRY'=>'IR']);
$d = $rows("ip='5.200.1.1'")[0];
t_ok('5 persian path decoded', $d['path']==='/خدمات/تلویزیون/', $d['path']);
t_ok('5 hint model Pixel -> Google', $d['device_brand']==='Google' && $d['device_model']==='Pixel 7');
hit('5.200.1.2',$S23,['p'=>'/','q'=>'?p=123&token=SECRET&s=hello','sid'=>'dddddddd44444444','n'=>'1'],['HTTP_CF_IPCOUNTRY'=>'IR']);
$e = $rows("ip='5.200.1.2'")[0];
t_ok('5 plain permalink keeps only ?p=123, drops token/search', $e['path']==='/?p=123', $e['path']);

// 6. Anonymize
$s['anonymize_ip']=1; update_option('bhtk_mod_visitor_stats',$s);
$GLOBALS['t']['http']=fn($u)=>['code'=>200,'body'=>'{"ip":"x","success":true,"country_code":"TR"}'];
$pend=hit('85.15.7.77',$S23,['p'=>'/','sid'=>'eeeeeeee55555555','n'=>'1']);
t_ok('6 anonymized ip stored, lookup still pending with full ip', $rows("ip='85.15.7.0'") && $pend['ip']==='85.15.7.77' && $pend['stored_ip']==='');
$fin->invoke($m,$pend);
t_ok('6 country filled by row id', $rows("ip='85.15.7.0'")[0]['country']==='TR');
$s['anonymize_ip']=0; update_option('bhtk_mod_visitor_stats',$s);

// 7. Lookup service down: nothing stored, back-off flag set, no exception
$GLOBALS['t']['http']=fn($u)=>new WP_Error('timeout','timeout'); $GLOBALS['t']['http_log']=[];
$pend=hit('37.9.9.9',$S23,['p'=>'/','sid'=>'ffffffff66666666','n'=>'1']); $fin->invoke($m,$pend);
t_ok('7 failed lookup leaves country empty, sets back-off', $rows("ip='37.9.9.9'")[0]['country']==='' && get_transient('bhtk_geo_down')===1);
$n=count($GLOBALS['t']['http_log']); $pend=hit('37.9.9.10',$S23,['p'=>'/','sid'=>'gggggggg77777777','n'=>'1']); $fin->invoke($m,$pend);
t_ok('7 while backing off no more http calls', count($GLOBALS['t']['http_log'])===$n);
delete_transient('bhtk_geo_down');
// maintenance backfills when the service is back
$GLOBALS['t']['http']=fn($u)=>['code'=>200,'body'=>'{"country":"IR","ip":"x"}'];
$m->maintenance();
t_ok('7 maintenance backfilled missing countries', $rows("country=''")===[] , json_encode(array_column($rows("country=''"),'ip')));

// 8. Rate limit: 45 quick hits from one visitor -> at most 40 stored in the window
for($i=0;$i<45;$i++) hit('88.88.88.88',$S23,['p'=>'/spam/','sid'=>'hhhhhhhh88888888','n'=>'0'],['HTTP_CF_IPCOUNTRY'=>'IR']);
t_ok('8 rate limit caps a flood', count($rows("ip='88.88.88.88'"))===40, (string)count($rows("ip='88.88.88.88'")));

// 9. Stats queries
$range = BHTK_Visits::resolve_range(['range'=>'7']);
$sum = BHTK_Visits::summary($range,[]);
t_ok('9 summary sane', $sum['views']>=40 && $sum['visits']>=6 && $sum['countries']>=3 && $sum['visitors']>=7, json_encode($sum));
$f = BHTK_Visits::read_filters(['country'=>'ir','rtype'=>'evil','dtype'=>'mobile','ip'=>"5.120.",'path'=>'/home-video/']);
t_ok('9 filters sanitised', $f==['country'=>'IR','dtype'=>'mobile','ip'=>'5.120.','path'=>'/home-video/'], json_encode($f));
$top = BHTK_Visits::top('country',$range,[],10,true);
t_ok('9 top countries IR first', array_key_first($top)==='IR', json_encode($top));
$sum2 = BHTK_Visits::summary($range,['country'=>'DE']);
t_ok('9 country filter', $sum2['views']===2 && $sum2['countries']===1, json_encode($sum2));
$ipf = BHTK_Visits::summary($range,['ip'=>'5.120.']);
t_ok('9 ip prefix filter', $ipf['views']===2, json_encode($ipf));
$src = BHTK_Visits::sources($range,[],10);
t_ok('9 sources include Telegram + campaign Instagram + direct', count(array_filter($src,fn($r)=>$r['name']==='Telegram'))===1 && count(array_filter($src,fn($r)=>$r['type']==='campaign'))===1 && count(array_filter($src,fn($r)=>$r['type']==='direct'))>=1, json_encode($src));
$mod = BHTK_Visits::models($range,[],10);
t_ok('9 models list has Samsung Galaxy S23 Ultra', (bool)array_filter($mod,fn($r)=>$r['model']==='Galaxy S23 Ultra'), json_encode($mod));
t_ok('9 daily has today', count(BHTK_Visits::daily($range,[]))===1);
list($lg,$tot)=BHTK_Visits::log($range,[],1,10);
t_ok('9 log paging', count($lg)===10 && $tot===$sum['views']);
t_ok('9 online now', BHTK_Visits::online_now(5)>=7);
// SQL injection attempts through filters and ranges are inert
$bad = BHTK_Visits::read_filters(['source'=>"x' OR '1'='1", 'model'=>"'; DROP TABLE wp_bhtk_visits;--"]);
BHTK_Visits::summary($range,$bad); BHTK_Visits::top('os',$range,$bad); BHTK_Visits::log($range,$bad,1,5);
t_ok('9 injection inert, table intact', BHTK_Visits::count_all()>0);
t_ok('9 custom range validation', BHTK_Visits::resolve_range(['range'=>'custom','from'=>'2026-13-45','to'=>'x'])['key']==='30' && BHTK_Visits::resolve_range(['range'=>'custom','from'=>'2026-10-01','to'=>'2026-10-26'])['key']==='custom');
// 10. Retention
$db->exec("UPDATE wp_bhtk_visits SET visit_day='2020-01-01' WHERE id=1");
BHTK_Visits::purge_older_than(365); t_ok('10 retention purges old rows only', count($rows('id=1'))===0 && BHTK_Visits::count_all()>10);
echo ($GLOBALS['t_fails'] ?? 0) ? "\nFAILED: {$GLOBALS['t_fails']}\n" : "\nHITS OK\n";
