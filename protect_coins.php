#!/usr/bin/env php
<?php
// author: danda

// First, process CLI args.
$params = process_args( $argv );

// Obtain unspent coins.  May throw exception.
$unspent = process_unspent( $params );

// Generate an address.
showheader( "Generating Address" );
$keyinfo = zcash_cli_rpc( 'zcrawkeygen' );
showresult( $keyinfo );


// Create raw Tx
$txbuflist = [];
$total = 0;
foreach( $unspent as $u ) {
	$obj = ['txid' => $u['txid'], 'vout' => 0];
	$txbuflist[] = json_encode( $obj );
	$total += $u['amount'];
}
$txbuf = sprintf( '[%s]', implode( ',', $txbuflist ) );

showheader( "Creating Raw Tx" );
$rawtx = zcash_cli_rpc( 'createrawtransaction', [$txbuf, '{}'] );
showresult( $rawtx );

// send to our private zcash address

showheader( "Calling zcrawpour" );
$fee = $params['fee']; 
$param = [ $keyinfo['zcaddress'] => $total - $fee ];
$pour = zcash_cli_rpc( 'zcrawpour', [$rawtx, '{}', json_encode( $param ), $total, $fee] );
showresult( $pour );

// Sign the Tx
showheader( "Signing Tx" );
$sig = zcash_cli_rpc( 'signrawtransaction', [$pour['rawtxn']] );
showresult( $sig );


// Send the Tx to network
showheader( "Sending the Tx to ourself" );
$tx = zcash_cli_rpc( 'sendrawtransaction', [$sig['hex']] );
showresult( $tx );


// Decrypt received Tx
showheader( "Decrypting the received Tx" );
$received = zcash_cli_rpc( 'zcrawreceive', [$keyinfo['zcsecretkey'], $pour['encryptedbucket1']] );
showresult( $received );

// Done
showheader( "Done!" );
exit(0);



// Support Functions

function get_params() {

        $params = getopt( 'g', array( 'unspent:', 'fee:', 'zcash-cli:', 'verbosity:', 'help' ) );

        $params['zcash-cli'] = @$params['zcash-cli'] ?: './src/zcash-cli';
        $params['unspent'] = @$params['unspent'] ?: null;
        $params['fee'] = @$params['fee'] ? (float)$params['fee']: null;
        $params['verbosity'] = @$params['verbosity'] ?: 'debug';
        $params['help'] = isset( $params['help'] );

	return $params;
}

function process_args( $argv ) {

	$params = get_params();

	if( $params['help'] ) {
		printhelp();
		exit(1);
	}
	if( !$params['unspent'] ) {
		printhelp();
		throw new Exception( "--unspent is required" );
	}

	return $params;
}

function myecho( $str, $level ) {
	$params = get_params();
	$plevel = $params['verbosity'];

	static $levels = ['silent' => 0, 'errors' => 1, 'summaries' => 2, 'results' => 3, 'debug' => 4];
	$plevelidx = @$levels[$plevel];
	$levelidx = @$levels[$level];

	if( $levelidx <= $plevelidx ) {
		echo $str;
	}
}

function process_unspent( $params ) {

	// Obtain unspent coins
	showheader( "Listing Unspent Coins" );
	$unspent = zcash_cli_rpc( 'listunspent' );
	showresult( $unspent );

	if( !is_array( $unspent ) || !count( $unspent ) ) {
        	myecho( "\n\nNo unspent coins to process.  Quitting!\n", 'errors' );
	        exit(0);
	}

	$choice = $params['unspent'];
	$list = [];
	if( $choice == 'first' ) {
		$list = [ $unspent[0] ];
	}
	else if ( $choice == 'last' ) {
		$list = [ array_pop( $unspent ) ];
	}
	else if ( $choice == 'all' ) {
		$list = $unspent;
	}
	else {
		$items = explode(',', $choice);
		foreach( $items as $txid ) {
			// validate that each txid is present in unspent list.
			$utxo = tx_in_unspent( trim($txid), $unspent );
			if( !$utxo ) {
				throw new Exception( "tx not in utxo list. (txid: $txid" );
			}
			$list[] = $utxo;
		}
	}


	showheader( "Unspent Coins Chosen" );
	myecho ("  User's choice: $choice\n\n", 'results');
	showresult( $list );

	return $list;
}

function tx_in_unspent( $txid, $unspent ) {
	foreach( $unspent as $utxo ) {
		if( $txid == $utxo['txid'] ) {
			return $utxo;
		}
	}
	return null;
}

function zcash_cli( $argstr ) {

	$params = get_params();
        $cmd = sprintf( '%s %s', $params['zcash-cli'], $argstr );
        myecho( "\nexecuting: $cmd\n\n", 'debug' );
        exec( $cmd, $output, $rc );
        if( $rc != 0 ) {
                throw new Exception( "cmd returned non-zero exit code: $rc.  cmd was:\n$cmd" );
        }
        $buf = implode( "\n", $output );
        $is_json = in_array( $buf{0}, ['[', '{'] );
        return $is_json ? json_decode( $buf, true ) : $buf;
}

function zcash_cli_rpc( $command, $args=null ) {
        $call = "$command ";
        if( is_array( $args ) ) {
                foreach( $args as $k => $v ) {
                        $call .= escapeshellarg($v) . ' ';
                }
        }
        return zcash_cli( $call );
}

function showresult( $data ) {
        myecho ("\nResult:\n", 'results');
        myecho (json_encode( $data, JSON_PRETTY_PRINT ) . "\n\n", 'results');
}

function showheader( $text ) {
	myecho( "\n\n-- $text --\n", 'summaries' );
}

function printhelp() {
         
	$buf = <<< END

   protect_coins.php --unspent=<arg>

   This script makes public funds (utxo) into private funds.

   Required:

    --unspent=<arg>	  all|first|last|<txlist>
                           all    = convert all unspent outputs
                           first  = convert first unspent output
                           last   = convert last unspent output
                           txlist = one or more txid, comma separated.

   Optional:
    
    --fee=<amt>           fee amount.  default = 0

    --zcash-cli=<path>    path to zcash-cli.  default = './src/zcash-cli'

    --verbosity=<level>   silent|results|full|debug   default = full

    --help                display usage information


END;

	fprintf( STDERR, $buf );       

}










