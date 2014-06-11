=== Theme Logo ===
Contributors: zackperdue
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=4JDBDWKZSFZUY
Tags: Theme customization, logo replacement
Requires at least: 3.0
Tested up to: 3.0.1
Stable tag: 4.3

== Description ==
This plugin adds a field in the admin panel that allows you to change the logo of your website without needing a developer.

== Installation ==
Just upload the zip folder using the plugin installer option in Wordpress. Once it is uploaded you should activate it now - you will notice nothing happens. 
Now you will need someone that is capable of editing the header file or wherever you current logo image is in the markup. They will have to replace your markup for the logo with <pre><code><?php theme_logo(); ?></code></pre> once this is done you can upload your image using wordpress' media uploader, with this you need to copy the uploaded images src location to your clipboard. After you have copied and sized your image to how you want it navigate to Appearance>Theme Logo and then paste the location in the text box and click save. It should now be showing up on your theme. Enjoy!
