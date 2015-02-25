=== MyBB Cross-Postalicious ===
Contributors: mechter
Donate link: http://www.markusechterhoff.com/donation/
Tags: mybb, forum, mybbxp, cross-posting, cross, comments
Requires at least: 3.5
Tested up to: 4.1.1
Stable tag: 1.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Automatically cross-post your Wordpress posts to MyBB, also contains a 'recent forum topics' widget.

== Description ==

**Important:** I am not personally updating/fixing/supporting this plugin any more, please see [this thread](https://wordpress.org/support/topic/a-note-about-updates-fixes-support-etc) for details.

I needed a plugin to:

* Automatically cross-post a new thread to MyBB when I publish a blog post or page
* Automatically link the newly created thread from my Wordpress post as a substitute for comments
* Display a widget to list recent topics, with forum ID whitelist
* Work on a multisite installation
* Keep MyBB as far away from Wordpress as possible (see notes below)

So I scratched my itch and now share the result.

A couple of notes:

* Tested with Wordpress 3.5, 4.1.1 and MyBB 1.6
* Although at the time of writing MyBB seems like the best FOSS forum software no money can buy, the MyBB codebase, to me, seems like an unholy mess and rather dated. I didn't like working with it, I don't trust it and I don't want any of it to touch my Wordpress installation. I don't want the two to share databases and I don't want to interface directly with MyBB code. Therefore, I do a couple of simple database queries and when things get more complex (e.g. posting a thread), I have Wordpress connect to MyBB via HTTP and post just like a regular user. As a matter of fact, your MyBB installation could run on a different server entirely as long as you have access to the MyBB database.
* No single-signon, I like to keep accounts separate.
* Forum posts are not listed as comments, there is a (configurable) link to the discussion thread instead. I actually had a "display posts as comments" feature in at first (still have the code somewhere if you want it), but then took it out for a couple of reasons. It was messy to deal with and in the end I felt that comments are less community oriented than a forum, so I'd rather point my users to the forum saying "Hey look, there is a real community, not just a blog post with comments, join in, have fun!".
* Cross-Posts are to be updated from the edit page of the original post, directly updating a cross-post using your forum will result in the changes being lost the next time you update the original post.
* Deleting an original post will leave the cross-post untouched (you can manually link it to a different original post from the MyBBXP settings page).
* Deleting a cross-post using your forum will cause the original post to behave as if it were never cross-posted (it can be cross-posted anew).

== Installation ==

1. Download, extract, upload to /wp-content/plugins/ folder, activate plugin in wordpress
2. Edit plugin settings to match your board
3. Add the widget, if you want
4. Find the new "MyBB Cross-Postalicious" box on post/page adding/editing and edit its content to your liking

== Frequently Asked Questions ==

= Is this plugin under active development? =

That depends. I will keep it updated for as long as I need it and try and fix any bugs you may encounter, but I'm quite happy with the features as they are. If you'd like more functionality, you'll either have to convince me that I really want that feature, pay me a ton of money, program it yourself, or find someone else to do the job for you. :)

= Does this plugin come with support? =
I don't know. I like to think of myself as a nice guy, so if you run into trouble, I'll try to help out. However I might not check in often to see if people are having trouble.

= Can I fork your plugin? =
It's licensed GPLv3+, go nuts.

== Screenshots ==

No screenshots, just try and see for yourself. In case you don't like it, the uninstall is 100% clean (obviously, your posts and cross-posts will not be deleted).

== Changelog ==

= 1.1 =

fixed widget links
widget no longer displays draft forum posts, thanks kab012345

= 1.0 =

first release for Wordpress 3.5 and MyBB 1.6

== Upgrade Notice ==

no upgrades, no upgrade notices
