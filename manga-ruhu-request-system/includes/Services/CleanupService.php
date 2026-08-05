<?php
/**
 * Reddedilen önerileri ayarlanabilir süre sonunda temizleyen servis.
 *
 * @package MangaRuhu\RequestSystem\Services
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Services;

use MangaRuhu\RequestSystem\Admin\Settings;
use MangaRuhu\RequestSystem\Database\Repositories\RequestRepository;
use MangaRuhu\RequestSystem\Loader;

final class CleanupService {

	public const CRON_HOOK = 'mrrs_cleanup_rejected';

	public function register( Loader $loader ): void {
		$loader->add_action( self::CRON_HOOK, $this, 'run' );
	}

	public function run(): void {
		$retention = Settings::get_option( 'rejected_retention', 'never_delete' );
		if ( 'never_delete' === $retention ) {
			return;
		}

		$seconds = array(
			'1_hour' => HOUR_IN_SECONDS,
			'1_day'  => DAY_IN_SECONDS,
			'1_week' => WEEK_IN_SECONDS,
		)[ $retention ] ?? null;

		if ( null === $seconds ) {
			return;
		}

		$repo = new RequestRepository();
		$repo->delete_rejected_older_than( $seconds );
	}
}