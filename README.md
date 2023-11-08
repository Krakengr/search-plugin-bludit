# Search Plugin for Bludit
Search plugin for Bludit

With this plugin you can search your site for posts that have the searched word(s).<!--more-->

This plugin is based on Bludit's search plugin (https://github.com/bludit/bludit/tree/v3.0/bl-plugins/search), however it makes use of the SQLite database instead of JSON files the original plugins uses. The use of the SQLite database has better support for UTF8.

Installation
============
1. Download the plugin.
2. Uncompress the zip file.
3. Copy the folder "db-search" into the folder /bl-plugins/ on the server.

Make sure your server supports SQLite3 before installing this plugin.
