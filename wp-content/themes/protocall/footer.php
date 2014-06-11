<?php
/**
 * The footer Template
 *
 * @package WordPress
 * @subpackage minimatica
 * @since Minimatica 1.0
 */
 ?>
 		<footer id="footer">
			<?php get_sidebar( 'footer' ); ?>
 			<nav id="access" role="navigation">
 				<?php wp_nav_menu( array( 'theme_location'  => 'primary_nav', 'container_id' => 'primary-nav', 'container_class' => 'nav', 'fallback_cb' => 'minimatica_nav_menu' ) ); ?>
			</nav><!-- #access -->

			<div>
			<div class="copyright">
				<div style="float: right;">Webdesign - Webmastering : <a href="http://www.sosweb.ch" title="SOSWEB.CH - Agence de Webmastering Webdesign" target="_blank">SOSWEB.CH</a></div>
				<div style="float: left">&copy; 2011 - Protocall, tous droits réservés.</div>
				<div style="text-align: center"><a href="<?php echo esc_url( __( 'http://wordpress.org/', 'twentyeleven' ) ); ?>" title="<?php esc_attr_e( 'Semantic Personal Publishing Platform', 'twentyeleven' ); ?>" rel="generator"><?php printf( __( 'Fi&egrave;rement propuls&eacute; par %s', 'twentyeleven' ), 'WordPress' ); ?></a></div>
			</div>

		</footer><!-- #footer -->
	</div><!-- #wrapper -->
	<?php wp_footer(); ?>
</body>
</html>




