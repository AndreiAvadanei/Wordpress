<?php
/*

Plugin Name: WIT-RSS-Widget
Plugin URI: http://www.worldit.info
Description: Lorem ipsum dolor.
Version: 0.1
Author: Avadanei Andrei
Author URI: http://www.worldit.info/

*/


register_activation_hook(__FILE__, 'wit_activate_settings' );
add_action('admin_menu', 'wit_add_menu' );
add_action('wit_rss_update_data', 'buildRSSBox');

function wit_rss_outputContent($show = TRUE)
{
	$output = stripslashes(get_option('wit_rss_data'));
	if($show == TRUE) echo $output;
	else return $output;
}

function buildRSSBox()
{
	global $wpdb;
		
	$feeduri    = get_option('wit_rss_feed');
    $feedcount  = get_option('wit_rss_count');
	$feedimgd   = get_option('wit_rss_imgdefault');
	
	
    $feedmashup = stripslashes(get_option('wit_rss_mashup'));
	$output     = '';
	
	$content = curl($feeduri);
	if(strrpos($content,'<channel>') !== FALSE)
	{
		 preg_match_all("/<item>(.*?)<\/item>/s",$content,$items);
		 
		 for($i = 0; $i < $feedcount; $i++)
		 {
			 if(isset($items[1][$i]) == FALSE) break;
			 $item = $items[1][$i];
			 
			 preg_match("/<title>(.*?)<\/title>/",$item,$title);
			 preg_match("/<link>(.*?)<\/link>/",$item,$url);
			 preg_match("/<comments>(.*?)<\/comments>/",$item,$commentsURL);
			 preg_match("/<pubDate>(.*?)<\/pubDate>/",$item,$date);
			 preg_match("/<dc:creator>(.*?)<\/dc:creator>/",$item,$author);
			 preg_match("/<description>(.*?)<\/description>/s",$item,$excerpt);
			 preg_match("/<content:encoded>(.*?)<\/content:encoded>/s",$item,$content);
			 preg_match("/<slash:comments>(.*?)<\/slash:comments>/",$item,$commentsCount);
			 
			 if(stripos($item,'<img') !== FALSE)
			 { 
				$image = substr($item,stripos($item,'<img'));
				$image = substr($image,0,stirpos($image,'>'));
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
										  '%WIT_RSS_COMMENTS_URL%'
										  ),
									array($excerpt[1],
										  $content[1],
										  $image[1] == '' ? $feedimgd : $image[1],
										  $url[1],
										  $title[1],
										  $author[1],
										  gmdate('d M Y',strtotime($date[1])),
										  ($commentsCount[1] == '0' ? 'Fara comentarii' : ($commentsCount[1] == 1 ? '1 comentariu' : $commentsCount[1].' comentarii')),
										  $commentsURL[1]
										  ),
									$feedmashup
									);
		 }
	}
	else
	{
		$output = 'Could not get updates.';
	}
	
	update_option('wit_rss_data',trim($output));
}

function wit_add_menu()
{
	add_options_page('WIT RSS Box Settings', 'WIT RSS Box', 10, __FILE__, 'wit_settings');
}

function wit_activate_settings()
{
	add_option('wit_rss_count'     ,3);
	add_option('wit_rss_data'      ,'');
	add_option('wit_rss_feed'      ,'');
	add_option('wit_rss_imgdefault','');
	add_option('wit_rss_mashup'    ,'
			   <div class="clearfloat">
	    		  <a href="%WIT_RSS_URL%" rel="bookmark" title="%WIT_RSS_TITLE%">
					<img src="%WIT_RSS_IMAGE%" alt="%WIT_RSS_TITLE%" class="left" height="65px" width="100px">
				  </a>
      			  <div class="info">
				    <a href="%WIT_RSS_URL%" rel="bookmark" class="title">%WIT_RSS_TITLE%</a>
					<div class="meta">[%WIT_RSS_DATE% | Scris de %WIT_RSS_AUTHOR% | <a href="%WIT_RSS_COMMENTS_URL%" title="Comment on %WIT_RSS_TITLE%">%WIT_RSS_COMMENTS%</a>]</div>
				  </div>	
			   </div>
');
	wp_schedule_event(time(), 'hourly', 'wit_rss_update_data');
}

function wit_settings()
{
	global $wpdb;
	
	if(isset($_POST['submit']))
	{
		if(is_numeric($_POST['feedcount']))  update_option('wit_rss_count' ,$_POST['feedcount']);
		if($_POST['feeduri']!= '')           update_option('wit_rss_feed'  ,$_POST['feeduri']);
		if($_POST['feedmashup']!= '')        update_option('wit_rss_mashup',trim($_POST['feedmashup']));
		if($_POST['feedimgdefault']!='')     update_option('wit_rss_imgdefault',trim($_POST['feedimgdefault']));
		?>
		<script type="text/javascript">

		window.location = "<?=$_SERVER['PHP_SELF'].'?page=wit-rss-widget/wit_rss_widget.php&update=true'?>";

		</script>
        <?php
		
		wp_clear_scheduled_hook('wit_rss_update_data');
		wp_schedule_event(time(), 'hourly', 'wit_rss_update_data');
	}
	if($_GET['update']) echo '<div class="updated"><p><strong>'.__('Settings saved').'</strong></p></div>';
	
	$feeduri         = get_option('wit_rss_feed');
    $feedcount       = get_option('wit_rss_count');
    $feedmashup      = htmlspecialchars(stripslashes(get_option('wit_rss_mashup')));
	$feedimgdefault  = get_option('wit_rss_imgdefault');
	 
	?>
        
	<div class="wrap">

		<div id="icon-options-general" class="icon32"><br/></div>

		<h2>WIT RSS Box Settings</h2>
        <form method="post" action="<?=$_SERVER['PHP_SELF'].'?page=wit-rss-widget/wit_rss_widget.php'?>">

		<table class="form-table">
			<tbody>            	
                <tr>
					<th><label for="feeduri">Enter your feed URL : </label></th>
					<td>
						<input id="feeduri" type="text" name="feeduri" value="<?php echo $feeduri; ?>"  />
						<span class="setting-description">Your RSS adress : it can be RSS or Feedburner, it doesn`t matter. </span>
					</td>
				</tr>    
                <tr>
					<th><label for="feedcount">Items Count : </label></th>
					<td>
						<input id="feedcount" type="text" name="feedcount" value="<?php echo $feedcount; ?>"  />
						<span class="setting-description">Number of items that will be showed by RSS Box. </span>
					</td>
				</tr>   
                <tr>
					<th><label for="feedimgdefault">Default image : </label></th>
					<td>
						<input id="feedimgdefault" type="text" name="feedimgdefault" value="<?php echo $feedimgdefault; ?>"  />
						<span class="setting-description">The Default Image if an item doesn`t have any image.</span>
					</td>
				</tr>   
                <tr>
					<th><label for="feedmashup">Item HTML : </label></th>
					<td>
						<textarea id="feedmashup" cols="120" rows="10" name="feedmashup"><?php echo $feedmashup; ?></textarea><br />
						<span class="setting-description">You can use different variables for personalize output of the item.<br />
                        %WIT_RSS_EXCERPT% - the excerpt of the item<br />
                        %WIT_RSS_TEXT% - the content of the item<br />
                        %WIT_RSS_IMAGE% - the URL of the first image finded in %WIT_RSS_TEXT%<br />
                        %WIT_RSS_URL% - the URL of the item<br />
                        %WIT_RSS_TITLE% - the item Title<br />
                        %WIT_RSS_AUTHOR% - the author of the item<br />
                        %WIT_RSS_DATE% - item date <br />
                        %WIT_RSS_COMMENTS% - number of item comments<br />
                        %WIT_RSS_COMMENTS_URL% - url to the item comments</span>
					</td>
				</tr>    
                          
            </tbody>
            
        </table>
        <p class="submit"><input type="submit" name="submit" value="Save Changes" class="button-primary" /></p>
        
        </form>
    </div>
    
    <?php
}

function curl($url)
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