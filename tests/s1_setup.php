<?php
require __DIR__ . '/harness.php';
$p = BHTK_Plugin::instance();
t_ok('modules registered', isset($p->get_modules()['visitor-stats']) && isset($p->get_modules()['code-shield']));
t_ok('visitor-stats off by default', ! $p->is_enabled('visitor-stats'));
t_ok('code-shield off by default', ! $p->is_enabled('code-shield'));

// Menu with everything off: the two tabs must still exist.
$GLOBALS['t']['admin']=true; $GLOBALS['t']['menu']=[];
BHTK_Admin::instance()->register_menu();
$slugs = array_map(fn($m)=>$m[1] ?? '', $GLOBALS['t']['menu']);
t_ok('tab bhtk-shield visible while off', in_array('bhtk-shield',$slugs,true), json_encode($slugs));
t_ok('tab bhtk-visits visible while off', in_array('bhtk-visits',$slugs,true));
t_ok('tab bhtk-links visible (on by default)', in_array('bhtk-links',$slugs,true));
t_ok('tab order: shield before visits before settings', array_search('bhtk-shield',$slugs) < array_search('bhtk-visits',$slugs) && array_search('bhtk-visits',$slugs) < array_search('bhtk-settings',$slugs));

// Toggle ON from the tab.
foreach (['code-shield'=>'bhtk-shield','visitor-stats'=>'bhtk-visits'] as $id=>$page) {
  $_POST = ['bhtk_module'=>$id,'bhtk_state'=>'1','bhtk_page'=>$page,'bhtk_view'=>'','_bhtk_nonce'=>'nonce:bhtk_toggle_module_'.$id];
  try { BHTK_Admin::instance()->handle_toggle_module(); t_ok("toggle on $id redirects",false); }
  catch (RedirectException $e) { t_ok("toggle on $id", $p->is_enabled($id) && str_contains($e->getMessage(), "page=$page"), $e->getMessage()); }
}
t_ok('table created on enable', BHTK_Visits::table_exists());
t_ok('cron scheduled on enable', (bool) wp_next_scheduled('bhtk_visits_maintenance'));

// Bad nonce is refused.
$_POST = ['bhtk_module'=>'code-shield','bhtk_state'=>'','bhtk_page'=>'bhtk-shield','_bhtk_nonce'=>'wrong'];
try { BHTK_Admin::instance()->handle_toggle_module(); t_ok('bad nonce refused', false); } catch (WPDieException $e) { t_ok('bad nonce refused', true); }
// No capability is refused.
$GLOBALS['t']['can']=false; $_POST['_bhtk_nonce']='nonce:bhtk_toggle_module_code-shield';
try { BHTK_Admin::instance()->handle_toggle_module(); t_ok('no capability refused', false); } catch (WPDieException $e) { t_ok('no capability refused', true); }
$GLOBALS['t']['can']=true;
// Unknown module is refused.
$_POST = ['bhtk_module'=>'nope','_bhtk_nonce'=>'nonce:bhtk_toggle_module_nope'];
try { BHTK_Admin::instance()->handle_toggle_module(); t_ok('unknown module refused', false); } catch (WPDieException $e) { t_ok('unknown module refused', true); }

// Toggle OFF code-shield: others untouched, tab stays.
$_POST = ['bhtk_module'=>'code-shield','bhtk_page'=>'bhtk-shield','_bhtk_nonce'=>'nonce:bhtk_toggle_module_code-shield'];
try { BHTK_Admin::instance()->handle_toggle_module(); } catch (RedirectException $e) {}
t_ok('toggle off code-shield', ! $p->is_enabled('code-shield'));
t_ok('other modules untouched', $p->is_enabled('visitor-stats') && $p->is_enabled('link-shortener') && $p->is_enabled('qr-generator') && $p->is_enabled('local-avatars'));
$GLOBALS['t']['menu']=[]; BHTK_Admin::instance()->register_menu();
t_ok('shield tab still there while off', in_array('bhtk-shield', array_map(fn($m)=>$m[1] ?? '', $GLOBALS['t']['menu']), true));

// Save module settings through the generic handler. Forged or old keys (text copying, console, page-source decoys, notice, confirmation) are dropped.
$_POST = ['bhtk_module'=>'code-shield','bhtk_page'=>'bhtk-shield','_bhtk_nonce'=>'nonce:bhtk_save_module_code-shield',
  'bhtk_mod'=>['code-shield'=>['menu'=>'1','keys'=>'1','copy'=>'1','devtools'=>'cover','source_decoy'=>'1','seo_ack'=>'1','notice'=>'<b>Hi</b> there']]];
try { BHTK_Admin::instance()->handle_save_module(); } catch (RedirectException $e) { t_ok('save module redirects back to tab', str_contains($e->getMessage(),'page=bhtk-shield')); }
$sh = $p->get_modules()['code-shield'];
$s = $sh->get_settings();
t_ok('shield: the saved options are exactly the five safe ones', ['menu'=>1,'keys'=>1,'drag'=>0,'hide_info'=>0,'admins'=>0]===get_option('bhtk_mod_code_shield'), json_encode(get_option('bhtk_mod_code_shield')));
t_ok('shield: forged risky keys are not stored', !isset($s['copy']) && !isset($s['devtools']) && !isset($s['source_decoy']) && !isset($s['notice']) && !isset($s['seo_ack']), json_encode($s));
$d = $sh->default_settings();
t_ok('shield: every option is off by default', 5===count($d) && 0===array_sum($d));
t_ok('shield: junk values and arrays become off or on safely', ['menu'=>1,'keys'=>0,'drag'=>0,'hide_info'=>0,'admins'=>0]===$sh->sanitize_settings(['menu'=>['a'],'keys'=>'','xx'=>'1']));
t_ok('shield: renamed to Page Shield, id kept so saved settings keep working', 'Page Shield'===$sh->get_name() && 'code-shield'===$sh->get_id());
t_ok('shield: the decoy and cover code is gone', !method_exists($sh,'print_decoy_top') && !method_exists($sh,'effective_settings') && !method_exists($sh,'seo_risks'));
$js = file_get_contents(getenv('PLUGIN_DIR') . '/assets/js/shield.js');
t_ok('shield.js only has the safe options', false===strpos($js,'devtools') && false===strpos($js,'selectstart') && false===strpos($js,'console') && false!==strpos($js,'contextmenu'));
// A site that saved risky options with an older build: they simply do nothing now.
update_option('bhtk_mod_code_shield',['menu'=>0,'keys'=>0,'drag'=>0,'copy'=>1,'devtools'=>'cover','source_decoy'=>1,'hide_info'=>0,'admins'=>0,'notice'=>'x','seo_ack'=>1]);
$GLOBALS['t']['admin']=true; $GLOBALS['t']['can']=true;
$html = t_out(function() use($sh){ $_GET=['page'=>'bhtk-shield']; $sh->render_page(); });
t_ok('shield tab: five checkboxes, no risky group, no confirmation box, no console or cover options', 5===preg_match_all('/type="checkbox" name="bhtk_mod\[code-shield\]\[(menu|keys|drag|hide_info|admins)\]"/',$html) && false===strpos($html,'bhtk_shield_ack') && false===strpos($html,'bhtk-risk--high') && false===strpos($html,'devtools') && false===strpos($html,'source_decoy') && false===strpos($html,'Cover the page') && false===strpos($html,'Code Shield') && false!==strpos($html,'Page Shield'), substr($html,0,300));
t_ok('shield tab: legacy risky values are not shown as ticked', false===strpos($html,'name="bhtk_mod[code-shield][copy]"'));
$sh->save_settings($sh->default_settings());
echo ($GLOBALS['t_fails'] ?? 0) ? "\nFAILED: {$GLOBALS['t_fails']}\n" : "\nSETUP OK\n";
