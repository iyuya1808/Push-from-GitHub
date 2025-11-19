<?php
/**
 * ログ機能クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ログ機能クラス
 */
class GitHub_Push_Logger {
	
	/**
	 * インスタンス
	 */
	private static $instance = null;
	
	/**
	 * シングルトンインスタンスを取得
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * コンストラクタ
	 */
	private function __construct() {
	}
	
	/**
	 * ログを記録
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $action アクション
	 * @param string $status ステータス
	 * @param string $message メッセージ
	 * @param string $version バージョン（オプション）
	 */
	public function log( $plugin_id, $action, $status, $message, $version = '' ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'github_push_logs';
		
		// テーブルが存在するか確認
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			// テーブルが存在しない場合は作成を試みる
			$this->create_table_if_not_exists();
		}
		
		$result = $wpdb->insert(
			$table_name,
			array(
				'plugin_id' => $plugin_id,
				'action' => $action,
				'status' => $status,
				'message' => $message,
				'version' => $version,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		
		// エラーが発生した場合はログに記録（デバッグ用）
		if ( $result === false ) {
			error_log( 'GitHub Push: Failed to insert log. Error: ' . $wpdb->last_error );
		}
	}
	
	/**
	 * テーブルが存在しない場合に作成
	 */
	private function create_table_if_not_exists() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $wpdb->prefix . 'github_push_logs';
		
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			plugin_id varchar(255) NOT NULL,
			action varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			message text,
			version varchar(50),
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY plugin_id (plugin_id),
			KEY created_at (created_at)
		) $charset_collate;";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/**
	 * ログを取得
	 *
	 * @param string $plugin_id プラグインID（オプション）
	 * @param int $limit 取得件数
	 * @param int $offset オフセット
	 * @return array ログ配列
	 */
	public function get_logs( $plugin_id = '', $limit = 50, $offset = 0 ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'github_push_logs';
		
		// テーブルが存在するか確認
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			// テーブルが存在しない場合は空配列を返す
			return array();
		}
		
		$where = '';
		$params = array();
		
		if ( ! empty( $plugin_id ) ) {
			$where = 'WHERE plugin_id = %s';
			$params[] = $plugin_id;
		}
		
		// LIMITとOFFSETは常にパラメータとして追加
		$params[] = $limit;
		$params[] = $offset;
		
		$query = "SELECT * FROM $table_name $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
		
		if ( ! empty( $params ) ) {
			$prepared_query = $wpdb->prepare( $query, $params );
		} else {
			// この分岐は実際には到達しないが、念のため
			$prepared_query = $wpdb->prepare( $query, $limit, $offset );
		}
		
		$results = $wpdb->get_results( $prepared_query, ARRAY_A );
		
		// エラーが発生した場合はログに記録
		if ( $wpdb->last_error ) {
			error_log( 'GitHub Push: Failed to get logs. Error: ' . $wpdb->last_error . ' Query: ' . $prepared_query );
		}
		
		return $results ? $results : array();
	}
	
	/**
	 * ログの総数を取得
	 *
	 * @param string $plugin_id プラグインID（オプション）
	 * @return int ログの総数
	 */
	public function get_log_count( $plugin_id = '' ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'github_push_logs';
		
		// テーブルが存在するか確認
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			return 0;
		}
		
		$where = '';
		
		if ( ! empty( $plugin_id ) ) {
			$where = $wpdb->prepare( 'WHERE plugin_id = %s', $plugin_id );
		}
		
		$query = "SELECT COUNT(*) FROM $table_name $where";
		
		return (int) $wpdb->get_var( $query );
	}
	
	/**
	 * ログ画面を表示
	 */
	public function render_logs_page() {
		global $wpdb;
		
		// テーブルが存在しない場合は作成
		$table_name = $wpdb->prefix . 'github_push_logs';
		$table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name );
		
		if ( ! $table_exists ) {
			$this->create_table_if_not_exists();
			// 再確認
			$table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name );
		}
		
		$plugin_id = isset( $_GET['plugin_id'] ) ? sanitize_text_field( $_GET['plugin_id'] ) : '';
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = 50;
		$offset = ( $paged - 1 ) * $per_page;
		
		$logs = $this->get_logs( $plugin_id, $per_page, $offset );
		$total_logs = $this->get_log_count( $plugin_id );
		$total_pages = ceil( $total_logs / $per_page );
		
		$plugins = get_option( 'github_push_plugins', array() );
		
		// デバッグ情報（開発環境用）
		$debug_info = '';
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$debug_info = sprintf(
				'<div class="notice notice-info"><p>%s: %s=%s, %s=%d, %s=%d, %s=%s</p></div>',
				__( 'デバッグ情報', 'github-push' ),
				__( 'テーブル存在', 'github-push' ),
				$table_exists ? 'true' : 'false',
				__( 'ログ件数', 'github-push' ),
				$total_logs,
				__( '取得件数', 'github-push' ),
				count( $logs ),
				__( 'エラー', 'github-push' ),
				$wpdb->last_error ? esc_html( $wpdb->last_error ) : __( 'なし', 'github-push' )
			);
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'ログ', 'github-push' ); ?></h1>
			<?php echo $debug_info; ?>
			
			<div class="github-push-logs-filter">
				<form method="get" action="">
					<input type="hidden" name="page" value="github-push-logs">
					<select name="plugin_id">
						<option value=""><?php echo esc_html__( 'すべてのプラグイン', 'github-push' ); ?></option>
						<?php foreach ( $plugins as $id => $plugin ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $plugin_id, $id ); ?>>
								<?php echo esc_html( $plugin['plugin_name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="submit" class="button" value="<?php echo esc_attr__( 'フィルター', 'github-push' ); ?>">
				</form>
			</div>
			
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( '日時', 'github-push' ); ?></th>
						<th><?php echo esc_html__( 'プラグイン', 'github-push' ); ?></th>
						<th><?php echo esc_html__( 'アクション', 'github-push' ); ?></th>
						<th><?php echo esc_html__( 'ステータス', 'github-push' ); ?></th>
						<th><?php echo esc_html__( 'バージョン', 'github-push' ); ?></th>
						<th><?php echo esc_html__( 'メッセージ', 'github-push' ); ?></th>
						<th><?php echo esc_html__( '操作', 'github-push' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $logs ) ) : ?>
						<tr>
							<td colspan="7"><?php echo esc_html__( 'ログがありません', 'github-push' ); ?></td>
						</tr>
					<?php else : ?>
						<?php
						$backups = get_option( 'github_push_backups', array() );
						foreach ( $logs as $log ) :
							// 更新成功のログで、バージョンがあり、バックアップが存在する場合のみロールバックボタンを表示
							$can_rollback = false;
							$backup_path = '';
							
							if ( $log['action'] === 'update' && $log['status'] === 'success' && ! empty( $log['version'] ) ) {
								// このログのバージョンに対応するバックアップを探す
								// 更新前のバージョンがバックアップに保存されているので、そのバージョンと一致するバックアップを探す
								if ( isset( $backups[ $log['plugin_id'] ] ) && is_array( $backups[ $log['plugin_id'] ] ) ) {
									// ログの日時より前のバックアップを探す（更新前に作成されたバックアップ）
									$log_time = strtotime( $log['created_at'] );
									foreach ( $backups[ $log['plugin_id'] ] as $backup ) {
										$backup_time = strtotime( $backup['created_at'] );
										// ログの日時より前のバックアップで、ファイルが存在する場合
										if ( $backup_time <= $log_time && file_exists( $backup['path'] ) ) {
											$can_rollback = true;
											$backup_path = $backup['path'];
											// 最新のバックアップを使用（複数ある場合）
											break;
										}
									}
								}
							}
							?>
							<tr>
								<td><?php echo esc_html( $log['created_at'] ); ?></td>
								<td>
									<?php
									if ( isset( $plugins[ $log['plugin_id'] ] ) ) {
										echo esc_html( $plugins[ $log['plugin_id'] ]['plugin_name'] );
									} else {
										echo esc_html( $log['plugin_id'] );
									}
									?>
								</td>
								<td><?php echo esc_html( $this->translate_action( $log['action'] ) ); ?></td>
								<td>
									<span class="status status-<?php echo esc_attr( $log['status'] ); ?>">
										<?php echo esc_html( $this->translate_status( $log['status'] ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log['version'] ); ?></td>
								<td><?php echo esc_html( $this->translate_log_message( $log['message'] ) ); ?></td>
								<td>
									<?php if ( $can_rollback ) : ?>
										<button class="button button-small rollback-from-log" 
												data-plugin-id="<?php echo esc_attr( $log['plugin_id'] ); ?>"
												data-version="<?php echo esc_attr( $log['version'] ); ?>"
												data-backup-path="<?php echo esc_attr( $backup_path ); ?>">
											<?php echo esc_html__( 'このバージョンに戻す', 'github-push' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
			
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						$page_links = paginate_links( array(
							'base' => add_query_arg( 'paged', '%#%' ),
							'format' => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total' => $total_pages,
							'current' => $paged,
						) );
						
						echo $page_links;
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * アクション名を翻訳
	 *
	 * @param string $action アクション名
	 * @return string 翻訳されたアクション名
	 */
	private function translate_action( $action ) {
		$actions = array(
			'version_check' => __( 'バージョンチェック', 'github-push' ),
			'update' => __( '更新', 'github-push' ),
			'rollback' => __( 'ロールバック', 'github-push' ),
			'backup' => __( 'バックアップ', 'github-push' ),
		);

		return isset( $actions[ $action ] ) ? $actions[ $action ] : $action;
	}

	/**
	 * ステータス名を翻訳
	 *
	 * @param string $status ステータス名
	 * @return string 翻訳されたステータス名
	 */
	private function translate_status( $status ) {
		$statuses = array(
			'success' => __( '成功', 'github-push' ),
			'error' => __( 'エラー', 'github-push' ),
			'info' => __( '情報', 'github-push' ),
			'warning' => __( '警告', 'github-push' ),
		);

		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}

	/**
	 * ログメッセージを翻訳
	 * データベースに保存されているメッセージが日本語の場合、現在の言語設定に応じて翻訳
	 *
	 * @param string $message ログメッセージ
	 * @return string 翻訳されたメッセージ
	 */
	private function translate_log_message( $message ) {
		// 自動更新チェックのプレフィックスを翻訳
		if ( strpos( $message, '自動更新チェック:' ) === 0 ) {
			$translated_prefix = __( '自動更新チェック:', 'github-push' );
			$rest = trim( substr( $message, strlen( '自動更新チェック:' ) ) );
			// 残りの部分も翻訳を試みる
			$rest_translated = $this->translate_message_content( $rest );
			return $translated_prefix . ' ' . $rest_translated;
		}
		
		// 一般的なメッセージパターンを翻訳
		return $this->translate_message_content( $message );
	}

	/**
	 * メッセージ内容を翻訳
	 *
	 * @param string $message メッセージ
	 * @return string 翻訳されたメッセージ
	 */
	private function translate_message_content( $message ) {
		// 一般的なメッセージパターンを翻訳
		$patterns = array(
			'/^更新が利用可能です。現在: (.+) → 最新: (.+)$/u' => __( '更新が利用可能です。現在: %s → 最新: %s', 'github-push' ),
			'/^更新はありません。現在のバージョン: (.+)$/u' => __( '更新はありません。現在のバージョン: %s', 'github-push' ),
			'/^プラグインを (.+) に更新しました$/u' => __( 'プラグインを %s に更新しました', 'github-push' ),
		);
		
		foreach ( $patterns as $pattern => $translation_template ) {
			if ( preg_match( $pattern, $message, $matches ) ) {
				array_shift( $matches ); // 最初の要素（全体一致）を削除
				return vsprintf( $translation_template, $matches );
			}
		}
		
		// パターンに一致しない場合は、そのまま返す
		// エラーメッセージなどは既に翻訳されている可能性があるため
		return $message;
	}
}

