<?php
require __DIR__ . '/harness.php';
$p = BHTK_Plugin::instance(); $m = $p->get_modules()['visitor-stats'];
$GLOBALS['t']['admin']=true;
$_POST = ['_bhtk_nonce'=>'nonce:bhtk_save_module_visitor-stats','_wpnonce'=>'nonce:bhtk_save_module_visitor-stats','bhtk_module'=>'visitor-stats','bhtk_page'=>'bhtk-visits',
 'bhtk_mod'=>['visitor-stats'=>['ignore_admins'=>'1','respect_dnt'=>'1','retention'=>'99999','ip_source'=>'evil','geo_provider'=>'ipapi','calendar'=>'jalali','exclude_ips'=>"1.2.3.4\n  bad ip \n10.0.0.0/8\n<script>"]]];
try { BHTK_Admin::instance()->handle_save_module(); } catch ( RedirectException $e ) { echo 'REDIRECT ', $e->getMessage(), "\n"; }
$s = $m->get_settings(); echo json_encode($s, JSON_UNESCAPED_UNICODE), "\n";
t_ok('retention clamped', $s['retention'] <= 3650 && $s['retention'] >= 0, (string)$s['retention']);
t_ok('bad ip_source falls back to auto', $s['ip_source']==='auto');
t_ok('unchecked ignore_users saved as 0', (int)$s['ignore_users']===0);
t_ok('respect_dnt on', (int)$s['respect_dnt']===1);
t_ok('removed provider ipapi falls back to auto', $s['geo_provider']==='auto', (string)$s['geo_provider']);
t_ok('calendar jalali', $s['calendar']==='jalali');
t_ok('no script tag kept', strpos($s['exclude_ips'],'<')===false);
// toggle off → cron cleared, off state; toggle on again.
$p->set_module_enabled('visitor-stats', true); t_ok('on', $p->is_enabled('visitor-stats'));
$p->set_module_enabled('visitor-stats', false); t_ok('off', ! $p->is_enabled('visitor-stats'));
