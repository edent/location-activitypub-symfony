# WORK IN PROGRESS

A basic ActivityPub server for sharing your location. Written in PHP / Symfony.

Read all about it at https://shkspr.mobi/blog/2024/02/a-tiny-incomplete-single-user-write-only-activitypub-server-in-php/

This *only* allows you to post messages to your followers.  That's all it does.  It won't display favourites or reposts. There's no support for following other accounts or reading replies.  It cannot delete posts nor can it verify signatures. It doesn't have a database or any storage beyond flat files.

But it will happily send messages and allow itself to be followed.

With thanks to https://rknight.me/blog/building-an-activitypub-server/ and https://justingarrison.com/blog/2022-12-06-mastodon-files-instance/ and https://tinysubversions.com/notes/activitypub-tool/

## Deployment

You probably shouldn't deploy this. But if you like to live dangerously...

## Get a domain and set up https

This needs to be set up in a rool domain - `https://example.com` or `https://locate.example.com`. You cannot install it in `https://example.com/locate`

## Create a Symfony 7 app on your host

Follow https://symfony.com/doc/current/setup.html

## Deploy

Copy all the files in this repo to the appropriate folder on your server. You probably want to change the `/public/icon.jpg` and `/public/image.jpg` unless you want your account to look like mine!

## Edit the `.env.local` file

It needs the following parameters:

```
USERNAME="your_new_username"
API_PASSWORD="A_super_secure_password"
HTTP_BASIC_AUTH_USERNAME="USER"
HTTP_BASIC_AUTH_PASSWORD="PASS"
PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
```

Set your username. Don't include the `@`.

Generate a new password.  This protects the API.

The `HTTP_BASIC_AUTH_USERNAME` and `HTTP_BASIC_AUTH_PASSWORD` protect the `/new` page where you compose your check-ins.

Create a public/private keypair.  You can do this in PHP with:

```php
$config = [
	"private_key_bits" => 2048,
	"private_key_type" => OPENSSL_KEYTYPE_RSA,
];

$keypair = openssl_pkey_new($config);
openssl_pkey_export($keypair, $private_key);

$public_key = openssl_pkey_get_details($keypair);
$public_key = $public_key["key"];
print_r($public_key);
print_r($private_key);
```

## Test

Visit `https://YOURDOMAIN.tld/YOUR_USERNAME` - you should see a JSON file.  Also check `/follower`, `/following`, and `/outbox`

## Follow

Go to your Mastodon client - or Fediverse website - and search for `@YOUR_USERNAME@YOURDOMAIN.tld`. It should find your account. If not, try sending a message containing the username.

Follow the account.

Check in `/public/logs/` to see if the follow request was made.

Check `/public/followers.json` to see if the follow request was successful.

## Post

Go to `/new` and enter your username and password. Update your location. Check in.

A new JSON file should appear in `/public/posts/` and in your followers feeds.