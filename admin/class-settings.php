<?php
/**
 * 設定画面クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 設定画面クラス
 */
class GitHub_Push_Settings {
	
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_github_push_save_plugin', array( $this, 'save_plugin' ) );
		add_action( 'admin_post_github_push_delete_plugin', array( $this, 'delete_plugin' ) );
	}
	
	/**
	 * 設定を登録
	 */
	public function register_settings() {
		register_setting( 'github_push_options', 'github_push_options' );
	}
	
	/**
	 * 設定画面を表示
	 */
	public function render_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
		$plugin_id = isset( $_GET['plugin_id'] ) ? sanitize_text_field( $_GET['plugin_id'] ) : '';
		
		if ( $action === 'edit' ) {
			$this->render_edit_form( $plugin_id );
		} else {
			$this->render_plugin_list();
		}
	}
	
	/**
	 * プラグイン一覧を表示
	 */
	private function render_plugin_list() {
		$plugins = get_option( 'github_push_plugins', array() );
		
		include GITHUB_PUSH_PLUGIN_DIR . 'admin/views/settings-page.php';
	}
	
	/**
	 * 編集フォームを表示
	 */
	private function render_edit_form( $plugin_id = '' ) {
		$plugin = array();
		
		if ( ! empty( $plugin_id ) ) {
			$plugins = get_option( 'github_push_plugins', array() );
			$plugin = isset( $plugins[ $plugin_id ] ) ? $plugins[ $plugin_id ] : array();
		}
		
		// インストール済みプラグイン一覧を取得
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$installed_plugins = get_plugins();
		
		// 既に登録されているプラグインを除外
		$registered_plugins = get_option( 'github_push_plugins', array() );
		$registered_slugs = array();
		foreach ( $registered_plugins as $reg_plugin ) {
			if ( isset( $reg_plugin['plugin_slug'] ) ) {
				$registered_slugs[] = $reg_plugin['plugin_slug'];
			}
		}
		
		// 編集時は現在のプラグインは除外しない
		if ( ! empty( $plugin_id ) && isset( $plugin['plugin_slug'] ) ) {
			$registered_slugs = array_diff( $registered_slugs, array( $plugin['plugin_slug'] ) );
		}
		
		include GITHUB_PUSH_PLUGIN_DIR . 'admin/views/edit-plugin-form.php';
	}
	
	/**
	 * プラグインを保存
	 */
	public function save_plugin() {
		check_admin_referer( 'github_push_save_plugin' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( '権限がありません', 'github-push' ) );
		}
		
		$plugin_id = isset( $_POST['plugin_id'] ) ? sanitize_text_field( $_POST['plugin_id'] ) : '';
		$repo_url = isset( $_POST['repo_url'] ) ? esc_url_raw( $_POST['repo_url'] ) : '';
		$branch = isset( $_POST['branch'] ) ? sanitize_text_field( $_POST['branch'] ) : 'main';
		$plugin_slug = isset( $_POST['plugin_slug'] ) ? sanitize_text_field( $_POST['plugin_slug'] ) : '';
		$token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';
		$use_tags = isset( $_POST['use_tags'] ) ? (bool) $_POST['use_tags'] : false;
		
		if ( empty( $repo_url ) || empty( $plugin_slug ) ) {
			wp_redirect( add_query_arg( array( 'page' => 'github-push', 'error' => 'missing_fields' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		
		// GitHubからリポジトリ情報を取得してプラグイン名を生成
		$github_api = GitHub_Push_Github_API::get_instance();
		$repo_info = $github_api->get_repo_info( $repo_url, $token );
		
		if ( is_wp_error( $repo_info ) ) {
			// エラーの場合はリポジトリ名から生成
			$repo_info = $github_api->parse_repo_url( $repo_url );
			if ( ! is_wp_error( $repo_info ) ) {
				$plugin_name = $repo_info['repo'];
			} else {
				$plugin_name = __( '不明なプラグイン', 'github-push' );
			}
		} else {
			// リポジトリ名を整形
			$plugin_name = isset( $repo_info['name'] ) ? $repo_info['name'] : ( isset( $repo_info['full_name'] ) ? $repo_info['full_name'] : '' );
			// ハイフンやアンダースコアをスペースに変換して整形
			$plugin_name = str_replace( array( '-', '_' ), ' ', $plugin_name );
			$plugin_name = ucwords( $plugin_name );
		}
		
		$plugins = get_option( 'github_push_plugins', array() );
		
		if ( empty( $plugin_id ) ) {
			$plugin_id = sanitize_title( $plugin_name ) . '-' . time();
		}
		
		$plugins[ $plugin_id ] = array(
			'plugin_name' => $plugin_name,
			'repo_url' => $repo_url,
			'branch' => $branch,
			'plugin_slug' => $plugin_slug,
			'token' => $token,
			'use_tags' => $use_tags,
			'created_at' => isset( $plugins[ $plugin_id ]['created_at'] ) ? $plugins[ $plugin_id ]['created_at'] : current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);
		
		update_option( 'github_push_plugins', $plugins );
		
		wp_redirect( add_query_arg( array( 'page' => 'github-push', 'message' => 'saved' ), admin_url( 'admin.php' ) ) );
		exit;
	}
	
	/**
	 * プラグインを削除
	 */
	public function delete_plugin() {
		check_admin_referer( 'github_push_delete_plugin' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( '権限がありません', 'github-push' ) );
		}
		
		$plugin_id = isset( $_GET['plugin_id'] ) ? sanitize_text_field( $_GET['plugin_id'] ) : '';
		
		if ( empty( $plugin_id ) ) {
			wp_redirect( add_query_arg( array( 'page' => 'github-push', 'error' => 'plugin_not_found' ), admin_url( 'admin.php' ) ) );
			exit;
		}
		
		$plugins = get_option( 'github_push_plugins', array() );
		
		if ( isset( $plugins[ $plugin_id ] ) ) {
			unset( $plugins[ $plugin_id ] );
			update_option( 'github_push_plugins', $plugins );
		}
		
		wp_redirect( add_query_arg( array( 'page' => 'github-push', 'message' => 'deleted' ), admin_url( 'admin.php' ) ) );
		exit;
	}
}

