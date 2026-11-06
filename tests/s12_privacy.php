<?php
require __DIR__ . '/harness.php';
$p = BHTK_Plugin::instance(); $m = $p->get_modules()['visitor-stats']; $p->set_module_enabled('visitor-stats', true);
t_ok('country lookup online is off by default (opt-in)', 0 === $m->default_settings()['geo_online'] && 0 === $m->get_settings()['geo_online']);
$db = $GLOBALS['wpdb']->raw(); $db->exec('DELETE FROM wp_bhtk_visits');
$ins = function($uid,$ip,$path){ global $db; $db->exec("INSERT INTO wp_bhtk_visits (visited_at,visit_day,session_id,visitor_hash,ip,path,user_agent,user_id,referrer) VALUES (datetime('now'),date('now'),'s','h','$ip','$path','UA/1',$uid,'https://r.example/')"); };
$ins(2,'5.5.5.1','/a/'); $ins(2,'5.5.5.2','/b/'); $ins(0,'5.5.5.3','/c/'); $ins(1,'5.5.5.4','/d/');
t_ok('privacy hooks registered', !empty($GLOBALS['t']['hooks']['wp_privacy_personal_data_exporters']) && !empty($GLOBALS['t']['hooks']['wp_privacy_personal_data_erasers']) && !empty($GLOBALS['t']['hooks']['delete_user']) && !empty($GLOBALS['t']['hooks']['admin_init']));
$ex = apply_filters('wp_privacy_personal_data_exporters', array());
$er = apply_filters('wp_privacy_personal_data_erasers', array());
t_ok('exporter and eraser listed', isset($ex['bani-honar-toolkit']['callback'], $er['bani-honar-toolkit']['callback']));
function get_user_by($f,$v){ return ('email'===$f && 'ali@example.com'===$v) ? (object)['ID'=>2] : false; }
if(!function_exists('get_user_meta')){ function get_user_meta($u,$k='',$s=false){ return ''; } }
function is_email($e){ return (bool) filter_var($e, FILTER_VALIDATE_EMAIL); }
$r = call_user_func($ex['bani-honar-toolkit']['callback'], 'ali@example.com', 1);
t_ok('export: only that member visits, newest first, with IP', 2===count($r['data']) && true===$r['done'] && '/b/'===$r['data'][0]['data'][2]['value'] && '5.5.5.2'===$r['data'][0]['data'][1]['value'], json_encode($r));
$r = call_user_func($ex['bani-honar-toolkit']['callback'], 'nobody@example.com', 1);
t_ok('export: unknown e-mail gives nothing', array()===$r['data'] && true===$r['done']);
$r = call_user_func($ex['bani-honar-toolkit']['callback'], 'not-an-email', 1);
t_ok('export: invalid e-mail gives nothing', array()===$r['data']);
$er_r = call_user_func($er['bani-honar-toolkit']['callback'], 'ali@example.com', 1);
t_ok('erase: removes only that member rows', true===$er_r['items_removed'] && true===$er_r['done'] && 2===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits")->fetchColumn() && 0===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits WHERE user_id=2")->fetchColumn());
$er_r = call_user_func($er['bani-honar-toolkit']['callback'], 'nobody@example.com', 1);
t_ok('erase: unknown e-mail removes nothing', false===$er_r['items_removed'] && 2===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits")->fetchColumn());
$ins(2,'5.5.5.9','/e/'); $ins(2,'5.5.5.8','/f/');
do_action('delete_user', 2);
t_ok('deleting a member keeps the visits but forgets who', 4===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits")->fetchColumn() && 0===(int)$db->query("SELECT COUNT(*) FROM wp_bhtk_visits WHERE user_id=2")->fetchColumn());
function wp_add_privacy_policy_content($n,$t){ $GLOBALS['t']['policy']=[$n,$t]; }
BHTK_Privacy::policy_text();
t_ok('suggested privacy text added, mentions opt-in lookup and Gravatar', 'Bani Honar Toolkit'===$GLOBALS['t']['policy'][0] && false!==strpos($GLOBALS['t']['policy'][1],'ipwho.is') && false!==strpos($GLOBALS['t']['policy'][1],'Gravatar') && false!==strpos($GLOBALS['t']['policy'][1],'off unless'));
echo ($GLOBALS['t_fails'] ?? 0) ? "\nFAILED: {$GLOBALS['t_fails']}\n" : "\nPRIVACY OK\n";
