<?php
/**
 * Open Builder canvas template.
 *
 * Loaded via template_include when a theme-builder template (header, footer or
 * body) applies to the current request. It renders a minimal, theme-agnostic
 * document: wp_head/wp_footer still fire so other plugins and styles work, but
 * the theme's own header.php/footer.php/content templates are bypassed.
 *
 * @var array $GLOBALS['openb_tb'] header/footer/body template ids + body_type
 */

defined( 'ABSPATH' ) || exit;

use OpenBuilder\Theme_Builder;
use OpenBuilder\Post_Types;

$tb        = isset( $GLOBALS['openb_tb'] ) ? $GLOBALS['openb_tb'] : [];
$header_id = (int) ( $tb['header'] ?? 0 );
$footer_id = (int) ( $tb['footer'] ?? 0 );
$body_id   = (int) ( $tb['body'] ?? 0 );
$body_type = (string) ( $tb['body_type'] ?? '' );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'openb-themeless' ); ?>>
<?php wp_body_open(); ?>

<a class="openb-skip-link" href="#openb-content"><?php esc_html_e( 'Skip to content', 'open-builder' ); ?></a>

<?php if ( $header_id ) : ?>
	<header class="openb-site-header"><?php echo Theme_Builder::render_template( $header_id ); // Trusted renderer. ?></header>
<?php endif; ?>

<main class="openb-site-main" id="openb-content" tabindex="-1">
	<?php
	if ( $body_id ) {
		// A body template is assigned: render it. Dynamic widgets inside it
		// (Post Title, Post Content, Posts) read the current query/loop.
		if ( is_singular() ) {
			while ( have_posts() ) {
				the_post();
				echo Theme_Builder::render_template( $body_id ); // Trusted renderer.
			}
		} else {
			echo Theme_Builder::render_template( $body_id ); // Trusted renderer.
		}
	} else {
		// No body template — only header/footer apply. Render the natural
		// content so the page still works on any theme.
		if ( is_singular() ) {
			while ( have_posts() ) {
				the_post();
				$post_id = get_the_ID();
				if ( Post_Types::is_enabled( $post_id ) ) {
					// Built with Open Builder: render its tree directly.
					echo Theme_Builder::render_template( $post_id ); // Trusted renderer.
				} else {
					echo '<div class="openb-natural-content">';
					the_content();
					echo '</div>';
				}
			}
		} else {
			// Archive / blog index fallback: a simple, accessible post list.
			echo '<div class="openb-archive ob-section__inner">';
			if ( have_posts() ) {
				echo '<div class="openb-archive__list">';
				while ( have_posts() ) {
					the_post();
					printf(
						'<article class="openb-archive__item"><h2 class="openb-archive__title"><a href="%s">%s</a></h2><div class="openb-archive__excerpt">%s</div></article>',
						esc_url( get_permalink() ),
						esc_html( get_the_title() ),
						wp_kses_post( get_the_excerpt() )
					);
				}
				echo '</div>';
				echo '<div class="openb-archive__nav">' . wp_kses_post( get_the_posts_pagination() ) . '</div>';
			} else {
				echo '<p>' . esc_html__( 'Nothing found.', 'open-builder' ) . '</p>';
			}
			echo '</div>';
		}
	}
	?>
</main>

<?php if ( $footer_id ) : ?>
	<footer class="openb-site-footer"><?php echo Theme_Builder::render_template( $footer_id ); // Trusted renderer. ?></footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
