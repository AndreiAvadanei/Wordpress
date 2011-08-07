<?php
/*

Plugin Name: WIT-Post Views-Visitors

Plugin URI: http://www.worldit.info

Description: Enables you to display how many times a post/page had been viewed.Also we can see how many unique visitor we had on a post or a page.Please visit <a href="http://www.worldit.info">WIT-Post Views Page</a> for more information about the plugin.
Version: 1.0.2

Author: Avadanei Andrei

Author URI: http://www.worldit.info

*/

/*  Copyright 2009 Avadanei Andrei  (email : andrei@worldit.info)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$countViews    = TRUE;
$countVisitors = TRUE;
$most_viewed_template  = '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %VIEW_COUNT%</li>';
$most_uviewed_template = '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %VIEW_U_COUNT%</li>';

if (!function_exists('add_action')) {

	$wp_root = '../../..';
	if (file_exists($wp_root.'/wp-load.php')) 
	{
		require_once($wp_root.'/wp-load.php');

	} 
	else 
	{

		require_once($wp_root.'/wp-config.php');
	}
}

if(function_exists('wit_activate_stats') == FALSE)
{	
	function wit_activate_stats()
	{
		global $user_ID, $post,$wpdb;
		global $countViews,$countVisitors;
		if(!wp_is_post_revision($post))
		{
			if(is_single() || is_page()) 
			{
				$post_views   = intval(get_post_meta($post->ID, "wit_views", true));  
				$post_unique  = intval(get_post_meta($post->ID, "wit_unique_views",true));
				$post_ips     = array_filter(explode(';',str_replace(' ','',get_post_meta($post->ID, "wit_unique_ips",true))), create_function('$a','return (trim($a)!="");'));
				
				if($countVisitors == TRUE)
				{
					$ip = htmlentities($_SERVER['REMOTE_ADDR'],ENT_QUOTES);
					if(in_array($ip,$post_ips) == FALSE)
					{					
						$post_ips[] = trim($ip); 
						$post_ips = array_unique($post_ips);
						
						if(!update_post_meta($post->ID, 'wit_unique_ips', implode(';',$post_ips))) 
						{
							add_post_meta($post->ID, 'wit_unique_ips', implode(';',$post_ips), true);
						}	
						
						if(!update_post_meta($post->ID, 'wit_unique_views', sizeof($post_ips))) 
						{
							add_post_meta($post->ID, 'wit_unique_views', sizeof($post_ips), true);
						}	
					}
				}
				if($countViews == TRUE)
				{					
					if(!update_post_meta($post->ID, 'wit_views', ($post_views+1))) 
					{
						add_post_meta($post->ID, 'wit_views', 1, true);
					}
				}
			}
		}
	}
}

if(function_exists('wit_add_views_fields') == FALSE)
{
	function wit_add_views_fields($post_ID)
	{	
		global $wpdb,$post;
	
		if(!wp_is_post_revision($post_ID)) 
		{	
			add_post_meta($post->ID, 'wit_views', 0, true);
			add_post_meta($post->ID, 'wit_unique_views', 0, true);
			add_post_meta($post->ID, 'wit_unique_ips', 0, true);
		}
	}
}

if(function_exists('wit_get_post_info') == FALSE)
{
	function wit_get_post_info($type = 'views',$message = array('Nici o afisare','O afisare','# afisari'),$show = TRUE)
	{
		global $user_ID, $post,$wpdb;
		if($type == 'views' || $type == 'unique_views')
		{
			$info = intval(get_post_meta($post->ID, "wit_".$type, true)); 
			$info = ($info <= 1 ? $message[$info] : str_replace('#',$info,$message[2]));
			if($show == TRUE)
				echo $info;
			else
				return $info;
		}
		return '';
	}
}


if(!function_exists('wit_get_viewed'))
{
	/* wp-postviews function get_most_viewed adapted for wit-post views/visitors,only the function structure keeped */
	function wit_get_viewed($meta='wit_views',$type = 'DESC',$limit = 10, $nchars = 0, $display = true)
	{
		global $wpdb, $post,$most_viewed_template,$most_uviewed_template;
		
		$query = $wpdb->get_results("SELECT DISTINCT $wpdb->posts.*, (meta_value+0) AS views FROM $wpdb->posts LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID WHERE post_date < '".current_time('mysql')."' AND post_status = 'publish' AND meta_key = '$meta' ORDER  BY views $type LIMIT $limit");
		if($query)
		{
			$output = '';
			foreach ($query as $post) 
			{
				$views  = isset($post->views) ? intval($post->views) : '';				
				
				$post_title = get_the_title();
				if($chars > 0)
				{
					$post_title = substr($post_title,0, $chars);
				}
				$post_excerpt = empty($post->post_excerpt) ?  substr($post->post_content,0,$chars) : $post->post_excerpt;
				$post_content = get_the_content();	
				
				$temp = ($meta == 'wit_views' ? $most_viewed_template : $most_uviewed_template);
				$temp = str_replace(array("%VIEW_COUNT%",
										  "%VIEW_U_COUNT%",
										  "%POST_TITLE%",
										  "%POST_EXCERPT%",
										  "%POST_CONTENT%",
										  "%POST_URL%"),
									array(intval($views),
										  intval($views),
										  $post_title,
										  $post_excerpt,
										  $post_content,
										  get_permalink()),$temp);
				$output .= $temp;
			}			
		} 
		else 
		{
			$output = '<li>N/A</li>'."\n";
		}
		if($display)
		{
			echo $output;
		} 
		else
		{
			return $output;
		}
	}
}


add_action('the_post'  , 'wit_activate_stats' );
add_action('publish_post'     , 'wit_add_views_fields'  );
add_action('publish_page'     , 'wit_add_views_fields'  );
?>