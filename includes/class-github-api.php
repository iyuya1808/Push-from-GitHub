<?php

/**
 * GitHub API連携クラス
 *
 * @package GitHub_Push
 */

// 直接アクセスを防ぐ
if (! defined('ABSPATH')) {
	exit;
}

/**
 * GitHub API連携クラス
 */
class GitHub_Push_Github_API
{

	/**
	 * インスタンス
	 */
	private static $instance = null;

	/**
	 * APIベースURL
	 */
	private $api_base = 'https://api.github.com';

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
	 * 更新チェック
	 *
	 * @param string $plugin_id プラグインID
	 * @param bool $force_refresh キャッシュを無視して強制更新
	 * @return array|WP_Error 更新情報またはエラー
	 */
	public function check_for_updates($plugin_id, $force_refresh = false)
	{
		$plugin = $this->get_plugin_data($plugin_id);

		if (! $plugin) {
			return new WP_Error('plugin_not_found', __('プラグインが見つかりません', 'push-from-github'));
		}

		// キャッシュをチェック
		$cache_key = 'github_push_update_' . $plugin_id;

		if (! $force_refresh) {
			$cached = get_transient($cache_key);

			if (false !== $cached) {
				return $cached;
			}
		}

		$current_version = $this->get_current_version($plugin_id);

		// タグを使用している場合は通常のバージョン比較
		if (isset($plugin['use_tags']) && $plugin['use_tags']) {
			$latest_version = $this->get_latest_version($plugin);

			if (is_wp_error($latest_version)) {
				// エラーをそのまま返す（エラーログはajax_check_updateで記録される）
				return $latest_version;
			} else {
				$update_available = version_compare($latest_version, $current_version, '>');

				$result = array(
					'update_available' => $update_available,
					'current_version' => $current_version,
					'latest_version' => $latest_version,
					'download_url' => $update_available ? $this->get_download_url($plugin, $latest_version) : '',
				);
			}
		} else {
			// タグを使用していない場合は、GitHubからプラグインファイルのバージョンを取得
			$latest_version_info = $this->get_latest_plugin_version_from_github($plugin);

			if (is_wp_error($latest_version_info)) {
				// エラーの場合は現在のバージョンを使用（更新なしとして扱う）
				// エラーメッセージをログに記録
				$logger = GitHub_Push_Logger::get_instance();
				$error_message = $latest_version_info->get_error_message();
				$logger->log($plugin_id, 'version_check', 'error', $error_message);

				// Not Foundエラーの場合は、現在のバージョンを最新として扱う（更新なし）
				$result = array(
					'update_available' => false,
					'current_version' => $current_version,
					'latest_version' => $current_version,
					'download_url' => '',
					'error' => $error_message,
				);
			} else {
				$latest_version = $latest_version_info['version'];
				$update_available = version_compare($latest_version, $current_version, '>');

				$result = array(
					'update_available' => $update_available,
					'current_version' => $current_version,
					'latest_version' => $latest_version,
					'download_url' => $update_available ? $this->get_download_url($plugin, '') : '',
				);
			}
		}

		// キャッシュに保存（15分間）
		set_transient($cache_key, $result, 15 * MINUTE_IN_SECONDS);

		return $result;
	}

	/**
	 * 最新バージョンを取得
	 *
	 * @param array $plugin プラグイン情報
	 * @return string|WP_Error バージョンまたはエラー
	 */
	private function get_latest_version($plugin)
	{
		$repo_url = $plugin['repo_url'];
		$branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
		$token = isset($plugin['token']) ? $plugin['token'] : '';

		// リポジトリ情報を取得
		$repo_info = $this->parse_repo_url($repo_url);

		if (is_wp_error($repo_info)) {
			return $repo_info;
		}

		// タグを使用する場合
		if (isset($plugin['use_tags']) && $plugin['use_tags']) {
			$latest_tag = $this->get_latest_tag($repo_info['owner'], $repo_info['repo'], $token);

			if (is_wp_error($latest_tag)) {
				return $latest_tag;
			}

			return $latest_tag;
		}

		// ブランチの最新コミットを取得
		$commit = $this->get_branch_commit($repo_info['owner'], $repo_info['repo'], $branch, $token);

		if (is_wp_error($commit)) {
			return $commit;
		}

		// コミットSHAの短縮版をバージョンとして使用（非推奨：get_latest_plugin_version_from_githubを使用）
		return substr($commit['sha'], 0, 7);
	}

	/**
	 * GitHubから最新のプラグインファイルのバージョンを取得（タグ未使用時）
	 *
	 * @param array $plugin プラグイン情報
	 * @return array|WP_Error バージョン情報（version, commit_sha）またはエラー
	 */
	private function get_latest_plugin_version_from_github($plugin)
	{
		$repo_url = $plugin['repo_url'];
		$branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';
		$token = isset($plugin['token']) ? $plugin['token'] : '';
		$plugin_slug = isset($plugin['plugin_slug']) ? $plugin['plugin_slug'] : '';

		if (empty($plugin_slug)) {
			return new WP_Error('plugin_slug_missing', __('プラグインスラッグが指定されていません', 'push-from-github'));
		}

		$repo_info = $this->parse_repo_url($repo_url);

		if (is_wp_error($repo_info)) {
			return $repo_info;
		}

		// ブランチの最新コミットを取得
		$commit = $this->get_branch_commit($repo_info['owner'], $repo_info['repo'], $branch, $token);

		if (is_wp_error($commit)) {
			return $commit;
		}

		$commit_sha = $commit['sha'];

		// プラグインファイルのパスを取得（プラグインスラッグをそのまま使用）
		$plugin_file_path = $plugin_slug;
		$plugin_filename = basename($plugin_file_path);

		// まず、リポジトリのルートディレクトリの内容を取得してファイル構造を確認
		$root_contents_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents?ref=' . $branch;
		$root_contents = $this->make_request($root_contents_url, $token);

		$possible_paths = array();
		$root_files = array(); // デバッグ用

		if (! is_wp_error($root_contents) && is_array($root_contents)) {
			// ルートディレクトリのファイル一覧を取得（デバッグ用）
			foreach ($root_contents as $item) {
				if (isset($item['name'])) {
					$root_files[] = $item['name'] . (isset($item['type']) ? ' (' . $item['type'] . ')' : '');
				}
			}

			// まず、プラグインスラッグから推測したファイル名で検索
			$found_path = $this->find_plugin_file_in_contents($root_contents, $plugin_filename, $repo_info, $branch, $token);
			if ($found_path) {
				$possible_paths[] = $found_path;
			} else {
				// 見つからない場合、リポジトリ内のプラグインファイルを自動検索（Version: ヘッダーがあるPHPファイルを探す）
				$found_path = $this->find_plugin_main_file($root_contents, $repo_info, $branch, $token);
				if ($found_path) {
					$possible_paths[] = $found_path;
				}
			}
		}

		// 見つからなかった場合、従来のパターンも試す
		if (empty($possible_paths)) {
			$possible_paths = array(
				$plugin_file_path, // プラグインスラッグをそのまま使用
				basename($plugin_file_path), // ファイル名のみ
			);

			// ディレクトリ名とファイル名を分離
			$plugin_dir = dirname($plugin_file_path);

			// ディレクトリが '.' でない場合（サブディレクトリがある場合）
			if ($plugin_dir !== '.' && $plugin_dir !== $plugin_file_path) {
				// ディレクトリ名/ファイル名のパターンを追加
				$possible_paths[] = $plugin_dir . '/' . $plugin_filename;
			}

			// 一般的なディレクトリ構造を試す
			$common_dirs = array('src', 'includes', 'lib', 'app');
			foreach ($common_dirs as $dir) {
				$possible_paths[] = $dir . '/' . $plugin_filename;
				if ($plugin_dir !== '.' && $plugin_dir !== $plugin_file_path) {
					$possible_paths[] = $dir . '/' . $plugin_dir . '/' . $plugin_filename;
				}
			}
		}

		// 重複を削除
		$possible_paths = array_unique($possible_paths);

		$file_response = null;
		$last_error = null;
		$tried_paths = array();

		foreach ($possible_paths as $path) {
			$tried_paths[] = $path;
			$file_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents/' . $path . '?ref=' . $branch;

			$file_response = $this->make_request($file_url, $token);

			if (! is_wp_error($file_response)) {
				// 成功した場合はループを抜ける
				break;
			}

			$last_error = $file_response;
		}

		if (is_wp_error($file_response)) {
			// より詳細なエラーメッセージを返す
			$error_message = $file_response->get_error_message();
			if ($error_message === 'Not Found') {
				$paths_list = implode(', ', $tried_paths);
				$error_msg = sprintf(
					__('プラグインファイルが見つかりませんでした。', 'push-from-github')
				);
				// translators: %s: Plugin filename
				$error_msg .= "\n" . sprintf(__('検索したファイル名: %s', 'push-from-github'), $plugin_filename);
				// translators: %s: Tried paths
				$error_msg .= "\n" . sprintf(__('試したパス: %s', 'push-from-github'), $paths_list);

				// ルートディレクトリの内容を表示（デバッグ用）
				if (!empty($root_files)) {
					// translators: %s: Root files/directories list
					$error_msg .= "\n" . sprintf(__('リポジトリルートのファイル/ディレクトリ: %s', 'push-from-github'), implode(', ', array_slice($root_files, 0, 20)));
					if (count($root_files) > 20) {
						$error_msg .= ' ... (他' . (count($root_files) - 20) . '件)';
					}
				} else {
					$error_msg .= "\n" . __('リポジトリのルートディレクトリを取得できませんでした。', 'push-from-github');
					if (is_wp_error($root_contents)) {
						// translators: %s: Error message
						$error_msg .= ' ' . sprintf(__('エラー: %s', 'push-from-github'), $root_contents->get_error_message());
					}
				}

				$error_msg .= "\n" . sprintf(
					// translators: %1$s: Repository owner, %2$s: Repository name, %3$s: Branch name
					__('GitHubリポジトリ「%1$s/%2$s」のブランチ「%3$s」内のファイル構造を確認してください。', 'push-from-github'),
					$repo_info['owner'],
					$repo_info['repo'],
					$branch
				);

				return new WP_Error('file_not_found', $error_msg);
			}
			return $file_response;
		}

		// Base64でエンコードされている場合はデコード
		if (isset($file_response['content'])) {
			// 改行文字を削除（GitHub APIは改行文字を含む場合がある）
			$encoded_content = str_replace(array("\n", "\r", " "), '', $file_response['content']);
			$file_content = base64_decode($encoded_content, true);

			if ($file_content === false) {
				return new WP_Error('file_decode_error', __('ファイル内容のデコードに失敗しました', 'push-from-github'));
			}
		} else {
			return new WP_Error('file_content_error', __('ファイル内容を取得できませんでした', 'push-from-github'));
		}

		// プラグインファイルからバージョンを抽出
		$version = $this->extract_version_from_plugin_file($file_content);

		if (empty($version)) {
			// バージョンが見つからない場合はエラーを返す（コミットSHAは使用しない）
			return new WP_Error('version_not_found', __('プラグインファイルからバージョン情報を取得できませんでした。Version: ヘッダーが正しく記載されているか確認してください。', 'push-from-github'));
		}

		return array(
			'version' => $version,
			'commit_sha' => $commit_sha,
		);
	}

	/**
	 * プラグインファイルからバージョンを抽出
	 *
	 * @param string $file_content ファイル内容
	 * @return string バージョン
	 */
	private function extract_version_from_plugin_file($file_content)
	{
		// 複数のパターンでバージョンを検索
		$patterns = array(
			'/Version:\s*([^\s\n\r\*\/]+)/i',                    // Version: 1.0.0
			'/\'Version\'\s*=>\s*[\'"]([^\'"]+)[\'"]/i',        // 'Version' => '1.0.0'
			'/"Version"\s*=>\s*[\'"]([^\'"]+)[\'"]/i',          // "Version" => "1.0.0"
			'/define\s*\(\s*[\'"]VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', // define('VERSION', '1.0.0')
		);

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $file_content, $matches)) {
				$version = trim($matches[1]);
				// バージョン番号の形式を検証（数字とドット、ハイフンを含む）
				if (preg_match('/^[\d\.\-]+$/', $version)) {
					return $version;
				}
			}
		}

		// コメントブロック内のVersion:を検索（より柔軟な検索）
		if (preg_match('/\*\s*Version:\s*([^\s\n\r\*\/]+)/i', $file_content, $matches)) {
			$version = trim($matches[1]);
			if (preg_match('/^[\d\.\-]+$/', $version)) {
				return $version;
			}
		}

		return '';
	}

	/**
	 * リポジトリ内のプラグインメインファイルを検索（Version: ヘッダーがあるファイル）
	 *
	 * @param array $contents ディレクトリの内容
	 * @param array $repo_info リポジトリ情報
	 * @param string $branch ブランチ名
	 * @param string $token アクセストークン
	 * @return string|false 見つかったパスまたはfalse
	 */
	private function find_plugin_main_file($contents, $repo_info, $branch, $token)
	{
		// ルートディレクトリのPHPファイルをチェック
		foreach ($contents as $item) {
			if (isset($item['type']) && $item['type'] === 'file' && isset($item['name'])) {
				if (preg_match('/\.php$/', $item['name'])) {
					// PHPファイルの内容を取得してVersion: ヘッダーを確認
					$file_path = isset($item['path']) ? $item['path'] : $item['name'];
					$file_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents/' . $file_path . '?ref=' . $branch;
					$file_response = $this->make_request($file_url, $token);

					if (! is_wp_error($file_response) && isset($file_response['content'])) {
						// Base64デコード
						$encoded_content = str_replace(array("\n", "\r", " "), '', $file_response['content']);
						$file_content = base64_decode($encoded_content, true);

						if ($file_content !== false) {
							// Version: ヘッダーがあるか確認
							if (preg_match('/Version:\s*([^\s\n\r\*\/]+)/i', $file_content)) {
								// プラグインメインファイルが見つかった
								return $file_path;
							}
						}
					}
				} elseif (isset($item['type']) && $item['type'] === 'dir' && isset($item['path'])) {
					// サブディレクトリも検索（1階層まで）
					$subdir_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents/' . $item['path'] . '?ref=' . $branch;
					$subdir_contents = $this->make_request($subdir_url, $token);

					if (! is_wp_error($subdir_contents) && is_array($subdir_contents)) {
						foreach ($subdir_contents as $subitem) {
							if (isset($subitem['type']) && $subitem['type'] === 'file' && isset($subitem['name'])) {
								if (preg_match('/\.php$/', $subitem['name'])) {
									$subfile_path = isset($subitem['path']) ? $subitem['path'] : $item['path'] . '/' . $subitem['name'];
									$subfile_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents/' . $subfile_path . '?ref=' . $branch;
									$subfile_response = $this->make_request($subfile_url, $token);

									if (! is_wp_error($subfile_response) && isset($subfile_response['content'])) {
										$encoded_content = str_replace(array("\n", "\r", " "), '', $subfile_response['content']);
										$subfile_content = base64_decode($encoded_content, true);

										if ($subfile_content !== false) {
											if (preg_match('/Version:\s*([^\s\n\r\*\/]+)/i', $subfile_content)) {
												return $subfile_path;
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * リポジトリの内容からプラグインファイルを検索
	 *
	 * @param array $contents ディレクトリの内容
	 * @param string $filename 検索するファイル名
	 * @param array $repo_info リポジトリ情報
	 * @param string $branch ブランチ名
	 * @param string $token アクセストークン
	 * @return string|false 見つかったパスまたはfalse
	 */
	private function find_plugin_file_in_contents($contents, $filename, $repo_info, $branch, $token)
	{
		// まず、指定されたファイル名で検索
		foreach ($contents as $item) {
			if (isset($item['type'])) {
				if ($item['type'] === 'file' && isset($item['name']) && $item['name'] === $filename) {
					// ファイルが見つかった
					return isset($item['path']) ? $item['path'] : $item['name'];
				} elseif ($item['type'] === 'dir' && isset($item['path'])) {
					// サブディレクトリを再帰的に検索（最大2階層まで）
					$subdir_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents/' . $item['path'] . '?ref=' . $branch;
					$subdir_contents = $this->make_request($subdir_url, $token);

					if (! is_wp_error($subdir_contents) && is_array($subdir_contents)) {
						foreach ($subdir_contents as $subitem) {
							if (isset($subitem['type']) && $subitem['type'] === 'file' && isset($subitem['name']) && $subitem['name'] === $filename) {
								// サブディレクトリ内でファイルが見つかった
								return isset($subitem['path']) ? $subitem['path'] : $item['path'] . '/' . $subitem['name'];
							}
						}
					}
				}
			}
		}

		// 指定されたファイル名で見つからない場合、ルートディレクトリのPHPファイルを検索
		// プラグインのメインファイルは通常、ルートディレクトリにある
		foreach ($contents as $item) {
			if (isset($item['type']) && $item['type'] === 'file' && isset($item['name'])) {
				// PHPファイルで、プラグインファイルの可能性が高いものを探す
				// 通常、プラグインのメインファイルはルートディレクトリにある
				if (preg_match('/\.php$/', $item['name'])) {
					// ルートディレクトリのPHPファイルを返す（最初に見つかったもの）
					return isset($item['path']) ? $item['path'] : $item['name'];
				}
			}
		}

		return false;
	}

	/**
	 * 最新タグを取得
	 *
	 * @param string $owner オーナー
	 * @param string $repo リポジトリ名
	 * @param string $token アクセストークン
	 * @return string|WP_Error タグ名またはエラー
	 */
	private function get_latest_tag($owner, $repo, $token = '')
	{
		$url = $this->api_base . '/repos/' . $owner . '/' . $repo . '/tags';

		$response = $this->make_request($url, $token);

		if (is_wp_error($response)) {
			// APIエラーの場合は、より詳細なメッセージを返す
			$error_message = $response->get_error_message();
			if ($error_message === 'Not Found') {
				// translators: %1$s: Repository owner, %2$s: Repository name
				return new WP_Error('no_tags', sprintf(__('リポジトリにタグが見つかりません。GitHubリポジトリ「%1$s/%2$s」にタグが作成されているか確認してください。タグがない場合は、「タグを使用する」のチェックを外してブランチを使用してください。', 'push-from-github'), $owner, $repo));
			}
			return $response;
		}

		if (empty($response) || ! is_array($response) || count($response) === 0) {
			// translators: %1$s: Repository owner, %2$s: Repository name
			return new WP_Error('no_tags', sprintf(__('リポジトリにタグが見つかりません。GitHubリポジトリ「%1$s/%2$s」にタグが作成されているか確認してください。タグがない場合は、「タグを使用する」のチェックを外してブランチを使用してください。', 'push-from-github'), $owner, $repo));
		}

		// 最初のタグ（最新）を返す
		return $response[0]['name'];
	}

	/**
	 * ブランチの最新コミットを取得
	 *
	 * @param string $owner オーナー
	 * @param string $repo リポジトリ名
	 * @param string $branch ブランチ名
	 * @param string $token アクセストークン
	 * @return array|WP_Error コミット情報またはエラー
	 */
	private function get_branch_commit($owner, $repo, $branch, $token = '')
	{
		$url = $this->api_base . '/repos/' . $owner . '/' . $repo . '/commits/' . $branch;

		$response = $this->make_request($url, $token);

		if (is_wp_error($response)) {
			return $response;
		}

		return $response;
	}

	/**
	 * ダウンロードURLを取得
	 *
	 * @param array $plugin プラグイン情報
	 * @param string $version バージョン
	 * @return string|WP_Error ダウンロードURLまたはエラー
	 */
	public function get_download_url($plugin, $version)
	{
		$repo_url = $plugin['repo_url'];
		$branch = isset($plugin['branch']) ? $plugin['branch'] : 'main';

		$repo_info = $this->parse_repo_url($repo_url);

		if (is_wp_error($repo_info)) {
			return $repo_info;
		}

		// タグを使用する場合
		if (isset($plugin['use_tags']) && $plugin['use_tags']) {
			$download_url = 'https://github.com/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/archive/refs/tags/' . $version . '.zip';
		} else {
			// ブランチのZIPをダウンロード
			$download_url = 'https://github.com/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/archive/refs/heads/' . $branch . '.zip';
		}

		return $download_url;
	}

	/**
	 * ZIPファイルをダウンロード
	 *
	 * @param string $url ダウンロードURL
	 * @param string $token アクセストークン
	 * @return string|WP_Error ダウンロードしたファイルパスまたはエラー
	 */
	public function download_zip($url, $token = '')
	{
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/github-push-temp';

		// 一時ディレクトリを作成
		if (! file_exists($temp_dir)) {
			wp_mkdir_p($temp_dir);
		}

		$filename = basename(wp_parse_url($url, PHP_URL_PATH));
		$file_path = $temp_dir . '/' . uniqid('github-push-') . '-' . $filename;

		$args = array(
			'timeout' => 300,
			'stream' => true,
			'filename' => $file_path,
		);

		// トークンがある場合は認証ヘッダーを追加
		if (! empty($token)) {
			$args['headers'] = array(
				'Authorization' => 'token ' . $token,
			);
		}

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);

		if ($response_code !== 200) {
			$error_message = wp_remote_retrieve_response_message($response);
			// translators: %s: Error message
			return new WP_Error('download_failed', sprintf(__('ダウンロードに失敗しました: %s', 'push-from-github'), $error_message));
		}

		if (! file_exists($file_path)) {
			return new WP_Error('file_not_found', __('ダウンロードしたファイルが見つかりません', 'push-from-github'));
		}

		return $file_path;
	}

	/**
	 * APIリクエストを実行
	 *
	 * @param string $url API URL
	 * @param string $token アクセストークン
	 * @return array|WP_Error レスポンスまたはエラー
	 */
	private function make_request($url, $token = '')
	{
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
		);

		if (! empty($token)) {
			$args['headers']['Authorization'] = 'token ' . $token;
		}

		$response = wp_remote_get($url, $args);

		if (is_wp_error($response)) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code($response);

		if ($response_code !== 200) {
			$body = wp_remote_retrieve_body($response);
			$error_data = json_decode($body, true);
			$error_message = isset($error_data['message']) ? $error_data['message'] : __('APIリクエストに失敗しました', 'push-from-github');

			return new WP_Error('api_error', $error_message);
		}

		$body = wp_remote_retrieve_body($response);
		return json_decode($body, true);
	}

	/**
	 * リポジトリ情報を取得
	 *
	 * @param string $repo_url リポジトリURL
	 * @param string $token アクセストークン
	 * @return array|WP_Error リポジトリ情報またはエラー
	 */
	public function get_repo_info($repo_url, $token = '')
	{
		$repo_info = $this->parse_repo_url($repo_url);

		if (is_wp_error($repo_info)) {
			return $repo_info;
		}

		$url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'];

		$response = $this->make_request($url, $token);

		if (is_wp_error($response)) {
			return $response;
		}

		return array(
			'name' => isset($response['name']) ? $response['name'] : '',
			'full_name' => isset($response['full_name']) ? $response['full_name'] : '',
			'description' => isset($response['description']) ? $response['description'] : '',
			'owner' => $repo_info['owner'],
			'repo' => $repo_info['repo'],
		);
	}

	/**
	 * リポジトリURLを解析
	 *
	 * @param string $url リポジトリURL
	 * @return array|WP_Error リポジトリ情報またはエラー
	 */
	public function parse_repo_url($url)
	{
		// GitHub URLの形式を解析
		// https://github.com/owner/repo
		// git@github.com:owner/repo.git

		$pattern = '/github\.com[\/:]([^\/]+)\/([^\/\.]+)/';

		if (preg_match($pattern, $url, $matches)) {
			return array(
				'owner' => $matches[1],
				'repo' => rtrim($matches[2], '.git'),
			);
		}

		return new WP_Error('invalid_url', __('無効なGitHub URLです', 'push-from-github'));
	}

	/**
	 * プラグインデータを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return array|false プラグイン情報またはfalse
	 */
	private function get_plugin_data($plugin_id)
	{
		$plugins = get_option('github_push_plugins', array());

		if (! isset($plugins[$plugin_id])) {
			return false;
		}

		return $plugins[$plugin_id];
	}

	/**
	 * 現在のバージョンを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return string バージョン
	 */
	public function get_current_version($plugin_id)
	{
		$plugin_slug = $this->get_plugin_slug($plugin_id);

		if (! $plugin_slug) {
			return '0.0.0';
		}

		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;

		if (! file_exists($plugin_file)) {
			return '0.0.0';
		}

		$plugin_data = get_plugin_data($plugin_file);

		return isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';
	}

	/**
	 * プラグインスラッグを取得
	 *
	 * @param string $plugin_id プラグインID
	 * @return string|false プラグインスラッグまたはfalse
	 */
	private function get_plugin_slug($plugin_id)
	{
		$plugin = $this->get_plugin_data($plugin_id);

		if (! $plugin) {
			return false;
		}

		return isset($plugin['plugin_slug']) ? $plugin['plugin_slug'] : false;
	}

	/**
	 * リポジトリがWordPressプラグインかどうかを検証
	 *
	 * @param string $repo_url リポジトリURL
	 * @param string $plugin_slug プラグインスラッグ
	 * @param string $branch ブランチ名
	 * @param string $token アクセストークン
	 * @return true|WP_Error 検証成功またはエラー
	 */
	public function validate_plugin_repository($repo_url, $plugin_slug, $branch = 'main', $token = '')
	{
		// リポジトリURLを解析
		$repo_info = $this->parse_repo_url($repo_url);
		if (is_wp_error($repo_info)) {
			return $repo_info;
		}

		// リポジトリが存在するか確認
		$repo_data = $this->get_repo_info($repo_url, $token);
		if (is_wp_error($repo_data)) {
			$error_code = $repo_data->get_error_code();
			$error_message = $repo_data->get_error_message();

			if ($error_message === 'Not Found') {
				// translators: %1$s: Repository owner, %2$s: Repository name
				return new WP_Error(
					'repo_not_found',
					sprintf(
						__('指定されたGitHubリポジトリが見つかりませんでした。リポジトリURLが正しいか、アクセス権限があるか確認してください。', 'push-from-github'),
						$repo_info['owner'],
						$repo_info['repo']
					)
				);
			}

			return new WP_Error(
				'repo_access_error',
				sprintf(
					// translators: %s: Error message
					__('リポジトリにアクセスできませんでした: %s', 'push-from-github'),
					$error_message
				)
			);
		}

		// プラグインファイルのパスを取得
		$plugin_filename = basename($plugin_slug);
		$plugin_dir = dirname($plugin_slug);

		// リポジトリのルートディレクトリの内容を取得
		$root_contents_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents?ref=' . $branch;
		$root_contents = $this->make_request($root_contents_url, $token);

		if (is_wp_error($root_contents)) {
			return new WP_Error(
				'branch_access_error',
				sprintf(
					// translators: %s: Branch name
					__('ブランチ「%s」にアクセスできませんでした。ブランチ名が正しいか確認してください。', 'push-from-github'),
					$branch
				)
			);
		}

		// プラグインファイルを検索
		$plugin_file_path = $this->find_plugin_file_in_contents($root_contents, $plugin_filename, $repo_info, $branch, $token);

		if (!$plugin_file_path) {
			// プラグインファイルが見つからない場合、プラグインメインファイルを検索
			$plugin_file_path = $this->find_plugin_main_file($root_contents, $repo_info, $branch, $token);

			if (!$plugin_file_path) {
				return new WP_Error(
					'plugin_file_not_found',
					sprintf(
						// translators: %s: Plugin slug
						__('指定されたプラグインファイル（%s）がリポジトリ内に見つかりませんでした。リポジトリがWordPressプラグインではない可能性があります。', 'push-from-github'),
						$plugin_slug
					)
				);
			}
		}

		// プラグインファイルの内容を取得して検証
		$file_url = $this->api_base . '/repos/' . $repo_info['owner'] . '/' . $repo_info['repo'] . '/contents/' . $plugin_file_path . '?ref=' . $branch;
		$file_response = $this->make_request($file_url, $token);

		if (is_wp_error($file_response)) {
			return new WP_Error(
				'plugin_file_read_error',
				sprintf(
					// translators: %s: Error message
					__('プラグインファイルの内容を取得できませんでした: %s', 'push-from-github'),
					$file_response->get_error_message()
				)
			);
		}

		// Base64デコード
		if (!isset($file_response['content'])) {
			return new WP_Error(
				'plugin_file_content_error',
				__('プラグインファイルの内容が取得できませんでした。', 'push-from-github')
			);
		}

		$encoded_content = str_replace(array("\n", "\r", " "), '', $file_response['content']);
		$file_content = base64_decode($encoded_content, true);

		if ($file_content === false) {
			return new WP_Error(
				'plugin_file_decode_error',
				__('プラグインファイルの内容をデコードできませんでした。', 'push-from-github')
			);
		}

		// プラグインヘッダーを検証
		$has_plugin_name = preg_match('/Plugin\s*Name:\s*([^\n\r]+)/i', $file_content);
		$has_version = preg_match('/Version:\s*([^\s\n\r\*\/]+)/i', $file_content);

		if (!$has_plugin_name) {
			return new WP_Error(
				'invalid_plugin_header',
				__('プラグインファイルに「Plugin Name:」ヘッダーが見つかりませんでした。このリポジトリはWordPressプラグインではない可能性があります。', 'push-from-github')
			);
		}

		if (!$has_version) {
			return new WP_Error(
				'invalid_plugin_header',
				__('プラグインファイルに「Version:」ヘッダーが見つかりませんでした。プラグインヘッダーが正しく記載されているか確認してください。', 'push-from-github')
			);
		}

		// 検証成功
		return true;
	}
}
