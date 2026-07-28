<?php
/**
 * Template Name: MangaRuhu - Seri Önerileri
 * Template Post Type: page
 *
 * Seri öneri sisteminin ön yüz sayfası.
 * Aktif temanın header/footer/container'ını kullanır.
 *
 * @package MangaRuhu\RequestSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div class="<?php echo esc_attr( apply_filters( 'mrrs_page_container_class', 'container' ) ); ?>">

	<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<?php if ( get_the_title() ) : ?>
				<header class="entry-header">
					<h1 class="entry-title"><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>

			<div class="entry-content mrrs-page-content">
				<?php
				// Form önce, liste sonra (ya da tersi tema filtreye göre ayarlanabilir).
				$order = (array) apply_filters( 'mrrs_page_section_order', array( 'form', 'board' ) );

				foreach ( $order as $section ) {
					if ( 'form' === $section ) {
						echo do_shortcode( '[mrrs_form]' );
					} elseif ( 'board' === $section ) {
						echo do_shortcode( '[mrrs_board]' );
					}
				}
				?>
			</div>

		</article>

	<?php endwhile; ?>

</div>

<?php get_footer(); ?>