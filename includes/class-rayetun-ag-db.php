<?php
/**
 * Database table creation and migration.
 *
 * @package AgentGarrison
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rayetun_AG_DB {

	const DB_VERSION            = '1.0';
	const BOT_VISITS_TABLE      = 'rayetun_ag_bot_visits';
	const REFERRALS_TABLE       = 'rayetun_ag_llm_referrals';
	const CITATION_KEYWORDS_TABLE = 'rayetun_ag_citation_keywords';
	const CITATION_RESULTS_TABLE  = 'rayetun_ag_citation_results';
	const CITATION_SCANS_TABLE    = 'rayetun_ag_citation_scans';

	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$visits_table = $wpdb->prefix . self::BOT_VISITS_TABLE;
		$sql_visits   = "CREATE TABLE $visits_table (
			id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			bot_name     varchar(100) NOT NULL DEFAULT '',
			bot_category varchar(50) NOT NULL DEFAULT '',
			page_url     varchar(2083) NOT NULL DEFAULT '',
			user_agent   text NOT NULL,
			ip_address   varchar(45) NOT NULL DEFAULT '',
			spoofed      tinyint(1) NOT NULL DEFAULT 0,
			honeypot     tinyint(1) NOT NULL DEFAULT 0,
			visited_at   datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY bot_name (bot_name),
			KEY visited_at (visited_at)
		) $charset;";

		$referrals_table = $wpdb->prefix . self::REFERRALS_TABLE;
		$sql_referrals   = "CREATE TABLE $referrals_table (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			platform   varchar(50) NOT NULL DEFAULT '',
			page_url   varchar(2083) NOT NULL DEFAULT '',
			post_id    bigint(20) unsigned NOT NULL DEFAULT 0,
			referrer   varchar(2083) NOT NULL DEFAULT '',
			utm_source varchar(200) NOT NULL DEFAULT '',
			visited_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY platform (platform),
			KEY post_id (post_id),
			KEY visited_at (visited_at)
		) $charset;";

		$kw_table  = $wpdb->prefix . self::CITATION_KEYWORDS_TABLE;
		$sql_kw    = "CREATE TABLE $kw_table (
			id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword    varchar(500) NOT NULL DEFAULT '',
			added_at   datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id)
		) $charset;";

		$res_table = $wpdb->prefix . self::CITATION_RESULTS_TABLE;
		$sql_res   = "CREATE TABLE $res_table (
			id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword_id       bigint(20) unsigned NOT NULL DEFAULT 0,
			platform         varchar(50) NOT NULL DEFAULT '',
			cited_url        varchar(2083) NOT NULL DEFAULT '',
			post_id          bigint(20) unsigned NOT NULL DEFAULT 0,
			context_snippet  text NOT NULL,
			confidence_score tinyint(3) unsigned NOT NULL DEFAULT 0,
			is_demo          tinyint(1) NOT NULL DEFAULT 0,
			checked_at       datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY keyword_id (keyword_id),
			KEY platform (platform),
			KEY checked_at (checked_at)
		) $charset;";

		// Closed-loop citation outcomes: one row per question x backend x scan,
		// recording cited/not-cited and the competitor domains the AI cited instead.
		$scans_table = $wpdb->prefix . self::CITATION_SCANS_TABLE;
		$sql_scans   = "CREATE TABLE $scans_table (
			id                 bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword_id         bigint(20) unsigned NOT NULL DEFAULT 0,
			platform           varchar(50) NOT NULL DEFAULT '',
			cited              tinyint(1) NOT NULL DEFAULT 0,
			competitor_domains text NOT NULL,
			answer_excerpt     text NOT NULL,
			is_demo            tinyint(1) NOT NULL DEFAULT 0,
			scanned_at         datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY keyword_id (keyword_id),
			KEY scanned_at (scanned_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_visits );
		dbDelta( $sql_referrals );
		dbDelta( $sql_kw );
		dbDelta( $sql_res );
		dbDelta( $sql_scans );

		update_option( 'rayetun_ag_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		$installed = get_option( 'rayetun_ag_db_version', '0' );
		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			// Flag a one-time rewrite flush so /llms.txt and the honeypot trap
			// resolve after an upgrade without the user re-saving permalinks.
			update_option( 'rayetun_ag_llms_flush', 1 );
			update_option( 'rayetun_ag_honeypot_flush', 1 );
		}
	}

	/**
	 * Neutralise CSV/formula injection for a single exported cell.
	 *
	 * Stored fields such as page URLs and referrers are influenced by unauthenticated
	 * visitors. A value beginning with =, +, -, @, or a control character is treated as
	 * a formula by Excel/Sheets/LibreOffice. Prefixing it with a single quote forces the
	 * spreadsheet to render it as literal text. See OWASP "CSV Injection".
	 *
	 * @param mixed $value Raw cell value.
	 * @return string Safe cell value.
	 */
	public static function csv_safe_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}
}
