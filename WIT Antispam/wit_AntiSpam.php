<?php 
/*
	Plugin Name: wit_AntiSpam
	Plugin URI: http://www.worldit.info
	Description: The target of this Anti Spam system is to stop the bot comments. This system insert a new element completely invisible to users, but for bots no. This is happening because bots are trying to fill all visible inputs and the protection system will detected this action. 
	Version: 1.0
	Author: Avadanei Andrei
	Author URI: http://www.worldit.info
*/

										######################
										#      Licence       #
										######################
/*
	This plugin is completely free to use by anyone. If something goes wrong about it , please notify me about the bugs.
*/

/*
 * @function    : wit_AntiSpam()
 * @description : insert new element in submit form, only if not used before this	
 * @TIP         : you can add different form names which you find in every day forms
 */
function wit_AntiSpam($content)
{
	// Add Here different form element names
	$antiForms =  array('fax',
						'phone',
						'age',
						'middleName',
						'firstName',
						'lastName',
						'question',
						'answer',
						'id',
						'userid',
						'target'
						);
	$count = sizeof($antiForms);

	if(!isset($_SESSION['wit_formUsed']) || $_SESSION['wit_formUsed'] == TRUE) 
	{
		$_SESSION['wit_formName'] = $antiForms[rand(0,$count)]; 
		$_SESSION['wit_formUsed'] = FALSE;
	}
	echo '<p style="position:absolute;left:-'.rand(13371337,133713371337).'px;">DO NOT FILL FORM! <input type="text" name="'.$_SESSION['wit_formName'].'" /></p>';
}

/* 
 * @function    : wit_AntiSpam_Check()
 * @description : check if the new element create before was filled
 *
 */
function wit_AntiSpam_Check($content)
{
	if($_POST[$_SESSION['wit_formName']] != '' || $_SESSION['wit_formUsed'] == TRUE)
	{
		$_SESSION['wit_formUsed'] = TRUE;
		die('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>WordPress &rsaquo; Bot Comment Detected</title>
	<link rel="stylesheet" href="'.htmlentities($_SERVER['SERVER_NAME'],ENT_QUOTES).'/wp-admin/css/install.css" type="text/css" />
</head>
<body id="error-page">
	<p>Bot comment detected. It looks that you are a bot!</p></body>
</html>
');
	}
	else
	{
		$_SESSION['wit_formUsed'] = TRUE;
		return $content;
	}
}

/* 
 * @function 	: wit_sessionStart()
 * @description : enable sessions and object buffer
 */
function wit_sessionStart()
{
	@ob_start();
	@session_start();
}

/* 
 * @function    : wit_sessionFlush()
 * @description : release buffered objects 
 */
function wit_sessionFlush()
{
	@ob_flush();
}

/* ADD Actions */
add_action('init','wit_sessionStart');
add_filter('pre_comment_content', 'wit_AntiSpam_Check');
add_action('comment_form', 'wit_AntiSpam');
add_action('wp_footer','wit_sessionFlush');