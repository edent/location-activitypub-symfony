# WORK IN PROGRESS

A basic ActivityPub server for sharing your location. Written in PHP / Symfony

This *only* allows you to post messages to your followers.  That's all it does.  It won't record favourites or reposts. There's no support for following other accounts or receiving replies.  It cannot delete posts nor can it verify signatures. It doesn't have a database or any storage beyond flat files.

But it will happily send messages and allow itself to be followed.

With thanks to https://rknight.me/blog/building-an-activitypub-server/ and https://justingarrison.com/blog/2022-12-06-mastodon-files-instance/ and https://tinysubversions.com/notes/activitypub-tool/