=== P2 Hovercards ===
Contributors: betzster
Requires at least: 3.4
Tested up to: 3.4.1
Stable tag: 1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

API for adding hovercards on things like Zendesk, Trac, etc

== Description ==

P2 hovercards allows you to easily add hovercards to your P2 blog. This is useful for support tickets or anything that you might want to know more information about without necessarily clicking a link.

New services can be added with `p2_hovercards_add_service()`. It takes the name of the serivce, the regex pattern to find its tags, the regex replacement for a url and a ticket, and a callback for setting up data for the card. You get an 'id', 'service', and 'url'. The callback should return an array with 'title', 'subtitle', 'url', 'description', 'comments', 'meta', where 'comments' and 'meta' are arrays.

Use the following example or look in `examples.php` for more in depth examples:

	p2_hovercards_add_service( 'core_trac', '#(\d+)', '<a href="http://core.trac.wordpress.org/ticket/$1">$0</a>', '$1', function( $args ){
		$id = (int) $args[ 'id' ];
		$url = $args[ 'url' ];
		$service = $args[ 'service' ];

		// Do stuff with $id, $service, and $url
		
		return compact( 'title', 'subtitle', 'url', 'description', 'comments', 'meta' );
	});

== Screenshots ==

1. Sample hovercard for a core trac ticket

== Changelog ==

= 1.0 =
* Initial Release