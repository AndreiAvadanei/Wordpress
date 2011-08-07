<?php
$wp_root = './';

if (file_exists($wp_root.'/wp-load.php')) 
{
	require_once($wp_root.'/wp-load.php');
} 
else 
{
	require_once($wp_root.'/wp-config.php');
}

if(!function_exists('wit_import')) 
{
	function wit_import() 
	{

		global $wpdb, $post;

		$temp  = '';
		$query = $wpdb->get_results("SELECT DISTINCT $wpdb->posts.*, (meta_value+0) AS views FROM $wpdb->posts LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID WHERE post_date < '".current_time('mysql')."' AND post_status = 'publish' AND meta_key = 'views' ORDER  BY views DESC");

		if($query)
		{
			foreach ($query as $post) 
			{
				$post_views1 = intval($post->views);
				$post_views2 = intval(get_post_meta($post->ID, "wit_views", true));  	
				
				if(!update_post_meta($post->ID, 'wit_views', ($post_views1+$post_views2))) 
				{
					add_post_meta($post->ID, 'wit_views', $post_views1, true);
				}
			}		
			echo 'Importarea a fost finalizata cu succes.Fisierul <strong>wit-convertor.php ar trebui sa fie eliminat</strong>.Este indicat sa va asigurati ca acesta a fost sters.';
			@unlink('wit-import.php');
		} 
		else 
		{
			 echo 'Importarea nu a putut fi facuta.';
		}
	}
}

if(isset($_GET['sure']) && $_GET['sure'] == 'YES')
 wit_import();
else
 echo 'Esti sigur ca doresti sa importi statisticile de la WP Post Views? <br />
 <a href="?sure=YES">Da</a> <a href="http://www.google.ro">Nu</a>';
?>