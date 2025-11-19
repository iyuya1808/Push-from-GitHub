<?php

/**
 * Plugin Name: WP Push from GitHub
 * Plugin URI: https://github.com/your-username/github-push
 * Description: 非公開GitHubリポジトリで管理されているWordPressプラグインを自動的に導入・更新するプラグイン
 * Version: 1.1.4
 * Author: Technophere
 * Author URI: https://technophere.codm
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: github-push
 * Domain Path: /languages
 */

// 直接アクセスを防ぐ
if (! defined('ABSPATH')) {
	exit;
}

// プラグインの定数定義
define('GITHUB_PUSH_VERSION', '1.0.0');
define('GITHUB_PUSH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GITHUB_PUSH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GITHUB_PUSH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * メインクラス
 */
class GitHub_Push
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
	private function __construct()
	{
		$this->init();
	}

	/**
	 * 初期化
	 */
	private function init()
	{
		// クラスの読み込み
		$this->load_dependencies();

		// フックの登録
		$this->register_hooks();

		// 多言語対応
		$this->load_textdomain();
	}

	/**
	 * 依存クラスの読み込み
	 */
	private function load_dependencies()
	{
		require_once GITHUB_PUSH_PLUGIN_DIR . 'includes/class-github-api.php';
		require_once GITHUB_PUSH_PLUGIN_DIR . 'includes/class-updater.php';
		require_once GITHUB_PUSH_PLUGIN_DIR . 'includes/class-rollback.php';
		require_once GITHUB_PUSH_PLUGIN_DIR . 'includes/class-notifications.php';
		require_once GITHUB_PUSH_PLUGIN_DIR . 'includes/class-logger.php';

		if (is_admin()) {
			require_once GITHUB_PUSH_PLUGIN_DIR . 'admin/class-settings.php';
		}
	}

	/**
	 * フックの登録
	 */
	private function register_hooks()
	{
		// プラグイン有効化時の処理
		register_activation_hook(__FILE__, array($this, 'activate'));

		// プラグイン無効化時の処理
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));

		// 管理画面の初期化
		if (is_admin()) {
			add_action('admin_init', array($this, 'admin_init'));
			add_action('admin_menu', array($this, 'add_admin_menu'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
			add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
		}

		// Ajax処理
		add_action('wp_ajax_github_push_check_update', array($this, 'ajax_check_update'));
		add_action('wp_ajax_github_push_update_plugin', array($this, 'ajax_update_plugin'));
		add_action('wp_ajax_github_push_rollback', array($this, 'ajax_rollback'));
		add_action('wp_ajax_github_push_get_repo_info', array($this, 'ajax_get_repo_info'));
	}

	/**
	 * プラグイン有効化時の処理
	 */
	public function activate()
	{
		// データベーステーブルの作成（必要に応じて）
		$this->create_tables();

		// デフォルトオプションの設定
		$this->set_default_options();
	}

	/**
	 * プラグイン無効化時の処理
	 */
	public function deactivate()
	{
		// プラグイン無効化時の処理（必要に応じて追加）
	}

	/**
	 * 管理画面の初期化
	 */
	public function admin_init()
	{
		// 設定画面の初期化
		if (class_exists('GitHub_Push_Settings')) {
			GitHub_Push_Settings::get_instance();
		}

		// 通知の表示
		if (class_exists('GitHub_Push_Notifications')) {
			$notifications = GitHub_Push_Notifications::get_instance();
			add_action('admin_notices', array($notifications, 'display_admin_notices'));
		}
	}

	/**
	 * 管理メニューの追加
	 */
	public function add_admin_menu()
	{
		add_menu_page(
			__('WP Push from GitHub', 'github-push'),
			__('WPGP', 'github-push'),
			'manage_options',
			'github-push',
			array($this, 'render_settings_page'),
			'dashicons-update',
			30
		);

		add_submenu_page(
			'github-push',
			__('GitHub設定', 'github-push'),
			__('GitHub設定', 'github-push'),
			'manage_options',
			'github-push',
			array($this, 'render_settings_page')
		);

		add_submenu_page(
			'github-push',
			__('ログ', 'github-push'),
			__('ログ', 'github-push'),
			'manage_options',
			'github-push-logs',
			array($this, 'render_logs_page')
		);

		add_submenu_page(
			'github-push',
			__('一般設定', 'github-push'),
			__('一般設定', 'github-push'),
			'manage_options',
			'github-push-settings',
			array($this, 'render_general_settings_page')
		);
	}

	/**
	 * 管理画面アセットの読み込み
	 */
	public function enqueue_admin_assets($hook)
	{
		if (strpos($hook, 'github-push') === false) {
			return;
		}

		wp_enqueue_style(
			'github-push-admin',
			GITHUB_PUSH_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			GITHUB_PUSH_VERSION
		);

		wp_enqueue_script(
			'github-push-admin',
			GITHUB_PUSH_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			GITHUB_PUSH_VERSION,
			true
		);

		wp_localize_script(
			'github-push-admin',
			'githubPush',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('github-push-nonce'),
				'i18n' => array(
					'checking' => __('更新をチェック中...', 'github-push'),
					'updating' => __('更新中...', 'github-push'),
					'success' => __('更新が完了しました', 'github-push'),
					'error' => __('エラーが発生しました', 'github-push'),
					'confirmRollback' => __('本当にロールバックしますか？', 'github-push'),
				),
			)
		);
	}

	/**
	 * 設定画面の表示
	 */
	public function render_settings_page()
	{
		if (class_exists('GitHub_Push_Settings')) {
			GitHub_Push_Settings::get_instance()->render_page();
		}
	}

	/**
	 * ログ画面の表示
	 */
	public function render_logs_page()
	{
		if (class_exists('GitHub_Push_Logger')) {
			$logger = GitHub_Push_Logger::get_instance();
			$logger->render_logs_page();
		}
	}

	/**
	 * 一般設定画面の表示
	 */
	public function render_general_settings_page()
	{
		// 設定保存処理
		if (isset($_POST['github_push_save_general_settings']) && check_admin_referer('github_push_general_settings')) {
			$options = get_option('github_push_options', array());
			$options['language'] = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
			update_option('github_push_options', $options);

			// テキストドメインを再読み込み
			$this->load_textdomain();

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('設定を保存しました', 'github-push') . '</p></div>';
		}

		$options = get_option('github_push_options', array());
		$current_language = isset($options['language']) ? $options['language'] : '';

		// 利用可能な言語リスト
		$available_languages = array(
			'' => __('WordPressのデフォルト言語を使用', 'github-push'),
			'ja' => __('日本語', 'github-push'),
			'en_US' => __('English (US)', 'github-push'),
		);

		// プラグインヘッダーからバージョンを取得
		if (!function_exists('get_plugin_data')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}
		$plugin_data = get_plugin_data(__FILE__);
		$plugin_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : GITHUB_PUSH_VERSION;
?>
		<div class="wrap">
			<h1><?php echo esc_html__('一般設定', 'github-push'); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field('github_push_general_settings'); ?>
				<input type="hidden" name="github_push_save_general_settings" value="1">

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="language"><?php echo esc_html__('言語', 'github-push'); ?></label>
							</th>
							<td>
								<select name="language" id="language">
									<?php foreach ($available_languages as $code => $name) : ?>
										<option value="<?php echo esc_attr($code); ?>" <?php selected($current_language, $code); ?>>
											<?php echo esc_html($name); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php echo esc_html__('プラグインの表示言語を選択します。空の場合は、WordPressの設定に従います。', 'github-push'); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('変更を保存', 'github-push'); ?>">
				</p>
			</form>

			<div class="github-push-plugin-info" style="margin-top: 40px; padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
				<h2 style="margin-top: 0; margin-bottom: 15px; font-size: 16px;"><?php echo esc_html__('プラグイン情報', 'github-push'); ?></h2>
				<table class="form-table" style="margin-bottom: 0;">
					<tbody>
						<tr>
							<th scope="row" style="width: 120px; padding: 10px 0;"><?php echo esc_html__('バージョン', 'github-push'); ?></th>
							<td style="padding: 10px 0;"><?php echo esc_html($plugin_version); ?></td>
						</tr>
						<tr>
							<th scope="row" style="padding: 10px 0;"><?php echo esc_html__('開発者', 'github-push'); ?></th>
							<td style="padding: 10px 0;">
								<a href="https://technophere.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('テクノフィア', 'github-push'); ?></a>
							</td>
						</tr>
						<tr>
							<th scope="row" style="padding: 10px 0;"><?php echo esc_html__('お問い合わせ', 'github-push'); ?></th>
							<td style="padding: 10px 0;">
								<a href="https://technophere.com/contact" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('お問い合わせ', 'github-push'); ?></a>
							</td>
						</tr>
						<tr>
							<th scope="row" style="padding: 10px 0;"><?php echo esc_html__('ライセンス', 'github-push'); ?></th>
							<td style="padding: 10px 0;">GPL v2 or later</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
<?php
	}

	/**
	 * プラグイン一覧ページにメタ情報を追加
	 *
	 * @param array $plugin_meta 既存のメタ情報
	 * @param string $plugin_file プラグインファイル
	 * @return array メタ情報
	 */
	public function add_plugin_row_meta($plugin_meta, $plugin_file)
	{
		if (GITHUB_PUSH_PLUGIN_BASENAME !== $plugin_file) {
			return $plugin_meta;
		}

		$plugin_meta[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url('https://technophere.com'),
			esc_html__('開発者: テクノフィア', 'github-push')
		);

		return $plugin_meta;
	}

	/**
	 * Ajax: 更新チェック
	 */
	public function ajax_check_update()
	{
		check_ajax_referer('github-push-nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('権限がありません', 'github-push')));
		}

		$plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';

		if (empty($plugin_id)) {
			wp_send_json_error(array('message' => __('プラグインIDが指定されていません', 'github-push')));
		}

		$github_api = GitHub_Push_Github_API::get_instance();
		$logger = GitHub_Push_Logger::get_instance();

		// 手動チェック時はキャッシュを無視して強制更新
		$result = $github_api->check_for_updates($plugin_id, true);

		if (is_wp_error($result)) {
			// エラーログを記録
			$logger->log($plugin_id, 'version_check', 'error', $result->get_error_message());
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		// 成功時のログを記録
		$current_version = isset($result['current_version']) ? $result['current_version'] : '';
		$latest_version = isset($result['latest_version']) ? $result['latest_version'] : '';
		$update_available = isset($result['update_available']) && $result['update_available'];

		if ($update_available) {
			$message = sprintf(__('更新が利用可能です。現在: %s → 最新: %s', 'github-push'), $current_version, $latest_version);
			$logger->log($plugin_id, 'version_check', 'success', $message, $latest_version);
		} else {
			$message = sprintf(__('更新はありません。現在のバージョン: %s', 'github-push'), $current_version);
			$logger->log($plugin_id, 'version_check', 'info', $message, $current_version);
		}

		wp_send_json_success($result);
	}

	/**
	 * Ajax: プラグイン更新
	 */
	public function ajax_update_plugin()
	{
		check_ajax_referer('github-push-nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('権限がありません', 'github-push')));
		}

		$plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';

		if (empty($plugin_id)) {
			wp_send_json_error(array('message' => __('プラグインIDが指定されていません', 'github-push')));
		}

		$updater = GitHub_Push_Updater::get_instance();
		$result = $updater->update_plugin($plugin_id);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success($result);
	}

	/**
	 * Ajax: リポジトリ情報取得
	 */
	public function ajax_get_repo_info()
	{
		check_ajax_referer('github-push-nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('権限がありません', 'github-push')));
		}

		$repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
		$token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

		if (empty($repo_url)) {
			wp_send_json_error(array('message' => __('リポジトリURLが指定されていません', 'github-push')));
		}

		$github_api = GitHub_Push_Github_API::get_instance();
		$result = $github_api->get_repo_info($repo_url, $token);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success($result);
	}

	/**
	 * Ajax: ロールバック
	 */
	public function ajax_rollback()
	{
		check_ajax_referer('github-push-nonce', 'nonce');

		if (! current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('権限がありません', 'github-push')));
		}

		$plugin_id = isset($_POST['plugin_id']) ? sanitize_text_field($_POST['plugin_id']) : '';
		$backup_path = isset($_POST['backup_path']) ? sanitize_text_field($_POST['backup_path']) : '';
		$version = isset($_POST['version']) ? sanitize_text_field($_POST['version']) : '';

		if (empty($plugin_id)) {
			wp_send_json_error(array('message' => __('プラグインIDが指定されていません', 'github-push')));
		}

		$rollback = GitHub_Push_Rollback::get_instance();
		$result = $rollback->rollback_plugin($plugin_id, $backup_path, $version);

		if (is_wp_error($result)) {
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		wp_send_json_success($result);
	}

	/**
	 * データベーステーブルの作成
	 */
	private function create_tables()
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
	 * デフォルトオプションの設定
	 */
	private function set_default_options()
	{
		$default_options = array(
			'check_interval' => 24, // 時間
			'email_notifications' => false,
			'auto_update' => false,
			'language' => '', // 空の場合はWordPressのデフォルト言語を使用
		);

		add_option('github_push_options', $default_options);
	}

	/**
	 * テキストドメインを読み込み
	 */
	private function load_textdomain()
	{
		$options = get_option('github_push_options', array());
		$language = isset($options['language']) ? $options['language'] : '';

		// テキストドメインを読み込み
		$domain = 'github-push';
		$locale = !empty($language) ? $language : determine_locale();
		$mofile = GITHUB_PUSH_PLUGIN_DIR . 'languages/' . $domain . '-' . $locale . '.mo';

		// ファイルが存在する場合のみ読み込み
		if (file_exists($mofile)) {
			load_textdomain($domain, $mofile);
		} else {
			// ファイルが存在しない場合は、標準のload_plugin_textdomainを使用
			load_plugin_textdomain($domain, false, dirname(GITHUB_PUSH_PLUGIN_BASENAME) . '/languages');
		}
	}
}

// プラグインの初期化
function github_push_init()
{
	return GitHub_Push::get_instance();
}

// WordPressの初期化後に実行
add_action('plugins_loaded', 'github_push_init');
