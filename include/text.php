<?php

// This is our template processor.
// $s is the string requiring macro substitution.
// $r is an array of key value pairs (search => replace)
// returns substituted string.
// WARNING: this is pretty basic, and doesn't properly handle search strings that are substrings of each other.
// For instance if 'test' => "foo" and 'testing' => "bar", testing could become either bar or fooing, 
// depending on the order in which they were declared in the array.   

require_once("include/template_processor.php");

if(! function_exists('replace_macros')) {  
function replace_macros($s,$r) {
	global $t;
	
	return $t->replace($s,$r);

}}


// random hash, 64 chars

if(! function_exists('random_string')) {
function random_string() {
	return(hash('sha256',uniqid(rand(),true)));
}}

/**
 * This is our primary input filter. 
 *
 * The high bit hack only involved some old IE browser, forget which (IE5/Mac?)
 * that had an XSS attack vector due to stripping the high-bit on an 8-bit character
 * after cleansing, and angle chars with the high bit set could get through as markup.
 * 
 * This is now disabled because it was interfering with some legitimate unicode sequences 
 * and hopefully there aren't a lot of those browsers left. 
 *
 * Use this on any text input where angle chars are not valid or permitted
 * They will be replaced with safer brackets. This may be filtered further
 * if these are not allowed either.   
 *
 */

if(! function_exists('notags')) {
function notags($string) {

	return(str_replace(array("<",">"), array('[',']'), $string));

//  High-bit filter no longer used
//	return(str_replace(array("<",">","\xBA","\xBC","\xBE"), array('[',']','','',''), $string));
}}

// use this on "body" or "content" input where angle chars shouldn't be removed,
// and allow them to be safely displayed.

if(! function_exists('escape_tags')) {
function escape_tags($string) {

	return(htmlspecialchars($string));
}}


// generate a string that's random, but usually pronounceable. 
// used to generate initial passwords

if(! function_exists('autoname')) {
function autoname($len) {

	$vowels = array('a','a','ai','au','e','e','e','ee','ea','i','ie','o','ou','u'); 
	if(mt_rand(0,5) == 4)
		$vowels[] = 'y';

	$cons = array(
			'b','bl','br',
			'c','ch','cl','cr',
			'd','dr',
			'f','fl','fr',
			'g','gh','gl','gr',
			'h',
			'j',
			'k','kh','kl','kr',
			'l',
			'm',
			'n',
			'p','ph','pl','pr',
			'qu',
			'r','rh',
			's','sc','sh','sm','sp','st',
			't','th','tr',
			'v',
			'w','wh',
			'x',
			'z','zh'
			);

	$midcons = array('ck','ct','gn','ld','lf','lm','lt','mb','mm', 'mn','mp',
				'nd','ng','nk','nt','rn','rp','rt');

	$noend = array('bl', 'br', 'cl','cr','dr','fl','fr','gl','gr',
				'kh', 'kl','kr','mn','pl','pr','rh','tr','qu','wh');

	$start = mt_rand(0,2);
  	if($start == 0)
    		$table = $vowels;
  	else
    		$table = $cons;

	$word = '';

	for ($x = 0; $x < $len; $x ++) {
  		$r = mt_rand(0,count($table) - 1);
  		$word .= $table[$r];
  
  		if($table == $vowels)
    			$table = array_merge($cons,$midcons);
  		else
    			$table = $vowels;

	}

	$word = substr($word,0,$len);

	foreach($noend as $noe) {
  		if((strlen($word) > 2) && (substr($word,-2) == $noe)) {
    			$word = substr($word,0,-1);
    			break;
  		}
	}
	if(substr($word,-1) == 'q')
		$word = substr($word,0,-1);    
	return $word;
}}


// escape text ($str) for XML transport
// returns escaped text.

if(! function_exists('xmlify')) {
function xmlify($str) {
	$buffer = '';
	
	for($x = 0; $x < strlen($str); $x ++) {
		$char = $str[$x];
        
		switch( $char ) {

			case "\r" :
				break;
			case "&" :
				$buffer .= '&amp;';
				break;
			case "'" :
				$buffer .= '&apos;';
				break;
			case "\"" :
				$buffer .= '&quot;';
				break;
			case '<' :
				$buffer .= '&lt;';
				break;
			case '>' :
				$buffer .= '&gt;';
				break;
			case "\n" :
				$buffer .= "\n";
				break;
			default :
				$buffer .= $char;
				break;
		}	
	}
	$buffer = trim($buffer);
	return($buffer);
}}

// undo an xmlify
// pass xml escaped text ($s), returns unescaped text

if(! function_exists('unxmlify')) {
function unxmlify($s) {
	$ret = str_replace('&amp;','&', $s);
	$ret = str_replace(array('&lt;','&gt;','&quot;','&apos;'),array('<','>','"',"'"),$ret);
	return $ret;	
}}

// convenience wrapper, reverse the operation "bin2hex"

if(! function_exists('hex2bin')) {
function hex2bin($s) {
	if(! ctype_xdigit($s)) {
		logger('hex2bin: illegal input: ' . print_r(debug_backtrace(), true));
		return($s);
	}

	return(pack("H*",$s));
}}

// Automatic pagination.
// To use, get the count of total items.
// Then call $a->set_pager_total($number_items);
// Optionally call $a->set_pager_itemspage($n) to the number of items to display on each page
// Then call paginate($a) after the end of the display loop to insert the pager block on the page
// (assuming there are enough items to paginate).
// When using with SQL, the setting LIMIT %d, %d => $a->pager['start'],$a->pager['itemspage']
// will limit the results to the correct items for the current page. 
// The actual page handling is then accomplished at the application layer. 

if(! function_exists('paginate')) {
function paginate(&$a) {
	$o = '';
	$stripped = preg_replace('/(&page=[0-9]*)/','',$a->query_string);
	$stripped = str_replace('q=','',$stripped);
	$stripped = trim($stripped,'/');
	$pagenum = $a->pager['page'];
	$url = $a->get_baseurl() . '/' . $stripped;


	  if($a->pager['total'] > $a->pager['itemspage']) {
		$o .= '<div class="pager">';
    		if($a->pager['page'] != 1)
			$o .= '<span class="pager_prev">'."<a href=\"$url".'&page='.($a->pager['page'] - 1).'">' . t('prev') . '</a></span> ';

		$o .=  "<span class=\"pager_first\"><a href=\"$url"."&page=1\">" . t('first') . "</a></span> ";

    		$numpages = $a->pager['total'] / $a->pager['itemspage'];

			$numstart = 1;
    		$numstop = $numpages;

    		if($numpages > 14) {
      			$numstart = (($pagenum > 7) ? ($pagenum - 7) : 1);
      			$numstop = (($pagenum > ($numpages - 7)) ? $numpages : ($numstart + 14));
    		}
   
		for($i = $numstart; $i <= $numstop; $i++){
      			if($i == $a->pager['page'])
				$o .= '<span class="pager_current">'.(($i < 10) ? '&nbsp;'.$i : $i);
			else
				$o .= "<span class=\"pager_n\"><a href=\"$url"."&page=$i\">".(($i < 10) ? '&nbsp;'.$i : $i)."</a>";
			$o .= '</span> ';
		}

		if(($a->pager['total'] % $a->pager['itemspage']) != 0) {
			if($i == $a->pager['page'])
				$o .= '<span class="pager_current">'.(($i < 10) ? '&nbsp;'.$i : $i);
			else
				$o .= "<span class=\"pager_n\"><a href=\"$url"."&page=$i\">".(($i < 10) ? '&nbsp;'.$i : $i)."</a>";
			$o .= '</span> ';
		}

		$lastpage = (($numpages > intval($numpages)) ? intval($numpages)+1 : $numpages);
		$o .= "<span class=\"pager_last\"><a href=\"$url"."&page=$lastpage\">" . t('last') . "</a></span> ";

    		if(($a->pager['total'] - ($a->pager['itemspage'] * $a->pager['page'])) > 0)
			$o .= '<span class="pager_next">'."<a href=\"$url"."&page=".($a->pager['page'] + 1).'">' . t('next') . '</a></span>';
		$o .= '</div>'."\r\n";
	}
	return $o;
}}

// Turn user/group ACLs stored as angle bracketed text into arrays

if(! function_exists('expand_acl')) {
function expand_acl($s) {
	// turn string array of angle-bracketed elements into numeric array
	// e.g. "<1><2><3>" => array(1,2,3);
	$ret = array();

	if(strlen($s)) {
		$t = str_replace('<','',$s);
		$a = explode('>',$t);
		foreach($a as $aa) {
			if(intval($aa))
				$ret[] = intval($aa);
		}
	}
	return $ret;
}}		

// Used to wrap ACL elements in angle brackets for storage 

if(! function_exists('sanitise_acl')) {
function sanitise_acl(&$item) {
	if(intval($item))
		$item = '<' . intval(notags(trim($item))) . '>';
	else
		unset($item);
}}


// Convert an ACL array to a storable string

if(! function_exists('perms2str')) {
function perms2str($p) {
	$ret = '';
	$tmp = $p;
	if(is_array($tmp)) {
		array_walk($tmp,'sanitise_acl');
		$ret = implode('',$tmp);
	}
	return $ret;
}}

// generate a guaranteed unique (for this domain) item ID for ATOM
// safe from birthday paradox

if(! function_exists('item_new_uri')) {
function item_new_uri($hostname,$uid) {

	do {
		$dups = false;
		$hash = random_string();

		$uri = "urn:X-dfrn:" . $hostname . ':' . $uid . ':' . $hash;

		$r = q("SELECT `id` FROM `item` WHERE `uri` = '%s' LIMIT 1",
			dbesc($uri));
		if(count($r))
			$dups = true;
	} while($dups == true);
	return $uri;
}}

// Generate a guaranteed unique photo ID.
// safe from birthday paradox

if(! function_exists('photo_new_resource')) {
function photo_new_resource() {

	do {
		$found = false;
		$resource = hash('md5',uniqid(mt_rand(),true));
		$r = q("SELECT `id` FROM `photo` WHERE `resource-id` = '%s' LIMIT 1",
			dbesc($resource)
		);
		if(count($r))
			$found = true;
	} while($found == true);
	return $resource;
}}


// wrapper to load a view template, checking for alternate
// languages before falling back to the default

// obsolete, deprecated.

if(! function_exists('load_view_file')) {
function load_view_file($s) {
	global $lang, $a;
	if(! isset($lang))
		$lang = 'en';
	$b = basename($s);
	$d = dirname($s);
	if(file_exists("$d/$lang/$b"))
		return file_get_contents("$d/$lang/$b");
	
	$theme = current_theme();
	
	if(file_exists("$d/theme/$theme/$b"))
		return file_get_contents("$d/theme/$theme/$b");
			
	return file_get_contents($s);
}}

if(! function_exists('get_intltext_template')) {
function get_intltext_template($s) {
	global $lang;

	if(! isset($lang))
		$lang = 'en';

	if(file_exists("view/$lang/$s"))
		return file_get_contents("view/$lang/$s");
	elseif(file_exists("view/en/$s"))
		return file_get_contents("view/en/$s");
	else
		return file_get_contents("view/$s");
}}

if(! function_exists('get_markup_template')) {
function get_markup_template($s) {

	$theme = current_theme();
	
	if(file_exists("view/theme/$theme/$s"))
		return file_get_contents("view/theme/$theme/$s");
	else
		return file_get_contents("view/$s");

}}





// for html,xml parsing - let's say you've got
// an attribute foobar="class1 class2 class3"
// and you want to find out if it contains 'class3'.
// you can't use a normal sub string search because you
// might match 'notclass3' and a regex to do the job is 
// possible but a bit complicated. 
// pass the attribute string as $attr and the attribute you 
// are looking for as $s - returns true if found, otherwise false

if(! function_exists('attribute_contains')) {
function attribute_contains($attr,$s) {
	$a = explode(' ', $attr);
	if(count($a) && in_array($s,$a))
		return true;
	return false;
}}

if(! function_exists('logger')) {
function logger($msg,$level = 0) {
	$debugging = get_config('system','debugging');
	$loglevel  = intval(get_config('system','loglevel'));
	$logfile   = get_config('system','logfile');

	if((! $debugging) || (! $logfile) || ($level > $loglevel))
		return;
	
	@file_put_contents($logfile, datetime_convert() . ':' . session_id() . ' ' . $msg . "\n", FILE_APPEND);
	return;
}}


if(! function_exists('activity_match')) {
function activity_match($haystack,$needle) {
	if(($haystack === $needle) || ((basename($needle) === $haystack) && strstr($needle,NAMESPACE_ACTIVITY_SCHEMA)))
		return true;
	return false;
}}


// Pull out all #hashtags and @person tags from $s;
// We also get @person@domain.com - which would make 
// the regex quite complicated as tags can also
// end a sentence. So we'll run through our results
// and strip the period from any tags which end with one.
// Returns array of tags found, or empty array.


if(! function_exists('get_tags')) {
function get_tags($s) {
	$ret = array();

	// ignore anything in a code block

	$s = preg_replace('/\[code\](.*?)\[\/code\]/sm','',$s);

	// Match full names against @tags including the space between first and last
	// We will look these up afterward to see if they are full names or not recognisable.

	if(preg_match_all('/(@[^ \x0D\x0A,:?]+ [^ \x0D\x0A,:?]+)([ \x0D\x0A,:?]|$)/',$s,$match)) {
		foreach($match[1] as $mtch) {
			if(strstr($mtch,"]")) {
				// we might be inside a bbcode color tag - leave it alone
				continue;
			}
			if(substr($mtch,-1,1) === '.')
				$ret[] = substr($mtch,0,-1);
			else
				$ret[] = $mtch;
		}
	}

	// Otherwise pull out single word tags. These can be @nickname, @first_last
	// and #hash tags.

	if(preg_match_all('/([@#][^ \x0D\x0A,:?]+)([ \x0D\x0A,:?]|$)/',$s,$match)) {
		foreach($match[1] as $mtch) {
			if(strstr($mtch,"]")) {
				// we might be inside a bbcode color tag - leave it alone
				continue;
			}
			// ignore strictly numeric tags like #1
			if((strpos($mtch,'#') === 0) && ctype_digit(substr($mtch,1)))
				continue;
			if(substr($mtch,-1,1) === '.')
				$ret[] = substr($mtch,0,-1);
			else
				$ret[] = $mtch;
		}
	}
	return $ret;
}}


// quick and dirty quoted_printable encoding

if(! function_exists('qp')) {
function qp($s) {
return str_replace ("%","=",rawurlencode($s));
}} 



if(! function_exists('get_mentions')) {
function get_mentions($item) {
	$o = '';
	if(! strlen($item['tag']))
		return $o;

	$arr = explode(',',$item['tag']);
	foreach($arr as $x) {
		$matches = null;
		if(preg_match('/@\[url=([^\]]*)\]/',$x,$matches)) {
			$o .= "\t\t" . '<link rel="mentioned" href="' . $matches[1] . '" />' . "\r\n";
			$o .= "\t\t" . '<link rel="ostatus:attention" href="' . $matches[1] . '" />' . "\r\n";
		}
	}
	return $o;
}}

if(! function_exists('contact_block')) {
function contact_block() {
	$o = '';
	$a = get_app();

	$shown = get_pconfig($a->profile['uid'],'system','display_friend_count');
	if(! $shown)
		$shown = 24;

	if((! is_array($a->profile)) || ($a->profile['hide-friends']))
		return $o;
	$r = q("SELECT COUNT(*) AS `total` FROM `contact` WHERE `uid` = %d AND `self` = 0 AND `blocked` = 0 and `pending` = 0",
			intval($a->profile['uid'])
	);
	if(count($r)) {
		$total = intval($r[0]['total']);
	}
	if(! $total) {
		$o .= '<h4 class="contact-h4">' . t('No contacts') . '</h4>';
		return $o;
	}
	$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `self` = 0 AND `blocked` = 0 and `pending` = 0 ORDER BY RAND() LIMIT %d",
			intval($a->profile['uid']),
			intval($shown)
	);
	if(count($r)) {
		$o .= '<h4 class="contact-h4">' .  sprintf( tt('%d Contact','%d Contacts', $total),$total) . '</h4><div id="contact-block">';
		foreach($r as $rr) {
			$o .= micropro($rr,true,'mpfriend');
		}
		$o .= '</div><div id="contact-block-end"></div>';
		$o .=  '<div id="viewcontacts"><a id="viewcontacts-link" href="viewcontacts/' . $a->profile['nickname'] . '">' . t('View Contacts') . '</a></div>';
		
	}

	$arr = array('contacts' => $r, 'output' => $o);

	call_hooks('contact_block_end', $arr);
	return $o;

}}

if(! function_exists('micropro')) {
function micropro($contact, $redirect = false, $class = '', $textmode = false) {

	if($class)
		$class = ' ' . $class;

	$url = $contact['url'];
	$sparkle = '';

	if($redirect) {
		$a = get_app();
		$redirect_url = $a->get_baseurl() . '/redir/' . $contact['id'];
		if(local_user() && ($contact['uid'] == local_user()) && ($contact['network'] === 'dfrn')) {
			$url = $redirect_url;
			$sparkle = ' sparkle';
		}
	}
	$click = ((x($contact,'click')) ? ' onclick="' . $contact['click'] . '" ' : '');
	if($click)
		$url = '';
	if($textmode) {
		return '<div class="contact-block-textdiv' . $class . '"><a class="contact-block-link' . $class . $sparkle 
			. (($click) ? ' fakelink' : '') . '" '
			. (($url) ? ' href="' . $url . '"' : '') . $click
			. '" title="' . $contact['name'] . ' [' . $contact['url'] . ']" alt="' . $contact['name'] 
			. '" >'. $contact['name'] . '</a></div>' . "\r\n";
	}
	else {
		return '<div class="contact-block-div' . $class . '"><a class="contact-block-link' . $class . $sparkle 
			. (($click) ? ' fakelink' : '') . '" '
			. (($url) ? ' href="' . $url . '"' : '') . $click . ' ><img class="contact-block-img' . $class . $sparkle . '" src="' 
			. $contact['micro'] . '" title="' . $contact['name'] . ' [' . $contact['url'] . ']" alt="' . $contact['name'] 
			. '" /></a></div>' . "\r\n";
	}
}}



if(! function_exists('search')) {
function search($s,$id='search-box',$url='/search') {
	$a = get_app();
	$o  = '<div id="' . $id . '">';
	$o .= '<form action="' . $a->get_baseurl() . $url . '" method="get" >';
	$o .= '<input type="text" name="search" id="search-text" value="' . $s .'" />';
	$o .= '<input type="submit" name="submit" id="search-submit" value="' . t('Search') . '" />'; 
	$o .= '</form></div>';
	return $o;
}}

if(! function_exists('valid_email')) {
function valid_email($x){
	if(preg_match('/^[_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)+$/',$x))
		return true;
	return false;
}}


if(! function_exists('aes_decrypt')) {
function aes_decrypt($val,$ky)
{
    $key="\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
    for($a=0;$a<strlen($ky);$a++)
      $key[$a%16]=chr(ord($key[$a%16]) ^ ord($ky[$a]));
    $mode = MCRYPT_MODE_ECB;
    $enc = MCRYPT_RIJNDAEL_128;
    $dec = @mcrypt_decrypt($enc, $key, $val, $mode, @mcrypt_create_iv( @mcrypt_get_iv_size($enc, $mode), MCRYPT_DEV_URANDOM ) );
    return rtrim($dec,(( ord(substr($dec,strlen($dec)-1,1))>=0 and ord(substr($dec, strlen($dec)-1,1))<=16)? chr(ord( substr($dec,strlen($dec)-1,1))):null));
}}


if(! function_exists('aes_encrypt')) {
function aes_encrypt($val,$ky)
{
    $key="\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
    for($a=0;$a<strlen($ky);$a++)
      $key[$a%16]=chr(ord($key[$a%16]) ^ ord($ky[$a]));
    $mode=MCRYPT_MODE_ECB;
    $enc=MCRYPT_RIJNDAEL_128;
    $val=str_pad($val, (16*(floor(strlen($val) / 16)+(strlen($val) % 16==0?2:1))), chr(16-(strlen($val) % 16)));
    return mcrypt_encrypt($enc, $key, $val, $mode, mcrypt_create_iv( mcrypt_get_iv_size($enc, $mode), MCRYPT_DEV_URANDOM));
}} 


/**
 *
 * Function: linkify
 *
 * Replace naked text hyperlink with HTML formatted hyperlink
 *
 */

if(! function_exists('linkify')) {
function linkify($s) {
	$s = preg_replace("/(https?\:\/\/[a-zA-Z0-9\:\/\-\?\&\.\=\_\~\#\'\%\$\!\+]*)/", ' <a href="$1" target="external-link">$1</a>', $s);
	return($s);
}}


/**
 * 
 * Function: smilies
 *
 * Description:
 * Replaces text emoticons with graphical images
 *
 * @Parameter: string $s
 *
 * Returns string
 */

if(! function_exists('smilies')) {
function smilies($s) {
	$a = get_app();

	return str_replace(
	array( '&lt;3', '&lt;/3', '&lt;\\3', ':-)', ':)', ';-)', ':-(', ':(', ':-P', ':P', ':-"', ':-x', ':-X', ':-D', '8-|', '8-O'),
	array(
		'<img src="' . $a->get_baseurl() . '/images/smiley-heart.gif" alt="<3" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-brokenheart.gif" alt="</3" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-brokenheart.gif" alt="<\\3" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-smile.gif" alt=":-)" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-smile.gif" alt=":)" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-wink.gif" alt=";-)" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-frown.gif" alt=":-(" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-frown.gif" alt=":(" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-tongue-out.gif" alt=":-P" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-tongue-out.gif" alt=":P" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-kiss.gif" alt=":-\"" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-kiss.gif" alt=":-x" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-kiss.gif" alt=":-X" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-laughing.gif" alt=":-D" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-surprised.gif" alt="8-|" />',
		'<img src="' . $a->get_baseurl() . '/images/smiley-surprised.gif" alt="8-O" />'
	), $s);
}}



if(! function_exists('day_translate')) {
function day_translate($s) {
	$ret = str_replace(array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
		array( t('Monday'), t('Tuesday'), t('Wednesday'), t('Thursday'), t('Friday'), t('Saturday'), t('Sunday')),
		$s);

	$ret = str_replace(array('January','February','March','April','May','June','July','August','September','October','November','December'),
		array( t('January'), t('February'), t('March'), t('April'), t('May'), t('June'), t('July'), t('August'), t('September'), t('October'), t('November'), t('December')),
		$ret);

	return $ret;
}}


if(! function_exists('normalise_link')) {
function normalise_link($url) {
	$ret = str_replace(array('https:','//www.'), array('http:','//'), $url);
	return(rtrim($ret,'/'));
}}

/**
 *
 * Compare two URLs to see if they are the same, but ignore
 * slight but hopefully insignificant differences such as if one 
 * is https and the other isn't, or if one is www.something and 
 * the other isn't - and also ignore case differences.
 *
 * Return true if the URLs match, otherwise false.
 *
 */

if(! function_exists('link_compare')) {
function link_compare($a,$b) {
	if(strcasecmp(normalise_link($a),normalise_link($b)) === 0)
		return true;
	return false;
}}


if(! function_exists('prepare_body')) {
function prepare_body($item,$attach = false) {

	$s = prepare_text($item['body']);
	if(! $attach)
		return $s;

	$arr = explode(',',$item['attach']);
	if(count($arr)) {
		$s .= '<div class="body-attach">';
		foreach($arr as $r) {
			$matches = false;
			$icon = '';
			$cnt = preg_match('|\[attach\]href=\"(.*?)\" size=\"(.*?)\" type=\"(.*?)\" title=\"(.*?)\"\[\/attach\]|',$r,$matches);
			if($cnt) {
				$icontype = strtolower(substr($matches[3],0,strpos($matches[3],'/')));
				switch($icontype) {
					case 'video':
					case 'audio':
					case 'image':
					case 'text':
						$icon = '<div class="attachtype type-' . $icontype . '"></div>';
						break;
					default:
						$icon = '<div class="attachtype type-unkn"></div>';
						break;
				}
				$title = ((strlen(trim($matches[4]))) ? escape_tags(trim($matches[4])) : escape_tags($matches[1]));
				$title .= ' ' . $matches[2] . ' ' . t('bytes');

				$s .= '<a href="' . strip_tags($matches[1]) . '" title="' . $title . '" class="attachlink" target="external-link" >' . $icon . '</a>';
			}
		}
		$s .= '<div class="clear"></div></div>';
	}
	return $s;
}}

if(! function_exists('prepare_text')) {
function prepare_text($text) {

	require_once('include/bbcode.php');

	$s = smilies(bbcode($text));

	return $s;
}}


/**
 * return atom link elements for all of our hubs
 */

if(! function_exists('feed_hublinks')) {
function feed_hublinks() {

	$hub = get_config('system','huburl');

	$hubxml = '';
	if(strlen($hub)) {
		$hubs = explode(',', $hub);
		if(count($hubs)) {
			foreach($hubs as $h) {
				$h = trim($h);
				if(! strlen($h))
					continue;
				$hubxml .= '<link rel="hub" href="' . xmlify($h) . '" />' . "\n" ;
			}
		}
	}
	return $hubxml;
}}

/* return atom link elements for salmon endpoints */

if(! function_exists('feed_salmonlinks')) {
function feed_salmonlinks($nick) {

	$a = get_app();

	$salmon  = '<link rel="salmon" href="' . xmlify($a->get_baseurl() . '/salmon/' . $nick) . '" />' . "\n" ;

	// old style links that status.net still needed as of 12/2010 

	$salmon .= '  <link rel="http://salmon-protocol.org/ns/salmon-replies" href="' . xmlify($a->get_baseurl() . '/salmon/' . $nick) . '" />' . "\n" ; 
	$salmon .= '  <link rel="http://salmon-protocol.org/ns/salmon-mention" href="' . xmlify($a->get_baseurl() . '/salmon/' . $nick) . '" />' . "\n" ; 
	return $salmon;
}}

if(! function_exists('get_plink')) {
function get_plink($item) {
	$a = get_app();	
	$plink = (((x($item,'plink')) && (! $item['private'])) ? '<div class="wall-item-links-wrapper"><a href="' 
			. $item['plink'] . '" title="' . t('link to source') . '" target="external-link" class="icon remote-link"></a></div>' : '');
	return $plink;
}}

if(! function_exists('unamp')) {
function unamp($s) {
	return str_replace('&amp;', '&', $s);
}}




if(! function_exists('lang_selector')) {
function lang_selector() {
	global $lang;
	$o = '<div id="lang-select-icon" class="icon language" title="' . t('Select an alternate language') . '" onclick="openClose(\'language-selector\');" ></div>';
	$o .= '<div id="language-selector" style="display: none;" >';
	$o .= '<form action="" method="post" ><select name="system_language" onchange="this.form.submit();" >';
	$langs = glob('view/*/strings.php');
	if(is_array($langs) && count($langs)) {
		if(! in_array('view/en/strings.php',$langs))
			$langs[] = 'view/en/';
		asort($langs);
		foreach($langs as $l) {
			$ll = substr($l,5);
			$ll = substr($ll,0,strrpos($ll,'/'));
			$selected = (($ll === $lang) ? ' selected="selected" ' : '');
			$o .= '<option value="' . $ll . '"' . $selected . '>' . $ll . '</option>';
		}
	}
	$o .= '</select></form></div>';
	return $o;
}}


if(! function_exists('return_bytes')) {
function return_bytes ($size_str) {
    switch (substr ($size_str, -1))
    {
        case 'M': case 'm': return (int)$size_str * 1048576;
        case 'K': case 'k': return (int)$size_str * 1024;
        case 'G': case 'g': return (int)$size_str * 1073741824;
        default: return $size_str;
    }
}}

function generate_guid() {
	$found = true;
	do {
		$guid = substr(random_string(),0,16);
		$x = q("SELECT `uid` FROM `user` WHERE `guid` = '%s' LIMIT 1",
			dbesc($guid)
		);
		if(! count($x))
			$found = false;
	} while ($found == true );
	return $guid;
}


function pkcs5_pad ($text, $blocksize)
{
    $pad = $blocksize - (strlen($text) % $blocksize);
    return $text . str_repeat(chr($pad), $pad);
}

function pkcs5_unpad($text)
{
    $pad = ord($text{strlen($text)-1});
    if ($pad > strlen($text)) return false;
    if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) return false;
    return substr($text, 0, -1 * $pad);
} 


function base64url_encode($s, $strip_padding = false) {

	$s = strtr(base64_encode($s),'+/','-_');

	if($strip_padding)
		$s = str_replace('=','',$s);

	return $s;
}

function base64url_decode($s) {

/*
 *  // Placeholder for new rev of salmon which strips base64 padding.
 *  // PHP base64_decode handles the un-padded input without requiring this step
 *  // Uncomment if you find you need it.
 *
 *	$l = strlen($s);
 *	if(! strpos($s,'=')) {
 *		$m = $l % 4;
 *		if($m == 2)
 *			$s .= '==';
 *		if($m == 3)
 *			$s .= '=';
 *	}
 *
 */

	return base64_decode(strtr($s,'-_','+/'));
}
