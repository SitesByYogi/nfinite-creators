<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Nfinite_Creators_Identity {
	const META_CLAIM_STATUS        = '_nfinite_creator_claim_status';
	const META_VERIFICATION_STATUS = '_nfinite_creator_verification_status';
	const META_ACCOUNT_CLASS       = '_nfinite_creator_account_class';

	public static function init() {}

	public static function claim_status( $creator_id ) {
		$status = sanitize_key( (string) get_post_meta( absint( $creator_id ), self::META_CLAIM_STATUS, true ) );
		return in_array( $status, array( 'unclaimed', 'claimed' ), true ) ? $status : 'unclaimed';
	}

	public static function verification_status( $creator_id ) {
		$status = sanitize_key( (string) get_post_meta( absint( $creator_id ), self::META_VERIFICATION_STATUS, true ) );
		return in_array( $status, array( 'unverified', 'verified' ), true ) ? $status : 'unverified';
	}

	public static function account_class( $creator_id ) {
		$class = sanitize_key( (string) get_post_meta( absint( $creator_id ), self::META_ACCOUNT_CLASS, true ) );
		return in_array( $class, array( 'creator', 'publisher' ), true ) ? $class : 'creator';
	}

	public static function is_publisher( $creator_id ) {
		return 'publisher' === self::account_class( $creator_id );
	}

	public static function is_verified( $creator_id ) {
		return ! self::is_publisher( $creator_id ) && 'verified' === self::verification_status( $creator_id );
	}

	public static function is_claimed( $creator_id ) {
		return ! self::is_publisher( $creator_id ) && 'claimed' === self::claim_status( $creator_id );
	}

	public static function badge_data( $creator_id ) {
		if ( self::is_publisher( $creator_id ) ) {
			return array(
				'key'   => 'publisher',
				'label' => __( 'PairOfDice Publisher', 'nfinite-creators' ),
				'title' => __( 'Official PairOfDice publishing and editorial account.', 'nfinite-creators' ),
			);
		}
		if ( self::is_verified( $creator_id ) ) {
			return array(
				'key'   => 'verified',
				'label' => __( 'PairOfDice Verified', 'nfinite-creators' ),
				'title' => __( 'Identity or authorized representation has been verified by PairOfDice.', 'nfinite-creators' ),
			);
		}
		if ( self::is_claimed( $creator_id ) ) {
			return array(
				'key'   => 'claimed',
				'label' => __( 'Claimed', 'nfinite-creators' ),
				'title' => __( 'This profile has been claimed by its creator or authorized manager.', 'nfinite-creators' ),
			);
		}
		return array();
	}

	public static function render_badge( $creator_id, $compact = false ) {
		$data = self::badge_data( $creator_id );
		if ( ! $data ) { return ''; }
		$icon = 'publisher' === $data['key'] ? 'P' : '&#10003;';
		$label = $compact && 'publisher' !== $data['key'] ? ( 'verified' === $data['key'] ? __( 'Verified', 'nfinite-creators' ) : __( 'Claimed', 'nfinite-creators' ) ) : $data['label'];
		return sprintf(
			'<span class="nfinite-identity-badge nfinite-identity-badge--%1$s" title="%2$s" aria-label="%3$s"><span class="nfinite-identity-badge__icon" aria-hidden="true">%4$s</span><span>%5$s</span></span>',
			esc_attr( $data['key'] ), esc_attr( $data['title'] ), esc_attr( $data['label'] ), $icon, esc_html( $label )
		);
	}
}
