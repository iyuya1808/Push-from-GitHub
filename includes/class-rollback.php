<?php
/**
 * ロールバック機能クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ロールバック機能クラス
 */
class GitHub_Push_Rollback {
	
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
	 * プラグインをロールバック
	 *
	 * @param string $plugin_id プラグインID
	 * @param string $backup_path バックアップファイルパス（省略時は最新のバックアップ）
	 * @param string $version ロールバック先のバージョン（ログ表示用）
	 * @return array|WP_Error ロールバック結果またはエラー
	 */
	public function rollback_plugin( $plugin_id, $backup_path = '', $version = '' ) {
		$logger = GitHub_Push_Logger::get_instance();
		
		// プラグイン情報を取得
		$plugin = $this->get_plugin_data( $plugin_id );
		
		if ( ! $plugin ) {
			$error = new WP_Error( 'plugin_not_found', __( 'プラグインが見つかりません', 'push-from-github' ) );
			$logger->log( $plugin_id, 'rollback', 'error', $error->get_error_message() );
			return $error;
		}
		
		// バックアップパスを取得
		if ( empty( $backup_path ) ) {
			$backup_path = $this->get_latest_backup( $plugin_id );
			
			if ( is_wp_error( $backup_path ) ) {
				$logger->log( $plugin_id, 'rollback', 'error', $backup_path->get_error_message() );
				return $backup_path;
			}
		}
		
		if ( ! file_exists( $backup_path ) ) {
			$error = new WP_Error( 'backup_not_found', __( 'バックアップファイルが見つかりません', 'push-from-github' ) );
			$logger->log( $plugin_id, 'rollback', 'error', $error->get_error_message() );
			return $error;
		}
		
		$plugin_slug = isset( $plugin['plugin_slug'] ) ? $plugin['plugin_slug'] : '';
		
		if ( empty( $plugin_slug ) ) {
			$error = new WP_Error( 'plugin_slug_missing', __( 'プラグインスラッグが指定されていません', 'push-from-github' ) );
			$logger->log( $plugin_id, 'rollback', 'error', $error->get_error_message() );
			return $error;
		}
		
		$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $plugin_slug );
		
		// 既存のプラグインが有効かどうかを確認
		$is_active = is_plugin_active( $plugin_slug );
		
		// 既存のプラグインを削除
		if ( file_exists( $plugin_dir ) ) {
			$this->delete_directory( $plugin_dir );
		}
		
		// ZIPファイルを展開
		$extracted_path = $this->extract_zip( $backup_path );
		
		if ( is_wp_error( $extracted_path ) ) {
			$logger->log( $plugin_id, 'rollback', 'error', $extracted_path->get_error_message() );
			return $extracted_path;
		}
		
		// 展開したファイルを移動
		$move_result = $this->move_extracted_files( $extracted_path, $plugin_dir );
		
		if ( is_wp_error( $move_result ) ) {
			$logger->log( $plugin_id, 'rollback', 'error', $move_result->get_error_message() );
			$this->delete_directory( $extracted_path );
			return $move_result;
		}
		
		// 一時ファイルを削除
		$this->delete_directory( $extracted_path );
		
		// プラグインを再読み込み
		wp_cache_flush();
		
		// プラグインが有効だった場合は再度有効化
		if ( $is_active ) {
			activate_plugin( $plugin_slug );
		}
		
		// 通知を送信
		$notifications = GitHub_Push_Notifications::get_instance();
		$notifications->send_rollback_notification( $plugin_id );
		
		$message = ! empty( $version ) 
			? sprintf( 
				// translators: %s: Version number
				__( 'プラグインをバージョン %s にロールバックしました', 'push-from-github' ), $version )
			: __( 'プラグインをロールバックしました', 'push-from-github' );
		$logger->log( $plugin_id, 'rollback', 'success', $message, $version );
		
		return array(
			'message' => $message,
		);
	}
	
	/**
	 * 最新のバックアップを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return string|WP_Error バックアップパスまたはエラー
	 */
	private function get_latest_backup( $plugin_id ) {
		$backups = get_option( 'github_push_backups', array() );
		
		if ( ! isset( $backups[ $plugin_id ] ) || empty( $backups[ $plugin_id ] ) ) {
			return new WP_Error( 'no_backup', __( 'バックアップが見つかりません', 'push-from-github' ) );
		}
		
		$plugin_backups = $backups[ $plugin_id ];
		
		// created_at でソート（新しい順）
		usort( $plugin_backups, function( $a, $b ) {
			$time_a = isset( $a['created_at'] ) ? strtotime( $a['created_at'] ) : 0;
			$time_b = isset( $b['created_at'] ) ? strtotime( $b['created_at'] ) : 0;
			return $time_b - $time_a;
		} );
		
		// ファイルが存在する最新のバックアップを探す
		foreach ( $plugin_backups as $backup ) {
			if ( isset( $backup['path'] ) && file_exists( $backup['path'] ) ) {
				return $backup['path'];
			}
		}
		
		return new WP_Error( 'backup_not_found', __( '有効なバックアップファイルが見つかりません', 'push-from-github' ) );
	}
	
	/**
	 * ZIPファイルを展開
	 *
	 * @param string $zip_path ZIPファイルのパス
	 * @return string|WP_Error 展開先パスまたはエラー
	 */
	private function extract_zip( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_not_supported', __( 'ZIP拡張機能が利用できません', 'push-from-github' ) );
		}
		
		$zip = new ZipArchive();
		
		if ( $zip->open( $zip_path ) !== true ) {
			return new WP_Error( 'zip_open_failed', __( 'ZIPファイルを開けませんでした', 'push-from-github' ) );
		}
		
		$upload_dir = wp_upload_dir();
		$extract_dir = $upload_dir['basedir'] . '/github-push-temp/' . uniqid( 'rollback-' );
		
		if ( ! wp_mkdir_p( $extract_dir ) ) {
			$zip->close();
			return new WP_Error( 'extract_dir_failed', __( '展開ディレクトリを作成できませんでした', 'push-from-github' ) );
		}
		
		$result = $zip->extractTo( $extract_dir );
		$zip->close();
		
		if ( ! $result ) {
			return new WP_Error( 'extract_failed', __( 'ZIPファイルの展開に失敗しました', 'push-from-github' ) );
		}
		
		return $extract_dir;
	}
	
	/**
	 * 展開したファイルを移動
	 *
	 * @param string $extracted_path 展開先パス
	 * @param string $plugin_dir プラグインディレクトリ
	 * @return bool|WP_Error 成功またはエラー
	 */
	private function move_extracted_files( $extracted_path, $plugin_dir ) {
		$files = scandir( $extracted_path );
		
		if ( $files === false ) {
			return new WP_Error( 'scan_failed', __( '展開ディレクトリを読み取れませんでした', 'push-from-github' ) );
		}
		
		// ディレクトリとファイルを分けて探す
		$directories = array();
		$has_files = false;
		
		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}
			
			$file_path = $extracted_path . '/' . $file;
			
			if ( is_dir( $file_path ) ) {
				$directories[] = $file_path;
			} else {
				$has_files = true;
			}
		}
		
		// ソースディレクトリを決定
		$source_dir = null;
		
		if ( $has_files ) {
			// ファイルが直接ある場合は、展開先自体がプラグインディレクトリ
			$source_dir = $extracted_path;
		} elseif ( count( $directories ) === 1 ) {
			// ディレクトリが1つだけの場合は、そのディレクトリを使用
			$source_dir = $directories[0];
		} elseif ( count( $directories ) > 1 ) {
			// 複数のディレクトリがある場合は、最初のディレクトリを使用
			$source_dir = $directories[0];
		} else {
			// ディレクトリもファイルもない場合はエラー
			return new WP_Error( 'no_files_found', __( '展開されたファイルが見つかりませんでした', 'push-from-github' ) );
		}
		
		// プラグインディレクトリを作成
		if ( ! wp_mkdir_p( $plugin_dir ) ) {
			return new WP_Error( 'plugin_dir_failed', __( 'プラグインディレクトリを作成できませんでした', 'push-from-github' ) );
		}
		
		// ファイルをコピー
		$result = $this->copy_directory( $source_dir, $plugin_dir );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		
		return true;
	}
	
	/**
	 * ディレクトリをコピー
	 *
	 * @param string $source ソースディレクトリ
	 * @param string $destination コピー先ディレクトリ
	 * @return bool|WP_Error 成功またはエラー
	 */
	private function copy_directory( $source, $destination ) {
		global $wp_filesystem;
		
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'filesystem_not_available', __( 'ファイルシステムにアクセスできません', 'push-from-github' ) );
		}
		
		$result = copy_dir( $source, $destination );
		
		if ( ! $result ) {
			return new WP_Error( 'copy_failed', __( 'ファイルのコピーに失敗しました', 'push-from-github' ) );
		}
		
		return true;
	}
	
	/**
	 * ディレクトリを削除
	 *
	 * @param string $dir ディレクトリパス
	 * @return bool 成功
	 */
	private function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return true;
		}
		
		global $wp_filesystem;
		
		if ( empty( $wp_filesystem ) ) {
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();
		}
		
		if ( $wp_filesystem ) {
			return $wp_filesystem->rmdir( $dir, true );
		}
		
		// フォールバック
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		
		foreach ( $files as $file ) {
			$file_path = $dir . '/' . $file;
			
			if ( is_dir( $file_path ) ) {
				$this->delete_directory( $file_path );
			} else {
				wp_delete_file( $file_path );
			}
		}
		
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem->rmdir( $dir, true );
	}
	
	/**
	 * プラグインデータを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return array|false プラグイン情報またはfalse
	 */
	private function get_plugin_data( $plugin_id ) {
		$plugins = get_option( 'github_push_plugins', array() );
		
		if ( ! isset( $plugins[ $plugin_id ] ) ) {
			return false;
		}
		
		return $plugins[ $plugin_id ];
	}
}

