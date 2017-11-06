# letsencrypt-gehirn
Let's Encrypt hook script for Gehirn DNS Web Service API
## これはなに？
- Gehirn DNS で Let's Encrypt をDNS認証（DNS-01）して使うための PHP Hook Script です。
## 必要な環境
- ルート権限
- php5.6 またはそれ以降
- [dehydrated](https://github.com/lukas2511/dehydrated) ※旧 letsencrypt.sh
- Gehirn DNS Web Service API Wrapper Class
- [PHPMailer 6.x Library](https://github.com/PHPMailer/PHPMailer)
## 使い方
### Gehirn DNS を使う準備
- Gehirn DNS は１日２円で使える Web Service API を備えたDNSサービスです。
- Gehirn DNS はバージョン管理を備えています。
- [Gehirn DNS](https://www.gehirn.jp/gis/dns.html) でサインアップします。
- APIキーを設定したらトークンとシークレットを得ます。
- APIキーを設定したら権限管理からDNSのゾーンでアクセスを許可するゾーンをフルアクセスにします。
- APIについては [公式 Gehirn Web Services API Documentation](https://support.gehirn.jp/apidocs/) または [非公式 Gehirn DNS API 仕様](https://yosida95.com/2015/12/18/gehirn_dns_api_spec.html) を参考にします。
### ソースをサーバーに配置
- letsencrypt-gehirn.zip をサーバー上の適当に場所にダウンロードします。
```
unzip letsencrypt-gehirn.zip
cd letsencrypt
chmod +x letsencrypt-gehirn.php
```
- letsencrypt　に dehydrated スクリプトを置き、実行権限を付けます。
- letsencrypt　の domains.txt を修正します。
- letsencrypt　の letsencrypt-gehirn.php の START_WEB, STOP_WEB, CERT_DIR, GMAIL_USER, GMAIL_PASS, SEND_TO を設定します。
- letsencrypt/lib/gehirnDNS.php の GEHIRN_API_TOKEN, GEHIRN_API_SECRET を設定します。
- letsencrypt/lib/PHPMailer フォルダにPHPMailerのsrcフォルダとlanguageフォルダを移動します。
### 初回 Let's Encrypt Account 登録作業
メールアドレスは使いません。
```
./dehydrated --register --accept-terms
```
これでaccountフォルダが作成されます。
### SSL証明書の取得
```
./dehydrated --cron --challenge dns-01 --hook ./letsencrypt-gehirn.php
```
正常に終わればcertsフォルダにSSL証明書が作成されて、CERT_DIRにもコピーされます。
エラーがあればメッセージに従って対応します。
### CRON設定について
以下は毎朝７時５分にスクリプトを実行するCRON設定です。毎朝実行されますが、証明書の有効期限が３０日を切らない限り、証明書の再取得はされません。
```
5 7 * * * /root/letsencrypt/dehydrated --cron --challenge dns-01 --hook /root/letsencrypt/letsencrypt-gehirn.php
```
### hook機能について
これは dehydrated の docs/example/hook.sh に書かれていたコメントを機械翻訳したものです。
#### deploy_challenge
- このフックは、リストされている代替名を含め、検証が必要なすべてのドメインに対して1回呼び出されます。
#### clean_challenge
- このフックは、検証が成功したかどうかにかかわらず、各ドメインの検証を試みた後に呼び出されます。ここでは不要になったファイルやDNSレコードを削除できます。
#### deploy_cert
- このフックは、生成された証明書ごとに１回呼び出されます。たとえば、新しい証明書をサービス固有の場所にコピーして、サービスを再ロードすることができます。
#### unchanged_cert
- このフックはまだ有効で再発行されなかった１つの証明書ごとに１回呼ばれます。
#### invalid_challenge
- チャレンジレスポンスが失敗した場合にこのフックが呼び出されるため、ドメインオーナーはそれに応じて行動出来ます。</dt>
#### request_failure
- このフックは、HTTP要求が失敗したとき（ACMEサーバがビジー状態のとき、エラーが返ったときなど）に呼び出されます。これは'2'で始まらない応答コードで呼び出されます。管理者にリクエストに関する問題を警告するのに便利です。
#### startup_hook
- このフックはcronコマンドの前に呼び出され、いくつかの初期タスク（Webサーバの起動など）を行います。
#### exit_hook
- このフックは、cronコマンドの最後に呼び出され、最終的な（クリーンアップまたはその他の）タスクを実行するために使用できます。
