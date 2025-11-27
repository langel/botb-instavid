Dependencies you'll need from whatever package manager you are stuck with: 
ffmpeg, imagemagick, php, php-gd 

Primary usage:
`./instavid.sh {entry_id}` (detects automagically if entry is less than 60 seconds and renders a short video)

This project currently requires you to have a BotB account and to add the account data from your cookie into a config.ini.
There is an example .ini file attached in the repo. Simply copy and rename it to ``config.ini``.
Then add ``windows`` if you're using Windows, or anything else if you're not using windows.
Also add the cookie ``user_id``, ``botbr_id`` and ``serial`` from your BotB cookie (after you are logged in).
