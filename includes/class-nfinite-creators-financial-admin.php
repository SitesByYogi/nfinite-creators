<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Financial Admin + Creator Statements V2.
 *
 * Provides an operations layer over the unified creator ledger without creating
 * a second source of financial truth. Statement snapshots are derived from the
 * ledger and remain auditable back to their source entries.
 */
class Nfinite_Creators_Financial_Admin {
	const DB_VERSION = '1';
	const DB_VERSION_OPTION = 'nfinite_financial_admin_db_version';
	const SETTINGS_OPTION = 'nfinite_financial_admin_settings';
	const META_PAYOUT_HOLD = '_nfinite_payout_hold';
	const META_PAYOUT_HOLD_REASON = '_nfinite_payout_hold_reason';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 3 );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 35 );
		add_action( 'admin_post_nfinite_financial_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_nfinite_financial_close_period', array( __CLASS__, 'close_period' ) );
		add_action( 'admin_post_nfinite_financial_reopen_period', array( __CLASS__, 'reopen_period' ) );
		add_action( 'admin_post_nfinite_financial_adjustment', array( __CLASS__, 'create_adjustment' ) );
		add_action( 'admin_post_nfinite_financial_reverse_entry', array( __CLASS__, 'reverse_entry' ) );
		add_action( 'admin_post_nfinite_financial_creator_hold', array( __CLASS__, 'creator_hold' ) );
		add_action( 'admin_post_nfinite_financial_export_statement', array( __CLASS__, 'export_statement' ) );
		add_action( 'admin_post_nfinite_financial_export_period', array( __CLASS__, 'export_period' ) );
		add_shortcode( 'nfinite_creator_statements', array( __CLASS__, 'creator_statements_shortcode' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'nfinite_creator_statements';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			statement_key varchar(191) NOT NULL,
			creator_id bigint(20) unsigned NOT NULL,
			accounting_period varchar(20) NOT NULL,
			currency varchar(12) NOT NULL DEFAULT 'USD',
			gross_amount bigint(20) NOT NULL DEFAULT 0,
			platform_fee_amount bigint(20) NOT NULL DEFAULT 0,
			creator_amount bigint(20) NOT NULL DEFAULT 0,
			reversed_amount bigint(20) NOT NULL DEFAULT 0,
			provisional_amount bigint(20) NOT NULL DEFAULT 0,
			finalized_amount bigint(20) NOT NULL DEFAULT 0,
			paid_amount bigint(20) NOT NULL DEFAULT 0,
			payable_amount bigint(20) NOT NULL DEFAULT 0,
			entry_count int(11) unsigned NOT NULL DEFAULT 0,
			status varchar(30) NOT NULL DEFAULT 'finalized',
			snapshot longtext NULL,
			closed_by bigint(20) unsigned NOT NULL DEFAULT 0,
			closed_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY statement_key (statement_key),
			KEY creator_period (creator_id,accounting_period),
			KEY accounting_period (accounting_period),
			KEY status (status)
		) {$charset};";
		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== (string) get_option( self::DB_VERSION_OPTION, '' ) ) { self::install(); }
	}

	public static function settings() {
		$s = get_option( self::SETTINGS_OPTION, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), array(
			'minimum_payout_minor' => 2500,
			'default_currency' => 'USD',
			'statement_note' => 'PairOfDice Media creator earnings statement.',
		) );
	}

	public static function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=nfinite_creator',
			__( 'Financial Admin', 'nfinite-creators' ),
			__( 'Financial Admin', 'nfinite-creators' ),
			'manage_options',
			'nfinite-financial-admin',
			array( __CLASS__, 'admin_page' )
		);
	}

	private static function period() {
		$p = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : gmdate( 'Y-m' );
		return preg_match( '/^\d{4}-\d{2}$/', $p ) ? $p : gmdate( 'Y-m' );
	}

	private static function money( $minor, $currency = 'USD' ) {
		$major = class_exists( 'Nfinite_Creators_Payments' ) ? Nfinite_Creators_Payments::from_minor( (int) $minor, $currency ) : ( (int) $minor / 100 );
		return function_exists( 'wc_price' ) ? wc_price( $major, array( 'currency' => $currency ) ) : esc_html( strtoupper( $currency ) . ' ' . number_format_i18n( $major, 2 ) );
	}

	private static function source_label( $type ) {
		$labels = array(
			'beat_sale' => 'Beat sales', 'product_sale' => 'Product sales', 'service_sale' => 'Service sales',
			'music_streaming' => 'PairOfDice streams', 'video_engagement' => 'Video revenue share', 'membership' => 'Memberships',
			'event' => 'Events', 'distribution' => 'Distribution', 'editorial' => 'Editorial', 'affiliate' => 'Affiliate',
			'sponsorship' => 'Sponsorships / brand deals', 'adjustment' => 'Adjustments',
		);
		return $labels[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
	}

	/** Aggregate the live ledger for one period. */
	public static function period_rows( $period ) {
		global $wpdb;
		$table = Nfinite_Creators_Payments::table_name();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE accounting_period=%s ORDER BY creator_id ASC, created_at ASC", $period ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function summarize_rows( $rows ) {
		$out = array();
		foreach ( (array) $rows as $row ) {
			$creator_id = absint( $row['creator_id'] ?? 0 );
			if ( ! $creator_id ) { continue; }
			$currency = strtoupper( $row['currency'] ?: 'USD' );
			$key = $creator_id . ':' . $currency;
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'creator_id' => $creator_id, 'currency' => $currency, 'gross' => 0, 'platform_fee' => 0, 'creator' => 0,
					'reversed' => 0, 'provisional' => 0, 'finalized' => 0, 'paid' => 0, 'payable' => 0, 'count' => 0, 'sources' => array(), 'entries' => array(),
				);
			}
			$s =& $out[ $key ];
			$gross = max( 0, (int) $row['eligible_amount'] );
			$creator = max( 0, (int) $row['creator_amount'] );
			$reversed = min( $creator, max( 0, (int) $row['reversed_amount'] ) );
			$net = max( 0, $creator - $reversed );
			$is_provisional = ! empty( $row['is_provisional'] ) || 'provisional' === ( $row['earning_status'] ?? '' );
			$is_paid = in_array( ( $row['payout_status'] ?? '' ), array( 'transferred', 'paid' ), true ) || 'transferred' === ( $row['status'] ?? '' );
			$s['gross'] += $gross;
			$s['platform_fee'] += max( 0, (int) $row['platform_fee_amount'] );
			$s['creator'] += $creator;
			$s['reversed'] += $reversed;
			if ( $is_provisional ) { $s['provisional'] += $net; }
			else { $s['finalized'] += $net; }
			if ( $is_paid ) { $s['paid'] += $net; }
			elseif ( ! $is_provisional && ! in_array( ( $row['payout_status'] ?? '' ), array( 'reversed', 'cancelled' ), true ) ) { $s['payable'] += $net; }
			$s['count']++;
			$type = sanitize_key( $row['source_type'] ?? 'adjustment' );
			if ( ! isset( $s['sources'][ $type ] ) ) { $s['sources'][ $type ] = array( 'gross' => 0, 'creator' => 0, 'reversed' => 0, 'count' => 0 ); }
			$s['sources'][ $type ]['gross'] += $gross;
			$s['sources'][ $type ]['creator'] += $creator;
			$s['sources'][ $type ]['reversed'] += $reversed;
			$s['sources'][ $type ]['count']++;
			$s['entries'][] = $row;
		}
		return array_values( $out );
	}

	private static function creator_name( $creator_id ) {
		$title = get_the_title( $creator_id );
		return $title ? $title : sprintf( __( 'Creator #%d', 'nfinite-creators' ), $creator_id );
	}

	private static function creator_hold_state( $creator_id ) {
		return array(
			'hold' => '1' === (string) get_post_meta( $creator_id, self::META_PAYOUT_HOLD, true ),
			'reason' => (string) get_post_meta( $creator_id, self::META_PAYOUT_HOLD_REASON, true ),
		);
	}

	public static function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$period = self::period();
		$summary = self::summarize_rows( self::period_rows( $period ) );
		$settings = self::settings();
		$totals = array( 'gross'=>0,'fee'=>0,'creator'=>0,'reversed'=>0,'provisional'=>0,'finalized'=>0,'paid'=>0,'payable'=>0 );
		foreach ( $summary as $s ) {
			$totals['gross'] += $s['gross']; $totals['fee'] += $s['platform_fee']; $totals['creator'] += $s['creator']; $totals['reversed'] += $s['reversed'];
			$totals['provisional'] += $s['provisional']; $totals['finalized'] += $s['finalized']; $totals['paid'] += $s['paid']; $totals['payable'] += $s['payable'];
		}
		$statement_count = self::statement_count( $period );
		$base = admin_url( 'edit.php?post_type=nfinite_creator&page=nfinite-financial-admin' );
		?>
		<div class="wrap nfinite-financial-admin">
			<h1><?php esc_html_e( 'Financial Admin + Creator Statements', 'nfinite-creators' ); ?></h1>
			<p><?php esc_html_e( 'Operational view of the unified Creator Earnings ledger. Statements are snapshots of ledger data, not a second accounting system.', 'nfinite-creators' ); ?></p>
			<?php self::notice(); ?>
			<style>
			.nfinite-fin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0}.nfinite-fin-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px}.nfinite-fin-card span{display:block;color:#646970;font-size:12px;text-transform:uppercase}.nfinite-fin-card strong{display:block;font-size:22px;margin-top:4px}.nfinite-fin-tools{background:#fff;border:1px solid #dcdcde;padding:16px;margin:16px 0}.nfinite-fin-table td small{display:block;color:#646970}.nfinite-fin-badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#f0f0f1;font-size:11px}.nfinite-fin-badge--hold{background:#fcf0f1;color:#8a2424}.nfinite-fin-actions{display:flex;gap:6px;flex-wrap:wrap}.nfinite-fin-form-row{display:flex;gap:10px;align-items:end;flex-wrap:wrap}.nfinite-fin-form-row label{display:flex;flex-direction:column;gap:4px}.nfinite-fin-details{margin-top:24px}.nfinite-fin-details summary{cursor:pointer;font-weight:600}.nfinite-fin-source{display:inline-block;margin:2px 6px 2px 0;padding:2px 6px;background:#f6f7f7;border-radius:4px}
			</style>

			<div class="nfinite-fin-tools"><form method="get" class="nfinite-fin-form-row"><input type="hidden" name="post_type" value="nfinite_creator"><input type="hidden" name="page" value="nfinite-financial-admin"><label><?php esc_html_e( 'Accounting period', 'nfinite-creators' ); ?><input type="month" name="period" value="<?php echo esc_attr( $period ); ?>"></label><?php submit_button( __( 'View Period', 'nfinite-creators' ), 'secondary', '', false ); ?></form></div>

			<div class="nfinite-fin-grid">
				<?php foreach ( array( 'gross'=>'Gross revenue','fee'=>'Platform retained','finalized'=>'Finalized creator earnings','provisional'=>'Provisional','paid'=>'Paid / transferred','payable'=>'Currently payable','reversed'=>'Reversed / refunded' ) as $key=>$label ) : ?>
				<div class="nfinite-fin-card"><span><?php echo esc_html( $label ); ?></span><strong><?php echo wp_kses_post( self::money( $totals[ $key ], $settings['default_currency'] ) ); ?></strong></div>
				<?php endforeach; ?>
			</div>

			<div class="nfinite-fin-tools">
				<h2><?php esc_html_e( 'Period Close', 'nfinite-creators' ); ?></h2>
				<p><?php echo esc_html( sprintf( __( '%1$s creator statement snapshots currently exist for %2$s.', 'nfinite-creators' ), number_format_i18n( $statement_count ), $period ) ); ?></p>
				<div class="nfinite-fin-actions">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'nfinite_financial_close_' . $period ); ?><input type="hidden" name="action" value="nfinite_financial_close_period"><input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>"><?php submit_button( __( 'Close / Refresh Period Statements', 'nfinite-creators' ), 'primary', '', false ); ?></form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Reopen and remove finalized statement snapshots for this period? The underlying ledger is not deleted.');"><?php wp_nonce_field( 'nfinite_financial_reopen_' . $period ); ?><input type="hidden" name="action" value="nfinite_financial_reopen_period"><input type="hidden" name="period" value="<?php echo esc_attr( $period ); ?>"><?php submit_button( __( 'Reopen Period', 'nfinite-creators' ), 'secondary', '', false ); ?></form>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=nfinite_financial_export_period&period=' . rawurlencode( $period ) ), 'nfinite_financial_export_period_' . $period ) ); ?>"><?php esc_html_e( 'Export Period CSV', 'nfinite-creators' ); ?></a>
				</div>
			</div>

			<h2><?php esc_html_e( 'Creator Balances', 'nfinite-creators' ); ?></h2>
			<table class="widefat striped nfinite-fin-table"><thead><tr><th>Creator</th><th>Gross</th><th>Creator Earnings</th><th>Provisional</th><th>Paid</th><th>Payable</th><th>Payout State</th><th>Statement</th></tr></thead><tbody>
			<?php if ( ! $summary ) : ?><tr><td colspan="8"><?php esc_html_e( 'No ledger activity exists for this period.', 'nfinite-creators' ); ?></td></tr><?php endif; ?>
			<?php foreach ( $summary as $s ) : $hold=self::creator_hold_state($s['creator_id']); $eligible=( $s['payable'] >= (int)$settings['minimum_payout_minor'] && ! $hold['hold'] ); ?>
			<tr><td><strong><?php echo esc_html( self::creator_name( $s['creator_id'] ) ); ?></strong><small>#<?php echo esc_html( $s['creator_id'] ); ?> · <?php echo esc_html( $s['currency'] ); ?></small><?php foreach($s['sources'] as $type=>$src): ?><span class="nfinite-fin-source"><?php echo esc_html(self::source_label($type)); ?> × <?php echo esc_html($src['count']); ?></span><?php endforeach; ?></td>
			<td><?php echo wp_kses_post(self::money($s['gross'],$s['currency'])); ?></td><td><?php echo wp_kses_post(self::money(max(0,$s['creator']-$s['reversed']),$s['currency'])); ?></td><td><?php echo wp_kses_post(self::money($s['provisional'],$s['currency'])); ?></td><td><?php echo wp_kses_post(self::money($s['paid'],$s['currency'])); ?></td><td><strong><?php echo wp_kses_post(self::money($s['payable'],$s['currency'])); ?></strong></td>
			<td><?php if($hold['hold']): ?><span class="nfinite-fin-badge nfinite-fin-badge--hold">On hold</span><small><?php echo esc_html($hold['reason']); ?></small><?php elseif($eligible): ?><span class="nfinite-fin-badge">Eligible</span><?php else: ?><span class="nfinite-fin-badge">Below threshold</span><?php endif; ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:6px"><?php wp_nonce_field('nfinite_financial_hold_'.$s['creator_id']); ?><input type="hidden" name="action" value="nfinite_financial_creator_hold"><input type="hidden" name="creator_id" value="<?php echo esc_attr($s['creator_id']); ?>"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><input type="hidden" name="hold" value="<?php echo $hold['hold']?'0':'1'; ?>"><input type="text" name="reason" placeholder="Hold reason" value="<?php echo esc_attr($hold['reason']); ?>" style="max-width:130px"><?php submit_button($hold['hold']?'Release Hold':'Place Hold','small','',false); ?></form></td>
			<td><a class="button button-small" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=nfinite_financial_export_statement&creator_id='.$s['creator_id'].'&period='.rawurlencode($period)),'nfinite_financial_export_statement_'.$s['creator_id'].'_'.$period)); ?>">CSV</a></td></tr>
			<?php endforeach; ?></tbody></table>

			<div class="nfinite-fin-tools nfinite-fin-details"><h2><?php esc_html_e( 'Manual Adjustment', 'nfinite-creators' ); ?></h2><p><?php esc_html_e( 'Use this for an auditable creator credit. Debits/refunds should be handled as reversals against the original ledger entry.', 'nfinite-creators' ); ?></p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nfinite-fin-form-row"><?php wp_nonce_field('nfinite_financial_adjustment'); ?><input type="hidden" name="action" value="nfinite_financial_adjustment"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><label>Creator ID<input type="number" min="1" required name="creator_id"></label><label>Amount (<?php echo esc_html($settings['default_currency']); ?>)<input type="number" min="0.01" step="0.01" required name="amount"></label><label>Reason<input type="text" required name="reason" size="34"></label><?php submit_button(__('Add Finalized Credit','nfinite-creators'),'secondary','',false); ?></form></div>

			<div class="nfinite-fin-tools"><h2><?php esc_html_e( 'Ledger Reversal', 'nfinite-creators' ); ?></h2><p><?php esc_html_e( 'Reverse all or part of a specific ledger entry. If the entry has already transferred through Stripe, Nfinite uses the existing Stripe transfer-reversal flow.', 'nfinite-creators' ); ?></p>
			<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nfinite-fin-form-row" onsubmit="return confirm('Apply this reversal?');"><?php wp_nonce_field('nfinite_financial_reverse'); ?><input type="hidden" name="action" value="nfinite_financial_reverse_entry"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><label>Ledger Entry ID<input type="number" min="1" required name="entry_id"></label><label>Amount<input type="number" min="0.01" step="0.01" required name="amount"></label><label>Reason<input type="text" required name="reason" size="34"></label><?php submit_button(__('Apply Reversal','nfinite-creators'),'secondary','',false); ?></form></div>

			<div class="nfinite-fin-tools"><h2><?php esc_html_e( 'Financial Settings', 'nfinite-creators' ); ?></h2><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nfinite-fin-form-row"><?php wp_nonce_field('nfinite_financial_settings'); ?><input type="hidden" name="action" value="nfinite_financial_save_settings"><input type="hidden" name="period" value="<?php echo esc_attr($period); ?>"><label>Minimum payout threshold<input type="number" min="0" step="0.01" name="minimum_payout" value="<?php echo esc_attr(Nfinite_Creators_Payments::from_minor((int)$settings['minimum_payout_minor'],$settings['default_currency'])); ?>"></label><label>Currency<input type="text" maxlength="3" name="currency" value="<?php echo esc_attr($settings['default_currency']); ?>"></label><label>Statement note<input type="text" size="45" name="statement_note" value="<?php echo esc_attr($settings['statement_note']); ?>"></label><?php submit_button(__('Save Settings','nfinite-creators'),'secondary','',false); ?></form></div>
		</div>
		<?php
	}

	private static function notice() {
		if ( empty( $_GET['nfinite_finance_notice'] ) ) { return; }
		$code = sanitize_key( wp_unslash( $_GET['nfinite_finance_notice'] ) );
		$messages = array(
			'closed'=>'Period statements were closed/refreshed.', 'reopened'=>'Period statement snapshots were removed; ledger entries were untouched.',
			'adjusted'=>'Creator adjustment credit recorded.', 'reversed'=>'Ledger reversal recorded.', 'held'=>'Creator payout hold updated.', 'settings'=>'Financial settings saved.',
			'error'=>'The requested financial operation could not be completed.',
		);
		if ( isset( $messages[$code] ) ) { echo '<div class="notice '.('error'===$code?'notice-error':'notice-success').' is-dismissible"><p>'.esc_html($messages[$code]).'</p></div>'; }
	}

	private static function redirect( $period, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'post_type'=>'nfinite_creator','page'=>'nfinite-financial-admin','period'=>$period,'nfinite_finance_notice'=>$notice ), admin_url('edit.php') ) ); exit;
	}

	public static function save_settings() {
		if ( ! current_user_can('manage_options') ) { wp_die('Forbidden'); }
		check_admin_referer('nfinite_financial_settings');
		$period = isset($_POST['period'])?sanitize_text_field(wp_unslash($_POST['period'])):gmdate('Y-m');
		$currency = isset($_POST['currency'])?strtoupper(substr(sanitize_text_field(wp_unslash($_POST['currency'])),0,3)):'USD';
		$amount = isset($_POST['minimum_payout'])?(float)wp_unslash($_POST['minimum_payout']):25;
		update_option(self::SETTINGS_OPTION,array('minimum_payout_minor'=>Nfinite_Creators_Payments::to_minor(max(0,$amount),$currency),'default_currency'=>$currency,'statement_note'=>sanitize_text_field(wp_unslash($_POST['statement_note']??''))),false);
		self::redirect($period,'settings');
	}

	public static function close_period() {
		if ( ! current_user_can('manage_options') ) { wp_die('Forbidden'); }
		$period=sanitize_text_field(wp_unslash($_POST['period']??'')); check_admin_referer('nfinite_financial_close_'.$period);
		if(!preg_match('/^\d{4}-\d{2}$/',$period)){self::redirect(gmdate('Y-m'),'error');}
		global $wpdb; $table=self::table_name(); $now=current_time('mysql',true); $user=get_current_user_id();
		foreach(self::summarize_rows(self::period_rows($period)) as $s){
			$key=$period.':'.$s['creator_id'].':'.$s['currency'];
			$snapshot=array('sources'=>$s['sources'],'entry_ids'=>array_map(function($r){return absint($r['id']);},$s['entries']),'generated_at'=>$now);
			$data=array('statement_key'=>$key,'creator_id'=>$s['creator_id'],'accounting_period'=>$period,'currency'=>$s['currency'],'gross_amount'=>$s['gross'],'platform_fee_amount'=>$s['platform_fee'],'creator_amount'=>$s['creator'],'reversed_amount'=>$s['reversed'],'provisional_amount'=>$s['provisional'],'finalized_amount'=>$s['finalized'],'paid_amount'=>$s['paid'],'payable_amount'=>$s['payable'],'entry_count'=>$s['count'],'status'=>'finalized','snapshot'=>wp_json_encode($snapshot),'closed_by'=>$user,'closed_at'=>$now,'updated_at'=>$now);
			$id=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE statement_key=%s LIMIT 1",$key)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if($id){$wpdb->update($table,$data,array('id'=>absint($id)));}else{$wpdb->insert($table,$data);}
		}
		self::redirect($period,'closed');
	}

	public static function reopen_period() {
		if(!current_user_can('manage_options')){wp_die('Forbidden');}
		$period=sanitize_text_field(wp_unslash($_POST['period']??''));check_admin_referer('nfinite_financial_reopen_'.$period);global $wpdb;$wpdb->delete(self::table_name(),array('accounting_period'=>$period));self::redirect($period,'reopened');
	}

	public static function create_adjustment() {
		if(!current_user_can('manage_options')){wp_die('Forbidden');}check_admin_referer('nfinite_financial_adjustment');
		$period=sanitize_text_field(wp_unslash($_POST['period']??gmdate('Y-m')));$creator=absint($_POST['creator_id']??0);$reason=sanitize_text_field(wp_unslash($_POST['reason']??''));$settings=self::settings();$major=(float)wp_unslash($_POST['amount']??0);$minor=Nfinite_Creators_Payments::to_minor($major,$settings['default_currency']);
		if(!$creator||$minor<=0||!$reason){self::redirect($period,'error');}
		$result=Nfinite_Creators_Payments::record_earning(array('entry_key'=>'admin-adjustment:'.$creator.':'.time().':'.wp_generate_password(6,false,false),'creator_id'=>$creator,'source_type'=>'adjustment','source_id'=>'admin:'.get_current_user_id(),'source_label'=>$reason,'earning_kind'=>'adjustment','accounting_period'=>$period,'currency'=>$settings['default_currency'],'eligible_amount'=>$minor,'platform_fee_amount'=>0,'creator_amount'=>$minor,'status'=>'finalized','earning_status'=>'finalized','payout_status'=>'not_scheduled','is_provisional'=>0,'finalized_at'=>current_time('mysql',true)));
		self::redirect($period,is_wp_error($result)?'error':'adjusted');
	}

	public static function reverse_entry() {
		if(!current_user_can('manage_options')){wp_die('Forbidden');}check_admin_referer('nfinite_financial_reverse');
		$period=sanitize_text_field(wp_unslash($_POST['period']??gmdate('Y-m')));$id=absint($_POST['entry_id']??0);$reason=sanitize_text_field(wp_unslash($_POST['reason']??''));$row=Nfinite_Creators_Payments::ledger_entry_by_id($id);
		if(!$row||!$reason){self::redirect($period,'error');}
		$amount=Nfinite_Creators_Payments::to_minor((float)wp_unslash($_POST['amount']??0),$row['currency']?:'USD');$remaining=max(0,(int)$row['creator_amount']-(int)$row['reversed_amount']);$amount=min($remaining,$amount);if($amount<=0){self::redirect($period,'error');}
		if(!empty($row['transfer_id'])){$result=Nfinite_Creators_Payments::reverse_transfer($row,$amount,'admin_financial:'.$reason);if(is_wp_error($result)){self::redirect($period,'error');}}
		else{$new=(int)$row['reversed_amount']+$amount;$status=$new>=(int)$row['creator_amount']?'reversed':'partially_reversed';Nfinite_Creators_Payments::update_earning_entry($row['entry_key'],array('reversed_amount'=>$new,'status'=>$status,'payout_status'=>$status,'last_error'=>'Admin reversal: '.$reason));}
		self::redirect($period,'reversed');
	}

	public static function creator_hold() {
		if(!current_user_can('manage_options')){wp_die('Forbidden');}$creator=absint($_POST['creator_id']??0);check_admin_referer('nfinite_financial_hold_'.$creator);$period=sanitize_text_field(wp_unslash($_POST['period']??gmdate('Y-m')));if(!$creator){self::redirect($period,'error');}$hold=!empty($_POST['hold'])?'1':'0';update_post_meta($creator,self::META_PAYOUT_HOLD,$hold);update_post_meta($creator,self::META_PAYOUT_HOLD_REASON,$hold?sanitize_text_field(wp_unslash($_POST['reason']??'')):'');self::redirect($period,'held');
	}

	public static function statement_count( $period ) { global $wpdb; return (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.self::table_name().' WHERE accounting_period=%s',$period)); }

	public static function creator_statements( $creator_id, $limit=24 ) { global $wpdb; return $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table_name().' WHERE creator_id=%d ORDER BY accounting_period DESC LIMIT %d',absint($creator_id),max(1,absint($limit))),ARRAY_A); }

	private static function export_headers( $filename ) { nocache_headers(); header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="'.sanitize_file_name($filename).'"'); }

	public static function export_statement() {
		if(!is_user_logged_in()){auth_redirect();}$creator=absint($_GET['creator_id']??0);$period=sanitize_text_field(wp_unslash($_GET['period']??''));$creator_author=(int)get_post_field('post_author',$creator);if(!current_user_can('manage_options')&&get_current_user_id()!==$creator_author){wp_die('Forbidden');}check_admin_referer('nfinite_financial_export_statement_'.$creator.'_'.$period);
		$rows=array_values(array_filter(self::period_rows($period),function($r)use($creator){return absint($r['creator_id'])===$creator;}));self::export_headers('pairofdice-statement-'.$creator.'-'.$period.'.csv');$out=fopen('php://output','w');fputcsv($out,array('PairOfDice Media Creator Statement',$period,self::creator_name($creator)));fputcsv($out,array('Entry ID','Date','Source','Description','Gross','Platform Fee','Creator Earnings','Reversed','Net','Earning Status','Payout Status','Transfer ID'));
		foreach($rows as $r){$currency=$r['currency']?:'USD';fputcsv($out,array($r['id'],$r['created_at'],self::source_label($r['source_type']),$r['source_label'],Nfinite_Creators_Payments::from_minor($r['eligible_amount'],$currency),Nfinite_Creators_Payments::from_minor($r['platform_fee_amount'],$currency),Nfinite_Creators_Payments::from_minor($r['creator_amount'],$currency),Nfinite_Creators_Payments::from_minor($r['reversed_amount'],$currency),Nfinite_Creators_Payments::from_minor(max(0,$r['creator_amount']-$r['reversed_amount']),$currency),$r['earning_status'],$r['payout_status'],$r['transfer_id']));}fclose($out);exit;
	}

	public static function export_period() {
		if(!current_user_can('manage_options')){wp_die('Forbidden');}$period=sanitize_text_field(wp_unslash($_GET['period']??''));check_admin_referer('nfinite_financial_export_period_'.$period);$summary=self::summarize_rows(self::period_rows($period));self::export_headers('pairofdice-financial-'.$period.'.csv');$out=fopen('php://output','w');fputcsv($out,array('Creator ID','Creator','Currency','Gross','Platform Fee','Creator Earnings','Reversed','Provisional','Finalized','Paid','Payable','Payout Hold'));
		foreach($summary as $s){$c=$s['currency'];$hold=self::creator_hold_state($s['creator_id']);fputcsv($out,array($s['creator_id'],self::creator_name($s['creator_id']),$c,Nfinite_Creators_Payments::from_minor($s['gross'],$c),Nfinite_Creators_Payments::from_minor($s['platform_fee'],$c),Nfinite_Creators_Payments::from_minor($s['creator'],$c),Nfinite_Creators_Payments::from_minor($s['reversed'],$c),Nfinite_Creators_Payments::from_minor($s['provisional'],$c),Nfinite_Creators_Payments::from_minor($s['finalized'],$c),Nfinite_Creators_Payments::from_minor($s['paid'],$c),Nfinite_Creators_Payments::from_minor($s['payable'],$c),$hold['hold']?'Yes':'No'));}fclose($out);exit;
	}

	public static function creator_statements_shortcode( $atts ) {
		if(!is_user_logged_in()){return '<div class="nfinite-studio-empty"><strong>'.esc_html__('Sign in to view statements.','nfinite-creators').'</strong></div>';}
		$a=shortcode_atts(array('creator_id'=>0),$atts,'nfinite_creator_statements');$creator=absint($a['creator_id']);if(!$creator){$creator=absint(get_user_meta(get_current_user_id(),'_nfinite_creator_profile_id',true));}if(!$creator&&class_exists('Nfinite_Creators_Publishing')){$creator=absint(Nfinite_Creators_Publishing::current_user_creator_id());}if(!$creator){return '';}$author=(int)get_post_field('post_author',$creator);if(get_current_user_id()!==$author&&!current_user_can('manage_options')){return '';}$rows=self::creator_statements($creator);ob_start();echo '<div class="nfinite-creator-statements"><h3>'.esc_html__('Creator Statements','nfinite-creators').'</h3>';if(!$rows){echo '<p>'.esc_html__('No finalized statements are available yet.','nfinite-creators').'</p>';}else{echo '<div class="nfinite-analytics-finance-list">';foreach($rows as $r){$url=wp_nonce_url(admin_url('admin-post.php?action=nfinite_financial_export_statement&creator_id='.$creator.'&period='.rawurlencode($r['accounting_period'])),'nfinite_financial_export_statement_'.$creator.'_'.$r['accounting_period']);echo '<div><span><strong>'.esc_html($r['accounting_period']).'</strong><small>'.esc_html(ucfirst($r['status'])).' · '.esc_html($r['entry_count']).' entries</small></span><span><strong>'.wp_kses_post(self::money($r['finalized_amount'],$r['currency'])).'</strong><small><a href="'.esc_url($url).'">'.esc_html__('Download CSV','nfinite-creators').'</a></small></span></div>'; }echo '</div>';}echo '</div>';return ob_get_clean();
	}
}
