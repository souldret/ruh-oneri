<?php
/**
 * Ayarlar sayfası.
 *
 * @package MangaRuhu\RequestSystem\Admin
 */

declare(strict_types=1);

namespace MangaRuhu\RequestSystem\Admin;

final class Settings {

	public const OPTION_GROUP    = 'mrrs_settings';
	public const OPTION_NAME     = 'mrrs_options';
	public const FRONTEND_PAGE   = 'mrrs_frontend_page_id';

	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => $this->defaults(),
			)
		);

		// Özel seçenek: frontend sayfa ID'si (ayrı option).
		register_setting(
			self::OPTION_GROUP,
			self::FRONTEND_PAGE,
			array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);

		/* ── Genel Ayarlar ── */
		add_settings_section(
			'mrrs_general',
			__( 'Genel Ayarlar', 'manga-ruhu-request-system' ),
			'__return_false',
			self::OPTION_GROUP
		);

		add_settings_field(
			'allow_guest_votes',
			__( 'Misafir Oyları', 'manga-ruhu-request-system' ),
			array( $this, 'field_checkbox' ),
			self::OPTION_GROUP,
			'mrrs_general',
			array(
				'key'   => 'allow_guest_votes',
				'label' => __( 'Giriş yapmayan kullanıcılar oy kullanabilsin', 'manga-ruhu-request-system' ),
			)
		);

		add_settings_field(
			'allow_guest_submit',
			__( 'Misafir Öneri', 'manga-ruhu-request-system' ),
			array( $this, 'field_checkbox' ),
			self::OPTION_GROUP,
			'mrrs_general',
			array(
				'key'   => 'allow_guest_submit',
				'label' => __( 'Giriş yapmayan kullanıcılar öneri gönderebilsin', 'manga-ruhu-request-system' ),
			)
		);

		add_settings_field(
			'per_page',
			__( 'Sayfa Başına Öneri', 'manga-ruhu-request-system' ),
			array( $this, 'field_per_page' ),
			self::OPTION_GROUP,
			'mrrs_general'
		);

		add_settings_field(
			'rejected_retention',
			__( 'Reddedilen Önerileri Sakla', 'manga-ruhu-request-system' ),
			array( $this, 'field_rejected_retention' ),
			self::OPTION_GROUP,
			'mrrs_general'
		);

		/* ── Öneri Kuralları Banner'ı ── */
		add_settings_section(
			'mrrs_rules_banner',
			__( 'Öneri Kuralları Banner\'ı', 'manga-ruhu-request-system' ),
			array( $this, 'section_rules_banner_desc' ),
			self::OPTION_GROUP
		);

		add_settings_field(
			'rules_banner_enabled',
			__( 'Banner\'ı Etkinleştir', 'manga-ruhu-request-system' ),
			array( $this, 'field_checkbox' ),
			self::OPTION_GROUP,
			'mrrs_rules_banner',
			array(
				'key'   => 'rules_banner_enabled',
				'label' => __( 'Öneri formunun üzerinde kural/kriter banner\'ını göster', 'manga-ruhu-request-system' ),
			)
		);

		add_settings_field(
			'rules_banner_text',
			__( 'Banner Metni', 'manga-ruhu-request-system' ),
			array( $this, 'field_rules_banner_text' ),
			self::OPTION_GROUP,
			'mrrs_rules_banner'
		);

		add_settings_field(
			'rules_banner_color',
			__( 'Duyuru Çubuğu Rengi', 'manga-ruhu-request-system' ),
			array( $this, 'field_main_color' ),
			self::OPTION_GROUP,
			'mrrs_rules_banner',
			array( 'slug' => 'rules_banner_color' )
		);

		add_settings_field(
			'rules_banner_text_color',
			__( 'Duyuru Çubuğu Yazı Rengi', 'manga-ruhu-request-system' ),
			array( $this, 'field_main_color' ),
			self::OPTION_GROUP,
			'mrrs_rules_banner',
			array( 'slug' => 'rules_banner_text_color' )
		);

		/* ── Ana Renkler ── */
		add_settings_section(
			'mrrs_main_colors',
			__( 'Ana Renkler', 'manga-ruhu-request-system' ),
			array( $this, 'section_main_colors_desc' ),
			self::OPTION_GROUP
		);

		$main_color_fields = array(
			'color_accent'      => __( 'Vurgu Rengi (Accent)', 'manga-ruhu-request-system' ),
			'color_accent_light'=> __( 'Açık Vurgu Rengi', 'manga-ruhu-request-system' ),
			'color_text'        => __( 'Ana Metin Rengi', 'manga-ruhu-request-system' ),
			'color_text_muted'  => __( 'Soluk Metin Rengi', 'manga-ruhu-request-system' ),
			'color_card_bg'     => __( 'Kart Arka Planı', 'manga-ruhu-request-system' ),
			'color_border'      => __( 'Kenar Rengi', 'manga-ruhu-request-system' ),
		);
		foreach ( $main_color_fields as $slug => $label ) {
			add_settings_field(
				$slug,
				$label,
				array( $this, 'field_main_color' ),
				self::OPTION_GROUP,
				'mrrs_main_colors',
				array( 'slug' => $slug )
			);
		}

		/* ── Rozet Renkleri ── */
		add_settings_section(
			'mrrs_badge_colors',
			__( 'Durum Rozeti Renkleri', 'manga-ruhu-request-system' ),
			array( $this, 'section_badge_colors_desc' ),
			self::OPTION_GROUP
		);

		$badge_statuses = array(
			'pending'     => __( 'Beklemede', 'manga-ruhu-request-system' ),
			'reviewing'   => __( 'İnceleniyor', 'manga-ruhu-request-system' ),
			'approved'    => __( 'Onaylandı', 'manga-ruhu-request-system' ),
			'rejected'    => __( 'Reddedildi', 'manga-ruhu-request-system' ),
			'translating' => __( 'Çeviriye Alındı', 'manga-ruhu-request-system' ),
		);
		foreach ( $badge_statuses as $slug => $label ) {
			add_settings_field(
				'badge_color_' . $slug,
				$label,
				array( $this, 'field_badge_color' ),
				self::OPTION_GROUP,
				'mrrs_badge_colors',
				array( 'slug' => $slug )
			);
		}

		/* ── Frontend Sayfası ── */
		add_settings_section(
			'mrrs_frontend_page_section',
			__( 'Frontend Sayfası', 'manga-ruhu-request-system' ),
			array( $this, 'section_frontend_page_desc' ),
			self::OPTION_GROUP
		);

		add_settings_field(
			self::FRONTEND_PAGE,
			__( 'Öneri Sayfası', 'manga-ruhu-request-system' ),
			array( $this, 'field_page_select' ),
			self::OPTION_GROUP,
			'mrrs_frontend_page_section'
		);
	}

	public function section_frontend_page_desc(): void {
		echo '<p>' . esc_html__( 'Seri öneri sisteminin görüneceği WordPress sayfasını seçin. Seçilen sayfa otomatik olarak eklenti şablonunu kullanır.', 'manga-ruhu-request-system' ) . '</p>';
	}

	public function field_page_select(): void {
		$current = (int) get_option( self::FRONTEND_PAGE, 0 );

		wp_dropdown_pages( array(
			'name'              => self::FRONTEND_PAGE,
			'id'                => 'mrrs_frontend_page_id',
			'selected'          => $current,
			'show_option_none'  => __( '— Sayfa Seçin —', 'manga-ruhu-request-system' ),
			'option_none_value' => '0',
			'post_status'       => 'publish',
		) );

		if ( $current > 0 ) {
			$url = get_permalink( $current );
			if ( $url ) {
				echo ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
					. esc_html__( 'Sayfayı Görüntüle', 'manga-ruhu-request-system' )
					. ' ↗</a>';
			}
		}

		echo '<p class="description">'
			. esc_html__( 'Seçtiğiniz sayfaya manuel olarak herhangi bir kod eklemenize gerek yoktur.', 'manga-ruhu-request-system' )
			. '</p>';
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Yetkiniz yok.', 'manga-ruhu-request-system' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function field_checkbox( array $args ): void {
		$opts  = $this->get();
		$key   = $args['key'];
		$value = ! empty( $opts[ $key ] );
		$name  = self::OPTION_NAME . '[' . esc_attr( $key ) . ']';
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value ); ?>>
			<?php echo esc_html( $args['label'] ?? '' ); ?>
		</label>
		<?php
	}

	public function field_per_page(): void {
		$opts    = $this->get();
		$current = (int) ( $opts['per_page'] ?? 20 );
		$name    = self::OPTION_NAME . '[per_page]';
		?>
		<select name="<?php echo esc_attr( $name ); ?>" id="mrrs_per_page">
			<?php foreach ( array( 10, 20, 30, 50 ) as $n ) : ?>
				<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $current, $n ); ?>>
					<?php echo esc_html( $n ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Seri öneri listesinde sayfa başına kaç öneri gösterileceğini belirler.', 'manga-ruhu-request-system' ); ?></p>
		<?php
	}

	public function field_rejected_retention(): void {
		$opts    = $this->get();
		$current = (string) ( $opts['rejected_retention'] ?? 'never_delete' );
		$name    = self::OPTION_NAME . '[rejected_retention]';
		$options = array(
			'never_delete' => __( 'Hiçbir zaman silme (varsayılan)', 'manga-ruhu-request-system' ),
			'1_hour'       => __( '1 saat sonra sil', 'manga-ruhu-request-system' ),
			'1_day'        => __( '1 gün sonra sil', 'manga-ruhu-request-system' ),
			'1_week'       => __( '1 hafta sonra sil', 'manga-ruhu-request-system' ),
		);
		?>
		<select name="<?php echo esc_attr( $name ); ?>" id="mrrs_rejected_retention">
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e( 'Seçilen süre sonunda reddedilen öneriler kalıcı olarak silinir, geri alınamaz.', 'manga-ruhu-request-system' ); ?>
		</p>
		<?php
	}

	public function section_rules_banner_desc(): void {
		echo '<p>' . esc_html__( 'Öneri listesinin en üstünde gösterilecek kural/kriter banner\'ını yönetin.', 'manga-ruhu-request-system' ) . '</p>';
	}

	public function field_rules_banner_text(): void {
		$opts    = $this->get();
		$current = (string) ( $opts['rules_banner_text'] ?? '' );
		$name    = self::OPTION_NAME . '[rules_banner_text]';
		?>
		<textarea
			name="<?php echo esc_attr( $name ); ?>"
			id="mrrs_rules_banner_text"
			rows="4"
			class="large-text"
			placeholder="<?php esc_attr_e( 'Yalnızca manhwa önerileri kabul edilmektedir. Öneriler günlük olarak incelenmektedir.', 'manga-ruhu-request-system' ); ?>"
		><?php echo esc_textarea( $current ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'İzin verilen HTML etiketleri: <b>, <i>, <a>, <br>, <ul>, <li>, <p>', 'manga-ruhu-request-system' ); ?>
		</p>
		<?php
	}

	public function section_main_colors_desc(): void {
		echo '<p>' . esc_html__( 'Eklentinin ana renklerini özelleştirin. Boş bırakırsanız varsayılan renkler kullanılır.', 'manga-ruhu-request-system' ) . '</p>';
	}

	public function field_main_color( array $args ): void {
		$slug    = sanitize_key( $args['slug'] ?? '' );
		$opts    = $this->get();
		$current = (string) ( $opts[ $slug ] ?? '' );
		$default = $this->default_main_color( $slug );
		$name    = self::OPTION_NAME . '[' . esc_attr( $slug ) . ']';
		?>
		<input
			type="color"
			name="<?php echo esc_attr( $name ); ?>"
			id="mrrs_<?php echo esc_attr( $slug ); ?>"
			value="<?php echo esc_attr( '' !== $current ? $current : $default ); ?>"
		>
		<button type="button" class="button button-small" style="margin-left:6px"
			onclick="document.getElementById('mrrs_<?php echo esc_attr( $slug ); ?>').value='<?php echo esc_attr( $default ); ?>'">
			<?php esc_html_e( 'Sıfırla', 'manga-ruhu-request-system' ); ?>
		</button>
		<code style="margin-left:6px;color:#999"><?php echo esc_html( $default ); ?></code>
		<?php
	}

	public function section_badge_colors_desc(): void {
		echo '<p>' . esc_html__( 'Her durum rozetinin rengini özelleştirin. Boş bırakırsanız varsayılan renkler kullanılır.', 'manga-ruhu-request-system' ) . '</p>';
	}

	public function field_badge_color( array $args ): void {
		$slug    = sanitize_key( $args['slug'] ?? '' );
		$opts    = $this->get();
		$current = (string) ( $opts[ 'badge_color_' . $slug ] ?? '' );
		$default = $this->default_badge_color( $slug );
		$name    = self::OPTION_NAME . '[badge_color_' . esc_attr( $slug ) . ']';
		?>
		<input
			type="color"
			name="<?php echo esc_attr( $name ); ?>"
			id="mrrs_badge_color_<?php echo esc_attr( $slug ); ?>"
			value="<?php echo esc_attr( '' !== $current ? $current : $default ); ?>"
		>
		<button type="button" class="button button-small" style="margin-left:6px"
			onclick="document.getElementById('mrrs_badge_color_<?php echo esc_attr( $slug ); ?>').value='<?php echo esc_attr( $default ); ?>'">
			<?php esc_html_e( 'Sıfırla', 'manga-ruhu-request-system' ); ?>
		</button>
		<?php
	}

	public function sanitize( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return $this->defaults();
		}
		$allowed_per_page = array( 10, 20, 30, 50 );
		$per_page         = (int) ( $input['per_page'] ?? 20 );

		$allowed_retention = array( 'never_delete', '1_hour', '1_day', '1_week' );
		$retention         = (string) ( $input['rejected_retention'] ?? 'never_delete' );

		$out = array(
			'allow_guest_votes'   => ! empty( $input['allow_guest_votes'] ),
			'allow_guest_submit'  => ! empty( $input['allow_guest_submit'] ),
			'per_page'            => in_array( $per_page, $allowed_per_page, true ) ? $per_page : 20,
			'rejected_retention'  => in_array( $retention, $allowed_retention, true ) ? $retention : 'never_delete',
			'rules_banner_enabled'    => ! empty( $input['rules_banner_enabled'] ),
			'rules_banner_text'       => wp_kses_post( (string) ( $input['rules_banner_text'] ?? '' ) ),
			'rules_banner_color'      => sanitize_hex_color( (string) ( $input['rules_banner_color'] ?? '' ) ) ?? '',
			'rules_banner_text_color' => sanitize_hex_color( (string) ( $input['rules_banner_text_color'] ?? '' ) ) ?? '',
		);

		// Ana renkler
		foreach ( array( 'color_accent', 'color_accent_light', 'color_text', 'color_text_muted', 'color_card_bg', 'color_border' ) as $key ) {
			$val         = isset( $input[ $key ] ) ? sanitize_hex_color( (string) $input[ $key ] ) : '';
			$out[ $key ] = $val ?? '';
		}

		// Rozet renkleri
		foreach ( array( 'pending', 'reviewing', 'approved', 'rejected', 'translating' ) as $slug ) {
			$key         = 'badge_color_' . $slug;
			$val         = isset( $input[ $key ] ) ? sanitize_hex_color( (string) $input[ $key ] ) : '';
			$out[ $key ] = $val ?? '';
		}

		return $out;
	}

	public function get(): array {
		$opts = get_option( self::OPTION_NAME, array() );
		return is_array( $opts ) ? array_merge( $this->defaults(), $opts ) : $this->defaults();
	}

	public static function get_option( string $key, mixed $default = null ): mixed {
		$opts = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
	}

	public static function get_frontend_page_id(): int {
		return (int) get_option( self::FRONTEND_PAGE, 0 );
	}

	public static function get_per_page(): int {
		$opts     = get_option( self::OPTION_NAME, array() );
		$per_page = is_array( $opts ) ? (int) ( $opts['per_page'] ?? 20 ) : 20;
		return in_array( $per_page, array( 10, 20, 30, 50 ), true ) ? $per_page : 20;
	}

	private function defaults(): array {
		$out = array(
			'allow_guest_votes'    => true,
			'allow_guest_submit'   => true,
			'per_page'             => 20,
			'rejected_retention'   => 'never_delete',
			'rules_banner_enabled'    => false,
			'rules_banner_text'       => '',
			'rules_banner_color'      => '',
			'rules_banner_text_color' => '',
		);
		foreach ( array( 'color_accent', 'color_accent_light', 'color_text', 'color_text_muted', 'color_card_bg', 'color_border' ) as $key ) {
			$out[ $key ] = '';
		}
		foreach ( array( 'pending', 'reviewing', 'approved', 'rejected', 'translating' ) as $slug ) {
			$out[ 'badge_color_' . $slug ] = '';
		}
		return $out;
	}

	private function default_main_color( string $slug ): string {
		$defaults = array(
			'color_accent'            => '#7c3aed',
			'color_accent_light'      => '#a78bfa',
			'color_text'              => '#e2e8f0',
			'color_text_muted'        => '#94a3b8',
			'color_card_bg'           => '#141622',
			'color_border'            => '#ffffff',
			'rules_banner_color'      => '#7c3aed',
			'rules_banner_text_color' => '',
		);
		return $defaults[ $slug ] ?? '#ffffff';
	}

	/**
	 * Banner renk ayarlarını döndür.
	 *
	 * @return array{rules_banner_color:string, rules_banner_text_color:string}
	 */
	public static function get_rules_banner_colors(): array {
		$opts  = get_option( self::OPTION_NAME, array() );
		$opts  = is_array( $opts ) ? $opts : array();
		$color = sanitize_hex_color( (string) ( $opts['rules_banner_color'] ?? '' ) );
		$text  = sanitize_hex_color( (string) ( $opts['rules_banner_text_color'] ?? '' ) );
		return array(
			'rules_banner_color'      => ( null !== $color && '' !== $color ) ? $color : '',
			'rules_banner_text_color' => ( null !== $text && '' !== $text ) ? $text : '',
		);
	}

	/**
	 * Ana renkleri döndür.
	 *
	 * @return array<string,string>
	 */
	public static function get_main_colors(): array {
		$opts     = get_option( self::OPTION_NAME, array() );
		$opts     = is_array( $opts ) ? $opts : array();
		$keys     = array( 'color_accent', 'color_accent_light', 'color_text', 'color_text_muted', 'color_card_bg', 'color_border' );
		$result   = array();
		foreach ( $keys as $key ) {
			$val          = sanitize_hex_color( (string) ( $opts[ $key ] ?? '' ) );
			$result[$key] = $val ?? '';
		}
		return $result;
	}

	private function default_badge_color( string $slug ): string {
		$defaults = array(
			'pending'     => '#fbbf24',
			'reviewing'   => '#60a5fa',
			'approved'    => '#4ade80',
			'rejected'    => '#f87171',
			'translating' => '#a78bfa',
		);
		return $defaults[ $slug ] ?? '#94a3b8';
	}

	/**
	 * Rozet renklerini CSS custom property olarak döndür.
	 * Özelleştirilmemiş renkler için boş string döner (varsayılan CSS'e düşer).
	 *
	 * @return array<string,string>  slug => hex_color
	 */
	public static function get_badge_colors(): array {
		$opts     = get_option( self::OPTION_NAME, array() );
		$opts     = is_array( $opts ) ? $opts : array();
		$defaults = array(
			'pending'     => '',
			'reviewing'   => '',
			'approved'    => '',
			'rejected'    => '',
			'translating' => '',
		);
		$result = array();
		foreach ( $defaults as $slug => $fallback ) {
			$val = sanitize_hex_color( (string) ( $opts[ 'badge_color_' . $slug ] ?? '' ) );
			$result[ $slug ] = $val ?? $fallback;
		}
		return $result;
	}
}