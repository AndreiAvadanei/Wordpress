<?php
/*

Plugin Name: WIT Automatic Link Exchange
Plugin URI: http://www.worldit.info
Description: WIT Automatic Link Exchange este un plugin care ofera un sistem automat de link exchange, oferind posibilitatea de a crea clasamente saptamanare, lunare etc. pentru incurajarea acestui gen de parteneriate. Pluginul are un sistem automat de monitorizare a link-urilor inscrise in "director". Clasamentele sunt organizate in functie de reffering, de voturile primite etc. Clasamentele se reseteaza, desi istoria pastreaza clasamentele in arhiva.
Version: 0.1
Author: Avadanei Andrei
Author URI: http://www.worldit.info/

*/

/*
        0 - titlu
		1 - link
		2 - categorie
		3 - descriere
		4 - linke
		5 - nume/prenume
		6 - mail
		7 - ok 
		8 - show
		9 - likes
		10 - vizite
		11 - ID unique
*/
define('wit_ale_version','0.1');
register_activation_hook(__FILE__, 'wit_ale_activate_settings');
register_deactivation_hook(__FILE__, 'wit_ale_deactivate_settings');
add_action('admin_menu', 'wit_ale_add_menu' );
add_action('wit_ale_validate_links', 'wit_ale_validate_links');
add_action('wit_ale_validate_queue', 'wit_ale_validate_queue');
add_action('wit_ale_reset_links', 'wit_ale_reset_links');
add_action('get_header','wit_ale_check_refferer');
add_filter('the_content','wit_ale_checkpage');


define('DOMAIN','worldit.info');
define('PAGE','http://www.worldit.info/schimb-de-linkuri/');

define('REGEX_1','http:\/\/www\.'.preg_replace('/([\.\-])/','\\\$1',DOMAIN).'/');
define('REGEX_2','http:\/\/'.preg_replace('/([\.\-])/','\\\$1',DOMAIN).'/');
wit_ale_validate_queue();


function wit_ale_activate_settings()
{
	global $wpdb;
	update_option('ale_activated' ,'started');
	update_option('ale_interval'  ,'14');//every 2 weeks
	update_option('ale_links','15');
	update_option('ale_update_queue',''); 
	update_option('ale_links_data',''); 
	update_option('ale_history','');
	update_option('ale_count_refferers','yes');
	update_option('ale_count_lovers','yes');
	update_option('ale_url_ips','');
	wp_schedule_event(time(), 'daily', 'wit_ale_validate_links');
	
	$time = time();
	$interval = (int)get_option('ale_interval');
	update_option('ale_event',$time.':'.($time+((int)$interval*24*60*60)));
	 
	wp_schedule_single_event($time+((int)$interval*24*60*60), 'wit_ale_reset_links'); 
}
function wit_ale_deactivate_settings()
{
	wp_clear_scheduled_hook('wit_ale_validate_links');
	wp_clear_scheduled_hook('wit_ale_validate_queue');
	wp_clear_scheduled_hook('wit_ale_reset_links');
}
function wit_ale_reset_links()
{
	$links_parsed = wit_ale_links_parse(get_option('ale_links_data'));
	$sortl    = wit_ale_links_parse(array_slice(wit_ale_sort_by($links_parsed,  9),0,3), TRUE);
	$sortv    = wit_ale_links_parse(array_slice(wit_ale_sort_by($links_parsed, 10),0,3), TRUE);
	
	$history  = get_option('ale_history'); if($history != '') $history .= '[::][::]';
	$history  = implode('[::||::]', array(get_option('ale_event'), $sortl,$sortv)).$history;
	$time     = time();
	$interval = (int)get_option('ale_interval');
	
	
	wp_clear_scheduled_hook('wit_ale_reset_links');
	wp_schedule_single_event($time+((int)$interval*24*60*60), 'wit_ale_reset_links'); 
	foreach($links_parsed as $key => $link) {$links_parsed[$key][9]=0;$links_parsed[$key][10]=0;}
	
	update_option('ale_event'     , $time.':'.($time+((int)$interval*24*60*60)));
	update_option('ale_history'   , $history);
	 
	 
	update_option('ale_links_data', wit_ale_links_parse($links_parsed, TRUE));
	update_option('ale_url_ips'   , '');
}
function checkMail($email)
{		
	  if (!ereg("^[^@]{1,64}@[^@]{1,255}$", $email)) 
		return FALSE;
	  
	 
	  $email_array = explode("@", $email);
	  $local_array = explode(".", $email_array[0]);
	  for ($i = 0; $i < sizeof($local_array); $i++) 
		 if (!ereg("^(([A-Za-z0-9!#$%&'*+/=?^_`{|}~-][A-Za-z0-9!#$%&'*+/=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$", $local_array[$i])) 
			return FALSE;			
	   
	  if (!ereg("^\[?[0-9\._-]+\]?$", $email_array[1])) 
	  { 
		  $domain_array = explode(".", $email_array[1]);
		  if (sizeof($domain_array) < 2) 
			  return FALSE; 
		}
		
		for ($i = 0; $i < sizeof($domain_array); $i++) 
		  if (!ereg("^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$", $domain_array[$i])) 
			  return FALSE;	
	return TRUE;	
}
function wit_ale_check_refferer()
{
	if(get_option('ale_count_refferers')!= '') 
	{			
		$refferer     = parse_url($_SERVER['HTTP_REFERER']);
		$links_parsed = wit_ale_links_parse(get_option('ale_links_data'));
		if(isset($refferer['host'])) {
			foreach($links_parsed as $key => $urld)
				if(stripos($urld[1], $refferer['host']) !== FALSE) {
					$links_parsed[$key][10]++;
					update_option('ale_links_data', wit_ale_links_parse($links_parsed, TRUE));	
					break;	
				}
		}
	}
}
function wit_ale_add_menu()
{
	add_options_page('Automatic Link Exchange', 'Automatic Link Exchange', 10, __FILE__, 'wit_ale_settings');
}
function wit_ale_validate_links() 
{  
	$content = explode('(::||::)',get_option('ale_links_data'));
	if(sizeof($content)) {
		$to_update = '0';
		for($i=1;$i<sizeof($content);$i++) $to_update .= ','.$i;		
		update_option('ale_update_queue',$to_update); 
	}
} 
function wit_ale_validate_queue()
{ 
	
	$to_update = explode(',',get_option('ale_update_queue'));
	if(!is_array($to_update)) $to_update = array($to_update);
	$links = wit_ale_links_parse(get_option('ale_links_data'));
 	 
	for($i = 0; $i < 1; $i++) 
	{
		if(isset($to_update[$i]) && is_numeric($to_update[$i]) && wit_ale_validate($links[$to_update[$i]]) !== TRUE)
		{ 
			$links[$to_update[$i]][8] = 0;
			//todo mail
			$ok = true;
		}
		unset($to_update[$i]);
	} 
	if(isset($ok)) update_option('ale_links_data', wit_ale_links_parse($links, TRUE));
	$to_update = implode(',',$to_update);
	update_option('ale_update_queue',$to_update); 
}
function wit_ale_show_score($type,$count = 1,$deep = 1,$show = TRUE,$template = '<li><a href="{#winnerUrl}" title="{#winnerTitle}" target="_blank">{#winnerTitle}</a></li>',$promo = TRUE) 
{
	$types = array('likes'  => 1,
				   'visits' => 2);
	$stages = array_slice(explode('[::][::]',get_option('ale_history')),0,$deep);
	$stages = array_map(create_function('$a', ' $x = explode("[::||::]", $a); 
												$x[1]=array_slice(wit_ale_links_parse($x[1]),0,'.$count.'); 
												$x[2]=array_slice(wit_ale_links_parse($x[2]),0,'.$count.'); 
											    return $x;
										'),
										$stages
						);
	$output = '';
	
	if(sizeof($stages))
	{
		$n = sizeof($stages);
		$i = 0;
		foreach($stages as $stage)
		{
			$i++;
			$stage[0]  = explode(':', $stage[0]);
			$dateStart = $stage[0][0];
			$dateEnd   = $stage[0][1];
			$output   .= '<ul class="ale_stage" id="stage_'.$dateStart.'_'.$dateEnd.'">';
			foreach($stage[$types[$type]] as $winner)
			{				 
				//if((!is_long($dateStart) && !is_int($dateStart)) || (!is_long($dateEnd) && !is_int($dateEnd))) continue;
				$data = array(
					'dateStart'    => gmdate('D, d M Y H:i:s',$dateStart), 
					'dateEnd'      => gmdate('D, d M Y H:i:s',$dateEnd),   
					'winnerTitle'  => $winner[0],
					'winnerUrl'    => $winner[1],
					'winnerCat'    => $winner[2],
					'winnerLinke'  => $winner[3],
					'winnerName'   => $winner[4],
					'winnerMail'   => $winner[5],
					'winnerLikes'  => $winner[9],
					'winnerVisits' => $winner[10],
					'winnerID'     => $winner[11]
				); 
				$output .= str_ireplace(array_map(create_function('$a','return "{#".$a."}";'), array_keys($data)), array_values($data), $template);
			}			
			if($i == $n && $promo == TRUE)
			$output .= '<li style="text-align:right;">Stiai ca poti fi tu cel recomandat? <a title="Afla cum poti deveni gratuit recomandarea '.DOMAIN.'" href="'.PAGE.'" target="_blank">Afla cum</a>.</li>';
			$output .= '</ul>';
		}
	} 
	else
	{
		$output .= '<class="ale_stage"><li>Nu exista nici o recomandare.</li></ul>';
	}
	if($show == TRUE) echo $output;
	return $output;
}
function wit_ale_links_parse($links, $reverse = FALSE)
{
	if($reverse == FALSE) {
		$links_parsed = explode('(::||::)',$links);
		$links_parsed = array_map(create_function('$a', 'return explode(":||:",$a);'),$links_parsed);
	} else {
		$links_parsed = array_map(create_function('$a', 'return implode(":||:",$a);'),$links);
		$links_parsed = implode('(::||::)',$links_parsed);	
	}
	return $links_parsed;
}
function wit_ale_validate($url, $fullv = FALSE)
{     
	if($fullv == TRUE) {
		if(strlen($url[0]) < 3) return 'Titlul este prea scurt.';
		if(!preg_match("/^(([\w]+:)?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&amp;?([-+_~.\d\w]|%[a-fA-f\d]{2,2})=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?$/", $url[1])) return 'Adresa website-ului este invalida.';
	 	if(strlen($url[3]) < 10) return 'Descrierea trebuie sa aiba minim 10 caractere.';
		if(strlen($url[5]) < 5) return 'Numele si prenumele trebuie sa aiba in total minim 5 caractere.';
		if(!checkMail($url[6])) return 'Ai introdus o adresa de e-mail invalida.';
		$host = parse_url($url[1], PHP_URL_HOST);
		$host2 = parse_url($url[4], PHP_URL_HOST);
		if($host != $host2) return 'Nu ai completat acelasi host.';
	}
	 
	$content = @ale_curl($url[4]); 
	if ( !preg_match('/a.*?href=["\']'.REGEX_1, $content) && !preg_match('/a.*?href=["\']'.REGEX_2, $content)) {
		return 'Nu am gasit linkul care sa faca referinta la '.DOMAIN;		
	}  
	return TRUE;
}
function wit_ale_clear($data)
{
	if(is_array($data))	{
		$arr = array();
		foreach($data as $key => $val)	
			$arr[wit_ale_clear($key)] = wit_ale_clear($val);
		return $arr;
	}
	else {
	   return htmlspecialchars($data,ENT_QUOTES);
	}
}
function wit_ale_sort_by($arr, $sortby)
{
	$to_sort = array();
	$new_array = array();
	
	foreach($arr as $v)
		$to_sort[] = $v[$sortby];
 
	asort($to_sort); 
	foreach(array_reverse($to_sort, true) as $key => $v)
		$new_array[] = $arr[$key];
		
	return $new_array;
}
function wit_ale_generate_pagination($rPp, $count, $page, $orderby, $uri, $all) 
{  
	  $text = "";
	  $wh = wit_ale_reset_param($uri, 'ale_page');
	  
	  if($all == 0)      { $text .= "<strong>Nu exista intrari.</strong>";       return $text; }
	  if($page > $count) { $text .= "<strong>Pagina nu a fost gasita.</strong>"; return $text; }
	 
	  if($count == 1) {
		  $text .= '<div align="left">Pagina 1 din 1&nbsp;&nbsp;&nbsp;</div>';
	  }
	  else {		      
		  $text .= '<div align="left">';
		  $text .= 'Pagina '.$page.' din '.$count.'&nbsp;&nbsp;&nbsp;';
		  if($page > 1) { 
			  $text .= '<a href="'.str_replace('#',($page-1),$wh).'">&laquo;</a>&nbsp;';
		  }
		  
		  if($count <= 6) {
			  for($pagini = 1; $pagini <= $count; $pagini++) {
				  if(($page) == $pagini) $text .= '<strong>'.$pagini.'</strong>&nbsp;';
				  else  $text .= '<a href="'.str_replace('#',$pagini,$wh).'">'.$pagini.'</a>&nbsp;';
			  }
		  }
		  else {
			  if(($count - $page) < 3) {
				  for($pagini = 1; $pagini < 4; $pagini++) {
					   $text .= '<a href="'.str_replace('#',$pagini,$wh).'">'.$pagini.'</a>&nbsp;';
				  }
				  $text .= "<strong>...</strong>";
				  for($pagini = $count - 2; $pagini <= $count; $pagini++) {
					   if(($page) == $pagini) $text .= '<strong>'.$pagini.'</strong>&nbsp;';
					   else $text .= '<a href="'.str_replace('#',$pagini,$wh).'">'.$pagini.'</a>&nbsp;';
				  }
			  }
			  else if($page < 4) {
				  for($pagini = 1; $pagini < 4; $pagini++) {
					   if(($page) == $pagini) $text .= '<strong>'.$pagini.'</strong>&nbsp;';
					   else  $text .= '<a href="'.str_replace('#',$pagini ,$wh).'">'.$pagini.'</a>&nbsp;';
				  }
				  $text .= "<strong>...</strong>";
				  for($pagini = $count - 2; $pagini <= $count; $pagini++) {	       		 
					   $text .= '<a href="'.str_replace('#',$pagini,$wh).'">'.$pagini.'</a>&nbsp;';
				  }
			  }
			  else {
				  for($pagini = 1; $pagini < 4; $pagini++) {
					   $text .= '<a href="'.str_replace('#',$pagini,$wh).'">'.$pagini.'</a>&nbsp;';
				  }
				  $text .= "<strong>...</strong>";
				  $text .= '<strong>'.$page.'</strong>&nbsp;';
				  $text .= "<strong>...</strong>";
				  for($pagini = $count - 2; $pagini <= $count; $pagini++) {	       		 
					   $text .= '<a href="'.str_replace('#',$pagini,$wh).'">'.$pagini.'</a>&nbsp;';
				  }
			  }
		  }
		  
		  if($page < $count) { 
			  $text .= '<a href=" '.str_replace('#',($page+1),$wh).'">&raquo;</a>&nbsp;';				
		  }
		  $text .= '</div>';
	  }
	  return $text;
}
function wit_ale_reset_param($uri, $param)
{
	if(stripos($uri, $param) !== FALSE) {
		  $whhh = stripos($uri, $param);
		  $len  = strlen($param);
		  $wh = substr($uri, 0, $whhh + $len+1);
		  $cnt = 0;	  
		  while(isset($uri{$whhh+$len+ $cnt}) && $uri{$whhh+$len+$cnt} != '&') {
			  $cnt++;
		  }
		 
		  $wh.='#'.substr($uri, $whhh+$len+$cnt);
	  } else {
		$wh = $uri;
		if(stripos($wh,'?')!== FALSE)
			$wh .= '&'.$param.'=#';
		else
			$wh .= '?'.$param.'=#';
	  }
	 return $wh;
}
function wit_ale_checkpage($post_content, $isAdmin = false)
{ 
	global $wpdb, $post;
	$new_content = '';
	
	
	if(stripos($post_content, '[WIT_ALE_REGISTER]')!== FALSE || $isAdmin == true) { 		
	 	if(isset($_POST['ale_vote']) && get_option('ale_count_lovers') != '')
		{			
			$data = wit_ale_links_parse(get_option('ale_links_data'));
			$ips  = explode(':||:',get_option('ale_url_ips'));
			foreach($data as $key => $v)
				if($v[11]==$_POST['ale_vote_id'])
				{
					foreach($ips as $keyy => $ip) 
						if(stripos($ip, $_SERVER['REMOTE_ADDR']) !== FALSE) 
						{
							$ip = explode(':',$ip);
							$found = TRUE;
							if($ip[1]!=$v[11]) 
							{
								$updateid = $ip[1];
								$ip[1]=$v[11];
								$ip = implode(':',$ip);
								$ips[$keyy] = $ip;
								$data[$key][9]++;
								break;
							}
						}
					if(!isset($found)) 
					{
						$ips[] = $_SERVER['REMOTE_ADDR'].':'.$v[11];
						$data[$key][9]++;
					}
						
					if(isset($updateid))
						foreach($data as $key => $v)
							if($v[11] == $updateid)
								$data[$key][9]--;
					$ips = implode(':||:', $ips);
					$data = wit_ale_links_parse($data,TRUE);
					 
					update_option('ale_url_ips',$ips);
					update_option('ale_links_data',$data);
					break;
				}
		} 
		
		$ipsv  = get_option('ale_url_ips');
		$links = get_option('ale_links_data');	
		if(isset($_POST['ale_submit'])) 
		{		 	
			$_POST = wit_ale_clear($_POST);
			
			unset($_POST['ale_submit']);
			 
			if(sizeof($_POST) == 8)	
			{ 	 								
				$data = implode(':||:',$_POST);
				$data.=':||:1:||:0:||:0:||:'.md5(rand(10000, 999999));
				$llll = parse_url($_POST['ale_url']);
				$error = wit_ale_validate(array_values($_POST), TRUE);
				if($error === TRUE && stripos($links,$llll['host']) === FALSE) 
				{
					if($links == '')
						$links = $data;
					else
						$links.='(::||::)'.$data;
						
					update_option('ale_links_data', $links);
				} 
				else 
				{
					$new_content .= '<p><strong>Eroare :</strong> '.$error.' </p>';	
				}
			}
			else
			{
				$new_content .= '<p>Nu ai completat toate campurile.</p>';
			}
		}
		
		$links_parsed = wit_ale_links_parse($links);
		 
		foreach($links_parsed as $key => $urld)		
			if(!$urld[8] && !$isAdmin) unset($links_parsed[$key]);		
		$links_parsed = array_values($links_parsed);
		$all          = sizeof($links_parsed);
		$colors       = array('#fafafa','#f0f0f0');		
		$rPp          = get_option('ale_links');
		$count        = ceil($all / $rPp);
		$page         = isset($_GET['ale_page']) && is_numeric($_GET['ale_page']) && $_GET['page'] <= $count ? $_GET['ale_page'] : 1;
		
		$orderby 	  = 9; //vizite+todo	
		
		$links_parsed = array_slice(wit_ale_sort_by($links_parsed, $orderby),($page * $rPp - $rPp)); 
		if(sizeof($links_parsed) > $rPp) unset($links_parsed[$rPp]);
 		$uri          = $_SERVER['REQUEST_URI'];
		 
		$new_content .= (!$isAdmin ? '<p><a href="#add_link_how_to"><strong>Cum adaug link-ul meu gratuit in aceasta pagina?</strong></a></p>' : '');
		$new_content .= '
		<p id="links_list">
			<div id="ale_menu">'.wit_ale_generate_pagination($rPp, $count, $page, $orderby, $uri, $all).'</div>
			<table class="wit_ale_table" cellpadding="1" cellspacing="1" width="100%">
				<tr id="wit_ale_header"> 
					<th width="5%" valign="middle" align="center">#</th>
					<th width="25%"valign="middle" align="center">Website</th>
					<th width="70%"valign="middle" align="center">Despre</th>  
				</tr>';
				$i = $page * $rPp - $rPp + 1;
				for($k = 0; ($i <= $page * $rPp) && ($k < sizeof($links_parsed)); $k++)				 
				{
					$urld = $links_parsed[$k];
					 
					if(!$urld[8]) {   if(!$isAdmin) continue; }
					
					$new_content .= '<tr id="ale_table_tr_'.$i.'" bgcolor="'.$colors[$i%sizeof($colors)].'">
						<td align="center"><strong style="'.($isAdmin && !$urld[8] ? 'border:1px solid red;' : '').'">'.$i.'</strong></td>
						<td align="center"><a title="'.$urld[0].'" target="_blank" href="'.$urld[1].'"><strong>'.$urld[0].'</strong></a></td>
						<td> 
							'.$urld[3].' 
							<div id="ale_about">Adaugat in <strong>'.$urld[2].'</strong> de <strong>'.$urld[5].'</strong>. Este placut de <strong>'.$urld[9].'</strong> persoane si a adus <strong>'.$urld[10].'</strong> vizite.</div>';
					if($isAdmin == true)
					{
						$new_content .= '
						<div style="border-bottom:1px solid #000000;">
						<strong>Contact : </strong><em>'.$urld[6].'</em> <br />
						<a href="'.$urld[4].'" target="_blank">Refferer</a> | 
						<a href="'.$_SERVER['PHP_SELF'].'?page=wit-automatic-link-exchange/wit-automatic-link-exchange.php&ale_page=ips&ale_id='.$urld[11].'">Check IPs</a> | 
						<a href="'.$_SERVER['PHP_SELF'].'?page=wit-automatic-link-exchange/wit-automatic-link-exchange.php&ale_page=state&ale_id='.$urld[11].'">'.($urld[8] ? 'Hide' : 'Show').'</a> | 
						<a href="'.$_SERVER['PHP_SELF'].'?page=wit-automatic-link-exchange/wit-automatic-link-exchange.php&ale_page=delete&ale_id='.$urld[11].'">Delete</a>
						</div>
						';
					}
					else
					{
						if(get_option('ale_count_lovers') != '')
						{
							if(stripos($ipsv, $_SERVER['REMOTE_ADDR'].':'.$urld[11])!== FALSE)
								$new_content .= '<div id="ale_recomandation"><em>Recomandarea mea</em></div>';
							else
							   $new_content .= '<form method="post" name="ale_recomandation"><input type="hidden" value="'.$urld[11].'" name="ale_vote_id" /><input id="ale_vote" type="submit" name="ale_vote" value="Recomand" /></form>';
						}
					}
				$new_content .= '							
							</td> 
						  </tr>';
				  if(TRUE) $i++;
				}
			  $ale_event = explode(':', get_option('ale_event'));
			  $ale_event[0] = gmdate('D, d M Y H:i:s', $ale_event[0]);
			  $ale_event[1] = gmdate('D, d M Y H:i:s', $ale_event[1]);	 
			 $new_content .= '
			</table>
			<div id="ale_menu">'.wit_ale_generate_pagination($rPp, $count, $page, $orderby, $uri, $all).'</div>
		</p>';
		if($isAdmin) {
			return $new_content;	
		}
		
		$new_content .= '<h3>Etapa curenta</h3>
		<p><strong>Data inceput</strong> : <em>'.$ale_event[0].'</em><br />
		   <strong>Data resetare</strong> : <em>'.$ale_event[1].'</em><br />
		</p>
		<h3>Recomandarile ultimei etape</h3>
		'.wit_ale_show_score('likes',3,1, FALSE, '<li class="bullet"><a href="{#winnerUrl}" title="{#winnerTitle}" target="_blank">{#winnerTitle}</a></li>', FALSE).'
		<h3 id="add_link_how_to">Cum adaug link-ul meu in aceasta pagina?</h3>
		<p>Simplu! Copiaza intr-o pagina de pe site-ul tau unul din link-urile existente in pagina de aici dupa care completati formularul de mai jos specificand adresa site-ului si adresa paginii unde se gaseste link-ul (pentru verificare). Daca totul e ok, link-ul tau va apare imediat in aceasta pagina, daca nu, va apare un mesaj care iti va spune ce ai gresit.
Succes la promovare si la cat mai multi vizitatori. </p>
		<h3>Avantaje</h3>
		<p>
			&bull; Poti ajunge pe prima pagina daca vei avea cele mai multe voturi!<br />
			&bull; Poti primi premii surpriza!<br />
			&bull; Ai o sansa in plus de a ajunge partener permanent. Partenerii permanenti sunt pe prima pagina tot timpul! <br />
			&bull; Iti poti face o idee despre cum esti vazut in online-ul romanesc, analizand numarul de voturi! <br />
			&bull; Voturile sunt resetate o data la doua saptamani pentru a oferi o sansa si pentru noii veniti! <br />
		</p>
		<h3>Inregistreaza-te acum!</h3>
		<form action="" method="post" id="wit_ale_form">
  
        <p><input type="text" name="ale_title" id="ale_title" class="wit_ale" value="" size="22" tabindex="1" />
        <label for="ale_title"><small>Titlu</small></label></p>
        
        <p><input type="text" name="ale_url" id="ale_url" class="wit_ale" value="http://" size="22" tabindex="2" />
        <label for="ale_url"><small>Adresa site</small></label></p>
        
        <p><input type="text" name="ale_category" id="ale_category" class="wit_ale" value="" size="22" tabindex="3" />
        <label for="ale_category"><small>Categorie</small></label></p>
                
         <p><input type="text" name="ale_description" id="ale_description" class="wit_ale" value="" size="22" tabindex="3" />
        <label for="ale_description"><small>Descriere</small></label></p>
        
        <p><input type="text" name="ale_linke" id="ale_linke" class="wit_ale" value="http://" size="22" tabindex="3" />
        <label for="ale_linke"><small>Adresa paginii unde exista link reciproc</small></label></p>
        
        <p><input type="text" name="ale_name" id="ale_name" class="wit_ale" value="" size="22" tabindex="3" />
        <label for="ale_name"><small>Numele dumneavoastra</small></label></p>
        
        <p><input type="text" name="ale_mail" id="ale_mail" class="wit_ale" value="" size="22" tabindex="3" />
        <label for="ale_mail"><small>E-mail</small></label></p>
        
        <input name="ale_is_ok" id="ale_is_ok" class="wit_ale" value="1" type="checkbox">
      	<label for="ale_is_ok">Confirm ca site-ul adaugat nu promoveaza materiale ilegale, warez, cu drept de autor sau cu continut adult, nu instiga la violenta sau la discriminari.</label> 
       
        <p><input name="ale_submit" type="submit" id="ale_submit" tabindex="5" value="Trimite" />
        
        </p>
        
</form>

<h3>Precizari</h3>
<p>
&bull; Ne rezervam dreptul de a nu accepta orice site si de a face verificari periodice pentru a verifica indeplinirea conditiilor specificate mai sus.<br />
&bull; Adresele a caror conditii de inregistrare au fost incalcate se vor scoate fara o notificare prealabila. <br />
&bull; Verificarea linkului reciproc se face automat iar adresa site-ului apare automat in urmatoarele 24 ore, si se sterge automat in 24 ore daca la o noua verificare nu se gaseste link-ul reciproc. <br />
&bull; Pentru a fi siguri ca robotul nostru detecteaza corect linkul in pagina dumneavoastra folositi codurile html prezentate in pagina de publicitate. Nu adaugati site-ul dvs. daca nu ati pus link reciproc intai pe pagina dvs. <br />
</p>

<div align="right"><em><small>Pagina generata de <a href="http://www.worldit.info">WIT Automatic Link Exchange v'.wit_ale_version.'</a>.</small></em></div>
';
		
		$post_content = str_replace('[WIT_ALE_REGISTER]',$new_content,$post_content);
	}
	return $post_content;
}
function wit_ale_settings()
{
	global $wpdb;

	if(isset($_POST['submit']))	{
										  	 //update_option('ale_activated'      ,$_POST['ale_activated']);
		if(is_numeric($_POST['ale_links']))  update_option('ale_interval'       ,$_POST['ale_interval']);
		if(is_numeric($_POST['ale_links']))  update_option('ale_links'          ,$_POST['ale_links']);
											 update_option('ale_count_refferers',$_POST['ale_count_refferers']);
										 	 update_option('ale_count_lovers'   ,$_POST['ale_count_lovers']);
				
		if($_POST['ale_activated'] == 'restart')
			wit_ale_reset_links();
		?>
		<script type="text/javascript">
		window.location = "<?=$_SERVER['PHP_SELF'].'?page=wit-automatic-link-exchange/wit-automatic-link-exchange.php&update=true'?>";
		</script>
        <?php
	} else if(isset($_GET['ale_page'],$_GET['ale_id'])) {
		$links = wit_ale_links_parse(get_option('ale_links_data'));
		$id = 0; $n = sizeof($links);
		
		while($links[$id][11]!=$_GET['ale_id'] && $id < $n) $id++;
		if($id >= $n) echo '<div class="updated"><p><strong>'.__('Invalid ID').'</strong></p></div>';
		else 
		{
			switch($_GET['ale_page'])
			{
				case 'ips':
					 echo '<div class="updated"><p> <strong> IPS </strong><br />';
					 $ips = array_map(create_function('$a','return explode(":",$a);'),explode(':||:',get_option('ale_url_ips')));
					 foreach($ips as $ip)
					 	if($ip[1] == $links[$id][11])
						{
							$country = @ale_curl('http://whatismyipaddress.com/ip/'.$ip[0]);
							preg_match('/http:\/\/whatismyipaddress\.com\/images\/flags\/(.*?)"/', $country, $ccc);
							echo $ip[0].' <img src="http://whatismyipaddress.com/images/flags/'.$ccc[1].'" /> <br />';
						}
					 echo '</p></div>';
				break;
				case 'state':
					$links[$id][8]=!$links[$id][8];
					echo '<div class="updated"><p><strong>'.__('Successfully state changed.').'</strong></p></div>';
					update_option('ale_links_data',wit_ale_links_parse($links, TRUE));
				break;
				case 'delete':
					unset($links[$id]);
					echo '<div class="updated"><p><strong>'.__('Successfully removed.').'</strong></p></div>';
					update_option('ale_links_data',wit_ale_links_parse($links, TRUE));
				break;
			}
		}
	}
	if($_GET['update']) echo '<div class="updated"><p><strong>'.__('Settings saved').'</strong></p></div>';
	
	$ale_activated       = get_option('ale_activated');
	$ale_interval        = get_option('ale_interval');
	$ale_links           = get_option('ale_links');
	$ale_count_refferers = get_option('ale_count_refferers');
	$ale_count_lovers    = get_option('ale_count_lovers'); 
	$ale_event           = get_option('ale_event');
	?>
    	<div class="wrap">

		<div id="icon-options-general" class="icon32"><br/></div>

		<h2>WIT Automatic Link Exchange</h2>
        <form method="post" action="<?=$_SERVER['PHP_SELF'].'?page=wit-automatic-link-exchange/wit-automatic-link-exchange.php'?>">

		<table class="form-table">
			<tbody>            	
            	 <tr>
					<th><label for="ale_activated">Registering status : </label></th>
					<td>
                    	<select id="ale_activated" name="ale_activated">
                        	<option value="started"<?=$ale_activated=='started'?' selected="selected"':''?>>Running</option>
                            <option value="restart"<?=$ale_activated!='started'?' selected="selected"':''?>>Restart</option>
                        </select> 
						<span class="setting-description">If link exchange registering are stopped then we can not register in link exchange directory.</span>
					</td>
				</tr> 
                <tr>
                	<th>Stage started/will be restarted</th>
                    <td><?php  
							 
							$ale_event = explode(':', $ale_event);
							$ale_event[0] = gmdate('D, d M Y H:i:s', $ale_event[0]);
							$ale_event[1] = gmdate('D, d M Y H:i:s', $ale_event[1]);	 
							echo '<strong>'.$ale_event[0].'</strong> - <strong>'.$ale_event[1].'</strong>';
						?>
                    </td>
                </tr>
                <tr>
					<th><label for="ale_interval">Reset interval : </label></th>
					<td>
						<input id="ale_interval" type="text" name="ale_interval" style="width:50px;" value="<?=$ale_interval?>"  />
						<span class="setting-description">This value represents the interval (in days) between stats resets. This should be 14.</span>
					</td>
				</tr>
                 <tr>
					<th><label for="ale_links">Links per page : </label></th>
					<td>
						<input id="ale_links" type="text" name="ale_links" style="width:50px;" value="<?=$ale_links?>"  />
						<span class="setting-description">Number of links per page.</span>
					</td>
				</tr>        
                <tr>
					<th><label for="ale_count_refferers">Count refferers visits: </label></th>
					<td>
						<input id="ale_count_refferers" type="checkbox" name="ale_count_refferers" value="yes" <?=$ale_count_refferers!=''?'checked="checked"':''?> /> 
						<span class="setting-description">Count visits received from our Link Exchange parteners. </span>
					</td>
				</tr>   
                <tr>
					<th><label for="ale_count_lovers">Count like votes : </label></th>
					<td>
						<input id="ale_count_lovers" type="checkbox" name="ale_count_lovers" value="yes" <?=$ale_count_lovers!=''?'checked="checked"':''; ?> /> 
						<span class="setting-description">Count number of votes (uniques like votes).</span>
					</td>
				</tr>  
            </tbody>
            
        </table>
        <p class="submit"><input type="submit" name="submit" value="Save Changes" class="button-primary" /></p>
        
        </form>
        
        <h2>Parteners</h2>
        <?php echo wit_ale_checkpage('', TRUE); ?>
    </div>
    
    <?php
}

function ale_curl($url)
{
	$rand = rand(100000,400000);	
	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	$result = curl_exec ($ch);
	return $result;
	curl_close ($ch);
}
?>