<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Routehub

Routehub は、Laravel で構築された社内業務管理システムです。

既存のスプレッドシートや FileMaker ベースの業務を置き換え、スケジュール、欠席、代行、カレンダー、予報、交通費、講師情報などを一元管理することを目的としています。

## 概要

Routehub は、複数の地域・部署にまたがる業務データを管理するためのシステムです。

主な目的は以下の通りです。

- 分散している業務データの一元管理
- スケジュール、欠席、代行状況の可視化
- 手動確認や連絡ミスの削減
- 地域・部署単位でのアクセス制御
- カレンダーや予報画面による業務状況の把握

## 主な機能

### ユーザー・スコープ管理

- ユーザー管理
- Spatie Permission を利用したロール・権限管理
- 地域・部署による管理スコープ制御
- セッションによる現在のスコープ選択

### カレンダー

- FullCalendar を利用したスケジュール表示
- 通常スケジュールの表示
- イベント割当
- 欠席・有給の表示
- 祝日・会社休暇の表示
- 地域・部署スコープに応じたカレンダー表示制御

### スケジュール管理

- スケジュールライン管理
- スケジュール詳細管理
- レッスン・スクール情報との連携
- コピー・一括更新機能
- スクールタイムテーブルとの連携

### 欠席・有給管理

- 欠席・有給申請
- 承認ワークフロー
- 有給残高管理
- 有給消化履歴管理
- 承認済み欠席のカレンダー反映

### 予報カレンダー

- 祝日・会社休暇の表示
- 代行数の集計
- 代行詳細モーダル
- 欠席者を考慮した予報表示

### 交通費管理

- 月別交通費レポート管理
- 交通費明細編集
- JSpreadsheet を利用した入力画面
- 行単位の保存・削除機能

### 通勤定期申告

- 定期券情報の登録
- 管理者による代理登録
- 経路申告との連携

### 経路申告

- 通勤経路の申告・登録
- 管理者による申告レポート確認
- 管理者による代理申告

### メッセージ・お知らせ

- 管理者から従業員へのメッセージ送信
- 受信メッセージの確認・既読管理
- コメント機能
- 添付ファイル対応

### 残業管理

- 処理中の残業申請一覧表示

### CSV インポート・エクスポート

- ユーザーCSVインポート・エクスポート
- スケジュール関連データのCSV処理
- 既存データからの移行サポート

## ロール設計

| ロール | 説明 |
|---|---|
| `general` | 一般ユーザー。自分のデータのみ閲覧・申請可能 |
| `admin` | 管理者。スコープ内の他ユーザーのデータを閲覧・編集可能 |
| `super_admin` | 最高管理者。ロール変更承認・全通知管理が可能 |

## バッチ処理（スケジュール済みコマンド）

以下のコマンドが自動実行されます。スケジューラーが動いていない場合は手動実行も可能です。

| コマンド | スケジュール | 内容 |
|---|---|---|
| `expenses:generate-monthly` | 毎月2日 0:00 | 当月分の交通費レポートを自動生成 |
| `expenses:cleanup-empty` | 毎月3日 0:00 | 2ヶ月前の空レポートを削除 |
| `leave:grant` | 手動実行 | 有給を一斉付与（例: `php artisan leave:grant 10 --date=2025-04-01`） |

スケジューラーはサーバーの cron に以下を登録することで動作します。

```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

## 技術構成

- PHP 8.2
- Laravel 10
- PostgreSQL 16
- Docker
- Apache
- Vite
- JavaScript / Vue
- FullCalendar
- JSpreadsheet
- Bootstrap
- Spatie Laravel Permission

## ローカル開発環境

### Docker コンテナ起動

```bash
docker compose up -d
```

### アプリケーションコンテナに入る

```bash
docker exec -it jin_app bash
```

### PHP依存パッケージのインストール

```bash
composer install
```

### Node.js依存パッケージのインストール

```bash
npm install
```

### マイグレーション実行

```bash
php artisan migrate
```

### Seeder 実行

```bash
php artisan db:seed
```

### フロントエンド開発サーバー起動

```bash
npm run dev
```

## データベース

このプロジェクトでは PostgreSQL を使用しています。

ローカル環境の基本設定例は以下の通りです。

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laraveldb
DB_USERNAME=dbuser
DB_PASSWORD=dbpass
```

## Adminer

ローカル環境では、以下のURLから Adminer にアクセスできます。

```text
http://localhost:3033
```

接続設定は以下の通りです。

```text
System: PostgreSQL
Server: pgsql
Username: dbuser
Password: dbpass
Database: laraveldb
```

## ログ設定

Render 環境では、Laravel のログを Render Dashboard に表示するため、stderr へ出力する設定が必要です。

推奨環境変数は以下の通りです。

```env
LOG_STACK=single,stderr
LOG_LEVEL=info
```

一時的なデバッグ時は以下も使用できます。

```env
LOG_LEVEL=debug
```

## デプロイ時の注意点

このプロジェクトは Docker を利用して Render 上で動作することを想定しています。

注意点は以下の通りです。

- Laravel は Render のリバースプロキシ配下で動作する
- `TrustProxies` の設定が必要
- Laravel ログは stderr に出力する必要がある
- PostgreSQL 前提のため、マイグレーションやクエリで MySQL 固有構文に注意する

## テスト

テストは以下のコマンドで実行します。

```bash
php artisan test
```

## 補足

このシステムは現在も開発中です。

業務ロジック、カレンダールール、スコープ制御、予報表示の仕様は、実際の運用要件に合わせて今後も変更される可能性があります。
