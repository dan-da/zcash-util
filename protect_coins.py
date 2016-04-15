#!/usr/bin/env python
# author: danda

import sys
import string
import getopt
import subprocess
import json

# Support Functions
def json_decode( buf ):
	return json.loads(buf, strict=False)

def json_encode( buf ):
	return json.dumps(buf)

def json_encode_pretty( buf ):
	return json.dumps(buf, indent=4, sort_keys=True)

def get_params():

        opts,args = getopt.getopt( sys.argv[1:], '', [ 'unspent=', 'fee=', 'zcash-cli=', 'verbosity=', 'help' ] )

        params = {}
	for x in opts:
		name = x[0].lstrip('-')
		params[name] = x[1]

        params['zcash-cli'] = params['zcash-cli']  if 'zcash-cli' in params else './src/zcash-cli'
        params['unspent']   = params['unspent']    if 'unspent'   in params else None
        params['fee']       = float(params['fee']) if 'fee'       in params else 0
        params['verbosity'] = params['verbosity']  if 'verbosity' in params else 'debug'
        params['help'] = 'help' in params

	return params

def process_args( ):

	params = get_params()

	if( params['help'] ): 
		printhelp()
		sys.exit(1)

	if params['unspent'] == None:
		printhelp()
		raise ValueError( "--unspent is required" )

	return params

def myecho( str, level ):
	params = get_params()
	plevel = params['verbosity']

	levels = {'silent' : 0, 'errors' : 1, 'summaries' : 2, 'results' : 3, 'debug' : 4}
	plevelidx = levels[plevel]
	levelidx = levels[level]

	if( levelidx <= plevelidx ):
		print str

def process_unspent( params ):

	# Obtain unspent coins
	showheader( "Listing Unspent Coins" )
	unspent = zcash_cli_rpc( 'listunspent' )
	showresult( unspent )

	if( len(unspent) <= 0 ):
        	myecho( "\n\nNo unspent coins to process.  Quitting!\n", 'errors' )
	        exit(0)

	choice = params['unspent']
	list = []
	if( choice == 'first' ):
		list = [ unspent[0] ]
	elif ( choice == 'last' ):
		list = [ unspent[len(unspent)-1] ]
	elif ( choice == 'all' ):
		list = unspent
	else:
		items = choice.split(',')
		for txid in items:
			# validate that each txid is present in unspent list.
			utxo = tx_in_unspent( txid.strip(), unspent )
			if( utxo == None ):
				raise ValueError( "tx not in utxo list. (txid: txid" )
			list.append( utxo )


	showheader( "Unspent Coins Chosen" )
	myecho ("  User's choice: choice\n\n", 'results')
	showresult( list )

	return list

def tx_in_unspent( txid, unspent ):
	for utxo in unspent:
		if( txid == utxo['txid'] ):
			return utxo
	return None

def zcash_cli( argstr ):

	params = get_params()
        cmd = '%s %s' % ( params['zcash-cli'], argstr )
        myecho( "\nexecuting: %s\n\n" % (cmd), 'debug' )
        buf = subprocess.check_output(cmd, universal_newlines=True, shell=True, stderr=subprocess.STDOUT)
        is_json = buf[0] in ['[', '{']
        return json_decode( buf ) if is_json else buf.strip()

def escapeshellarg(arg):
	if type(arg) in [str, unicode]:
		return "\\'".join("'" + p + "'" for p in arg.split("'"))
#		return string.replace( tmp, "\n", '' );
	return repr(arg)

def zcash_cli_rpc( command, args=None ):
        call = command + " "
        if( args != None ):
                for v in args:
                        call = call + escapeshellarg(v) + ' '
        return zcash_cli( call )

def showresult( data ):
        myecho ("\nResult:\n", 'results')
        myecho (json_encode_pretty( data ) + "\n\n", 'results')

def showheader( text ):
	myecho( "\n\n-- %s --\n" % (text), 'summaries' )

def printhelp():
         
	buf = '''
   protect_coins.py --unspent=<arg>

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

'''

	print buf


# First, process CLI args.
params = process_args()

# Obtain unspent coins.  May throw exception.
unspent = process_unspent( params )

# Generate an address.
showheader( "Generating Address" )
keyinfo = zcash_cli_rpc( 'zcrawkeygen' )
showresult( keyinfo )


# Create raw Tx
txbuflist = []
total = 0
for u in unspent:
	obj = {'txid' : u['txid'], 'vout' : 0}
	txbuflist.append( json_encode( obj ) )
	total += u['amount']
txbuf = '[%s]' % ( ','.join( txbuflist ) )

showheader( "Creating Raw Tx" )
rawtx = zcash_cli_rpc( 'createrawtransaction', [txbuf, '{}'] )
showresult( rawtx )

# send to our private zcash address

showheader( "Calling zcrawpour" )
fee = params['fee']
param = { keyinfo['zcaddress'] : total - fee }
pour = zcash_cli_rpc( 'zcrawpour', [rawtx, '{}', json_encode( param ), total, fee] )
showresult( pour )

# Sign the Tx
showheader( "Signing Tx" )
sig = zcash_cli_rpc( 'signrawtransaction', [pour['rawtxn']] )
showresult( sig )


# Send the Tx to network
showheader( "Sending the Tx to ourself" )
tx = zcash_cli_rpc( 'sendrawtransaction', [sig['hex']] )
showresult( tx )


# Decrypt received Tx
showheader( "Decrypting the received Tx" )
received = zcash_cli_rpc( 'zcrawreceive', [keyinfo['zcsecretkey'], pour['encryptedbucket1']] )
showresult( received )

# Done
showheader( "Done!" )
exit(0)


