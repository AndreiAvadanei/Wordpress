<?php
/*

Plugin Name: WIT-RSS-Widget v0.5
Plugin URI: http://www.worldit.info
Description: You can add multiple RSS boxes with different settings.
Version: 0.5.0
Author: Avadanei Andrei
Author URI: http://www.worldit.info/

*/


register_activation_hook(__FILE__, 'wit_activate' );
register_deactivation_hook(__FILE__, 'wit_deactivation');

add_action('admin_menu', 'wit_add_on_menu' );
add_action('wit_rss_update_feeds', 'updateFeeds');
add_action('plugins_loaded','wit_register_widgets');

$wit_feed_mashup = '<li style="padding-bottom: 15px;">
    <img id="rss" src="%WIT_RSS_IMAGE%"> 
   <a target="_blank" href="%WIT_RSS_URL%" title="Citeste %WIT_RSS_TITLE% pe %WIT_RSS_NAME%">%WIT_RSS_TITLE% </a>
</li>';

function wit_widget_box($args,$hash) 
{
  extract($args);
  $feed =  wit_get_feed_option($hash);
  echo $before_widget;	
  echo $before_title . str_replace(array('%WIT_RSS_NAME%','%WIT_RSS_LINK%'),array( $feed['name'], $feed['link']), stripslashes($feed['title'])) . $after_title;
  wit_rss_feed_outputContent(TRUE,$hash);
  echo $after_widget;
}

function wit_register_widgets()
{
	$feeds = explode(';',trim(get_option('wit_rss_feeds')));
	$feed  = array();
	for($i = 0; $i < sizeof($feeds); $i++)
	{
		if(trim($feeds[$i])=='') continue;
	
		$feedc  = wit_get_feed_option($feeds[$i]);
		$feed   = $feedc['name'].' - RSS Widget'; 
		register_sidebar_widget($feed,create_function('$args','return wit_widget_box($args,"'.$feedc['hash'].'");')); 
	}
	
}

function wit_widget_options()
{
	
}

function wit_rss_feed_outputContent($show = TRUE,$hash = '')
{
	$output = stripslashes(get_option('wit_rss_data_'.$hash));
	if($show == TRUE) echo $output;
	else return $output;
}

function updateFeeds()
{
	$feeds = explode(';',trim(get_option('wit_rss_feeds')));
	for($i = 0; $i < sizeof($feeds); $i++)
	{
		if(trim($feeds[$i])=='') continue;
		
		updateFeed($feeds[$i]);
	}
}

function updateFeed($hash)
{
	global $wpdb;
	
	$feedopt    = wit_get_feed_option($hash);
	
	$feeduri    = $feedopt['url'];
    $feedcount  = $feedopt['count'];
	$feedimgd   = $feedopt['default'];
 	
    $feedmashup = stripslashes($feedopt['mashup']);
	$output     = '';
	
	$content = get_content($feeduri);
	if(strrpos($content,'<channel>') !== FALSE || strrpos($content, '<?xml-stylesheet type="text/xsl" media="screen" href="/~d/styles/atom10full.xsl"?>') !== FALSE)
	{
		 preg_match_all("/<link>(.*?)<\/link>/",$item,$link);
		 preg_match_all("/<item>(.*?)<\/item>/s",$content,$items);
		 if(!sizeof($items[1])) 
    		 {
			preg_match_all("/<entry>(.*?)<\/entry>/s", $content, $items);
		 	$atom10 = true;
		 }
		
		 for($i = 0; $i < $feedcount; $i++)
		 {
			 if(isset($items[1][$i]) == FALSE) break;
			 $item = $items[1][$i];
			 
			 if(isset($atom10))
			 {
				 preg_match("/<title type=\"html\">(.*?)<\/title>/",$item,$title);
				 preg_match("/<link href=\"(.*?)\" \/>/",$item,$url);
				 $commentsURL = '';
				 preg_match("/<updated>(.*?)<\/updated>/",$item,$date);
				 preg_match("/<author><name>(.*?)<\/name><\/author>/",$item,$author);
				 $excerpt = '';
				 $content = '';
				 $commentsCount = 0;
			 }
			 else
			 {		
				 preg_match("/<title>(.*?)<\/title>/",$item,$title);
				 preg_match("/<link>(.*?)<\/link>/",$item,$url);
				 preg_match("/<comments>(.*?)<\/comments>/",$item,$commentsURL);
				 preg_match("/<pubDate>(.*?)<\/pubDate>/",$item,$date);
				 preg_match("/<dc:creator>(.*?)<\/dc:creator>/",$item,$author);
				 preg_match("/<description>(.*?)<\/description>/s",$item,$excerpt);
				 preg_match("/<content:encoded>(.*?)<\/content:encoded>/s",$item,$content);
				 preg_match("/<slash:comments>(.*?)<\/slash:comments>/",$item,$commentsCount);
			 }	
		 
			 if(stripos($item,'<img') !== FALSE)
			 { 
				$image = substr($item,stripos($item,'<img'));
				$image = substr($image,0,stripos($image,'>'));
				$image = $image;
				preg_match('/src="(.*?)"/',$image,$image);
				if(!sizeof($image)) preg_match('/src=\'(.*?)\'/',$image,$image);
			 }
			 else
			 {
				 $image = '';
			 }
			
			 $excerpt = str_replace(array("<![CDATA[","]]>"),"",$excerpt);
	   	     $content = str_replace(array("<![CDATA[","]]>"),"",$content);
			 
			 $output .= str_replace(array('%WIT_RSS_EXCERPT%',
										  '%WIT_RSS_TEXT%',
										  '%WIT_RSS_IMAGE%',
										  '%WIT_RSS_URL%',
										  '%WIT_RSS_TITLE%',
										  '%WIT_RSS_AUTHOR%',
										  '%WIT_RSS_DATE%',
										  '%WIT_RSS_COMMENTS%',
										  '%WIT_RSS_COMMENTS_URL%',
										  '%WIT_RSS_URL%'
										  ),
									array($excerpt[1],
										  $content[1],
										  $image[1] == '' ? $feedimgd : $image[1],
										  $url[1],
										  str_replace(array("<![CDATA[","]]>"),"",$title[1]),
										  str_replace(array("<![CDATA[","]]>"),"",$author[1]),
										  gmdate('d M Y',strtotime($date[1])),
										  ($commentsCount[1] == '0' ? 'Fara comentarii' : ($commentsCount[1] == 1 ? '1 comentariu' : $commentsCount[1].' comentarii')),
										  $commentsURL[1],
										  $link[1][0]
										  ),
									$feedmashup
									);
		 }
	}
	else
	{
		$output = 'Could not get updates.';
	}
	update_option('wit_rss_data_'.$hash,trim($output));
}


function wit_add_on_menu()
{
	add_options_page('WIT RSS Box Settings', 'WIT RSS Box v0.5', 10, __FILE__, 'wit_settings_main');
}



function wit_deactivation() 
{
	wp_clear_scheduled_hook('wit_rss_update_feeds');
}


function wit_activate()
{
	add_option('wit_rss_feeds','');
	wp_schedule_event(time(), 'hourly', 'wit_rss_update_feeds');
}

function wit_get_feed_option($wit_id)
{
	$info = explode('#'.md5(chr(255).chr(254).chr(255)).'#',get_option('wit_rss_info_'.$wit_id));
	if(sizeof($info)==0) return FALSE;
	 
	return array('name'    => $info[0],
				 'title'   => $info[6],
				 'link'    => $info[7],
 				 'url'     => $info[1],
				 'count'   => $info[2],
				 'hash'    => $info[3],
				 'code'    => "&lt;?php if(function_exists('wit_rss_feed_outputContent')) wit_rss_feed_outputContent(TRUE,'".$info[3]."');  ?>",
				 'mashup'  => $info[4],
				 'default' => $info[5]
				 );
}

function wit_settings_main()
{
	global $wpdb;
	
	?>
    <div class="wrap">
		<div id="icon-options-general" class="icon32"><br/></div>
		<h2>WIT RSS Box Settings</h2>  
      
    <?php
		
	$_GET['wit_action'] = isset($_GET['wit_action']) ? $_GET['wit_action'] : 'home';
	switch($_GET['wit_action'])
	{
		case 'home':
		 
		?>
           <p class="submit"><input type="button" onclick="window.location='<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=new';?>';" name="addnew" value="Add new widget" class="button-primary" /></p> 
        <?php
			$feeds = explode(';',trim(get_option('wit_rss_feeds')));
			$count = sizeof($feeds);
			 
			?>
            <table class="form-table">
				<tbody>    
                <tr>
                	<th><strong>Manage Widget</strong></th>
                    <th><strong>Widget Code</strong></th>
                    <th><strong>Delete</strong></th>
                </tr>       
            <?php
			for($i = 0; $i < $count; $i++)
			{
				if(trim($feeds[$i])=='') continue;
				$feedopt = wit_get_feed_option($feeds[$i]);
				
				if($feedopt == FALSE) continue;
				?>
                  <tr>
                    <td><p class="submit"><input type="button" onclick="window.location = '<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=single&hash='.$feeds[$i]?>';" name="current_<?php echo $feeds[$i]; ?>" value="Edit <?php echo $feedopt['name']; ?> feed" class="button-primary" /></p></td>
                    <td><em><?php echo $feedopt['code']; ?></em></td>
                    <td><a class="button rbutton" onclick="if(confirm('Are you sure you want to remove <?=$feedopt['name']?> feed?')) window.location='<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=remove&hash='.$feeds[$i]?>';">Remove <?=$feedopt['name']?></a></td>
                  </tr>
                <?php
			}
			?>
            	</tbody>
            </table>
            <?php
		break;
		case 'remove':
			$rsses = explode(';',get_option('wit_rss_feeds'));
			 
			if(in_array($_GET['hash'],$rsses)) 
			{
				unset($rsses[array_search($_GET['hash'],$rsses)]);
				update_option('wit_rss_feeds',implode(';',$rsses));
				delete_option('wit_rss_info_'.$_GET['hash']);
				delete_option('wit_rss_data_'.$_GET['hash']);
				echo '<div class="updated"><p><strong>'.__('Feed Removed. <a class="button rbutton" href="?page='.__FILE__.'">Go back</a>').'</strong></p></div>';
			}
		break;		
		case 'single':
			$rsses = explode(';',get_option('wit_rss_feeds'));
			 
			if(in_array($_GET['hash'],$rsses)) 
			{					
				if(isset($_POST['submit']))
				{					
					update_option('wit_rss_info_'.$_GET['hash'],implode('#'.md5(chr(255).chr(254).chr(255)).'#',array($_POST['feedname'],
																										$_POST['feeduri'],
																										$_POST['feedcount'],
																										$_GET['hash'],
																										trim($_POST['feedmashup']),
																										trim($_POST['feedimgdefault']),
																										trim($_POST['feedtitle']),
																										trim($_POST['feedlink'])
																										)
															)
							   );
					updateFeed($_GET['hash']);
					wp_clear_scheduled_hook('wit_rss_update_data');
					wp_schedule_event(time()+60*60, 'hourly', 'wit_rss_update_data');
					?>
                    
					<script type="text/javascript">
						window.location = "<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=single&hash='.$_GET['hash'].'&update=true'?>";
					</script>
					<?php				
					
				}
				if($_GET['update']) echo '<div class="updated"><p><strong>'.__('Settings saved').'</strong></p></div>';
				 
				$feedopt = wit_get_feed_option($_GET['hash']);				
			?>            
            <form method="post" action="<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=single&hash='.$_GET['hash']?>">
					<table class="form-table">
						<tbody>          
                        	<tr>
                            	<th><label for="feedname">Enter your Feed name : </label></th>
								<td>
									<input id="feedname" type="text" name="feedname" value="<?=$feedopt['name']?>"  />
									<span class="setting-description">The name of the new feed </span>
								</td>
                            </tr>
                            <tr>
                            	<th><label for="feedlink">Enter URL Partener : </label></th>
								<td>
									<input id="feedlink" type="text" name="feedlink" value="<?=$feedopt['link']?>"  />
									<span class="setting-description">The link (with http://) of the new partener </span>
								</td>
                            </tr>
                              <tr>
                            	<th><label for="feedtitle">Enter Feed Title(Description) : </label></th>
								<td>
									<input id="feedtitle" type="text" name="feedtitle" value="<?=htmlspecialchars(stripslashes($feedopt['title']))?>"  />
									<span class="setting-description">You can use : %WIT_RSS_NAME% for RSS Name or %WIT_RSS_LINK% for website link.</span>
								</td>
                            </tr>
							<tr>
								<th><label for="feeduri">Enter your feed URL : </label></th>
								<td>
									<input id="feeduri" type="text" name="feeduri" value="<?=$feedopt['url']?>"  />
									<span class="setting-description">Your RSS adress : it can be RSS or Feedburner, it doesn`t matter. </span>
								</td>
							</tr>    
							<tr>
								<th><label for="feedcount">Items Count : </label></th>
								<td>
									<input id="feedcount" type="text" name="feedcount" value="<?=$feedopt['count']?>"  />
									<span class="setting-description">Number of items that will be showed by RSS Box. </span>
								</td>
							</tr>   
							<tr>
								<th><label for="feedimgdefault">Default image : </label></th>
								<td>
									<input id="feedimgdefault" type="text" name="feedimgdefault" value="<?=$feedopt['default']?>"  />
									<span class="setting-description">The Default Image if an item doesn`t have any image.</span>
								</td>
							</tr>   
							<tr>
								<th><label for="feedmashup">Item HTML : </label></th>
								<td>
									<textarea id="feedmashup" cols="120" rows="10" name="feedmashup"><?=htmlspecialchars(stripslashes($feedopt['mashup']))?></textarea><br />
									<span class="setting-description">You can use different variables for personalize output of the item.<br />
									%WIT_RSS_EXCERPT% - the excerpt of the item<br />
									%WIT_RSS_TEXT% - the content of the item<br />
									%WIT_RSS_IMAGE% - the URL of the first image finded in %WIT_RSS_TEXT%<br />
									%WIT_RSS_URL% - the URL of the item<br />
									%WIT_RSS_TITLE% - the item Title<br />
									%WIT_RSS_AUTHOR% - the author of the item<br />
									%WIT_RSS_DATE% - item date <br />
									%WIT_RSS_COMMENTS% - number of item comments<br />
									%WIT_RSS_COMMENTS_URL% - url to the item comments<br />
                                    %WIT_RSS_LINK% - website partener url<br />
                                    %WIT_RSS_NAME% - rss name<br />
									%WIT_RSS_URL% - url of the RSSed site</span>									
								</td>
							</tr>    
							<tr>
								<th><label for="feedpreview">Preview HTML : </label></th>
								<td>
							<textarea id="feedpreview" cols="120" rows="10" name="feedpreview"><?=htmlspecialchars(stripslashes(get_option('wit_rss_data_'.$_GET['hash'])))?></textarea><br />	
                            	</td>
							</tr>    		  
						</tbody>
						
					</table>
					<p class="submit"><input type="submit" name="submit" value="Save changes" class="button-primary" />                    
                    <input type="button" name="goback" onclick="window.location = '<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.''?>';" value="Go back" class="button-primary" /></p>
                     <a class="button rbutton" onclick="if(confirm('Are you sure you want to remove <?=$feedopt['name']?> feed?')) window.location='<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=remove&hash='.$feedopt['hash']?>';">Remove <?=$feedopt['name']?></a>
					
					</form>			
            <?php
			}
		break;
		case 'new':
			if(isset($_POST['submit']))
			{
				if(is_numeric($_POST['feedcount']) && $_POST['feeduri']!= '' && $_POST['feedmashup']!= '') 
				{
					$hash = md5($_POST['feeduri'].rand(1000,9999));
					add_option('wit_rss_info_'.$hash,implode('#'.md5(chr(255).chr(254).chr(255)).'#',array($_POST['feedname'],
																						$_POST['feeduri'],
																						$_POST['feedcount'],
																						$hash,
																						trim($_POST['feedmashup']),
																						trim($_POST['feedimgdefault']),
																						trim($_POST['feedtitle']),
																						trim($_POST['feedlink'])
																						)
															)
							   );
					add_option('wit_rss_data_'.$hash,'');
					$rsses = get_option('wit_rss_feeds');
					update_option('wit_rss_feeds',($rsses == '' ? $hash : $rsses.';'.$hash));
					updateFeed($hash);
					wp_clear_scheduled_hook('wit_rss_update_data');
					wp_schedule_event(time()+(60*60), 'hourly', 'wit_rss_update_data');
					?>
					<script type="text/javascript">
            
                    window.location = "<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=new&update=true'?>";
            
                    </script>
                    <?php                  
                    
				}				
			}
			
			if($_GET['update'])
			{
				echo '<div class="updated"><p><strong>'.__('Settings saved. <a class="button rbutton" href="?page='.__FILE__.'">Go back</a>').'</strong></p></div>';
			}
			else
			{
				global $wit_feed_mashup;
				?>
					<form method="post" action="<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.'&wit_action=new'?>">
					<table class="form-table">
						<tbody>          
                        	<tr>
                            	<th><label for="feedname">Enter your Feed name : </label></th>
								<td>
									<input id="feedname" type="text" name="feedname" value=""  />
									<span class="setting-description">The name of the new feed </span>
								</td>
                            </tr>  	
                             <tr>
                            	<th><label for="feedlink">Enter URL Partener : </label></th>
								<td>
									<input id="feedlink" type="text" name="feedlink" value="http://"  />
									<span class="setting-description">The link (with http://) of the new partener </span>
								</td>
                            </tr>
                              <tr>
                            	<th><label for="feedtitle">Enter Feed Title(Description) : </label></th>
								<td>
									<input id="feedtitle" type="text" name="feedtitle" value="Nou pe <a href='%WIT_RSS_LINK%'>%WIT_RSS_NAME%</a>"  />
									<span class="setting-description">You can use : %WIT_RSS_NAME% for RSS Name or %WIT_RSS_LINK% for website link.</span>
								</td>
                            </tr>						
							<tr>
								<th><label for="feeduri">Enter your feed URL : </label></th>
								<td>
									<input id="feeduri" type="text" name="feeduri" value=""  />
									<span class="setting-description">Your RSS adress : it can be RSS or Feedburner, it doesn`t matter. </span>
								</td>
							</tr>    
							<tr>
								<th><label for="feedcount">Items Count : </label></th>
								<td>
									<input id="feedcount" type="text" name="feedcount" value=""  />
									<span class="setting-description">Number of items that will be showed by RSS Box. </span>
								</td>
							</tr>   
							<tr>
								<th><label for="feedimgdefault">Default image : </label></th>
								<td>
									<input id="feedimgdefault" type="text" name="feedimgdefault" value="http://"  />
									<span class="setting-description">The Default Image if an item doesn`t have any image.</span>
								</td>
							</tr>   
							<tr>
								<th><label for="feedmashup">Item HTML : </label></th>
								<td>
									<textarea id="feedmashup" cols="120" rows="10" name="feedmashup"><?php echo $wit_feed_mashup; ?></textarea><br />
									<span class="setting-description">You can use different variables for personalize output of the item.<br />
									%WIT_RSS_EXCERPT% - the excerpt of the item<br />
									%WIT_RSS_TEXT% - the content of the item<br />
									%WIT_RSS_IMAGE% - the URL of the first image finded in %WIT_RSS_TEXT%<br />
									%WIT_RSS_URL% - the URL of the item<br />
									%WIT_RSS_TITLE% - the item Title<br />
									%WIT_RSS_AUTHOR% - the author of the item<br />
									%WIT_RSS_DATE% - item date <br />
									%WIT_RSS_COMMENTS% - number of item comments<br />
									%WIT_RSS_COMMENTS_URL% - url to the item comments<br />
                                    %WIT_RSS_LINK% - website partener url<br />
                                    %WIT_RSS_NAME% - rss name<br />
									%WIT_RSS_URL% - url of the RSSed site</span>					
                                    				
								</td>
							</tr>    
									  
						</tbody>
						
					</table>
					<p class="submit"><input type="submit" name="submit" value="Add new" class="button-primary" /> 
                    <input type="button" name="goback" onclick="window.location = '<?=$_SERVER['PHP_SELF'].'?page='.__FILE__.''?>';" value="Go back" class="button-primary" /></p>
					
					</form>			
			<?php
			}
		break;
	}	
     ?>	         
    </div>
    
    <?php
}


function get_content($page)
{
	if(function_exists('curl_init')) //daca avem curl activat
	{
		$obj = curl_init();
		curl_setopt($obj, CURLOPT_URL, htmlspecialchars($page)); //pagina la care dorim sa ne conectam
		curl_setopt($obj,CURLOPT_USERAGENT,htmlspecialchars($_SERVER['HTTP_USER_AGENT'])); //browserul cu care ne vom conecta
                curl_setopt($obj, CURLOPT_RETURNTRANSFER, 1); //setam variabila ce va spune ca dorim response-ul paginii
		$response = curl_exec($obj); //primim raspunsul
		curl_close($obj);		//inchidem obiectul initializat
	}
	else if(function_exists('file_get_contents') && ini_get('allow_url_fopen') == 1) //daca exista file_get_contents
	{
		$response = file_get_contents(htmlspecialchars($page));
	}
	return ($response != "" ? $response : FALSE);
}
?>
