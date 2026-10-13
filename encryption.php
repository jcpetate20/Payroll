<?php

/*
|--------------------------------------------------------------------------
| Payroll System Encryption Helper
|--------------------------------------------------------------------------
| Used to encrypt sensitive employee information.
|
| New encryption:
| AES-256-GCM with authentication tag.
|
| Legacy decryption:
| AES-256-CBC is still supported so previously encrypted
| employee records can continue to be decrypted.
|
| The encryption key is stored outside the web-accessible folder.
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Load Secure Configuration
|--------------------------------------------------------------------------
*/

$config = require __DIR__ . "/../../secure_config.php";

$encryption_key = $config["encryption_key"];


/*
|--------------------------------------------------------------------------
| Encryption Settings
|--------------------------------------------------------------------------
*/

$encryption_cipher = "aes-256-gcm";

$encryption_version = "enc:v2:";


/*
|--------------------------------------------------------------------------
| Convert the configured hexadecimal key into
| a proper 32-byte key for AES-256-GCM.
|--------------------------------------------------------------------------
*/

$gcm_key = hex2bin($encryption_key);


/*
|--------------------------------------------------------------------------
| Encrypt Data
|--------------------------------------------------------------------------
*/

function encrypt_data($data)
{
    global $gcm_key, $encryption_cipher, $encryption_version;

    /*
    |--------------------------------------------------------------------------
    | Empty values do not need encryption.
    |--------------------------------------------------------------------------
    */

    if ($data === null || $data === "") {
        return $data;
    }


    /*
    |--------------------------------------------------------------------------
    | AES-256-GCM uses a 12-byte IV/nonce.
    |--------------------------------------------------------------------------
    */

    $iv_length = openssl_cipher_iv_length($encryption_cipher);

    if ($iv_length === false) {
        return false;
    }

    $iv = random_bytes($iv_length);


    /*
    |--------------------------------------------------------------------------
    | Authentication tag
    |--------------------------------------------------------------------------
    */

    $tag = "";


    /*
    |--------------------------------------------------------------------------
    | Encrypt using AES-256-GCM.
    |--------------------------------------------------------------------------
    */

    $encrypted = openssl_encrypt(
        $data,
        $encryption_cipher,
        $gcm_key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        "",
        16
    );


    /*
    |--------------------------------------------------------------------------
    | Check encryption result.
    |--------------------------------------------------------------------------
    */

    if ($encrypted === false) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Store:
    |
    | Version + IV + Authentication Tag + Ciphertext
    |
    | Everything is Base64 encoded for database storage.
    |--------------------------------------------------------------------------
    */

    $combined = $iv . $tag . $encrypted;

    return $encryption_version . base64_encode($combined);
}


/*
|--------------------------------------------------------------------------
| Decrypt Data
|--------------------------------------------------------------------------
*/

function decrypt_data($data)
{
    global $gcm_key, $encryption_cipher, $encryption_version, $encryption_key;

    /*
    |--------------------------------------------------------------------------
    | Empty values do not need decryption.
    |--------------------------------------------------------------------------
    */

    if ($data === null || $data === "") {
        return $data;
    }


    /*
    |--------------------------------------------------------------------------
    | New AES-256-GCM encrypted data
    |--------------------------------------------------------------------------
    */

    if (strpos($data, $encryption_version) === 0) {

        /*
        |--------------------------------------------------------------------------
        | Remove the version prefix.
        |--------------------------------------------------------------------------
        */

        $encoded_data = substr(
            $data,
            strlen($encryption_version)
        );


        /*
        |--------------------------------------------------------------------------
        | Decode Base64 data.
        |--------------------------------------------------------------------------
        */

        $decoded = base64_decode(
            $encoded_data,
            true
        );

        if ($decoded === false) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | AES-256-GCM:
        |
        | IV = 12 bytes
        | Authentication Tag = 16 bytes
        |--------------------------------------------------------------------------
        */

        $iv_length = openssl_cipher_iv_length(
            $encryption_cipher
        );

        $tag_length = 16;


        /*
        |--------------------------------------------------------------------------
        | Make sure the encrypted data is long enough.
        |--------------------------------------------------------------------------
        */

        if (
            strlen($decoded) <=
            ($iv_length + $tag_length)
        ) {
            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | Extract IV.
        |--------------------------------------------------------------------------
        */

        $iv = substr(
            $decoded,
            0,
            $iv_length
        );


        /*
        |--------------------------------------------------------------------------
        | Extract authentication tag.
        |--------------------------------------------------------------------------
        */

        $tag = substr(
            $decoded,
            $iv_length,
            $tag_length
        );


        /*
        |--------------------------------------------------------------------------
        | Extract encrypted content.
        |--------------------------------------------------------------------------
        */

        $encrypted = substr(
            $decoded,
            $iv_length + $tag_length
        );


        /*
        |--------------------------------------------------------------------------
        | Decrypt and verify authentication tag.
        |--------------------------------------------------------------------------
        */

        $decrypted = openssl_decrypt(
            $encrypted,
            $encryption_cipher,
            $gcm_key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            ""
        );


        return $decrypted;
    }


    /*
    |--------------------------------------------------------------------------
    | Legacy AES-256-CBC Support
    |--------------------------------------------------------------------------
    | This allows records encrypted with the previous
    | encryption.php to continue working.
    |--------------------------------------------------------------------------
    */

    $legacy_cipher = "AES-256-CBC";

    $decoded = base64_decode(
        $data,
        true
    );


    /*
    |--------------------------------------------------------------------------
    | If it isn't valid Base64, it isn't encrypted data.
    |--------------------------------------------------------------------------
    */

    if ($decoded === false) {
        return false;
    }


    $legacy_iv_length = openssl_cipher_iv_length(
        $legacy_cipher
    );


    /*
    |--------------------------------------------------------------------------
    | Make sure the legacy data contains an IV.
    |--------------------------------------------------------------------------
    */

    if (
        strlen($decoded) <=
        $legacy_iv_length
    ) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | Extract the old IV.
    |--------------------------------------------------------------------------
    */

    $legacy_iv = substr(
        $decoded,
        0,
        $legacy_iv_length
    );


    /*
    |--------------------------------------------------------------------------
    | Extract the old encrypted content.
    |--------------------------------------------------------------------------
    */

    $legacy_encrypted = substr(
        $decoded,
        $legacy_iv_length
    );


    /*
    |--------------------------------------------------------------------------
    | Decrypt the old AES-256-CBC data using
    | the original key handling.
    |--------------------------------------------------------------------------
    */

    $legacy_decrypted = openssl_decrypt(
        $legacy_encrypted,
        $legacy_cipher,
        $encryption_key,
        OPENSSL_RAW_DATA,
        $legacy_iv
    );


    return $legacy_decrypted;
}

?>