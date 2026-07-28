<?php
/**
 * Admin dashboard.
 *
 * @package MangaRuhu\RequestSystem
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MangaRuhu\RequestSystem\Database\Repositories\RequestRepository;

$repo   = new RequestRepository();
$counts = $repo->counts_by_status();
$total  = array_sum( $counts );
$pending  = (int) ( $counts['pending']  ?? 0 );
$approved = (int) ( $counts['approved'] ?? 0 );
$rejected = (int) ( $counts['rejected'] ?? 0 );
?>
<div class="wrap mrrs-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="mrrs-admin-stats">
		<div class="mrrs-admin-stat-card">
			<span class="mrrs-stat-number"><?php echo esc_html( (string) $total ); ?></span>
			<span class="mrrs-stat-label"><?php esc_html_e( 'Toplam Öneri', 'manga-ruhu-request-system' ); ?></span>
		</div>
		<div class="mrrs-admin-stat-card mrrs-stat--pending">
			<span class="mrrs-stat-number"><?php echo esc_html( (string) $pending ); ?></span>
			<span class="mrrs-stat-label"><?php esc_html_e( 'Bekleyen', 'manga-ruhu-request-system' ); ?></span>
		</div>
		<div class="mrrs-admin-stat-card mrrs-stat--approved">
			<span class="mrrs-stat-number"><?php echo esc_html( (string) $approved ); ?></span>
			<span class="mrrs-stat-label"><?php esc_html_e( 'Onaylanan', 'manga-ruhu-request-system' ); ?></span>
		</div>
		<div class="mrrs-admin-stat-card mrrs-stat--rejected">
			<span class="mrrs-stat-number"><?php echo esc_html( (string) $rejected ); ?></span>
			<span class="mrrs-stat-label"><?php esc_html_e( 'Reddedilen', 'manga-ruhu-request-system' ); ?></span>
		</div>
	</div>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=mrrs-requests&status=pending' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Bekleyen Önerileri Gözden Geçir', 'manga-ruhu-request-system' ); ?>
			<?php if ( $pending > 0 ) : ?>
				<span class="awaiting-mod"><?php echo esc_html( (string) $pending ); ?></span>
			<?php endif; ?>
		</a>
	</p>
</div>