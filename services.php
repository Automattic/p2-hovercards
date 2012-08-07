<?php

// Core Trac Tickets
p2_hovercards_add_service( 'core_trac', '#(\d+)-(core|ios|android|blackberry|nokia|webos|plugins|bbpress|supportpress|glotpress|backpress|windows)', '<a href="http://$2.trac.wordpress.org/ticket/$1">$0</a>', '$1-$2', function( $args ) {
	$ticket = explode( '-', $args[ 'id' ] );
	$id = $ticket[0];
	$trac = $ticket[1];
	$url = esc_url( $args[ 'url' ] );
	$service = $args[ 'service' ];

	// Get ticket info from CSV
	$cache_key = "$id-$trac";
	$csv = wp_cache_get( $cache_key, 'p2_hovercards' );
	if ( false === $csv ) {
		$request = sprintf( "http://%s.trac.wordpress.org/ticket/%s?format=csv", esc_attr( $trac ), intval( $id ) );
		$response = wp_remote_get( $request );
		if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
			$csv = '';	
		} else {
			$csv = wp_remote_retrieve_body( $response );
		}
		wp_cache_set( "$id-$trac", $csv, 'p2_hovercards' );
	}

	if ( empty( $csv ) )
		return false;

	// Create the array of info from the CSV
	$header = explode( "\n", $csv );
	$header = str_getcsv( $header[0] );
	$csv = str_getcsv( $csv );
	$ticketdata = array();
	for( $i = count( $header ); $i < count( $csv ); $i++ ) {
		$ticketdata[ $header[ $i % count( $header ) + 1 ] ] = $csv[ $i ];
	}

	$title = esc_attr( $ticketdata['summary'] );
	$subtitle = sprintf( "Ticket #%d", intval( $id ) );
	$description =  wpautop( apply_filters( 'p2_hovercards_excerpt', esc_html( $ticketdata['description'] ) ) );

	if ( '' != $ticketdata['owner'] )
		$meta['Owner'] = esc_attr( $ticketdata['owner'] );

	if ( '' != $ticketdata['reporter'] && !isset( $meta['Owner'] ) )
		$meta['Reporter'] = esc_attr( $ticketdata['reporter'] );

	if ( '' != $ticketdata['type'] )
		$meta['Type'] = esc_attr( $ticketdata['type'] );

	if ( '' != $ticketdata['version'] )
		$meta['Version'] = esc_attr( $ticketdata['version'] );

	if ( '' != $ticketdata['status'] )
		$meta['Status'] = esc_attr( $ticketdata['status'] );

	if ( '' != $ticketdata['component'] )
		$meta['Component'] = esc_attr( $ticketdata['component'] );

	if ( '' != $ticketdata['severity'] )
		$meta['Severity'] = esc_attr( $ticketdata['severity'] );

	// Get comments from RSS
	if ( ! $rss = wp_cache_get( "$id-$trac-comments", 'p2_hovercards' ) ) {
		$rss = wp_remote_retrieve_body( wp_remote_get( sprintf( "http://%s.trac.wordpress.org/ticket/%s?format=rss", esc_attr( $trac ), intval( $id ) ) ) );
		wp_cache_set( "$id-$trac-comments", $rss, 'p2_hovercards' );
	}
	$rss = new SimpleXmlElement( $rss );
	if ( !isset( $rss->channel ) )
		return false;

	$commentdata = $rss->channel->item;
	$ns = $rss->getNamespaces(true); $comments = array(); $i = 0;
	foreach ( $commentdata as $comment ) {
		$dc = $comment->children( $ns['dc'] );

		$comments[$i]['date'] = date( get_option( 'time_format' ) . ' \o\n ' . get_option( 'date_format' ), strtotime( (string) $comment->pubDate ) );
		$comments[$i]['author'] = esc_attr( $dc->creator );
		$comments[$i]['comment'] = apply_filters( 'p2_hovercards_excerpt', wp_kses_post( $comment->description ) );

		$i++;
		$updated = $comment->pubDate;
	}

	$comments = array_slice( array_reverse( $comments ), 0, 2 );

	if ( count( $comments ) > 0 )
		$meta['Updated'] = human_time_diff( strtotime( (string) $updated ) ) . ' ago';

	// Return an array with all the things we need
	return compact( 'title', 'subtitle', 'url', 'description', 'comments', 'meta' );
} );

// Core SVN revisions
p2_hovercards_add_service( 'core_svn', 'r(\d+)-(core|ios|android|blackberry|nokia|webos|plugins|bbpress|supportpress|glotpress|backpress|windows)', '<a href="http://$2.trac.wordpress.org/changeset/$1">$0</a>', '$1-$2', function( $args ) {
	$ticket = explode( '-', $args[ 'id' ] );
	$id = $ticket[0];
	$svn = $ticket[1];
	$url = esc_url( $args[ 'url' ] );
	$service = $args[ 'service' ];

	// Get ticket info from CSV
	if ( ! $info = wp_cache_get( "$id-$trac", 'p2_hovercards' ) ) {
		$info = svn_log( sprintf( "https://%s.svn.wordpress.org/", $svn ), intval( $id ) );
		wp_cache_set( "$id-$trac", $info, 'p2_hovercards' );
	}

	if ( ! $info )
		return false;

	$changeset = $info[0];
	$timestamp = intval( strtotime( $changeset['date'] ) );

	$title = apply_filters( 'p2_hovercards_excerpt', esc_html( $changeset['msg'] ), 45 );
	$subtitle = sprintf( "Changeset #%d", intval( $id ) );
	$description = wpautop( apply_filters( 'p2_hovercards_excerpt', esc_html( $changeset['msg'] ) ) );

	$meta['Author'] = $changeset['author'];
	$meta['Date'] = date( get_option( 'date_format' ), $timestamp );
	if ( time() - $timestamp < 864000 )
		$meta['Updated'] = human_time_diff( $timestamp ) . ' ago';

	// Return an array with all the things we need
	return compact( 'title', 'subtitle', 'url', 'description', 'meta' );
} );
