<?php
require __DIR__ . '/harness.php';
$p = BHTK_Plugin::instance(); $m = $p->get_modules()['visitor-stats']; $p->set_module_enabled('visitor-stats', true);
$GLOBALS['t']['admin']=true; BHTK_Admin::instance()->register_menu();
$db = $GLOBALS['wpdb']->raw(); $db->exec('DELETE FROM wp_bhtk_visits');
$rec = new ReflectionMethod($m,'record_hit'); $rec->setAccessible(true);
$UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
function hit($m,$rec,$ip,$post,$ua){ $_SERVER=['REQUEST_METHOD'=>'POST','REMOTE_ADDR'=>$ip,'HTTP_USER_AGENT'=>$ua]; $_POST=array_merge(['p'=>'/','sid'=>'sid'.mt_rand(1000,9999).'abcdef','n'=>'1','t'=>'T'],$post); return $rec->invoke($m); }

// --- search terms
hit($m,$rec,'5.1.1.1',['p'=>'/','q'=>'?s=%D8%AA%D8%A8%D9%84%DB%8C%D8%BA%D8%A7%D8%AA+%20%20%D8%AA%D9%84%D9%88%DB%8C%D8%B2%DB%8C%D9%88%D9%86%DB%8C'],$UA);
hit($m,$rec,'5.1.1.2',['p'=>'/','q'=>'?s=Home+VIDEO'],$UA);
hit($m,$rec,'5.1.1.3',['p'=>'/search/Home%20Video/'],$UA);
hit($m,$rec,'5.1.1.4',['p'=>'/about/','q'=>'?s='.str_repeat('x',300)],$UA);
hit($m,$rec,'5.1.1.5',['p'=>'/about/','q'=>'?s[]=a'],$UA);
$rows=$db->query("SELECT ip,search_term,path FROM wp_bhtk_visits ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
t_ok('persian term, spaces collapsed', $rows[0]['search_term']==='تبلیغات تلویزیونی', json_encode($rows[0],JSON_UNESCAPED_UNICODE));
t_ok('term lowercased', $rows[1]['search_term']==='home video');
t_ok('pretty search url term', $rows[2]['search_term']==='home video', json_encode($rows[2]));
t_ok('long term cut to 120', mb_strlen($rows[3]['search_term'])===120);
t_ok('array param ignored', $rows[4]['search_term']==='');
// --- logged-in user
$GLOBALS['t']['logged_in']=true; $GLOBALS['t']['can']=false; $GLOBALS['t']['uid']=2;
hit($m,$rec,'5.1.1.6',['p'=>'/account/'],$UA);
$GLOBALS['t']['uid']=1; $GLOBALS['t']['can']=true; // admin: ignored by default
$before=(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits")->fetchColumn();
hit($m,$rec,'5.1.1.7',['p'=>'/'],$UA);
t_ok('admin visit still ignored', (int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits")->fetchColumn()===$before);
$GLOBALS['t']['logged_in']=false; $GLOBALS['t']['can']=false;
hit($m,$rec,'5.1.1.8',['p'=>'/'],$UA);
$u=$db->query("SELECT ip,user_id FROM wp_bhtk_visits WHERE ip IN ('5.1.1.6','5.1.1.8') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
t_ok('member id stored, guest 0', (int)$u[0]['user_id']===2 && (int)$u[1]['user_id']===0, json_encode($u));

// --- enforcement
$GLOBALS['t']['can']=false;
t_ok('add range ok', BHTK_Blocklist::add('77.77.77.0/24','test','forever','5.5.5.5')==='ok');
t_ok('add single 1h ok', BHTK_Blocklist::add('88.88.88.88','x','1h','5.5.5.5')==='ok');
t_ok('own ip refused', BHTK_Blocklist::add('77.77.77.0/25','x','1h','77.77.77.50')==='own' && BHTK_Blocklist::add('77.77.77.50','x','1h','77.77.77.50')==='own');
t_ok('private refused', BHTK_Blocklist::add('10.0.0.1','x','1h','5.5.5.5')==='private' && BHTK_Blocklist::add('127.0.0.1','x','1h','5.5.5.5')==='private');
t_ok('private range refused', BHTK_Blocklist::add('192.168.0.0/16','x','1h','5.5.5.5')==='private');
t_ok('garbage refused', BHTK_Blocklist::add('999.1.1.1','x','1h','5.5.5.5')==='invalid' && BHTK_Blocklist::add('','x','1h','5.5.5.5')==='invalid');
t_ok('list entries', array_keys(BHTK_Blocklist::all())===['77.77.77.0/24','88.88.88.88'], json_encode(array_keys(BHTK_Blocklist::all())));
function try_enforce($ip,$server=[]) { $_SERVER=array_merge(['REMOTE_ADDR'=>$ip,'HTTP_USER_AGENT'=>'x'],$server); try { BHTK_Blocklist::enforce(); return 'pass'; } catch ( WPDieException $e ) { return 'blocked'; } }
t_ok('range member blocked', try_enforce('77.77.77.200')==='blocked');
t_ok('single blocked', try_enforce('88.88.88.88')==='blocked');
t_ok('neighbour passes', try_enforce('88.88.88.89')==='pass' && try_enforce('77.77.78.1')==='pass');
$st=$m->get_settings(); $m->save_settings(array_merge($st,['ip_source'=>'remote_addr']));
t_ok('direct mode: forged X-Forwarded-For cannot dodge the block', try_enforce('88.88.88.88',['HTTP_X_FORWARDED_FOR'=>'1.2.3.4'])==='blocked');
t_ok('direct mode: forged header cannot get a neighbour blocked', try_enforce('1.2.3.4',['HTTP_X_FORWARDED_FOR'=>'88.88.88.88'])==='pass');
$m->save_settings($st);
$GLOBALS['t']['logged_in']=true; $GLOBALS['t']['can']=true;
t_ok('logged-in admin passes even if listed', try_enforce('88.88.88.88')==='pass');
$GLOBALS['t']['logged_in']=false; $GLOBALS['t']['can']=false;
// expiry
$list=BHTK_Blocklist::all(); $list['88.88.88.88']['until']=time()-5; update_option('bhtk_blocked_ips',$list);
t_ok('expired entry not enforced', try_enforce('88.88.88.88')==='pass');
BHTK_Blocklist::purge(); t_ok('purge removes expired', array_keys(BHTK_Blocklist::all())===['77.77.77.0/24']);
t_ok('bypass filter', (function(){ add_filter('bhtk_ip_block_bypass',fn()=>true,10,2); return try_enforce('77.77.77.5'); })()==='pass');
$GLOBALS['t']['hooks']['bhtk_ip_block_bypass']=[];
// --- security: a header cannot hide a blocked connection
BHTK_Blocklist::add('99.88.77.66','x','forever','5.5.5.5');
$m->save_settings(array_merge($m->get_settings(),['ip_source'=>'auto']));
t_ok('auto mode: forged X-Real-IP / CF header / XFF cannot dodge a block on the real connection', try_enforce('99.88.77.66',['HTTP_X_REAL_IP'=>'1.2.3.4'])==='blocked' && try_enforce('99.88.77.66',['HTTP_CF_CONNECTING_IP'=>'1.2.3.4'])==='blocked' && try_enforce('99.88.77.66',['HTTP_X_FORWARDED_FOR'=>'1.2.3.4, 5.6.7.8'])==='blocked');
t_ok('auto mode: behind a CDN the client address from the header is still blocked', try_enforce('104.16.0.1',['HTTP_CF_CONNECTING_IP'=>'99.88.77.66'])==='blocked');
t_ok('auto mode: an innocent visitor is not blocked', try_enforce('104.16.0.1',['HTTP_CF_CONNECTING_IP'=>'8.8.4.4'])==='pass');
t_ok('candidate_ips lists header address and direct address once', BHTK_Geo::candidate_ips('auto',['REMOTE_ADDR'=>'104.16.0.1','HTTP_CF_CONNECTING_IP'=>'8.8.4.4'])===['8.8.4.4','104.16.0.1'] && BHTK_Geo::candidate_ips('auto',['REMOTE_ADDR'=>'8.8.4.4'])===['8.8.4.4'] && BHTK_Geo::candidate_ips('remote_addr',['REMOTE_ADDR'=>'104.16.0.1','HTTP_CF_CONNECTING_IP'=>'8.8.4.4'])===['104.16.0.1']);
t_ok('own-address check covers the direct connection too', BHTK_Blocklist::add('104.16.0.0/16','x','1h',['8.8.4.4','104.16.0.1'])==='own' && BHTK_Blocklist::add('104.16.0.1','x','1h','104.16.0.1')==='own');
BHTK_Blocklist::remove('99.88.77.66');
// render
$render=function($get){ $_GET=$get; $_SERVER['REQUEST_URI']='/wp-admin/admin.php'; $GLOBALS['t']['can']=true; return t_out(['BHTK_Visits_Page','render']); };
$h=$render(['page'=>'bhtk-visits','view'=>'blocked','add'=>'6.6.6.6"><script>alert(1)</script>']);
t_ok('blocked view lists range + form', str_contains($h,'77.77.77.0/24') && str_contains($h,'bhtk_visits_block') && str_contains($h,'bhtk_visits_unblock'));
t_ok('prefill escaped', !str_contains($h,'<script>alert(1)</script>'));
$h=$render(['page'=>'bhtk-visits','view'=>'log','range'=>'all']);
t_ok('log shows member tag + search tag + block link', str_contains($h,'Ali &lt;b&gt; (ali)') && str_contains($h,'Search: ') && str_contains($h,'view=blocked&#038;add=') || str_contains($h,'view=blocked&add='), '');
t_ok('log details have email + block button', str_contains($h,'ali@example.com') && str_contains($h,'Block this IP address'));
t_ok('no raw html from display name', !str_contains($h,'Ali <b>'));
$h=$render(['page'=>'bhtk-visits','view'=>'overview','range'=>'all']);
t_ok('overview has searches card with terms', str_contains($h,'Searches on your website') && str_contains($h,'home video') && str_contains($h,'search.google.com/search-console'));
$h=$render(['page'=>'bhtk-visits','view'=>'log','range'=>'all','search'=>'home video']);
t_ok('search filter works (2 rows)', substr_count($h,'bhtk-edit-v')>=2 && !str_contains($h,'5.1.1.1<'));
$h=$render(['page'=>'bhtk-visits','view'=>'log','range'=>'all','user'=>'2']);
t_ok('user filter works', str_contains($h,'5.1.1.6') && !str_contains($h,'5.1.1.2<'));
// private ip: no block button
$db->exec("INSERT INTO wp_bhtk_visits (visited_at,visit_day,session_id,visitor_hash,ip,path,user_agent) VALUES (datetime('now'),date('now'),'s','h','10.1.1.1','/priv/','ua')");
// export columns
$_POST=['_bhtk_nonce'=>'nonce:bhtk_visits_export','range'=>'all']; 
// --- security: the site-wide ceiling on stored visits
$GLOBALS['t']['trans']=[];
add_filter('bhtk_visits_max_per_minute', fn()=>5);
for($i=0;$i<9;$i++){ hit($m,$rec,'6.7.8.'.($i+1),['p'=>'/flood/'],$UA.' '.$i); }
t_ok('flood of made-up visitors is capped at the ceiling', 5===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits WHERE ip LIKE '6.7.8.%'")->fetchColumn());
$GLOBALS['t']['hooks']['bhtk_visits_max_per_minute']=[];
$GLOBALS['t']['trans']=[];
add_filter('bhtk_visits_max_per_minute', fn()=>0);
for($i=0;$i<9;$i++){ hit($m,$rec,'6.7.9.'.($i+1),['p'=>'/flood2/'],$UA.' '.$i); }
t_ok('ceiling 0 means no limit', 9===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits WHERE ip LIKE '6.7.9.%'")->fetchColumn());
$GLOBALS['t']['hooks']['bhtk_visits_max_per_minute']=[];
echo "S7 DONE\n";
