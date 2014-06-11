<?php 
/**
 * @package Theme_Logo
 * @version 1.0
 */
/*
Plugin Name: Theme Logo
Plugin URI: http://wordpress.org/#
Description: Add an option to change your themes displayed logo.
Author: Zack Perdue
Version: 1.0
Author URI: http://zackperdue.com/
*/

function theme_logo(){
	echo '<img src="'.get_option("tl_logo_src").'" alt="'.get_option("blogname").'" />';
}

function menu_item(){
	add_submenu_page("themes.php", "Theme Logo", "Theme Logo", 8, "theme-logo", "menu_page");
}

function menu_page(){
	?>
	<div id="theme-logo" class="wrap">
		<h2>Theme Logo</h2>
		<p>With the Theme Logo plugin, you can manually change your website logo without a developer.</p>
		<form action="options.php" method="post">
		<?php 
			if(function_exists('settings_fields')){
	            settings_fields('tl-options');
	        }else{
	        	wp_nonce_field('update-options');
	            ?>
	            <input type="hidden" name="action" value="update" />
	            <input type="hidden" name="page_options" value="tl_logo_src" />
	            <?php
	        }
		?>
			<table class="form-table">
				<tr>
					<td scope="row" valign="top">
						Logo Location
					</td>
					<td>
						<input type="text" value="<?php echo get_option("tl_logo_src"); ?>" name="tl_logo_src" id="tl_logo_src" />
						<p>Ex.: http://domain.com/images/logo.png</p>
					</td>
					<td>
						<?php theme_logo(); ?>
					</td>
				</tr>
			</table>
			<p class="submit">
            	<input type="submit" name="Submit" value="<?php _e('Save Changes') ?>" />
       		</p>
		</form>
		<div id="how-to-use">
			<h2>How to Use</h2>
			<p>Go into your header file and replace your current logo image with <code>&lt;?php theme_logo(); ?&gt;</code>
			<br />
			<br />
			This will output <code>&lt;img src=&quot;http://domain.com/images/logo.png&quot; alt=&quot;Your Blog Title&quot; &gt;</code></p>
		</div>
		<div id="donate">
		<h2>If you like this plugin, please donate.</h2>
		<br />
		<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="4JDBDWKZSFZUY">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
		
		</div>
	</div>
	<?php 
}

function tl_init(){
	if(function_exists(register_setting)){
		register_setting("tl-options", "tl_logo_src");
	}
}

function tl_activate(){
	add_option("tl_logo_src", "default");
}

function tl_deactivate(){
	delete_option("tl_logo_src");
}

if(is_admin()){
	add_action("admin_menu", "menu_item");
	add_action('admin_init', 'tl_init');
}

register_activation_hook(__FILE__, "tl_activate");
register_deactivation_hook(__FILE__, "tl_deactivate");


?>