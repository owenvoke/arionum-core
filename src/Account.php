<?php

namespace Arionum\Arionum;

/**
 * Class Account
 */
class Account
{
    /**
     * Insert the account into the database and update the public key if empty.
     * @param string $publicKey
     * @param string $block
     * @return void
     */
    public function add(string $publicKey, string $block): void
    {
        global $db;
        $id = $this->getAddress($publicKey);
        $bind = [":id" => $id, ":public_key" => $publicKey, ":block" => $block, ":public_key2" => $publicKey];
        $db->run(
            "INSERT INTO accounts
             SET id=:id, public_key=:public_key, block=:block, balance=0
             ON DUPLICATE KEY
             UPDATE public_key=if(public_key='',:public_key2,public_key)",
            $bind
        );
    }

    /**
     * Insert just the account without the public key.
     * @param string $id
     * @param string $block
     * @return void
     */
    public function addId(string $id, string $block): void
    {
        global $db;
        $bind = [":id" => $id, ":block" => $block];
        $db->run(
            "INSERT ignore INTO accounts
             SET id=:id, public_key='', block=:block, balance=0",
            $bind
        );
    }

    /**
     * Generate the account's address from the public key.
     * @param string $publicKey
     * @return string
     */
    public function getAddress(string $publicKey): string
    {
        if ($address = $this->isBrokenBlockWinner($publicKey)) {
            return $address;
        }

        // Hash 9 times in sha512 (binary) and encode with Base58
        for ($i = 0; $i < 9; $i++) {
            $publicKey = hash('sha512', $publicKey, true);
        }

        return base58Encode($publicKey);
    }

    /**
     * Check the ECDSA secp256k1 signature for a specific public key.
     * @param string $data
     * @param string $signature
     * @param string $publicKey
     * @return bool
     */
    public function checkSignature(string $data, string $signature, string $publicKey): bool
    {
        return ecVerify($data, $signature, $publicKey);
    }

    /**
     * Generate a new account and a public/private key pair.
     * @return array
     */
    public function generateAccount(): array
    {
        // Using secp256k1 curve for ECDSA
        $args = [
            "curve_name"       => "secp256k1",
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ];

        // Generate a new key pair
        $key1 = openssl_pkey_new($args);

        // Export the private key encoded as PEM
        openssl_pkey_export($key1, $pvkey);

        // Convert the PEM to a Base58 format
        $private_key = pemToCoin($pvkey);

        // Export the private key encoded as PEM
        $pub = openssl_pkey_get_details($key1);

        // Convert the PEM to a Base58 format
        $public_key = pemToCoin($pub['key']);

        // Generate the account's address based on the public key
        $address = $this->getAddress($public_key);
        return ["address" => $address, "public_key" => $public_key, "private_key" => $private_key];
    }

    /**
     * Check the validity of a Base58 encoded key.
     * At the moment, it only checks that the characters are Base58.
     * @param string $id
     * @return bool
     */
    public function validKey(string $id): bool
    {
        $chars = str_split("123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz");
        for ($i = 0; $i < strlen($id);
             $i++) {
            if (!in_array($id[$i], $chars)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check the validity of an address.
     * At the moment, it checks only that the characters are Base58 and the length is >=70 and <=128.
     * @param string $id
     * @return bool
     */
    public function valid(string $id): bool
    {
        if (strlen($id) < 70 || strlen($id) > 128) {
            return false;
        }
        $chars = str_split("123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz");
        for ($i = 0; $i < strlen($id);
             $i++) {
            if (!in_array($id[$i], $chars)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the balance of a specified address.
     * @param string $id
     * @return string
     */
    public function balance(string $id): string
    {
        global $db;
        $res = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $id]);
        if ($res === false) {
            $res = "0.00000000";
        }

        return number_format($res, 8, ".", "");
    }

    /**
     * Get the balance of a specified address, including any pending debits from the Mempool.
     * @param string $id
     * @return string
     */
    public function pendingBalance(string $id): string
    {
        global $db;
        $res = $db->single("SELECT balance FROM accounts WHERE id=:id", [":id" => $id]);
        if ($res === false) {
            $res = "0.00000000";
        }

        // If the original balance is 0, no mempool transactions are possible
        if ($res == "0.00000000") {
            return $res;
        }
        $mem = $db->single("SELECT SUM(val+fee) FROM mempool WHERE src=:id", [":id" => $id]);
        $rez = $res - $mem;
        return number_format($rez, 8, ".", "");
    }

    /**
     * Get transactions for a specific address.
     * @param string $id
     * @param int    $limit
     * @return array
     */
    public function getTransactions(string $id, int $limit = 100): array
    {
        global $db;
        $block = new Block();
        $current = $block->current();
        $public_key = $this->publicKey($id);
        $limit = intval($limit);
        if ($limit > 100 || $limit < 1) {
            $limit = 100;
        }
        $res = $db->run(
            "SELECT * FROM transactions WHERE dst=:dst or public_key=:src ORDER by height DESC LIMIT :limit",
            [":src" => $public_key, ":dst" => $id, ":limit" => $limit]
        );

        $transactions = [];
        foreach ($res as $x) {
            $trans = [
                "block"      => $x['block'],
                "height"     => $x['height'],
                "id"         => $x['id'],
                "dst"        => $x['dst'],
                "val"        => $x['val'],
                "fee"        => $x['fee'],
                "signature"  => $x['signature'],
                "message"    => $x['message'],
                "version"    => $x['version'],
                "date"       => $x['date'],
                "public_key" => $x['public_key'],
            ];
            $trans['src'] = $this->getAddress($x['public_key']);
            $trans['confirmations'] = $current['height'] - $x['height'];

            // version 0 -> reward transaction, version 1 -> normal transaction
            if ($x['version'] == 0) {
                $trans['type'] = "mining";
            } elseif ($x['version'] == 1) {
                if ($x['dst'] == $id) {
                    $trans['type'] = "credit";
                } else {
                    $trans['type'] = "debit";
                }
            } else {
                $trans['type'] = "other";
            }
            ksort($trans);
            $transactions[] = $trans;
        }

        return $transactions;
    }

    /**
     * Get Mempool transactions from a specific address.
     * @param string $id
     * @return array
     */
    public function getMempoolTransactions(string $id): array
    {
        global $db;
        $transactions = [];
        $res = $db->run(
            "SELECT * FROM mempool WHERE src=:src ORDER by height DESC LIMIT 100",
            [":src" => $id, ":dst" => $id]
        );
        foreach ($res as $x) {
            $trans = [
                "block"      => $x['block'],
                "height"     => $x['height'],
                "id"         => $x['id'],
                "src"        => $x['src'],
                "dst"        => $x['dst'],
                "val"        => $x['val'],
                "fee"        => $x['fee'],
                "signature"  => $x['signature'],
                "message"    => $x['message'],
                "version"    => $x['version'],
                "date"       => $x['date'],
                "public_key" => $x['public_key'],
            ];
            $trans['type'] = "mempool";

            // They are unconfirmed, so they will have -1 confirmations.
            $trans['confirmations'] = -1;

            ksort($trans);
            $transactions[] = $trans;
        }
        return $transactions;
    }

    /**
     * Return the public key for a specific account.
     * @param string $id
     * @return string
     */
    public function publicKey(string $id): string
    {
        global $db;
        $res = $db->single("SELECT public_key FROM accounts WHERE id=:id", [":id" => $id]);
        return $res;
    }

    /**
     * Check if the public key is in a list of broken block winner addresses.
     * These were missing the first 0 bytes from the address.
     * @param string $hash
     * @return null|string
     */
    private function isBrokenBlockWinner(string $hash)
    {
        // phpcs:disable Generic.Files.LineLength
        $brokenAddresses = [
            'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCwCpspGFGQSaF9yVGLamBgymdf8M7FafghmP3oPzQb3W4PZsZApVa41uQrrHRVBH5p9bdoz7c6XeRQHK2TkzWR45e' => '22SoB29oyq2JhMxtBbesL7JioEYytyC6VeFmzvBH6fRQrueSvyZfEXR5oR7ajSQ9mLERn6JKU85EAbVDNChke32',
            'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCzbRyyz5oDNDKhk5jyjg4caRjkbqegMZMrUkuBjVMuYcVfPyc3aKuLmPHS4QEDjCrNGks7Z5oPxwv4yXSv7WJnkbL' => 'AoFnv3SLujrJSa2J7FDTADGD7Eb9kv3KtNAp7YVYQEUPcLE6cC6nLvvhVqcVnRLYF5BFF38C1DyunUtmfJBhyU',
            'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyradtFFJoaYB4QdcXyBGSXjiASMMnofsT4f5ZNaxTnNDJt91ubemn3LzgKrfQh8CBpqaphkVNoRLub2ctdMnrzG1' => 'RncXQuc7S7aWkvTUJSHEFvYoV3ntAf7bfxEHjSiZNBvQV37MzZtg44L7GAV7szZ3uV8qWqikBewa3piZMqzBqm',
            'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyjKMBY4ihhJ2G25EVezg7KnoCBVbhdvWfqzNA4LC5R7wgu3VNfJgvqkCq9sKKZcCoCpX6Qr9cN882MoXsfGTvZoj' => 'Rq53oLzpCrb4BdJZ1jqQ2zsixV2ukxVdM4H9uvUhCGJCz1q2wagvuXV4hC6UVwK7HqAt1FenukzhVXgzyG1y32',
        ];
        // phpcs:enable

        if (key_exists($hash, $brokenAddresses)) {
            return $brokenAddresses[$hash];
        }

        return null;
    }
}
