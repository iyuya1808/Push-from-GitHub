<?php

/**
 * ログ機能クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if (! defined('ABSPATH')) {
	exit;
}

/**
 * ログ機能クラス
 */
class GitHub_Push_Logger
{

	/**
	 * インスタンス
	 */
	private static $instance = null;

	/**
	 * シングルトンインスタンスを取得
	 */
	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {}

	/**
	 * ログを記録
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $action アクション
	 * @param string $status ステータス
	 * @param string $message メッセージ
	 * @param string $version バージョン（オプション）
	 */
	public function log($plugin_id, $action, $status, $message, $version = '')
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'github_push_logs';

		// テーブルが存在するか確認
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
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
				'created_at' => current_time('mysql'),
			),
			array('%s', '%s', '%s', '%s', '%s', '%s')
		);

		// エラーが発生した場合はログに記録（デバッグ用）
		if ($result === false) {
			error_log('GitHub Push: Failed to insert log. Error: ' . $wpdb->last_error);
		}
	}

	/**
	 * テーブルが存在しない場合に作成
	 */
	private function create_table_if_not_exists()
	{
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

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/**
	 * ログを取得
	 *
	 * @param string $plugin_id プラグインID（オプション）
	 * @param int $limit 取得件数
	 * @param int $offset オフセット
	 * @return array ログ配列
	 */
	public function get_logs($plugin_id = '', $limit = 50, $offset = 0)
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'github_push_logs';

		// テーブルが存在するか確認
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			// テーブルが存在しない場合は空配列を返す
			return array();
		}

		$where_clause = '';
		$params = array();

		if (! empty($plugin_id)) {
			$where_clause = 'WHERE plugin_id = %s';
			$params[] = $plugin_id;
		}

		// LIMITとOFFSETは常にパラメータとして追加
		$params[] = $limit;
		$params[] = $offset;

		// テーブル名は安全な値なので直接使用
		$table_name_escaped = esc_sql($table_name);
		$query = "SELECT * FROM `{$table_name_escaped}` {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $wpdb->prepare()で準備済み
		$prepared_query = $wpdb->prepare($query, $params);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prepared_queryは$wpdb->prepare()の結果
		$results = $wpdb->get_results($prepared_query, ARRAY_A);

		// エラーが発生した場合はログに記録
		if ($wpdb->last_error) {
			error_log('GitHub Push: Failed to get logs. Error: ' . $wpdb->last_error . ' Query: ' . $prepared_query);
		}

		return $results ? $results : array();
	}

	/**
	 * ログの総数を取得
	 *
	 * @param string $plugin_id プラグインID（オプション）
	 * @return int ログの総数
	 */
	public function get_log_count($plugin_id = '')
	{
		global $wpdb;

		$table_name = $wpdb->prefix . 'github_push_logs';

		// テーブルが存在するか確認
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			return 0;
		}

		$where_clause = '';
		$params = array();

		if (! empty($plugin_id)) {
			$where_clause = 'WHERE plugin_id = %s';
			$params[] = $plugin_id;
		}

		// テーブル名は安全な値なので直接使用
		$table_name_escaped = esc_sql($table_name);
		$query = "SELECT COUNT(*) FROM `{$table_name_escaped}` {$where_clause}";

		if (! empty($params)) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $wpdb->prepare()で準備済み
			$prepared_query = $wpdb->prepare($query, $params);
		} else {
			$prepared_query = $query;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $prepared_queryは$wpdb->prepare()の結果または安全なクエリ
		return (int) $wpdb->get_var($prepared_query);
	}

	/**
	 * ログ画面を表示
	 */
	public function render_logs_page()
	{
		global $wpdb;

		// テーブルが存在しない場合は作成
		$table_name = $wpdb->prefix . 'github_push_logs';
		$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);

		if (! $table_exists) {
			$this->create_table_if_not_exists();
			// 再確認
			$table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
		}

		$plugin_id = isset($_GET['plugin_id']) ? sanitize_text_field($_GET['plugin_id']) : '';
		$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
		$per_page = 50;
		$offset = ($paged - 1) * $per_page;

		$logs = $this->get_logs($plugin_id, $per_page, $offset);
		$total_logs = $this->get_log_count($plugin_id);
		$total_pages = ceil($total_logs / $per_page);

		$plugins = get_option('github_push_plugins', array());

		// デバッグ情報（開発環境用）
		$debug_info = '';
		if (defined('WP_DEBUG') && WP_DEBUG) {
			$debug_info = sprintf(
				'<div class="notice notice-info"><p>%s: %s=%s, %s=%d, %s=%d, %s=%s</p></div>',
				__('デバッグ情報', 'push-from-github'),
				__('テーブル存在', 'push-from-github'),
				$table_exists ? 'true' : 'false',
				__('ログ件数', 'push-from-github'),
				$total_logs,
				__('取得件数', 'push-from-github'),
				count($logs),
				__('エラー', 'push-from-github'),
				$wpdb->last_error ? esc_html($wpdb->last_error) : __('なし', 'push-from-github')
			);
		}
?>
		<div class="wrap">
			<h1><?php echo esc_html__('ログ', 'push-from-github'); ?></h1>
			<?php echo wp_kses_post($debug_info); ?>

			<div class="github-push-logs-filter">
				<form method="get" action="">
					<input type="hidden" name="page" value="github-push-logs">
					<select name="plugin_id">
						<option value=""><?php echo esc_html__('すべてのプラグイン', 'push-from-github'); ?></option>
						<?php foreach ($plugins as $id => $plugin) : ?>
							<option value="<?php echo esc_attr($id); ?>" <?php selected($plugin_id, $id); ?>>
								<?php echo esc_html($plugin['plugin_name']); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<input type="submit" class="button" value="<?php echo esc_attr__('フィルター', 'push-from-github'); ?>">
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php echo esc_html__('日時', 'push-from-github'); ?></th>
						<th><?php echo esc_html__('プラグイン', 'push-from-github'); ?></th>
						<th><?php echo esc_html__('アクション', 'push-from-github'); ?></th>
						<th><?php echo esc_html__('ステータス', 'push-from-github'); ?></th>
						<th><?php echo esc_html__('バージョン', 'push-from-github'); ?></th>
						<th><?php echo esc_html__('メッセージ', 'push-from-github'); ?></th>
						<th><?php echo esc_html__('操作', 'push-from-github'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($logs)) : ?>
						<tr>
							<td colspan="7"><?php echo esc_html__('ログがありません', 'push-from-github'); ?></td>
						</tr>
					<?php else : ?>
						<?php
						$backups = get_option('github_push_backups', array());
						foreach ($logs as $log) :
							// 更新成功のログで、バージョンがあり、バックアップが存在する場合のみロールバックボタンを表示
							$can_rollback = false;
							$backup_path = '';
							$backup_version = '';

							if ($log['action'] === 'update' && $log['status'] === 'success' && ! empty($log['version'])) {
								// このログのバージョンに対応するバックアップを探す
								// 更新前のバージョンがバックアップに保存されているので、そのバージョンと一致するバックアップを探す
								if (isset($backups[$log['plugin_id']]) && is_array($backups[$log['plugin_id']])) {
									// ログの日時より前のバックアップを探す（更新前に作成されたバックアップ）
									$log_time = strtotime($log['created_at']);
									$candidate_backups = array();

									foreach ($backups[$log['plugin_id']] as $backup) {
										if (! isset($backup['created_at']) || ! isset($backup['path'])) {
											continue;
										}

										$backup_time = strtotime($backup['created_at']);
										// ログの日時より前のバックアップで、ファイルが存在する場合
										if ($backup_time <= $log_time && file_exists($backup['path'])) {
											$candidate_backups[] = $backup;
										}
									}

									// created_at でソート（新しい順）して最新のものを選択
									if (! empty($candidate_backups)) {
										usort($candidate_backups, function ($a, $b) {
											$time_a = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
											$time_b = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
											return $time_b - $time_a;
										});

										$can_rollback = true;
										$backup_path = $candidate_backups[0]['path'];
										// バックアップのバージョン（更新前のバージョン）を取得
										$backup_version = isset($candidate_backups[0]['version']) ? $candidate_backups[0]['version'] : '';
									}
								}
							}
						?>
							<tr>
								<td><?php echo esc_html($log['created_at']); ?></td>
								<td>
									<?php
									if (isset($plugins[$log['plugin_id']])) {
										echo esc_html($plugins[$log['plugin_id']]['plugin_name']);
									} else {
										echo esc_html($log['plugin_id']);
									}
									?>
								</td>
								<td>
									<span class="action action-<?php echo esc_attr($log['action']); ?>">
										<?php echo esc_html($this->translate_action($log['action'])); ?>
									</span>
								</td>
								<td>
									<span class="status status-<?php echo esc_attr($log['status']); ?>">
										<?php echo esc_html($this->translate_status($log['status'])); ?>
									</span>
								</td>
								<td><?php echo esc_html($log['version']); ?></td>
								<td><?php echo esc_html($this->translate_log_message($log['message'])); ?></td>
								<td>
									<?php if ($can_rollback) : ?>
										<button class="button button-small rollback-from-log"
											data-plugin-id="<?php echo esc_attr($log['plugin_id']); ?>"
											data-version="<?php echo esc_attr($backup_version); ?>"
											data-backup-path="<?php echo esc_attr($backup_path); ?>">
											<?php
											if (! empty($backup_version)) {
												// translators: %s: Version number
												printf(esc_html__('バージョン %s に戻す', 'push-from-github'), esc_html($backup_version));
											} else {
												echo esc_html__('このバージョンに戻す', 'push-from-github');
											}
											?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ($total_pages > 1) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						$page_links = paginate_links(array(
							'base' => add_query_arg('paged', '%#%'),
							'format' => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total' => $total_pages,
							'current' => $paged,
						));

						// paginate_links()は安全なHTMLを返すため、エスケープ不要だが、規約に従ってエスケープ
						echo wp_kses_post($page_links);
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
	private function translate_action($action)
	{
		$actions = array(
			'version_check' => __('バージョンチェック', 'push-from-github'),
			'update' => __('更新', 'push-from-github'),
			'rollback' => __('ロールバック', 'push-from-github'),
			'backup' => __('バックアップ', 'push-from-github'),
		);

		return isset($actions[$action]) ? $actions[$action] : $action;
	}

	/**
	 * ステータス名を翻訳
	 *
	 * @param string $status ステータス名
	 * @return string 翻訳されたステータス名
	 */
	private function translate_status($status)
	{
		$statuses = array(
			'success' => __('成功', 'push-from-github'),
			'error' => __('エラー', 'push-from-github'),
			'info' => __('情報', 'push-from-github'),
			'warning' => __('警告', 'push-from-github'),
		);

		return isset($statuses[$status]) ? $statuses[$status] : $status;
	}

	/**
	 * ログメッセージを翻訳
	 * データベースに保存されているメッセージが日本語の場合、現在の言語設定に応じて翻訳
	 *
	 * @param string $message ログメッセージ
	 * @return string 翻訳されたメッセージ
	 */
	private function translate_log_message($message)
	{
		// 自動更新チェックのプレフィックスを翻訳
		if (strpos($message, '自動更新チェック:') === 0) {
			$translated_prefix = __('自動更新チェック:', 'push-from-github');
			$rest = trim(substr($message, strlen('自動更新チェック:')));
			// 残りの部分も翻訳を試みる
			$rest_translated = $this->translate_message_content($rest);
			return $translated_prefix . ' ' . $rest_translated;
		}

		// 一般的なメッセージパターンを翻訳
		return $this->translate_message_content($message);
	}

	/**
	 * メッセージ内容を翻訳
	 *
	 * @param string $message メッセージ
	 * @return string 翻訳されたメッセージ
	 */
	private function translate_message_content($message)
	{
		// 一般的なメッセージパターンを翻訳
		$patterns = array(
			'/^更新が利用可能です。現在: (.+) → 最新: (.+)$/u' =>
			// translators: %1$s: Current version, %2$s: Latest version
			__('更新が利用可能です。現在: %1$s → 最新: %2$s', 'push-from-github'),
			'/^更新はありません。現在のバージョン: (.+)$/u' =>
			// translators: %s: Current version
			__('更新はありません。現在のバージョン: %s', 'push-from-github'),
			'/^プラグインを (.+) に更新しました$/u' =>
			// translators: %s: Version number
			__('プラグインを %s に更新しました', 'push-from-github'),
		);

		foreach ($patterns as $pattern => $translation_template) {
			if (preg_match($pattern, $message, $matches)) {
				array_shift($matches); // 最初の要素（全体一致）を削除
				return vsprintf($translation_template, $matches);
			}
		}

		// パターンに一致しない場合は、そのまま返す
		// エラーメッセージなどは既に翻訳されている可能性があるため
		return $message;
	}
}
