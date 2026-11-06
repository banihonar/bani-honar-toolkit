<?php
require __DIR__ . '/harness.php';
$p = BHTK_Plugin::instance();
$GLOBALS['t']['admin'] = true; $GLOBALS['t']['can'] = true;
BHTK_Admin::instance()->register_menu();
t_ok('contact tab registered last', 'bhtk-contact' === end($GLOBALS['t']['menu'])[1]);
t_ok('address of the IT team', 'it@banihonarpars.ir' === BHTK_Contact::email());
foreach (['suggestion','criticism','problem','module','security'] as $k) {
  $u = BHTK_Contact::mailto($k);
  t_ok("mailto $k: address, subject with version, body", 0===strpos($u,'mailto:it@banihonarpars.ir?subject=') && false!==strpos($u,rawurlencode('[Bani Honar Toolkit '.BHTK_VERSION.']')) && false!==strpos($u,'&body=') && strlen($u) < 1800 && !preg_match('/[\r\n <>"]/',$u), $u);
}
t_ok('unknown kind gives nothing', '' === BHTK_Contact::mailto('x') && '' === BHTK_Contact::mailto("problem\r\nBcc: a@b.c"));
parse_str(parse_url(BHTK_Contact::mailto('problem'), PHP_URL_QUERY), $q);
t_ok('problem report carries the technical details, no site address', false!==strpos($q['body'],'Bani Honar Toolkit: '.BHTK_VERSION) && false!==strpos($q['body'],'PHP: ') && false!==strpos($q['body'],'WordPress: 6.8.1') && false===strpos($q['body'],'banihonarpars.ir/') && false===strpos($q['body'],'http'), $q['body']);
parse_str(parse_url(BHTK_Contact::mailto('suggestion'), PHP_URL_QUERY), $q2);
t_ok('suggestion carries no technical details', false===strpos($q2['body'],'PHP:') && false!==strpos($q2['body'],"\n"), $q2['body']);
// Three ways to write, because many computers have no e-mail program set up.
foreach (['suggestion','criticism','problem','module','security'] as $k) {
  $g = BHTK_Contact::gmail($k);
  t_ok("gmail $k: compose link to the IT team with subject and body", 0===strpos($g,'https://mail.google.com/mail/?view=cm&fs=1&to=it%40banihonarpars.ir&su=') && false!==strpos($g,'&body=') && strlen($g) < 1800 && !preg_match('/[\r\n <>"\']/',$g), $g);
  $pl = BHTK_Contact::plain($k);
  t_ok("plain $k: address, subject and text, ready to paste", 0===strpos($pl,"To: it@banihonarpars.ir\nSubject: [Bani Honar Toolkit ".BHTK_VERSION.'] ') && false!==strpos($pl,"\n\nHello IT team,"), $pl);
}
t_ok('unknown kind gives no gmail link and no text', '' === BHTK_Contact::gmail('x') && '' === BHTK_Contact::plain('x') && '' === BHTK_Contact::gmail("problem\r\nBcc: a@b.c"));
parse_str(parse_url(BHTK_Contact::gmail('problem'), PHP_URL_QUERY), $gq);
t_ok('gmail problem link carries the details, no site address', false!==strpos($gq['body'],'PHP: ') && false===strpos($gq['body'],'http') && 'it@banihonarpars.ir'===$gq['to']);
$html = t_out(['BHTK_Contact','render']);
t_ok('screen shows 5 cards, the address, a copy button per card and the note', 5===substr_count($html,'class="bhtk-contact__item ') && substr_count($html,'it@banihonarpars.ir')>=3 && 7<=substr_count($html,'data-copy=') && false!==strpos($html,'Nothing is sent from your website') && false!==strpos($html,'Copy message'));
t_ok('every card has a Gmail link that opens a new tab safely and a mail-app link', 5===substr_count($html,'href="https://mail.google.com/mail/?view=cm&#038;fs=1&#038;to=it%40banihonarpars.ir') && 5===substr_count($html,'target="_blank" rel="noopener noreferrer"') && 5===substr_count($html,'href="mailto:it@banihonarpars.ir?subject=') && false===strpos($html,'&body') );
t_ok('no inline script, no form, no external request', false===strpos($html,'<script') && false===strpos($html,'<form') && false===strpos($html,'http://') && 0===preg_match_all('/<(?:script|iframe)\b/',$html));
$GLOBALS['t']['can'] = false; $r='none'; try { t_out(['BHTK_Contact','render']); } catch (WPDieException $e) { $r='die'; }
t_ok('screen needs the plugin capability', 'die'===$r);
$GLOBALS['t']['can'] = true;
$dash = t_out([BHTK_Admin::instance(),'render_dashboard']);
t_ok('dashboard points to the contact screen', false!==strpos($dash,'page=bhtk-contact'));
echo ($GLOBALS['t_fails'] ?? 0) ? "\nFAILED: {$GLOBALS['t_fails']}\n" : "\nCONTACT OK\n";
