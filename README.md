# zcash-util : Utility scripts for zcash

These are some scripts I am making while experimenting with zcash.
Maybe someone else will find them useful.

So far I just have the protect_coins script, but will likely add more.

## protect_coins.php

### Example Run

( long fields have been abbreviated for brevity )

<pre>
$ ./protect_coins.php --unspent=first


-- Listing Unspent Coins --

executing: ./src/zcash-cli listunspent 


Result:
[
    {
        "txid": "2019...a6f1",
        "vout": 0,
        "address": "mxFMkDZu19QECvqoCn67FQgvYi2Dbsp71Y",
        "scriptPubKey": "2102...32ac",
        "amount": 50,
        "confirmations": 258,
        "spendable": true
    }
]



-- Unspent Coins Chosen --
  User's choice: first


Result:
[
    {
        "txid": "2019...a6f1",
        "vout": 0,
        "address": "mxFMkDZu19QECvqoCn67FQgvYi2Dbsp71Y",
        "scriptPubKey": "2102...32ac",
        "amount": 50,
        "confirmations": 258,
        "spendable": true
    }
]



-- Generating Address --

executing: ./src/zcash-cli zcrawkeygen 


Result:
{
    "zcaddress": "20ad....1070",
    "zcsecretkey": "20e5...cc92"
}



-- Creating Raw Tx --

executing: ./src/zcash-cli createrawtransaction '[{"txid":"2019...a6f1","vout":0}]' '{}' 


Result:
"0100...0000"



-- Calling zcrawpour --

executing: ./src/zcash-cli zcrawpour '0100...1070":49.9}' '50' '0.1' 


Result:
{
    "encryptedbucket1": "04f7...c275",
    "encryptedbucket2": "04e5f...d2c4",
    "rawtxn": "..."
}



-- Signing Tx --

executing: ./src/zcash-cli signrawtransaction '...' 


Result:
{
    "hex": "...",
    "complete": true
}



-- Sending the Tx to ourself --

executing: ./src/zcash-cli sendrawtransaction '0200...330a' 


Result:
"9ae8...230b"



-- Decrypting the received Tx --

executing: ./src/zcash-cli zcrawreceive '20e5...c92' '04f7...c275' 


Result:
{
    "amount": 49.9,
    "bucket": "805b...4c68",
    "exists": false
}



-- Done! --


</pre>



### Usage

<pre>
$ ./protect_coins.php 

   protect_coins.php --unspent=<arg>

   This script makes public funds (utxo) into private funds.

   Required:

    --unspent=<arg>       all|first|last|<txlist>
                           all    = convert all unspent outputs
                           first  = convert first unspent output
                           last   = convert last unspent output
                           txlist = one or more txid, comma separated.

   Optional:

    --zcash-cli=<path>    path to zcash-cli.  default = './src/zcash-cli'

    --verbosity=<level>   silent|results|full|debug   default = full

    --help                display usage information
</pre>


# Requirements

PHP 5.x command-line interpreter installed in your path.

# Installation and Running.

```
 git clone or download/unzip file.
 cd zcash-util
 chmod +x protect_coins.php  ( if necessary )
 ./protect_coins.php --unspent=first
```



