<?php
define('ABSPATH','/'); define('BHTK_VERSION','2.3.0');
class BHTK_I18n { static function lang(){return "fa";} }
function __($s){return $s;}
function wp_parse_url($u,$c=-1){return parse_url($u,$c);}
$P=rtrim( getenv('PLUGIN_DIR'), '/' ).'/modules/visitor-stats/';
foreach(['countries','ua','referrer','geo'] as $f) require $P."class-bhtk-$f.php";
$fail=0; function t($name,$got,$exp){global $fail; $ok=($got===$exp); if(!$ok)$fail++; echo ($ok?"OK   ":"FAIL ").$name.($ok?"":"\n   got: ".json_encode($got,JSON_UNESCAPED_UNICODE)."\n   exp: ".json_encode($exp,JSON_UNESCAPED_UNICODE))."\n";}
function pick($r){return $r['type'].'|'.$r['brand'].'|'.$r['model'].'|'.$r['code'].'|'.$r['os'].' '.$r['os_version'].'|'.$r['browser'].' '.$r['browser_version'];}

$uas = [
 ['S23 Ultra Chrome',"Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36",[],'mobile|Samsung|Galaxy S23 Ultra|SM-S918B|Android 14|Chrome 120'],
 ['Reduced UA + hint',"Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36",['model'=>'2201116SG','platform'=>'Android','platform_version'=>'13.0.0','mobile'=>true],'mobile|Xiaomi|2201116SG||Android 13|Chrome 124'],
 ['Reduced UA no hint',"Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Mobile Safari/537.36",[],'mobile||||Android 10|Chrome 124'],
 ['Pixel',"Mozilla/5.0 (Linux; Android 13; Pixel 7 Pro) AppleWebKit/537.36 Chrome/118.0.0.0 Mobile Safari/537.36",[],'mobile|Google|Pixel 7 Pro||Android 13|Chrome 118'],
 ['Redmi Note',"Mozilla/5.0 (Linux; Android 12; Redmi Note 11) AppleWebKit/537.36 Chrome/110.0.0.0 Mobile Safari/537.36",[],'mobile|Xiaomi|Redmi Note 11||Android 12|Chrome 110'],
 ['iPhone Safari',"Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1",['width'=>390,'height'=>844,'dpr'=>3],'mobile|Apple|iPhone 12 / 13 / 14||iOS 17.2|Safari 17'],
 ['iPad desktop mode',"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15",['touch'=>5],'tablet|Apple|iPad||iPadOS |Safari 17'],
 ['Mac',"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.1 Safari/605.1.15",[],'desktop|Apple|Mac||macOS |Safari 17'],
 ['Win10/11 chrome no hint',"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",[],'desktop||PC||Windows 10/11|Chrome 120'],
 ['Win11 hint',"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",['platform_version'=>'15.0.0'],'desktop||PC||Windows 11|Chrome 120'],
 ['Edge',"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.2210.91",['platform_version'=>'10.0.0'],'desktop||PC||Windows 10|Edge 120'],
 ['Firefox Linux',"Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0",[],'desktop||PC||Ubuntu |Firefox 121'],
 ['Samsung Internet tablet',"Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Safari/537.36",[],'tablet|Samsung|SM-X710||Android 13|Samsung Internet 23'],
 ['Instagram IAB',"Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/20G75 Instagram 305.0.1.9.108 (iPhone13,2; iOS 16_6; en_US; en-US; scale=3.00; 1170x2532; 5)",[],'mobile|Apple|iPhone||iOS 16.6|Instagram 305'],
 ['Android WebView',"Mozilla/5.0 (Linux; Android 11; SM-A525F Build/RP1A.200720.012; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/112.0.0.0 Mobile Safari/537.36",[],'mobile|Samsung|Galaxy A52|SM-A525F|Android 11|WebView 112'],
 ['Googlebot',"Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",[],'bot|||| |Bot '],
 ['curl',"curl/8.0.1",[],'bot|||| |Bot '],
 ['Huawei',"Mozilla/5.0 (Linux; Android 10; ELE-L29) AppleWebKit/537.36 Chrome/99.0.4844.88 Mobile Safari/537.36",[],'mobile|Huawei|ELE-L29||Android 10|Chrome 99'],
];
foreach($uas as $u){ $got = pick(BHTK_UA::parse($u[1],$u[2])); t($u[0],$got,$u[3]); }
t('empty UA is bot', BHTK_UA::is_bot(''), true);
t('Chrome not bot', BHTK_UA::is_bot("Mozilla/5.0 (Linux; Android 14; SM-S918B) Chrome/120 Mobile Safari/537.36"), false);

$site='banihonarpars.ir';
$c = function($r,$s='',$m='',$ua='') use($site){ $x=BHTK_Referrer::classify(BHTK_Referrer::clean_url($r),$site,$s,$m,$ua); return $x['type'].'|'.$x['name'].'|'.$x['host']; };
t('google', $c('https://www.google.com/search?q=x'), 'search|Google|google.com');
t('google ir', $c('https://www.google.co.uk/'), 'search|Google|google.co.uk');
t('telegram', $c('https://t.me/channel/12'), 'social|Telegram|t.me');
t('instagram l.', $c('https://l.instagram.com/?u=x'), 'social|Instagram|l.instagram.com');
t('eitaa', $c('https://eitaa.com/x'), 'social|Eitaa|eitaa.com');
t('internal', $c('https://banihonarpars.ir/home-video/'), 'internal||banihonarpars.ir');
t('internal www', $c('https://www.banihonarpars.ir/'), 'internal||banihonarpars.ir');
t('lookalike not internal', $c('https://evilbanihonarpars.ir/'), 'referral|evilbanihonarpars.ir|evilbanihonarpars.ir');
t('direct', $c(''), 'direct||');
t('android app telegram', $c('android-app://org.telegram.messenger'), 'social|Telegram|org.telegram.messenger');
t('utm', $c('', 'instagram','story'), 'campaign|Instagram|');
t('utm custom', $c('https://t.me/x', 'Aban-Sale','cpc'), 'campaign|Aban-Sale|t.me');
t('instagram IAB no referrer', $c('', '', '', 'Mozilla Instagram 305'), 'social|Instagram|');
t('referral', $c('https://www.digikala.com/x?y=1'), 'referral|digikala.com|digikala.com');
t('clean url strips query', BHTK_Referrer::clean_url('https://a.com/p?token=SECRET#frag'), 'https://a.com/p');
t('clean url rejects js', BHTK_Referrer::clean_url('javascript:alert(1)'), '');

$s=function($a){return BHTK_Geo::client_ip('auto',$a);};
t('remote only', $s(['REMOTE_ADDR'=>'5.6.7.8']), '5.6.7.8');
t('CF header', $s(['REMOTE_ADDR'=>'10.0.0.1','HTTP_CF_CONNECTING_IP'=>'89.1.2.3']), '89.1.2.3');
t('XFF public first', $s(['REMOTE_ADDR'=>'10.0.0.1','HTTP_X_FORWARDED_FOR'=>'192.168.1.2, 91.99.1.1, 10.0.0.1']), '91.99.1.1');
t('XFF spoof ignored in remote_addr mode', BHTK_Geo::client_ip('remote_addr',['REMOTE_ADDR'=>'5.6.7.8','HTTP_X_FORWARDED_FOR'=>'1.1.1.1']), '5.6.7.8');
t('ipv6 mapped', $s(['REMOTE_ADDR'=>'::ffff:5.6.7.8']), '5.6.7.8');
t('bad header ignored', $s(['REMOTE_ADDR'=>'5.6.7.8','HTTP_CF_CONNECTING_IP'=>'<script>']), '5.6.7.8');
t('port stripped', BHTK_Geo::normalize('1.2.3.4:8080'), '1.2.3.4');
t('anon v4', BHTK_Geo::anonymize('91.99.12.34'), '91.99.12.0');
t('anon v6', BHTK_Geo::anonymize('2a01:4f8:c17:1234:5678::1'), '2a01:4f8:c17::');
t('list exact', BHTK_Geo::in_list('1.2.3.4', "9.9.9.9\n1.2.3.4"), true);
t('list cidr', BHTK_Geo::in_list('192.0.2.77', "192.0.2.0/24"), true);
t('list cidr miss', BHTK_Geo::in_list('192.0.3.77', "192.0.2.0/24"), false);
t('clean list', BHTK_Geo::clean_list("1.2.3.4\nfoo\n10.0.0.0/8\n1.2.3.4\n300.1.1.1"), "1.2.3.4\n10.0.0.0/8");
t('country CF', BHTK_Geo::country_from_headers(['HTTP_CF_IPCOUNTRY'=>'ir']), 'IR');
t('country XX rejected', BHTK_Geo::country_from_headers(['HTTP_CF_IPCOUNTRY'=>'XX']), '');
t('country T1 rejected', BHTK_Geo::country_from_headers(['HTTP_CF_IPCOUNTRY'=>'T1']), '');
t('parse ipwho', BHTK_Geo::parse_response('ipwho','{"ip":"1.1.1.1","success":true,"country_code":"AU"}'), 'AU');
t('parse ipwho fail', BHTK_Geo::parse_response('ipwho','{"success":false,"message":"limit"}'), '');
t('parse geojs obj', BHTK_Geo::parse_response('geojs','{"country":"IR","ip":"5.6.7.8","name":"Iran"}'), 'IR');
t('parse geojs list', BHTK_Geo::parse_response('geojs','[{"country":"DE","ip":"1.2.3.4"}]'), 'DE');
t('removed provider ipapi is not a provider', isset(BHTK_Geo::providers()['ipapi']), false);
t('providers are ipwho and geojs', array_keys(BHTK_Geo::providers()), ['ipwho','geojs']);
t('unknown parser gives empty', BHTK_Geo::parse_response('ipapi','{"country":"US"}'), '');
t('flag IR hex', bin2hex(BHTK_Countries::flag('IR')), bin2hex("🇮🇷"));
t('name fa', BHTK_Countries::name('IR'), 'ایران');
t('name en', BHTK_Countries::name('DE','en'), 'Germany');
echo $fail? "\n$fail FAILED\n" : "\nALL PASSED\n";
