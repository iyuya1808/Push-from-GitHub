=== Push from GitHub ===

Contributors: iyuya0623
Tags: github, plugin management, updates, private repository, deployment
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.1.9
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GitHubで管理されているWordPressプラグインを自動的に導入・更新するプラグイン。非公開リポジトリにも対応。  

## 概要

Push from GitHubは、GitHubで管理されているWordPressプラグインをWordPress管理画面から直接管理できるプラグインです。公開リポジトリだけでなく、非公開リポジトリにも対応しており、Personal Access Tokenを使用してアクセスできます。

## 主な機能

- **GitHubリポジトリ連携**: GitHubリポジトリからプラグインを自動的に取得・更新
- **非公開リポジトリ対応**: Personal Access Tokenを使用して非公開リポジトリにアクセス
- **更新方法の選択**: タグ（リリース）またはブランチから更新を取得
- **自動バックアップ**: 更新前に自動的にバックアップを作成（最大5つまで保持）
- **ロールバック機能**: 更新前のバージョンに簡単に戻すことが可能
- **ログ機能**: すべての操作をログに記録し、管理画面で確認可能
- **通知機能**: 更新やエラーを管理画面で通知（メール通知も可能）
- **多言語対応**: 日本語、英語に対応

## 要件

- WordPress 5.0以上
- PHP 7.0以上
- ZipArchive拡張機能（ZIPファイルの展開に必要）
- GitHubアカウント（非公開リポジトリを使用する場合）
- Personal Access Token（非公開リポジトリを使用する場合）

## インストール

1. プラグインディレクトリに `github-push` フォルダをアップロード
2. WordPress管理画面の「プラグイン」メニューから「Push from GitHub」を有効化
3. 管理画面の「PFG」メニューから設定を開始

## 使用方法

### 1. プラグインの登録

1. 管理画面の「PFG」→「GitHub設定」にアクセス
2. 「追加」ボタンをクリック
3. 以下の情報を入力:
   - **GitHubリポジトリURL**: 管理したいGitHubリポジトリのURL
   - **更新方法**: タグを使用するか、ブランチを使用するか
   - **ブランチ名**: ブランチを使用する場合のブランチ名（デフォルト: main）
   - **プラグインスラッグ**: WordPressにインストールされているプラグインのスラッグ（例: `my-plugin/my-plugin.php`）
   - **Personal Access Token**: 非公開リポジトリを使用する場合に必要

### 2. 更新のチェックと適用

- 登録されたプラグイン一覧から「更新チェック」ボタンで更新を確認
- 更新が利用可能な場合は「更新」ボタンが表示され、ワンクリックで更新可能
- 更新前に自動的にバックアップが作成されます

### 3. ロールバック

- 「ログ」ページから過去の更新履歴を確認
- 更新成功のログには「このバージョンに戻す」ボタンが表示され、ワンクリックでロールバック可能

### 4. ログの確認

- 「PFG」→「ログ」からすべての操作履歴を確認
- プラグイン別にフィルタリング可能
- ページネーション対応

### 5. 一般設定

- 「PFG」→「一般設定」から言語設定を変更可能
- プラグイン情報の確認

## 更新方法について

### タグを使用する場合

- GitHubのリリースタグ（例: v1.0.0, v1.2.3）から最新バージョンを取得
- セマンティックバージョニングに適している
- 安定版のリリースを管理する場合に推奨

### ブランチを使用する場合

- 指定したブランチ（例: main, develop）の最新コミットから更新を取得
- 継続的な開発や最新の変更を常に反映したい場合に適している
- プラグインファイルの `Version:` ヘッダーからバージョン情報を取得

## Personal Access Tokenの作成方法

1. GitHubの [Settings > Developer settings > Personal access tokens](https://github.com/settings/tokens) にアクセス
2. 「Generate new token (classic)」をクリック
3. 必要なスコープを選択:
   - 非公開リポジトリ: `repo` スコープ
   - 公開リポジトリのみ: `public_repo` スコープ
4. トークンを生成し、安全に保管
5. プラグイン設定画面でトークンを入力

**注意**: トークンは機密情報です。他人に共有せず、漏洩した場合はすぐにGitHubで無効化してください。

## バックアップについて

- 更新前に自動的にバックアップが作成されます
- バックアップは `wp-content/uploads/github-push-backups/` に保存されます
- 各プラグインにつき最大5つのバックアップを保持（古いものから自動削除）
- バックアップからロールバックが可能

## ログについて

- すべての操作（更新チェック、更新、ロールバックなど）がログに記録されます
- ログはデータベースに保存され、管理画面で確認可能
- プラグイン別にフィルタリング可能
- ページネーション対応（1ページあたり50件）

## 通知について

- 更新成功、ロールバック、エラーなどの通知が管理画面に表示されます
- 一般設定でメール通知を有効化することも可能（今後実装予定）

## トラブルシューティング

### プラグインファイルが見つからない

- プラグインスラッグが正しく入力されているか確認
- GitHubリポジトリ内にプラグインファイルが存在するか確認
- ブランチ名が正しいか確認

### バージョン情報が取得できない

- プラグインファイルの `Version:` ヘッダーが正しく記載されているか確認
- タグを使用する場合、リポジトリにタグが作成されているか確認

### 非公開リポジトリにアクセスできない

- Personal Access Tokenが正しく入力されているか確認
- トークンに `repo` スコープが付与されているか確認
- トークンが有効期限内か確認

### ZIPファイルの展開に失敗する

- PHPのZipArchive拡張機能が有効になっているか確認
- サーバーの一時ディレクトリに書き込み権限があるか確認

## ファイル構造

```
github-push/
├── admin/
│   ├── class-settings.php          # 設定画面クラス
│   └── views/
│       ├── settings-page.php       # 設定画面テンプレート
│       └── edit-plugin-form.php    # プラグイン編集フォーム
├── assets/
│   ├── css/
│   │   └── admin.css               # 管理画面用CSS
│   └── js/
│       └── admin.js                # 管理画面用JavaScript
├── includes/
│   ├── class-github-api.php        # GitHub API連携クラス
│   ├── class-logger.php            # ログ機能クラス
│   ├── class-notifications.php     # 通知機能クラス
│   ├── class-rollback.php          # ロールバック機能クラス
│   └── class-updater.php           # プラグイン更新処理クラス
├── languages/
│   ├── github-push.pot             # 翻訳テンプレート
│   ├── github-push-en_US.po       # 英語翻訳
│   └── github-push-en_US.mo       # 英語翻訳（コンパイル済み）
├── github-push.php                 # メインプラグインファイル
└── README.md                       # このファイル
```

## 開発者向け情報

### クラス構造

- `GitHub_Push`: メインクラス（シングルトンパターン）
- `GitHub_Push_Github_API`: GitHub API連携を担当
- `GitHub_Push_Updater`: プラグイン更新処理を担当
- `GitHub_Push_Rollback`: ロールバック処理を担当
- `GitHub_Push_Logger`: ログ記録を担当
- `GitHub_Push_Notifications`: 通知機能を担当
- `GitHub_Push_Settings`: 設定画面を担当

### データベーステーブル

- `wp_github_push_logs`: ログを保存するテーブル（プラグイン有効化時に自動作成）

### オプション

- `github_push_plugins`: 登録されたプラグイン情報
- `github_push_backups`: バックアップ情報
- `github_push_options`: 一般設定
- `github_push_notices`: 通知情報

### フック

プラグインは以下のWordPressフックを使用しています:

- `plugins_loaded`: プラグインの初期化
- `admin_init`: 管理画面の初期化
- `admin_menu`: 管理メニューの追加
- `admin_enqueue_scripts`: 管理画面アセットの読み込み
- `admin_notices`: 通知の表示
- `plugin_row_meta`: プラグイン一覧ページにメタ情報を追加

### Ajaxエンドポイント

- `github_push_check_update`: 更新チェック
- `github_push_update_plugin`: プラグイン更新
- `github_push_rollback`: ロールバック
- `github_push_get_repo_info`: リポジトリ情報取得

## ライセンス

GPL v2 or later

## 開発者

**テクノフィア (Technophere)**

- ウェブサイト: https://technophere.com
- お問い合わせ: https://technophere.com/contact

## == Frequently Asked Questions ==

### 非公開リポジトリを使用できますか？

はい、Personal Access Tokenを使用することで非公開リポジトリにもアクセスできます。GitHubのSettings > Developer settings > Personal access tokensからトークンを生成してください。

### どのような更新方法がありますか？

タグ（リリース）またはブランチから更新を取得できます。タグを使用する場合はセマンティックバージョニングに適しており、ブランチを使用する場合は継続的な開発に適しています。

### バックアップは自動的に作成されますか？

はい、更新前に自動的にバックアップが作成されます。各プラグインにつき最大5つのバックアップを保持し、古いものから自動削除されます。

### ロールバックは可能ですか？

はい、ログページから過去の更新履歴を確認し、「このバージョンに戻す」ボタンでワンクリックでロールバック可能です。

### PHPのZipArchive拡張機能が必要ですか？

はい、ZIPファイルの展開に必要です。サーバーで有効になっているか確認してください。

## == Changelog ==

### 1.1.9
* 現在のバージョン

### 1.1.8
* セキュリティ改善とコード品質向上

### 1.1.6
* バグ修正とパフォーマンス改善

### 1.1.5
* バグ修正とパフォーマンス改善

### 1.0.0
* 初回リリース
* GitHubリポジトリ連携機能
* 非公開リポジトリ対応
* 自動バックアップ機能
* ロールバック機能
* ログ機能
* 通知機能
* 多言語対応（日本語、英語）

## == Upgrade Notice ==

### 1.1.9
推奨アップグレード。セキュリティ改善とコード品質向上が含まれています。

### 1.1.8
推奨アップグレード。セキュリティ改善とコード品質向上が含まれています。

### 1.1.6
推奨アップグレード。バグ修正とパフォーマンス改善が含まれています。

### 1.0.0
初回リリースです。新規インストールをお試しください。

## == Screenshots ==

1. プラグイン設定画面 - GitHubリポジトリの登録と管理
2. ログ画面 - すべての操作履歴の確認
3. 一般設定画面 - 言語設定とプラグイン情報

## サポート

問題が発生した場合や機能要望がある場合は、以下の方法でお問い合わせください:

- ウェブサイト: https://technophere.com/contact
- GitHub Issues: （リポジトリが公開されている場合）

## 注意事項

- このプラグインはGitHub APIを使用します。APIレート制限に注意してください（認証なし: 60リクエスト/時、認証あり: 5,000リクエスト/時）
- 非公開リポジトリを使用する場合は、Personal Access Tokenの管理に注意してください
- バックアップは自動的に作成されますが、重要なデータは別途バックアップを取ることを推奨します
- プラグインの更新前に、必ずテスト環境で動作確認を行うことを推奨します

